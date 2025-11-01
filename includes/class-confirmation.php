<?php

/**
 * Confirmation class for GF Booking plugin
 *
 * @package GFormBooking
 */

namespace GFormBooking;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Confirmation
 */
class Confirmation
{

    /**
     * Send appointment confirmation email
     *
     * @param Appointment $appointment Appointment object.
     * @param string      $token Security token.
     * @return bool
     */
    public function send_appointment_confirmation($appointment, $token)
    {
        if (! $appointment->exists()) {
            return false;
        }

        $to = $appointment->get('customer_email');
        $subject = sprintf(
            /* translators: %s: Site name */
            __('Appointment Confirmation - %s', 'gform-booking'),
            get_bloginfo('name')
        );

        // Get email sender and signature from service settings.
        $service = new \GFormBooking\Service($appointment->get('service_id'));
        $settings = $service->exists() ? $service->get('settings') : array();
        $message = $this->get_confirmation_email_content($appointment, $token, $settings);
        $from_name = isset($settings['email_from_name']) ? $settings['email_from_name'] : get_bloginfo('name');
        $from_email = isset($settings['email_from_email']) ? $settings['email_from_email'] : get_option('admin_email');

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );

        // Attach iCal (.ics) file to improve Outlook handling
        $attachments = array();
        try {
            $ical_content = self::generate_ical_content($appointment);
            $tmp_file = wp_tempnam('appointment-' . $appointment->get_id() . '.ics');
            if ($tmp_file) {
                // Ensure .ics extension for better client recognition
                $ics_path = $tmp_file . '.ics';
                if (@file_put_contents($ics_path, $ical_content) !== false) {
                    $attachments[] = $ics_path;
                }
            }
        } catch (\Throwable $e) {
            // Swallow attachment errors; email can still be sent without attachment
        }

        $sent = wp_mail($to, $subject, $message, $headers, $attachments);

        // Cleanup temp attachment files
        if (!empty($attachments)) {
            foreach ($attachments as $path) {
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
        }

        // Also notify the assigned user/service admin.
        $this->send_admin_notification($appointment);

        return $sent;
    }

    /**
     * Send notification to assigned user
     *
     * @param Appointment $appointment Appointment object.
     */
    private function send_admin_notification($appointment)
    {
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
            __('New Appointment Booking - %s', 'gform-booking'),
            get_bloginfo('name')
        );

        $date_format = get_option('date_format');
        $time_format = get_option('time_format');

        $appointment_date = date_i18n($date_format, strtotime($appointment->get('appointment_date')));
        $start_time = date_i18n($time_format, strtotime($appointment->get('start_time')));
        $end_time = date_i18n($time_format, strtotime($appointment->get('end_time')));
        $service_name = $service->exists() ? $service->get('name') : __('N/A', 'gform-booking');

        $message = sprintf(
            /* translators: %1$s: Customer name, %2$s: Email, %3$s: Phone, %4$s: Date, %5$s: Time, %6$s: Service */
            __("A new appointment has been booked:\n\nCustomer: %1\$s\nEmail: %2\$s\nPhone: %3\$s\nDate: %4\$s\nTime: %5\$s - %6\$s\nService: %7\$s", 'gform-booking'),
            $appointment->get('customer_name'),
            $appointment->get('customer_email'),
            $appointment->get('customer_phone') ?: __('Not provided', 'gform-booking'),
            $appointment_date,
            $start_time . ' - ' . $end_time,
            '',
            $service_name
        );

