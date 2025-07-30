<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the admin page for managing streamer calendars.
 *
 * @return void
 */
function koi_calendar_admin_page(): void
{
    global $wpdb;
    $calendar_table = $wpdb->prefix . 'koi_calendar';
    $streamers_table = $wpdb->prefix . 'koi_streamers';

    // Get all streamers to create tabs.
    $streamers = $wpdb->get_results("SELECT id, name FROM $streamers_table ORDER BY name ASC");
    $active_tab = isset($_GET['calendar_streamer_id']) ? intval($_GET['calendar_streamer_id']) : ($streamers[0]->id ?? 0);
    $show_all_tab = isset($_GET['calendar_show_all']);

    echo '<div class="wrap"><h1>Calendars</h1>';
    // Display tabs for each streamer and an "All entries" tab.
    echo '<h2 class="nav-tab-wrapper">';
    foreach ($streamers as $streamer) {
        $active = ($active_tab == $streamer->id && !$show_all_tab) ? ' nav-tab-active' : '';
        $url = add_query_arg(['page' => 'koi_calendar_admin', 'calendar_streamer_id' => $streamer->id], admin_url('admin.php'));
        echo '<a href="' . esc_url($url) . '" class="nav-tab' . $active . '">' . esc_html($streamer->name) . '</a>';
    }
    // Add "All entries" tab.
    $all_tab_active = $show_all_tab ? ' nav-tab-active' : '';
    $all_tab_url = add_query_arg(['page' => 'koi_calendar_admin', 'calendar_show_all' => 1], admin_url('admin.php'));
    echo '<a href="' . esc_url($all_tab_url) . '" class="nav-tab' . $all_tab_active . '">All entries</a>';
    echo '</h2>';

    // Display the content for the "All entries" tab using FullCalendar.
    if ($show_all_tab) {
        $entries = $wpdb->get_results("SELECT c.*, s.name as streamer_name FROM $calendar_table c LEFT JOIN $streamers_table s ON c.streamer_id = s.id WHERE c.available = 1 ORDER BY c.date ASC, c.time_from ASC");
        $events = [];
        foreach ($entries as $entry) {
            $desc = mb_substr($entry->request, 0, 255);
            if (mb_strlen($entry->request) > 255) {
                $desc .= '...';
            }
            $desc = wordwrap($desc, 50, "\n", true);
            $from = substr($entry->time_from, 0, 5);
            $to = substr($entry->time_to, 0, 5);
            $dot = !empty(trim($entry->request)) ? ' <span class="koi-calendar-dot">&bull;</span>' : '';
            $events[] = [
                'title' => $entry->streamer_name . " {$from} - {$to}" . ($dot ? ' •' : ''),
                'start' => $entry->date . 'T' . $from,
                'end' => $entry->date . 'T' . $to,
                'description' => $desc,
            ];
        }
        echo '<div class="koi-calendar" id="koi-calendar"></div>';
        echo '<script>
        window.koiCalendarEvents = ' . json_encode($events) . ';
    </script>';
        echo '<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.18/main.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.18/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var calendarEl = document.getElementById("koi-calendar");
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: "dayGridMonth",
            locale: "pl",
            height: 500,
            events: window.koiCalendarEvents,
            displayEventTime: false,
            headerToolbar: {
				left: "prev,next today",
				center: "title",
				right: ""
			},
            eventDidMount: function(info) {
    			new bootstrap.Tooltip(info.el, {
    			    title: info.event.extendedProps.description,
    			    placement: "top",
    			    trigger: "hover",
    			    container: "body"
    			});
			}
        });
        calendar.render();
    });
    </script>';
        echo '</div>';
        return;
    }

    // Display the content for a specific streamer's tab.
    if ($active_tab) {
        // Handle edit or delete actions on a calendar entry.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calendar_action'])) {
            if (!current_user_can('manage_options')) {
                wp_die('Insufficient permissions.');
            }
            if ($_POST['calendar_action'] === 'delete' && isset($_POST['entry_id'])) {
                $wpdb->delete($calendar_table, ['id' => intval($_POST['entry_id'])]);
                echo '<div class="updated"><p>Wpis usunięty.</p></div>';
            }
            if ($_POST['calendar_action'] === 'edit' && isset($_POST['entry_id'])) {
                $id = intval($_POST['entry_id']);
                $date = sanitize_text_field($_POST['date']);
                $from = sanitize_text_field($_POST['time_from']);
                $to = sanitize_text_field($_POST['time_to']);
                $available = intval($_POST['available']);
                $request = sanitize_text_field($_POST['request']);
                $wpdb->update($calendar_table, [
                    'date' => $date,
                    'time_from' => $from,
                    'time_to' => $to,
                    'available' => $available,
                    'request' => $request
                ], ['id' => $id]);
                echo '<div class="updated"><p>Entry updated.</p></div>';
            }
        }

        // Get all calendar entries for the selected streamer.
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $calendar_table WHERE streamer_id = %d ORDER BY date ASC, time_from ASC",
            $active_tab
        ));

        // Display the entries in a table with edit and delete options.
        echo '<table class="widefat striped"><thead>
            <tr>
                <th>Date</th>
                <th>From</th>
                <th>To</th>
                <th>Availability</th>
                <th>Note</th>
                <th>Action</th>
            </tr>
        </thead><tbody>';
        foreach ($entries as $entry) {
            echo '<tr>
                <form method="post">
                <td><input type="date" name="date" value="' . esc_attr($entry->date) . '"></td>
                <td><input type="time" name="time_from" value="' . esc_attr(substr($entry->time_from, 0, 5)) . '"></td>
				<td><input type="time" name="time_to" value="' . esc_attr(substr($entry->time_to, 0, 5)) . '"></td>
                <td>
                    <select name="available">
                        <option value="1"' . selected($entry->available, 1, false) . '>Yes</option>
                        <option value="0"' . selected($entry->available, 0, false) . '>No</option>
                    </select>
                </td>
                <td><textarea class="koi-textarea" name="request">' . esc_textarea($entry->request) . '</textarea></td>
                <td>
                    <input type="hidden" name="entry_id" value="' . esc_attr($entry->id) . '">
                    <button type="submit" name="calendar_action" value="edit" class="button button-primary">Zapisz</button>
                    <button type="submit" name="calendar_action" value="delete" class="button button-secondary" onclick="return confirm(\'Na pewno usunąć?\')">Usuń</button>
                </td>
                </form>
            </tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}

// Add the admin page to the WordPress admin menu.
add_action('admin_menu', function () {
    add_menu_page(
        'Koi Calendars',
        'Koi Calendars',
        'manage_options',
        'koi_calendar_admin',
        'koi_calendar_admin_page',
        'dashicons-calendar-alt',
        3
    );
});
