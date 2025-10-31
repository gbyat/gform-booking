<?php

/**
 * Autoloader class for GF Booking plugin
 *
 * @package GFormBooking
 */

namespace GFormBooking;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Autoloader
 */
class Autoloader
{

    /**
     * Initialize the autoloader
     */
    public static function init()
    {
        // Check for Gravity Forms.
        if (! class_exists('GFAPI')) {
            add_action('admin_notices', array(__CLASS__, 'gravity_forms_missing_notice'));
            return;
        }

        // Load plugin files.
        self::load_files();

        // Initialize GitHub updater early (before other components).
        self::init_github_updater();

        // Initialize components.
        self::init_components();

        // Hook into Gravity Forms.
        add_action('gform_loaded', array(__CLASS__, 'init_gform_addon'));
    }

    /**
     * Load required files
     */
    private static function load_files()
    {
        require_once GFORM_BOOKING_PLUGIN_DIR . 'includes/class-database.php';
        require_once GFORM_BOOKING_PLUGIN_DIR . 'includes/class-service.php';
        require_once GFORM_BOOKING_PLUGIN_DIR . 'includes/class-appointment.php';
        require_once GFORM_BOOKING_PLUGIN_DIR . 'includes/class-calendar.php';
        require_once GFORM_BOOKING_PLUGIN_DIR . 'includes/class-admin.php';
        require_once GFORM_BOOKING_PLUGIN_DIR . 'includes/class-form-fields.php';
        require_once GFORM_BOOKING_PLUGIN_DIR . 'includes/class-confirmation.php';
        require_once GFORM_BOOKING_PLUGIN_DIR . 'includes/class-updater.php';
    }

