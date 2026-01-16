# CRPC Reading Program Notifications

**Version:** 1.0.0\
**Author:** City Reformed\
**Requires:** FluentCRM

**Table of Contents**

- [Description](#description)
- [Installation](#installation)
- [Configuration](#configuration)
- [AI Developer Context](#ai-developer-context)

## Description

This plugin automates the process of sending email notifications to subscribers
when a new post is published in a specific category.

It integrates strictly with **FluentCRM**:

1. Checks for the existence of FluentCRM.
2. Retrieves contacts associated with a specific Tag.
3. Sends a styled email with the post link and a direct "Manage Subscription"
   link.

## Installation

1. Upload the `crpc-reading-notifications` folder to the `/wp-content/plugins/`
   directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Ensure **FluentCRM** is installed and active.

## Configuration

1. Go to **Settings > Reading Program** in the WP Admin.
2. **Add Rule**:
   - **Post Category**: Select the WordPress Post Category (e.g., "Reading
     Program").
   - **FluentCRM Tag**: Select the FluentCRM Tag (e.g., "Reading Plan
     Subscriber").
3. Save Changes.

When a post is published in that Category, all contacts with that Tag will
receive an email.

---

## AI Developer Context

**Purpose**: Bridge the gap between WordPress core publishing events and
FluentCRM lists without complex automation workflows.

### Core Logic (`includes/notification-logic.php`)

- **Hook**: `transition_post_status`
- **Trigger**: `new_status` == 'publish' && `old_status` != 'publish' &&
  `post_type` == 'post'.
- **Validation**: Checks against `crpc_reading_rules` option to see if the
  post's category matches a configured rule.
- **Recipient Fetching**: Uses `\FluentCrmApi('contacts')->all()` to fetch
  contacts by Tag ID.
- **Email Sending**: Uses `wp_mail()`. We construct the message manually
  ensuring an **Unsubscribe Link** is present.
- **Unsubscribe Link**:
  `site_url("?fluentcrm=manage_subscription&contact_hash=...&contact_id=...")`.
  This is critical for CAN-SPAM compliance.

### Settings Page (`includes/admin-settings.php`)

- **Option Name**: `crpc_reading_rules`
- **Structure**: Array of arrays:
  `[ ['category_id' => 1, 'tag_id' => 10], ... ]`
- **Dependencies**: Relies on `\FluentCrmApi` to populate the Tags dropdown.

### Future Improvements

- **Email Template**: Currently hardcoded string. Could likely be improved by
  using a FluentCRM Email Template ID if the API allows dispatching a specific
  template.
- **Batching**: Currently limits to 1000 subscribers. If list grows larger,
  implement background processing (Action Scheduler).
