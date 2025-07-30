<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates the Koi Calendar table in the database.
 *
 * @return void
 */
function create_koi_calendar_table(): void
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'koi_calendar';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        streamer_id mediumint(9) NOT NULL,
        date DATE NOT NULL,
        time_from TIME NOT NULL,
        time_to TIME NOT NULL,
        available BOOLEAN NOT NULL DEFAULT 0,
        request VARCHAR(255) DEFAULT NULL,
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
