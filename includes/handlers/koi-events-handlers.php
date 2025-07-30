<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the form submission for adding a new event.
 *
 * @return void
 */
function koi_events_form_handler(): void
{
    if (isset($_POST['event_action']) && $_POST['event_action'] === 'add_event') {
        if (!isset($_POST['event_nonce_field']) || !wp_verify_nonce($_POST['event_nonce_field'], 'event_nonce_action')) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No permissions.', 'koi-events'));
        }

        $name = sanitize_text_field($_POST['event_name']);
        $icon_url = esc_url_raw($_POST['icon_url']);

        // Validate the icon URL
        if ($icon_url && !filter_var($icon_url, FILTER_VALIDATE_URL)) {
            echo '<div class="error"><p>' . esc_html__('Invalid icon URL.', 'koi-events') . '</p></div>';
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'koi_events';

        $wpdb->insert($table_name, [
            'name' => $name,
            'icon_url' => $icon_url
        ], [
            '%s',
            '%s'
        ]);

        if ($wpdb->insert_id) {
            echo '<div class="updated"><p>' . esc_html__('Event added successfully', 'koi-events') . '</p></div>';
        } else {
            error_log('Database Insert Error: ' . $wpdb->last_error);
            echo '<div class="error"><p>' . esc_html__('Failed to add event. Please try again.', 'koi-events') . '</p></div>';
        }
    }
}

/**
 * Handles the form submission for editing or deleting an event.
 *
 * @return void
 */
function koi_events_edit_form_handler(): void
{
    global $wpdb;
    $events_table = $wpdb->prefix . 'koi_events';

    // Event editing
    if (isset($_POST['event_action']) && $_POST['event_action'] === 'edit_event') {
        if (!isset($_POST['koi_events_nonce_field']) || !wp_verify_nonce($_POST['koi_events_nonce_field'], 'koi_events_nonce_action')) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No permissions.', 'koi-events'));
        }

        $event_id = intval($_POST['event_id']);
        $name = sanitize_text_field($_POST['name']);
        $icon_url = esc_url_raw($_POST['icon_url']);

        if ($icon_url && !filter_var($icon_url, FILTER_VALIDATE_URL)) {
            echo '<div class="error"><p>' . esc_html__('Invalid icon URL.', 'koi-events') . '</p></div>';
            return;
        }

        $result = $wpdb->update(
            $events_table,
            ['name' => $name, 'icon_url' => $icon_url],
            ['id' => $event_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            echo '<div class="updated"><p>' . esc_html__('Event updated successfully', 'koi-events') . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__('Database error: ', 'koi-events') . esc_html($wpdb->last_error) . '</p></div>';
        }
    }
    // Event deletion
    elseif (isset($_POST['event_action']) && $_POST['event_action'] === 'delete_event') {
        if (!isset($_POST['koi_events_nonce_field']) || !wp_verify_nonce($_POST['koi_events_nonce_field'], 'koi_events_nonce_action')) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No permissions.', 'koi-events'));
        }

        $event_id = intval($_POST['event_id']);

        $result = $wpdb->delete($events_table, ['id' => $event_id], ['%d']);

        if ($result !== false) {
            echo '<div class="updated"><p>' . esc_html__('Event deleted successfully', 'koi-events') . '</p></div>';
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Database error: ' . $wpdb->last_error);
            }
            echo '<div class="error"><p>' . esc_html__('Cannot delete event: there are entries linked to this event.', 'koi-events') . '</p></div>';
        }
    }
}

// Register the form handlers to the 'init' action hook.
add_action('init', 'koi_events_form_handler');
add_action('init', 'koi_events_edit_form_handler');
