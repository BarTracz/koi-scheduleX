<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the form submission for adding a new subathon.
 *
 * @return void
 */
function koi_subathons_form_handler(): void
{
    if (isset($_POST['subathon_action']) && $_POST['subathon_action'] === 'add_subathon') {
        if (!isset($_POST['subathon_nonce_field']) || !wp_verify_nonce($_POST['subathon_nonce_field'], 'subathon_nonce_action')) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No permissions.', 'koi-subathons'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'koi_subathons';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $hour = isset($_POST['hour']) ? intval($_POST['hour']) : null;
        $minute = isset($_POST['minute']) ? intval($_POST['minute']) : null;
        if ($hour === null || $minute === null || $hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            echo '<div class="error"><p>' . esc_html__('Invalid hour or minute.', 'koi-subathons') . '</p></div>';
            return;
        }
        $time = sprintf('%02d:%02d', $hour, $minute);
        if (empty($date) || empty($time)) {
            echo '<div class="error"><p>' . esc_html__('Date and time are required.', 'koi-subathons') . '</p></div>';
            return;
        }

        $start_date = $date . ' ' . $time;

        $wpdb->insert($table_name, [
            'streamer_id' => intval($_POST['streamer_id']),
            'timer_link' => esc_url_raw($_POST['timer_link']),
            'goals_link' => esc_url_raw($_POST['goals_link']),
            'timer_link_mobile' => esc_url_raw($_POST['timer_link_mobile']),
            'goals_link_mobile' => esc_url_raw($_POST['goals_link_mobile']),
            'start_date' => $start_date
        ], [
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s'
        ]);

        if ($wpdb->insert_id) {
            echo '<div class="updated"><p>' . esc_html__('Subathon added successfully', 'koi-subathons') . '</p></div>';
        } else {
            error_log('Database Insert Error: ' . $wpdb->last_error);
            echo '<div class="error"><p>' . esc_html__('Failed to add subathon. Please try again.', 'koi-subathons') . '</p></div>';
        }
    }
}

/**
 * Handles the form submission for editing or deleting a subathon.
 *
 * @return void
 */
function koi_subathons_edit_form_handler(): void
{
    global $wpdb;
    $subathons_table = $wpdb->prefix . 'koi_subathons';

    // Subathon editing
    if (isset($_POST['subathon_action']) && $_POST['subathon_action'] === 'edit_subathon') {
        if (!isset($_POST['koi_subathons_nonce_field']) || !wp_verify_nonce($_POST['koi_subathons_nonce_field'], 'koi_subathons_nonce_action')) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No permissions.', 'koi-subathons'));
        }

        $subathon_id = intval($_POST['subathon_id']);
        $date = sanitize_text_field($_POST['date']);
        $hour = isset($_POST['hour']) ? intval($_POST['hour']) : null;
        $minute = isset($_POST['minute']) ? intval($_POST['minute']) : null;
        if ($hour === null || $minute === null || $hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            echo '<div class="error"><p>' . esc_html__('Invalid hour or minute.', 'koi-subathons') . '</p></div>';
            return;
        }
        $time = sprintf('%02d:%02d', $hour, $minute);
        $start_date = $date . ' ' . $time;

        $result = $wpdb->update(
            $subathons_table,
            [
                'streamer_id' => intval($_POST['streamer_id']),
                'timer_link' => esc_url_raw($_POST['timer_link']),
                'goals_link' => esc_url_raw($_POST['goals_link']),
                'timer_link_mobile' => esc_url_raw($_POST['timer_link_mobile']),
                'goals_link_mobile' => esc_url_raw($_POST['goals_link_mobile']),
                'start_date' => $start_date,
            ],
            ['id' => $subathon_id],
            ['%d', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            echo '<div class="updated"><p>' . esc_html__('Subathon updated successfully', 'koi-subathons') . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__('Database error: ', 'koi-subathons') . esc_html($wpdb->last_error) . '</p></div>';
        }
    }
    // Subathon deletion
    elseif (isset($_POST['subathon_action']) && $_POST['subathon_action'] === 'delete_subathon') {
        if (!isset($_POST['koi_subathons_nonce_field']) || !wp_verify_nonce($_POST['koi_subathons_nonce_field'], 'koi_subathons_nonce_action')) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No permissions.', 'koi-subathons'));
        }

        $subathon_id = intval($_POST['subathon_id']);

        $result = $wpdb->delete($subathons_table, ['id' => $subathon_id], ['%d']);

        if ($result !== false) {
            echo '<div class="updated"><p>' . esc_html__('Subathon deleted successfully', 'koi-subathons') . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__('Database error: ', 'koi-subathons') . esc_html($wpdb->last_error) . '</p></div>';
        }
    }
}

// Register the form handlers to the 'init' action hook.
add_action('init', 'koi_subathons_form_handler');
add_action('init', 'koi_subathons_edit_form_handler');