    /**
     * Initialize plugin components
     */
    private static function init_components()
    {
        // Initialize database.
        Database::init();

        // Initialize admin.
        if (is_admin()) {
            new Admin();
        }

        // Enqueue scripts and styles.
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));

        // AJAX handlers.
        add_action('wp_ajax_gf_booking_get_availability', array(__CLASS__, 'ajax_get_availability'));
        add_action('wp_ajax_nopriv_gf_booking_get_availability', array(__CLASS__, 'ajax_get_availability'));
        add_action('wp_ajax_gf_booking_get_month_calendar', array(__CLASS__, 'ajax_get_month_calendar'));
        add_action('wp_ajax_nopriv_gf_booking_get_month_calendar', array(__CLASS__, 'ajax_get_month_calendar'));
        add_action('wp_ajax_gf_booking_update_appointment', array(__CLASS__, 'ajax_update_appointment'));
        add_action('wp_ajax_nopriv_gf_booking_update_appointment', array(__CLASS__, 'ajax_update_appointment'));

        // Public appointment management page.
        add_action('wp', array(__CLASS__, 'handle_public_management'));

        // iCal download handler.
        add_action('template_redirect', array(__CLASS__, 'handle_ical_download'));
    }

    /**
     * Initialize GitHub updater
     */
    private static function init_github_updater()
    {
        // Only load in admin or when checking for updates.
        if (is_admin() || wp_doing_cron()) {
            new Updater(GFORM_BOOKING_PLUGIN_FILE);
        }
    }

    /**
     * Initialize Gravity Forms add-on
     */
    public static function init_gform_addon()
    {
        if (! method_exists('GFForms', 'include_addon_framework')) {
            return;
        }

        require_once GFORM_BOOKING_PLUGIN_DIR . 'includes/class-gf-addon.php';
        \GFAddOn::register(__NAMESPACE__ . '\GF_Addon');
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public static function enqueue_scripts()
    {
        wp_enqueue_style(
            'gf-booking-frontend',
            GFORM_BOOKING_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            GFORM_BOOKING_VERSION
        );

        // Enqueue dynamic color styles
        wp_add_inline_style('gf-booking-frontend', self::get_dynamic_colors_css());

        wp_enqueue_script(
            'gf-booking-frontend',
            GFORM_BOOKING_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            GFORM_BOOKING_VERSION,
            true
        );

        wp_localize_script(
            'gf-booking-frontend',
            'gfBooking',
            array(
                'ajaxUrl'   => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('gf_booking_nonce'),
                'timeFormat' => get_option('time_format'),
                'currency'  => \GFormBooking\Admin::get_currency(),
                'strings'   => array(
                    'loading'   => __('Loading...', 'gform-booking'),
                    'noSlots'   => __('No available time slots', 'gform-booking'),
                    'error'     => __('An error occurred. Please try again.', 'gform-booking'),
                    'confirm'   => __('Are you sure?', 'gform-booking'),
                    'success'   => __('Success!', 'gform-booking'),
                    // Provide both singular and plural forms for JavaScript
                    'slot'      => array(
                        'singular' => __('slot', 'gform-booking'),
                        'plural'   => __('slots', 'gform-booking'),
                    ),
                    'spot'      => array(
                        'singular' => __('spot', 'gform-booking'),
                        'plural'   => __('spots', 'gform-booking'),
                    ),
                    'left'      => __('left', 'gform-booking'),
                ),
            )
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_admin_scripts($hook)
    {
        // Only load on our admin pages.
        if (strpos($hook, 'gform-booking') === false) {
            return;
        }

        wp_enqueue_style(
            'gf-booking-admin',
            GFORM_BOOKING_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            GFORM_BOOKING_VERSION
        );

        wp_enqueue_script(
            'gf-booking-admin',
            GFORM_BOOKING_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            GFORM_BOOKING_VERSION,
            true
        );

        wp_localize_script(
            'gf-booking-admin',
            'gfBookingAdmin',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('gf_booking_admin_nonce'),
            )
        );
    }

    /**
     * AJAX handler for getting availability
     */
    public static function ajax_get_availability()
    {
        check_ajax_referer('gf_booking_nonce', 'nonce');

        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $service_id = isset($_POST['service_id']) ? absint($_POST['service_id']) : 0;

        $calendar = new Calendar($service_id);
        $slots = $calendar->get_available_slots($date);

        wp_send_json_success($slots);
    }

    /**
     * AJAX handler for getting month calendar
     */
    public static function ajax_get_month_calendar()
    {
        check_ajax_referer('gf_booking_nonce', 'nonce');

        $year = isset($_POST['year']) ? absint($_POST['year']) : date('Y');
        $month = isset($_POST['month']) ? absint($_POST['month']) : date('m');
        $service_id = isset($_POST['service_id']) ? absint($_POST['service_id']) : 0;

        $calendar = new Calendar($service_id);
        $month_data = $calendar->get_month_calendar($year, $month);

        wp_send_json_success($month_data);
    }

    /**
     * AJAX handler for updating appointments
     */
    public static function ajax_update_appointment()
    {
        check_ajax_referer('gf_booking_nonce', 'nonce');

        $action = isset($_POST['update_action']) ? sanitize_text_field($_POST['update_action']) : '';
        $appointment_id = isset($_POST['appointment_id']) ? absint($_POST['appointment_id']) : 0;
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';

        if (! $appointment_id || ! $token) {
            wp_send_json_error(array('message' => __('Invalid request.', 'gform-booking')));
        }

        $appointment = new Appointment($appointment_id);

        if (! $appointment->verify_token($token)) {
            wp_send_json_error(array('message' => __('Invalid token.', 'gform-booking')));
        }

        if ('cancel' === $action) {
            $result = $appointment->cancel();
        } elseif ('modify' === $action) {
            // Handle modification.
            $new_date = isset($_POST['new_date']) ? sanitize_text_field($_POST['new_date']) : '';
            $new_time = isset($_POST['new_time']) ? sanitize_text_field($_POST['new_time']) : '';
            $result = $appointment->modify($new_date, $new_time);
        } else {
            wp_send_json_error(array('message' => __('Invalid action.', 'gform-booking')));
        }

        if ($result) {
            wp_send_json_success(array('message' => __('Appointment updated successfully.', 'gform-booking')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update appointment.', 'gform-booking')));
        }
    }

    /**
     * Handle public appointment management
     */
    public static function handle_public_management()
    {
        // Get management page ID from settings
        $management_page_id = 0;
        $gf_addon = \GFormBooking\GF_Addon::get_instance();
        if ($gf_addon && method_exists($gf_addon, 'get_plugin_settings')) {
            $settings = $gf_addon->get_plugin_settings();
            $management_page_id = isset($settings['management_page_id']) ? absint($settings['management_page_id']) : 0;
        }

        // Check if this is an appointment management request (new way with token on page, or old way with gf_booking param)
        $is_old_url = isset($_GET['gf_booking']) && $_GET['gf_booking'] === 'manage';
        $has_token = isset($_GET['token']) && !empty($_GET['token']);

        // If we have a management page configured, check if we're on it
        $is_on_management_page = false;
        if ($management_page_id > 0) {
            $is_on_management_page = is_page($management_page_id);
        }

        // If neither old URL nor on management page with token, return early
        if (! $is_old_url && (! $is_on_management_page || ! $has_token)) {
            return;
        }

        // Get token and appointment ID
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        // Try old URL format first (has appointment ID)
        $appointment_id = isset($_GET['appointment']) ? absint($_GET['appointment']) : 0;

        if (! $token) {
            // No token provided - show error or redirect
            wp_die(esc_html__('Invalid request. A valid appointment token is required.', 'gform-booking'));
        }

        // If no appointment ID in URL, we need to find it by token
        if (! $appointment_id) {
            global $wpdb;
            $table = $wpdb->prefix . 'gf_booking_appointments';
            $cache_key = 'gf_booking_token_' . md5($token);
            $appointment_id = wp_cache_get($cache_key, 'gf_booking');

            if (false === $appointment_id) {
                $appointment_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Querying custom appointment table for secure token lookup.
                    $wpdb->prepare(
                        "SELECT id FROM $table WHERE token = %s",
                        $token
                    )
                );

                if ($appointment_id) {
                    // Cache for five minutes to satisfy Plugin Check caching guidance without risking stale data.
                    $cache_ttl = 5 * 60;
                    wp_cache_set($cache_key, (int) $appointment_id, 'gf_booking', $cache_ttl);
                }
            }

            if (! $appointment_id) {
                wp_die(esc_html__('Invalid or expired token.', 'gform-booking'));
            }
        }

        // Verify token and load appointment.
        $appointment = new Appointment($appointment_id);

        if (! $appointment->exists() || ! $appointment->verify_token($token)) {
            wp_die(esc_html__('Invalid or expired token.', 'gform-booking'));
        }

        // Handle modification if requested.
        if (isset($_POST['modify_appointment']) && $_POST['modify_appointment'] === 'yes') {
            // Enforce cutoff if configured.
            $service = new \GFormBooking\Service($appointment->get('service_id'));
            $settings = $service->exists() ? $service->get('settings') : array();
            $cutoff_hours = isset($settings['cutoff_hours']) ? absint($settings['cutoff_hours']) : 0;
            if ($cutoff_hours > 0) {
                $now = current_time('timestamp');
                $appt_ts = strtotime($appointment->get('appointment_date') . ' ' . $appointment->get('start_time'));
                if ($appt_ts - ($cutoff_hours * 3600) <= $now) {
                    /* translators: %d: Number of hours before the appointment. */
                    $error_message = sprintf(esc_html__('Modifications are not allowed within %d hours of the appointment.', 'gform-booking'), $cutoff_hours);
                }
            }

            $new_date = isset($_POST['new_date']) ? sanitize_text_field($_POST['new_date']) : '';
            $new_time = isset($_POST['new_time']) ? sanitize_text_field($_POST['new_time']) : '';

            if ($new_date && $new_time) {
                $result = $appointment->modify($new_date, $new_time);

                if (is_wp_error($result)) {
                    // Rate limit or other error.
                    $error_message = $result->get_error_message();
                } elseif ($result) {
                    // Reload appointment to get updated data.
                    $appointment = new Appointment($appointment_id);
                    $success_message = __('Your appointment has been modified. A confirmation email has been sent.', 'gform-booking');
                } else {
                    $error_message = __('Failed to modify appointment. Please try again.', 'gform-booking');
                }
            } else {
                $error_message = __('Please select a new date and time.', 'gform-booking');
            }
        }

        // Handle cancellation if requested.
        if (isset($_POST['cancel_appointment']) && $_POST['cancel_appointment'] === 'yes') {
            // Enforce cutoff if configured.
            $service = new \GFormBooking\Service($appointment->get('service_id'));
            $settings = $service->exists() ? $service->get('settings') : array();
            $cutoff_hours = isset($settings['cutoff_hours']) ? absint($settings['cutoff_hours']) : 0;
            if ($cutoff_hours > 0) {
                $now = current_time('timestamp');
                $appt_ts = strtotime($appointment->get('appointment_date') . ' ' . $appointment->get('start_time'));
                if ($appt_ts - ($cutoff_hours * 3600) <= $now) {
                    /* translators: %d: Number of hours before the appointment. */
                    $error_message = sprintf(esc_html__('Cancellations are not allowed within %d hours of the appointment.', 'gform-booking'), $cutoff_hours);
                }
            }

            if (empty($error_message) && $appointment->cancel()) {
                $success_message = __('Your appointment has been cancelled.', 'gform-booking');
            } else {
                $error_message = __('Failed to cancel appointment. Please try again.', 'gform-booking');
            }
        }

        // Get formatted dates for template
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        $appointment_date = date_i18n($date_format, strtotime($appointment->get('appointment_date')));
        $start_time = date_i18n($time_format, strtotime($appointment->get('start_time')));
        $end_time = date_i18n($time_format, strtotime($appointment->get('end_time')));
        $status = $appointment->get('status');

        // Get management page ID from settings
        $management_page_id = 0;
        $gf_addon = \GFormBooking\GF_Addon::get_instance();
        if ($gf_addon && method_exists($gf_addon, 'get_plugin_settings')) {
            $settings = $gf_addon->get_plugin_settings();
            $management_page_id = isset($settings['management_page_id']) ? absint($settings['management_page_id']) : 0;
        }

        // If using a management page, inject content via filter
        if ($management_page_id > 0) {
            add_filter('the_content', function ($content) use ($appointment, $token, $success_message, $error_message) {
                // Only inject if we have valid appointment data
                if (!$appointment || !$appointment->exists()) {
                    return $content;
                }

                // Prepare variables for template
                $date_format = get_option('date_format');
                $time_format = get_option('time_format');
                $appointment_date = date_i18n($date_format, strtotime($appointment->get('appointment_date')));
                $start_time = date_i18n($time_format, strtotime($appointment->get('start_time')));
                $end_time = date_i18n($time_format, strtotime($appointment->get('end_time')));
                $status = $appointment->get('status');

                // Render management content
                ob_start();
                include GFORM_BOOKING_PLUGIN_DIR . 'templates/management-content.php';
                $management_content = ob_get_clean();
                return $management_content;
            }, 999);

            // Don't exit, let WordPress show the page normally
            return;
        }

        // Old method: custom rendering
        self::render_public_management_page($appointment, $token, isset($success_message) ? $success_message : '', isset($error_message) ? $error_message : '');
        exit;
    }

    /**
     * Render public appointment management page
     *
     * @param Appointment $appointment Appointment object.
     * @param string      $token Security token.
     * @param string      $success_message Success message.
     * @param string      $error_message Error message.
     */
    private static function render_public_management_page($appointment, $token, $success_message = '', $error_message = '')
    {
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');

        $appointment_date = date_i18n($date_format, strtotime($appointment->get('appointment_date')));
        $start_time = date_i18n($time_format, strtotime($appointment->get('start_time')));
        $end_time = date_i18n($time_format, strtotime($appointment->get('end_time')));
        $status = $appointment->get('status');
        $participants = $appointment->get('participants') ?: 1;

        // Load active theme for proper styling - handle both classic and block themes.
        if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
            // Block theme: Use block theme template loading
?>
            <!DOCTYPE html>
            <html <?php language_attributes(); ?>>

            <head>
                <meta charset="<?php bloginfo('charset'); ?>">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php wp_head(); ?>
            </head>

            <body <?php body_class(); ?>>
                <?php wp_body_open(); ?>
                <div class="wp-site-blocks">
                <?php
                // Block themes use block templates - get header template part if available
                if (locate_template('parts/header.html')) {
                    echo '<header class="wp-block-template-part">';
                    block_template_part('header');
                    echo '</header>';
                }
            } else {
                // Classic theme: use traditional header/footer
                get_header();
            }
                ?>
                <div class="gf-booking-management" style="max-width: 600px; margin: 50px auto; padding: 20px;">
                    <h1><?php esc_html_e('Manage Your Appointment', 'gform-booking'); ?></h1>

                    <?php if ($success_message): ?>
                        <div class="notice notice-success" style="padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 3px; margin: 20px 0;">
                            <p><?php echo esc_html($success_message); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="notice notice-error" style="padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 3px; margin: 20px 0;">
                            <p><?php echo esc_html($error_message); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($status === 'confirmed' || $status === 'changed'): ?>
                        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin: 20px 0;">
                            <h2><?php esc_html_e('Appointment Details', 'gform-booking'); ?></h2>
                            <p><strong><?php esc_html_e('Name:', 'gform-booking'); ?></strong> <?php echo esc_html($appointment->get('customer_name')); ?></p>
                            <p><strong><?php esc_html_e('Date:', 'gform-booking'); ?></strong> <?php echo esc_html($appointment_date); ?></p>
                            <p><strong><?php esc_html_e('Time:', 'gform-booking'); ?></strong> <?php echo esc_html($start_time); ?> - <?php echo esc_html($end_time); ?></p>
                        </div>

                        <?php if ($status === 'confirmed' || $status === 'changed'): ?>
                            <div style="margin: 30px 0; padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 3px;">
                                <h2><?php esc_html_e('Modify Appointment', 'gform-booking'); ?></h2>
                                <p><?php esc_html_e('Need to change your appointment? Select a new date and time below.', 'gform-booking'); ?></p>

                                <?php
                                // Render calendar for date selection.
                                wp_enqueue_style('gf-booking-frontend');
                                wp_enqueue_script('gf-booking-frontend');
                                wp_localize_script('gf-booking-frontend', 'gfBooking', array(
                                    'ajaxUrl' => admin_url('admin-ajax.php'),
                                    'nonce' => wp_create_nonce('gf_booking_nonce'),
                                    'timeFormat' => get_option('time_format'),
                                    'currency'  => \GFormBooking\Admin::get_currency(),
                                    'strings' => array(
                                        'loading' => __('Loading...', 'gform-booking'),
                                        'noSlots' => __('No available time slots', 'gform-booking'),
                                        'error' => __('An error occurred. Please try again.', 'gform-booking'),
                                        // Provide both singular and plural forms for JavaScript
                                        'slot' => array(
                                            'singular' => __('slot', 'gform-booking'),
                                            'plural'   => __('slots', 'gform-booking'),
                                        ),
                                        'spot' => array(
                                            'singular' => __('spot', 'gform-booking'),
                                            'plural'   => __('spots', 'gform-booking'),
                                        ),
                                        'left' => __('left', 'gform-booking'),
                                    ),
                                ));

                                echo wp_kses_post(\GFormBooking\Form_Fields::render_calendar_field('', $appointment->get('service_id')));
                                ?>

                                <form method="post" id="gf-booking-modify-form" style="display: none; margin-top: 20px;">
                                    <input type="hidden" name="modify_appointment" value="yes">
                                    <input type="hidden" name="new_date" id="gf-booking-new-date">
                                    <input type="hidden" name="new_time" id="gf-booking-new-time">
                                    <button type="submit" style="background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer;">
                                        <?php esc_html_e('Confirm New Appointment Time', 'gform-booking'); ?>
                                    </button>
                                </form>

                                <script type="text/javascript">
                                    jQuery(document).ready(function($) {
                                        // Listen for time slot selection.
                                        $(document).on('gf-booking-time-selected', function(e, time) {
                                            var $calendar = $('.gf-booking-calendar');
                                            var selectedDate = '';

                                            if ($calendar.hasClass('gf-booking-month-calendar')) {
                                                selectedDate = $calendar.find('.gf-booking-day.selected').data('date');
                                            } else {
                                                selectedDate = $calendar.find('.gf-booking-date').val();
                                            }

                                            if (selectedDate && time) {
                                                $('#gf-booking-new-date').val(selectedDate);
                                                $('#gf-booking-new-time').val(time);
                                                $('#gf-booking-modify-form').show();
                                            }
                                        });
                                    });
                                </script>
                            </div>
                        <?php endif; ?>

                        <!-- Cancel button: Show for both 'confirmed' and 'changed' status -->
                        <form method="post" style="margin: 20px 0;">
                            <h2><?php esc_html_e('Cancel Appointment', 'gform-booking'); ?></h2>
                            <p><?php esc_html_e('If you need to cancel your appointment, please click the button below. You can always book a new appointment if needed.', 'gform-booking'); ?></p>
                            <input type="hidden" name="cancel_appointment" value="yes">
                            <button type="submit" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to cancel this appointment?', 'gform-booking')); ?>');" style="background: #dc3232; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer;">
                                <?php esc_html_e('Cancel Appointment', 'gform-booking'); ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="notice notice-info" style="padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 3px; margin: 20px 0;">
                            <p><?php esc_html_e('This appointment has already been cancelled.', 'gform-booking'); ?></p>
                        </div>
                    <?php endif; ?>

                    <p style="margin-top: 30px; font-size: 14px; color: #666;">
                        <a href="<?php echo esc_url(home_url()); ?>"><?php esc_html_e('Return to homepage', 'gform-booking'); ?></a>
                    </p>
                </div>
                <?php
                // Load footer for block or classic theme
                if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
                    // Block themes use block templates - get footer template part if available
                    echo '<footer class="wp-block-template-part">';
                    if (locate_template('parts/footer.html')) {
                        block_template_part('footer');
                    }
                    echo '</footer>';
                ?>
                </div>
                <?php wp_footer(); ?>
            </body>

            </html>
        <?php
                } else {
                    // Classic theme: use traditional footer
                    get_footer();
                }
            }

            /**
             * Handle iCal download
             */
            public static function handle_ical_download()
            {
                // Check if this is an iCal download request.
                if (! isset($_GET['gf_booking']) || $_GET['gf_booking'] !== 'ical') {
                    return;
                }

                // Get appointment ID.
                $appointment_id = isset($_GET['appointment']) ? absint($_GET['appointment']) : 0;

                if (! $appointment_id) {
                    wp_die(esc_html__('Invalid request.', 'gform-booking'));
                }

                // Load appointment.
                $appointment = new Appointment($appointment_id);

                if (! $appointment->exists()) {
                    wp_die(esc_html__('Appointment not found.', 'gform-booking'));
                }

                // Generate iCal content.
                $ical_content = Confirmation::generate_ical_content($appointment);

                // Set headers for opening in calendar app (not attachment).
                header('Content-Type: text/calendar; method=PUBLISH; charset=utf-8');
                header('Content-Disposition: inline; filename="appointment-' . $appointment_id . '.ics"');
                header('Content-Length: ' . strlen($ical_content));
                header('Cache-Control: no-cache, must-revalidate');
                header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

                // Output iCal content.
                echo $ical_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Required raw ICS output.
                exit;
            }

            /**
             * Show notice if Gravity Forms is not installed
             */
            public static function gravity_forms_missing_notice()
            {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('GF Booking requires Gravity Forms to be installed and activated.', 'gform-booking'); ?></p>
        </div>
<?php
            }

            /**
             * Get dynamic color CSS based on settings
             * 
             * @return string CSS with color overrides
             */
            public static function get_dynamic_colors_css()
            {
                $gf_addon = \GFormBooking\GF_Addon::get_instance();
                if (!$gf_addon) {
                    return '';
                }

                $settings = $gf_addon->get_plugin_settings();
                if (!isset($settings['colors']) || !is_array($settings['colors'])) {
                    return '';
                }

                $colors = $settings['colors'];
                $css = ':root { ';

                if (!empty($colors['primary'])) {
                    $css .= '--gf-booking-primary: ' . esc_attr($colors['primary']) . '; ';
                }
                if (!empty($colors['primary_hover'])) {
                    $css .= '--gf-booking-primary-hover: ' . esc_attr($colors['primary_hover']) . '; ';
                }
                if (!empty($colors['secondary_bg'])) {
                    $css .= '--gf-booking-secondary-bg: ' . esc_attr($colors['secondary_bg']) . '; ';
                }
                if (!empty($colors['secondary_border'])) {
                    $css .= '--gf-booking-secondary-border: ' . esc_attr($colors['secondary_border']) . '; ';
                }
                if (!empty($colors['success_bg'])) {
                    $css .= '--gf-booking-success-bg: ' . esc_attr($colors['success_bg']) . '; ';
                }
                if (!empty($colors['success_border'])) {
                    $css .= '--gf-booking-success-border: ' . esc_attr($colors['success_border']) . '; ';
                }
                if (!empty($colors['error_bg'])) {
                    $css .= '--gf-booking-error-bg: ' . esc_attr($colors['error_bg']) . '; ';
                }
                if (!empty($colors['error_border'])) {
                    $css .= '--gf-booking-error-border: ' . esc_attr($colors['error_border']) . '; ';
                }
                if (!empty($colors['info_bg'])) {
                    $css .= '--gf-booking-info-bg: ' . esc_attr($colors['info_bg']) . '; ';
                }
                if (!empty($colors['info_border'])) {
                    $css .= '--gf-booking-info-border: ' . esc_attr($colors['info_border']) . '; ';
                }
                if (!empty($colors['warning_bg'])) {
                    $css .= '--gf-booking-warning-bg: ' . esc_attr($colors['warning_bg']) . '; ';
                }
                if (!empty($colors['warning_border'])) {
                    $css .= '--gf-booking-warning-border: ' . esc_attr($colors['warning_border']) . '; ';
                }
                if (!empty($colors['calendar_header_bg'])) {
                    $css .= '--gf-booking-calendar-header-bg: ' . esc_attr($colors['calendar_header_bg']) . '; ';
                }
                if (!empty($colors['calendar_available_bg'])) {
                    $css .= '--gf-booking-calendar-available-bg: ' . esc_attr($colors['calendar_available_bg']) . '; ';
                }
                if (!empty($colors['calendar_unavailable_bg'])) {
                    $css .= '--gf-booking-calendar-unavailable-bg: ' . esc_attr($colors['calendar_unavailable_bg']) . '; ';
                }
                if (!empty($colors['calendar_day_hover'])) {
                    $css .= '--gf-booking-calendar-day-hover: ' . esc_attr($colors['calendar_day_hover']) . '; ';
                }

                $css .= '}';
                return $css;
            }
        }
