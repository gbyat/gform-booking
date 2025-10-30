# Security Documentation

## WordPress Security Standards Compliance

This plugin follows WordPress security best practices and coding standards.

### ✅ Implemented Security Measures

#### 1. **Input Sanitization**
All user inputs are properly sanitized before processing:
- `sanitize_text_field()` - for text fields
- `sanitize_email()` - for email addresses
- `sanitize_textarea_field()` - for textarea fields
- `sanitize_hex_color()` - for color values
- `absint()` - for integer values
- `wp_kses_post()` - for rich text content

**Examples:**
```php
$name = sanitize_text_field($_POST['name']);
$email = sanitize_email($_POST['email']);
$service_id = absint($_POST['service_id']);
```

#### 2. **Output Escaping**
All output is properly escaped to prevent XSS attacks:
- `esc_html()` - for text content
- `esc_attr()` - for HTML attributes
- `esc_url()` - for URLs
- `esc_js()` - for JavaScript strings
- `esc_html_e()` - for translatable text output

**Examples:**
```php
echo esc_html($customer_name);
echo '<div class="' . esc_attr($class) . '">';
echo '<a href="' . esc_url($url) . '">';
```

#### 3. **SQL Injection Prevention**
All database queries use prepared statements via `$wpdb->prepare()`:

```php
// Safe prepared statement
$appointment_id = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$table} WHERE token = %s",
    $token
));

// Table names are safe because they use $wpdb->prefix
$rows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
```

**Note:** Static table names using `$wpdb->prefix` are considered safe as the prefix is defined by WordPress and not user-controlled.

#### 4. **Nonce Verification**
All admin actions and forms are protected with WordPress nonces:

**Admin Actions:**
```php
check_admin_referer('cancel_appointment');
check_admin_referer('delete_appointment');
check_admin_referer('gf_booking_settings');
```

**AJAX Requests:**
```php
check_ajax_referer('gf_booking_nonce', 'nonce');
```

**Forms:**
```php
wp_nonce_field('save_service', 'service_nonce');
```

**Links with nonces:**
```php
echo wp_nonce_url(
    add_query_arg(array('action' => 'cancel', 'appointment_id' => $id)),
    'cancel_appointment'
);
```

#### 5. **Capability Checks**
All admin pages and actions require proper user capabilities:

**Admin Menu:**
```php
add_menu_page(
    __('GF Booking', 'gform-booking'),
    __('GF Booking', 'gform-booking'),
    'manage_options',  // Requires administrator capability
    'gform-booking',
    ...
);
```

**Action Handlers:**
```php
public function handle_admin_actions()
{
    // Check user capabilities
    if (! current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions.', 'gform-booking'));
    }
    // ... handle actions
}
```

#### 6. **Token-Based Authentication**
Public appointment management uses secure token-based authentication:

```php
// Generate secure token
$token = wp_generate_password(32, false);

// Verify token
if (!$appointment->verify_token($token)) {
    wp_die(__('Invalid or expired token.', 'gform-booking'));
}
```

#### 7. **Rate Limiting**
Appointment modifications are rate-limited to prevent abuse:

```php
// Max 5 modifications per appointment
if ($modification_count >= 5) {
    return new WP_Error('max_modifications_exceeded', ...);
}

// Min 5 minutes between modifications
if ($minutes_since_modification < 5) {
    return new WP_Error('rate_limit_exceeded', ...);
}
```

#### 8. **Email Security**
All email addresses are validated and sanitized:

```php
$email = sanitize_email($data['customer_email']);
```

#### 9. **AJAX Security**
All AJAX endpoints verify nonces and user capabilities where applicable:

```php
public static function ajax_get_availability()
{
    check_ajax_referer('gf_booking_nonce', 'nonce');
    // ... handle request
}
```

#### 10. **Direct File Access Prevention**
All PHP files check for direct access:

```php
// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}
```

### Security Audit Checklist

- ✅ All user inputs sanitized
- ✅ All outputs escaped
- ✅ SQL queries use prepared statements
- ✅ Nonces on all forms and actions
- ✅ Capability checks on admin functions
- ✅ Secure token generation/verification
- ✅ Rate limiting implemented
- ✅ Direct file access prevented
- ✅ AJAX endpoints secured
- ✅ Export functions protected with nonces

### Additional Security Recommendations

1. **Keep WordPress Updated**: Always use the latest version of WordPress
2. **Keep Plugin Updated**: Install updates as soon as they're available
3. **Use Strong Passwords**: Ensure admin accounts use strong passwords
4. **Limit Access**: Only grant admin access to trusted users
5. **Monitor Logs**: Regularly check WordPress security logs
6. **Backup Regularly**: Maintain regular backups of your site
7. **Use HTTPS**: Always use SSL/TLS for your WordPress site

### Reporting Security Issues

If you discover a security vulnerability, please report it responsibly:

1. **Do NOT** create a public GitHub issue
2. Email security details to: mail@webentwicklerin.at
3. Include detailed information about the vulnerability
4. Allow time for the issue to be addressed before public disclosure

### References

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [WordPress Data Validation](https://developer.wordpress.org/apis/handbook/internationalization/localization/string-validation/)
- [WordPress Security](https://wordpress.org/support/article/hardening-wordpress/)

