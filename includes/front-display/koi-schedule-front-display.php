<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display the schedule for streamers.
 *
 * @return false|string
 */
function display_schedule(): false|string
{
    global $wpdb;
    $schedule_table  = $wpdb->prefix . 'koi_schedule';
    $streamers_table = $wpdb->prefix . 'koi_streamers';

    // Get parameters from the request.
    $week_offset = isset($_GET['week_offset']) ? intval($_GET['week_offset']) : 0;
    $selected_streamer_id = isset($_GET['streamer_filter_id']) ? intval($_GET['streamer_filter_id']) : 0;
    $selected_day = isset($_GET['day_filter']) ? sanitize_text_field($_GET['day_filter']) : '';
    $selected_hour = isset($_GET['hour_filter']) ? sanitize_text_field($_GET['hour_filter']) : '';

    // Week start and end dates based on the offset.
    $start_of_week = date('Y-m-d', strtotime("monday this week $week_offset week"));
    $end_of_week   = date('Y-m-d', strtotime("sunday this week $week_offset week"));

    // Get unique hours from the schedule table for the current week.
    $unique_hours = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT DATE_FORMAT(time, '%%H:%%i') as hour FROM {$schedule_table} WHERE DATE(time) BETWEEN %s AND %s ORDER BY hour ASC",
            $start_of_week,
            $end_of_week
        )
    );

    // Mapping days of the week to Polish names for the filter.
    $days_for_select = [
        ''          => 'Wszystkie dni',
        'Monday'    => 'Poniedziałek',
        'Tuesday'   => 'Wtorek',
        'Wednesday' => 'Środa',
        'Thursday'  => 'Czwartek',
        'Friday'    => 'Piątek',
        'Saturday'  => 'Sobota',
        'Sunday'    => 'Niedziela',
    ];

    // Building the main SQL query conditions and parameters.
    $main_sql_conditions = ["DATE(s.time) BETWEEN %s AND %s"];
    $main_sql_params = [$start_of_week, $end_of_week];

    if ($selected_streamer_id > 0) {
        $main_sql_conditions[] = "st.id = %d";
        $main_sql_params[] = $selected_streamer_id;
    }
    if ($selected_day && $selected_day !== '') {
        $main_sql_conditions[] = "DAYNAME(s.time) = %s";
        $main_sql_params[] = $selected_day;
    }
    if ($selected_hour && $selected_hour !== '') {
        $main_sql_conditions[] = "DATE_FORMAT(s.time, '%%H:%%i') = %s";
        $main_sql_params[] = $selected_hour;
    }
    $main_sql_order_by = "ORDER BY s.time ASC";

    $events_table = $wpdb->prefix . 'koi_events';
    $main_sql_select_from = "
	SELECT
		DATE_FORMAT(s.time, '%%H:%%i') AS time_formatted,
		DATE_FORMAT(s.time, '%%Y-%%m-%%d') AS date_formatted,
		st.name AS streamer_name,
		st.link AS streamer_link,
		st.avatar_url AS streamer_avatar_url,
		e.id AS event_id,
		e.name AS event_name,
		e.icon_url AS event_icon_url
		FROM {$schedule_table} s
		INNER JOIN {$streamers_table} st ON s.streamer_id = st.id
		LEFT JOIN {$events_table} e ON s.event_id = e.id
	";

    // Getting the results from the database.
    $query = $wpdb->prepare(
        $main_sql_select_from . " WHERE " . implode(" AND ", $main_sql_conditions) . " " . $main_sql_order_by,
        ...$main_sql_params
    );
    $results = $wpdb->get_results($query);
    if (!is_array($results)) {
        $results = [];
        error_log("Koi Schedule - Main Query results was not an array, set to empty. WPDB Error: " . $wpdb->last_error);
    }

    // Getting all streamers for the filter buttons.
    $all_streamers_for_buttons = $wpdb->get_results("SELECT id, name FROM {$streamers_table} ORDER BY name ASC");

    // Grouping the schedule results by day and hour.
    $grouped_schedule = [];
    foreach ($results as $row) {
        $datetime = new DateTime($row->date_formatted);
        $day_en   = $datetime->format('l');
        $day_pl   = $days_for_select[$day_en] ?? $day_en;
        $hour     = $row->time_formatted;
        $date_for_day_display = $datetime->format('d.m');

        if (!isset($grouped_schedule[$day_pl])) {
            $grouped_schedule[$day_pl] = ['display_date' => $date_for_day_display, 'hours' => []];
        }
        if (!isset($grouped_schedule[$day_pl]['hours'][$hour])) {
            $grouped_schedule[$day_pl]['hours'][$hour] = [];
        }
        $grouped_schedule[$day_pl]['hours'][$hour][] = [
            'name'        => esc_html($row->streamer_name),
            'link'        => esc_url($row->streamer_link),
            'avatar_url'  => esc_url($row->streamer_avatar_url),
            'event_id'    => $row->event_id,
            'event_name'  => esc_html($row->event_name),
            'event_icon_url'  => esc_url($row->event_icon_url),
        ];
    }

    ob_start();

    // Building the base URL for the filters.
    $base_page_url = esc_url(remove_query_arg(
        ['week_offset', 'streamer_filter_id', 'day_filter', 'hour_filter'],
        add_query_arg(null, null)
    ));

    // Toolbar with filters.
    echo '<div class="koi-schedule-filters-toolbar">';

    // Days filter buttons
    if (!empty($all_streamers_for_buttons)) {
        echo '<div class="koi-day-filters">';
        $day_btn_base_args = ['week_offset' => $week_offset, 'streamer_filter_id' => $selected_streamer_id];
        if ($selected_hour) {
            $day_btn_base_args['hour_filter'] = $selected_hour;
        }
        foreach ($days_for_select as $day_value => $day_name) {
            $args = $day_btn_base_args;
            if ($day_value !== '') {
                $args['day_filter'] = $day_value;
            } else {
                unset($args['day_filter']);
            }
            $is_active = ($selected_day === $day_value) ? ' active' : '';
            echo '<button type="button" class="koi-filter-button' . $is_active . '" onclick="window.location.href=\'' . esc_url(add_query_arg($args, $base_page_url)) . '\'">' . esc_html($day_name) . '</button>';
        }
        echo '</div>';
    }

    // Streamer filter buttons
    echo '<div class="koi-streamer-filters">';
    $streamer_btn_base_args = ['week_offset' => $week_offset];
    if ($selected_day) {
        $streamer_btn_base_args['day_filter'] = $selected_day;
    }
    if ($selected_hour) {
        $streamer_btn_base_args['hour_filter'] = $selected_hour;
    }

    echo '<button type="button" class="koi-filter-button' . ($selected_streamer_id === 0 ? ' active' : '') . '" onclick="window.location.href=\'' . esc_url(add_query_arg(array_merge($streamer_btn_base_args, ['streamer_filter_id' => 0]), $base_page_url)) . '\'">Wszyscy</button>';
    foreach ($all_streamers_for_buttons as $streamer_obj) {
        $is_active = ($selected_streamer_id == $streamer_obj->id) ? ' active' : '';
        echo '<button type="button" class="koi-filter-button' . $is_active . '" onclick="window.location.href=\'' . esc_url(add_query_arg(array_merge($streamer_btn_base_args, ['streamer_filter_id' => $streamer_obj->id]), $base_page_url)) . '\'">' . esc_html($streamer_obj->name) . '</button>';
    }
    echo '</div>';

    // Hour filter buttons
    echo '<div class="koi-hour-filters">';
    $hour_select_base_args = ['week_offset' => $week_offset, 'streamer_filter_id' => $selected_streamer_id];
    if ($selected_day) {
        $hour_select_base_args['day_filter'] = $selected_day;
    }
    echo '<button type="button" class="koi-filter-button' . ($selected_hour === '' ? ' active' : '') . '" onclick="window.location.href=\'' . esc_url(add_query_arg($hour_select_base_args, $base_page_url)) . '\'">Wszystkie godziny</button>';
    foreach ($unique_hours as $hour) {
        $args = $hour_select_base_args;
        $args['hour_filter'] = $hour;
        $is_active = ($selected_hour === $hour) ? ' active' : '';
        echo '<button type="button" class="koi-filter-button' . $is_active . '" onclick="window.location.href=\'' . esc_url(add_query_arg($args, $base_page_url)) . '\'">' . esc_html($hour) . '</button>';
    }
    echo '</div>';

    echo '</div>';

    $subathons_table = $wpdb->prefix . 'koi_subathons';
    $subathons = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT s.id, s.streamer_id, st.name AS streamer_name, s.start_date, s.timer_link, s.goals_link
         FROM {$subathons_table} s
         INNER JOIN {$streamers_table} st ON s.streamer_id = st.id
         WHERE DATE(s.start_date) <= %s
         ORDER BY s.start_date ASC",
            $start_of_week
        )
    );

    if (!empty($subathons)) {
        echo '<div class="koi-subathons-list">';
        echo '<h2>Subathony</h2>';
        foreach ($subathons as $subathon) {
            echo '<p>' . esc_html($subathon->streamer_name) . '</p>';
            echo '<p>' . esc_html(date('Y.m.d', strtotime($subathon->start_date))) . ' ' . esc_html(date('H:i', strtotime($subathon->start_date))) . '</p>';
            if (!empty($subathon->timer_link)) {
                echo '<iframe src="' . esc_url($subathon->timer_link) . '" width="400" height="50" frameborder="0" allowfullscreen></iframe>';
            }
            if (!empty($subathon->goals_link)) {
                echo '<iframe src="' . esc_url($subathon->goals_link) . '" width="600" height="100" frameborder="0" allowfullscreen></iframe>';
            }
        }
        echo '</div>';
    }

    // Schedule container
    echo '<div class="koi-schedule-container">';

    if (!empty($grouped_schedule)) {
        echo '<div class="koi-schedule-week">';
        $ordered_days_pl = array_values($days_for_select);
        array_shift($ordered_days_pl); // Usuwa "Wszystkie dni"

        // Calculate the start and end dates of the week.
        $week_dates = [];
        $start = new DateTime($start_of_week);
        foreach ($ordered_days_pl as $i => $day_pl) {
            $date = clone $start;
            $date->modify("+$i day");
            $week_dates[$day_pl] = $date->format('d.m');
        }

        echo '<div class="koi-schedule-week-columns">';

        // Column 1: Poniedzialek-Sroda
        echo '<div class="koi-schedule-week-column">';
        navigation(
            $week_offset,
            $selected_streamer_id,
            $selected_day,
            $selected_hour,
            $base_page_url,
            $start_of_week,
            $end_of_week
        );
        for ($i = 0; $i < 3; $i++) {
            column($ordered_days_pl[$i], $grouped_schedule, $week_dates);
        }
        echo '</div>';

        // Column 2: Czwartek-Niedziela
        echo '<div class="koi-schedule-week-column">';
        for ($i = 3; $i < 7; $i++) {
            column($ordered_days_pl[$i], $grouped_schedule, $week_dates);
        }
        echo '</div>';

        echo '</div>';
        echo '</div>';
    } else {
        // No streamers scheduled for the selected filters.
        echo '<div class="koi-schedule-no-streamers">';
        navigation(
            $week_offset,
            $selected_streamer_id,
            $selected_day,
            $selected_hour,
            $base_page_url,
            $start_of_week,
            $end_of_week
        );
        echo '<p>Brak zaplanowanych streamów dla wybranych filtrów w tym tygodniu.</p>';
        echo '</div>';
    }

    echo '</div>';

    return ob_get_clean();
}

