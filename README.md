# FluentCRM Post Notifications

**Version:** 1.0.0\
**Author:** Sunny Morgan\
**Requires:** FluentCRM

**Table of Contents**

- [Description](#description)
- [Installation](#installation)
- [Configuration](#configuration)
- [Developer Context](#developer-context)

## Description

This plugin automates the process of sending email notifications to FluentCRM
subscribers when a new post is published in a specific category.

It acts as a bridge between WordPress "Posts" and FluentCRM "Tags".

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
3. Save Changes.

When a post is published in that Category, all contacts with that Tag will
receive an email. When a post is published in that Category, all contacts with
that Tag will receive an email.

## Email Templates (Optional)

You can optionally use FluentCRM email templates for your notifications instead
of plain text emails.

### Setting Up Templates

1. Go to **FluentCRM → Email Templates** in WordPress admin
2. Create a new template or edit an existing one
3. Use the visual editor to design your email
4. Include dynamic post content using placeholders (see below)
5. In the plugin settings, select your template for each category rule

### Available Placeholders

Use these placeholders in your template to insert dynamic post content:

- `{{post_title}}` - The post title
- `{{post_link}}` or `{{post_url}}` - URL to the post
- `{{post_excerpt}}` - Post excerpt or auto-generated summary
- `{{post_content}}` - Full post content
- `{{featured_image_url}}` - URL of the featured image

### Template Subject Line

The email subject is taken from the template's **Excerpt** field. You can use
the same placeholders in the subject line.

If no excerpt is set, the plugin will generate a default subject: "New
[Category]: [Post Title]"

### Footer and Unsubscribe Link

The plugin automatically adds a footer with an unsubscribe link to all templated
emails. You don't need to add this manually.

### Example Template

```html
<h2>{{post_title}}</h2>

<p>A new post has been published:</p>

{{post_excerpt}}

<p style="margin: 30px 0">
   <a
      href="{{post_link}}"
      style="background-color: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px"
   >
      Read the Full Post
   </a>
</p>
```

Go back to **Settings > Post Notifications** and select this template in your
Rule.

## Preparing FluentCRM

Before configuring the plugin, you need to set up your Tags in CRM.

1. **Create Tag**:
   - Go to **FluentCRM > Tags**.
   - Click **Create Tag**.
   - Name it something clear (e.g. "Reading Program Subscriber").
2. **Check Settings**:
   - Ensure **FluentCRM > Settings > Business Settings** are filled out
     (required for email footer compliance).

CRM Docs: https://developers.fluentcrm.com/getting-started/

## User Signup Form

To let users sign up for these updates:

1. **Create Form**: Go to **Fluent Forms > New Form**.
2. **Add Fields**: Add at least an "Email" field (and name if desired).
3. **Connect CRM**:
   - Go to **Settings & Integrations > Marketing & CRM Integrations**.
   - Click **Add New Integration** -> **FluentCRM**.
   - **Map Fields**: Email -> Email.
   - **Apply Tag**: Select the FluentCRM Tag you want to add to these users
     (e.g. "Reading Program").
4. **Insert Form**:
   - Go to any Page or Post.
   - Add a **"Fluent Forms"** block.
   - Select your new form.

Now, when a user submits this form, they get the Tag. When you publish a Post,
this plugin sees the Tag and sends the email.

---

## Developer Context

### Purpose

This plugin bridges WordPress post publishing events with FluentCRM email
notifications, enabling automatic subscriber notifications when posts are
published in specific categories. It provides a simpler alternative to complex
FluentCRM automation workflows for basic post notification scenarios.

### Architecture Overview

**Core Components:**

- `crpc-reading-notifications.php` - Main plugin file with initialization
- `includes/admin-settings.php` - Admin UI for rule configuration
- `includes/notification-logic.php` - Email sending logic and hooks

**Data Storage:**

- Option: `fcpn_rules` - Array of category-to-tag mappings with optional
  templates
- Post Meta: `_fcpn_notification_sent` - Timestamp flag to prevent duplicate
  sends

**Namespace:** `FCPN`

### Email Sending Logic

#### Dual-Hook Strategy

The plugin uses two WordPress hooks to ensure reliable notification delivery
across different publishing methods:

1. **`rest_after_insert_post`** (Priority 10)
   - Fires after Block Editor saves via REST API
   - Ensures post categories are already saved
   - Primary hook for Gutenberg/Block Editor

2. **`transition_post_status`** (Priority 20)
   - Fires when post status changes to 'publish'
   - Handles Classic Editor, Quick Edit, and programmatic publishes
   - Backup hook for non-REST scenarios

Both hooks call the same `check_and_send_notification()` function, which uses a
post meta flag to prevent duplicate sends.

#### Notification Flow

```
Post Published
    ↓
Hook Triggered (rest_after_insert_post OR transition_post_status)
    ↓
check_and_send_notification()
    ↓
1. Check if already sent (_fcpn_notification_sent meta)
2. Verify post is published
3. Load notification rules (fcpn_rules option)
4. Match post categories to rules
5. Collect target FluentCRM tag IDs
6. Mark as sent (update post meta immediately)
7. Fetch subscribers via direct SQL query
8. Send emails (templated or plain text)
```

#### Duplicate Prevention

- **Post Meta Flag**: `_fcpn_notification_sent` is set immediately after rule
  matching
- **Timing**: Flag is set BEFORE emails are sent to prevent race conditions
- **Value**: Unix timestamp of when notification was triggered

### Template Integration

#### Template Selection

- Templates are selected per-rule in the admin settings
- First matching rule with a template wins
- Template ID is stored in the rule array: `$rule['template_id']`

#### Template Processing

**Placeholder Replacement:**

```php
$replacements = [
    '{{post_title}}'         => $post->post_title,
    '{{post_link}}'          => get_permalink($post->ID),
    '{{post_url}}'           => get_permalink($post->ID),
    '{{post_excerpt}}'       => get_the_excerpt($post),
    '{{post_content}}'       => $post->post_content,
    '{{featured_image_url}}' => get_the_post_thumbnail_url($post->ID, 'full'),
];
```

**Subject Line:**

- Taken from template's `post_excerpt` field
- Placeholders are replaced in subject line too
- Falls back to "New [Category]: [Post Title]" if not set

**Footer:**

- Automatically appended to all templated emails
- Contains business name and unsubscribe link
- Uses FluentCRM's `fluentCrmGetContactManagedHash()` for secure URLs

### Contact Retrieval

**Direct SQL Query** (bypasses FluentCRM API for reliability):

```sql
SELECT DISTINCT s.id, s.email, s.first_name, s.hash
FROM {$wpdb->prefix}fc_subscribers s
JOIN {$wpdb->prefix}fc_subscriber_pivot p ON s.id = p.subscriber_id
WHERE s.status = 'subscribed'
AND p.object_id IN (tag_ids)
```

**Why Direct SQL?**

- FluentCRM's `Subscriber::filterByTags()` had reliability issues
- Direct query ensures we get all subscribed contacts
- More predictable and debuggable

### Email Sending

**Templated Emails:**

- Uses `wp_mail()` with HTML content type
- Includes manually-built footer with unsubscribe link
- Secure hash generated via `fluentCrmGetContactManagedHash($subscriber->id)`

**Plain Text Emails:**

- Fallback when no template is selected
- Simple text format with post details
- Includes unsubscribe URL

**Unsubscribe URL Format:**

```php
add_query_arg([
    'fluentcrm'   => 1,
    'route'       => 'unsubscribe',
    'secure_hash' => fluentCrmGetContactManagedHash($subscriber->id)
], site_url('/'))
```

### Database Schema

**FluentCRM Tables Used:**

- `fc_subscribers` - Subscriber data (email, name, status, hash)
- `fc_subscriber_pivot` - Many-to-many relationship (subscribers ↔ tags)

**Post Meta:**

- `_fcpn_notification_sent` - Timestamp when notification was triggered

**Options:**

- `fcpn_rules` - Array of rule objects:
  ```php
  [
      [
          'category_id' => 36,
          'tag_id' => 1,
          'template_id' => 123  // Optional
      ]
  ]
  ```

### Admin Settings

**Page Location:** Settings → Post Notifications\
**Menu Slug:** `fluent-crm-post-notifications`\
**Option Group:** `fcpn_settings_group`\
**Capability Required:** `manage_options`

**Dynamic Dropdowns:**

- Categories: Populated from `get_categories()`
- Tags: Populated from FluentCRM's `Tag::get()`
- Templates: Populated from `get_posts()` with post types `fc_template` and
  `fluentcrm_campaigntemplate`

**JavaScript:** jQuery-based for dynamic rule addition/removal

### Dependencies

**Required:**

- WordPress 5.0+
- FluentCRM (any version with Tags feature)

**FluentCRM Classes Used:**

- `\FluentCrm\App\Models\Subscriber` - For subscriber model
- `\FluentCrm\App\Models\Tag` - For tag retrieval

**FluentCRM Functions Used:**

- `fluentCrmGetContactManagedHash()` - Secure hash generation
- `FluentCrmApi()` - API availability check

### Filters & Actions

**Actions Used:**

- `rest_after_insert_post` - Primary hook for Block Editor
- `transition_post_status` - Backup hook for Classic Editor
- `plugin_action_links` - Add Settings link to plugins page

**No Custom Filters Provided** (future enhancement opportunity)

### Known Limitations

1. **Manage Subscription Link**: Not included in footer because it requires a
   campaign email ID (`ce_id`) which we don't have for standalone emails
2. **Template Parsing**: FluentCRM's smartcode system (`##crm.*##`) is not used;
   we use custom `{{}}` placeholders instead
3. **No Email Tracking**: Emails are sent via `wp_mail()` without FluentCRM's
   campaign tracking
4. **Single Template Per Send**: If multiple rules match, only the first rule's
   template is used

### Future Enhancement Ideas

- Add custom filters for email content modification
- Support for multiple templates per send
- Integration with FluentCRM's campaign system for tracking
- Scheduled/delayed notifications
- Conditional logic (e.g., only send if post has featured image)
- Support for custom post types
- Admin notification when emails are sent
