<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form for adding new subathons.
 *
 * @return false|string
 */
function subathons_entry_form(): false|string
{
    ob_start();
    ?>
	<form method="post" action="" id="koi-subathons-form">
		<table>
			<tr>
				<th>Streamer</th>
			</tr>
			<tr>
				<td>
					<select id="streamer_id" name="streamer_id" required>
						<?php
                        global $wpdb;
    $streamers = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}koi_streamers");
    foreach ($streamers as $streamer) {
        echo '<option value="' . esc_attr($streamer->id) . '">' . esc_html($streamer->name) . '</option>';
    }
    ?>
					</select>
				</td>
			</tr>
			<tr>
				<th>Timer Link</th>
			</tr>
			<tr>
				<td><input type="url" id="timer_link" name="timer_link" required></td>
			</tr>
			<tr>
				<th>Goals Link</th>
			</tr>
			<tr>
				<td><input type="url" id="goals_link" name="goals_link"></td>
			</tr>
            <tr>
                <th>Mobile Timer Link</th>
            </tr>
            <tr>
                <td><input type="url" id="timer_link_mobile" name="timer_link_mobile" required></td>
            </tr>
            <tr>
                <th>Mobile Goals Link</th>
            </tr>
            <tr>
                <td><input type="url" id="goals_link_mobile" name="goals_link_mobile"></td>
            </tr>
            <tr>
                <th>Start Date</th>
            </tr>
            <tr>
                <td><input type="date" id="date" name="date" required></td>
            </tr>
            <tr>
                <th>Start Time</th>
            </tr>
            <tr>
                <td>
                    <div>
                        <input type="number" id="hour" name="hour" min="0" max="23" placeholder="hh" required>
                        :
                        <input type="number" id="minute" name="minute" min="0" max="59" placeholder="mm" required>
                    </div>
                </td>
            </tr>
		</table>
		<p>
			<input type="submit" name="submit_subathon" value="Add Subathon" class="button button-primary">
		</p>
		<input type="hidden" name="subathon_action" value="add_subathon">
		<?php wp_nonce_field('subathon_nonce_action', 'subathon_nonce_field'); ?>
	</form>
	<?php
    return ob_get_clean();
}

/**
 * Form for editing and deleting subathons.
 *
 * @return void
 */
function subathons_edit_entry_form(): void
{
    global $wpdb;
    $subathons_table = $wpdb->prefix . 'koi_subathons';

    $items_per_page = 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $subathons_table");

    $subathons = $wpdb->get_results($wpdb->prepare("
		SELECT id, streamer_id, timer_link, goals_link, timer_link_mobile, goals_link_mobile, start_date, created_at
		FROM $subathons_table
		LIMIT %d OFFSET %d
	", $items_per_page, $offset));

    echo '<div class="wrap">';
    echo '<h1>Edit subathons</h1>';

    if ($subathons) {
        echo '<table class="wp-list-table widefat striped">';
        echo '<thead><tr><th>Streamer</th><th>Timer Link</th><th>Goals Link</th><th>Mobile Timer Link</th><th>Mobile Goals Link</th><th>Start Date</th><th>Start Time</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        foreach ($subathons as $subathon) {
            echo '<tr>';
            echo '<form method="post" action="">';
            echo '<td>';
            echo '<select name="streamer_id" required>';
            $streamers = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}koi_streamers");
            foreach ($streamers as $streamer) {
                $selected = ($streamer->id == $subathon->streamer_id) ? 'selected' : '';
                echo '<option value="' . esc_attr($streamer->id) . '" ' . $selected . '>' . esc_html($streamer->name) . '</option>';
            }
            echo '</select>';
            echo '</td>';
            echo '<td><input type="url" name="timer_link" value="' . esc_url($subathon->timer_link) . '" required></td>';
            echo '<td><input type="url" name="goals_link" value="' . esc_url($subathon->goals_link) . '" required></td>';
            echo '<td><input type="url" name="timer_link_mobile" value="' . esc_url($subathon->timer_link_mobile) . '" required></td>';
            echo '<td><input type="url" name="goals_link_mobile" value="' . esc_url($subathon->goals_link_mobile) . '" required></td>';
            echo '<td><input type="date" name="date" value="' . esc_attr(date('Y-m-d', strtotime($subathon->start_date))) . '" required></td>';
            echo '<td>
                <input type="number" class="koi-number-field" name="hour" min="0" max="23" value="' . esc_attr(date('H', strtotime($subathon->start_date))) . '" required placeholder="hh">
                :
                <input type="number" class="koi-number-field" name="minute" min="0" max="59" value="' . esc_attr(date('i', strtotime($subathon->start_date))) . '" required placeholder="mm">
            </td>';
            echo '<td>';
            echo '<input type="hidden" name="subathon_id" value="' . esc_attr($subathon->id) . '">';
            echo '<button type="submit" name="subathon_action" value="edit_subathon" class="button button-primary">Update</button> ';
            echo '<button type="submit" name="subathon_action" value="delete_subathon" class="button button-secondary" onclick="return confirm(\'Are you sure you want to delete this subathon?\');">Delete</button>';
            wp_nonce_field('koi_subathons_nonce_action', 'koi_subathons_nonce_field');
            echo '</td>';
            echo '</form>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        // Pagination
        $total_pages = ceil($total_items / $items_per_page);
        echo '<div class="tablenav"><div class="tablenav-pages">';
        if ($current_page > 1) {
            echo '<a class="button" href="' . esc_url(add_query_arg(['paged' => $current_page - 1], admin_url('admin.php?page=koi-subathons-edit'))) . '">Previous</a>';
        }
        if ($current_page < $total_pages) {
            echo '<a class="button" href="' . esc_url(add_query_arg(['paged' => $current_page + 1], admin_url('admin.php?page=koi-subathons-edit'))) . '">Next</a>';
        }
        echo '</div></div>';
    } else {
        echo '<p>No entries found.</p>';
    }

    echo '</div>';
}

/**
 * Adds the menu page for Koi Subathons in the WordPress admin dashboard.
 *
 * @return void
 */
function subathons_add_menu_page(): void
{
    add_menu_page(
        'Koi Subathons',
        'Koi Subathons',
        'manage_options',
        'koi_subathons',
        'subathons_entry_page',
        'dashicons-image-filter',
        2
    );

    add_submenu_page(
        'koi_subathons',
        'Edit Subathons',
        'Edit Subathons',
        'manage_options',
        'koi-subathons-edit',
        'subathons_edit_entry_page'
    );
}

/**
 * Main page for adding new subathons.
 *
 * @return void
 */
function subathons_entry_page(): void
{
    echo '<div class="wrap">';
    echo '<h1>Koi subathons form</h1>';
    echo subathons_entry_form();
    echo '</div>';
}

/**
 * Main page for editing and deleting subathons.
 *
 * @return void
 */
function subathons_edit_entry_page(): void
{
    echo '<div class="wrap">';
    subathons_edit_entry_form();
    echo '</div>';
}

// Action registration for the admin menu.
add_action('admin_menu', 'subathons_add_menu_page');