        // Get email sender from service settings.
        $from_name = isset($settings['email_from_name']) ? $settings['email_from_name'] : get_bloginfo('name');
        $from_email = isset($settings['email_from_email']) ? $settings['email_from_email'] : get_option('admin_email');

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );

        // Attach iCal (.ics) for admins too, so they can add to calendar quickly
        $attachments = array();
        try {
            $ical_content = self::generate_ical_content($appointment);
            $tmp_file = wp_tempnam('appointment-' . $appointment->get_id() . '.ics');
            if ($tmp_file) {
                $ics_path = $tmp_file . '.ics';
                if (@file_put_contents($ics_path, $ical_content) !== false) {
                    $attachments[] = $ics_path;
                }
            }
        } catch (\Throwable $e) {
        }

        $sent = wp_mail($to, $subject, $message, $headers, $attachments);

        if (!empty($attachments)) {
            foreach ($attachments as $path) {
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
        }

        if ($sent) {
            do_action('gf_booking/admin_notification_sent', $appointment->get_id());
        }

        return $sent;
    }

    /**
     * Get confirmation email content
     *
     * @param Appointment $appointment Appointment object.
     * @param string      $token Security token.
     * @return string Email content.
     */
    private function get_confirmation_email_content($appointment, $token, $service_settings = array())
    {
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');

        $appointment_date = date_i18n($date_format, strtotime($appointment->get('appointment_date')));
        $start_time = date_i18n($time_format, strtotime($appointment->get('start_time')));
        $end_time = date_i18n($time_format, strtotime($appointment->get('end_time')));

        $service_signature = isset($service_settings['email_signature']) ? $service_settings['email_signature'] : '';
        $signature_html = $service_signature ? wpautop($service_signature) : '';
        $cutoff_hours = isset($service_settings['cutoff_hours']) ? absint($service_settings['cutoff_hours']) : 0;

        // Create modify/cancel link.
        // Check if a management page is configured
        $gf_addon = \GFormBooking\GF_Addon::get_instance();
        $settings = $gf_addon ? $gf_addon->get_plugin_settings() : array();
        $management_page_id = isset($settings['management_page_id']) ? absint($settings['management_page_id']) : 0;

        if ($management_page_id > 0) {
            // Use configured management page
            $manage_url = add_query_arg(
                array(
                    'token' => $token,
                ),
                get_permalink($management_page_id)
            );
        } else {
            // Fallback to old URL format
            $manage_url = add_query_arg(
                array(
                    'gf_booking'  => 'manage',
                    'appointment' => $appointment->get_id(),
                    'token'       => $token,
                ),
                home_url()
            );
        }

        // Create iCal download link.
        $ical_url = self::get_ical_download_url($appointment->get_id());

        $colors = isset($settings['colors']) && is_array($settings['colors']) ? $settings['colors'] : array();
        $primary = !empty($colors['primary']) ? $colors['primary'] : '#0073aa';
        $primary_hover = !empty($colors['primary_hover']) ? $colors['primary_hover'] : '#005177';
        $button_hover = !empty($colors['primary_hover']) ? $colors['primary_hover'] : $primary;

        ob_start();
?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                }

                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }

                .header {
                    background-color: <?php echo esc_attr($primary); ?>;
                    color: #ffffff;
                    padding: 20px;
                    text-align: center;
                }

                .content {
                    background-color: #f9f9f9;
                    padding: 20px;
                    border: 1px solid #ddd;
                }

                .appointment-details {
                    background-color: #ffffff;
                    padding: 15px;
                    margin: 15px 0;
                    border-left: 4px solid <?php echo esc_attr($primary); ?>;
                }

                .button {
                    display: inline-block;
                    padding: 10px 20px;
                    margin: 10px 5px;
                    text-decoration: none;
                    border-radius: 3px;
                    background-color: <?php echo esc_attr($primary); ?>;
                    color: #ffffff !important;
                }

                .button-secondary {
                    background-color: <?php echo esc_attr($button_hover); ?>;
                    color: #ffffff !important;
                }

                a {
                    color: <?php echo esc_attr($primary); ?>;
                }

                a:hover,
                .button:hover,
                .button-secondary:hover {
                    color: #ffffff !important;
                    background-color: <?php echo esc_attr($primary_hover); ?>;
                }

                .signature {
                    margin-top: 20px;
                    font-size: 14px;
                    color: #444444;
                }
            </style>
        </head>

        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
                </div>
                <div class="content">
                    <h2><?php esc_html_e('Appointment Confirmation', 'gform-booking'); ?></h2>

                    <p><?php esc_html_e('Hello', 'gform-booking'); ?> <?php echo esc_html($appointment->get('customer_name')); ?>,</p>

                    <p><?php esc_html_e('Thank you for your appointment booking:', 'gform-booking'); ?></p>

                    <div class="appointment-details">
                        <p><strong><?php esc_html_e('Date:', 'gform-booking'); ?></strong> <?php echo esc_html($appointment_date); ?></p>
                        <p><strong><?php esc_html_e('Time:', 'gform-booking'); ?></strong> <?php echo esc_html($start_time); ?> - <?php echo esc_html($end_time); ?></p>
                    </div>

                    <p style="text-align: center;">
                        <a href="<?php echo esc_url($ical_url); ?>" class="button button-secondary">
                            📅 <?php esc_html_e('Add to Calendar', 'gform-booking'); ?>
                        </a>
                    </p>

                    <?php if ($cutoff_hours > 0) : ?>
                        <p><?php printf(esc_html__('Changes and cancellations are possible until %d hours before the appointment.', 'gform-booking'), $cutoff_hours); ?></p>
                    <?php endif; ?>

                    <p><?php esc_html_e('If you need to review or adjust your booking, please use the link below:', 'gform-booking'); ?></p>

                    <p style="text-align: center;">
                        <a href="<?php echo esc_url($manage_url); ?>" class="button">
                            <?php esc_html_e('Manage Appointment', 'gform-booking'); ?>
                        </a>
                    </p>

                    <p><?php esc_html_e('If you have any questions, please feel free to contact us.', 'gform-booking'); ?></p>
                </div>

                <?php if (! empty($signature_html)) : ?>
                    <div class="signature">
                        <?php echo wp_kses_post($signature_html); ?>
                    </div>
                <?php endif; ?>
            </div>
        </body>

        </html>
