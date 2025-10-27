<?php

/**
 * Gravity Forms Addon class for GF Booking plugin
 *
 * @package GFormBooking
 */

namespace GFormBooking;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class GF_Addon
 */
class GF_Addon extends \GFAddOn
{

    /**
     * Version number
     *
     * @var string
     */
    protected $_version = GFORM_BOOKING_VERSION;

    /**
     * Minimum Gravity Forms version
     *
     * @var string
     */
    protected $_min_gravityforms_version = '2.5';

    /**
     * Plugin slug
     *
     * @var string
     */
    protected $_slug = 'gform-booking';

    /**
     * Plugin path
     *
     * @var string
     */
    protected $_path = 'gform-booking/gform-booking.php';

    /**
     * Full path to plugin file
     *
     * @var string
     */
    protected $_full_path = GFORM_BOOKING_PLUGIN_FILE;

    /**
     * Title
     *
     * @var string
     */
    protected $_title = 'GF Booking';

    /**
     * Short title
     *
     * @var string
     */
    protected $_short_title = 'Booking';

    /**
     * Instance of this class
     *
     * @var GF_Addon|null
     */
    private static $_instance = null;

    /**
     * Get instance
     *
     * @return GF_Addon|null
     */
    public static function get_instance()
    {
        if (self::$_instance == null) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Register hooks
     */
    public function init()
    {
        parent::init();

        // Register calendar field type.
        add_action('gform_field_input', array($this, 'render_calendar_field'), 10, 5);
        add_action('gform_editor_js', array($this, 'editor_script'));
        add_filter('gform_add_field_buttons', array($this, 'add_field_button'));
        add_filter('gform_field_type_title', array($this, 'set_field_title'), 10, 2);

        // Add field settings.
        add_action('gform_field_standard_settings', array($this, 'field_settings_ui'), 10, 2);

        // Process booking when form is submitted.
        add_action('gform_entry_created', array($this, 'create_booking_from_entry'), 10, 2);
    }

    /**
     * Add calendar field button to GF editor
     */
    public function add_field_button($field_groups)
    {
        foreach ($field_groups as &$group) {
            if ($group['name'] === 'advanced_fields') {
                $group['fields'][] = array(
                    'class' => 'button',
                    'value' => __('Calendar', 'gform-booking'),
                    'data-type' => 'calendar',
                    'onclick' => 'StartAddField("calendar")',
                );
                break;
            }
        }
        return $field_groups;
    }

    /**
     * Set field title
     */
    public function set_field_title($title, $field_type)
    {
        if ($field_type === 'calendar') {
            return __('Calendar', 'gform-booking');
        }
        return $title;
    }

    /**
     * Render calendar field
     */
    public function render_calendar_field($input, $field, $value, $entry_id, $form_id)
    {
        if ($field->type !== 'calendar') {
            return $input;
        }

        // Get service ID from field settings.
        $service_id = isset($field->gfBookingService) ? $field->gfBookingService : 0;

        // Add hidden input to store the value.
        $calendar_html = Form_Fields::render_calendar_field($value, $service_id);

        // Wrap in a div with the field ID.
        $calendar_html = '<div class="gf-booking-field-wrapper" data-field-id="' . esc_attr($field->id) . '">' . $calendar_html;

        // Add a hidden input that Gravity Forms expects.
        $calendar_html .= '<input type="hidden" name="input_' . esc_attr($field->id) . '" id="input_' . esc_attr($form_id) . '_' . esc_attr($field->id) . '" class="gf-booking-value" value="' . esc_attr($value) . '">';

        $calendar_html .= '</div>';

        return $calendar_html;
    }

    /**
     * Editor script for calendar field
     */
    public function editor_script()
    {
?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Add service selection to calendar field settings.
                fieldSettings['calendar'] = '.label_setting, .admin_label_setting, .visibility_setting, .gf_booking_service_setting, .css_class_setting, .conditional_logic_field_setting, .conditional_logic_page_setting, .conditional_logic_nextbutton_setting, .error_message_setting, .label_placement_setting, .description_setting';

                // Show/hide service setting based on field type.
                jQuery(document).on('gform_load_field_settings', function(event, field, form) {
                    if (field.type === 'calendar') {
                        jQuery('.gf_booking_service_setting').show();
                        jQuery('#gf_booking_service_select').val(field.gfBookingService || '');
                    } else {
                        jQuery('.gf_booking_service_setting').hide();
                    }
                });

                // Populate services dropdown.
                bindCalendarServiceSetting();

                function bindCalendarServiceSetting() {
                    $(document).on('change', '.gf_booking_service_select', function() {
                        SetFieldProperty('gfBookingService', this.value);
                    });
                }

                // Populate select on load.
                setTimeout(function() {
                    var services = <?php echo json_encode(Service::get_all()); ?>;
                    var select = $('.gf_booking_service_select');
                    if (select.length && select.find('option').length <= 1) {
                        $.each(services, function(i, service) {
                            select.append('<option value="' + service.id + '">' + service.name + '</option>');
                        });
                    }
                }, 100);
            });
        </script>
        <?php
    }

    /**
     * Create booking from form entry
     *
     * @param array $entry Entry data.
     * @param array $form Form data.
     */
    public function create_booking_from_entry($entry, $form)
    {
        // Find the calendar field in the form.
        $calendar_field = null;
        $appointment_date = '';
        $start_time = '';
        $end_time = '';
        $selected_service_id = 0;

        foreach ($form['fields'] as $field) {
            if ($field->type === 'calendar' && !empty($field->gfBookingService)) {
                $calendar_field = $field;
                $field_id = $field->id;

                // Get the selected service.
                $selected_service_id = absint($field->gfBookingService);

                // Parse the calendar field value.
                // Format: "YYYY-MM-DD|HH:MM" (e.g., "2024-12-15|14:00")
                $field_value = rgar($entry, $field_id);
                error_log('GF Booking: Field value from entry: ' . $field_value);
                if (!empty($field_value)) {
                    $parts = explode('|', $field_value);
                    error_log('GF Booking: Parsed parts: ' . json_encode($parts));
                    if (count($parts) >= 2) {
                        $appointment_date = sanitize_text_field($parts[0]);
                        $start_time_str = sanitize_text_field($parts[1]);
                        // Add seconds if not already present.
                        if (strlen($start_time_str) === 5) {
                            $start_time = $start_time_str . ':00';
                        } else {
                            $start_time = $start_time_str;
                        }
                        error_log('GF Booking: Date: ' . $appointment_date . ', Start time: ' . $start_time);

                        // Calculate end time (add 30 minutes by default, or get from service).
                        $service = new Service($selected_service_id);
                        if ($service->exists()) {
                            $slot_duration = $service->get('slot_duration') ?: 30;
                            $start_timestamp = strtotime($start_time);
                            $end_timestamp = $start_timestamp + ($slot_duration * 60);
                            $end_time = date('H:i:s', $end_timestamp);
                        } else {
                            $end_time = date('H:i:s', strtotime($start_time) + (30 * 60));
                        }
                    }
                }
                break;
            }
        }

        // If no calendar field found or no date/time selected, skip booking creation.
        if (!$calendar_field || empty($appointment_date) || empty($start_time)) {
            error_log('GF Booking: No calendar field or date/time found. Entry ID: ' . $entry['id']);
            return;
        }

        // Try to find name, email, and phone fields from the form.
        $name = '';
        $email = '';
        $phone = '';

        foreach ($form['fields'] as $field) {
            $field_value = rgar($entry, $field->id);
            if (empty($field_value)) {
                continue;
            }

            if ($field->type === 'name' || $field->inputType === 'name') {
                // Gravity Forms name field can be an array or string.
                if (is_array($field_value)) {
                    // Combine first and last name.
                    $name_parts = array_filter(array(
                        rgar($field_value, rgar($field, 'inputs')[0]['id']),
                        rgar($field_value, rgar($field, 'inputs')[1]['id'])
                    ));
                    $name = trim(implode(' ', $name_parts));
                } else {
                    $name = $field_value;
                }
            } elseif ($field->type === 'email' || $field->inputType === 'email') {
                $email = $field_value;
            } elseif ($field->type === 'phone') {
                $phone = $field_value;
            }
        }

        // Fallback: if no name found, use email or a generic value.
        if (empty($name)) {
            $name = $email ?: __('Customer', 'gform-booking');
        }

        // Log booking attempt.
        error_log('GF Booking: Attempting to create appointment. Date: ' . $appointment_date . ', Time: ' . $start_time . ', Service: ' . $selected_service_id);

        // Create appointment from entry.
        $appointment_id = Appointment::create(
            array(
                'service_id'       => $selected_service_id,
                'entry_id'         => $entry['id'],
                'form_id'          => $form['id'],
                'customer_name'    => $name,
                'customer_email'   => $email,
                'customer_phone'   => $phone,
                'appointment_date' => $appointment_date,
                'start_time'       => $start_time,
                'end_time'         => $end_time,
            )
        );

        if ($appointment_id) {
            error_log('GF Booking: Appointment created successfully. ID: ' . $appointment_id);
        } else {
            error_log('GF Booking: Failed to create appointment.');
        }
    }

    /**
     * Get form settings
     *
     * @param array $form Form data.
     * @return array Settings.
     */
    public function get_form_settings($form)
    {
        $settings = $this->get_plugin_settings();

        return wp_parse_args(rgar($form, $this->_slug), $settings);
    }

    /**
     * Add settings for calendar fields
     */
    public function field_settings_ui($position, $form_id)
    {
        // Add settings for calendar fields at position 1430.
        if ($position == 1430) {
        ?>
            <li class="gf_booking_service_setting field_setting" style="display:none;">
                <label for="gf_booking_service_select" class="section_label">
                    <?php esc_html_e('Service', 'gform-booking'); ?>
                    <?php gform_tooltip('gf_booking_service'); ?>
                </label>
                <select id="gf_booking_service_select" class="gf_booking_service_select" onchange="SetFieldProperty('gfBookingService', this.value);">
                    <option value=""><?php esc_html_e('Select a service', 'gform-booking'); ?></option>
                    <?php
                    $services = Service::get_all();
                    foreach ($services as $service) {
                        echo '<option value="' . esc_attr($service['id']) . '">' . esc_html($service['name']) . '</option>';
                    }
                    ?>
                </select>
            </li>
<?php
        }
    }

    public function __construct()
    {
        parent::__construct();
    }
}
