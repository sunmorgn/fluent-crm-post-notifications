<?php

namespace FCPN\Logic;

/**
 * Main Notification Processing Logic
 * Called by multiple hooks to ensure we catch the 'Publish' event
 * after terms are definitely saved.
 */
function check_and_send_notification($post, $source = 'unknown') {

    // Ensure we have a WP_Post object
    $post = get_post($post);
    if (! $post) return;

    // Trace
    error_log("FCPN Debug: Processing Post {$post->ID} from source: {$source}");

    // 1. Check Status
    if ($post->post_status !== 'publish' || $post->post_type !== 'post') {
        error_log("FCPN Debug: Not published or not a post. Aborting.");
        return;
    }

    // 2. Check duplicate protection (Meta Flag)
    if (get_post_meta($post->ID, '_fcpn_notification_sent', true)) {
        error_log("FCPN Debug: Notification already sent for Post {$post->ID}. Skipping.");
        return;
    }

    // 3. Check FluentCRM
    if (! function_exists('\FluentCrmApi')) {
        return;
    }

    // 4. Load Rules
    $rules = get_option('fcpn_rules', []);
    if (empty($rules)) {
        error_log("FCPN Debug: No rules configured. Aborting.");
        return;
    }

    // 5. Check Categories (Explicitly fetch fresh)
    clean_post_cache($post->ID);
    $current_cats = wp_get_post_categories($post->ID);

    error_log("FCPN Debug: Categories found for Post {$post->ID}: " . print_r($current_cats, true));

    // REWRITE THE RULE LOOP TO CAPTURE TEMPLATE
    $target_tag_ids = [];
    $selected_template_id = 0;

    foreach ($rules as $rule) {
        if (!empty($rule['category_id']) && in_array($rule['category_id'], $current_cats)) {
            if (!empty($rule['tag_id'])) {
                $target_tag_ids[] = $rule['tag_id'];
                // Capture template from the first matching rule that has one
                if (empty($selected_template_id) && !empty($rule['template_id'])) {
                    $selected_template_id = $rule['template_id'];
                }
            }
        }
    }

    $target_tag_ids = array_unique($target_tag_ids);

    if (empty($target_tag_ids)) {
        error_log("FCPN Debug: No matching category rules matched. Aborting.");
        return;
    }

    error_log("FCPN Debug: MATCH FOUND! Sending emails for Tags: " . implode(',', $target_tag_ids));

    // 6. MARK AS SENT IMMEDIATELY to prevent double-send
    update_post_meta($post->ID, '_fcpn_notification_sent', time());

    /**
     * Prepare Message Content (Template vs Default)
     */
    $is_html = false;
    $base_message = '';
    $subject = ''; // Initialize subject

    // Common Data
    $excerpt = get_the_excerpt($post);
    if (empty($excerpt)) $excerpt = wp_trim_words($post->post_content, 20);
    $permalink = get_permalink($post->ID);
    $blog_name = get_bloginfo('name');

    // Fetch Template Content if selected
    if (!empty($selected_template_id)) {
        $tmpl_post = get_post($selected_template_id);
        if ($tmpl_post) {
            $is_html = true;
            $base_message = $tmpl_post->post_content;

            // Basic Replacements (Post Data)
            $replacements = [
                '{{post_title}}'   => $post->post_title,
                '{{post_link}}'    => $permalink,
                '{{post_url}}'     => $permalink,
                '{{post_excerpt}}' => $excerpt,
                '{{post_content}}' => $post->post_content,
                '{{featured_image_url}}' => get_the_post_thumbnail_url($post->ID, 'full'),
            ];

            foreach ($replacements as $key => $val) {
                $base_message = str_replace($key, (string)$val, $base_message);
            }

            // Check for Subject override?
            // Templates usually have a subject in `post_excerpt` or similar?
            // FluentCRM Email Templates stores Subject in `post_excerpt`.
            if (!empty($tmpl_post->post_excerpt)) {
                $subject = $tmpl_post->post_excerpt; // Use Template Subject
                // Replace macros in subject too
                foreach ($replacements as $key => $val) {
                    $subject = str_replace($key, (string)$val, $subject);
                }
            }
        }
    }

    // Default Subject if not set by template
    if (empty($subject)) {
        // Get Display Name for Categories
        $matching_category_names = [];
        foreach ($current_cats as $cat_id) {
            foreach ($rules as $rule) {
                if ($rule['category_id'] == $cat_id) {
                    $term = get_term($cat_id);
                    if (!is_wp_error($term)) {
                        $matching_category_names[] = $term->name;
                    }
                }
            }
        }
        $matching_category_names = array_unique($matching_category_names);
        $category_display_name = implode(' & ', $matching_category_names);
        if (empty($category_display_name)) $category_display_name = 'Update';

        $subject = "New {$category_display_name}: " . $post->post_title;
    }

    // Get Contacts
    global $wpdb;
    $table_subscribers = $wpdb->prefix . 'fc_subscribers';
    $table_pivot       = $wpdb->prefix . 'fc_subscriber_pivot';

    $ids_sql = implode(',', array_map('intval', $target_tag_ids));

    $sql = "SELECT DISTINCT s.id, s.email, s.first_name, s.hash
            FROM $table_subscribers s
            JOIN $table_pivot p ON s.id = p.subscriber_id
            WHERE s.status = 'subscribed'
            AND p.object_id IN ($ids_sql)";

    $contacts = $wpdb->get_results($sql);

    error_log("FCPN Debug: Contacts found (SQL): " . count($contacts));

    if (empty($contacts)) {
        error_log("FCPN Debug: No contacts found for these tags.");
        return;
    }

    foreach ($contacts as $contact) {
        if (empty($contact->email)) continue;

        // Final Message Preparation per Contact
        $message = '';
        $headers = [];

        if ($is_html) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';

            // Ensure Footer exists (Compliance)
            // If the template doesn't have a footer or unsubscribe link, append the default footer.
            if (strpos($base_message, '##crm.email_footer##') === false && strpos($base_message, '##crm.manage_subscription_url##') === false) {
                $base_message .= '<br/><hr/>##crm.email_footer##';
            }

            // Parse SmartCodes (including ##crm.manage_subscription_url##)
            // We use the direct Parser class if available for reliability
            if (class_exists('\FluentCrm\App\Services\Libs\Parser\Parser')) {
                $message = \FluentCrm\App\Services\Libs\Parser\Parser::parse($base_message, $contact);
            } else {
                $message = apply_filters('fluent_crm/parse_campaign_email_message', $base_message, $contact);
            }
        } else {
            // Fallback Text Logic
            // Use FluentCRM's parser to generate the correct Manage Subscription URL
            $unsubscribe_url = apply_filters('fluent_crm/parse_campaign_email_message', '##crm.manage_subscription_url##', $contact);

            // Fallback if filter didn't work (returned raw code)
            if (strpos($unsubscribe_url, '##') !== false) {
                $unsubscribe_url = site_url("?fluentcrm=1&route=unsubscribe&hash={$contact->hash}&contact_id={$contact->id}");
            }

            // Re-calculate category_display_name for text message if not already done for subject
            if (!isset($category_display_name)) {
                $matching_category_names = [];
                foreach ($current_cats as $cat_id) {
                    foreach ($rules as $rule) {
                        if ($rule['category_id'] == $cat_id) {
                            $term = get_term($cat_id);
                            if (!is_wp_error($term)) {
                                $matching_category_names[] = $term->name;
                            }
                        }
                    }
                }
                $matching_category_names = array_unique($matching_category_names);
                $category_display_name = implode(' & ', $matching_category_names);
                if (empty($category_display_name)) $category_display_name = 'Update';
            }

            $message  = "Hi " . (!empty($contact->first_name) ? $contact->first_name : 'Reader') . ",\n\n";
            $message .= "A new post in {$category_display_name} has been published: " . $post->post_title . "\n";
            $message .= "Read it here: " . $permalink . "\n\n";
            $message .= $excerpt . "\n\n";
            $message .= "----------------\n";
            $message .= "You are receiving this because you signed up for {$category_display_name} updates from {$blog_name}.\n";
            $message .= "Manage Subscription: " . $unsubscribe_url . "\n";
        }

        wp_mail($contact->email, $subject, $message, $headers);
    }

    error_log("FCPN Debug: Emails sent to " . count($contacts) . " contacts.");
}

/**
 * Hook 1: REST API (Block Editor) - Fires after terms are saved
 */
add_action('rest_after_insert_post', function ($post, $request, $creating) {
    check_and_send_notification($post, 'rest_api');
}, 10, 3);

/**
 * Hook 2: Standard Transition (Classic Editor / Quick Edit)
 * Low priority to hope terms are saved?
 */
add_action('transition_post_status', function ($new_status, $old_status, $post) {
    if ($new_status === 'publish' && $old_status !== 'publish') {
        check_and_send_notification($post, 'transition_status');
    }
}, 20, 3);
