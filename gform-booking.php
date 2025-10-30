<?php

/**
 * Plugin Name:       GF Booking
 * Plugin URI:        https://github.com/gbyat/gform-booking
 * Description:       Appointment booking system for Gravity Forms with multiple calendars and flexible time slots.
 * Version:           1.0.0
 * Author:            webentwicklerin, Gabriele Laesser
 * Author URI:        https://webentwicklerin.at
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gform-booking
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Requires PHP:      7.4
 *
 * @package GFormBooking
 */

namespace GFormBooking;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('GFORM_BOOKING_VERSION', '1.0.0');
define('GFORM_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GFORM_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GFORM_BOOKING_PLUGIN_FILE', __FILE__);
define('GFORM_BOOKING_GITHUB_REPO', 'gbyat/gform-booking');

// Include required files.
require_once GFORM_BOOKING_PLUGIN_DIR . 'includes/class-autoloader.php';

// Initialize the plugin.
Autoloader::init();
