<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the form submission for adding a new schedule entry.
 *
 * @return void
 */
function schedule_entry_form_handler(): void
{
    if (
        isset($_POST['schedule_action']) && $_POST['schedule_action'] === 'add_schedule'
    ) {
        if (
            !isset($_POST['koi_schedule_nonce_field']) ||
            !wp_verify_nonce($_POST['koi_schedule_nonce_field'], 'koi_schedule_nonce_action')
        ) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No permissions.', 'koi-schedule'));
        }

        $streamer_id = intval($_POST['streamer_id']);
        $schedule_entries = $_POST['schedule_entries'];

        global $wpdb;
        $table_name = $wpdb->prefix . 'koi_schedule';
        $success = false;

        foreach ($schedule_entries as $entry) {
            $date = sanitize_text_field($entry['date']);
            // Get the time from the radio button or custom input.
            if (isset($entry['time_radio'])) {
                if ($entry['time_radio'] === 'other') {
                    $hour = isset($entry['hour']) ? intval($entry['hour']) : null;
                    $minute = isset($entry['minute']) ? intval($entry['minute']) : null;
                    if ($hour === null || $minute === null || $hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
                        echo '<div class="error"><p>' . esc_html__('Invalid hour or minute.', 'koi-schedule') . '</p></div>';
                        continue;
                    }
                    $time = sprintf('%02d:%02d', $hour, $minute);
                } else {
                    $time = sanitize_text_field($entry['time_radio']);
                }
            } else {
                $time = '00:00';
            }
            // Validate date and time format.
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
                echo '<div class="error"><p>' . esc_html__('Invalid date/time format.', 'koi-schedule') . '</p></div>';
                continue;
            }

            $datetime = $date . ' ' . $time;

            $event_id = isset($entry['event_id']) ? intval($entry['event_id']) : null;
            $wpdb->insert($table_name, array(
                'time' => $datetime,
                'streamer_id' => $streamer_id,
                'event_id' => $event_id
            ), array(
                '%s',
                '%d',
                '%d'
            ));
            if ($wpdb->insert_id) {
                $success = true;
            }
        }
        if ($success) {
            echo '<div class="updated"><p>' . esc_html__('Entry added successfully', 'koi-schedule') . '</p></div>';
        } else {
            error_log('Database Insert Error: ' . $wpdb->last_error);
            echo '<div class="error"><p>' . esc_html__('Failed to add entry. Please try again.', 'koi-schedule') . '</p></div>';
        }
    }
}

/**
 * Handles the form submission for editing or deleting schedule entries.
 *
 * @return void
 */