<?php
        return ob_get_clean();
    }

    /**
     * Generate iCal download link for appointment
     *
     * @param int $appointment_id Appointment ID.
     * @return string iCal download URL.
     */
    public static function get_ical_download_url($appointment_id)
    {
        return add_query_arg(
            array(
                'gf_booking' => 'ical',
                'appointment' => $appointment_id,
            ),
            home_url()
        );
    }

    /**
     * Generate iCal file content
     *
     * @param Appointment $appointment Appointment object.
     * @return string iCal content.
     */
    public static function generate_ical_content($appointment)
    {
        $date_start = $appointment->get('appointment_date') . ' ' . $appointment->get('start_time');
        $date_end = $appointment->get('appointment_date') . ' ' . $appointment->get('end_time');

        // Convert to UTC timestamps.
        $timestamp_start = get_gmt_from_date($date_start, 'U');
        $timestamp_end = get_gmt_from_date($date_end, 'U');

        // Format for iCal.
        $dtstart = gmdate('Ymd\THis\Z', $timestamp_start);
        $dtend = gmdate('Ymd\THis\Z', $timestamp_end);

        // Get service name.
        $service = new \GFormBooking\Service($appointment->get('service_id'));
        $service_name = $service->exists() ? $service->get('name') : __('Appointment', 'gform-booking');

        $summary = sprintf(
            /* translators: %s: Customer name. */
            __('Appointment: %s', 'gform-booking'),
            sanitize_text_field($appointment->get('customer_name'))
        );

        // Build description with all available information.
        $description_parts = array();
        $description_parts[] = sprintf(
            /* translators: %s: Customer name. */
            __('Customer: %s', 'gform-booking'),
            $appointment->get('customer_name')
        );
        $description_parts[] = sprintf(
            /* translators: %s: Customer email address. */
            __('Email: %s', 'gform-booking'),
            $appointment->get('customer_email')
        );

        if ($appointment->get('customer_phone')) {
            $description_parts[] = sprintf(
                /* translators: %s: Customer phone number. */
                __('Phone: %s', 'gform-booking'),
                $appointment->get('customer_phone')
            );
        }

        if ($service_name) {
            $description_parts[] = sprintf(
                /* translators: %s: Service name. */
                __('Service: %s', 'gform-booking'),
                $service_name
            );
        }

        $description = implode('\\n', $description_parts);

        $location = get_bloginfo('name');
        $uid = 'appointment-' . $appointment->get('id') . '@' . parse_url(home_url(), PHP_URL_HOST);

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//" . get_bloginfo('name') . "//GF Booking//EN\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        // Use PUBLISH for a normal appointment (not a meeting request)
        $ical .= "METHOD:PUBLISH\r\n";
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:" . $uid . "\r\n";
        $ical .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        $ical .= "DTSTART:" . $dtstart . "\r\n";
        $ical .= "DTEND:" . $dtend . "\r\n";
        $ical .= "SUMMARY:" . $summary . "\r\n";
        $ical .= "DESCRIPTION:" . str_replace(array("\r", "\n"), array("\\r", "\\n"), $description) . "\r\n";
        $ical .= "LOCATION:" . $location . "\r\n";
        $ical .= "STATUS:CONFIRMED\r\n";
        $ical .= "SEQUENCE:0\r\n";
        $ical .= "END:VEVENT\r\n";
        $ical .= "END:VCALENDAR\r\n";

        return $ical;
    }
}
