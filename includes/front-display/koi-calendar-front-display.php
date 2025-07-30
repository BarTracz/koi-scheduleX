<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display the calendar for streamers' availability.
 *
 * @return false|string
 */
function display_calendar(): false|string
{
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url());
        exit;
    }

    $user = wp_get_current_user();
    $allowed_roles = ['contributor', 'administrator'];
    if (!array_intersect($allowed_roles, $user->roles)) {
        wp_safe_redirect(home_url());
        exit;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'koi_calendar';

    $relation_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}koi_streamers WHERE user_id = %d",
        get_current_user_id()
    ));

    if (!$relation_id) {
        return '<p>No streamer profile associated with your user account.</p>';
    }

    $month = isset($_GET['month']) ? intval($_GET['month']) : date('n', strtotime('+1 month'));
    $year  = isset($_GET['year']) ? intval($_GET['year']) : date('Y', strtotime('+1 month'));

    $first_day_of_month = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
    $last_day_of_month = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
    $days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT date, HOUR(time_from) as hour_from, MINUTE(time_from) as min_from,
               HOUR(time_to) as hour_to, MINUTE(time_to) as min_to, available, request 
        FROM {$table_name} 
        WHERE streamer_id = %d AND date BETWEEN %s AND %s",
        $relation_id,
        $first_day_of_month,
        $last_day_of_month
    ));

    $available_map = [];
    foreach ($results as $row) {
        $available_map[$row->date] = [
            'from'      => $row->hour_from,
            'from_min'  => $row->min_from,
            'to'        => $row->hour_to,
            'to_min'    => $row->min_to,
            'available' => $row->available,
            'request'   => $row->request
        ];
    }

    ob_start();

    $prev_month = $month - 1;
    $prev_year = $year;
    if ($prev_month == 0) {
        $prev_month = 12;
        $prev_year--;
    }

    $next_month = $month + 1;
    $next_year = $year;
    if ($next_month == 13) {
        $next_month = 1;
        $next_year++;
    }
    ?>
<div class="koi-calendar-container">
    <div class="koi-calendar-nav">
        <a class="koi-schedule-date-arrow" href="<?php echo esc_url(add_query_arg(['month' => $prev_month, 'year' => $prev_year])); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span></a>
        <p class="koi-schedule-date-range"><?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></p>
        <a class="koi-schedule-date-arrow" href="<?php echo esc_url(add_query_arg(['month' => $next_month, 'year' => $next_year])); ?>"><span class="dashicons dashicons-arrow-right-alt2"></span></a>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="koi-calendar-form">
        <input type="hidden" name="action" value="koi_calendar_save">
        <input type="hidden" name="month" value="<?php echo esc_attr($month); ?>">
        <input type="hidden" name="year" value="<?php echo esc_attr($year); ?>">
		<?php wp_nonce_field('koi_calendar_save', 'koi_calendar_nonce'); ?>
        <table class="koi-calendar-table">
            <thead>
            <tr>
                <th>Day</th>
                <th>Availability (from-to)</th>
                <th>Off</th>
                <th>Note</th>
            </tr>
            </thead>
            <tbody>
			<?php for ($d = 1; $d <= $days_in_month; $d++):
			    $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
			    $val = $available_map[$date] ?? null;
			    $is_unavailable = isset($val['available']) && $val['available'] == 0;
			    ?>
                <tr>
                    <td><?php echo $date; ?></td>
                    <td>
                        <input type="number" class="koi-front-number-field" name="availability[<?php echo $date; ?>][from]" min="0" max="23" value="<?php echo isset($val['from']) && !$is_unavailable ? esc_attr($val['from']) : ''; ?>" <?php disabled($is_unavailable, true); ?> placeholder="hh"> :
                        <input type="number" class="koi-front-number-field" name="availability[<?php echo $date; ?>][from_min]" min="0" max="59" value="<?php echo isset($val['from_min']) && !$is_unavailable ? esc_attr($val['from_min']) : ''; ?>" <?php disabled($is_unavailable, true); ?> placeholder="mm"> -
                        <input type="number" class="koi-front-number-field" name="availability[<?php echo $date; ?>][to]" min="0" max="23" value="<?php echo isset($val['to']) && !$is_unavailable ? esc_attr($val['to']) : ''; ?>" <?php disabled($is_unavailable, true); ?> placeholder="hh"> :
                        <input type="number" class="koi-front-number-field" name="availability[<?php echo $date; ?>][to_min]" min="0" max="59" value="<?php echo isset($val['to_min']) && !$is_unavailable ? esc_attr($val['to_min']) : ''; ?>" <?php disabled($is_unavailable, true); ?> placeholder="mm">
                    </td>
                    <td>
                        <input type="checkbox" name="unavailable[<?php echo $date; ?>]" class="unavailable-checkbox" <?php checked(isset($val['available']) && $val['available'] == 0); ?>>
                    </td>
                    <td>
                        <textarea name="request[<?php echo $date; ?>]" class="koi-textarea" maxlength="255" placeholder="Note (Karaoke, uncertain, etc.)" <?php disabled($is_unavailable, true); ?>><?php echo isset($val['request']) ? esc_textarea($val['request']) : ''; ?></textarea>
                    </td>
                </tr>
			<?php endfor; ?>
            </tbody>
        </table>
        <p><input type="submit" class="button button-primary" value="Save Availability"></p>
    </form>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.unavailable-checkbox').forEach(function(checkbox) {
                const row = checkbox.closest('tr');
                const inputs = row.querySelectorAll('input[type="number"], textarea');

                checkbox.addEventListener('change', function() {
                    inputs.forEach(function(input) {
                        input.disabled = checkbox.checked;
                        if(checkbox.checked) {
                            if (input.type === 'number' || input.type === 'textarea') {
                                input.value = '';
                            }
                        }
                    });
                });
            });

            document.getElementById('koi-calendar-form').addEventListener('submit', function(e) {
                let errorMessages = [];
                document.querySelectorAll('.koi-calendar-table tbody tr').forEach(function(row) {
                    const fromHourInput = row.querySelector('input[name$="[from]"]');
                    const fromMinInput = row.querySelector('input[name$="[from_min]"]');
                    const toHourInput = row.querySelector('input[name$="[to]"]');
                    const toMinInput = row.querySelector('input[name$="[to_min]"]');
                    const unavailableCheckbox = row.querySelector('.unavailable-checkbox');

                    if (fromHourInput && !unavailableCheckbox.checked) {
                        const fromHour = parseInt(fromHourInput.value, 10);
                        const toHour = parseInt(toHourInput.value, 10);
                        const fromMin = parseInt(fromMinInput.value, 10) || 0;
                        const toMin = parseInt(toMinInput.value, 10) || 0;

                        if (!isNaN(fromHour) && !isNaN(toHour)) {
                            if (fromHour > toHour || (fromHour === toHour && fromMin >= toMin)) {
                                errorMessages.push(`For day ${row.cells[0].innerText}, the start time must be earlier than the end time.`);
                            }
                        }
                    }
                });

                if (errorMessages.length > 0) {
                    e.preventDefault();
                    alert(errorMessages.join('\n'));
                }
            });
        });
    </script>
	<?php
    return ob_get_clean();
}

// Register the shortcode to display the calendar.
add_shortcode('koi_calendar_display', 'display_calendar');
