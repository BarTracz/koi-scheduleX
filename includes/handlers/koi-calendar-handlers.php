<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the submission of the streamer's availability calendar form.
 *
 * @return void
 */
function koi_calendar_form_handler(): void
{
    // Check if the request is a POST request and the nonce is valid.
    if (
        $_SERVER['REQUEST_METHOD'] !== 'POST' ||
        !isset($_POST['koi_calendar_nonce']) ||
        !wp_verify_nonce($_POST['koi_calendar_nonce'], 'koi_calendar_save')
    ) {
        return;
    }

    // Ensure the user is logged in.
    if (!is_user_logged_in()) {
        wp_die('No permissions.');
    }

    // Check if the user has the appropriate role.
    $user = wp_get_current_user();
    $allowed_roles = ['contributor', 'administrator'];
    if (!array_intersect($allowed_roles, $user->roles)) {
        wp_die('No permissions.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'koi_calendar';
    $user_id = get_current_user_id();
    // Get the streamer relation ID based on the WordPress user ID.
    $relation_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}koi_streamers WHERE user_id = %d",
            $user_id
        )
    );

    // Get the month and year from the submitted form.
    $month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
    $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    // Delete existing entries for the streamer for the given month and year.
    $first_day_of_month = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
    $last_day_of_month = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $table_name WHERE streamer_id = %d AND date BETWEEN %s AND %s",
            $relation_id,
            $first_day_of_month,
            $last_day_of_month
        )
    );

    $insert_values = [];
    $placeholders = [];
    $availability = $_POST['availability'] ?? [];
    $unavailable = $_POST['unavailable'] ?? [];
    $requests = $_POST['request'] ?? [];

    // Loop through each day of the month to process form data.
    for ($d = 1; $d <= $days_in_month; $d++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $request_text = isset($requests[$date]) ? sanitize_text_field($requests[$date]) : null;
        $entry_data = null;

        // If the day is marked as unavailable.
        if (isset($unavailable[$date])) {
            $entry_data = [
                'streamer_id' => $relation_id,
                'date' => $date,
                'time_from' => '00:00:00',
                'time_to' => '23:59:59',
                'available' => 0,
                'request' => $request_text
            ];
            // If availability times are provided.
        } elseif (!empty($availability[$date]['from']) && !empty($availability[$date]['to'])) {
            $from_hour = intval($availability[$date]['from']);
            $from_min = isset($availability[$date]['from_min']) ? intval($availability[$date]['from_min']) : 0;
            $to_hour = intval($availability[$date]['to']);
            $to_min = isset($availability[$date]['to_min']) ? intval($availability[$date]['to_min']) : 0;

            if ($to_hour === 24) {
                $to_hour = 23;
                $to_min = 59;
            }

            // Skip if the start time is later than or equal to the end time.
            if ($from_hour > $to_hour || ($from_hour === $to_hour && $from_min >= $to_min)) {
                continue;
            }

            $entry_data = [
                'streamer_id' => $relation_id,
                'date' => $date,
                'time_from' => sprintf('%02d:%02d:00', $from_hour, $from_min),
                'time_to' => sprintf('%02d:%02d:59', $to_hour, $to_min),
                'available' => 1,
                'request' => $request_text
            ];
        }

        // If there's data to save for the day, add it to the insert array.
        if ($entry_data) {
            array_push($insert_values, $entry_data['streamer_id'], $entry_data['date'], $entry_data['time_from'], $entry_data['time_to'], $entry_data['available'], $entry_data['request']);
            $placeholders[] = '(%d, %s, %s, %s, %d, %s)';
        }
    }

    // If there are values to insert, execute the database query.
    if (!empty($insert_values)) {
        $query = "INSERT INTO $table_name (streamer_id, date, time_from, time_to, available, request) VALUES " . implode(', ', $placeholders);
        $wpdb->query($wpdb->prepare($query, $insert_values));
    }

    // Redirect the user back to the calendar page with a success message.
    $redirect_url = add_query_arg([
        'calendar_saved' => 1,
        'month' => $month,
        'year' => $year
    ], wp_get_referer() ?: home_url());
    wp_safe_redirect($redirect_url);
    exit;
}
// Hook the form handler to the `admin_post` action.
add_action('admin_post_koi_calendar_save', 'koi_calendar_form_handler');
