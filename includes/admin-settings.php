<?php

namespace CRPC\Notifications\Admin;

/**
 * Register Settings Page
 */
add_action('admin_menu', function () {
    add_options_page(
        'Reading Program Notifications',
        'Reading Program',
        'manage_options',
        'crpc-reading-notifications',
        __NAMESPACE__ . '\\render_settings_page'
    );
});

/**
 * Register Setting
 */
add_action('admin_init', function () {
    register_setting('crpc_reading_notifications_group', 'crpc_reading_rules');
});

/**
 * Render Page
 */
function render_settings_page() {
    if (! current_user_can('manage_options')) {
        return;
    }

    // Get existing rules
    $rules = get_option('crpc_reading_rules', []);
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
        <h1>Reading Program Notifications</h1>
        <p>Configure which Post Categories should trigger emails to which FluentCRM Tags.</p>

        <?php if (! function_exists('FluentCrmApi')) : ?>
            <div class="notice notice-error">
                <p>FluentCRM is not active. This plugin requires FluentCRM.</p>
            </div>
        <?php endif; ?>

        <form action="options.php" method="post">
            <?php
            settings_fields('crpc_reading_notifications_group');
            do_settings_sections('crpc_reading_notifications_group');
            ?>

            <table class="widefat fixed" id="crpc_rules_table" style="margin-bottom: 20px;">
                <thead>
                    <tr>
                        <th>Post Category</th>
                        <th>Send To (FluentCRM Tag)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="crpc_rules_tbody">
                    <?php if (empty($rules)) : ?>
                        <tr class="empty-row">
                            <td colspan="3">No rules configured. Add one below.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($rules as $index => $rule) : ?>
                            <tr>
                                <td>
                                    <select name="crpc_reading_rules[<?php echo $index; ?>][category_id]" style="width: 100%;">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat) : ?>
                                            <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($rule['category_id'], $cat->term_id); ?>>
                                                <?php echo esc_html($cat->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="crpc_reading_rules[<?php echo $index; ?>][tag_id]" style="width: 100%;">
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
            document.addEventListener('DOMContentLoaded', function() {
                const tbody = document.getElementById('crpc_rules_tbody');
                const addButton = document.getElementById('add_row');

                // Template for new row (using PHP data)
                const categories = <?php echo json_encode(array_map(function ($c) {
                                        return ['id' => $c->term_id, 'name' => $c->name];
                                    }, $categories)); ?>;
                const tags = <?php echo json_encode(array_map(function ($t) {
                                    return ['id' => $t->id, 'title' => $t->title];
                                }, $tags)); ?>;

                addButton.addEventListener('click', function() {
                    const index = tbody.querySelectorAll('tr:not(.empty-row)').length;
                    const tr = document.createElement('tr');

                    // Clear empty row message if exists
                    const emptyRow = tbody.querySelector('.empty-row');
                    if (emptyRow) emptyRow.remove();

                    let catOptions = '<option value="">Select Category</option>';
                    categories.forEach(c => catOptions += `<option value="${c.id}">${c.name}</option>`);

                    let tagOptions = '<option value="">Select Tag</option>';
                    tags.forEach(t => tagOptions += `<option value="${t.id}">${t.title}</option>`);

                    tr.innerHTML = `
						<td>
							<select name="crpc_reading_rules[${index}][category_id]" style="width: 100%;">
								${catOptions}
							</select>
						</td>
						<td>
							<select name="crpc_reading_rules[${index}][tag_id]" style="width: 100%;">
								${tagOptions}
							</select>
						</td>
						<td>
							<button type="button" class="button remove-row">Remove</button>
						</td>
					`;
                    tbody.appendChild(tr);
                });

                tbody.addEventListener('click', function(e) {
                    if (e.target.classList.contains('remove-row')) {
                        e.target.closest('tr').remove();
                    }
                });
            });
        </script>
    </div>
<?php
}
