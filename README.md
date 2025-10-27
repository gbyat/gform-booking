# GF Booking - Appointment Booking for Gravity Forms

A flexible appointment booking system for WordPress Gravity Forms with multiple calendars, customizable time slots, and email confirmations.

## Features

- **Multiple Calendars**: Create separate booking calendars for different services
- **Flexible Scheduling**: Configure allowed weekdays and time ranges for each service
- **Smart Time Slots**: Automatic time slot generation with buffer time
- **Slot Types**:
  - Time-based slots (30-minute, 15-minute, etc.)
  - Half-day slots (morning/afternoon)
  - Full-day slots
- **Calendar Views**: Choose between simple date picker or full month calendar
- **Future-Dated Only**: Appointments can only be booked for future dates (starting tomorrow)
- **Visual Calendar**: Clean, responsive calendar interface with month view
- **Email Confirmations**: Automatic confirmation emails with modify/cancel links
- **Admin Management**: Easy-to-use admin interface for managing services and appointments
- **WordPress Integration**: Uses WordPress time settings and formats
- **Booked Slots Hidden**: Automatically hides already-booked time slots
- **Admin Extension**: Admins can extend time slots for existing appointments

## Installation

1. Copy the plugin folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Make sure Gravity Forms is installed and activated
4. Go to 'GF Booking' in the WordPress admin menu to configure services

## Usage

### Setting Up Services

1. Navigate to **GF Booking > Services** in WordPress admin
2. Click "Add New Service"
3. Configure:
   - Service name
   - Allowed weekdays (Mon-Sun)
   - Start and end times
   - Slot duration (minutes)
   - Buffer time between appointments

### Creating a Booking Form

1. Create a new Gravity Form
2. Add fields for:
   - Customer name
   - Email address
   - Phone (optional)
   - Appointment date (date picker)
   - Appointment time (will be populated by available slots)
3. Enable "GF Booking" addon in form settings
4. Select the service calendar to use

### Email Confirmations

When an appointment is booked, customers automatically receive:

- Appointment confirmation with date and time
- Links to modify or cancel their appointment
- Secure token-based access to their booking

## File Structure

```
gform-booking/
├── gform-booking.php          # Main plugin file
├── includes/
│   ├── class-autoloader.php   # Plugin initialization
│   ├── class-database.php     # Database operations
│   ├── class-service.php      # Service management
│   ├── class-appointment.php  # Appointment management
│   ├── class-calendar.php     # Calendar and availability logic
│   ├── class-admin.php        # Admin interface
│   ├── class-confirmation.php # Email confirmations
│   ├── class-form-fields.php  # Gravity Forms integration
│   └── class-gf-addon.php     # GF Addon framework
├── assets/
│   ├── css/
│   │   ├── frontend.css       # Frontend styles
│   │   └── admin.css          # Admin styles
│   └── js/
│       ├── frontend.js        # Frontend JavaScript
│       └── admin.js           # Admin JavaScript
└── README.md
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Gravity Forms 2.5 or higher

## Author

**webentwicklerin, Gabriele Laesser**

- Website: https://webentwicklerin.at

## License

GPL-2.0-or-later

## Credits

Built following WordPress coding standards and best practices.
