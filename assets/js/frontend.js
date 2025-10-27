/**
 * GF Booking - Frontend JavaScript
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // Handle participants field change - reload slots if a date is already selected.
        $(document).on('change', 'input[type="number"]', function () {
            var $calendar = $(this).closest('form').find('.gf-booking-calendar');
            if ($calendar.length > 0) {
                var selectedDate = '';
                var serviceId = $calendar.data('service-id');

                // Find the selected date.
                if ($calendar.hasClass('gf-booking-month-calendar')) {
                    var $selectedDay = $calendar.find('.gf-booking-day.selected');
                    if ($selectedDay.length) {
                        selectedDate = $selectedDay.data('date');
                    }
                } else {
                    var $dateInput = $calendar.find('.gf-booking-date');
                    if ($dateInput.length) {
                        selectedDate = $dateInput.val();
                    }
                }

                if (selectedDate) {
                    loadSlotsForDate($calendar, selectedDate, serviceId);
                }
            }
        });

        // Handle service selection change.
        $(document).on('change', '.gf-booking-service-select', function () {
            var $selector = $(this);
            var selectedServiceId = $selector.val();

            if (!selectedServiceId) {
                // Hide calendar if no service selected.
                $selector.closest('.gf-booking-service-selector').siblings('.gf-booking-calendar').hide();
                return;
            }

            // Reload the page with the selected service ID (or use AJAX to reload calendar).
            // For simplicity, we'll trigger a form submission or reload.
            // Better: Use AJAX to reload just the calendar.
            location.reload(); // Temporary solution - should be AJAX-based
        });

        // Handle date selection (simple date picker).
        $(document).on('change', '.gf-booking-date', function () {
            var $calendar = $(this).closest('.gf-booking-calendar');
            var date = $(this).val();
            var serviceId = $calendar.data('service-id');

            if (!date) {
                return;
            }

            loadSlotsForDate($calendar, date, serviceId);
        });

        // Handle day selection in month calendar.
        $(document).on('click', '.gf-booking-day.available', function () {
            var $day = $(this);
            var date = $day.data('date');
            var $calendar = $day.closest('.gf-booking-month-calendar');
            var $selectedInfo = $calendar.find('.gf-booking-selected-info');
            var serviceId = $calendar.data('service-id');

            // Update selected state.
            $calendar.find('.gf-booking-day').removeClass('selected');
            $day.addClass('selected');

            // Show selected info and loading state.
            $selectedInfo.find('.selected-date').text(formatDateDisplay(date));
            $selectedInfo.show();
            $selectedInfo.find('.gf-booking-slots').html('<p>' + gfBooking.strings.loading + '</p>');

            // Load latest slots for this date via AJAX.
            loadSlotsForDate($calendar, date, serviceId);
        });

        // Handle time slot selection.
        $(document).on('click', '.gf-booking-slot:not(.unavailable)', function () {
            var $slot = $(this);
            var time = $slot.data('time');
            var slotType = $slot.data('type');

            // Update selected state.
            $slot.siblings().removeClass('selected');
            $slot.addClass('selected');

            // Get the selected date.
            var $calendar = $slot.closest('.gf-booking-calendar');
            var selectedDate = '';
            var serviceId = $calendar.data('service-id');

            // Find the selected date - either from date picker or from selected day.
            if ($calendar.hasClass('gf-booking-month-calendar')) {
                var $selectedDay = $calendar.find('.gf-booking-day.selected');
                if ($selectedDay.length) {
                    selectedDate = $selectedDay.data('date');
                }
            } else {
                // Simple date picker.
                var $dateInput = $calendar.find('.gf-booking-date');
                if ($dateInput.length) {
                    selectedDate = $dateInput.val();
                }
            }

            if (selectedDate && time) {
                // Calculate end time based on slot type.
                var endTime = '';
                if (slotType === 'full_day') {
                    endTime = $slot.data('end') || '23:59:59';
                } else if (slotType === 'half_day') {
                    endTime = $slot.data('end') || '17:00:00';
                } else {
                    // Regular time slot - calculate end time from duration (default 30 min).
                    var timeParts = time.split(':');
                    var hours = parseInt(timeParts[0]);
                    var minutes = parseInt(timeParts[1]);
                    minutes += 30; // Default slot duration.
                    if (minutes >= 60) {
                        hours++;
                        minutes -= 60;
                    }
                    endTime = (hours < 10 ? '0' : '') + hours + ':' + (minutes < 10 ? '0' : '') + minutes + ':00';
                }

                // Format: "YYYY-MM-DD|HH:MM:SS"
                var fieldValue = selectedDate + '|' + time;

                // Find the hidden input field for this calendar.
                // First try to find the wrapper.
                var $wrapper = $calendar.closest('.gf-booking-field-wrapper');
                var $calendarField;

                if ($wrapper.length > 0) {
                    $calendarField = $wrapper.find('.gf-booking-value');
                } else {
                    // Fallback: look for the hidden input in the same gfield container.
                    $calendarField = $calendar.closest('.gfield').find('.gf-booking-value');
                }

                if ($calendarField.length > 0) {
                    $calendarField.val(fieldValue);
                    console.log('GF Booking: Set field value to:', fieldValue);
                } else {
                    console.warn('GF Booking: Could not find input field for calendar');
                }
            }

            // Trigger custom event.
            $calendar.trigger('gf-booking-time-selected', [time]);
        });

        // Handle month navigation.
        $(document).on('click', '.gf-booking-prev-month, .gf-booking-next-month', function () {
            var $button = $(this);
            var isPrev = $button.hasClass('gf-booking-prev-month');
            var $calendar = $button.closest('.gf-booking-month-calendar');
            var $currentMonth = $calendar.find('.gf-booking-current-month');
            var monthText = $currentMonth.text().trim();

            // Parse current month/year from text
            // Handle different formats: "January 2024" or "Januar 2024" (localized)
            var dateParts = monthText.split(' ');
            if (dateParts.length >= 2) {
                var year = parseInt(dateParts[dateParts.length - 1]); // Last part is always year

                // Try to extract month from the first part
                // We'll use the current year/month from data attributes if available
                var currentYear = parseInt(dateParts[dateParts.length - 1]);
                var currentDate = new Date();

                // Try to find month index from the month text
                var monthNames = {
                    // English
                    'January': 0, 'February': 1, 'March': 2, 'April': 3, 'May': 4, 'June': 5,
                    'July': 6, 'August': 7, 'September': 8, 'October': 9, 'November': 10, 'December': 11,
                    // German
                    'Januar': 0, 'Februar': 1, 'März': 2, 'April': 3, 'Mai': 4, 'Juni': 5,
                    'Juli': 6, 'August': 7, 'September': 8, 'Oktober': 9, 'November': 10, 'Dezember': 11
                };

                var monthName = dateParts[0];
                var monthIndex = monthNames[monthName];

                if (typeof monthIndex === 'undefined') {
                    // Fallback: try to parse as date
                    var testDate = new Date(monthText);
                    if (!isNaN(testDate.getTime())) {
                        currentDate = testDate;
                        monthIndex = testDate.getMonth();
                        year = testDate.getFullYear();
                    }
                }

                if (typeof monthIndex !== 'undefined') {
                    // Calculate new month
                    if (isPrev) {
                        monthIndex--;
                        if (monthIndex < 0) {
                            monthIndex = 11;
                            year--;
                        }
                    } else {
                        monthIndex++;
                        if (monthIndex > 11) {
                            monthIndex = 0;
                            year++;
                        }
                    }

                    var newMonth = monthIndex + 1;

                    // Reload calendar for new month
                    loadMonthCalendar($calendar, year, newMonth);
                }
            }
        });
    });

    /**
     * Load slots for a specific date
     */
    function loadSlotsForDate($calendar, date, serviceId) {
        // Show loading state.
        var $slotsContainer = $calendar.find('.gf-booking-slots');
        $slotsContainer.html('<p>' + gfBooking.strings.loading + '</p>');

        // Get available slots via AJAX.
        $.ajax({
            url: gfBooking.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gf_booking_get_availability',
                nonce: gfBooking.nonce,
                date: date,
                service_id: serviceId
            },
            success: function (response) {
                if (response.success && response.data.length > 0) {
                    displayTimeSlots($calendar, response.data);
                    $calendar.find('.gf-booking-time-slots').show();
                } else {
                    $slotsContainer.html('<p>' + gfBooking.strings.noSlots + '</p>');
                }
            },
            error: function () {
                $slotsContainer.html('<p>' + gfBooking.strings.error + '</p>');
            }
        });
    }

    /**
     * Load month calendar via AJAX
     */
    function loadMonthCalendar($calendar, year, month) {
        var serviceId = $calendar.data('service-id');
        var $monthHeader = $calendar.find('.gf-booking-current-month');

        // Show loading state
        $calendar.find('.gf-booking-calendar-table').css('opacity', '0.5');

        $.ajax({
            url: gfBooking.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gf_booking_get_month_calendar',
                nonce: gfBooking.nonce,
                year: year,
                month: month,
                service_id: serviceId
            },
            success: function (response) {
                if (response.success && response.data) {
                    // Update month header using Date API for proper localization
                    var date = new Date(year, month - 1, 1);
                    var monthName = date.toLocaleDateString('de-DE', { month: 'long' });
                    $monthHeader.text(monthName + ' ' + year);

                    // Update calendar table
                    renderMonthCalendar($calendar, response.data);
                    $calendar.find('.gf-booking-calendar-table').css('opacity', '1');
                }
            },
            error: function () {
                $calendar.find('.gf-booking-calendar-table').css('opacity', '1');
                alert('Error loading calendar');
            }
        });
    }

    /**
     * Render month calendar from data
     */
    function renderMonthCalendar($calendar, monthData) {
        var $tbody = $calendar.find('.gf-booking-calendar-table tbody');
        $tbody.empty();

        // Get max date from calendar data attribute.
        var maxDate = $calendar.data('max-date');
        var maxDateTimestamp = maxDate ? new Date(maxDate + 'T00:00:00').getTime() : null;

        $.each(monthData, function (weekIndex, week) {
            var $row = $('<tr></tr>');

            for (var i = 0; i < 7; i++) {
                if (week[i] && week[i].day) {
                    var day = week[i];
                    var slotsCount = day.slots ? day.slots.length : 0;
                    var availableClass = slotsCount > 0 ? 'available' : 'unavailable';

                    // Check if date exceeds max booking date.
                    if (maxDateTimestamp) {
                        var dayDate = new Date(day.date + 'T00:00:00').getTime();
                        if (dayDate > maxDateTimestamp) {
                            availableClass = 'unavailable';
                            slotsCount = 0;
                        }
                    }

                    var $cell = $('<td></td>')
                        .addClass('gf-booking-day')
                        .addClass(availableClass)
                        .attr('data-date', day.date)
                        .attr('data-slots', JSON.stringify(day.slots))
                        .html(
                            '<div class="gf-booking-day-number">' + day.day + '</div>' +
                            '<div class="gf-booking-slots-count">' + slotsCount + ' slot' + (slotsCount !== 1 ? 's' : '') + '</div>'
                        );
                    $row.append($cell);
                } else {
                    $row.append($('<td></td>'));
                }
            }

            $tbody.append($row);
        });
    }

    /**
     * Display time slots
     */
    function displayTimeSlots($calendar, slots) {
        var $slotsContainer = $calendar.find('.gf-booking-slots');
        var html = '';

        // Get the number of participants from the form (if available).
        var participants = getParticipantsCount();

        $.each(slots, function (index, slot) {
            // Check if this slot has enough capacity for the requested participants.
            if (slot.remaining !== undefined && slot.remaining !== null) {
                if (slot.remaining < participants) {
                    // Not enough spots for the requested participants.
                    return true; // Skip this slot (continue to next).
                }
            }

            var slotType = slot.type || 'time';
            var timeDisplay;

            if (slotType === 'full_day') {
                timeDisplay = slot.label || 'Full Day';
            } else if (slotType === 'half_day') {
                timeDisplay = slot.label + ' (' + formatTime(slot.start) + ' - ' + formatTime(slot.end) + ')';
            } else {
                timeDisplay = formatTime(slot.start) + ' - ' + formatTime(slot.end);
            }

            html += '<div class="gf-booking-slot gf-booking-slot-' + slotType + '" data-time="' + slot.start + '" data-type="' + slotType + '">';
            html += timeDisplay;

            // Add remaining spots information if available.
            if (slot.remaining !== undefined) {
                html += ' <span class="gf-booking-spots-remaining">(' + slot.remaining + ' ' + (slot.remaining === 1 ? 'spot' : 'spots') + ' left)</span>';
            }

            html += '</div>';
        });

        $slotsContainer.html(html);
    }

    /**
     * Format time for display
     */
    function formatTime(timeString) {
        // Use WordPress time format setting.
        var parts = timeString.split(':');
        var hours = parseInt(parts[0]);
        var minutes = parts[1];

        // Check if WordPress time format is 24-hour or 12-hour.
        var timeFormat = gfBooking.timeFormat || 'H:i';
        var is24Hour = timeFormat.indexOf('g') === -1 && timeFormat.indexOf('h') === -1;

        if (is24Hour) {
            // 24-hour format (e.g., "H:i" or "G:i")
            return (hours < 10 ? '0' : '') + hours + ':' + minutes;
        } else {
            // 12-hour format (e.g., "g:i A" or "g:ia")
            var ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            return hours + ':' + minutes + ' ' + ampm;
        }
    }

    /**
     * Format date for display
     */
    function formatDateDisplay(dateString) {
        // Simple date formatting.
        var date = new Date(dateString + 'T00:00:00');
        var options = { year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }

    /**
     * Get participants count from the form
     * Looks for number or quantity fields in the form
     */
    function getParticipantsCount() {
        var participants = 1; // Default to 1.

        // Try to find the participants field in the form.
        // Look for input[type="number"] fields.
        var $participantsField = $('input[type="number"]').not('.gf-booking-value');

        if ($participantsField.length > 0) {
            // Take the first number field value as participants (could be improved with specific field ID).
            var val = parseInt($participantsField.first().val());
            if (val > 0) {
                participants = val;
            }
        }

        return participants;
    }

})(jQuery);
