<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates the Koi Subathons table in the database.
 *
 * @return void
 */
function create_koi_subathons_table(): void
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'koi_subathons';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            streamer_id mediumint(9) NOT NULL,
            timer_link VARCHAR(255) NOT NULL,
            goals_link VARCHAR(255) NOT NULL,
            start_date datetime NOT NULL,
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

function update_koi_subathons_table(): void
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'koi_subathons';

    if ($wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'timer_link_mobile'") === null &&
        $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'goals_link_mobile'") === null) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN IF NOT EXISTS timer_link_mobile VARCHAR(255) NOT NULL DEFAULT ''");
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN IF NOT EXISTS goals_link_mobile VARCHAR(255) NOT NULL DEFAULT ''");
    }
}
