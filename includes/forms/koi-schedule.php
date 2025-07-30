<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Displays the schedule calendars and handles AJAX deletion.
 *
 * @return void
 */
function koi_schedule_calendars()
{
    global $wpdb;
    $calendar_table = $wpdb->prefix . 'koi_calendar';
    $schedule_table = $wpdb->prefix . 'koi_schedule';
    $streamers_table = $wpdb->prefix . 'koi_streamers';

    $calendar_entries = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*, s.name as streamer_name FROM %i c LEFT JOIN %i s ON c.streamer_id = s.id WHERE c.available = 1 ORDER BY c.date ASC, c.time_from ASC",
        $calendar_table,
        $streamers_table
    ));
    $schedule_entries = $wpdb->get_results($wpdb->prepare(
        "SELECT sch.id, sch.time, sch.streamer_id, s.name as streamer_name FROM %i sch LEFT JOIN %i s ON sch.streamer_id = s.id ORDER BY sch.time ASC",
        $schedule_table,
        $streamers_table
    ));

    $calendar_events = [];
    foreach ($calendar_entries as $entry) {
        $desc = mb_substr($entry->request, 0, 255);
        if (mb_strlen($entry->request) > 255) {
            $desc .= '...';
        }
        $desc = wordwrap($desc, 50, "\n", true);
        $from = substr($entry->time_from, 0, 5);
        $to = substr($entry->time_to, 0, 5);
        $dot = !empty(trim($entry->request)) ? ' <span class="koi-calendar-dot">&bull;</span>' : '';
        $calendar_events[] = [
            'title' => $entry->streamer_name . " {$from} - {$to}" . ($dot ? ' •' : ''),
            'start' => $entry->date . 'T' . $from,
            'end' => $entry->date . 'T' . $to,
            'description' => $desc,
        ];
    }

    $schedule_events = [];
    foreach ($schedule_entries as $entry) {
        $date = date('Y-m-d', strtotime($entry->time));
        $from = date('H:i', strtotime($entry->time));
        $schedule_events[] = [
            'id'    => $entry->id,
            'title' => $entry->streamer_name . " " . $from,
            'start' => $date . 'T' . $from,
            'description' => '',
        ];
    }

    echo '<div class="koi-schedule-flex-wrap">
    <div class="koi-schedule-calendars-row">
        <div style="flex: 1;">
            <div class="koi-calendar" id="koi-calendar-availability"></div>
        </div>
        <div style="flex: 1;">
            <div class="koi-calendar" id="koi-calendar-schedule"></div>
        </div>
    </div>
    <div class="koi-schedule-form-side" id="koi-schedule-form-side">';

    $ajax_nonce = wp_create_nonce('koi_delete_schedule_nonce');
    ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const calendarEvents = <?php echo json_encode($calendar_events); ?>;
            const scheduleEvents = <?php echo json_encode($schedule_events); ?>;

            var calendarEl1 = document.getElementById("koi-calendar-availability");
            var calendar1 = new FullCalendar.Calendar(calendarEl1, {
                initialView: "dayGridMonth",
                locale: "en",
                height: "auto",
                events: calendarEvents,
                displayEventTime: false,
                headerToolbar: {
                    left: "prev,next today",
                    center: "title",
                    right: ""
                },
                eventDidMount: function(info) {
                    if (info.event.extendedProps.description) {
                        new bootstrap.Tooltip(info.el, {
                            title: info.event.extendedProps.description,
                            placement: "top",
                            trigger: "hover",
                            container: "body"
                        });
                    }
                }
            });
            calendar1.render();

            var calendarEl2 = document.getElementById("koi-calendar-schedule");
            var calendar2 = new FullCalendar.Calendar(calendarEl2, {
                initialView: "dayGridMonth",
                locale: "en",
                height: "auto",
                events: scheduleEvents,
                displayEventTime: false,
                headerToolbar: {
                    left: "prev,next today",
                    center: "title",
                    right: ""
                },
                eventClick: function(info) {
                    if (!confirm("Are you sure you want to delete this schedule entry?")) {
                        return;
                    }

                    var formData = new FormData();
                    formData.append("action", "koi_delete_schedule_event");
                    formData.append("id", info.event.id);
                    formData.append("_ajax_nonce", "<?php echo $ajax_nonce; ?>");

                    fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                        method: "POST",
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                info.event.remove();
                                alert("Entry deleted successfully.");
                            } else {
                                alert("Error: " + data.data.message);
                            }
                        })
                        .catch(error => {
                            console.error("Error:", error);
                            alert("An unexpected error occurred while deleting the entry.");
                        });
                }
            });
            calendar2.render();
        });
    </script>
	<?php
}

