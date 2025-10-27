<?php

/**
 * Calendar class for GF Booking plugin
 *
 * @package GFormBooking
 */

namespace GFormBooking;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Calendar
 */
class Calendar
{

    /**
     * Service ID
     *
     * @var int
     */
    private $service_id;

    /**
     * Service object
     *
     * @var Service
     */
    private $service;

    /**
     * Constructor
     *
     * @param int $service_id Service ID.
     */
    public function __construct($service_id = 0)
    {
        $this->service_id = $service_id;
        $this->service = new Service($service_id);
    }

    /**
     * Get available time slots for a specific date
     *
     * @param string $date Date in Y-m-d format.
     * @return array Array of available time slots.
     */
    public function get_available_slots($date)
    {
        if (! $this->service->exists()) {
            return array();
        }

        // Check if date is valid.
        if (! $this->is_valid_date($date)) {
            return array();
        }

        // Check if date is an allowed weekday.
        if (! $this->is_allowed_weekday($date)) {
            return array();
        }

        // Get all time slots for the day.
        $all_slots = $this->generate_time_slots();

        // If no slots generated, return empty array.
        if (empty($all_slots)) {
            return array();
        }

        // Get booked appointments for this date.
        $booked_slots = $this->get_booked_slots($date);

        // Filter out booked slots.
        $available_slots = array();
        foreach ($all_slots as $slot) {
            if (! $this->is_slot_booked($slot, $booked_slots)) {
                $available_slots[] = $slot;
            }
        }

        return $available_slots;
    }

