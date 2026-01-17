# 🚀 FluentCRM Post Notifications

This plugin automatically transforms WordPress post publishing into an email
delivery engine.

**Whenever you publish a post in a specific category, this plugin instantly
sends a professional notification email to your FluentCRM(free) subscribers.**
It eliminates the need to manually create campaigns or complex automation
workflows for routine post updates.

### ✨ How it Works

1. **Category Mapping:** Map a WordPress **Post Category** to a **FluentCRM
   Tag** in the settings.
2. **Automatic Detection:** The moment a post is published (or scheduled), the
   plugin identifies all subscribers carrying that specific tag.
3. **Instant Delivery:** It sends a clean, professional HTML email (or a custom
   template) containing the post title, excerpt, and a direct link.

### 📖 Example: The 2026 Reading Program

- **Signup:** Visitors join via a Form.
- **Publish:** Editors simply post to the trigger category.
- **Result:** Subscribers get the update immediately, no extra steps required.

### 🛠 Administrative Power

- **Manage Connections:** Fine-tune your category-to-tag mappings in the
  [Notification Settings](https://sitesetgo.com/wp-admin/options-general.php?page=fluent-crm-post-notifications).
- **Track Subscribers:** View and manage your growing list of readers directly
  in the
  [FluentCRM Dashboard](https://sitesetgo.com/wp-admin/admin.php?page=fluentcrm-admin#/subscribers?sort_by=id&sort_type=DESC&filter_type=simple&t=1768664418626).

---

**Version:** 1.0.0\
**Author:** Sunny Morgan\
**Requires:** FluentCRM (Free or Pro)

## Installation

1. Upload the `fluent-crm-post-notifications` folder to the
   `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Ensure **FluentCRM** is installed and active.

## Configuration

1. Go to **Settings > Post Notifications** in the WP Admin.
2. **Add Rule**:
   - **Post Category**: Select the WordPress Post Category.
   - **FluentCRM Tag**: Select the FluentCRM Tag to email.
   - **Email Template (Optional)**: Select a FluentCRM template.
3. Save Changes.

When a post is published in a matched category, all contacts with the assigned
tag will receive an email.

## Email Notifications

The plugin sends professional HTML emails for both templated notifications and
the default fallback.

### Using Email Templates (Optional)

1. Go to **FluentCRM → Email Templates**.
2. Create or edit a template using custom placeholders.
3. In the plugin settings, select this template for your rule.

### Available Placeholders

- `{{post_title}}` - The post title
- `{{post_link}}` or `{{post_url}}` - Redirect-free canonical URL to the post
- `{{post_excerpt}}` - Post excerpt or auto-generated summary
- `{{post_content}}` - Full post content
- `{{featured_image_url}}` - URL of the featured image

### Unified Professional Footer

All emails (templated or fallback) include a polished, centered footer with:

1. **Subscription Reason**: "You are receiving this because you signed up for
   [Category] updates from [Site Title]."
2. **Site Title**: Displayed as a centered header.
3. **Unsubscribe Link**: A "Manage Subscription / Unsubscribe" link that uses a
   secure hash (no login required).

## Preparing FluentCRM

Before configuring the plugin, ensure your tags and settings are ready:

1. **Create Tags**: Group users by notification preference (e.g.
   `notify:reading-program`).
2. **Business Settings**: Ensure **FluentCRM > Settings > Business Settings**
   are filled out to populate the blog name accurately.

---

## Developer Context

### Architecture Overview

**Core Components:**

- `fluent-crm-post-notifications.php` - Main plugin file
- `includes/admin-settings.php` - Admin UI (jQuery-powered)
- `includes/notification-logic.php` - Core processing logic

**Namespace:** `FCPN`

### Key Implementation Details

#### 1. Dual-Hook Reliability

Uses both `rest_after_insert_post` (Block Editor) and `transition_post_status`
(Classic/Quick/Auto) to ensure notifications trigger regardless of how the post
was published.

#### 2. Duplicate Prevention

Uses a `_fcpn_notification_sent` post meta flag (Unix timestamp) to ensure each
post only triggers a notification once, even if multiple hooks fire.

#### 3. Canonical Permalinks

Uses `wp_get_canonical_url()` to retrieve the "final" destination URL,
eliminating common 301 redirects caused by trailing slashes or protocol
mismatches.

#### 4. Direct SQL Retrieval

Bypasses the FluentCRM API for contact retrieval using a direct `$wpdb` query on
`fc_subscribers`. This was implemented to avoid reliability and pagination
issues found in native API filters.

#### 5. DRY Notification Logic

Renamed core function to `fcpn_process_post_notification()` to avoid collisions.
Shared logic for subject line generation, footer construction, and placeholder
replacement ensures consistency between templated and fallback emails.

### Technical Limitations

- **No Campaign Tracking**: Since we use `wp_mail()`, these are sent as
  transactional emails and do not appear in FluentCRM's "Campaigns" report.
- **Single Rule Priority**: If a post matches multiple rules, the plugin uses
  the tag(s) from all matching rules but the template from the _first_ matching
  rule only.
- **ce_id Exclusion**: The "Manage Subscription" page will not show specific
  campaign tracking data because these emails are not part of an internal
  FluentCRM campaign.
