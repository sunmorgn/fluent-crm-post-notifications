# FluentCRM Post Notifications

**Version:** 1.0.0\
**Author:** Sunny Morgan\
**Requires:** FluentCRM

**Table of Contents**

- [Description](#description)
- [Installation](#installation)
- [Configuration](#configuration)
- [AI Developer Context](#ai-developer-context)

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
receive an email.

---

## AI Developer Context

**Purpose**: Bridge the gap between WordPress core publishing events and
FluentCRM lists without complex automation workflows.

### Core Logic (`includes/notification-logic.php`)

- **Hook**: `transition_post_status`
- **Option**: `fcpn_rules`
- **Namespace**: `FCPN`

### Settings Page (`includes/admin-settings.php`)

- **Menu Slug**: `fluent-crm-post-notifications`
- **Option Group**: `fcpn_settings_group`