    /**
     * Check if a date is valid (future date, not same day)
     *
     * @param string $date Date in Y-m-d format.
     * @return bool
     */
    private function is_valid_date($date)
    {
        $date_time = strtotime($date);
        $today = strtotime('today');

        // Must be tomorrow or later.
        if ($date_time <= $today) {
            return false;
        }

        // Check if date is excluded.
        if ($this->is_excluded_date($date)) {
            return false;
        }

        // Check max booking date settings.
        $settings = $this->service->get('settings');
        $max_booking_type = isset($settings['max_booking_type']) ? $settings['max_booking_type'] : 'days_ahead';

        if ($max_booking_type === 'fixed_date') {
            // Check if date is before or on the fixed end date.
            $max_date = isset($settings['max_booking_date']) ? strtotime($settings['max_booking_date']) : null;
            if ($max_date && $date_time > $max_date) {
                return false;
            }
        } elseif ($max_booking_type === 'days_ahead') {
            // Check if date is within the specified number of days.
            $max_days = isset($settings['max_booking_days']) ? absint($settings['max_booking_days']) : 60;
            $max_date = strtotime("+{$max_days} days", $today);
            if ($date_time > $max_date) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a date is excluded
     *
     * @param string $date Date in Y-m-d format.
     * @return bool
     */
    private function is_excluded_date($date)
    {
        $settings = $this->service->get('settings');
        $excluded_dates = isset($settings['excluded_dates']) ? $settings['excluded_dates'] : array();

        if (empty($excluded_dates)) {
            return false;
        }

        // Handle both array and comma-separated string.
        if (is_string($excluded_dates)) {
            $excluded_dates = explode(',', $excluded_dates);
        }

        return in_array($date, $excluded_dates, true);
    }

    /**
     * Check if a date is an allowed weekday
     *
     * @param string $date Date in Y-m-d format.
     * @return bool
     */
    private function is_allowed_weekday($date)
    {
        $weekdays = $this->service->get('weekdays');

        if (empty($weekdays)) {
            return true; // All weekdays allowed if none specified.
        }

        // weekdays is already decoded in Service constructor, but ensure it's an array.
        if (! is_array($weekdays)) {
            // Try JSON decode first (new format).
            $decoded = json_decode($weekdays, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $weekdays = $decoded;
            } else {
                // Fallback to unserialize for old data.
                $weekdays = maybe_unserialize($weekdays);
            }
        }

        $day_of_week = date('N', strtotime($date)); // 1-7 (Monday-Sunday).

        // Convert to int for comparison.
        $day_of_week_int = (int) $day_of_week;
        $weekdays_int = array_map('intval', $weekdays);

        return in_array($day_of_week_int, $weekdays_int, false);
    }

    /**
     * Generate all time slots for the service
     *
     * @return array Array of time slots.
     */
    private function generate_time_slots()
    {
        $start_time = $this->service->get('start_time');
        $end_time = $this->service->get('end_time');
        $slot_duration = $this->service->get('slot_duration');
        $buffer_time = $this->service->get('buffer_time');

        // Check settings for slot type.
        $settings = $this->service->get('settings');
        $slot_type = isset($settings['slot_type']) ? $settings['slot_type'] : 'time';

        // Handle full day or half day slots.
        if ($slot_type === 'full_day' || $slot_type === 'half_day') {
            $day_slots = $this->generate_day_slots($slot_type, $settings);
            if (! empty($day_slots)) {
                return $day_slots;
            }
        }

        // Default: Generate normal time slots.
        $slots = array();

        // Check for lunch break.
        $has_lunch_break = isset($settings['has_lunch_break']) && $settings['has_lunch_break'];
        $lunch_break_start = isset($settings['lunch_break_start']) ? strtotime($settings['lunch_break_start']) : null;
        $lunch_break_end = isset($settings['lunch_break_end']) ? strtotime($settings['lunch_break_end']) : null;

        $start = strtotime($start_time);
        $end = strtotime($end_time);
        $duration_seconds = $slot_duration * 60;

        while ($start < $end) {
            $slot_end = $start + $duration_seconds;

            // Skip slot if it overlaps with lunch break.
            if ($has_lunch_break && $lunch_break_start && $lunch_break_end) {
                // Check if slot overlaps with lunch break.
                if (($start >= $lunch_break_start && $start < $lunch_break_end) ||
                    ($slot_end > $lunch_break_start && $slot_end <= $lunch_break_end) ||
                    ($start < $lunch_break_start && $slot_end > $lunch_break_end)
                ) {
                    // Skip this slot, jump to end of lunch break.
                    $start = $lunch_break_end;
                    continue;
                }
            }

            if ($slot_end <= $end) {
                $slots[] = array(
                    'start' => date('H:i:s', $start),
                    'end'   => date('H:i:s', $slot_end),
                );
            }

            // Add buffer time to the next slot.
            $start = $slot_end + ($buffer_time * 60);
        }

        return $slots;
    }

    /**
     * Generate day slots (full day or half day)
     *
     * @param string $slot_type Slot type (full_day or half_day).
     * @param array  $settings Service settings.
     * @return array
     */
    private function generate_day_slots($slot_type, $settings)
    {
        $start_time = $this->service->get('start_time');
        $end_time = $this->service->get('end_time');

        if ($slot_type === 'full_day') {
            return array(
                array(
                    'start' => '00:00:00',
                    'end'   => '23:59:59',
                    'label' => __('Full Day', 'gform-booking'),
                    'type'  => 'full_day',
                ),
            );
        } elseif ($slot_type === 'half_day') {
            $half_days = array();

            // Morning slot.
            if (isset($settings['half_day_morning']) && $settings['half_day_morning']) {
                $morning_start = isset($settings['half_day_morning_start']) ? $settings['half_day_morning_start'] : '08:00:00';
                $morning_end = isset($settings['half_day_morning_end']) ? $settings['half_day_morning_end'] : '12:00:00';

                $half_days[] = array(
                    'start' => $morning_start,
                    'end'   => $morning_end,
                    'label' => __('Morning', 'gform-booking'),
                    'type'  => 'half_day',
                );
            }

            // Afternoon slot.
            if (isset($settings['half_day_afternoon']) && $settings['half_day_afternoon']) {
                $afternoon_start = isset($settings['half_day_afternoon_start']) ? $settings['half_day_afternoon_start'] : '13:00:00';
                $afternoon_end = isset($settings['half_day_afternoon_end']) ? $settings['half_day_afternoon_end'] : '17:00:00';

                $half_days[] = array(
                    'start' => $afternoon_start,
                    'end'   => $afternoon_end,
                    'label' => __('Afternoon', 'gform-booking'),
                    'type'  => 'half_day',
                );
            }

            return $half_days;
        }

        return array();
    }

    /**
     * Get booked slots for a specific date
     *
     * @param string $date Date in Y-m-d format.
     * @return array Array of booked slots.
     */
    private function get_booked_slots($date)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'gf_booking_appointments';

        $booked = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT start_time, end_time FROM $table 
				WHERE service_id = %d 
				AND appointment_date = %s 
				AND status = 'confirmed'
				ORDER BY start_time ASC",
                $this->service_id,
                $date
            ),
            ARRAY_A
        );

