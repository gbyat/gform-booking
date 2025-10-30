# GF Booking - Appointment Booking for Gravity Forms

**Stable tag:** 1.0.0

A flexible appointment booking system for WordPress Gravity Forms with multiple calendars, customizable time slots, and email confirmations.

## Features

- **Multiple Calendars**: Create separate booking calendars for different services
- **Flexible Scheduling**: Configure daily time windows with closed days for each service
- **Two Booking Types**:
  - **Fixed Duration Slots**: Automatic time slot generation (15/30/60 minutes) with buffer time
  - **Custom Slots**: Define custom time windows with individual capacity and pricing
- **Multiple Slot Selection**: Allow customers to book multiple slots at once
- **Overlapping Slot Handling**: Custom slots can overlap; any booking makes overlapping slots unavailable
- **Calendar Views**: Choose between simple date picker or full month calendar
- **Future-Dated Only**: Appointments can only be booked for future dates
- **Visual Calendar**: Clean, responsive calendar interface with month view
- **Email Confirmations**: Automatic confirmation emails with iCal attachments and modify/cancel links
- **Custom Management Page**: Token-based appointment management on your own WordPress page
- **Admin Management**: Easy-to-use admin interface for managing services and appointments
- **Cutoff Times**: Optional cutoff window to prevent last-minute changes/cancellations
- **CSV/JSON Export**: Export appointments for backup or analysis
- **Price Calculation**: Display and store prices for multiple slot bookings
- **Currency Support**: Configurable currency with GF integration
- **Color Customization**: Fully customizable color scheme via admin settings
- **WordPress Integration**: Uses WordPress time settings and formats
- **Booked Slots Hidden**: Automatically hides already-booked time slots
- **Admin Extension**: Admins can extend time slots for existing appointments
- **Internationalization**: Full translation support (German included)

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
   - Service name and optional description
   - Booking type: Fixed Duration or Custom Slots
   - Daily time windows (morning/afternoon sessions or closed days)
   - Slot duration and buffer time (for fixed duration)
   - Max participants per slot and price (optional)
   - Custom slots with individual times, weekdays, capacity, and pricing
   - Cutoff hours to prevent last-minute changes

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

### Settings & Customization

In **GF Booking > Settings**, you can configure:

- **Currency**: Set the currency code for pricing (default: EUR)
- **Management Page**: Select a WordPress page for appointment management
- **Color Scheme**: Customize all colors using the WordPress color picker

### Email Confirmations

When an appointment is booked, customers automatically receive:

- Appointment confirmation email with date, time, and details
- **iCal attachment** to add to their calendar app (Outlook, Google Calendar, etc.)
- Secure token-based links to modify or cancel their appointment
- Auto-opening calendar when clicking "Add to Calendar" button

## File Structure

```
gform-booking/
├── gform-booking.php            # Main plugin file
├── includes/
│   ├── class-autoloader.php     # Plugin initialization
│   ├── class-database.php       # Database operations
│   ├── class-service.php        # Service management
│   ├── class-appointment.php    # Appointment management
│   ├── class-calendar.php       # Calendar and availability logic
│   ├── class-admin.php          # Admin interface
│   ├── class-confirmation.php   # Email confirmations & iCal
│   ├── class-form-fields.php    # Gravity Forms integration
│   ├── class-gf-addon.php       # GF Addon framework
│   └── class-updater.php        # GitHub updater integration
├── assets/
│   ├── css/
│   │   ├── frontend.css         # Frontend styles
│   │   └── admin.css            # Admin styles
│   └── js/
│       ├── frontend.js          # Frontend JavaScript
│       └── admin.js             # Admin JavaScript
├── templates/
│   └── management-content.php   # Management page template
├── languages/
│   ├── gform-booking.pot        # Translation template
│   └── gform-booking-de_DE.po   # German translation
├── scripts/
│   ├── generate-pot.js          # POT file generator
│   ├── release.js               # Release automation
│   └── sync-version.js          # Version sync utility
├── .github/
│   └── workflows/
│       └── release.yml           # GitHub Actions release workflow
├── package.json                  # NPM scripts and dependencies
└── README.md                     # This file
```

## Development

### Generating Translation Files

To generate the POT file with all translatable strings:

```bash
npm run pot
```

This will create/update `languages/gform-booking.pot` with all translatable strings from the PHP code.

### Creating a Release

To create a new release:

```bash
# Patch release (1.0.0 -> 1.0.1)
npm run release:patch

# Minor release (1.0.0 -> 1.1.0)
npm run release:minor

# Major release (1.0.0 -> 2.0.0)
npm run release:major
```

This will automatically:
- Bump the version number
- Update version in `package.json`, `gform-booking.php`, and `README.md`
- Create/update `CHANGELOG.md` with recent commits
- Commit all changes
- Create a git tag
- Push to GitHub
- Trigger GitHub Actions to build and publish the release

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Gravity Forms 2.5 or higher

## Author

**webentwicklerin, Gabriele Laesser**

- Website: https://webentwicklerin.at

## License

GPL-2.0-or-later

## Security

This plugin follows WordPress security best practices including:
- Input sanitization and output escaping
- SQL injection prevention with prepared statements
- Nonce verification on all forms and actions
- Capability checks for admin functions
- Token-based authentication for public pages
- Rate limiting for modifications

See [SECURITY.md](SECURITY.md) for detailed security documentation.

## Credits

Built following WordPress coding standards and best practices.
