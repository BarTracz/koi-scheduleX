<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates the Koi Streamers table in the database.
 *
 * @return void
 */
function create_koi_streamers_table(): void
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'koi_streamers';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name tinytext NOT NULL,
            link tinytext NOT NULL,
            avatar_url VARCHAR(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if (!empty($wpdb->last_error)) {
        error_log('Database Error: ' . $wpdb->last_error);
    }
}

function update_koi_streamers_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'koi_streamers';

    // Check if the 'user_id' column already exists
    $column_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $table_name,
            'user_id'
        )
    );

    if (!$column_exists) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN user_id BIGINT(20) UNSIGNED DEFAULT NULL");
        $wpdb->query("ALTER TABLE $table_name ADD CONSTRAINT fk_user_id FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL");
    }
}
