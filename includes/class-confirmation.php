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

        $message = $this->get_confirmation_email_content($appointment, $token);

        // Get email sender from service settings.
        $service = new \GFormBooking\Service($appointment->get('service_id'));
        $settings = $service->exists() ? $service->get('settings') : array();
        $from_name = isset($settings['email_from_name']) ? $settings['email_from_name'] : get_bloginfo('name');
        $from_email = isset($settings['email_from_email']) ? $settings['email_from_email'] : get_option('admin_email');

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );

        error_log('GF Booking: Sending confirmation email to: ' . $to);
        $sent = wp_mail($to, $subject, $message, $headers);
        error_log('GF Booking: Email sent: ' . ($sent ? 'yes' : 'no'));

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

        error_log('GF Booking: Sending admin notification to: ' . $to);
        $sent = wp_mail($to, $subject, $message, $headers);
        error_log('GF Booking: Admin notification sent: ' . ($sent ? 'yes' : 'no'));
    }

    /**
     * Get confirmation email content
     *
     * @param Appointment $appointment Appointment object.
     * @param string      $token Security token.
     * @return string Email content.
     */
    private function get_confirmation_email_content($appointment, $token)
    {
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');

        $appointment_date = date_i18n($date_format, strtotime($appointment->get('appointment_date')));
        $start_time = date_i18n($time_format, strtotime($appointment->get('start_time')));
        $end_time = date_i18n($time_format, strtotime($appointment->get('end_time')));

        // Create modify/cancel link.
        $manage_url = add_query_arg(
            array(
                'gf_booking'  => 'manage',
                'appointment' => $appointment->get_id(),
                'token'       => $token,
            ),
            home_url()
        );

        // Create iCal download link.
        $ical_url = self::get_ical_download_url($appointment->get_id());

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
                    background-color: #0073aa;
                    color: white;
                    padding: 20px;
                    text-align: center;
                }

                .content {
                    background-color: #f9f9f9;
                    padding: 20px;
                    border: 1px solid #ddd;
                }

                .appointment-details {
                    background-color: white;
                    padding: 15px;
                    margin: 15px 0;
                    border-left: 4px solid #0073aa;
                }

                .button {
                    display: inline-block;
                    padding: 10px 20px;
                    margin: 10px 5px;
                    text-decoration: none;
                    border-radius: 3px;
                }

                .button-modify {
                    background-color: #0073aa;
                    color: white;
                }

                .button-cancel {
                    background-color: #dc3232;
                    color: white;
                }

                .footer {
                    margin-top: 20px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                    font-size: 12px;
                    color: #666;
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

                    <p><?php esc_html_e('Your appointment has been confirmed:', 'gform-booking'); ?></p>

                    <div class="appointment-details">
                        <p><strong><?php esc_html_e('Date:', 'gform-booking'); ?></strong> <?php echo esc_html($appointment_date); ?></p>
                        <p><strong><?php esc_html_e('Time:', 'gform-booking'); ?></strong> <?php echo esc_html($start_time); ?> - <?php echo esc_html($end_time); ?></p>
                    </div>

                    <p><?php esc_html_e('Need to make changes? You can modify or cancel your appointment using the links below:', 'gform-booking'); ?></p>

                    <p style="text-align: center;">
                        <a href="<?php echo esc_url($manage_url); ?>" class="button button-modify">
                            <?php esc_html_e('Modify or Cancel Appointment', 'gform-booking'); ?>
                        </a>
                        <a href="<?php echo esc_url($ical_url); ?>" class="button" style="background-color: #666; color: white;">
                            📅 <?php esc_html_e('Add to Calendar', 'gform-booking'); ?>
                        </a>
                    </p>

                    <p><?php esc_html_e('If you have any questions, please contact us.', 'gform-booking'); ?></p>
                </div>

                <div class="footer">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: Site name */
                            esc_html__('This email was sent by %s.', 'gform-booking'),
                            get_bloginfo('name')
                        );
                        ?>
                    </p>
                </div>
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

        $summary = sprintf(
            __('Appointment: %s', 'gform-booking'),
            sanitize_text_field($appointment->get('customer_name'))
        );

        $description = sprintf(
            __("Customer: %s\nEmail: %s\nPhone: %s\nService: %s", 'gform-booking'),
            $appointment->get('customer_name'),
            $appointment->get('customer_email'),
            $appointment->get('customer_phone'),
            $appointment->get('service_name')
        );

        $location = get_bloginfo('name');
        $uid = 'appointment-' . $appointment->get('id') . '@' . parse_url(home_url(), PHP_URL_HOST);

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//" . get_bloginfo('name') . "//GF Booking//EN\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:REQUEST\r\n";
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