/**
 * Displays the form for adding schedule entries.
 *
 * @return false|string
 */
function schedule_entry_form(): false|string
{
    global $wpdb;
    $streamers_table = $wpdb->prefix . 'koi_streamers';
    $events_table = $wpdb->prefix . 'koi_events';
    $streamers = $wpdb->get_results($wpdb->prepare("SELECT id, name, user_id FROM %i", $streamers_table));
    $events = $wpdb->get_results($wpdb->prepare("SELECT id, name FROM %i", $events_table));

    ob_start();
    koi_schedule_calendars();
    ?>
    <form method="post" action="" id="koi-schedule-form">
        <table>
            <tr>
                <th>Streamer</th>
            </tr>
            <tr>
                <td>
                    <select id="streamer_id" name="streamer_id" required>
                        <option value="">Select a streamer</option>
						<?php foreach ($streamers as $streamer) : ?>
                            <option value="<?php echo esc_attr($streamer->id); ?>">
								<?php echo esc_html($streamer->name); ?>
                            </option>
						<?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <div id="schedule-entries">
            <table class="schedule-entry">
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Event</th>
                    <th>Action</th>
                </tr>
                <tr>
                    <td><input type="date" name="schedule_entries[0][date]" required></td>
                    <td>
                        <div>
                            <label><input type="radio" name="schedule_entries[0][time_radio]" value="12:00" checked>12</label>
                            <label><input type="radio" name="schedule_entries[0][time_radio]" value="16:00">16</label>
                            <label><input type="radio" name="schedule_entries[0][time_radio]" value="20:00">20</label>
                            <label>
                                <input type="radio" name="schedule_entries[0][time_radio]" value="other"> Other:
                                <input type="number" class="koi-number-field time-input-hour" name="schedule_entries[0][hour]" min="0" max="23" disabled placeholder="hh">
                                :
                                <input type="number" class="koi-number-field time-input-minute" name="schedule_entries[0][minute]" min="0" max="59" disabled placeholder="mm">
                            </label>
                        </div>
                    </td>
                    <td>
                        <select name="schedule_entries[0][event_id]">
							<?php foreach ($events as $event) : ?>
                                <option value="<?php echo esc_attr($event->id); ?>">
									<?php echo esc_html($event->name); ?>
                                </option>
							<?php endforeach; ?>
                        </select>
                    </td>
                    <td><button type="button" class="remove-entry button button-secondary">Remove</button></td>
                </tr>
            </table>
        </div>
        <p>
            <button type="button" id="add-entry" class="button button-secondary">Add another entry</button>
        </p>
        <p>
            <input type="submit" name="submit" value="Submit" class="button button-primary">
        </p>
        <input type="hidden" name="schedule_action" value="add_schedule">
		<?php wp_nonce_field('koi_schedule_nonce_action', 'koi_schedule_nonce_field'); ?>
    </form>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const eventsData = <?php echo json_encode($events); ?>;
            const container = document.getElementById('schedule-entries');

            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-entry')) {
                    const entry = e.target.closest('table.schedule-entry');
                    if (container.querySelectorAll('table.schedule-entry').length > 1) {
                        container.removeChild(entry);
                    } else {
                        alert("You must have at least one entry.");
                    }
                }
            });

            container.addEventListener('change', function(e) {
                if (e.target.type === 'radio' && e.target.name.includes('[time_radio]')) {
                    const entryRow = e.target.closest('tr');
                    const hourInput = entryRow.querySelector('.time-input-hour');
                    const minuteInput = entryRow.querySelector('.time-input-minute');
                    const isOther = e.target.value === 'other';

                    hourInput.disabled = !isOther;
                    hourInput.required = isOther;
                    minuteInput.disabled = !isOther;
                    minuteInput.required = isOther;

                    if (!isOther) {
                        hourInput.value = '';
                        minuteInput.value = '';
                    }
                }
            });

            document.getElementById('add-entry').addEventListener('click', function() {
                const index = container.querySelectorAll('table.schedule-entry').length;
                const entry = document.createElement('table');
                entry.classList.add('schedule-entry');

                let eventOptions = '';
                eventsData.forEach(event => {
                    eventOptions += `<option value="${event.id}">${event.name}</option>`;
                });

                entry.innerHTML = `
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Event</th>
                    <th>Action</th>
                </tr>
                <tr>
                    <td><input type="date" name="schedule_entries[${index}][date]" required></td>
                    <td>
                        <div>
                            <label><input type="radio" name="schedule_entries[${index}][time_radio]" value="12:00" checked>12</label>
                            <label><input type="radio" name="schedule_entries[${index}][time_radio]" value="16:00">16</label>
                            <label><input type="radio" name="schedule_entries[${index}][time_radio]" value="20:00">20</label>
                            <label>
                                <input type="radio" name="schedule_entries[${index}][time_radio]" value="other"> Other:
                                <input type="number" class="koi-number-field time-input-hour" name="schedule_entries[${index}][hour]" min="0" max="23" disabled placeholder="hh">
                                :
                                <input type="number" class="koi-number-field time-input-minute" name="schedule_entries[${index}][minute]" min="0" max="59" disabled placeholder="mm">
                            </label>
                        </div>
                    </td>
                    <td>
                        <select name="schedule_entries[${index}][event_id]">${eventOptions}</select>
                    </td>
                    <td><button type="button" class="remove-entry button button-secondary">Remove</button></td>
                </tr>
                `;
                container.appendChild(entry);
            });
        });
    </script>
	<?php
    echo '</div></div>';
    return ob_get_clean();
}

