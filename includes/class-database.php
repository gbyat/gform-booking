<?php

/**
 * Database class for GF Booking plugin
 *
 * @package GFormBooking
 */

namespace GFormBooking;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Database
 */
class Database
{

    /**
     * Initialize database tables
     */
    public static function init()
    {
        add_action('plugins_loaded', array(__CLASS__, 'create_tables'));
        register_activation_hook(GFORM_BOOKING_PLUGIN_FILE, array(__CLASS__, 'activate'));
        register_uninstall_hook(GFORM_BOOKING_PLUGIN_FILE, array(__CLASS__, 'uninstall'));
    }

    /**
     * Create database tables on activation
     */
    public static function activate()
    {
        self::create_tables();
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    public static function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'gf_booking_';

        // Services table.
        $services_table = $prefix . 'services';
        $services_sql = "CREATE TABLE IF NOT EXISTS $services_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			weekdays varchar(100) NOT NULL DEFAULT '',
			start_time time NOT NULL,
			end_time time NOT NULL,
			slot_duration int(11) NOT NULL DEFAULT 30,
			buffer_time int(11) NOT NULL DEFAULT 0,
			settings longtext,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id)
		) $charset_collate;";

        // Appointments table.
        $appointments_table = $prefix . 'appointments';
        $appointments_sql = "CREATE TABLE IF NOT EXISTS $appointments_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			service_id bigint(20) UNSIGNED NOT NULL,
			entry_id bigint(20) UNSIGNED DEFAULT NULL,
			form_id bigint(20) UNSIGNED NOT NULL,
			customer_name varchar(255) NOT NULL,
			customer_email varchar(255) NOT NULL,
			customer_phone varchar(100),
			appointment_date date NOT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'confirmed',
			token varchar(64) NOT NULL,
			notes text,
			settings longtext,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY service_id (service_id),
			KEY entry_id (entry_id),
			KEY appointment_date (appointment_date),
			KEY status (status)
		) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($services_sql);
        dbDelta($appointments_sql);
    }

    /**
     * Drop database tables on uninstall
     */
    public static function uninstall()
    {
        global $wpdb;
        $prefix = $wpdb->prefix . 'gf_booking_';

        $wpdb->query("DROP TABLE IF EXISTS {$prefix}appointments");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}services");
    }
}
