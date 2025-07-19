<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates the Koi Schedule table in the database.
 *
 * @return void
 */
function create_koi_schedule_table(): void
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'koi_schedule';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time DATETIME NOT NULL,
        streamer_id mediumint(9) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (streamer_id) REFERENCES {$wpdb->prefix}koi_streamers(id) ON DELETE CASCADE
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if (!empty($wpdb->last_error)) {
        error_log('Database Error: ' . $wpdb->last_error);
    }
}

/**
 * Updates the Koi Schedule table to add the 'event_id' column and foreign key constraint.
 *
 * @return void
 */
function update_koi_schedule_table(): void
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'koi_schedule';
    $events_table = $wpdb->prefix . 'koi_events';

    // Check if the 'event_id' column exists, and if not, add it.
    if ($wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'event_id'") === null) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN event_id mediumint(9) NOT NULL DEFAULT 1");
        $wpdb->query("ALTER TABLE $table_name ADD FOREIGN KEY (event_id) REFERENCES $events_table(id) ON DELETE RESTRICT");
    }

    if (!empty($wpdb->last_error)) {
        error_log('Database Error: ' . $wpdb->last_error);
    }
}
