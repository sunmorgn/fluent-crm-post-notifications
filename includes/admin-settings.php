<?php

namespace FCPN\Admin;

/**
 * Register Settings Page
 */
add_action('admin_menu', function () {
    add_options_page(
        'FluentCRM Post Notifications',
        'Post Notifications',
        'manage_options',
        'fluent-crm-post-notifications',
        __NAMESPACE__ . '\\render_settings_page'
    );
});

/**
 * Register Setting
 */
add_action('admin_init', function () {
    register_setting('fcpn_settings_group', 'fcpn_rules');
});

/**
 * Render Page
 */
function render_settings_page() {
    if (! current_user_can('manage_options')) {
        return;
    }

    // Get existing rules - Default to empty array
    $rules = get_option('fcpn_rules', []);

    // BACKWARD COMPATIBILITY: Check for old option name if new one is empty
    if (empty($rules)) {
        $old_rules = get_option('crpc_reading_rules');
        if (!empty($old_rules)) {
            $rules = $old_rules;
            // Optionally migrate: update_option('fcpn_rules', $old_rules);
        }
    }

    if (! is_array($rules)) {
        $rules = [];
    }

    // Get Data for Dropdowns
    $categories = get_categories(['hide_empty' => false]);

    // Get FluentCRM Tags safely
    $tags = [];
    if (function_exists('FluentCrmApi')) {
        $tags = \FluentCrmApi('tags')->all();
    }

?>
    <div class="wrap">
        <h1>FluentCRM Post Notifications</h1>
        <p>Configure which Post Categories should trigger emails to which FluentCRM Tags.</p>

        <?php if (! function_exists('FluentCrmApi')) : ?>
            <div class="notice notice-error">
                <p>FluentCRM is not active. This plugin requires FluentCRM.</p>
            </div>
        <?php endif; ?>

        <form action="options.php" method="post">
            <?php
            settings_fields('fcpn_settings_group');
            do_settings_sections('fcpn_settings_group');
            ?>

            <table class="widefat fixed" id="fcpn_rules_table" style="margin-bottom: 20px;">
                <thead>
                    <tr>
                        <th>Post Category</th>
                        <th>Send To (FluentCRM Tag)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="fcpn_rules_tbody">
                    <?php if (empty($rules)) : ?>
                        <tr class="empty-row">
                            <td colspan="3">No rules configured. Add one below.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($rules as $index => $rule) : ?>
                            <tr>
                                <td>
                                    <select name="fcpn_rules[<?php echo $index; ?>][category_id]" style="width: 100%;">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat) : ?>
                                            <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($rule['category_id'], $cat->term_id); ?>>
                                                <?php echo esc_html($cat->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="fcpn_rules[<?php echo $index; ?>][tag_id]" style="width: 100%;">
                                        <option value="">Select Tag</option>
                                        <?php foreach ($tags as $tag) : ?>
                                            <option value="<?php echo esc_attr($tag->id); ?>" <?php selected($rule['tag_id'], $tag->id); ?>>
                                                <?php echo esc_html($tag->title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <button type="button" class="button remove-row">Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <button type="button" class="button" id="add_row">Add New Rule</button>
            <br><br>

            <?php submit_button(); ?>
        </form>

        <!-- Simple JS to handle Add/Remove rows -->
        <script>
            jQuery(document).ready(function($) {
                const tbody = $('#fcpn_rules_tbody');
                const addButton = $('#add_row');

                // Template for new row (using PHP data)
                // Use safe fallback if arrays are empty
                const categories = <?php echo !empty($categories) ? json_encode(array_values(array_map(function ($c) {
                                        return ['id' => $c->term_id, 'name' => $c->name];
                                    }, $categories)), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : '[]'; ?>;

                const tags = <?php
                                $tags_for_js = [];
                                if (!empty($tags) && is_iterable($tags)) {
                                    foreach ($tags as $t) {
                                        $tags_for_js[] = ['id' => $t->id, 'title' => $t->title];
                                    }
                                }
                                echo json_encode($tags_for_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                ?>;

                addButton.on('click', function(e) {
                    e.preventDefault();

                    // Use timestamp to ensure unique index even if rows are deleted
                    const index = new Date().getTime();

                    // Clear empty row message if exists
                    tbody.find('.empty-row').remove();

                    let catOptions = '<option value="">Select Category</option>';
                    if (categories.length > 0) {
                        $.each(categories, function(i, c) {
                            catOptions += '<option value="' + c.id + '">' + c.name + '</option>';
                        });
                    }

                    let tagOptions = '<option value="">Select Tag</option>';
                    if (tags.length > 0) {
                        $.each(tags, function(i, t) {
                            tagOptions += '<option value="' + t.id + '">' + t.title + '</option>';
                        });
                    }

                    var rowHtml = '<tr>' +
                        '<td>' +
                        '<select name="fcpn_rules[' + index + '][category_id]" style="width: 100%;">' +
                        catOptions +
                        '</select>' +
                        '</td>' +
                        '<td>' +
                        '<select name="fcpn_rules[' + index + '][tag_id]" style="width: 100%;">' +
                        tagOptions +
                        '</select>' +
                        '</td>' +
                        '<td>' +
                        '<button type="button" class="button remove-row">Remove</button>' +
                        '</td>' +
                        '</tr>';

                    tbody.append(rowHtml);
                });

                tbody.on('click', '.remove-row', function(e) {
                    e.preventDefault();
                    $(this).closest('tr').remove();
                });
            });
        </script>
    </div>
<?php
}