function schedule_edit_entry_form_handler(): void
{
    global $wpdb;
    $schedule_table = $wpdb->prefix . 'koi_schedule';

    // Deleting older entries.
    if (isset($_POST['schedule_action']) && $_POST['schedule_action'] === 'delete_older') {
        if (
            !isset($_POST['koi_schedule_nonce_field']) ||
            !wp_verify_nonce($_POST['koi_schedule_nonce_field'], 'koi_schedule_nonce_action')
        ) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No permissions.', 'koi-schedule'));
        }

        $delete_older_date = sanitize_text_field($_POST['delete_older_date']);
        $delete_direction = isset($_POST['delete_direction']) ? $_POST['delete_direction'] : 'older';
        $operator = $delete_direction === 'newer' ? '>' : '<';

        if ($delete_older_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $delete_older_date)) {
            $result = $wpdb->query($wpdb->prepare(
                "DELETE FROM $schedule_table WHERE time $operator %s",
                $delete_older_date . ' 00:00:00'
            ));
            if ($result !== false) {
                $msg = $delete_direction === 'newer'
                    ? esc_html__('Entries newer than ', 'koi-schedule')
                    : esc_html__('Entries older than ', 'koi-schedule');
                echo '<div class="updated"><p>' . $msg . esc_html($delete_older_date) . esc_html__(' deleted successfully', 'koi-schedule') . '</p></div>';
            } else {
                echo '<div class="error"><p>' . esc_html__('Database error: ', 'koi-schedule') . esc_html($wpdb->last_error) . '</p></div>';
            }
        } else {
            echo '<div class="error"><p>' . esc_html__('Please select a valid date.', 'koi-schedule') . '</p></div>';
        }
    }

    // Editing a single entry.
    if (isset($_POST['schedule_action']) && $_POST['schedule_action'] === 'edit_schedule') {
        if (
            !isset($_POST['koi_schedule_nonce_field']) ||
            !wp_verify_nonce($_POST['koi_schedule_nonce_field'], 'koi_schedule_nonce_action')
        ) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No permissions.', 'koi-schedule'));
        }

        $entry_id = intval($_POST['entry_id']);
        $streamer_id = intval($_POST['streamer_id']);
        $date = sanitize_text_field($_POST['date']);
        $hour = isset($_POST['hour']) ? intval($_POST['hour']) : null;
        $minute = isset($_POST['minute']) ? intval($_POST['minute']) : null;
        if ($hour === null || $minute === null || $hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            echo '<div class="error"><p>' . esc_html__('Invalid hour or minute.', 'koi-schedule') . '</p></div>';
            return;
        }
        $time = sprintf('%02d:%02d', $hour, $minute);
        $datetime = $date . ' ' . $time;
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : null;
        $result = $wpdb->update(
            $schedule_table,
            ['time' => $datetime, 'streamer_id' => $streamer_id, 'event_id' => $event_id],
            ['id' => $entry_id],
            ['%s', '%d', '%d'],
            ['%d']
        );


        if ($result !== false) {
            echo '<div class="updated"><p>' . esc_html__('Schedule entry updated successfully', 'koi-schedule') . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__('Database error: ', 'koi-schedule') . esc_html($wpdb->last_error) . '</p></div>';
        }
    }

    if (isset($_POST['schedule_action']) && $_POST['schedule_action'] === 'bulk_edit') {
        if (
            !isset($_POST['koi_schedule_nonce_field']) ||
            !wp_verify_nonce($_POST['koi_schedule_nonce_field'], 'koi_schedule_nonce_action')
        ) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No permissions.', 'koi-schedule'));
        }
        $ids = isset($_POST['bulk_ids']) ? array_map('intval', $_POST['bulk_ids']) : [];
        if (!$ids) {
            echo '<div class="error"><p>No entries selected.</p></div>';
            return;
        }
        $fields = [];
        if (!empty($_POST['bulk_streamer_id'])) {
            $fields['streamer_id'] = intval($_POST['bulk_streamer_id']);
        }
        if (!empty($_POST['bulk_event_id'])) {
            $fields['event_id'] = intval($_POST['bulk_event_id']);
        }

        $bulk_date = !empty($_POST['bulk_date']) ? sanitize_text_field($_POST['bulk_date']) : '';
        $bulk_hour = isset($_POST['bulk_hour']) && $_POST['bulk_hour'] !== '' ? intval($_POST['bulk_hour']) : null;
        $bulk_minute = isset($_POST['bulk_minute']) && $_POST['bulk_minute'] !== '' ? intval($_POST['bulk_minute']) : null;

        if ($bulk_date !== '' || $bulk_hour !== null || $bulk_minute !== null) {
            global $wpdb;
            foreach ($ids as $id) {
                $current_time = $wpdb->get_var($wpdb->prepare(
                    "SELECT time FROM {$wpdb->prefix}koi_schedule WHERE id = %d",
                    $id
                ));
                if (!$current_time) {
                    continue;
                }
                $dt = new DateTime($current_time);

                // Zmień datę, jeśli podano
                if ($bulk_date !== '') {
                    $dt->setDate(
                        (int)substr($bulk_date, 0, 4),
                        (int)substr($bulk_date, 5, 2),
                        (int)substr($bulk_date, 8, 2)
                    );
                }
                // Change time if provided.
                if ($bulk_hour !== null && $bulk_minute !== null) {
                    $dt->setTime($bulk_hour, $bulk_minute, 0);
                }
                $fields['time'] = $dt->format('Y-m-d H:i:s');
                $wpdb->update($wpdb->prefix . 'koi_schedule', $fields, ['id' => $id]);
            }
            echo '<div class="updated"><p>Group edit finished.</p></div>';
            return;
        }

        if ($fields) {
            global $wpdb;
            foreach ($ids as $id) {
                $wpdb->update($wpdb->prefix . 'koi_schedule', $fields, ['id' => $id]);
            }
            echo '<div class="updated"><p>Group edit finished.</p></div>';
        } else {
            echo '<div class="error"><p>No changes chosen.</p></div>';
        }
    }

    // Deleting a single entry.
    if (isset($_POST['schedule_action']) && $_POST['schedule_action'] === 'delete_schedule') {
        if (
            !isset($_POST['koi_schedule_nonce_field']) ||
            !wp_verify_nonce($_POST['koi_schedule_nonce_field'], 'koi_schedule_nonce_action')
        ) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No permissions.', 'koi-schedule'));
        }

        $entry_id = intval($_POST['entry_id']);

        $result = $wpdb->delete(
            $schedule_table,
            ['id' => $entry_id],
            ['%d']
        );

        if ($result !== false) {
            echo '<div class="updated"><p>' . esc_html__('Schedule entry deleted successfully', 'koi-schedule') . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__('Database error: ', 'koi-schedule') . esc_html($wpdb->last_error) . '</p></div>';
        }
    }
}

// Register the form handlers to the 'init' action hook.
add_action('init', 'schedule_entry_form_handler');
add_action('init', 'schedule_edit_entry_form_handler');
