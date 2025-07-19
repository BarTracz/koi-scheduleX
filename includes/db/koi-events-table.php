<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates the Koi Events table in the database.
 *
 * @return void
 */
function create_koi_events_table(): void
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'koi_events';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name tinytext NOT NULL,
            icon_url VARCHAR(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if (!empty($wpdb->last_error)) {
        error_log('Database Error: ' . $wpdb->last_error);
    }

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE name = %s",
        'Normal'
    ));
    if (!$exists) {
        $wpdb->insert($table_name, [
            'name' => 'Normal',
            'icon_url' => '',
        ], [
            '%s',
            '%s'
        ]);
    }
}
