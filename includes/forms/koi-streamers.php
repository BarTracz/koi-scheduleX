<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds a new streamer entry form.
 *
 * @return false|string
 */
function streamers_entry_form(): false|string
{
    ob_start();
    ?>
    <form method="post" action="" id="koi-streamers-form">
        <table>
            <tr>
                <th>Name</th>
            </tr>
            <tr>
                <td><input type="text" id="streamer_name" name="streamer_name" required></td>
            </tr>
            <tr>
                <th>User ID</th>
            </tr>
            <tr>
                <td><input type="number" id="user_id" name="user_id" min="1"></td>
            </tr>
            <tr>
                <th>Link</th>
            </tr>
            <tr>
                <td><input type="url" id="streamer_link" name="streamer_link" required></td>
            </tr>
            <tr>
                <th>Avatar URL</th>
            </tr>
            <tr>
                <td><input type="url" id="streamer_avatar_url" name="streamer_avatar_url"></td>
            </tr>
        </table>
        <p>
            <input type="submit" name="submit_streamer" value="Add streamer" class="button button-primary">
        </p>
        <input type="hidden" name="streamer_action" value="add_streamer">
		<?php wp_nonce_field('streamer_nonce_action', 'streamer_nonce_field'); ?>
    </form>
	<?php
    return ob_get_clean();
}

/**
 * Form for editing and deleting streamers.
 *
 * @return void
 */
function streamers_edit_entry_form(): void
{
    global $wpdb;
    $streamers_table = $wpdb->prefix . 'koi_streamers';

    $items_per_page = 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $streamers_table");

    $streamers = $wpdb->get_results($wpdb->prepare("
        SELECT id, user_id, name, link, avatar_url, created_at
        FROM $streamers_table
        LIMIT %d OFFSET %d
    ", $items_per_page, $offset));

    echo '<div class="wrap">';
    echo '<h1>Edit Streamers</h1>';

    if ($streamers) {
        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr><th>Name</th><th>User ID</th><th>Link</th><th>Avatar</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        foreach ($streamers as $streamer) {
            echo '<tr>';
            echo '<form method="post" action="">';
            echo '<td><input type="text" name="name" value="' . esc_attr($streamer->name) . '" required></td>';
            echo '<td><input type="number" name="user_id" value="' . esc_attr($streamer->user_id) . '"></td>';
            echo '<td><input type="url" name="link" value="' . esc_attr($streamer->link) . '" required></td>';
            echo '<td><input type="url" name="avatar_url" value="' . esc_attr($streamer->avatar_url) . '"></td>';
            echo '<td>';
            echo '<input type="hidden" name="streamer_id" value="' . esc_attr($streamer->id) . '">';
            echo '<button type="submit" name="streamer_action" value="edit_streamer" class="button button-primary">Update</button> ';
            echo '<button type="submit" name="streamer_action" value="delete_streamer" class="button button-secondary" onclick="return confirm(\'Are you sure you want to delete this streamer?\');">Delete</button>';
            wp_nonce_field('koi_streamers_nonce_action', 'koi_streamers_nonce_field');
            echo '</td>';
            echo '</form>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        // Pagination
        $total_pages = ceil($total_items / $items_per_page);
        echo '<div class="tablenav"><div class="tablenav-pages">';
        if ($current_page > 1) {
            echo '<a class="button" href="' . esc_url(add_query_arg(['paged' => $current_page - 1], admin_url('admin.php?page=koi-streamers-edit'))) . '">Previous</a>';
        }
        if ($current_page < $total_pages) {
            echo '<a class="button" href="' . esc_url(add_query_arg(['paged' => $current_page + 1], admin_url('admin.php?page=koi-streamers-edit'))) . '">Next</a>';
        }
        echo '</div></div>';
    } else {
        echo '<p>No streamers found.</p>';
    }

    echo '</div>';
}

/**
 * Adds a menu page for Koi Streamers in the WordPress admin dashboard.
 *
 * @return void
 */
function streamers_add_menu_page(): void
{
    add_menu_page(
        'Koi Streamers',
        'Koi Streamers',
        'manage_options',
        'koi_streamers',
        'streamers_entry_page',
        'dashicons-admin-users',
        2
    );

    add_submenu_page(
        'koi_streamers',
        'Edit Streamers',
        'Edit Streamers',
        'manage_options',
        'koi-streamers-edit',
        'streamers_edit_entry_page'
    );
}

/**
 * Main page for adding new streamers.
 *
 * @return void
 */
function streamers_entry_page(): void
{
    echo '<div class="wrap">';
    echo '<h1>Koi streamers form</h1>';
    echo streamers_entry_form();
    echo '</div>';
}

/**
 * Page for editing existing streamers.
 *
 * @return void
 */
function streamers_edit_entry_page()
{
    echo '<div class="wrap">';
    streamers_edit_entry_form();
    echo '</div>';
}

// Action registration for the admin menu and styles.
add_action('admin_menu', 'streamers_add_menu_page');
