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
     * Get currency setting from global plugin settings or Gravity Forms
     * 
     * @return string Currency code (default: EUR)
     */
    public static function get_currency()
    {
        // First, try to get from our plugin settings.
        $gf_addon = \GFormBooking\GF_Addon::get_instance();
        if ($gf_addon) {
            $settings = $gf_addon->get_plugin_settings();
            if (!empty($settings['currency'])) {
                return sanitize_text_field($settings['currency']);
            }
        }

        // Fallback to Gravity Forms currency if available.
        if (class_exists('GFCommon')) {
            $currency = apply_filters('gform_currency', get_option('rg_gforms_currency'));
            if (!empty($currency)) {
                return $currency;
            }
        }

        // Default currency
        return 'EUR';
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu()
    {
        // Check user capabilities
        if (! current_user_can('manage_options')) {
            return;
        }

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

        add_submenu_page(
            'gform-booking',
            __('Settings', 'gform-booking'),
            __('Settings', 'gform-booking'),
            'manage_options',
            'gform-booking-settings',
            array($this, 'render_settings')
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
                        // Get schedule info from settings (Daily Time Windows or Custom Slots).
                        $settings = json_decode($service['settings'], true);
                        if (!is_array($settings)) {
                            $settings = array();
                        }

                        $schedule_info = array();
                        $slot_type = isset($settings['slot_type']) ? $settings['slot_type'] : 'time';

                        if ($slot_type === 'custom' && !empty($settings['custom_slots'])) {
                            $schedule_info[] = __('Custom Slots', 'gform-booking');
                        } elseif (!empty($settings['daily_time_windows'])) {
                            $schedule_info[] = __('Daily Time Windows', 'gform-booking');
                        } else {
                            $schedule_info[] = __('Time-based slots', 'gform-booking');
                            if (!empty($service['start_time']) && !empty($service['end_time'])) {
                                $schedule_info[] = date('g:i A', strtotime($service['start_time'])) . ' - ' . date('g:i A', strtotime($service['end_time']));
                            }
                        }

                        $schedule = implode(' | ', $schedule_info);

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

        // Weekdays are no longer configured separately - they're defined in Daily Time Windows or Custom Slots

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

        // Min/Max booking date settings.
        $min_booking_type = isset($settings['min_booking_type']) ? $settings['min_booking_type'] : 'days_ahead';
        $min_booking_days = isset($settings['min_booking_days']) ? $settings['min_booking_days'] : 1;
        $min_booking_date = isset($settings['min_booking_date']) ? $settings['min_booking_date'] : '';
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
                        <th scope="row"><label for="slot_type"><?php esc_html_e('Booking Type', 'gform-booking'); ?></label></th>
                        <td>
                            <select id="slot_type" name="slot_type">
                                <option value="time" <?php selected($slot_type, 'time'); ?>><?php esc_html_e('Fixed Duration Slots (e.g., 30min intervals)', 'gform-booking'); ?></option>
                                <option value="custom" <?php selected($slot_type, 'custom'); ?>><?php esc_html_e('Custom Slots (e.g., half day, full day)', 'gform-booking'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Fixed Duration: Creates slots based on duration. Custom: Define your own slots (e.g., 8am-12pm, 1pm-5pm) with individual capacity.', 'gform-booking'); ?></p>
                        </td>
                    </tr>
                    <tr class="time-windows-settings">
                        <th scope="row"><label><?php esc_html_e('Daily Time Windows', 'gform-booking'); ?></label></th>
                        <td>
                            <?php
                            // Get daily time windows from settings.
                            $daily_windows = isset($settings['daily_time_windows']) ? $settings['daily_time_windows'] : array();
                            if (!is_array($daily_windows)) {
                                $daily_windows = array();
                            }

                            // Default values if not set.
                            $day_names = array(
                                1 => __('Monday', 'gform-booking'),
                                2 => __('Tuesday', 'gform-booking'),
                                3 => __('Wednesday', 'gform-booking'),
                                4 => __('Thursday', 'gform-booking'),
                                5 => __('Friday', 'gform-booking'),
                                6 => __('Saturday', 'gform-booking'),
                                7 => __('Sunday', 'gform-booking'),
                            );

                            // If daily_windows is empty, create default with all weekdays open using current time settings.
                            if (empty($daily_windows)) {
                                foreach ($day_names as $day_num => $day_name) {
                                    $daily_windows[$day_num] = array(
                                        'closed' => false,
                                        'windows' => array(
                                            array(
                                                'start' => $start_time,
                                                'end' => $end_time,
                                            )
                                        )
                                    );
                                }
                            }
                            ?>
                            <div id="daily-time-windows" class="gf-booking-daily-windows">
                                <?php foreach ($day_names as $day_num => $day_name) :
                                    $day_config = isset($daily_windows[$day_num]) ? $daily_windows[$day_num] : array('closed' => false, 'windows' => array());
                                    $is_closed = isset($day_config['closed']) ? $day_config['closed'] : false;
                                    $windows = isset($day_config['windows']) && is_array($day_config['windows']) ? $day_config['windows'] : array();
                                    if (empty($windows) && !$is_closed) {
                                        $windows = array(array('start' => '09:00:00', 'end' => '17:00:00'));
                                    }
                                ?>
                                    <div class="daily-window-row" data-day="<?php echo esc_attr($day_num); ?>">
                                        <div class="daily-window-header">
                                            <strong><?php echo esc_html($day_name); ?></strong>
                                            <label class="closed-toggle">
                                                <input type="checkbox" name="daily_closed_<?php echo esc_attr($day_num); ?>" value="1" <?php checked($is_closed); ?>>
                                                <?php esc_html_e('Closed', 'gform-booking'); ?>
                                            </label>
                                        </div>
                                        <div class="daily-windows-container" <?php if ($is_closed) {
                                                                                    echo 'style="display:none;"';
                                                                                } ?>>
                                            <div class="windows-list" data-day="<?php echo esc_attr($day_num); ?>">
                                                <?php foreach ($windows as $window_index => $window) : ?>
                                                    <div class="window-item" data-index="<?php echo esc_attr($window_index); ?>">
                                                        <label><?php esc_html_e('From:', 'gform-booking'); ?>
                                                            <input type="time" name="daily_start_<?php echo esc_attr($day_num); ?>_<?php echo esc_attr($window_index); ?>" value="<?php echo esc_attr($window['start']); ?>" class="small-text">
                                                        </label>
                                                        <label style="margin-left: 10px;"><?php esc_html_e('To:', 'gform-booking'); ?>
                                                            <input type="time" name="daily_end_<?php echo esc_attr($day_num); ?>_<?php echo esc_attr($window_index); ?>" value="<?php echo esc_attr($window['end']); ?>" class="small-text">
                                                        </label>
                                                        <button type="button" class="button remove-window" style="margin-left: 10px;"><?php esc_html_e('Remove', 'gform-booking'); ?></button>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <button type="button" class="button add-window" data-day="<?php echo esc_attr($day_num); ?>"><?php esc_html_e('Add Time Window', 'gform-booking'); ?></button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="description">
                                <?php esc_html_e('Configure time windows for each day of the week. Use "Closed" to block a day completely, or add multiple time windows for morning/afternoon sessions.', 'gform-booking'); ?>
                            </p>
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
                    <tr class="custom-slots-settings" style="display:none;">
                        <th scope="row"><label><?php esc_html_e('Custom Slots', 'gform-booking'); ?></label></th>
                        <td>
                            <?php
                            $custom_slots = isset($settings['custom_slots']) ? $settings['custom_slots'] : array();
                            if (!is_array($custom_slots)) {
                                $custom_slots = array();
                            }
                            if (empty($custom_slots)) {
                                $custom_slots = array(array(
                                    'start' => '08:00',
                                    'end' => '17:00',
                                    'weekdays' => array(1, 2, 3, 4, 5),
                                    'capacity' => 1,
                                    'price' => '',
                                ));
                            }
                            ?>
                            <div class="gf-booking-custom-slots">
                                <table class="wp-list-table widefat fixed striped custom-slots-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Start Time', 'gform-booking'); ?></th>
                                            <th><?php esc_html_e('End Time', 'gform-booking'); ?></th>
                                            <th><?php esc_html_e('Days', 'gform-booking'); ?></th>
                                            <th><?php esc_html_e('Capacity', 'gform-booking'); ?></th>
                                            <th><?php esc_html_e('Price', 'gform-booking'); ?></th>
                                            <th><?php esc_html_e('Actions', 'gform-booking'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="custom-slots-list">
                                        <?php foreach ($custom_slots as $index => $slot) : ?>
                                            <tr class="custom-slot-row" data-index="<?php echo esc_attr($index); ?>">
                                                <td>
                                                    <input type="time" name="custom_slot_start[]" value="<?php echo esc_attr($slot['start']); ?>" class="small-text" required>
                                                </td>
                                                <td>
                                                    <input type="time" name="custom_slot_end[]" value="<?php echo esc_attr($slot['end']); ?>" class="small-text" required>
                                                </td>
                                                <td>
                                                    <div class="weekday-checkboxes">
                                                        <?php
                                                        $weekday_names = array(
                                                            1 => 'M',
                                                            2 => 'T',
                                                            3 => 'W',
                                                            4 => 'T',
                                                            5 => 'F',
                                                            6 => 'S',
                                                            7 => 'S',
                                                        );
                                                        $slot_weekdays = isset($slot['weekdays']) && is_array($slot['weekdays']) ? $slot['weekdays'] : array();
                                                        foreach ($weekday_names as $day_num => $day_letter) :
                                                            $checked = in_array($day_num, $slot_weekdays) ? 'checked' : '';
                                                        ?>
                                                            <label class="weekday-checkbox">
                                                                <input type="checkbox" name="custom_slot_weekdays_<?php echo esc_attr($index); ?>[]" value="<?php echo esc_attr($day_num); ?>" <?php echo $checked; ?>>
                                                                <span><?php echo esc_html($day_letter); ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="number" name="custom_slot_capacity[]" value="<?php echo esc_attr(isset($slot['capacity']) ? $slot['capacity'] : 1); ?>" min="1" class="small-text" required>
                                                </td>
                                                <td>
                                                    <input type="text" name="custom_slot_price[]" value="<?php echo esc_attr(isset($slot['price']) ? $slot['price'] : ''); ?>" placeholder="<?php esc_attr_e('e.g., 450.00', 'gform-booking'); ?>" pattern="[0-9]+([.,][0-9]{1,2})?" class="small-text">
                                                </td>
                                                <td>
                                                    <button type="button" class="button remove-custom-slot"><?php esc_html_e('Delete', 'gform-booking'); ?></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <button type="button" id="add-custom-slot" class="button"><?php esc_html_e('Add New Custom Slot', 'gform-booking'); ?></button>
                                <p class="description"><?php esc_html_e('Define custom time slots (e.g., morning 8am-12pm, afternoon 1pm-5pm). Each slot can have different capacity, price, and availability days. Slots can overlap.', 'gform-booking'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr class="time-slot-settings">
                        <th scope="row"><label for="max_participants"><?php esc_html_e('Max Participants per Slot', 'gform-booking'); ?></label></th>
                        <td>
                            <?php
                            $max_participants = isset($settings['max_participants']) ? $settings['max_participants'] : 1;
                            ?>
                            <input type="number" id="max_participants" name="max_participants" value="<?php echo esc_attr($max_participants); ?>" min="1" step="1">
                            <p class="description"><?php esc_html_e('Maximum number of people that can book the same time slot. Set to 1 for exclusive slots.', 'gform-booking'); ?></p>
                        </td>
                    </tr>
                    <tr class="time-slot-settings">
                        <th scope="row"><label for="slot_price"><?php esc_html_e('Price per Slot', 'gform-booking'); ?></label></th>
                        <td>
                            <?php
                            $slot_price = isset($settings['slot_price']) ? $settings['slot_price'] : '';
                            $currency = self::get_currency();
                            ?>
                            <input type="text" id="slot_price" name="slot_price" value="<?php echo esc_attr($slot_price); ?>" pattern="[0-9]+([.,][0-9]{1,2})?" placeholder="<?php echo esc_attr('0.00'); ?>" class="small-text">
                            <p class="description"><?php echo esc_html(sprintf(__('Price per slot (currency: %s). Enter numbers only, e.g., 450.00. Leave empty for free slots.', 'gform-booking'), $currency)); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="allow_multiple_slots"><?php esc_html_e('Allow Multiple Slot Bookings', 'gform-booking'); ?></label></th>
                        <td>
                            <?php
                            $allow_multiple_slots = isset($settings['allow_multiple_slots']) ? $settings['allow_multiple_slots'] : false;
                            ?>
                            <label>
                                <input type="checkbox" id="allow_multiple_slots" name="allow_multiple_slots" value="1" <?php checked($allow_multiple_slots); ?>>
                                <?php esc_html_e('Enable customers to book multiple slots at once', 'gform-booking'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('When enabled, customers can select multiple slots (e.g., morning + afternoon). If slots are on the same day, they can be merged into one appointment.', 'gform-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cutoff_hours"><?php esc_html_e('Cutoff (hours before appointment)', 'gform-booking'); ?></label></th>
                        <td>
                            <?php $cutoff_hours = isset($settings['cutoff_hours']) ? absint($settings['cutoff_hours']) : 0; ?>
                            <input type="number" id="cutoff_hours" name="cutoff_hours" value="<?php echo esc_attr($cutoff_hours); ?>" min="0" step="1" class="small-text">
                            <p class="description"><?php esc_html_e('Within this window, customers cannot modify or cancel appointments.', 'gform-booking'); ?></p>
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
                        <th scope="row"><label for="min_booking_type"><?php esc_html_e('Min Booking Date', 'gform-booking'); ?></label></th>
                        <td>
                            <select id="min_booking_type" name="min_booking_type">
                                <option value="days_ahead" <?php selected($min_booking_type, 'days_ahead'); ?>><?php esc_html_e('Days Ahead (from today)', 'gform-booking'); ?></option>
                                <option value="fixed_date" <?php selected($min_booking_type, 'fixed_date'); ?>><?php esc_html_e('Fixed Start Date', 'gform-booking'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr class="min-booking-days-setting">
                        <th scope="row"><label for="min_booking_days"><?php esc_html_e('Earliest Days Ahead', 'gform-booking'); ?></label></th>
                        <td><input type="number" id="min_booking_days" name="min_booking_days" value="<?php echo esc_attr($min_booking_days); ?>" min="0" step="1"></td>
                    </tr>
                    <tr class="min-booking-date-setting" style="display:none;">
                        <th scope="row"><label for="min_booking_date"><?php esc_html_e('Fixed Start Date', 'gform-booking'); ?></label></th>
                        <td><input type="date" id="min_booking_date" name="min_booking_date" value="<?php echo esc_attr($min_booking_date); ?>"></td>
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
                    if (slotType === 'custom') {
                        // Custom Slots: Show only custom slots settings
                        $('.time-slot-settings').hide(); // This includes Slot Duration, Buffer Time, Max Participants, and Price
                        $('.time-windows-settings').hide(); // Daily Time Windows
                        $('.custom-slots-settings').show();
                    } else {
                        // Fixed Duration: Show Daily Time Windows, Slot Duration, Buffer Time, Max Participants, and Price
                        $('.time-slot-settings').show(); // Slot Duration, Buffer Time, Max Participants, Price
                        $('.time-windows-settings').show(); // Daily Time Windows
                        $('.custom-slots-settings').hide();
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

                function toggleMinBookingSettings() {
                    var minType = $('#min_booking_type').val();
                    if (minType === 'fixed_date') {
                        $('.min-booking-days-setting').hide();
                        $('.min-booking-date-setting').show();
                    } else {
                        $('.min-booking-days-setting').show();
                        $('.min-booking-date-setting').hide();
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

                // Handle daily windows closed toggle.
                function toggleClosedDay(checkbox) {
                    var $row = $(checkbox).closest('.daily-window-row');
                    var $container = $row.find('.daily-windows-container');
                    if ($(checkbox).is(':checked')) {
                        $container.slideUp();
                    } else {
                        $container.slideDown();
                        // Ensure at least one window exists.
                        if ($container.find('.window-item').length === 0) {
                            addWindow($container.find('.add-window'));
                        }
                    }
                }

                $(document).on('change', '.closed-toggle input', function() {
                    toggleClosedDay(this);
                });

                // Handle add window button.
                function addWindow(button) {
                    var day = $(button).data('day');
                    var $list = $(button).siblings('.windows-list');
                    var index = $list.find('.window-item').length;

                    var html = '<div class="window-item" data-index="' + index + '">' +
                        '<label><?php esc_html_e('From:', 'gform-booking'); ?> <input type="time" name="daily_start_' + day + '_' + index + '" value="09:00" class="small-text"></label>' +
                        '<label style="margin-left: 10px;"><?php esc_html_e('To:', 'gform-booking'); ?> <input type="time" name="daily_end_' + day + '_' + index + '" value="17:00" class="small-text"></label>' +
                        '<button type="button" class="button remove-window" style="margin-left: 10px;"><?php esc_html_e('Remove', 'gform-booking'); ?></button>' +
                        '</div>';
                    $list.append(html);
                }

                $(document).on('click', '.add-window', function() {
                    addWindow($(this));
                });

                // Handle remove window button.
                $(document).on('click', '.remove-window', function() {
                    var $list = $(this).closest('.windows-list');
                    $(this).closest('.window-item').remove();

                    // Update indices.
                    $list.find('.window-item').each(function(index) {
                        $(this).attr('data-index', index);
                        $(this).find('input[name^="daily_start_"]').attr('name', $(this).find('input[name^="daily_start_"]').attr('name').replace(/_\d+_/, '_' + index + '_'));
                        $(this).find('input[name^="daily_end_"]').attr('name', $(this).find('input[name^="daily_end_"]').attr('name').replace(/_\d+_/, '_' + index + '_'));
                    });
                });

                // Handle custom slots add/remove.
                function addCustomSlot() {
                    var index = $('#custom-slots-list tr').length;
                    var weekdayNames = [1, 2, 3, 4, 5, 6, 7];
                    var weekdayLetters = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
                    var html = '<tr class="custom-slot-row" data-index="' + index + '">' +
                        '<td><input type="time" name="custom_slot_start[]" value="08:00" class="small-text" required></td>' +
                        '<td><input type="time" name="custom_slot_end[]" value="17:00" class="small-text" required></td>' +
                        '<td><div class="weekday-checkboxes">';
                    for (var i = 0; i < weekdayNames.length; i++) {
                        var checked = (weekdayNames[i] <= 5) ? 'checked' : '';
                        html += '<label class="weekday-checkbox">' +
                            '<input type="checkbox" name="custom_slot_weekdays_' + index + '[]" value="' + weekdayNames[i] + '" ' + checked + '>' +
                            '<span>' + weekdayLetters[i] + '</span>' +
                            '</label>';
                    }
                    html += '</div></td>' +
                        '<td><input type="number" name="custom_slot_capacity[]" value="1" min="1" class="small-text" required></td>' +
                        '<td><input type="text" name="custom_slot_price[]" placeholder="<?php esc_attr_e('e.g., 450.00', 'gform-booking'); ?>" pattern="[0-9]+([.,][0-9]{1,2})?" class="small-text"></td>' +
                        '<td><button type="button" class="button remove-custom-slot"><?php esc_html_e('Delete', 'gform-booking'); ?></button></td>' +
                        '</tr>';
                    $('#custom-slots-list').append(html);
                }

                $(document).on('click', '#add-custom-slot', addCustomSlot);
                $(document).on('click', '.remove-custom-slot', function() {
                    $(this).closest('tr').remove();
                });

                $('#has_lunch_break').on('change', toggleLunchBreak);
                $('#slot_type').on('change', toggleSlotSettings);
                $('#max_booking_type').on('change', toggleMaxBookingSettings);
                $('#min_booking_type').on('change', toggleMinBookingSettings);
                toggleSlotSettings(); // Call on page load.
                toggleMaxBookingSettings(); // Call on page load.
                toggleMinBookingSettings(); // Call on page load.
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

            .gf-booking-daily-windows {
                background: #f9f9f9;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                max-height: 600px;
                overflow-y: auto;
            }

            .daily-window-row {
                margin-bottom: 15px;
                padding: 10px;
                background: #fff;
                border: 1px solid #ccc;
                border-radius: 3px;
            }

            .daily-window-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }

            .daily-window-header strong {
                font-size: 14px;
            }

            .closed-toggle {
                margin-left: auto;
            }

            .windows-list {
                margin-bottom: 10px;
            }

            .window-item {
                margin-bottom: 10px;
                padding: 8px;
                background: #f5f5f5;
                border-radius: 3px;
            }

            .custom-slots-table {
                margin-top: 10px;
            }

            .weekday-checkboxes {
                display: flex;
                gap: 5px;
            }

            .weekday-checkbox {
                display: inline-block;
                margin: 0;
            }

            .weekday-checkbox input[type="checkbox"] {
                display: none;
            }

            .weekday-checkbox span {
                display: inline-block;
                width: 25px;
                height: 25px;
                line-height: 25px;
                text-align: center;
                border: 1px solid #ddd;
                background: #f5f5f5;
                cursor: pointer;
                border-radius: 3px;
            }

            .weekday-checkbox input[type="checkbox"]:checked+span {
                background: #0073aa;
                color: #fff;
                border-color: #0073aa;
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

        // Exports
        if (isset($_GET['export'])) {
            // Verify nonce
            if (! isset($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], 'export_appointments')) {
                wp_die(__('Security check failed.', 'gform-booking'));
            }

            $format = sanitize_text_field($_GET['export']);

            // Execute query (table name is safe as it uses $wpdb->prefix)
            $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY appointment_date ASC, start_time ASC", ARRAY_A);
            if ($format === 'json') {
                nocache_headers();
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="appointments.json"');
                echo wp_json_encode($rows);
                exit;
            } elseif ($format === 'csv') {
                nocache_headers();
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="appointments.csv"');
                $out = fopen('php://output', 'w');
                if (!empty($rows)) {
                    fputcsv($out, array_keys($rows[0]));
                    foreach ($rows as $r) {
                        fputcsv($out, $r);
                    }
                }
                fclose($out);
                exit;
            }
        }

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
                <p class="submit">
                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('export' => 'csv')), 'export_appointments')); ?>" class="button">CSV</a>
                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('export' => 'json')), 'export_appointments')); ?>" class="button">JSON</a>
                </p>
                <?php if (empty($appointments)) : ?>
                    <p><?php esc_html_e('No appointments found.', 'gform-booking'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Appointment Date & Time', 'gform-booking'); ?></th>
                                <th><?php esc_html_e('Booking Date', 'gform-booking'); ?></th>
                                <th><?php esc_html_e('Customer', 'gform-booking'); ?></th>
                                <th><?php esc_html_e('Participants', 'gform-booking'); ?></th>
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
                                $participants = isset($appointment['participants']) ? absint($appointment['participants']) : 1;
                                echo '<td>' . esc_html($participants) . '</td>';
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

        // Check user capabilities
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'gform-booking'));
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
            // Weekdays are no longer configured separately - keep empty array for database compatibility
            $weekdays = array();
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

            // Min/Max booking date settings.
            $min_booking_type = sanitize_text_field($_POST['min_booking_type']);
            $min_booking_days = isset($_POST['min_booking_days']) ? absint($_POST['min_booking_days']) : 0;
            $min_booking_date = sanitize_text_field($_POST['min_booking_date']);
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

            // Slot price setting (for fixed duration slots).
            $slot_price = isset($_POST['slot_price']) ? sanitize_text_field($_POST['slot_price']) : '';
            // Validate price format (numbers with optional decimal separator)
            if (!empty($slot_price) && !preg_match('/^[0-9]+([.,][0-9]{1,2})?$/', $slot_price)) {
                $slot_price = ''; // Invalid format, clear it
            }

            // Custom slots setting.
            $custom_slots = array();
            if (!empty($_POST['custom_slot_start']) && is_array($_POST['custom_slot_start'])) {
                foreach ($_POST['custom_slot_start'] as $index => $start_time) {
                    $weekdays = array();
                    foreach ($_POST as $key => $value) {
                        if (preg_match('/^custom_slot_weekdays_' . $index . '$/', $key) && is_array($value)) {
                            $weekdays = array_map('absint', $value);
                            break;
                        }
                        // Handle different formats.
                        if (strpos($key, 'custom_slot_weekdays_' . $index) !== false && is_array($value)) {
                            $weekdays = array_map('absint', $value);
                        }
                    }

                    $end_time = isset($_POST['custom_slot_end'][$index]) ? sanitize_text_field($_POST['custom_slot_end'][$index]) : '';
                    $capacity = isset($_POST['custom_slot_capacity'][$index]) ? absint($_POST['custom_slot_capacity'][$index]) : 1;
                    $price = isset($_POST['custom_slot_price'][$index]) ? sanitize_text_field($_POST['custom_slot_price'][$index]) : '';
                    if (!empty($price) && !preg_match('/^[0-9]+([.,][0-9]{1,2})?$/', $price)) {
                        $price = '';
                    }

                    if (!empty($start_time) && !empty($end_time)) {
                        $custom_slots[] = array(
                            'start' => $start_time,
                            'end' => $end_time,
                            'weekdays' => $weekdays,
                            'capacity' => $capacity,
                            'price' => $price,
                        );
                    }
                }
            }

            // Allow multiple slots setting.
            $allow_multiple_slots = isset($_POST['allow_multiple_slots']) && $_POST['allow_multiple_slots'] ? true : false;

            // Daily time windows setting.
            $daily_time_windows = array();
            for ($day = 1; $day <= 7; $day++) {
                $is_closed = isset($_POST['daily_closed_' . $day]) ? true : false;
                $windows = array();

                if (!$is_closed) {
                    // Collect windows for this day.
                    $day_start_fields = array();
                    $day_end_fields = array();

                    foreach ($_POST as $key => $value) {
                        if (preg_match('/^daily_start_' . $day . '_(\d+)$/', $key, $matches)) {
                            $day_start_fields[$matches[1]] = sanitize_text_field($value);
                        }
                        if (preg_match('/^daily_end_' . $day . '_(\d+)$/', $key, $matches)) {
                            $day_end_fields[$matches[1]] = sanitize_text_field($value);
                        }
                    }

                    // Match up start and end times.
                    foreach ($day_start_fields as $index => $start_time) {
                        if (isset($day_end_fields[$index])) {
                            $windows[] = array(
                                'start' => $start_time,
                                'end' => $day_end_fields[$index],
                            );
                        }
                    }
                }

                $daily_time_windows[$day] = array(
                    'closed' => $is_closed,
                    'windows' => $windows,
                );
            }

            $cutoff_hours = isset($_POST['cutoff_hours']) ? absint($_POST['cutoff_hours']) : 0;

            $settings = array(
                'slot_type' => $slot_type,
                'calendar_type' => $calendar_type,
                'max_participants' => $max_participants,
                'slot_price' => $slot_price,
                'custom_slots' => $custom_slots,
                'allow_multiple_slots' => $allow_multiple_slots,
                'has_lunch_break' => $has_lunch_break,
                'lunch_break_start' => $lunch_break_start,
                'lunch_break_end' => $lunch_break_end,
                'min_booking_type' => $min_booking_type,
                'min_booking_days' => $min_booking_days,
                'min_booking_date' => $min_booking_date,
                'max_booking_type' => $max_booking_type,
                'max_booking_days' => $max_booking_days,
                'max_booking_date' => $max_booking_date,
                'excluded_dates' => $excluded_dates,
                'notify_user_id' => $notify_user_id,
                'email_from_name' => $email_from_name,
                'email_from_email' => $email_from_email,
                'daily_time_windows' => $daily_time_windows,
                'cutoff_hours' => $cutoff_hours,
            );

            $data = array(
                'name' => $name,
                'description' => $description,
                'weekdays' => $weekdays, // Empty array - weekdays are now defined in Daily Time Windows or Custom Slots
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

    /**
     * Render settings page
     */
    public function render_settings()
    {
        // Handle settings save.
        if (isset($_POST['gf_booking_settings_save'])) {
            check_admin_referer('gf_booking_settings');

            $currency = sanitize_text_field($_POST['currency']);
            $management_page_id = isset($_POST['management_page_id']) ? absint($_POST['management_page_id']) : 0;

            // Save color settings
            $colors = array(
                'primary' => isset($_POST['color_primary']) ? sanitize_hex_color($_POST['color_primary']) : '#0073aa',
                'primary_hover' => isset($_POST['color_primary_hover']) ? sanitize_hex_color($_POST['color_primary_hover']) : '#005177',
                'secondary_bg' => isset($_POST['color_secondary_bg']) ? sanitize_hex_color($_POST['color_secondary_bg']) : '#f0f0f0',
                'secondary_border' => isset($_POST['color_secondary_border']) ? sanitize_hex_color($_POST['color_secondary_border']) : '#ddd',
                'success_bg' => isset($_POST['color_success_bg']) ? sanitize_hex_color($_POST['color_success_bg']) : '#d4edda',
                'success_border' => isset($_POST['color_success_border']) ? sanitize_hex_color($_POST['color_success_border']) : '#c3e6cb',
                'error_bg' => isset($_POST['color_error_bg']) ? sanitize_hex_color($_POST['color_error_bg']) : '#f8d7da',
                'error_border' => isset($_POST['color_error_border']) ? sanitize_hex_color($_POST['color_error_border']) : '#f5c6cb',
                'info_bg' => isset($_POST['color_info_bg']) ? sanitize_hex_color($_POST['color_info_bg']) : '#d1ecf1',
                'info_border' => isset($_POST['color_info_border']) ? sanitize_hex_color($_POST['color_info_border']) : '#bee5eb',
                'warning_bg' => isset($_POST['color_warning_bg']) ? sanitize_hex_color($_POST['color_warning_bg']) : '#fff3cd',
                'warning_border' => isset($_POST['color_warning_border']) ? sanitize_hex_color($_POST['color_warning_border']) : '#ffc107',
                'calendar_header_bg' => isset($_POST['color_calendar_header_bg']) ? sanitize_hex_color($_POST['color_calendar_header_bg']) : '#f0f0f0',
                'calendar_available_bg' => isset($_POST['color_calendar_available_bg']) ? sanitize_hex_color($_POST['color_calendar_available_bg']) : '#f0fdf4',
                'calendar_unavailable_bg' => isset($_POST['color_calendar_unavailable_bg']) ? sanitize_hex_color($_POST['color_calendar_unavailable_bg']) : '#f5f5f5',
                'calendar_day_hover' => isset($_POST['color_calendar_day_hover']) ? sanitize_hex_color($_POST['color_calendar_day_hover']) : '#e8f4f8',
            );

            // Save via GF Addon API.
            $gf_addon = \GFormBooking\GF_Addon::get_instance();
            if ($gf_addon) {
                $settings = $gf_addon->get_plugin_settings();
                $settings['currency'] = $currency;
                $settings['management_page_id'] = $management_page_id;
                $settings['colors'] = $colors;
                $gf_addon->update_plugin_settings($settings);
            }

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'gform-booking') . '</p></div>';
        }

        // Get current settings.
        $gf_addon = \GFormBooking\GF_Addon::get_instance();
        $current_currency = self::get_currency();
        $settings = $gf_addon ? $gf_addon->get_plugin_settings() : array();

        // Get current colors with defaults
        $default_colors = array(
            'primary' => '#0073aa',
            'primary_hover' => '#005177',
            'secondary_bg' => '#f0f0f0',
            'secondary_border' => '#ddd',
            'success_bg' => '#d4edda',
            'success_border' => '#c3e6cb',
            'error_bg' => '#f8d7da',
            'error_border' => '#f5c6cb',
            'info_bg' => '#d1ecf1',
            'info_border' => '#bee5eb',
            'warning_bg' => '#fff3cd',
            'warning_border' => '#ffc107',
            'calendar_header_bg' => '#f0f0f0',
            'calendar_available_bg' => '#f0fdf4',
            'calendar_unavailable_bg' => '#f5f5f5',
            'calendar_day_hover' => '#e8f4f8',
        );
        $colors = isset($settings['colors']) ? wp_parse_args($settings['colors'], $default_colors) : $default_colors;

        // Get Gravity Forms currency as fallback info.
        $gf_currency = 'N/A';
        if (class_exists('GFCommon')) {
            $gf_currency_value = apply_filters('gform_currency', get_option('rg_gforms_currency'));
            if (!empty($gf_currency_value)) {
                $gf_currency = $gf_currency_value;
            }
        }

        // Enqueue WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // Get theme color palette
        $theme_palette = array();

        // Block themes (theme.json)
        if (function_exists('wp_get_global_settings')) {
            // Try to get custom (user-defined) colors first
            $global_palettes = wp_get_global_settings(array('color', 'palette'));

            if (is_array($global_palettes)) {
                // Prefer 'custom' palette (user-defined site colors)
                if (isset($global_palettes['custom']) && is_array($global_palettes['custom'])) {
                    foreach ($global_palettes['custom'] as $item) {
                        if (!empty($item['color']) && is_string($item['color'])) {
                            $theme_palette[] = $item['color'];
                        }
                    }
                }

                // Fallback to 'theme' palette (theme-defined colors)
                if (empty($theme_palette) && isset($global_palettes['theme']) && is_array($global_palettes['theme'])) {
                    foreach ($global_palettes['theme'] as $item) {
                        if (!empty($item['color']) && is_string($item['color'])) {
                            $theme_palette[] = $item['color'];
                        }
                    }
                }

                // Last resort: 'default' palette
                if (empty($theme_palette) && isset($global_palettes['default']) && is_array($global_palettes['default'])) {
                    foreach ($global_palettes['default'] as $item) {
                        if (!empty($item['color']) && is_string($item['color'])) {
                            $theme_palette[] = $item['color'];
                        }
                    }
                }
            }
        }

        // Classic themes (editor-color-palette support)
        if (empty($theme_palette)) {
            $support = get_theme_support('editor-color-palette');
            if (is_array($support) && !empty($support[0]) && is_array($support[0])) {
                foreach ($support[0] as $item) {
                    if (!empty($item['color']) && is_string($item['color'])) {
                        $theme_palette[] = $item['color'];
                    }
                }
            }
        }

        // Remove duplicates and re-index
        $theme_palette = array_values(array_unique($theme_palette));

        // Pass to JavaScript
        wp_add_inline_script(
            'wp-color-picker',
            'window.gfBookingThemePalette = ' . wp_json_encode($theme_palette) . ';',
            'after'
        );
    ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('gf_booking_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="currency"><?php esc_html_e('Currency', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="currency" name="currency" value="<?php echo esc_attr($current_currency); ?>" class="regular-text" placeholder="EUR">
                            <p class="description">
                                <?php esc_html_e('The currency code used for pricing (e.g., EUR, USD, GBP).', 'gform-booking'); ?>
                                <?php if ($gf_currency !== 'N/A'): ?>
                                    <br><?php printf(esc_html__('Note: Gravity Forms currency is set to %s.', 'gform-booking'), '<strong>' . esc_html($gf_currency) . '</strong>'); ?>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <?php
                    // Get current management page setting
                    $current_management_page = isset($settings['management_page_id']) ? absint($settings['management_page_id']) : 0;
                    // Get all pages
                    $pages = get_pages(array('sort_column' => 'post_title'));
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="management_page_id"><?php esc_html_e('Appointment Management Page', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <select id="management_page_id" name="management_page_id" class="regular-text">
                                <option value="0"><?php esc_html_e('-- Select Page --', 'gform-booking'); ?></option>
                                <?php foreach ($pages as $page): ?>
                                    <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($current_management_page, $page->ID); ?>>
                                        <?php echo esc_html($page->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Select a page where customers can manage their appointments. This page will only be accessible with a valid appointment token.', 'gform-booking'); ?>
                                <br><?php esc_html_e('Create a new page if needed, then select it here. The page will automatically show the management interface when accessed with a valid token.', 'gform-booking'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php esc_html_e('Color Settings', 'gform-booking'); ?></h2>
                <p class="description"><?php esc_html_e('Customize the colors used in the booking calendar and interface.', 'gform-booking'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row" colspan="2">
                            <h3 style="margin: 0; padding: 0 0 10px 0;"><?php esc_html_e('Primary Colors', 'gform-booking'); ?></h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_primary"><?php esc_html_e('Primary Color', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="color_primary" name="color_primary" value="<?php echo esc_attr($colors['primary']); ?>" class="gf-booking-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_primary_hover"><?php esc_html_e('Primary Hover Color', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="color_primary_hover" name="color_primary_hover" value="<?php echo esc_attr($colors['primary_hover']); ?>" class="gf-booking-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="2">
                            <h3 style="margin: 20px 0 10px 0;"><?php esc_html_e('Secondary Colors', 'gform-booking'); ?></h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_secondary_bg"><?php esc_html_e('Secondary Background', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="color_secondary_bg" name="color_secondary_bg" value="<?php echo esc_attr($colors['secondary_bg']); ?>" class="gf-booking-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_secondary_border"><?php esc_html_e('Secondary Border', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="color_secondary_border" name="color_secondary_border" value="<?php echo esc_attr($colors['secondary_border']); ?>" class="gf-booking-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="2">
                            <h3 style="margin: 20px 0 10px 0;"><?php esc_html_e('Status Colors', 'gform-booking'); ?></h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_success_bg"><?php esc_html_e('Success Background', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="color_success_bg" name="color_success_bg" value="<?php echo esc_attr($colors['success_bg']); ?>" class="gf-booking-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_success_border"><?php esc_html_e('Success Border', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="color_success_border" name="color_success_border" value="<?php echo esc_attr($colors['success_border']); ?>" class="gf-booking-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_error_bg"><?php esc_html_e('Error Background', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="color_error_bg" name="color_error_bg" value="<?php echo esc_attr($colors['error_bg']); ?>" class="gf-booking-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_error_border"><?php esc_html_e('Error Border', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="color_error_border" name="color_error_border" value="<?php echo esc_attr($colors['error_border']); ?>" class="gf-booking-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_info_bg"><?php esc_html_e('Info Background', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="color_info_bg" name="color_info_bg" value="<?php echo esc_attr($colors['info_bg']); ?>" class="gf-booking-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_info_border"><?php esc_html_e('Info Border', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="color_info_border" name="color_info_border" value="<?php echo esc_attr($colors['info_border']); ?>" class="gf-booking-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_warning_bg"><?php esc_html_e('Warning Background', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="color_warning_bg" name="color_warning_bg" value="<?php echo esc_attr($colors['warning_bg']); ?>" class="gf-booking-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_warning_border"><?php esc_html_e('Warning Border', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="color_warning_border" name="color_warning_border" value="<?php echo esc_attr($colors['warning_border']); ?>" class="gf-booking-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="2">
                            <h3 style="margin: 20px 0 10px 0;"><?php esc_html_e('Calendar Colors', 'gform-booking'); ?></h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_calendar_header_bg"><?php esc_html_e('Calendar Header Background', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="color_calendar_header_bg" name="color_calendar_header_bg" value="<?php echo esc_attr($colors['calendar_header_bg']); ?>" class="gf-booking-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_calendar_available_bg"><?php esc_html_e('Available Day Background', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="color_calendar_available_bg" name="color_calendar_available_bg" value="<?php echo esc_attr($colors['calendar_available_bg']); ?>" class="gf-booking-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_calendar_unavailable_bg"><?php esc_html_e('Unavailable Day Background', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="color_calendar_unavailable_bg" name="color_calendar_unavailable_bg" value="<?php echo esc_attr($colors['calendar_unavailable_bg']); ?>" class="gf-booking-color-picker">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_calendar_day_hover"><?php esc_html_e('Day Hover Background', 'gform-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="color_calendar_day_hover" name="color_calendar_day_hover" value="<?php echo esc_attr($colors['calendar_day_hover']); ?>" class="gf-booking-color-picker">
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="gf_booking_settings_save" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'gform-booking'); ?>">
                </p>
            </form>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Use theme palette if available, otherwise use WordPress default
                var palette = (window.gfBookingThemePalette && window.gfBookingThemePalette.length > 0) ?
                    window.gfBookingThemePalette :
                    true; // true = WordPress default palette

                $('.gf-booking-color-picker').wpColorPicker({
                    palettes: palette
                });
            });
        </script>
<?php
    }
}
