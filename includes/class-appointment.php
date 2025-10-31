<?php

/**
 * Appointment class for GF Booking plugin
 *
 * @package GFormBooking
 */

namespace GFormBooking;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('WP_Error')) {
    $abs_path = defined('ABSPATH') ? constant('ABSPATH') : dirname(__FILE__, 4) . '/';
    $wpinc    = defined('WPINC') ? constant('WPINC') : 'wp-includes';
    require_once $abs_path . $wpinc . '/class-wp-error.php';
}

/**
 * Class Appointment
 *
 * @phpcsSuppress WordPress.Namespaces.PrefixAllGlobals.NonPrefixedClassFound
 */
class Appointment_Error extends \WP_Error {} // phpcs:ignore WordPress.Namespaces.PrefixAllGlobals.NonPrefixedClassFound

/**
 * Class Appointment
 */
class Appointment
{

    /**
     * Appointment ID
     *
     * @var int
     */
    private $id;

    /**
     * Appointment data
     *
     * @var array
     */
    private $data;

    /**
     * Constructor
     *
     * @param int $appointment_id Appointment ID.
     */
    public function __construct($appointment_id = 0)
    {
        global $wpdb;

        if ($appointment_id > 0) {
            $table     = $wpdb->prefix . 'gf_booking_appointments';
            $cache_key = 'gf_booking_appointment_' . absint($appointment_id);
            $this->data = wp_cache_get($cache_key, 'gf_booking');

            if (false === $this->data) {
                $this->data = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Querying plugin-managed appointments table.
                    $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $appointment_id),
                    'ARRAY_A'
                );

                if ($this->data) {
                    $minute_constant = defined('MINUTE_IN_SECONDS') ? constant('MINUTE_IN_SECONDS') : 60;
                    $cache_ttl = $minute_constant * 5;
                    wp_cache_set($cache_key, $this->data, 'gf_booking', $cache_ttl);
                }
            }

