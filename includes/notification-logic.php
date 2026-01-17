<?php

namespace FCPN\Logic;

/**
 * Main Notification Processing Logic
 * 
 * This function is called by multiple hooks to ensure we catch the 'Publish' event
 * after post categories are definitely saved. It checks if a published post matches
 * any configured rules and sends email notifications to subscribers with matching tags.
 * 
 * @param WP_Post|int $post The post object or ID
 * @param string $source The source of the call (for debugging)
 */
function check_and_send_notification($post, $source = 'unknown') {

    // Ensure we have a WP_Post object
    $post = get_post($post);
    if (!$post) return;

    // Only process published posts
    if ($post->post_status !== 'publish' || $post->post_type !== 'post') {
        return;
    }

    // Prevent duplicate sends using post meta flag
    if (get_post_meta($post->ID, '_fcpn_notification_sent', true)) {
        return;
    }

    // Ensure FluentCRM is available
    if (!function_exists('\FluentCrmApi')) {
        return;
    }

    // Load notification rules
    $rules = get_option('fcpn_rules', []);
    if (empty($rules)) {
        return;
    }

    // Get post categories (refresh cache to ensure we have latest data)
    clean_post_cache($post->ID);
    $current_cats = wp_get_post_categories($post->ID);

    // Find matching rules and collect target tags
    $target_tag_ids = [];
    $selected_template_id = 0;

    foreach ($rules as $rule) {
        if (!empty($rule['category_id']) && in_array($rule['category_id'], $current_cats)) {
            if (!empty($rule['tag_id'])) {
                $target_tag_ids[] = $rule['tag_id'];
                // Use template from first matching rule that has one
                if (empty($selected_template_id) && !empty($rule['template_id'])) {
                    $selected_template_id = $rule['template_id'];
                }
            }
        }
    }

    $target_tag_ids = array_unique($target_tag_ids);

    if (empty($target_tag_ids)) {
        return;
    }

    // Mark as sent immediately to prevent duplicate sends
    update_post_meta($post->ID, '_fcpn_notification_sent', time());

    // Prepare email content
    $emailSubject = '';
    $emailBody = '';

    // Common post data
    $excerpt = get_the_excerpt($post);
    if (empty($excerpt)) {
        $excerpt = wp_trim_words($post->post_content, 20);
    }
    $permalink = get_permalink($post->ID);
    $blog_name = get_bloginfo('name');

    // Use template if selected
    if (!empty($selected_template_id)) {
        $tmpl_post = get_post($selected_template_id);
        if ($tmpl_post) {
            $emailBody = $tmpl_post->post_content;

            // Replace post placeholders in template
            $replacements = [
                '{{post_title}}'         => $post->post_title,
                '{{post_link}}'          => $permalink,
                '{{post_url}}'           => $permalink,
                '{{post_excerpt}}'       => $excerpt,
                '{{post_content}}'       => $post->post_content,
                '{{featured_image_url}}' => get_the_post_thumbnail_url($post->ID, 'full') ?: '',
            ];

            foreach ($replacements as $key => $val) {
                $emailBody = str_replace($key, (string)$val, $emailBody);
            }

            // Get subject from template excerpt
            if (!empty($tmpl_post->post_excerpt)) {
                $emailSubject = $tmpl_post->post_excerpt;
                foreach ($replacements as $key => $val) {
                    $emailSubject = str_replace($key, (string)$val, $emailSubject);
                }
            }
        }
    }

    // Generate default subject if not set by template
    if (empty($emailSubject)) {
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
        if (empty($category_display_name)) {
            $category_display_name = 'Update';
        }

        $emailSubject = "New {$category_display_name}: " . $post->post_title;
    }

    // Get subscribers with matching tags using direct SQL query
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

    if (empty($contacts)) {
        return;
    }

    // Send emails to each subscriber
    foreach ($contacts as $contact) {
        if (empty($contact->email)) continue;

        // Get full subscriber model for FluentCRM integration
        $subscriber = \FluentCrm\App\Models\Subscriber::find($contact->id);
        if (!$subscriber) continue;

        $finalBody = $emailBody;

        // Send templated HTML email
        if (!empty($selected_template_id)) {
            // Generate secure unsubscribe URL
            $secure_hash = fluentCrmGetContactManagedHash($subscriber->id);
            $unsubscribe_url = add_query_arg(array_filter([
                'fluentcrm'   => 1,
                'route'       => 'unsubscribe',
                'secure_hash' => $secure_hash
            ]), site_url('/'));

            // Get business name for footer
            $business_settings = get_option('_fluentcrm_business_settings', []);
            $business_name = !empty($business_settings['business_name'])
                ? $business_settings['business_name']
                : get_bloginfo('name');

            // Build footer with unsubscribe link
            $footer = '<br/><br/><hr style="border: none; border-top: 1px solid #ddd; margin: 40px 0;"/>';
            $footer .= '<div style="font-size: 12px; color: #666; text-align: center;">';
            $footer .= '<p>' . esc_html($business_name) . '</p>';
            $footer .= '<p><a href="' . esc_url($unsubscribe_url) . '" style="color: #0073aa;">Unsubscribe from this list</a></p>';
            $footer .= '</div>';

            $finalBody .= $footer;

            // Send HTML email
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            wp_mail($subscriber->email, $emailSubject, $finalBody, $headers);
        } else {
            // Send plain text email (fallback when no template selected)
            $unsubscribe_url = apply_filters('fluent_crm/parse_campaign_email_message', '##crm.manage_subscription_url##', $subscriber);

            if (strpos($unsubscribe_url, '##') !== false) {
                $unsubscribe_url = site_url("?fluentcrm=1&route=unsubscribe&hash={$subscriber->hash}&contact_id={$subscriber->id}");
            }

            // Get category name for message
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
            if (empty($category_display_name)) {
                $category_display_name = 'Update';
            }

            // Build plain text message
            $message  = "Hi " . (!empty($subscriber->first_name) ? $subscriber->first_name : 'Reader') . ",\n\n";
            $message .= "A new post in {$category_display_name} has been published: " . $post->post_title . "\n";
            $message .= "Read it here: " . $permalink . "\n\n";
            $message .= $excerpt . "\n\n";
            $message .= "----------------\n";
            $message .= "You are receiving this because you signed up for {$category_display_name} updates from {$blog_name}.\n";
            $message .= "Manage Subscription: " . $unsubscribe_url . "\n";

            wp_mail($subscriber->email, $emailSubject, $message);
        }
    }
}

/**
 * Hook 1: REST API (Block Editor)
 * Fires after post is inserted via REST API, ensuring categories are saved
 */
add_action('rest_after_insert_post', function ($post, $request, $creating) {
    check_and_send_notification($post, 'rest_api');
}, 10, 3);

/**
 * Hook 2: Post Status Transition (Classic Editor / Quick Edit)
 * Fires when post status changes to 'publish'
 */
add_action('transition_post_status', function ($new_status, $old_status, $post) {
    if ($new_status === 'publish' && $old_status !== 'publish') {
        check_and_send_notification($post, 'transition_status');
    }
}, 20, 3);
