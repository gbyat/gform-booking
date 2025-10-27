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
            $table = $wpdb->prefix . 'gf_booking_appointments';
            $this->data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $appointment_id), ARRAY_A);

            if ($this->data) {
                $this->id = $appointment_id;
                if (! empty($this->data['settings'])) {
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
            'status'           => sanitize_text_field($data['status']),
            'token'            => $token,
            'notes'            => wp_kses_post($data['notes']),
            'settings'         => wp_json_encode($data['settings']),
            'created_at'       => current_time('mysql'),
            'updated_at'       => current_time('mysql'),
        );

        $result = $wpdb->insert($table, $insert_data);

        if ($result) {
            $appointment_id = $wpdb->insert_id;

            // Send confirmation email.
            self::send_confirmation_email($appointment_id, $token);

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
        if (isset($data['settings'])) {
            $update_data['settings'] = wp_json_encode($data['settings']);
        }

        $update_data['updated_at'] = current_time('mysql');

        return $wpdb->update(
            $table,
            $update_data,
            array('id' => $this->id)
        );
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
     * Modify appointment date/time
     *
     * @param string $new_date New date.
     * @param string $new_time New time.
     * @return bool
     */
    public function modify($new_date, $new_time)
    {
        // Calculate end time based on original duration.
        $start = strtotime($this->get('start_time'));
        $end = strtotime($this->get('end_time'));
        $duration = $end - $start;

        $new_end = date('H:i:s', strtotime($new_time) + $duration);

        return $this->update(
            array(
                'appointment_date' => $new_date,
                'start_time'       => $new_time,
                'end_time'         => $new_end,
            )
        );
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
}
