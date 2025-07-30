<?php

if (!defined('ABSPATH')) {
    exit;
}

function koi_calendar_form_handler()
{
    if (
        $_SERVER['REQUEST_METHOD'] !== 'POST' ||
        !isset($_POST['koi_calendar_nonce']) ||
        !wp_verify_nonce($_POST['koi_calendar_nonce'], 'koi_calendar_save')
    ) {
        return;
    }

    if (!is_user_logged_in()) {
        wp_die('Brak uprawnień.');
    }

    $user = wp_get_current_user();
    $allowed_roles = ['editor', 'administrator'];
    if (!array_intersect($allowed_roles, $user->roles)) {
        wp_die('Brak uprawnień.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'koi_calendar';
    $user_id = get_current_user_id();
    $relation_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}koi_streamers WHERE user_id = %d",
            $user_id
        )
    );

    $month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
    $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    $first_day_of_month = sprintf('%04d-%02d-01', $year, $month);
    $last_day_of_month = sprintf('%04d-%02d-%d', $year, $month, $days_in_month);

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

    for ($d = 1; $d <= $days_in_month; $d++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $request_text = isset($requests[$date]) ? sanitize_text_field($requests[$date]) : null;
        $entry_data = null;

        if (isset($unavailable[$date])) {
            $entry_data = [
                'streamer_id' => $relation_id,
                'date' => $date,
                'time_from' => '00:00:00',
                'time_to' => '23:59:59',
                'available' => 0,
                'request' => $request_text
            ];
        } elseif (!empty($availability[$date]['from']) && !empty($availability[$date]['to'])) {
            $from_hour = intval($availability[$date]['from']);
            $from_min = isset($availability[$date]['from_min']) ? intval($availability[$date]['from_min']) : 0;
            $to_hour = intval($availability[$date]['to']);
            $to_min = isset($availability[$date]['to_min']) ? intval($availability[$date]['to_min']) : 0;

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

        if ($entry_data) {
            array_push($insert_values, $entry_data['streamer_id'], $entry_data['date'], $entry_data['time_from'], $entry_data['time_to'], $entry_data['available'], $entry_data['request']);
            $placeholders[] = '(%d, %s, %s, %s, %d, %s)';
        }
    }

    if (!empty($insert_values)) {
        $query = "INSERT INTO $table_name (streamer_id, date, time_from, time_to, available, request) VALUES " . implode(', ', $placeholders);
        $wpdb->query($wpdb->prepare($query, $insert_values));
    }

    $redirect_url = add_query_arg([
        'calendar_saved' => 1,
        'month' => $month,
        'year' => $year
    ], wp_get_referer() ?: home_url());
    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_post_koi_calendar_save', 'koi_calendar_form_handler');
add_action('admin_post_nopriv_koi_calendar_save', 'koi_calendar_form_handler');
