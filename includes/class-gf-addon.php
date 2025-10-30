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

        // Get service IDs from field settings (support both old and new format).
        $service_ids = array();
        if (!empty($field->gfBookingServices) && is_array($field->gfBookingServices)) {
            $service_ids = array_map('absint', $field->gfBookingServices);
        } elseif (!empty($field->gfBookingService)) {
            // Support old single service format.
            $service_ids = array(absint($field->gfBookingService));
        }

        // If no services specified, try to get all services.
        if (empty($service_ids)) {
            $all_services = Service::get_all();
            $service_ids = array_map(function ($service) {
                return $service['id'];
            }, $all_services);
        }

        // Use first service ID for rendering (or 0 if none).
        $first_service_id = !empty($service_ids) ? $service_ids[0] : 0;

        // Add hidden input to store the value.
        $calendar_html = Form_Fields::render_calendar_field($value, $first_service_id, $service_ids);

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
                fieldSettings['calendar'] = '.label_setting, .admin_label_setting, .visibility_setting, .gf_booking_service_setting, .gf_booking_participants_setting, .gf_booking_name_setting, .gf_booking_phone_setting, .css_class_setting, .conditional_logic_field_setting, .conditional_logic_page_setting, .conditional_logic_nextbutton_setting, .error_message_setting, .label_placement_setting, .description_setting';

                // Show/hide service setting based on field type.
                jQuery(document).on('gform_load_field_settings', function(event, field, form) {
                    if (field.type === 'calendar') {
                        jQuery('.gf_booking_service_setting').show();
                        jQuery('.gf_booking_participants_setting').show();
                        jQuery('.gf_booking_name_setting').show();
                        jQuery('.gf_booking_phone_setting').show();

                        // Handle multi-select for services.
                        var selectedServices = field.gfBookingServices || field.gfBookingService || [];
                        if (!Array.isArray(selectedServices)) {
                            // Convert old single service to array.
                            selectedServices = selectedServices ? [selectedServices] : [];
                        }

                        // Check/uncheck checkboxes based on stored values.
                        jQuery('.gf_booking_service_checkbox').each(function() {
                            var serviceId = jQuery(this).data('service-id').toString();
                            jQuery(this).prop('checked', selectedServices.indexOf(serviceId) !== -1);
                        });

                        // Populate participants field dropdown.
                        var participantsSelect = jQuery('#gf_booking_participants_select');
                        if (participantsSelect.find('option').length <= 1 && form && form.fields) {
                            jQuery.each(form.fields, function(i, formField) {
                                // Only add number and quantity fields.
                                if (formField.type === 'number' || formField.type === 'quantity') {
                                    participantsSelect.append('<option value="' + formField.id + '">' + formField.label + ' (ID: ' + formField.id + ')</option>');
                                }
                            });
                        }

                        jQuery('#gf_booking_participants_select').val(field.gfBookingParticipantsField || '');

                        // Populate name field dropdown (text/name)
                        var nameSelect = jQuery('#gf_booking_name_select');
                        if (nameSelect.find('option').length <= 1 && form && form.fields) {
                            jQuery.each(form.fields, function(i, formField) {
                                if (formField.type === 'text' || formField.type === 'name') {
                                    nameSelect.append('<option value="' + formField.id + '">' + formField.label + ' (ID: ' + formField.id + ')</option>');
                                }
                            });
                        }
                        nameSelect.val(field.gfBookingNameField || '');

                        // Populate phone field dropdown (phone/text)
                        var phoneSelect = jQuery('#gf_booking_phone_select');
                        if (phoneSelect.find('option').length <= 1 && form && form.fields) {
                            jQuery.each(form.fields, function(i, formField) {
                                if (formField.type === 'phone' || formField.type === 'text') {
                                    phoneSelect.append('<option value="' + formField.id + '">' + formField.label + ' (ID: ' + formField.id + ')</option>');
                                }
                            });
                        }
                        phoneSelect.val(field.gfBookingPhoneField || '');
                    } else {
                        jQuery('.gf_booking_service_setting').hide();
                        jQuery('.gf_booking_participants_setting').hide();
                        jQuery('.gf_booking_name_setting').hide();
                        jQuery('.gf_booking_phone_setting').hide();
                    }
                });

                // Bind event handlers for services and participants.
                bindCalendarSettings();

                function bindCalendarSettings() {
                    // Handle service checkbox changes.
                    $(document).on('change', '.gf_booking_service_checkbox', function() {
                        var selectedServices = [];
                        jQuery('.gf_booking_service_checkbox:checked').each(function() {
                            selectedServices.push(jQuery(this).val());
                        });
                        SetFieldProperty('gfBookingServices', selectedServices);
                    });

                    // Handle participants field selection.
                    $(document).on('change', '.gf_booking_participants_select', function() {
                        SetFieldProperty('gfBookingParticipantsField', this.value);
                    });

                    // Handle name/phone selection
                    $(document).on('change', '.gf_booking_name_select', function() {
                        SetFieldProperty('gfBookingNameField', this.value);
                    });
                    $(document).on('change', '.gf_booking_phone_select', function() {
                        SetFieldProperty('gfBookingPhoneField', this.value);
                    });
                }

                // Services checkboxes are already populated in PHP.
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
                // Format: Single slot: "YYYY-MM-DDTHH:MM:SS" (e.g., "2024-12-15T14:00:00")
                //         Multiple slots: "YYYY-MM-DDTHH:MM:SS,YYYY-MM-DDTHH:MM:SS,..."
                $field_value = rgar($entry, $field_id);
                error_log('GF Booking: Field value from entry: ' . $field_value);

                if (!empty($field_value)) {
                    // Check if multiple slots are selected (comma-separated).
                    $slot_strings = explode(',', $field_value);
                    $slots = array();

                    foreach ($slot_strings as $slot_string) {
                        $parts = explode('T', trim($slot_string));
                        if (count($parts) >= 2) {
                            $slot_date = sanitize_text_field($parts[0]);
                            $start_time_str = sanitize_text_field($parts[1]);
                            // Add seconds if not already present.
                            if (strlen($start_time_str) === 5) {
                                $start_time_str = $start_time_str . ':00';
                            }

                            $slots[] = array(
                                'date' => $slot_date,
                                'start' => $start_time_str,
                            );
                        }
                    }

                    if (!empty($slots)) {
                        // Group slots by date to check if they need to be merged.
                        $service = new Service($selected_service_id);
                        $settings = $service->exists() ? $service->get('settings') : array();
                        $allow_multiple_slots = isset($settings['allow_multiple_slots']) ? $settings['allow_multiple_slots'] : false;
                        $slot_type = isset($settings['slot_type']) ? $settings['slot_type'] : 'time';

                        $total_price = 0.0;

                        if ($allow_multiple_slots && count($slots) > 1) {
                            // Check if all slots are on the same date.
                            $first_date = $slots[0]['date'];
                            $all_same_date = true;
                            foreach ($slots as $slot) {
                                if ($slot['date'] !== $first_date) {
                                    $all_same_date = false;
                                    break;
                                }
                            }

                            if ($all_same_date) {
                                // Merge slots on the same day into one appointment.
                                $appointment_date = $first_date;

                                // Find earliest start time and latest end time.
                                $earliest_start = $slots[0]['start'];
                                $latest_end = '';

                                foreach ($slots as $slot) {
                                    $slot_start = $slot['start'];
                                    $slot_end = $this->calculate_slot_end_time($service, $slot_start);
                                    // Sum price per slot
                                    if ($slot_type === 'custom') {
                                        // Match custom slot by start time
                                        if (!empty($settings['custom_slots']) && is_array($settings['custom_slots'])) {
                                            foreach ($settings['custom_slots'] as $cs) {
                                                if (isset($cs['start']) && $cs['start'] === substr($slot_start, 0, 5) && isset($cs['price']) && $cs['price'] !== '') {
                                                    $total_price += (float) str_replace(',', '.', $cs['price']);
                                                    break;
                                                }
                                            }
                                        }
                                    } else {
                                        if (!empty($settings['slot_price'])) {
                                            $total_price += (float) str_replace(',', '.', $settings['slot_price']);
                                        }
                                    }

                                    if ($slot_start < $earliest_start) {
                                        $earliest_start = $slot_start;
                                    }
                                    if (empty($latest_end) || $slot_end > $latest_end) {
                                        $latest_end = $slot_end;
                                    }
                                }

                                $start_time = $earliest_start;
                                $end_time = $latest_end;
                            } else {
                                // Multiple slots on different dates - use first slot only for now.
                                // TODO: Could create multiple appointments, but for now we'll just use the first.
                                $appointment_date = $slots[0]['date'];
                                $start_time = $slots[0]['start'];
                                $end_time = $this->calculate_slot_end_time($service, $start_time);
                                // Price only for the first slot
                                if ($slot_type === 'custom') {
                                    if (!empty($settings['custom_slots']) && is_array($settings['custom_slots'])) {
                                        foreach ($settings['custom_slots'] as $cs) {
                                            if (isset($cs['start']) && $cs['start'] === substr($start_time, 0, 5) && isset($cs['price']) && $cs['price'] !== '') {
                                                $total_price += (float) str_replace(',', '.', $cs['price']);
                                                break;
                                            }
                                        }
                                    }
                                } else {
                                    if (!empty($settings['slot_price'])) {
                                        $total_price += (float) str_replace(',', '.', $settings['slot_price']);
                                    }
                                }
                            }
                        } else {
                            // Single slot selected.
                            $appointment_date = $slots[0]['date'];
                            $start_time = $slots[0]['start'];
                            $end_time = $this->calculate_slot_end_time($service, $start_time);
                            if ($slot_type === 'custom') {
                                if (!empty($settings['custom_slots']) && is_array($settings['custom_slots'])) {
                                    foreach ($settings['custom_slots'] as $cs) {
                                        if (isset($cs['start']) && $cs['start'] === substr($start_time, 0, 5) && isset($cs['price']) && $cs['price'] !== '') {
                                            $total_price += (float) str_replace(',', '.', $cs['price']);
                                            break;
                                        }
                                    }
                                }
                            } else {
                                if (!empty($settings['slot_price'])) {
                                    $total_price += (float) str_replace(',', '.', $settings['slot_price']);
                                }
                            }
                        }

                        error_log('GF Booking: Date: ' . $appointment_date . ', Start time: ' . $start_time . ', End time: ' . $end_time);
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

        // Prefer explicit mapping from calendar field settings (if provided)
        if (!empty($calendar_field->gfBookingNameField)) {
            $mapped_name = rgar($entry, absint($calendar_field->gfBookingNameField));
            if (!empty($mapped_name)) {
                $name = sanitize_text_field($mapped_name);
            }
        }
        if (!empty($calendar_field->gfBookingPhoneField)) {
            $mapped_phone = rgar($entry, absint($calendar_field->gfBookingPhoneField));
            if (!empty($mapped_phone)) {
                $phone = sanitize_text_field($mapped_phone);
            }
        }

        foreach ($form['fields'] as $field) {
            $field_value = rgar($entry, $field->id);
            if (empty($field_value)) {
                continue;
            }

            if (empty($name) && ($field->type === 'name' || $field->inputType === 'name')) {
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
            } elseif (empty($phone) && $field->type === 'phone') {
                $phone = $field_value;
            }
        }

        // Look for participants field - first check if explicitly set in calendar field settings.
        $participants = 1; // Default to 1.

        if (!empty($calendar_field->gfBookingParticipantsField)) {
            // Use the explicitly set participants field.
            $participants_field_id = absint($calendar_field->gfBookingParticipantsField);
            $participants_value = rgar($entry, $participants_field_id);
            if (!empty($participants_value)) {
                $participants = max(1, absint($participants_value));
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
                'participants'     => $participants,
                'settings'         => array(
                    'total_price' => isset($total_price) ? $total_price : 0,
                    'currency'    => Admin::get_currency(),
                ),
            )
        );

        if ($appointment_id) {
            error_log('GF Booking: Appointment created successfully. ID: ' . $appointment_id);
        } else {
            error_log('GF Booking: Failed to create appointment.');
        }
    }

    /**
     * Calculate end time for a slot based on service settings
     *
     * @param Service $service Service object.
     * @param string  $start_time Start time in H:i:s format.
     * @return string End time in H:i:s format.
     */
    private function calculate_slot_end_time($service, $start_time)
    {
        if (!$service || !$service->exists()) {
            // Fallback: 30 minutes.
            return date('H:i:s', strtotime($start_time) + (30 * 60));
        }

        $settings = $service->get('settings');
        $slot_type = isset($settings['slot_type']) ? $settings['slot_type'] : 'time';

        if ($slot_type === 'custom') {
            // For custom slots, find the matching slot config to get the end time.
            $custom_slots = isset($settings['custom_slots']) ? $settings['custom_slots'] : array();
            $end_time = '';
            foreach ($custom_slots as $slot_config) {
                // Compare time without seconds.
                $config_start = isset($slot_config['start']) ? $slot_config['start'] : '';
                if (substr($config_start, 0, 5) === substr($start_time, 0, 5)) {
                    $end_time = isset($slot_config['end']) ? $slot_config['end'] : '';
                    // Add seconds if not present.
                    if (!empty($end_time) && strlen($end_time) === 5) {
                        $end_time = $end_time . ':00';
                    }
                    break;
                }
            }
            // Fallback if slot not found.
            if (empty($end_time)) {
                $slot_duration = $service->get('slot_duration') ?: 30;
                $start_timestamp = strtotime($start_time);
                $end_timestamp = $start_timestamp + ($slot_duration * 60);
                $end_time = date('H:i:s', $end_timestamp);
            }
            return $end_time;
        } else {
            // Fixed duration slots.
            $slot_duration = $service->get('slot_duration') ?: 30;
            $start_timestamp = strtotime($start_time);
            $end_timestamp = $start_timestamp + ($slot_duration * 60);
            return date('H:i:s', $end_timestamp);
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
                    <?php esc_html_e('Available Services', 'gform-booking'); ?>
                    <?php gform_tooltip('gf_booking_service'); ?>
                </label>
                <p class="description" style="margin-bottom: 8px;"><?php esc_html_e('Select one or more services. Users will be able to choose from these services.', 'gform-booking'); ?></p>
                <div id="gf_booking_services_list" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                    <?php
                    $services = Service::get_all();
                    $selected_services = array(); // Will be populated by JavaScript
                    foreach ($services as $service) {
                        echo '<label style="display: block; margin: 5px 0;">';
                        echo '<input type="checkbox" class="gf_booking_service_checkbox" value="' . esc_attr($service['id']) . '" data-service-id="' . esc_attr($service['id']) . '"> ';
                        echo esc_html($service['name']);
                        echo '</label>';
                    }
                    if (empty($services)) {
                        echo '<p style="color: #d54e21;">' . esc_html__('No services available. Please create services first.', 'gform-booking') . '</p>';
                    }
                    ?>
                </div>
                <input type="hidden" id="gf_booking_service_ids" name="gf_booking_service_ids">
            </li>
            <li class="gf_booking_participants_setting field_setting" style="display:none;">
                <label for="gf_booking_participants_select" class="section_label">
                    <?php esc_html_e('Participants Field', 'gform-booking'); ?>
                </label>
                <select id="gf_booking_participants_select" class="gf_booking_participants_select" onchange="SetFieldProperty('gfBookingParticipantsField', this.value);">
                    <option value=""><?php esc_html_e('None (default: 1)', 'gform-booking'); ?></option>
                </select>
                <p class="description"><?php esc_html_e('Select a number field to use for the number of participants. If not set, defaults to 1.', 'gform-booking'); ?></p>
            </li>
            <li class="gf_booking_name_setting field_setting" style="display:none;">
                <label for="gf_booking_name_select" class="section_label">
                    <?php esc_html_e('Customer Name Field', 'gform-booking'); ?>
                </label>
                <select id="gf_booking_name_select" class="gf_booking_name_select" onchange="SetFieldProperty('gfBookingNameField', this.value);">
                    <option value=""><?php esc_html_e('None', 'gform-booking'); ?></option>
                </select>
                <p class="description"><?php esc_html_e('Optional: Select a field to use as the customer name.', 'gform-booking'); ?></p>
            </li>
            <li class="gf_booking_phone_setting field_setting" style="display:none;">
                <label for="gf_booking_phone_select" class="section_label">
                    <?php esc_html_e('Phone Field', 'gform-booking'); ?>
                </label>
                <select id="gf_booking_phone_select" class="gf_booking_phone_select" onchange="SetFieldProperty('gfBookingPhoneField', this.value);">
                    <option value=""><?php esc_html_e('None', 'gform-booking'); ?></option>
                </select>
                <p class="description"><?php esc_html_e('Optional: Select a field to use as the phone number.', 'gform-booking'); ?></p>
            </li>
<?php
        }
    }

    /**
     * Define plugin settings fields
     *
     * @return array Settings fields.
     */
    public function plugin_settings_fields()
    {
        return array(
            array(
                'title'  => __('General Settings', 'gform-booking'),
                'fields' => array(
                    array(
                        'name'    => 'currency',
                        'label'   => __('Currency', 'gform-booking'),
                        'type'    => 'text',
                        'class'   => 'small',
                        'default_value' => 'EUR',
                        'tooltip' => __('The currency code used for pricing (e.g., EUR, USD, GBP).', 'gform-booking'),
                    ),
                ),
            ),
        );
    }

    public function __construct()
    {
        parent::__construct();
    }
}
