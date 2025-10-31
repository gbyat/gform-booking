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

        // Get all time slots for the day (pass date to use daily windows).
        $all_slots = $this->generate_time_slots($date);

        // If no slots generated, return empty array.
        if (empty($all_slots)) {
            return array();
        }

        // Filter out fully booked slots and add availability info.
        $available_slots = array();
        foreach ($all_slots as $slot) {
            $remaining = $this->get_slot_availability($slot, $date);
            if ($remaining !== null) {
                // Add remaining spots information to the slot.
                $slot['remaining'] = $remaining;
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

        // Check min/max booking date settings.
        $settings = $this->service->get('settings');
        // Min booking: enforce start window
        $min_booking_type = isset($settings['min_booking_type']) ? $settings['min_booking_type'] : 'days_ahead';
        if ($min_booking_type === 'fixed_date') {
            $min_date = isset($settings['min_booking_date']) ? strtotime($settings['min_booking_date']) : null;
            if ($min_date && $date_time < $min_date) {
                return false;
            }
        } elseif ($min_booking_type === 'days_ahead') {
            $min_days = isset($settings['min_booking_days']) ? absint($settings['min_booking_days']) : 1; // default: tomorrow
            $min_date = strtotime("+{$min_days} days", $today);
            if ($date_time < $min_date) {
                return false;
            }
        }

        // Max booking: enforce end window
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
        $settings = $this->service->get('settings');
        // If using custom slots, do not gate by Daily Time Windows.
        // Custom slot availability is controlled per-slot (weekdays) later.
        $slot_type = isset($settings['slot_type']) ? $settings['slot_type'] : 'time';
        if ($slot_type === 'custom') {
            return true;
        }
        $timestamp   = strtotime($date . ' UTC');
        $day_of_week = (int) gmdate('N', $timestamp); // 1-7 (Monday-Sunday).

        // First check if we have daily time windows configured.
        $daily_windows = isset($settings['daily_time_windows']) ? $settings['daily_time_windows'] : null;
        if (is_array($daily_windows) && isset($daily_windows[$day_of_week])) {
            $day_config = $daily_windows[$day_of_week];
            // If day is marked as closed, return false.
            if (isset($day_config['closed']) && $day_config['closed']) {
                return false;
            }
            // If day has windows configured, it's allowed.
            if (!empty($day_config['windows'])) {
                return true;
            }
        }

        // Fallback to old weekdays check for backwards compatibility.
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

        // Convert to int for comparison.
        $day_of_week_int = (int) $day_of_week;
        $weekdays_int = array_map('intval', $weekdays);

        return in_array($day_of_week_int, $weekdays_int, false);
    }

    /**
     * Generate all time slots for the service
     *
     * @param string $date Optional date to get daily-specific windows.
     * @return array Array of time slots.
     */
    private function generate_time_slots($date = null)
    {
        $slot_duration = $this->service->get('slot_duration');
        $buffer_time = $this->service->get('buffer_time');

        // Check settings for slot type.
        $settings = $this->service->get('settings');
        $slot_type = isset($settings['slot_type']) ? $settings['slot_type'] : 'time';

        // Handle custom slots. When 'custom' is selected, do NOT fall back
        // to generated fixed-duration slots. If none are available, return empty.
        if ($slot_type === 'custom') {
            return $this->generate_custom_slots($date);
        }

        // Default: Generate normal time slots.
        $slots = array();

        // Check if we have daily time windows configured.
        $daily_windows = isset($settings['daily_time_windows']) ? $settings['daily_time_windows'] : null;

        // If date is provided and we have daily windows, use them.
        if ($date && is_array($daily_windows)) {
            $timestamp   = strtotime($date . ' UTC');
            $day_of_week = (int) gmdate('N', $timestamp); // 1-7
            $day_config = isset($daily_windows[$day_of_week]) ? $daily_windows[$day_of_week] : null;

            if ($day_config && (!isset($day_config['closed']) || !$day_config['closed'])) {
                $windows = isset($day_config['windows']) ? $day_config['windows'] : array();

                // Generate slots for each time window.
                foreach ($windows as $window) {
                    if (isset($window['start']) && isset($window['end'])) {
                        $window_slots = $this->generate_slots_for_window(
                            $window['start'],
                            $window['end'],
                            $slot_duration,
                            $buffer_time
                        );
                        $slots = array_merge($slots, $window_slots);
                    }
                }
            }

            // Attach price if configured (fixed duration pricing).
            $price = isset($settings['slot_price']) ? $settings['slot_price'] : '';
            if ($price !== '') {
                $price = str_replace(',', '.', $price);
                foreach ($slots as &$slot_item) {
                    $slot_item['price'] = $price;
                }
                unset($slot_item);
            }

            return $slots;
        }

        // Fallback to global start/end times (for backwards compatibility).
        $start_time = $this->service->get('start_time');
        $end_time = $this->service->get('end_time');

        // Check for lunch break.
        $has_lunch_break = isset($settings['has_lunch_break']) && $settings['has_lunch_break'];
        $lunch_break_start = isset($settings['lunch_break_start']) ? strtotime($settings['lunch_break_start']) : null;
        $lunch_break_end = isset($settings['lunch_break_end']) ? strtotime($settings['lunch_break_end']) : null;

        // Parse time strings to avoid strtotime() using current date.
        $start_parts = explode(':', $start_time);
        list($start_hour, $start_minute, $start_second) = array((int)$start_parts[0], (int)$start_parts[1], (int)$start_parts[2]);

        $end_parts = explode(':', $end_time);
        list($end_hour, $end_minute, $end_second) = array((int)$end_parts[0], (int)$end_parts[1], (int)$end_parts[2]);

        // Create timestamps using mktime on a reference date (1970-01-01).
        $start = mktime($start_hour, $start_minute, $start_second, 1, 1, 1970);
        $end = mktime($end_hour, $end_minute, $end_second, 1, 1, 1970);

        // Adjust lunch break timestamps if needed.
        if ($has_lunch_break && $lunch_break_start && $lunch_break_end) {
            $lunch_break_start = mktime((int) gmdate('G', $lunch_break_start), (int) gmdate('i', $lunch_break_start), (int) gmdate('s', $lunch_break_start), 1, 1, 1970);
            $lunch_break_end   = mktime((int) gmdate('G', $lunch_break_end), (int) gmdate('i', $lunch_break_end), (int) gmdate('s', $lunch_break_end), 1, 1, 1970);
        }

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
                    'start' => gmdate('H:i:s', $start),
                    'end'   => gmdate('H:i:s', $slot_end),
                );
            }

            // Move to next slot without buffer time (if buffer_time is 0).
            $start = $slot_end;

            // Only add buffer time if it's set.
            if ($buffer_time > 0) {
                $start += ($buffer_time * 60);
            }
        }

        // Attach price if configured (fixed duration pricing).
        $price = isset($settings['slot_price']) ? $settings['slot_price'] : '';
        if ($price !== '') {
            $price = str_replace(',', '.', $price);
            foreach ($slots as &$slot_item) {
                $slot_item['price'] = $price;
            }
            unset($slot_item);
        }

        return $slots;
    }

    /**
     * Generate slots for a specific time window
     *
     * @param string $start_time Start time.
     * @param string $end_time End time.
     * @param int    $slot_duration Slot duration in minutes.
     * @param int    $buffer_time Buffer time in minutes.
     * @return array Array of time slots.
     */
    private function generate_slots_for_window($start_time, $end_time, $slot_duration, $buffer_time)
    {
        $slots = array();

        // Parse time strings.
        $start_parts = explode(':', $start_time);
        list($start_hour, $start_minute, $start_second) = array((int)$start_parts[0], (int)$start_parts[1], (int)$start_parts[2]);

        $end_parts = explode(':', $end_time);
        list($end_hour, $end_minute, $end_second) = array((int)$end_parts[0], (int)$end_parts[1], (int)$end_parts[2]);

        // Create timestamps using mktime on a reference date (1970-01-01).
        $start = mktime($start_hour, $start_minute, $start_second, 1, 1, 1970);
        $end = mktime($end_hour, $end_minute, $end_second, 1, 1, 1970);

        $duration_seconds = $slot_duration * 60;

        while ($start < $end) {
            $slot_end = $start + $duration_seconds;

            if ($slot_end <= $end) {
                $slots[] = array(
                    'start' => gmdate('H:i:s', $start),
                    'end'   => gmdate('H:i:s', $slot_end),
                );
            }

            // Move to next slot.
            $start = $slot_end;

            // Only add buffer time if it's set.
            if ($buffer_time > 0) {
                $start += ($buffer_time * 60);
            }
        }

        return $slots;
    }

    /**
     * Generate custom slots based on configuration
     *
     * @param string $date Date in Y-m-d format.
     * @return array Array of custom slots.
     */
    private function generate_custom_slots($date)
    {
        $settings = $this->service->get('settings');
        $custom_slots_config = isset($settings['custom_slots']) ? $settings['custom_slots'] : array();

        if (empty($custom_slots_config)) {
            return array();
        }

        $day_of_week = (int) gmdate('N', strtotime($date . ' UTC')); // 1-7
        $available_slots = array();

        // Get all booked appointments for this date first.
        $all_bookings = $this->get_booked_slots($date);

        foreach ($custom_slots_config as $slot_config) {
            // Check if this slot is available on this day.
            if (!empty($slot_config['weekdays']) && !in_array($day_of_week, $slot_config['weekdays'])) {
                continue;
            }

            $slot_start = isset($slot_config['start']) ? $slot_config['start'] : null;
            $slot_end = isset($slot_config['end']) ? $slot_config['end'] : null;

            // Check if any booked slot overlaps with this slot.
            // If yes, hide this slot completely (as it would conflict with existing booking).
            $has_conflict = false;
            if (is_array($all_bookings)) {
                foreach ($all_bookings as $booking) {
                    $booking_start = $booking['start_time'];
                    $booking_end = $booking['end_time'];

                    // Check for time overlap: two time ranges overlap if start of one is before end of the other
                    // and end of one is after start of the other.
                    if ($booking_start < $slot_end && $booking_end > $slot_start) {
                        $has_conflict = true;
                        break;
                    }
                }
            }

            // If there's a conflict with existing booking, don't show this slot.
            if ($has_conflict) {
                continue;
            }

            // No conflict with existing bookings, so check capacity.
            // For custom slots, we count how many times this slot has been booked.
            $capacity = isset($slot_config['capacity']) ? absint($slot_config['capacity']) : 1;
            $remaining = $capacity; // Start with full capacity.

            // Count exact bookings for this specific slot (start and end times match).
            // This allows multiple bookings of the same slot if capacity allows.
            if (is_array($all_bookings)) {
                $slot_bookings = 0;
                foreach ($all_bookings as $booking) {
                    // Check for exact match of start and end times (this specific slot).
                    if ($booking['start_time'] === $slot_start && $booking['end_time'] === $slot_end) {
                        $slot_bookings += isset($booking['participants']) ? absint($booking['participants']) : 1;
                    }
                }
                $remaining = max(0, $capacity - $slot_bookings);
            }

            // Only return slot if there's capacity available.
            if ($remaining > 0) {
                $slot = array(
                    'start' => $slot_config['start'],
                    'end' => $slot_config['end'],
                    'remaining' => $remaining,
                    'capacity' => $capacity,
                    'price' => isset($slot_config['price']) ? $slot_config['price'] : '',
                );
                $available_slots[] = $slot;
            }
        }

        return $available_slots;
    }

    /**
     * Get booked slots for a specific date
     *
     * @param string $date Date in Y-m-d format.
     * @param string $slot_start Start time of the slot to check (optional).
     * @param string $slot_end End time of the slot to check (optional).
     * @return int Number of overlapping bookings for the specified slot, or all bookings if no slot specified.
     */
    private function get_booked_slots($date, $slot_start = null, $slot_end = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'gf_booking_appointments';

        // If checking for a specific slot, sum up participants from overlapping bookings.
        if ($slot_start && $slot_end) {
            $cache_key = sprintf(
                'gf_booking_slot_count_%d_%s_%s_%s',
                absint($this->service_id),
                sanitize_key($date),
                sanitize_key(str_replace(':', '-', $slot_start)),
                sanitize_key(str_replace(':', '-', $slot_end))
            );

            $count = wp_cache_get($cache_key, 'gf_booking');

            if (false === $count) {
                $count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Calculating booked capacity from custom appointments table.
                    $wpdb->prepare(
                        "SELECT COALESCE(SUM(participants), 0) FROM $table 
				WHERE service_id = %d 
				AND appointment_date = %s 
				AND (status = 'confirmed' OR status = 'changed')
				AND start_time < %s 
				AND end_time > %s",
                        $this->service_id,
                        $date,
                        $slot_end,
                        $slot_start
                    )
                );

                $count = $count ? absint($count) : 0;
                $ttl   = defined('MINUTE_IN_SECONDS') ? constant('MINUTE_IN_SECONDS') : 60;
                wp_cache_set($cache_key, $count, 'gf_booking', $ttl);
            }

            return absint($count);
        }

        // Otherwise, return all bookings for the date.
        $cache_key = sprintf(
            'gf_booking_booked_slots_%d_%s',
            absint($this->service_id),
            sanitize_key($date)
        );

        $booked = wp_cache_get($cache_key, 'gf_booking');

        if (false === $booked) {
            $booked = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fetching appointment overlaps for availability calculation.
                $wpdb->prepare(
                    "SELECT start_time, end_time, participants FROM $table 
			WHERE service_id = %d 
			AND appointment_date = %s 
			AND (status = 'confirmed' OR status = 'changed')
			ORDER BY start_time ASC",
                    $this->service_id,
                    $date
                ),
                'ARRAY_A'
            );

            if (!is_array($booked)) {
                $booked = array();
            }

            $ttl = defined('MINUTE_IN_SECONDS') ? constant('MINUTE_IN_SECONDS') : 60;
            wp_cache_set($cache_key, $booked, 'gf_booking', $ttl);
        }

        return $booked;
    }

    /**
     * Check if a time slot is already booked and return number of remaining spots
     *
     * @param array $slot Time slot to check.
     * @param string $date Date in Y-m-d format.
     * @return int|null Returns number of remaining spots, or null if fully booked.
     */
    private function get_slot_availability($slot, $date)
    {
        $service = new Service($this->service_id);
        $settings = $service->get('settings');
        $max_participants = isset($settings['max_participants']) ? absint($settings['max_participants']) : 1;

        // For all slots (fixed duration or custom), count overlapping bookings.
        $booked_count = $this->get_booked_slots($date, $slot['start'], $slot['end']);
        $remaining = $max_participants - $booked_count;

        return $remaining > 0 ? $remaining : null;
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
        if (is_null($year) || is_null($month)) {
            $now = current_time('timestamp');
            $timezone = wp_timezone();
            if (is_null($year)) {
                $year = (int) wp_date('Y', $now, $timezone);
            }
            if (is_null($month)) {
                $month = (int) wp_date('m', $now, $timezone);
            }
        }

        $calendar = array();
        $first_day = mktime(0, 0, 0, $month, 1, $year);
        $days_in_month = (int) gmdate('t', $first_day);
        $day_of_week   = (int) gmdate('w', $first_day); // 0 = Sunday.

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
                $date = sprintf('%04d-%02d-%02d', $year, $month, $current_day);
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
                    $date = sprintf('%04d-%02d-%02d', $year, $month, $current_day);
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