/**
 * Displays the form for editing and deleting schedule entries.
 *
 * @return void
 */
function schedule_edit_entry_form(): void
{
    global $wpdb;
    $schedule_table = $wpdb->prefix . 'koi_schedule';
    $streamers_table = $wpdb->prefix . 'koi_streamers';
    $events_table = $wpdb->prefix . 'koi_events';

    $events = $wpdb->get_results($wpdb->prepare("SELECT id, name FROM %i", $events_table));
    $streamers = $wpdb->get_results($wpdb->prepare("SELECT id, name FROM %i", $streamers_table));

    $items_per_page = 30;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    $allowed_sort_columns = ['time', 'streamer_name'];
    $sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], $allowed_sort_columns) ? $_GET['sort_by'] : 'time';
    $order = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'ASC';

    $filter_streamer = isset($_GET['filter_streamer']) ? intval($_GET['filter_streamer']) : 0;
    $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
    $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';

    $where = [];
    $params = [];
    if ($filter_streamer) {
        $where[] = 's.streamer_id = %d';
        $params[] = $filter_streamer;
    }
    if ($filter_date_from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_from)) {
        $where[] = 's.time >= %s';
        $params[] = $filter_date_from . ' 00:00:00';
    }
    if ($filter_date_to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_to)) {
        $where[] = 's.time <= %s';
        $params[] = $filter_date_to . ' 23:59:59';
    }
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $count_query = "SELECT COUNT(*) FROM $schedule_table s INNER JOIN $streamers_table st ON s.streamer_id = st.id $where_sql";
    $total_items = $wpdb->get_var($wpdb->prepare($count_query, ...$params));

    $query = $wpdb->prepare(
        "SELECT s.id, s.time, s.streamer_id, s.event_id, st.name AS streamer_name
        FROM $schedule_table s
        INNER JOIN $streamers_table st ON s.streamer_id = st.id
        $where_sql
        ORDER BY $sort_by $order
        LIMIT %d OFFSET %d",
        ...array_merge($params, [$items_per_page, $offset])
    );
    $entries = $wpdb->get_results($query);

    echo '<div class="wrap">';
    echo '<h1>Edit schedule entries</h1>';

    echo '<form method="get" action="">';
    echo '<input type="hidden" name="page" value="koi-schedule-edit">';
    echo '<div class="koi-admin-filters">';
    echo '<label for="filter_streamer">Streamer: </label>';
    echo '<select name="filter_streamer" id="filter_streamer">';
    echo '<option value="0">-- All --</option>';
    foreach ($streamers as $streamer) {
        $selected = ($filter_streamer == $streamer->id) ? 'selected' : '';
        echo '<option value="' . esc_attr($streamer->id) . '" ' . $selected . '>' . esc_html($streamer->name) . '</option>';
    }
    echo '</select> ';
    echo '<label for="filter_date_from">From: </label>';
    echo '<input type="date" name="filter_date_from" id="filter_date_from" value="' . esc_attr($filter_date_from) . '"> ';
    echo '<label for="filter_date_to">To: </label>';
    echo '<input type="date" name="filter_date_to" id="filter_date_to" value="' . esc_attr($filter_date_to) . '"> ';
    echo '<input type="submit" class="button" value="Filter">';
    echo '</div>';
    echo '</form>';

    echo '<p>Sort by: ';
    echo '<a href="' . esc_url(add_query_arg(['sort_by' => 'streamer_name', 'order' => $order === 'ASC' ? 'desc' : 'asc'])) . '">Name</a> | ';
    echo '<a href="' . esc_url(add_query_arg(['sort_by' => 'time', 'order' => $order === 'ASC' ? 'desc' : 'asc'])) . '">Date</a>';
    echo '</p>';

    if ($entries) {
        echo '<form method="post" action="">';
        wp_nonce_field('koi_schedule_nonce_action', 'koi_schedule_nonce_field');

        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr><th><input type="checkbox" id="select-all"></th><th>Streamer</th><th>Date</th><th>Time</th><th>Event</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        foreach ($entries as $entry) {
            $entry_id = esc_attr($entry->id);
            echo '<tr>';
            echo '<td><input type="checkbox" name="bulk_ids[]" value="' . $entry_id . '"></td>';
            echo '<td>';
            echo '<select name="entries[' . $entry_id . '][streamer_id]" required>';
            foreach ($streamers as $streamer) {
                $selected = ($streamer->id == $entry->streamer_id) ? 'selected' : '';
                echo '<option value="' . esc_attr($streamer->id) . '" ' . $selected . '>' . esc_html($streamer->name) . '</option>';
            }
            echo '</select>';
            echo '</td>';
            echo '<td><input type="date" name="entries[' . $entry_id . '][date]" value="' . esc_attr(date('Y-m-d', strtotime($entry->time))) . '" required></td>';
            echo '<td>
                <input type="number" class="koi-number-field" name="entries[' . $entry_id . '][hour]" min="0" max="23" value="' . esc_attr(date('H', strtotime($entry->time))) . '" required placeholder="hh">
                :
                <input type="number" class="koi-number-field" name="entries[' . $entry_id . '][minute]" min="0" max="59" value="' . esc_attr(date('i', strtotime($entry->time))) . '" required placeholder="mm">
            </td>';
            echo '<td><select name="entries[' . $entry_id . '][event_id]">';
            foreach ($events as $event) {
                $selected = ($event->id == $entry->event_id) ? 'selected' : '';
                echo '<option value="' . esc_attr($event->id) . '" ' . $selected . '>' . esc_html($event->name) . '</option>';
            }
            echo '</select></td>';
            echo '<td>';
            echo '<button type="submit" name="edit_schedule[' . $entry_id . ']" class="button button-primary">Update</button> ';
            echo '<button type="submit" name="delete_schedule[' . $entry_id . ']" class="button button-secondary" onclick="return confirm(\'Are you sure you want to delete this entry?\');">Delete</button>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        echo '<div class="koi-admin-filters">';
        echo '<h3>Bulk Actions</h3>';
        echo '<label>Edit selected:</label> ';
        echo '<select name="bulk_streamer_id"><option value="">--no changes--</option>';
        foreach ($streamers as $streamer) {
            echo '<option value="' . esc_attr($streamer->id) . '">' . esc_html($streamer->name) . '</option>';
        }
        echo '</select> ';
        echo '<input type="date" name="bulk_date"> ';
        echo '<input type="number" class="koi-number-field" name="bulk_hour" min="0" max="23" placeholder="hh">:';
        echo '<input type="number" class="koi-number-field" name="bulk_minute" min="0" max="59" placeholder="mm"> ';
        echo '<select name="bulk_event_id"><option value="">--no changes--</option>';
        foreach ($events as $event) {
            echo '<option value="' . esc_attr($event->id) . '">' . esc_html($event->name) . '</option>';
        }
        echo '</select> ';
        echo '<button type="submit" name="schedule_action" value="bulk_edit" class="button button-primary">Update selected</button>';
        echo '</div>';
        echo '</form>';

        echo "<script>
            document.getElementById('select-all').addEventListener('change', function() {
                document.querySelectorAll('input[name=\"bulk_ids[]\"]').forEach(cb => cb.checked = this.checked);
            });
        </script>";

        echo '<div class="koi-admin-filters">';
        echo '<form method="post" action="">';
        wp_nonce_field('koi_schedule_nonce_action', 'koi_schedule_nonce_field');
        echo '<label for="delete-date">Delete entries: </label>';
        echo '<select name="delete_direction" id="delete-direction">';
        echo '<option value="older">older than</option>';
        echo '<option value="newer">newer than</option>';
        echo '</select> ';
        echo '<input type="date" id="delete-date" name="delete_older_date">';
        echo '<button type="submit" name="schedule_action" value="delete_older" class="button button-secondary" onclick="return confirm(\'Are you sure you want to delete entries?\');">Delete</button>';
        echo '</form>';
        echo '</div>';

        $total_pages = ceil($total_items / $items_per_page);
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            if ($current_page > 1) {
                echo '<a class="button" href="' . esc_url(add_query_arg(['paged' => $current_page - 1])) . '">Previous</a>';
            }
            if ($current_page < $total_pages) {
                echo '<a class="button" href="' . esc_url(add_query_arg(['paged' => $current_page + 1])) . '">Next</a>';
            }
            echo '</div></div>';
        }
    } else {
        echo '<p>No entries found.</p>';
    }

    echo '</div>';
}

