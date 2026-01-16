<?php

namespace CRPC\Notifications\Logic;

/**
 * Send email notification when a post is published in a specific category.
 */
add_action('transition_post_status', function ($new_status, $old_status, $post) {

    // Checks
    if ($new_status !== 'publish' || $old_status === 'publish') {
        return;
    }
    if ($post->post_type !== 'post') {
        return;
    }
    // Check FluentCRM is active
    if (! function_exists('\FluentCrmApi')) {
        return;
    }

    // Get Rules
    $rules = get_option('crpc_reading_rules', []);
    if (empty($rules) || ! is_array($rules)) {
        return;
    }

    $target_tag_ids = [];

    // Find matching rules
    foreach ($rules as $rule) {
        if (empty($rule['category_id']) || empty($rule['tag_id'])) {
            continue;
        }

        if (has_category($rule['category_id'], $post)) {
            $target_tag_ids[] = $rule['tag_id'];
        }
    }

    $target_tag_ids = array_unique($target_tag_ids);

    if (empty($target_tag_ids)) {
        return;
    }

    // Prepare Email Data
    $subject = "New Reading Plan: " . $post->post_title;
    $blog_name = get_bloginfo('name');

    /**
     * Send to each Tag group
     * Loop through tags separately just in case we want to customize the message per tag later,
     * but for now we could batch them. However, distinct tags might imply distinct user intents,
     * so let's process them to ensure we hit everyone.
     * 
     * Note: If a user has MULTIPLE tags that we are targeting, they might get multiple emails.
     * We should probably de-duplicate the USER list.
     */

    // Get all contacts in ANY of the tags
    $contacts = \FluentCrmApi('contacts')->all([
        'tags'   => $target_tag_ids,
        'status' => 'subscribed',
        'limit'  => 1000 // Reasonable batch limit
    ]);

    if (empty($contacts)) {
        return;
    }

    foreach ($contacts as $contact) {
        // Safety check for email
        if (empty($contact->email)) {
            continue;
        }

        // Generate Direct Unsubscribe URL for Fluent CRM
        $unsubscribe_url = site_url("?fluentcrm=manage_subscription&contact_hash={$contact->hash}&contact_id={$contact->id}");

        $excerpt = get_the_excerpt($post);
        if (empty($excerpt)) {
            $excerpt = wp_trim_words($post->post_content, 20);
        }

        $message  = "Hi " . (!empty($contact->first_name) ? $contact->first_name : 'Reader') . ",\n\n";
        $message .= "A new post in the Reading Program has been published: " . $post->post_title . "\n";
        $message .= "Read it here: " . get_permalink($post->ID) . "\n\n";
        $message .= $excerpt . "\n\n";
        $message .= "----------------\n";
        $message .= "You are receiving this because you signed up for {$blog_name} Reading Program updates.\n";
        $message .= "Manage Subscription: " . $unsubscribe_url . "\n";

        // Send
        wp_mail($contact->email, $subject, $message);
    }
}, 10, 3);