/**
 * Displays the navigation for the schedule.
 */
function navigation(
    int $week_offset,
    $selected_streamer_id,
    $selected_day,
    $selected_hour,
    string $base_page_url,
    string $start_of_week,
    string $end_of_week
): void {
    echo '<div class="koi-schedule-date-navigation">';
    $nav_args_prev = [ 'week_offset' => $week_offset - 1 ];
    $nav_args_next = [ 'week_offset' => $week_offset + 1 ];
    if ($selected_streamer_id > 0) {
        $nav_args_prev['streamer_filter_id'] = $selected_streamer_id;
        $nav_args_next['streamer_filter_id'] = $selected_streamer_id;
    }
    if ($selected_day) {
        $nav_args_prev['day_filter'] = $selected_day;
        $nav_args_next['day_filter'] = $selected_day;
    }
    if ($selected_hour) {
        $nav_args_prev['hour_filter'] = $selected_hour;
        $nav_args_next['hour_filter'] = $selected_hour;
    }
    echo '<a class="koi-schedule-date-arrow" href="' . esc_url(add_query_arg($nav_args_prev, $base_page_url)) . '"><span class="dashicons dashicons-arrow-left-alt2"></span></a>';
    echo '<p class="koi-schedule-date-range">' . date('d.m.Y', strtotime($start_of_week)) . ' - ' . date('d.m.Y', strtotime($end_of_week)) . '</p>';
    echo '<a class="koi-schedule-date-arrow" href="' . esc_url(add_query_arg($nav_args_next, $base_page_url)) . '"><span class="dashicons dashicons-arrow-right-alt2"></span></a>';
    echo '</div>';
}

