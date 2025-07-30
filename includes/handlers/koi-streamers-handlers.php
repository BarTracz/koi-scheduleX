<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the form submission for adding a new streamer.
 *
 * @return void
 */
function koi_streamers_form_handler(): void
{
    if (isset($_POST['streamer_action']) && $_POST['streamer_action'] === 'add_streamer') {
        if (!isset($_POST['streamer_nonce_field']) || !wp_verify_nonce($_POST['streamer_nonce_field'], 'streamer_nonce_action')) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No permissions.', 'koi-streamers'));
        }

        $name = sanitize_text_field($_POST['streamer_name']);
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $link = esc_url_raw($_POST['streamer_link']);
        $avatar_url = esc_url_raw($_POST['streamer_avatar_url']);

        // Walidacja URL
        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            echo '<div class="error"><p>' . esc_html__('Link invalid.', 'koi-streamers') . '</p></div>';
            return;
        }
        if ($avatar_url && !filter_var($avatar_url, FILTER_VALIDATE_URL)) {
            echo '<div class="error"><p>' . esc_html__('Invalid avatar URL.', 'koi-streamers') . '</p></div>';
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'koi_streamers';

        $wpdb->insert($table_name, [
            'name' => $name,
            'user_id' => $user_id > 0 ? $user_id : null,
            'link' => $link,
            'avatar_url' => $avatar_url
        ], [
            '%s',
            '%d',
            '%s',
            '%s'
        ]);

        if ($wpdb->insert_id) {
            echo '<div class="updated"><p>' . esc_html__('Streamer added successfully', 'koi-streamers') . '</p></div>';
        } else {
            error_log('Database Insert Error: ' . $wpdb->last_error);
            echo '<div class="error"><p>' . esc_html__('Failed to add streamer. Please try again.', 'koi-streamers') . '</p></div>';
        }
    }
}

/**
 * Handles the form submission for editing or deleting a streamer.
 *
 * @return void
 */
function koi_streamers_edit_form_handler(): void
{
    global $wpdb;
    $streamers_table = $wpdb->prefix . 'koi_streamers';

    // Editing streamer.
    if (isset($_POST['streamer_action']) && $_POST['streamer_action'] === 'edit_streamer') {
        if (!isset($_POST['koi_streamers_nonce_field']) || !wp_verify_nonce($_POST['koi_streamers_nonce_field'], 'koi_streamers_nonce_action')) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No permissions.', 'koi-streamers'));
        }

        $streamer_id = intval($_POST['streamer_id']);
        $name = sanitize_text_field($_POST['name']);
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $link = esc_url_raw($_POST['link']);
        $avatar_url = esc_url_raw($_POST['avatar_url']);

        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            echo '<div class="error"><p>' . esc_html__('Link invalid.', 'koi-streamers') . '</p></div>';
            return;
        }
        if ($avatar_url && !filter_var($avatar_url, FILTER_VALIDATE_URL)) {
            echo '<div class="error"><p>' . esc_html__('Invalid avatar URL.', 'koi-streamers') . '</p></div>';
            return;
        }

        $result = $wpdb->update(
            $streamers_table,
            ['name' => $name, 'link' => $link, 'avatar_url' => $avatar_url, 'user_id' => $user_id > 0 ? $user_id : null],
            ['id' => $streamer_id],
            ['%s', '%s', '%s', '%d'],
            ['%d']
        );

        if ($result !== false) {
            echo '<div class="updated"><p>' . esc_html__('Streamer updated successfully', 'koi-streamers') . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__('Database error: ', 'koi-streamers') . esc_html($wpdb->last_error) . '</p></div>';
        }
    }
    // Deleting streamer.
    elseif (isset($_POST['streamer_action']) && $_POST['streamer_action'] === 'delete_streamer') {
        if (!isset($_POST['koi_streamers_nonce_field']) || !wp_verify_nonce($_POST['koi_streamers_nonce_field'], 'koi_streamers_nonce_action')) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No permissions.', 'koi-streamers'));
        }

        $streamer_id = intval($_POST['streamer_id']);

        $result = $wpdb->delete($streamers_table, ['id' => $streamer_id], ['%d']);

        if ($result !== false) {
            echo '<div class="updated"><p>' . esc_html__('Streamer deleted successfully', 'koi-streamers') . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__('Database error: ', 'koi-streamers') . esc_html($wpdb->last_error) . '</p></div>';
        }
    }
}

// Register the form handlers to the 'init' action hook.
add_action('init', 'koi_streamers_form_handler');
add_action('init', 'koi_streamers_edit_form_handler');
