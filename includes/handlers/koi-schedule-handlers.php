<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Processes form submissions for adding, editing, and deleting schedule entries.
 *
 * @return void
 */
function koi_schedule_form_handler(): void
{
    if (
        !isset($_POST['schedule_action']) &&
        !isset($_POST['edit_schedule']) &&
        !isset($_POST['delete_schedule'])
    ) {
        return;
    }

    if (
        !isset($_POST['koi_schedule_nonce_field']) ||
        !wp_verify_nonce($_POST['koi_schedule_nonce_field'], 'koi_schedule_nonce_action')
    ) {
        return;
    }
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'koi-schedule'));
    }

    $action = $_POST['schedule_action'] ?? '';

    if ($action === 'add_schedule') {
        _koi_schedule_handle_add_schedule();
    } elseif ($action === 'bulk_edit') {
        _koi_schedule_handle_bulk_edit();
    } elseif ($action === 'delete_older') {
        _koi_schedule_handle_bulk_delete();
    } elseif (isset($_POST['edit_schedule'])) {
        _koi_schedule_handle_single_edit();
    } elseif (isset($_POST['delete_schedule'])) {
        _koi_schedule_handle_single_delete();
    }
}

/**
 * Handles adding new schedule entries.
 *
 * @return void
 */
function _koi_schedule_handle_add_schedule(): void
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'koi_schedule';
    $streamer_id = isset($_POST['streamer_id']) ? intval($_POST['streamer_id']) : 0;
    $schedule_entries = isset($_POST['schedule_entries']) ? (array) $_POST['schedule_entries'] : [];

    if (empty($streamer_id) || empty($schedule_entries)) {
        echo '<div class="error"><p>' . esc_html__('Missing required fields.', 'koi-schedule') . '</p></div>';
        return;
    }

    $insert_values = [];
    $placeholders = [];
    $error_count = 0;

    foreach ($schedule_entries as $entry) {
        $date = sanitize_text_field($entry['date']);
        if ($entry['time_radio'] === 'other') {
            $hour = intval($entry['hour']);
            $minute = intval($entry['minute']);
            $time = sprintf('%02d:%02d', $hour, $minute);
        } else {
            $time = sanitize_text_field($entry['time_radio']);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            $error_count++;
            continue;
        }

        $event_id = isset($entry['event_id']) ? intval($entry['event_id']) : null;

        array_push($insert_values, $date . ' ' . $time, $streamer_id, $event_id);
        $placeholders[] = '(%s, %d, %d)';
    }

    $success_count = 0;
    if (!empty($insert_values)) {
        $query = "INSERT INTO $table_name (time, streamer_id, event_id) VALUES " . implode(', ', $placeholders);
        $success_count = $wpdb->query($wpdb->prepare($query, $insert_values));
    }

    if ($success_count > 0) {
        echo '<div class="updated"><p>' . sprintf(esc_html__('%d entries added successfully.', 'koi-schedule'), $success_count) . '</p></div>';
    }
    if ($error_count > 0) {
        echo '<div class="error"><p>' . sprintf(esc_html__('Failed to add %d entries.', 'koi-schedule'), $error_count) . '</p></div>';
    }
}

/**
 * Handles editing a single schedule entry.
 *
 * @return void
 */
function _koi_schedule_handle_single_edit(): void
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'koi_schedule';
    $entry_id = key($_POST['edit_schedule']);
    $entry_data = $_POST['entries'][$entry_id];

    $time = sprintf('%02d:%02d', intval($entry_data['hour']), intval($entry_data['minute']));
    $datetime = sanitize_text_field($entry_data['date']) . ' ' . $time;

    $result = $wpdb->update(
        $table_name,
        [
            'time' => $datetime,
            'streamer_id' => intval($entry_data['streamer_id']),
            'event_id' => isset($entry_data['event_id']) ? intval($entry_data['event_id']) : null
        ],
        ['id' => $entry_id],
        ['%s', '%d', '%d'],
        ['%d']
    );

    if ($result !== false) {
        echo '<div class="updated"><p>' . esc_html__('Schedule entry updated successfully.', 'koi-schedule') . '</p></div>';
    } else {
        echo '<div class="error"><p>' . esc_html__('Failed to update entry.', 'koi-schedule') . ' ' . esc_html($wpdb->last_error) . '</p></div>';
    }
}

/**
 * Handles deleting a single schedule entry.
 *
 * @return void
 */
function _koi_schedule_handle_single_delete(): void
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'koi_schedule';
    $entry_id = key($_POST['delete_schedule']);

    $result = $wpdb->delete($table_name, ['id' => $entry_id], ['%d']);

    if ($result) {
        echo '<div class="updated"><p>' . esc_html__('Schedule entry deleted successfully.', 'koi-schedule') . '</p></div>';
    } else {
        echo '<div class="error"><p>' . esc_html__('Failed to delete entry.', 'koi-schedule') . ' ' . esc_html($wpdb->last_error) . '</p></div>';
    }
}

/**
 * Handles bulk editing schedule entries.
 *
 * @return void
 */
