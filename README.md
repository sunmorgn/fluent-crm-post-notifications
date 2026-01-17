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

You can design the email using FluentCRM's Visual Builder.

1. Go to **FluentCRM > Emails > Email Templates**.
   - Create a new template (Simple or Visual).
   - In the email body, use these placeholders to insert dynamic post content:
     - `{{post_title}}` : Title of the post
     - `{{post_content}}` : Full text content
     - `{{post_excerpt}}` : Summary/Excerpt
     - `{{post_link}}` : URL to the post
     - `{{featured_image_url}}` : URL of the post's featured image
   - **Important**: Include the unsubscribe link! You can use the standard
     FluentCRM footer or the smartcode `##crm.manage_subscription_url##`.
2. Go back to **Settings > Post Notifications** and select this template in your
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

**Purpose**: Bridge the gap between WordPress core publishing events and
FluentCRM lists without complex automation workflows.

### Core Logic (`includes/notification-logic.php`)

- **Hook**: `transition_post_status`
- **Option**: `fcpn_rules`
- **Namespace**: `FCPN`

### Settings Page (`includes/admin-settings.php`)

- **Menu Slug**: `fluent-crm-post-notifications`
- **Option Group**: `fcpn_settings_group`
