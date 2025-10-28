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
        add_action('template_redirect', array(__CLASS__, 'handle_public_management'));

        // iCal download handler.
        add_action('template_redirect', array(__CLASS__, 'handle_ical_download'));
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
                'strings'   => array(
                    'loading'   => __('Loading...', 'gform-booking'),
                    'noSlots'   => __('No available time slots', 'gform-booking'),
                    'error'     => __('An error occurred. Please try again.', 'gform-booking'),
                    'confirm'   => __('Are you sure?', 'gform-booking'),
                    'success'   => __('Success!', 'gform-booking'),
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
        // Check if this is an appointment management request.
        if (! isset($_GET['gf_booking']) || $_GET['gf_booking'] !== 'manage') {
            return;
        }

        // Get appointment ID and token.
        $appointment_id = isset($_GET['appointment']) ? absint($_GET['appointment']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        if (! $appointment_id || ! $token) {
            wp_die(__('Invalid request.', 'gform-booking'));
        }

        // Verify token and load appointment.
        $appointment = new Appointment($appointment_id);

        if (! $appointment->exists() || ! $appointment->verify_token($token)) {
            wp_die(__('Invalid or expired token.', 'gform-booking'));
        }

        // Handle modification if requested.
        if (isset($_POST['modify_appointment']) && $_POST['modify_appointment'] === 'yes') {
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
            if ($appointment->cancel()) {
                $success_message = __('Your appointment has been cancelled.', 'gform-booking');
            } else {
                $error_message = __('Failed to cancel appointment. Please try again.', 'gform-booking');
            }
        }

        // Display management page.
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

        // Load active theme for proper styling.
        get_header();
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
                            'strings' => array(
                                'loading' => __('Loading...', 'gform-booking'),
                                'noSlots' => __('No available time slots', 'gform-booking'),
                                'error' => __('An error occurred. Please try again.', 'gform-booking'),
                            ),
                        ));

                        echo \GFormBooking\Form_Fields::render_calendar_field('', $appointment->get('service_id'));
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
        get_footer();
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
            wp_die(__('Invalid request.', 'gform-booking'));
        }

        // Load appointment.
        $appointment = new Appointment($appointment_id);

        if (! $appointment->exists()) {
            wp_die(__('Appointment not found.', 'gform-booking'));
        }

        // Generate iCal content.
        $ical_content = Confirmation::generate_ical_content($appointment);

        // Set headers for opening in calendar app (not attachment).
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="appointment-' . $appointment_id . '.ics"');
        header('Content-Length: ' . strlen($ical_content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

        // Output iCal content.
        echo $ical_content;
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
}