function _koi_schedule_handle_bulk_edit(): void
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'koi_schedule';
    $ids = isset($_POST['bulk_ids']) ? array_map('intval', $_POST['bulk_ids']) : [];

    if (empty($ids)) {
        echo '<div class="error"><p>' . esc_html__('No entries selected for bulk edit.', 'koi-schedule') . '</p></div>';
        return;
    }

    $ids_placeholder = implode(', ', $ids);
    $update_fields = [];
    $update_formats = [];

    if (!empty($_POST['bulk_streamer_id'])) {
        $update_fields['streamer_id'] = intval($_POST['bulk_streamer_id']);
    }
    if (!empty($_POST['bulk_event_id'])) {
        $update_fields['event_id'] = intval($_POST['bulk_event_id']);
    }

    $bulk_date = !empty($_POST['bulk_date']) ? sanitize_text_field($_POST['bulk_date']) : '';
    $bulk_hour = isset($_POST['bulk_hour']) && $_POST['bulk_hour'] !== '' ? intval($_POST['bulk_hour']) : null;
    $bulk_minute = isset($_POST['bulk_minute']) && $_POST['bulk_minute'] !== '' ? intval($_POST['bulk_minute']) : null;
    $is_time_update = ($bulk_date || $bulk_hour !== null || $bulk_minute !== null);

    if (empty($update_fields) && !$is_time_update) {
        echo '<div class="error"><p>' . esc_html__('No changes were selected for the bulk edit.', 'koi-schedule') . '</p></div>';
        return;
    }

    $set_clauses = [];
    $query_params = [];

    foreach ($update_fields as $field => $value) {
        $set_clauses[] = "$field = %d";
        $query_params[] = $value;
    }

    if ($is_time_update) {
        $ids_placeholder_for_select = implode(', ', array_fill(0, count($ids), '%d'));
        $entries_to_update = $wpdb->get_results($wpdb->prepare("SELECT id, time FROM $table_name WHERE id IN ($ids_placeholder_for_select)", $ids));

        $time_case_sql = "time = CASE id ";
        foreach ($entries_to_update as $entry) {
            $dt = new DateTime($entry->time);
            if ($bulk_date) {
                $dt->setDate((int)substr($bulk_date, 0, 4), (int)substr($bulk_date, 5, 2), (int)substr($bulk_date, 8, 2));
            }
            if ($bulk_hour !== null && $bulk_minute !== null) {
                $dt->setTime($bulk_hour, $bulk_minute);
            }
            $time_case_sql .= $wpdb->prepare("WHEN %d THEN %s ", $entry->id, $dt->format('Y-m-d H:i:s'));
        }
        $time_case_sql .= "END";
        $set_clauses[] = $time_case_sql;
    }

    $sql = "UPDATE $table_name SET " . implode(', ', $set_clauses) . " WHERE id IN ($ids_placeholder)";
    $result = $wpdb->query($wpdb->prepare($sql, $query_params));

    if ($result !== false) {
        echo '<div class="updated"><p>' . sprintf(esc_html__('Bulk edit complete. %d entries updated.', 'koi-schedule'), $result) . '</p></div>';
    } else {
        echo '<div class="error"><p>' . esc_html__('An error occurred during the bulk edit.', 'koi-schedule') . ' ' . esc_html($wpdb->last_error) . '</p></div>';
    }
}


/**
 * Handles bulk deleting entries older or newer than a specific date.
 *
 * @return void
 */
function _koi_schedule_handle_bulk_delete(): void
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'koi_schedule';
    $delete_date = sanitize_text_field($_POST['delete_older_date']);
    $direction = $_POST['delete_direction'] ?? 'older';

    if (empty($delete_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $delete_date)) {
        echo '<div class="error"><p>' . esc_html__('Please select a valid date for bulk deletion.', 'koi-schedule') . '</p></div>';
        return;
    }

    $operator = ($direction === 'newer') ? '>' : '<';
    $datetime = $delete_date . ' 00:00:00';
    $result = $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE time $operator %s", $datetime));

    if ($result !== false) {
        $message = ($direction === 'newer')
            ? sprintf(esc_html__('Successfully deleted %d entries newer than %s.', 'koi-schedule'), $result, $delete_date)
            : sprintf(esc_html__('Successfully deleted %d entries older than %s.', 'koi-schedule'), $result, $delete_date);
        echo '<div class="updated"><p>' . $message . '</p></div>';
    } else {
        echo '<div class="error"><p>' . esc_html__('Failed to delete entries.', 'koi-schedule') . ' ' . esc_html($wpdb->last_error) . '</p></div>';
    }
}

/**
 * Handles the AJAX request to delete a schedule event.
 *
 * @return void
 */
function koi_schedule_delete_ajax_handler(): void
{
    check_ajax_referer('koi_delete_schedule_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'koi-schedule')]);
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        wp_send_json_error(['message' => __('Invalid entry ID provided.', 'koi-schedule')]);
    }

    global $wpdb;
    $schedule_table = $wpdb->prefix . 'koi_schedule';

    $deleted = $wpdb->delete($schedule_table, ['id' => $id], ['%d']);

    if ($deleted === false) {
        wp_send_json_error(['message' => __('Failed to delete the entry from the database.', 'koi-schedule')]);
    } else {
        wp_send_json_success(['message' => __('Entry deleted successfully.', 'koi-schedule')]);
    }
}
// Register the AJAX handler for deleting schedule events.
add_action('wp_ajax_koi_delete_schedule_event', 'koi_schedule_delete_ajax_handler');

// Register the form handler to the 'admin_init' action hook.
add_action('admin_init', 'koi_schedule_form_handler');
