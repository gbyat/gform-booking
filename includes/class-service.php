<?php

/**
 * Service class for GF Booking plugin
 *
 * @package GFormBooking
 */

namespace GFormBooking;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Service
 */
class Service
{

    /**
     * Service ID
     *
     * @var int
     */
    private $id;

    /**
     * Service data
     *
     * @var array
     */
    private $data;

    /**
     * Constructor
     *
     * @param int $service_id Service ID.
     */
    public function __construct($service_id = 0)
    {
        global $wpdb;

        if ($service_id > 0) {
            $table = $wpdb->prefix . 'gf_booking_services';
            $this->data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $service_id), ARRAY_A);

            if ($this->data) {
                $this->id = $service_id;
                // Decode weekdays if it's JSON encoded.
                if (! empty($this->data['weekdays'])) {
                    $decoded = json_decode($this->data['weekdays'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $this->data['weekdays'] = $decoded;
                    } else {
                        // Fallback: try maybe_unserialize for old data.
                        $this->data['weekdays'] = maybe_unserialize($this->data['weekdays']);
                    }
                }
                // Deserialize settings if it's JSON encoded.
                if (! empty($this->data['settings'])) {
                    $this->data['settings'] = json_decode($this->data['settings'], true);
                }
            }
        }
    }

    /**
     * Get all services
     *
     * @return array
     */
    public static function get_all()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'gf_booking_services';
        // Safe: table name uses $wpdb->prefix, no user input
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A);
    }

    /**
     * Create a new service
     *
     * @param array $data Service data.
     * @return int|false Service ID or false on failure.
     */
    public static function create($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'gf_booking_services';

        $defaults = array(
            'name'          => '',
            'description'   => '',
            'weekdays'      => '',
            'start_time'    => '09:00:00',
            'end_time'      => '17:00:00',
            'slot_duration' => 30,
            'buffer_time'   => 0,
            'settings'      => array(),
        );

        $data = wp_parse_args($data, $defaults);

        $insert_data = array(
            'name'          => sanitize_text_field($data['name']),
            'description'   => wp_kses_post($data['description']),
            'weekdays'      => wp_json_encode($data['weekdays']),
            'start_time'    => sanitize_text_field($data['start_time']),
            'end_time'      => sanitize_text_field($data['end_time']),
            'slot_duration' => absint($data['slot_duration']),
            'buffer_time'   => absint($data['buffer_time']),
            'settings'      => wp_json_encode($data['settings']),
            'created_at'    => current_time('mysql'),
            'updated_at'    => current_time('mysql'),
        );

        $result = $wpdb->insert($table, $insert_data);

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update service
     *
     * @param array $data Service data.
     * @return bool
     */
    public function update($data)
    {
        global $wpdb;

        if (! $this->id) {
            return false;
        }

        $table = $wpdb->prefix . 'gf_booking_services';

        $update_data = array();

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['description'])) {
            $update_data['description'] = wp_kses_post($data['description']);
        }
        if (isset($data['weekdays'])) {
            $update_data['weekdays'] = wp_json_encode($data['weekdays']);
        }
        if (isset($data['start_time'])) {
            $update_data['start_time'] = sanitize_text_field($data['start_time']);
        }
        if (isset($data['end_time'])) {
            $update_data['end_time'] = sanitize_text_field($data['end_time']);
        }
        if (isset($data['slot_duration'])) {
            $update_data['slot_duration'] = absint($data['slot_duration']);
        }
        if (isset($data['buffer_time'])) {
            $update_data['buffer_time'] = absint($data['buffer_time']);
        }
        if (isset($data['settings'])) {
            $update_data['settings'] = wp_json_encode($data['settings']);
        }

        $update_data['updated_at'] = current_time('mysql');

        return $wpdb->update(
            $table,
            $update_data,
            array('id' => $this->id)
        );
    }

    /**
     * Delete service
     *
     * @return bool
     */
    public function delete()
    {
        global $wpdb;

        if (! $this->id) {
            return false;
        }

        $table = $wpdb->prefix . 'gf_booking_services';
        return (bool) $wpdb->delete($table, array('id' => $this->id));
    }

    /**
     * Get service data
     *
     * @param string $key Data key.
     * @return mixed
     */
    public function get($key = '')
    {
        if (! $key) {
            return $this->data;
        }

        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    /**
     * Get the service ID
     *
     * @return int
     */
    public function get_id()
    {
        return $this->id;
    }

    /**
     * Check if service exists
     *
     * @return bool
     */
    public function exists()
    {
        return ! empty($this->data);
    }
}
