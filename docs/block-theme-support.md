# Block Theme Support

For block themes, the appointment management page (where users can cancel or modify their appointments) works best when used with a dedicated page and a template.

## Setup Instructions

1. Create a new page (e.g., "Manage Appointment") in WordPress.

2. Set a custom template for this page (if your theme supports it):

   - Go to Page Editor
   - Look for "Template" in the right sidebar
   - Select a simple template (or your default page template)

3. Add this page ID to the plugin settings (future feature) or configure it manually.

## Alternative: Using the URL Directly

The management page can also be accessed directly via URL:

```
https://your-site.com/?gf_booking=manage&appointment=123&token=abc123
```

This URL is automatically included in the confirmation email sent to customers.

## Template Customization

The management page content is rendered by the plugin. To customize the appearance:

1. Add custom CSS in your theme's style.css or via Customizer:

```css
.gf-booking-management {
  /* Your custom styles */
}
```

2. For block themes, the page will use your theme's default page template, so your header, footer, and overall styling will be applied.

## Participants Field

The system correctly handles multiple participants. When modifying an appointment, the same number of participants is maintained unless changed through a new booking form.

## Notes

- The management page respects your theme's styling
- All security tokens are validated
- Rate limiting prevents abuse (max 5 modifications per appointment, 5-minute cooldown)