/**
 * Displays a single column of the schedule for a specific day.
 */
function column($day_name_pl, array $grouped_schedule, array $week_dates): void
{
    $day_data = $grouped_schedule[$day_name_pl] ?? null;
    $day_display_date = $week_dates[$day_name_pl] ?? '';
    $hours_of_day = $day_data['hours'] ?? [];

    echo '<div class="koi-schedule-day">';
    echo '<h3 class="koi-schedule-day-title">' . esc_html(mb_strtoupper($day_name_pl, 'UTF-8')) . ($day_display_date ? ' - ' . esc_html($day_display_date) : '') . '</h3>';

    if (!empty($hours_of_day)) {
        ksort($hours_of_day);
        foreach ($hours_of_day as $hour_key => $streamers_in_slot) {
            echo '<div class="koi-schedule-time-slot">';
            echo '<h4 class="koi-schedule-hour">' . esc_html($hour_key) . '</h4>';
            echo '<div class="koi-streamer-list">';
            $streamer_counter = 0;
            foreach ($streamers_in_slot as $streamer) {
                if ($streamer_counter % 2 === 0) {
                    if ($streamer_counter > 0) {
                        echo '</div>';
                    }
                    echo '<div class="koi-streamer-row">';
                }
                echo '<div class="koi-schedule-streamer">';
                echo '<a href="' . esc_url($streamer['link']) . '" target="_blank" title="' . esc_attr($streamer['name']) . '">';
                echo '<span class="koi-streamer-info">';
                echo '<span class="koi-streamer-event">';
                echo '<img class="koi-streamer-event-icon" src="' . esc_url($streamer['event_icon_url']) . '" alt="' . esc_attr($streamer['event_name'] ?? '') . '">';
                echo '</span>';
                echo '<span class="koi-streamer-avatar" style="background-image: url(\'' . esc_url($streamer['avatar_url']) . '\');"></span>';
                echo '</a>';
                echo '</div>';
                $streamer_counter++;
            }
            if ($streamer_counter > 0) {
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        }
    } else {
        // No streams for this day.
        echo '<div class="koi-schedule-no-streams-day">';
        echo '<img class="koi-schedule-no-streams-day-img" src="' . esc_url(plugins_url('img/no-streams.png', __FILE__)) . '" alt="Brak streamów" />';
        echo '<p class="koi-schedule-no-streams-day">Brak streamów</p>';
        echo '</div>';
    }
    echo '</div>';
}

// Register the shortcode to display the schedule.
add_shortcode('koi_schedule_display', 'display_schedule');
