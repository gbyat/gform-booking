# GF Booking - Features Documentation

## Calendar Views

### Simple Date Picker (Default)

The default view shows a simple date input field. After selecting a date, available time slots are displayed below.

### Month Calendar View

To enable the month calendar view, configure your service settings:

```php
$settings = array(
    'calendar_type' => 'month',  // 'simple' or 'month'
    // ... other settings
);
```

The month calendar view shows:

- A full month view with clickable days
- Visual indicators for available/unavailable dates
- Slot count per day
- Automatic navigation between months

## Slot Types

### Time-based Slots (Default)

Regular time slots in 30-minute, 15-minute, or custom intervals.

### Full Day Slots

Book entire days. Perfect for rentals, equipment, or facilities that are booked by the day.

**Configuration:**

```php
$settings = array(
    'slot_type' => 'full_day',
    // ... other settings
);
```

### Half Day Slots

Split days into morning and afternoon periods.

**Configuration:**

```php
$settings = array(
    'slot_type' => 'half_day',
    'half_day_morning' => true,
    'half_day_morning_start' => '08:00:00',
    'half_day_morning_end' => '12:00:00',
    'half_day_afternoon' => true,
    'half_day_afternoon_start' => '13:00:00',
    'half_day_afternoon_end' => '17:00:00',
    // ... other settings
);
```

**Example Use Cases:**

- Office spaces (morning/afternoon sessions)
- Workshops or training sessions
- Consultation slots
- Facility rentals

## Service Configuration

When creating or editing a service, you can configure:

1. **Slot Type**: Choose between time-based, half-day, or full-day slots
2. **Calendar View**: Simple date picker or month calendar
3. **Half Day Times**: Configure morning and afternoon periods
4. **Allowed Weekdays**: Select which days of the week are available
5. **Buffer Time**: Add gaps between appointments

## Example Configurations

### Conference Room Rental (Half Day)

```php
array(
    'name' => 'Conference Room A',
    'slot_type' => 'half_day',
    'half_day_morning' => true,
    'half_day_morning_start' => '08:00:00',
    'half_day_morning_end' => '12:00:00',
    'half_day_afternoon' => true,
    'half_day_afternoon_start' => '13:00:00',
    'half_day_afternoon_end' => '17:00:00',
    'weekdays' => array(1, 2, 3, 4, 5), // Monday to Friday
    'calendar_type' => 'month',
)
```

### Equipment Rental (Full Day)

```php
array(
    'name' => 'Projector Equipment',
    'slot_type' => 'full_day',
    'weekdays' => array(1, 2, 3, 4, 5, 6, 7),
    'calendar_type' => 'month',
)
```

### Consultation Service (Time Slots)

```php
array(
    'name' => 'Individual Consultation',
    'slot_type' => 'time',
    'slot_duration' => 30,
    'buffer_time' => 15,
    'start_time' => '09:00:00',
    'end_time' => '17:00:00',
    'weekdays' => array(1, 2, 3, 4, 5),
    'calendar_type' => 'simple',
)
```

## CSS Customization

You can customize the appearance by overriding the following CSS classes:

- `.gf-booking-month-calendar` - Main calendar container
- `.gf-booking-day.available` - Available days
- `.gf-booking-day.unavailable` - Unavailable days
- `.gf-booking-day.selected` - Selected day
- `.gf-booking-slot-full-day` - Full day slot button
- `.gf-booking-slot-half-day` - Half day slot button

## Notes

- Full day and half day slots cannot be booked on the same day
- When a slot is booked, the entire time period is blocked
- Admins can still extend appointment times in the admin panel
- The system automatically hides unavailable slots