        // Debug logging
        error_log('GF Booking: get_booked_slots for date ' . $date . ', service_id ' . $this->service_id . ', found ' . count($booked) . ' booked slots');
        if (!empty($booked)) {
            error_log('GF Booking: Booked slots: ' . json_encode($booked));
        }

        return $booked;
    }

    /**
     * Check if a time slot is already booked
     *
     * @param array $slot Time slot to check.
     * @param array $booked_slots Array of booked slots.
     * @return bool
     */
    private function is_slot_booked($slot, $booked_slots)
    {
        if (empty($booked_slots)) {
            return false;
        }

        foreach ($booked_slots as $booked_slot) {
            // For full day or half day slots, check if any part overlaps.
            $slot_type = isset($slot['type']) ? $slot['type'] : 'time';

            if ($slot_type === 'full_day' || $slot_type === 'half_day') {
                // If there's any booking on this day, the full/half day slot is taken.
                error_log('GF Booking: Full/Half day slot overlaps with booking (any booking on day makes it unavailable)');
                return true;
            }

            // Check for time overlap.
            $overlaps = $slot['start'] < $booked_slot['end_time'] && $slot['end'] > $booked_slot['start_time'];
            if ($overlaps) {
                error_log('GF Booking: Slot ' . $slot['start'] . '-' . $slot['end'] . ' overlaps with booked slot ' . $booked_slot['start_time'] . '-' . $booked_slot['end_time'] . ' - MARKED AS BOOKED');
                return true;
            }
        }

        return false;
    }

    /**
     * Get calendar data for a month
     *
     * @param int $year Year.
     * @param int $month Month.
     * @return array Calendar data.
     */
    public function get_month_calendar($year = null, $month = null)
    {
        if (is_null($year)) {
            $year = date('Y');
        }
        if (is_null($month)) {
            $month = date('m');
        }

        $calendar = array();
        $first_day = mktime(0, 0, 0, $month, 1, $year);
        $days_in_month = date('t', $first_day);
        $day_of_week = date('w', $first_day); // 0 = Sunday.

        // Adjust day of week to match WordPress (Monday = 1).
        $day_of_week = ($day_of_week === 0) ? 6 : $day_of_week - 1;

        // Fill in the days.
        $current_day = 1;
        $week = 0;

        // Create first week with leading empty days if needed.
        for ($i = 0; $i < 7; $i++) {
            if ($i < $day_of_week) {
                $calendar[$week][$i] = null;
            } elseif ($current_day <= $days_in_month) {
                $date = sprintf('%d-%02d-%02d', $year, $month, $current_day);
                $calendar[$week][$i] = array(
                    'date'   => $date,
                    'day'    => $current_day,
                    'slots'  => $this->get_available_slots($date),
                );
                $current_day++;
            } else {
                $calendar[$week][$i] = null;
            }
        }
        $week++;

        // Fill remaining weeks.
        while ($current_day <= $days_in_month) {
            for ($i = 0; $i < 7; $i++) {
                if ($current_day <= $days_in_month) {
                    $date = sprintf('%d-%02d-%02d', $year, $month, $current_day);
                    $calendar[$week][$i] = array(
                        'date'   => $date,
                        'day'    => $current_day,
                        'slots'  => $this->get_available_slots($date),
                    );
                    $current_day++;
                } else {
                    $calendar[$week][$i] = null;
                }
            }
            $week++;
        }

        return $calendar;
    }
}