/**
 * Enqueues scripts and styles for the admin pages.
 *
 * @param string $hook The current admin page hook.
 * @return void
 */
function koi_schedule_admin_scripts($hook): void
{
    if ($hook !== 'toplevel_page_koi_schedule' && $hook !== 'koi-schedule_page_koi-schedule-edit') {
        return;
    }

    wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.18/main.min.css', [], '6.1.18');
    wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.18/index.global.min.js', [], '6.1.18', true);
    wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js', [], '5.3.1', true);
}
add_action('admin_enqueue_scripts', 'koi_schedule_admin_scripts');

/**
 * Adds a menu page for Koi Schedule in the WordPress admin dashboard.
 *
 * @return void
 */
function schedule_add_menu_page(): void
{
    add_menu_page(
        'Add schedule entries',
        'Koi Schedule',
        'manage_options',
        'koi_schedule',
        'schedule_entry_page',
        'dashicons-calendar',
        2
    );

    add_submenu_page(
        'koi_schedule',
        'Edit schedule entries',
        'Edit schedule entries',
        'manage_options',
        'koi-schedule-edit',
        'schedule_edit_entry_page'
    );
}

/**
 * Main page for adding new schedule entries.
 *
 * @return void
 */
function schedule_entry_page(): void
{
    echo '<div class="wrap">';
    echo '<h1>Koi schedule form</h1>';
    echo schedule_entry_form();
    echo '</div>';
}

/**
 * Page for editing existing schedule entries.
 *
 * @return void
 */
function schedule_edit_entry_page(): void
{
    echo '<div class="wrap">';
    schedule_edit_entry_form();
    echo '</div>';
}

// Action registration for the admin menu.
add_action('admin_menu', 'schedule_add_menu_page');
