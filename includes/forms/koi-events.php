<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form for adding new events.
 *
 * @return false|string
 */
function events_entry_form(): false|string
{
    ob_start();
    ?>
    <form method="post" action="" id="koi-events-form">
        <table>
            <tr>
                <th>Name</th>
            </tr>
            <tr>
                <td><input type="text" id="event_name" name="event_name" required></td>
            </tr>
            <tr>
                <th>Icon URL</th>
            </tr>
            <tr>
                <td><input type="url" id="icon_url" name="icon_url"></td>
            </tr>
        </table>
        <p>
            <input type="submit" name="submit_event" value="Add event" class="button button-primary">
        </p>
        <input type="hidden" name="event_action" value="add_event">
		<?php wp_nonce_field('event_nonce_action', 'event_nonce_field'); ?>
    </form>
	<?php
    return ob_get_clean();
}
/**
 * Form for editing and deleting events.
 *
 * @return void
 */
function events_edit_entry_form(): void
{
    global $wpdb;
    $events_table = $wpdb->prefix . 'koi_events';

    $items_per_page = 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $events_table");

    $events = $wpdb->get_results($wpdb->prepare("
        SELECT id, name, icon_url, created_at
        FROM $events_table
        LIMIT %d OFFSET %d
    ", $items_per_page, $offset));

    echo '<div class="wrap">';
    echo '<h1>Edit events</h1>';

    if ($events) {
        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr><th>Name</th><th>Icon URL</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        foreach ($events as $event) {
            echo '<tr>';
            echo '<form method="post" action="">';
            echo '<td><input type="text" name="name" value="' . esc_attr($event->name) . '" required></td>';
            echo '<td><input type="url" name="icon_url" value="' . esc_attr($event->icon_url) . '"></td>';
            echo '<td>';
            echo '<input type="hidden" name="event_id" value="' . esc_attr($event->id) . '">';
            echo '<button type="submit" name="event_action" value="edit_event" class="button button-primary">Update</button> ';
            echo '<button type="submit" name="event_action" value="delete_event" class="button button-secondary" onclick="return confirm(\'Are you sure you want to delete this event?\');">Delete</button>';
            wp_nonce_field('koi_events_nonce_action', 'koi_events_nonce_field');
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
            echo '<a class="button" href="' . esc_url(add_query_arg(['paged' => $current_page - 1], admin_url('admin.php?page=koi-events-edit'))) . '">Previous</a>';
        }
        if ($current_page < $total_pages) {
            echo '<a class="button" href="' . esc_url(add_query_arg(['paged' => $current_page + 1], admin_url('admin.php?page=koi-events-edit'))) . '">Next</a>';
        }
        echo '</div></div>';
    } else {
        echo '<p>No events found.</p>';
    }

    echo '</div>';
}

/**
 * Adds the menu page for Koi Events in the WordPress admin dashboard.
 *
 * @return void
 */
function events_add_menu_page(): void
{
    add_menu_page(
        'Koi Events',
        'Koi Events',
        'manage_options',
        'koi_events',
        'events_entry_page',
        'dashicons-image-filter',
        2
    );

    add_submenu_page(
        'koi_events',
        'Edit Events',
        'Edit Events',
        'manage_options',
        'koi-events-edit',
        'events_edit_entry_page'
    );
}

/**
 * Main page for adding new events.
 *
 * @return void
 */
function events_entry_page(): void
{
    echo '<div class="wrap">';
    echo '<h1>Koi events form</h1>';
    echo events_entry_form();
    echo '</div>';
}

/**
 * Main page for editing and deleting events.
 *
 * @return void
 */
function events_edit_entry_page(): void
{
    echo '<div class="wrap">';
    events_edit_entry_form();
    echo '</div>';
}

// Action registration for the admin menu.
add_action('admin_menu', 'events_add_menu_page');
