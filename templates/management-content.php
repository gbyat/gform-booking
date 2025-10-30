<?php

/**
 * Template for appointment management content
 * This file is included when rendering the management page
 *
 * @var Appointment $appointment
 * @var string $token
 * @var string $success_message
 * @var string $error_message
 */


?>
<div class="gf-booking-management" style="max-width: 960px; margin: 0 auto; padding: 20px;">

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