            if ($this->data) {
                $this->id = $appointment_id;
                if (! empty($this->data['settings']) && is_string($this->data['settings'])) {
                    $this->data['settings'] = json_decode($this->data['settings'], true);
                }
            }
        }
    }

    /**
     * Create a new appointment
     *
     * @param array $data Appointment data.
     * @return int|false Appointment ID or false on failure.
     */
    public static function create($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'gf_booking_appointments';

        $defaults = array(
            'service_id'      => 0,
            'entry_id'        => 0,
            'form_id'         => 0,
            'customer_name'   => '',
            'customer_email'  => '',
            'customer_phone'  => '',
            'appointment_date' => '',
            'start_time'      => '',
            'end_time'        => '',
            'participants'    => 1,
            'status'          => 'confirmed',
            'notes'           => '',
            'settings'        => array(),
        );

        $data = wp_parse_args($data, $defaults);

        // Generate unique token.
        $token = wp_generate_password(32, false);

        $insert_data = array(
            'service_id'       => absint($data['service_id']),
            'entry_id'         => absint($data['entry_id']),
            'form_id'          => absint($data['form_id']),
            'customer_name'    => sanitize_text_field($data['customer_name']),
            'customer_email'   => sanitize_email($data['customer_email']),
            'customer_phone'   => sanitize_text_field($data['customer_phone']),
            'appointment_date' => sanitize_text_field($data['appointment_date']),
            'start_time'       => sanitize_text_field($data['start_time']),
            'end_time'         => sanitize_text_field($data['end_time']),
            'participants'     => absint($data['participants']),
            'status'           => sanitize_text_field($data['status']),
            'token'            => $token,
            'notes'            => wp_kses_post($data['notes']),
            'settings'         => wp_json_encode($data['settings']),
            'created_at'       => current_time('mysql'),
            'updated_at'       => current_time('mysql'),
        );

        $result = $wpdb->insert($table, $insert_data); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Inserting appointment into custom table.

        if ($result) {
            $appointment_id = $wpdb->insert_id;

            // Send confirmation email.
            self::send_confirmation_email($appointment_id, $token);

            $cache_key   = 'gf_booking_appointment_' . absint($appointment_id);
            $cached_row  = $insert_data;
            $cached_row['id'] = $appointment_id;
            $minute_constant = defined('MINUTE_IN_SECONDS') ? constant('MINUTE_IN_SECONDS') : 60;
            $cache_ttl  = $minute_constant * 5;
            wp_cache_set($cache_key, $cached_row, 'gf_booking', $cache_ttl);

            self::clear_calendar_cache(
                $insert_data['service_id'],
                $insert_data['appointment_date'],
                $insert_data['start_time'],
                $insert_data['end_time']
            );

            return $appointment_id;
        }

        return false;
    }

    /**
     * Update appointment
     *
     * @param array $data Appointment data.
     * @return bool
     */
    public function update($data)
    {
        global $wpdb;

        if (! $this->id) {
            return false;
        }

        $table = $wpdb->prefix . 'gf_booking_appointments';

        $update_data = array();

        if (isset($data['appointment_date'])) {
            $update_data['appointment_date'] = sanitize_text_field($data['appointment_date']);
        }
        if (isset($data['start_time'])) {
            $update_data['start_time'] = sanitize_text_field($data['start_time']);
        }
        if (isset($data['end_time'])) {
            $update_data['end_time'] = sanitize_text_field($data['end_time']);
        }
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }
        if (isset($data['notes'])) {
            $update_data['notes'] = wp_kses_post($data['notes']);
        }
        if (isset($data['customer_name'])) {
            $update_data['customer_name'] = sanitize_text_field($data['customer_name']);
        }
        if (isset($data['customer_email'])) {
            $update_data['customer_email'] = sanitize_email($data['customer_email']);
        }
        if (isset($data['customer_phone'])) {
            $update_data['customer_phone'] = sanitize_text_field($data['customer_phone']);
        }
        if (isset($data['participants'])) {
            $update_data['participants'] = absint($data['participants']);
        }
        if (isset($data['settings'])) {
            $update_data['settings'] = wp_json_encode($data['settings']);
        }

        $update_data['updated_at'] = current_time('mysql');

        $original_data = $this->data ? $this->data : array();

        $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Updating appointment row in custom table.
            $table,
            $update_data,
            array('id' => $this->id)
        );

        if ($updated) {
            wp_cache_delete('gf_booking_appointment_' . absint($this->id), 'gf_booking');

            $original_service = isset($original_data['service_id']) ? absint($original_data['service_id']) : 0;
            $original_date    = isset($original_data['appointment_date']) ? $original_data['appointment_date'] : '';
            $original_start   = isset($original_data['start_time']) ? $original_data['start_time'] : '';
            $original_end     = isset($original_data['end_time']) ? $original_data['end_time'] : '';

            if ($original_service && $original_date) {
                self::clear_calendar_cache($original_service, $original_date, $original_start, $original_end);
            }

            $new_service = isset($update_data['service_id']) ? absint($update_data['service_id']) : $original_service;
            $new_date    = isset($update_data['appointment_date']) ? $update_data['appointment_date'] : $original_date;
            $new_start   = isset($update_data['start_time']) ? $update_data['start_time'] : $original_start;
            $new_end     = isset($update_data['end_time']) ? $update_data['end_time'] : $original_end;

            if ($new_service && $new_date) {
                self::clear_calendar_cache($new_service, $new_date, $new_start, $new_end);
            }

            return true;
        }

        return false;
    }

    /**
     * Cancel appointment
     *
     * @return bool
     */
    public function cancel()
    {
        $result = $this->update(array('status' => 'cancelled'));

        // Send cancellation notification to admin.
        if ($result) {
            self::send_cancellation_notification($this->id);
        }

        return $result;
    }

    /**
     * Send cancellation notification email to admin
     *
     * @param int $appointment_id Appointment ID.
     */
    private static function send_cancellation_notification($appointment_id)
    {
        $appointment = new Appointment($appointment_id);

        if (! $appointment->exists()) {
            return;
        }

        // Get the user to notify from service settings.
        $service = new \GFormBooking\Service($appointment->get('service_id'));
        $settings = $service->exists() ? $service->get('settings') : array();
        $notify_user_id = isset($settings['notify_user_id']) ? absint($settings['notify_user_id']) : 0;

        // Get user email (default to admin_email).
        if ($notify_user_id > 0) {
            $user = get_userdata($notify_user_id);
            $to = $user ? $user->user_email : get_option('admin_email');
        } else {
            $to = get_option('admin_email');
        }

        $subject = sprintf(
            /* translators: %s: Site name */
            __('Appointment Cancelled - %s', 'gform-booking'),
            get_bloginfo('name')
        );

        $date_format = get_option('date_format');
        $time_format = get_option('time_format');

        $appointment_date = date_i18n($date_format, strtotime($appointment->get('appointment_date')));
        $start_time = date_i18n($time_format, strtotime($appointment->get('start_time')));
        $end_time = date_i18n($time_format, strtotime($appointment->get('end_time')));

        // Get service name.
        $service = new \GFormBooking\Service($appointment->get('service_id'));
        $service_name = $service->exists() ? $service->get('name') : __('N/A', 'gform-booking');

        $message = sprintf(
            /* translators: %1$s: Customer name, %2$s: Date, %3$s: Time, %4$s: Service name */
            __("An appointment has been cancelled:\n\nCustomer: %1\$s\nEmail: %2\$s\nPhone: %3\$s\nDate: %4\$s\nTime: %5\$s - %6\$s\nService: %7\$s\n\nThis time slot is now available again.", 'gform-booking'),
            $appointment->get('customer_name'),
            $appointment->get('customer_email'),
            $appointment->get('customer_phone') ?: __('Not provided', 'gform-booking'),
            $appointment_date,
            $start_time,
            $end_time,
            $service_name
        );

        // Get email sender from service settings.
        $from_name = isset($settings['email_from_name']) ? $settings['email_from_name'] : get_bloginfo('name');
        $from_email = isset($settings['email_from_email']) ? $settings['email_from_email'] : get_option('admin_email');

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );

        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Send modification notification email to admin and customer
     *
     * @param int    $appointment_id Appointment ID.
     * @param string $token Security token.
     */
    private static function send_modification_notification($appointment_id, $token)
    {
        $appointment = new Appointment($appointment_id);

        if (! $appointment->exists()) {
            return;
        }

        // Get the user to notify from service settings.
        $service = new \GFormBooking\Service($appointment->get('service_id'));
        $settings = $service->exists() ? $service->get('settings') : array();
        $notify_user_id = isset($settings['notify_user_id']) ? absint($settings['notify_user_id']) : 0;

        // Get user email (default to admin_email).
        if ($notify_user_id > 0) {
            $user = get_userdata($notify_user_id);
            $admin_to = $user ? $user->user_email : get_option('admin_email');
        } else {
            $admin_to = get_option('admin_email');
        }

        // Get service name.
        $service_name = $service->exists() ? $service->get('name') : __('N/A', 'gform-booking');

        $date_format = get_option('date_format');
        $time_format = get_option('time_format');

        $appointment_date = date_i18n($date_format, strtotime($appointment->get('appointment_date')));
        $start_time = date_i18n($time_format, strtotime($appointment->get('start_time')));
        $end_time = date_i18n($time_format, strtotime($appointment->get('end_time')));

        // Get email sender from service settings.
        $from_name = isset($settings['email_from_name']) ? $settings['email_from_name'] : get_bloginfo('name');
        $from_email = isset($settings['email_from_email']) ? $settings['email_from_email'] : get_option('admin_email');

        // Send notification to admin.
        $admin_subject = sprintf(
            /* translators: %s: Site name */
            __('Appointment Modified - %s', 'gform-booking'),
            get_bloginfo('name')
        );

        $admin_message = sprintf(
            /* translators: %1$s: Customer name, %2$s: Email, %3$s: Phone, %4$s: Date, %5$s: Time, %6$s: Service */
            __("An appointment has been modified:\n\nCustomer: %1\$s\nEmail: %2\$s\nPhone: %3\$s\nNew Date: %4\$s\nNew Time: %5\$s - %6\$s\nService: %7\$s", 'gform-booking'),
            $appointment->get('customer_name'),
            $appointment->get('customer_email'),
            $appointment->get('customer_phone') ?: __('Not provided', 'gform-booking'),
            $appointment_date,
            $start_time,
            $end_time,
            $service_name
        );

        $admin_headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );

        wp_mail($admin_to, $admin_subject, $admin_message, $admin_headers);

        // Send confirmation to customer.
        $confirmation = new Confirmation();
        $confirmation->send_appointment_confirmation($appointment, $token);
    }

    /**
     * Modify appointment date/time
     *
     * @param string $new_date New date.
     * @param string $new_time New time.
     * @return bool|Appointment_Error True on success, WP_Error on failure.
     */
    public function modify($new_date, $new_time)
    {
        // Check rate limit: max 1 modification per 5 minutes, max 5 modifications total.
        $settings = $this->get('settings');
        if (empty($settings)) {
            $settings = array();
        }

        // Check modification count (max 5).
        $modification_count = isset($settings['modification_count']) ? absint($settings['modification_count']) : 0;
        if ($modification_count >= 5) {
            return $this->create_error(
                'max_modifications_exceeded',
                __('Maximum number of modifications (5) reached for this appointment. Please contact support if you need further changes.', 'gform-booking')
            );
        }

        // Check time limit: min 5 minutes between modifications.
        if (isset($settings['last_modification'])) {
            $last_modification = absint($settings['last_modification']);
            $current_time = time();
            $time_diff = $current_time - $last_modification;
            $minutes_since_modification = $time_diff / 60;

            if ($minutes_since_modification < 5) {
                // Less than 5 minutes since last modification.
                $minutes_remaining = ceil(5 - $minutes_since_modification);
                return $this->create_error(
                    'rate_limit_exceeded',
                    sprintf(
                        /* translators: %d: minutes remaining */
                        __('Please wait %d more minutes before modifying your appointment again.', 'gform-booking'),
                        $minutes_remaining
                    )
                );
            }
        }

        // Calculate end time based on original duration.
        $start = strtotime($this->get('start_time') . ' UTC');
        if (false === $start) {
            $start = strtotime($this->get('start_time'));
        }
        $end = strtotime($this->get('end_time') . ' UTC');
        if (false === $end) {
            $end = strtotime($this->get('end_time'));
        }
        $duration = $end - $start;

        $new_start_timestamp = strtotime($new_time . ' UTC');
        if (false === $new_start_timestamp) {
            $new_start_timestamp = strtotime($new_time);
        }
        $new_end = gmdate('H:i:s', $new_start_timestamp + $duration);

        // Keep the original token - don't change it.
        $current_token = $this->get('token');

        // Update modification tracking.
        $settings['last_modification'] = time();
        $settings['modification_count'] = isset($settings['modification_count']) ? absint($settings['modification_count']) + 1 : 1;

        $result = $this->update(
            array(
                'appointment_date' => $new_date,
                'start_time'       => $new_time,
                'end_time'         => $new_end,
                'status'           => 'changed',
                'settings'         => $settings,
            )
        );

        // Send modification notification emails.
        if ($result) {
            self::send_modification_notification($this->id, $current_token);
        }

        return $result;
    }

    /**
     * Verify token
     *
     * @param string $token Token to verify.
     * @return bool
     */
    public function verify_token($token)
    {
        return isset($this->data['token']) && hash_equals($this->data['token'], $token);
    }

    /**
     * Get appointment data
     *
     * @param string $key Data key.
     * @return mixed
     */
    public function get($key = '')
    {
        if (! $key) {
            return $this->data;
        }

        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    /**
     * Get the appointment ID
     *
     * @return int
     */
    public function get_id()
    {
        return $this->id;
    }

    /**
     * Check if appointment exists
     *
     * @return bool
     */
    public function exists()
    {
        return ! empty($this->data);
    }

    /**
     * Send confirmation email
     *
     * @param int    $appointment_id Appointment ID.
     * @param string $token Security token.
     */
    private static function send_confirmation_email($appointment_id, $token)
    {
        $appointment = new Appointment($appointment_id);

        if (! $appointment->exists()) {
            return;
        }

        $confirmation = new Confirmation();
        $confirmation->send_appointment_confirmation($appointment, $token);
    }

    /**
     * Clear cached calendar data impacted by appointment changes.
     *
     * @param int         $service_id Service ID.
     * @param string|null $date       Appointment date (Y-m-d).
     * @param string|null $start_time Start time (H:i:s).
     * @param string|null $end_time   End time (H:i:s).
     * @return void
     */
    private static function clear_calendar_cache($service_id, $date = null, $start_time = null, $end_time = null)
    {
        $service_id = absint($service_id);
        if (! $service_id || empty($date)) {
            return;
        }

        $date_key = sanitize_key($date);
        wp_cache_delete(sprintf('gf_booking_booked_slots_%d_%s', $service_id, $date_key), 'gf_booking');

        if (! empty($start_time) && ! empty($end_time)) {
            $start_key = sanitize_key(str_replace(':', '-', $start_time));
            $end_key   = sanitize_key(str_replace(':', '-', $end_time));
            wp_cache_delete(sprintf('gf_booking_slot_count_%d_%s_%s_%s', $service_id, $date_key, $start_key, $end_key), 'gf_booking');
        }
    }

    /**
     * Get the token
     *
     * @return string
     */
    private function get_token()
    {
        return $this->get('token');
    }

    /**
     * Create a WP_Error instance.
     *
     * @param string $code Error code.
     * @param string $message Error message.
     * @return Appointment_Error
     */
    private function create_error($code, $message)
    {
        return new Appointment_Error($code, $message);
    }
}
