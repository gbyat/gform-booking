<?php

/**
 * Admin class for GF Booking plugin
 *
 * @package GFormBooking
 */

namespace GFormBooking;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Admin
 */
class Admin
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('GF Booking', 'gform-booking'),
            __('GF Booking', 'gform-booking'),
            'manage_options',
            'gform-booking',
            array($this, 'render_dashboard'),
            'dashicons-calendar-alt',
            30
        );

        add_submenu_page(
            'gform-booking',
            __('Calendar', 'gform-booking'),
            __('Calendar', 'gform-booking'),
            'manage_options',
            'gform-booking',
            array($this, 'render_dashboard')
        );

        add_submenu_page(
            'gform-booking',
            __('Services', 'gform-booking'),
            __('Services', 'gform-booking'),
            'manage_options',
            'gform-booking-services',
            array($this, 'render_services')
        );

        add_submenu_page(
            'gform-booking',
            __('Appointments', 'gform-booking'),
            __('Appointments', 'gform-booking'),
            'manage_options',
            'gform-booking-appointments',
            array($this, 'render_appointments')
        );
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard()
    {
?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="gf-booking-dashboard">
                <p><?php esc_html_e('Welcome to GF Booking. Manage your appointment services and view upcoming appointments.', 'gform-booking'); ?></p>

                <div class="gf-booking-stats">
                    <?php
                    $services_count = count(Service::get_all());
                    global $wpdb;
                    $table = $wpdb->prefix . 'gf_booking_appointments';
                    $today = current_time('Y-m-d');
                    $upcoming_count = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM $table WHERE appointment_date >= %s AND status = 'confirmed'",
                            $today
                        )
                    );
                    ?>
                    <div class="stat-box">
                        <h3><?php echo esc_html($services_count); ?></h3>
                        <p><?php esc_html_e('Services', 'gform-booking'); ?></p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo esc_html($upcoming_count); ?></h3>
                        <p><?php esc_html_e('Upcoming Appointments', 'gform-booking'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Render services page
     */
    public function render_services()
    {
        // Check if we're adding or editing.
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $service_id = isset($_GET['service_id']) ? absint($_GET['service_id']) : 0;

        if ($action === 'add' || $action === 'edit') {
            $this->render_service_form($action, $service_id);
            return;
        }

        // Show list view.
    ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <p class="submit">
                <a href="<?php echo esc_url(add_query_arg('action', 'add', admin_url('admin.php?page=gform-booking-services'))); ?>" class="button button-primary">
                    <?php esc_html_e('Add New Service', 'gform-booking'); ?>
                </a>
            </p>

            <div class="gf-booking-services">
                <?php
                $services = Service::get_all();

                if (empty($services)) {
                    echo '<p>' . esc_html__('No services configured yet.', 'gform-booking') . '</p>';
                } else {
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr><th>' . esc_html__('Name', 'gform-booking') . '</th><th>' . esc_html__('Schedule', 'gform-booking') . '</th><th>' . esc_html__('Actions', 'gform-booking') . '</th></tr></thead>';
                    echo '<tbody>';

                    foreach ($services as $service) {
                        // Decode weekdays (JSON or fallback to unserialize for old data).
                        $weekdays = json_decode($service['weekdays'], true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $weekdays = maybe_unserialize($service['weekdays']);
                        }
                        $weekday_names = array();
                        $day_names = array(1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun');

                        if (is_array($weekdays)) {
                            foreach ($weekdays as $day) {
                                if (isset($day_names[$day])) {
                                    $weekday_names[] = $day_names[$day];
                                }
                            }
                        }

                        $schedule = implode(', ', $weekday_names);
                        if (empty($schedule)) {
                            $schedule = __('All days', 'gform-booking');
                        }
                        $schedule .= ' | ' . date('g:i A', strtotime($service['start_time'])) . ' - ' . date('g:i A', strtotime($service['end_time']));

                        echo '<tr>';
                        echo '<td><strong>' . esc_html($service['name']) . '</strong></td>';
                        echo '<td>' . esc_html($schedule) . '</td>';
                        echo '<td>';
                        echo '<a href="' . esc_url(add_query_arg(array('action' => 'edit', 'service_id' => $service['id']))) . '">' . esc_html__('Edit', 'gform-booking') . '</a> | ';
                        echo '<a href="' . esc_url(add_query_arg(array('action' => 'delete', 'service_id' => $service['id']))) . '" onclick="return confirm(\'' . esc_js(__('Are you sure?', 'gform-booking')) . '\')">' . esc_html__('Delete', 'gform-booking') . '</a>';
                        echo '</td>';
                        echo '</tr>';
                    }

                    echo '</tbody></table>';
                }
                ?>
            </div>
        </div>
    <?php
    }

    /**
     * Render service form
     */
    private function render_service_form($action, $service_id = 0)
    {
        $service = new Service($service_id);
        $form_title = $action === 'add' ? __('Add New Service', 'gform-booking') : __('Edit Service', 'gform-booking');

        // Get service data or defaults.
        $name = $service->exists() ? $service->get('name') : '';
        $description = $service->exists() ? $service->get('description') : '';

        // Get weekdays (already decoded in Service class).
        $weekdays = $service->exists() ? $service->get('weekdays') : array();
        if (!is_array($weekdays)) {
            $weekdays = array();
        }

        $start_time = $service->exists() ? $service->get('start_time') : '09:00:00';
        $end_time = $service->exists() ? $service->get('end_time') : '17:00:00';
        $slot_duration = $service->exists() ? $service->get('slot_duration') : 30;
        $buffer_time = $service->exists() ? $service->get('buffer_time') : 0;

        // Get settings (already decoded in Service class).
        $settings = $service->exists() ? $service->get('settings') : array();
        if (!is_array($settings)) {
            $settings = array();
        }
        $slot_type = isset($settings['slot_type']) ? $settings['slot_type'] : 'time';
        $calendar_type = isset($settings['calendar_type']) ? $settings['calendar_type'] : 'simple';

        // Half day settings.
        $half_day_morning = isset($settings['half_day_morning']) ? $settings['half_day_morning'] : false;
        $half_day_afternoon = isset($settings['half_day_afternoon']) ? $settings['half_day_afternoon'] : false;
        $half_day_morning_start = isset($settings['half_day_morning_start']) ? $settings['half_day_morning_start'] : '08:00:00';
        $half_day_morning_end = isset($settings['half_day_morning_end']) ? $settings['half_day_morning_end'] : '12:00:00';
        $half_day_afternoon_start = isset($settings['half_day_afternoon_start']) ? $settings['half_day_afternoon_start'] : '13:00:00';
        $half_day_afternoon_end = isset($settings['half_day_afternoon_end']) ? $settings['half_day_afternoon_end'] : '17:00:00';

        // Max booking date settings.
        $max_booking_type = isset($settings['max_booking_type']) ? $settings['max_booking_type'] : 'days_ahead';
        $max_booking_days = isset($settings['max_booking_days']) ? $settings['max_booking_days'] : 60;
        $max_booking_date = isset($settings['max_booking_date']) ? $settings['max_booking_date'] : '';

        // Excluded dates settings.
        $excluded_dates = isset($settings['excluded_dates']) ? $settings['excluded_dates'] : array();

        // Notify user setting.
        $notify_user_id = isset($settings['notify_user_id']) ? $settings['notify_user_id'] : 0;

        // Email sender settings.
        $email_from_name = isset($settings['email_from_name']) ? $settings['email_from_name'] : get_bloginfo('name');
        $email_from_email = isset($settings['email_from_email']) ? $settings['email_from_email'] : get_option('admin_email');
    ?>
        <div class="wrap">
            <h1><?php echo esc_html($form_title); ?></h1>

            <?php
            // Show success/error messages.
            if (isset($_GET['updated']) && $_GET['updated'] == '1') {
                echo '<div class="notice notice-success"><p>' . esc_html__('Service updated successfully.', 'gform-booking') . '</p></div>';
            }
            if (isset($_GET['error']) && $_GET['error'] == '1') {
                echo '<div class="notice notice-error"><p>' . esc_html__('Error saving service. Please try again.', 'gform-booking') . '</p></div>';
            }
            ?>

            <a href="<?php echo esc_url(admin_url('admin.php?page=gform-booking-services')); ?>">&larr; <?php esc_html_e('Back to Services', 'gform-booking'); ?></a>

            <form method="post" action="" class="gf-booking-service-form">
                <?php wp_nonce_field('save_service', 'service_nonce'); ?>
                <?php if ($service_id) : ?>
                    <input type="hidden" name="service_id" value="<?php echo esc_attr($service_id); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="name"><?php esc_html_e('Service Name', 'gform-booking'); ?></label></th>
                        <td><input type="text" id="name" name="name" value="<?php echo esc_attr($name); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="description"><?php esc_html_e('Description', 'gform-booking'); ?></label></th>
                        <td><textarea id="description" name="description" rows="3" class="large-text"><?php echo esc_textarea($description); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="notify_user_id"><?php esc_html_e('Notify User', 'gform-booking'); ?></label></th>
                        <td>
                            <?php
                            $users = get_users(array('orderby' => 'display_name'));
                            ?>
                            <select id="notify_user_id" name="notify_user_id">
                                <option value="0" <?php selected($notify_user_id, 0); ?>><?php esc_html_e('Site Admin', 'gform-booking'); ?></option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($notify_user_id, $user->ID); ?>>
                                        <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('This user will be notified when bookings are made or cancelled for this service.', 'gform-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="email_from_name"><?php esc_html_e('Email Sender Name', 'gform-booking'); ?></label></th>
                        <td>
                            <input type="text" id="email_from_name" name="email_from_name" value="<?php echo esc_attr($email_from_name); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('The name that appears as sender in confirmation emails.', 'gform-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="email_from_email"><?php esc_html_e('Email Sender Address', 'gform-booking'); ?></label></th>
                        <td>
                            <input type="email" id="email_from_email" name="email_from_email" value="<?php echo esc_attr($email_from_email); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('The email address that appears as sender in confirmation emails.', 'gform-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('Available Days', 'gform-booking'); ?></label></th>
                        <td>
                            <?php
                            $day_names = array(
                                1 => __('Monday', 'gform-booking'),
                                2 => __('Tuesday', 'gform-booking'),
                                3 => __('Wednesday', 'gform-booking'),
                                4 => __('Thursday', 'gform-booking'),
                                5 => __('Friday', 'gform-booking'),
                                6 => __('Saturday', 'gform-booking'),
                                7 => __('Sunday', 'gform-booking'),
                            );
                            foreach ($day_names as $day_num => $day_name) :
                                // Check if day number is in weekdays array (compare as strings).
                                $checked = in_array($day_num, (array)$weekdays, false) ? 'checked' : '';
                            ?>
                                <label><input type="checkbox" name="weekdays[]" value="<?php echo esc_attr($day_num); ?>" <?php echo $checked; ?>> <?php echo esc_html($day_name); ?></label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="start_time"><?php esc_html_e('Start Time', 'gform-booking'); ?></label></th>
                        <td><input type="time" id="start_time" name="start_time" value="<?php echo esc_attr($start_time); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="end_time"><?php esc_html_e('End Time', 'gform-booking'); ?></label></th>
                        <td><input type="time" id="end_time" name="end_time" value="<?php echo esc_attr($end_time); ?>" required></td>
                    </tr>
                    <tr class="time-slot-settings">
                        <th scope="row"><label><?php esc_html_e('Lunch Break', 'gform-booking'); ?></label></th>
                        <td>
                            <?php
                            $has_lunch_break = isset($settings['has_lunch_break']) ? $settings['has_lunch_break'] : false;
                            $lunch_break_start = isset($settings['lunch_break_start']) ? $settings['lunch_break_start'] : '12:00:00';
                            $lunch_break_end = isset($settings['lunch_break_end']) ? $settings['lunch_break_end'] : '13:00:00';
                            ?>
                            <label><input type="checkbox" name="has_lunch_break" id="has_lunch_break" value="1" <?php checked($has_lunch_break); ?>> <?php esc_html_e('Enable lunch break (no slots during this time)', 'gform-booking'); ?></label><br>
                            <div id="lunch-break-times" style="margin-top: 10px; <?php echo !$has_lunch_break ? 'display:none;' : ''; ?>">
                                <label><?php esc_html_e('From:', 'gform-booking'); ?> <input type="time" name="lunch_break_start" value="<?php echo esc_attr($lunch_break_start); ?>" class="small-text"></label>
                                <label style="margin-left: 10px;"><?php esc_html_e('To:', 'gform-booking'); ?> <input type="time" name="lunch_break_end" value="<?php echo esc_attr($lunch_break_end); ?>" class="small-text"></label>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="slot_type"><?php esc_html_e('Slot Type', 'gform-booking'); ?></label></th>
                        <td>
                            <select id="slot_type" name="slot_type">
                                <option value="time" <?php selected($slot_type, 'time'); ?>><?php esc_html_e('Time-based Slots', 'gform-booking'); ?></option>
                                <option value="half_day" <?php selected($slot_type, 'half_day'); ?>><?php esc_html_e('Half Day', 'gform-booking'); ?></option>
                                <option value="full_day" <?php selected($slot_type, 'full_day'); ?>><?php esc_html_e('Full Day', 'gform-booking'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr class="time-slot-settings">
                        <th scope="row"><label for="slot_duration"><?php esc_html_e('Slot Duration (minutes)', 'gform-booking'); ?></label></th>
                        <td><input type="number" id="slot_duration" name="slot_duration" value="<?php echo esc_attr($slot_duration); ?>" min="5" step="5"></td>
                    </tr>
                    <tr class="time-slot-settings">
                        <th scope="row"><label for="buffer_time"><?php esc_html_e('Buffer Time (minutes)', 'gform-booking'); ?></label></th>
                        <td><input type="number" id="buffer_time" name="buffer_time" value="<?php echo esc_attr($buffer_time); ?>" min="0"></td>
                    </tr>
                    <tr class="half-day-settings" style="display:none;">
                        <th scope="row"><label><?php esc_html_e('Half Day Options', 'gform-booking'); ?></label></th>
                        <td>
                            <label><input type="checkbox" name="half_day_morning" value="1" <?php checked($half_day_morning); ?>> <?php esc_html_e('Morning Session', 'gform-booking'); ?></label><br>
                            <label><?php esc_html_e('Morning:', 'gform-booking'); ?> <input type="time" name="half_day_morning_start" value="<?php echo esc_attr($half_day_morning_start); ?>" class="small-text"> - <input type="time" name="half_day_morning_end" value="<?php echo esc_attr($half_day_morning_end); ?>" class="small-text"></label><br>
                            <br>
                            <label><input type="checkbox" name="half_day_afternoon" value="1" <?php checked($half_day_afternoon); ?>> <?php esc_html_e('Afternoon Session', 'gform-booking'); ?></label><br>
                            <label><?php esc_html_e('Afternoon:', 'gform-booking'); ?> <input type="time" name="half_day_afternoon_start" value="<?php echo esc_attr($half_day_afternoon_start); ?>" class="small-text"> - <input type="time" name="half_day_afternoon_end" value="<?php echo esc_attr($half_day_afternoon_end); ?>" class="small-text"></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="max_participants"><?php esc_html_e('Max Participants per Slot', 'gform-booking'); ?></label></th>
                        <td>
                            <?php
                            $max_participants = isset($settings['max_participants']) ? $settings['max_participants'] : 1;
                            ?>
                            <input type="number" id="max_participants" name="max_participants" value="<?php echo esc_attr($max_participants); ?>" min="1" step="1">
                            <p class="description"><?php esc_html_e('Maximum number of people that can book the same time slot. Set to 1 for exclusive slots.', 'gform-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="calendar_type"><?php esc_html_e('Calendar View', 'gform-booking'); ?></label></th>
                        <td>
                            <select id="calendar_type" name="calendar_type">
                                <option value="simple" <?php selected($calendar_type, 'simple'); ?>><?php esc_html_e('Simple Date Picker', 'gform-booking'); ?></option>
                                <option value="month" <?php selected($calendar_type, 'month'); ?>><?php esc_html_e('Month Calendar', 'gform-booking'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="max_booking_type"><?php esc_html_e('Max Booking Date', 'gform-booking'); ?></label></th>
                        <td>
                            <select id="max_booking_type" name="max_booking_type">
                                <option value="days_ahead" <?php selected($max_booking_type, 'days_ahead'); ?>><?php esc_html_e('Days Ahead', 'gform-booking'); ?></option>
                                <option value="fixed_date" <?php selected($max_booking_type, 'fixed_date'); ?>><?php esc_html_e('Fixed Date', 'gform-booking'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr class="max-booking-days-setting">
                        <th scope="row"><label for="max_booking_days"><?php esc_html_e('Days Ahead', 'gform-booking'); ?></label></th>
                        <td><input type="number" id="max_booking_days" name="max_booking_days" value="<?php echo esc_attr($max_booking_days); ?>" min="1" step="1"></td>
                    </tr>
                    <tr class="max-booking-date-setting" style="display:none;">
                        <th scope="row"><label for="max_booking_date"><?php esc_html_e('Fixed End Date', 'gform-booking'); ?></label></th>
                        <td><input type="date" id="max_booking_date" name="max_booking_date" value="<?php echo esc_attr($max_booking_date); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="excluded_dates"><?php esc_html_e('Excluded Dates', 'gform-booking'); ?></label></th>
                        <td>
                            <div class="gf-booking-excluded-dates">
                                <div id="excluded-dates-list" class="excluded-dates-list">
                                    <?php if (!empty($excluded_dates)) : ?>
                                        <?php foreach ($excluded_dates as $date) : ?>
                                            <div class="excluded-date-item" data-date="<?php echo esc_attr($date); ?>">
                                                <span><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($date))); ?></span>
                                                <button type="button" class="button remove-date">&times;</button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <input type="date" id="excluded_date_picker" class="excluded-date-picker" />
                                <button type="button" id="add-excluded-date" class="button"><?php esc_html_e('Add Date', 'gform-booking'); ?></button>
                                <p class="description"><?php esc_html_e('Select specific dates to block (e.g., holidays).', 'gform-booking'); ?></p>
                                <input type="hidden" name="excluded_dates" id="excluded_dates_field" value="<?php echo esc_attr(implode(',', $excluded_dates)); ?>">
                            </div>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="save_service" class="button button-primary" value="<?php esc_attr_e('Save Service', 'gform-booking'); ?>">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gform-booking-services')); ?>" class="button"><?php esc_html_e('Cancel', 'gform-booking'); ?></a>
                </p>
            </form>
        </div>

        <script>
            jQuery(document).ready(function($) {
                function toggleSlotSettings() {
                    var slotType = $('#slot_type').val();
                    if (slotType === 'half_day') {
                        $('.time-slot-settings').hide();
                        $('.half-day-settings').show();
                    } else if (slotType === 'full_day') {
                        $('.time-slot-settings').hide();
                        $('.half-day-settings').hide();
                    } else {
                        $('.time-slot-settings').show();
                        $('.half-day-settings').hide();
                    }
                }

                function toggleMaxBookingSettings() {
                    var maxType = $('#max_booking_type').val();
                    if (maxType === 'fixed_date') {
                        $('.max-booking-days-setting').hide();
                        $('.max-booking-date-setting').show();
                    } else {
                        $('.max-booking-days-setting').show();
                        $('.max-booking-date-setting').hide();
                    }
                }

                // Handle excluded dates.
                $('#add-excluded-date').on('click', function() {
                    var date = $('#excluded_date_picker').val();
                    if (!date) {
                        alert('<?php echo esc_js(__('Please select a date first.', 'gform-booking')); ?>');
                        return;
                    }

                    // Check if date already exists.
                    if ($('.excluded-date-item[data-date="' + date + '"]').length > 0) {
                        alert('<?php echo esc_js(__('This date is already excluded.', 'gform-booking')); ?>');
                        return;
                    }

                    // Add to list.
                    var dateObj = new Date(date + 'T00:00:00');
                    var locale = '<?php echo strtok(get_locale(), '_'); ?>'; // Get only language code (e.g., 'de' from 'de_DE_formal')
                    var formattedDate = dateObj.toLocaleDateString(locale, {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                    var html = '<div class="excluded-date-item" data-date="' + date + '">' +
                        '<span>' + formattedDate + '</span>' +
                        '<button type="button" class="button remove-date">&times;</button>' +
                        '</div>';
                    $('#excluded-dates-list').append(html);

                    updateExcludedDatesField();
                    $('#excluded_date_picker').val('');
                });

                // Handle remove date.
                $(document).on('click', '.remove-date', function() {
                    $(this).closest('.excluded-date-item').remove();
                    updateExcludedDatesField();
                });

                function updateExcludedDatesField() {
                    var dates = [];
                    $('.excluded-date-item').each(function() {
                        dates.push($(this).data('date'));
                    });
                    $('#excluded_dates_field').val(dates.join(','));
                }

                // Handle lunch break toggle.
                function toggleLunchBreak() {
                    if ($('#has_lunch_break').is(':checked')) {
                        $('#lunch-break-times').show();
                    } else {
                        $('#lunch-break-times').hide();
                    }
                }

                $('#has_lunch_break').on('change', toggleLunchBreak);
                $('#slot_type').on('change', toggleSlotSettings);
                $('#max_booking_type').on('change', toggleMaxBookingSettings);
                toggleSlotSettings(); // Call on page load.
                toggleMaxBookingSettings(); // Call on page load.
                toggleLunchBreak(); // Call on page load.
            });
        </script>

        <style>
            .excluded-date-item {
                display: inline-block;
                background: #f0f0f0;
                padding: 5px 10px;
                margin: 5px;
                border-radius: 3px;
            }

            .excluded-date-item button {
                margin-left: 5px;
                padding: 0 5px;
                height: auto;
                line-height: 1;
            }

            .excluded-dates-list {
                margin-bottom: 10px;
            }
        </style>
    <?php
    }

    /**
     * Render appointments page
     */
    public function render_appointments()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'gf_booking_appointments';

        // Get filter status if set.
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        // Build query.
        $query = "SELECT a.*, s.name as service_name 
            FROM $table a 
            LEFT JOIN {$wpdb->prefix}gf_booking_services s ON a.service_id = s.id ";

        if ($status_filter && $status_filter !== 'past') {
            $query .= $wpdb->prepare("WHERE a.status = %s ", $status_filter);
        }

        $query .= "ORDER BY a.created_at DESC, a.appointment_date ASC, a.start_time ASC LIMIT 100";

        $all_appointments = $wpdb->get_results($query, ARRAY_A);

        // Filter and mark past appointments in PHP.
        $today = current_time('Y-m-d');
        $now = current_time('timestamp');
        $appointments = array();

        foreach ($all_appointments as $appointment) {
            // Calculate if appointment is in the past.
            $appointment_timestamp = strtotime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
            $is_past = $appointment_timestamp < $now;

            // Apply "past" filter.
            if ($status_filter === 'past' && !$is_past) {
                continue;
            }
            if ($status_filter !== 'past' && $status_filter !== '' && $is_past) {
                continue;
            }

            // Add display_status.
            $appointment['display_status'] = $is_past ? 'past' : $appointment['status'];
            $appointments[] = $appointment;
        }

    ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <!-- Status filter -->
            <div class="gf-booking-filter" style="margin-bottom: 20px;">
                <label><?php esc_html_e('Filter by status:', 'gform-booking'); ?></label>
                <select onchange="window.location.href='<?php echo esc_url(add_query_arg('status', 'STATUS_VALUE')); ?>'.replace('STATUS_VALUE', this.value)">
                    <option value=""><?php esc_html_e('All', 'gform-booking'); ?></option>
                    <option value="confirmed" <?php selected($status_filter, 'confirmed'); ?>><?php esc_html_e('Confirmed', 'gform-booking'); ?></option>
                    <option value="past" <?php selected($status_filter, 'past'); ?>><?php esc_html_e('Past', 'gform-booking'); ?></option>
                    <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>><?php esc_html_e('Cancelled', 'gform-booking'); ?></option>
                </select>
            </div>

            <div class="gf-booking-appointments">
                <?php if (empty($appointments)) : ?>
                    <p><?php esc_html_e('No appointments found.', 'gform-booking'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Appointment Date & Time', 'gform-booking'); ?></th>
                                <th><?php esc_html_e('Booking Date', 'gform-booking'); ?></th>
                                <th><?php esc_html_e('Customer', 'gform-booking'); ?></th>
                                <th><?php esc_html_e('Contact', 'gform-booking'); ?></th>
                                <th><?php esc_html_e('Service', 'gform-booking'); ?></th>
                                <th><?php esc_html_e('Status', 'gform-booking'); ?></th>
                                <th><?php esc_html_e('Actions', 'gform-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $date_format = get_option('date_format');
                            $time_format = get_option('time_format');

                            foreach ($appointments as $appointment) {
                                $date_time = strtotime($appointment['appointment_date']);
                                $start_time = strtotime($appointment['start_time']);
                                $end_time = strtotime($appointment['end_time']);
                                $created_at = strtotime($appointment['created_at']);
                                $display_status = isset($appointment['display_status']) ? $appointment['display_status'] : $appointment['status'];

                                echo '<tr>';
                                echo '<td>' . esc_html(date_i18n($date_format, $date_time)) . '<br>';
                                echo '<strong>' . esc_html(date_i18n($time_format, $start_time)) . ' - ' . esc_html(date_i18n($time_format, $end_time)) . '</strong></td>';
                                echo '<td><small>' . esc_html(date_i18n($date_format . ' ' . $time_format, $created_at)) . '</small></td>';
                                echo '<td>' . esc_html($appointment['customer_name']) . '</td>';
                                echo '<td><small>' . esc_html($appointment['customer_email']) . '</small>';
                                if (!empty($appointment['customer_phone'])) {
                                    echo '<br><small>' . esc_html($appointment['customer_phone']) . '</small>';
                                }
                                echo '</td>';
                                echo '<td>' . esc_html($appointment['service_name'] ?? __('N/A', 'gform-booking')) . '</td>';
                                echo '<td><span class="status-' . esc_attr($display_status) . '">' . esc_html(ucfirst($display_status)) . '</span></td>';
                                echo '<td>';

                                // Actions based on status.
                                if ($appointment['status'] === 'confirmed') {
                                    echo '<a href="' . esc_url(wp_nonce_url(add_query_arg(array('action' => 'cancel', 'appointment_id' => $appointment['id'])), 'cancel_appointment')) . '" class="button button-small">' . esc_html__('Cancel', 'gform-booking') . '</a> ';
                                }

                                echo '<a href="' . esc_url(wp_nonce_url(add_query_arg(array('action' => 'delete', 'appointment_id' => $appointment['id'])), 'delete_appointment')) . '" class="button button-small button-link-delete" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this appointment?', 'gform-booking')) . '\');">' . esc_html__('Delete', 'gform-booking') . '</a>';
                                echo '</td>';
                                echo '</tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
<?php
    }

    /**
     * Handle admin actions
     */
    public function handle_admin_actions()
    {
        if (! isset($_GET['page']) || strpos($_GET['page'], 'gform-booking') === false) {
            return;
        }

        // Handle appointment cancellation.
        if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['appointment_id'])) {
            check_admin_referer('cancel_appointment');
            $appointment = new Appointment(absint($_GET['appointment_id']));
            if ($appointment->exists()) {
                $appointment->cancel();
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Appointment cancelled successfully.', 'gform-booking') . '</p></div>';
                });
            }
            wp_redirect(admin_url('admin.php?page=gform-booking-appointments'));
            exit;
        }

        // Handle appointment deletion.
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['appointment_id'])) {
            check_admin_referer('delete_appointment');
            global $wpdb;
            $table = $wpdb->prefix . 'gf_booking_appointments';
            $appointment_id = absint($_GET['appointment_id']);
            $wpdb->delete($table, array('id' => $appointment_id), array('%d'));
            add_action('admin_notices', function () {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Appointment deleted successfully.', 'gform-booking') . '</p></div>';
            });
            wp_redirect(admin_url('admin.php?page=gform-booking-appointments'));
            exit;
        }

        // Handle service deletion.
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['service_id'])) {
            check_admin_referer('delete_service');
            $service = new Service(absint($_GET['service_id']));
            $service->delete();
            wp_redirect(admin_url('admin.php?page=gform-booking-services'));
            exit;
        }

        // Handle service form submissions.
        if (isset($_POST['save_service'])) {
            check_admin_referer('save_service', 'service_nonce');

            $service_id = isset($_POST['service_id']) ? absint($_POST['service_id']) : 0;
            $name = sanitize_text_field($_POST['name']);
            $description = sanitize_textarea_field($_POST['description']);
            $weekdays = isset($_POST['weekdays']) ? array_map('absint', $_POST['weekdays']) : array();
            $start_time = sanitize_text_field($_POST['start_time']);
            $end_time = sanitize_text_field($_POST['end_time']);
            $slot_type = sanitize_text_field($_POST['slot_type']);
            $slot_duration = absint($_POST['slot_duration']);
            $buffer_time = absint($_POST['buffer_time']);
            $calendar_type = sanitize_text_field($_POST['calendar_type']);

            // Lunch break settings.
            $has_lunch_break = isset($_POST['has_lunch_break']) ? true : false;
            $lunch_break_start = sanitize_text_field($_POST['lunch_break_start']);
            $lunch_break_end = sanitize_text_field($_POST['lunch_break_end']);

            // Half day settings.
            $half_day_morning = isset($_POST['half_day_morning']) ? true : false;
            $half_day_afternoon = isset($_POST['half_day_afternoon']) ? true : false;
            $half_day_morning_start = sanitize_text_field($_POST['half_day_morning_start']);
            $half_day_morning_end = sanitize_text_field($_POST['half_day_morning_end']);
            $half_day_afternoon_start = sanitize_text_field($_POST['half_day_afternoon_start']);
            $half_day_afternoon_end = sanitize_text_field($_POST['half_day_afternoon_end']);

            // Max booking date settings.
            $max_booking_type = sanitize_text_field($_POST['max_booking_type']);
            $max_booking_days = absint($_POST['max_booking_days']);
            $max_booking_date = sanitize_text_field($_POST['max_booking_date']);

            // Excluded dates settings.
            $excluded_dates = isset($_POST['excluded_dates']) ? array_map('sanitize_text_field', explode(',', $_POST['excluded_dates'])) : array();

            // Notify user setting.
            $notify_user_id = absint($_POST['notify_user_id']);

            // Email sender settings.
            $email_from_name = sanitize_text_field($_POST['email_from_name']);
            $email_from_email = sanitize_email($_POST['email_from_email']);

            // Max participants setting.
            $max_participants = isset($_POST['max_participants']) ? absint($_POST['max_participants']) : 1;

            $settings = array(
                'slot_type' => $slot_type,
                'calendar_type' => $calendar_type,
                'max_participants' => $max_participants,
                'has_lunch_break' => $has_lunch_break,
                'lunch_break_start' => $lunch_break_start,
                'lunch_break_end' => $lunch_break_end,
                'half_day_morning' => $half_day_morning,
                'half_day_afternoon' => $half_day_afternoon,
                'half_day_morning_start' => $half_day_morning_start,
                'half_day_morning_end' => $half_day_morning_end,
                'half_day_afternoon_start' => $half_day_afternoon_start,
                'half_day_afternoon_end' => $half_day_afternoon_end,
                'max_booking_type' => $max_booking_type,
                'max_booking_days' => $max_booking_days,
                'max_booking_date' => $max_booking_date,
                'excluded_dates' => $excluded_dates,
                'notify_user_id' => $notify_user_id,
                'email_from_name' => $email_from_name,
                'email_from_email' => $email_from_email,
            );

            $data = array(
                'name' => $name,
                'description' => $description,
                'weekdays' => $weekdays,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'slot_duration' => $slot_duration,
                'buffer_time' => $buffer_time,
                'settings' => $settings,
            );

            if ($service_id > 0) {
                // Update existing service.
                $service = new Service($service_id);
                $success = $service->update($data);
            } else {
                // Create new service.
                $success = Service::create($data);
            }

            if ($success) {
                // Redirect back to the same page (edit mode or list).
                if ($service_id > 0) {
                    // Editing: go back to edit page.
                    wp_redirect(admin_url('admin.php?page=gform-booking-services&action=edit&service_id=' . $service_id . '&updated=1'));
                } else {
                    // New service: redirect to list with message.
                    wp_redirect(admin_url('admin.php?page=gform-booking-services&added=1'));
                }
                exit;
            } else {
                // Handle errors if needed.
                $error_url = $service_id > 0
                    ? admin_url('admin.php?page=gform-booking-services&action=edit&service_id=' . $service_id . '&error=1')
                    : admin_url('admin.php?page=gform-booking-services&action=add&error=1');
                wp_redirect($error_url);
                exit;
            }
        }
    }
}
