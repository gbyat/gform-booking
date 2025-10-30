<?php

/**
 * Form Fields class for GF Booking plugin
 *
 * @package GFormBooking
 */

namespace GFormBooking;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Form_Fields
 */
class Form_Fields
{

    /**
     * Display calendar booking field
     *
     * @param string $value Current value.
     * @param int    $service_id Service ID (default/first service).
     * @param array  $available_service_ids Array of available service IDs (optional).
     * @return string HTML output.
     */
    public static function render_calendar_field($value = '', $service_id = 0, $available_service_ids = array())
    {
        // Get all services.
        $all_services = Service::get_all();

        // If available service IDs provided, filter services.
        if (!empty($available_service_ids) && is_array($available_service_ids)) {
            $all_services = array_filter($all_services, function ($service) use ($available_service_ids) {
                return in_array($service['id'], $available_service_ids);
            });
        }

        // If no service_id provided and there's only one service, use that.
        if (empty($service_id) && count($all_services) === 1) {
            $service_id = $all_services[0]['id'];
        }

        // If still no service_id or service doesn't exist, show message.
        if (empty($service_id)) {
            ob_start();
            echo '<p>' . esc_html__('No booking service available. Please configure a service first.', 'gform-booking') . '</p>';
            return ob_get_clean();
        }

        $calendar = new Calendar($service_id);
        $service = new Service($service_id);
        $settings = $service->get('settings');
        $calendar_type = isset($settings['calendar_type']) ? $settings['calendar_type'] : 'simple';
        $allow_multiple_slots = isset($settings['allow_multiple_slots']) ? $settings['allow_multiple_slots'] : false;

        // Calculate min/max date based on settings.
        $min_date = date('Y-m-d', strtotime('+1 day')); // Default earliest: tomorrow
        $min_booking_type = isset($settings['min_booking_type']) ? $settings['min_booking_type'] : 'days_ahead';
        if ($min_booking_type === 'fixed_date') {
            $min_date = isset($settings['min_booking_date']) ? $settings['min_booking_date'] : $min_date;
        } elseif ($min_booking_type === 'days_ahead') {
            $min_days = isset($settings['min_booking_days']) ? absint($settings['min_booking_days']) : 1;
            $min_date = date('Y-m-d', strtotime("+{$min_days} days"));
        }

        // Calculate max date based on settings.
        $max_date = date('Y-m-d', strtotime('+60 days')); // Default.
        $max_booking_type = isset($settings['max_booking_type']) ? $settings['max_booking_type'] : 'days_ahead';
        if ($max_booking_type === 'fixed_date') {
            $max_date = isset($settings['max_booking_date']) ? $settings['max_booking_date'] : $max_date;
        } elseif ($max_booking_type === 'days_ahead') {
            $max_days = isset($settings['max_booking_days']) ? absint($settings['max_booking_days']) : 60;
            $max_date = date('Y-m-d', strtotime("+{$max_days} days"));
        }

        // Determine which month to display initially: use min_date if in current month or future.
        $min_date_obj = new \DateTime($min_date);
        $today = new \DateTime();
        $calendar_year = (int) $today->format('Y');
        $calendar_month = (int) $today->format('m');

        // If min_date is in the future, start the calendar on that month.
        if ($min_date_obj > $today) {
            $calendar_year = (int) $min_date_obj->format('Y');
            $calendar_month = (int) $min_date_obj->format('m');
        }

        ob_start();

        // Show service selector only if multiple services exist.
        if (count($all_services) > 1) {
?>
            <div class="gf-booking-service-selector" style="margin-bottom: 20px;">
                <select id="gf-booking-service-select" name="gf_booking_service" class="gf-booking-service-select">
                    <option value=""><?php esc_html_e('-- Select a Service --', 'gform-booking'); ?></option>
                    <?php foreach ($all_services as $service_option): ?>
                        <option value="<?php echo esc_attr($service_option['id']); ?>" <?php selected($service_id, $service_option['id']); ?>>
                            <?php echo esc_html($service_option['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php
        }

        if ($calendar_type === 'month') {
            // Show month calendar view.
            $month_data = $calendar->get_month_calendar($calendar_year, $calendar_month);
        ?>
            <div class="gf-booking-calendar gf-booking-month-calendar" data-service-id="<?php echo esc_attr($service_id); ?>" data-min-date="<?php echo esc_attr($min_date); ?>" data-max-date="<?php echo esc_attr($max_date); ?>" data-allow-multiple-slots="<?php echo $allow_multiple_slots ? '1' : '0'; ?>">

                <div class="gf-booking-month-nav">
                    <button type="button" class="gf-booking-prev-month">&larr; <?php esc_html_e('Previous', 'gform-booking'); ?></button>
                    <h4 class="gf-booking-current-month">
                        <?php echo esc_html(date_i18n('F Y', mktime(0, 0, 0, $calendar_month, 1, $calendar_year))); ?>
                    </h4>
                    <button type="button" class="gf-booking-next-month"><?php esc_html_e('Next', 'gform-booking'); ?> &rarr;</button>
                </div>

                <table class="gf-booking-calendar-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Mon', 'gform-booking'); ?></th>
                            <th><?php esc_html_e('Tue', 'gform-booking'); ?></th>
                            <th><?php esc_html_e('Wed', 'gform-booking'); ?></th>
                            <th><?php esc_html_e('Thu', 'gform-booking'); ?></th>
                            <th><?php esc_html_e('Fri', 'gform-booking'); ?></th>
                            <th><?php esc_html_e('Sat', 'gform-booking'); ?></th>
                            <th><?php esc_html_e('Sun', 'gform-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($month_data as $week): ?>
                            <tr>
                                <?php for ($i = 0; $i < 7; $i++): ?>
                                    <?php if (isset($week[$i]) && $week[$i]): ?>
                                        <?php
                                        $day = $week[$i];
                                        $slots_count = count($day['slots']);
                                        $available_class = $slots_count > 0 ? 'available' : 'unavailable';
                                        ?>
                                        <td class="gf-booking-day <?php echo esc_attr($available_class); ?>"
                                            data-date="<?php echo esc_attr($day['date']); ?>"
                                            data-slots="<?php echo esc_attr(json_encode($day['slots'])); ?>">
                                            <div class="gf-booking-day-number"><?php echo esc_html($day['day']); ?></div>
                                            <div class="gf-booking-slots-count">
                                                <?php printf(esc_html(_n('%d slot', '%d slots', $slots_count, 'gform-booking')), $slots_count); ?>
                                            </div>
                                        </td>
                                    <?php else: ?>
                                        <td></td>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="gf-booking-selected-info" style="display:none;">
                    <p><?php esc_html_e('Selected date:', 'gform-booking'); ?> <span class="selected-date"></span></p>
                    <div class="gf-booking-time-slots">
                        <label><?php esc_html_e('Available times:', 'gform-booking'); ?></label>
                        <div class="gf-booking-slots"></div>
                    </div>
                </div>
            </div>
        <?php
        } else {
            // Simple date picker.
        ?>
            <div class="gf-booking-calendar" data-service-id="<?php echo esc_attr($service_id); ?>" data-min-date="<?php echo esc_attr($min_date); ?>" data-max-date="<?php echo esc_attr($max_date); ?>" data-allow-multiple-slots="<?php echo $allow_multiple_slots ? '1' : '0'; ?>">
                <div class="gf-booking-date-picker">
                    <input type="date" class="gf-booking-date" name="appointment_date" min="<?php echo esc_attr($min_date); ?>" max="<?php echo esc_attr($max_date); ?>" required>
                </div>
                <div class="gf-booking-time-slots" style="display:none;">
                    <label><?php esc_html_e('Available times:', 'gform-booking'); ?></label>
                    <div class="gf-booking-slots"></div>
                </div>
            </div>
<?php
        }

        return ob_get_clean();
    }
}
