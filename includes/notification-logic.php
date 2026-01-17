<?php

namespace FCPN\Logic;

/**
 * Core Notification Processing Logic
 * 
 * @param WP_Post|int $post The post object or ID
 * @param string $source The source of the call (for debugging)
 */
function fcpn_process_post_notification($post, $source = 'unknown') {

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

    // Prepare common post data
    $excerpt = get_the_excerpt($post);
    if (empty($excerpt)) {
        $excerpt = wp_trim_words($post->post_content, 20);
    }
    $blog_name = get_bloginfo('name');

    // Generate Category Display Name for Subject and Footer
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
    $category_display_name = !empty($matching_category_names) ? implode(' & ', $matching_category_names) : 'Update';

    // Get the final, canonical permalink (avoids redirects for trailing slashes, etc.)
    $permalink = wp_get_canonical_url($post->ID);
    if (!$permalink) {
        $permalink = get_permalink($post->ID);
    }

    // Prepare content and subject
    $emailSubject = '';
    $emailBody = '';

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

        // Shared dynamic data
        $first_name = !empty($subscriber->first_name) ? $subscriber->first_name : 'Reader';
        $sub_reason = "You are receiving this because you signed up for {$category_display_name} updates from {$blog_name}.";

        $secure_hash = fluentCrmGetContactManagedHash($subscriber->id);
        $unsubscribe_url = add_query_arg(array_filter([
            'fluentcrm'   => 1,
            'route'       => 'unsubscribe',
            'secure_hash' => $secure_hash
        ]), site_url('/'));

        // Shared HTML Footer
        $footer = '<div style="margin-top: 60px; padding-top: 20px; border-top: 1px solid #eeeeee; font-family: sans-serif; font-size: 13px; color: #666666; line-height: 1.6;">';
        $footer .= '<p style="margin: 0 0 20px 0;">' . esc_html($sub_reason) . '</p>';
        $footer .= '<div style="text-align: center;">';
        $footer .= '<p style="margin: 0 0 5px 0; color: #333333; font-weight: bold;">' . esc_html($blog_name) . '</p>';
        $footer .= '<p style="margin: 0;"><a href="' . esc_url($unsubscribe_url) . '" style="color: #0073aa; text-decoration: underline;">Unsubscribe</a></p>';
        $footer .= '</div>';
        $footer .= '</div>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        if (!empty($selected_template_id)) {
            // Send Templated HTML
            $finalBody = $emailBody . $footer;
            wp_mail($subscriber->email, $emailSubject, $finalBody, $headers);
        } else {
            // Send Fallback HTML (Professional default)
            $message  = '<div style="font-family: sans-serif; font-size: 15px; line-height: 1.6; color: #333333;">';
            $message .= '<p>Hi ' . esc_html($first_name) . ',</p>';
            $message .= '<p>A new post in <strong>' . esc_html($category_display_name) . '</strong> has been published: <strong>' . esc_html($post->post_title) . '</strong></p>';
            $message .= '<p><a href="' . esc_url($permalink) . '" style="color: #0073aa; text-decoration: underline;">Read it here</a></p>';
            if (!empty($excerpt)) {
                $message .= '<div style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #eeeeee; font-style: italic;">' . nl2br(esc_html($excerpt)) . '</div>';
            }
            $message .= $footer;
            $message .= '</div>';

            wp_mail($subscriber->email, $emailSubject, $message, $headers);
        }
    }
}

/**
 * Hook 1: REST API (Block Editor)
 */
add_action('rest_after_insert_post', function ($post, $request, $creating) {
    fcpn_process_post_notification($post, 'rest_api');
}, 10, 3);

/**
 * Hook 2: Post Status Transition (Classic/Quick/Auto)
 */
add_action('transition_post_status', function ($new_status, $old_status, $post) {
    if ($new_status === 'publish' && $old_status !== 'publish') {
        fcpn_process_post_notification($post, 'transition_status');
    }
}, 20, 3);
