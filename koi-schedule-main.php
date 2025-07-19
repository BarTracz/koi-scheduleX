<?php

/**
 * Plugin Name: KoiSchedule
 * Description: A plugin to manage schedules for KoiCorp
 * Version: 0.4.0
 * Author: KoiCorp
 */

if (! defined('ABSPATH')) {
    exit;
}

global $wpdb;

define('KOI_SCHEDULE_PATH', plugin_dir_path(__FILE__));
define('KOI_SCHEDULE_URL', plugin_dir_url(__FILE__));

define('KOI_SCHEDULE_TABLE_NAME', $wpdb->prefix . 'koi-schedule');
define('KOI_STREAMERS_TABLE_NAME', $wpdb->prefix . 'koi-streamers');
define('KOI_EVENTS_TABLE_NAME', $wpdb->prefix . 'koi_events');

require_once KOI_SCHEDULE_PATH . 'includes/db/koi-streamers-table.php';
require_once KOI_SCHEDULE_PATH . 'includes/db/koi-events-table.php';
require_once KOI_SCHEDULE_PATH . 'includes/db/koi-schedule-table.php';

require_once KOI_SCHEDULE_PATH . 'includes/koi-schedule-common.php';

require_once KOI_SCHEDULE_PATH . 'includes/forms/koi-streamers.php';
require_once KOI_SCHEDULE_PATH . 'includes/forms/koi-events.php';
require_once KOI_SCHEDULE_PATH . 'includes/forms/koi-schedule.php';

require_once KOI_SCHEDULE_PATH . 'includes/handlers/koi-streamers-handlers.php';
require_once KOI_SCHEDULE_PATH . 'includes/handlers/koi-events-handlers.php';
require_once KOI_SCHEDULE_PATH . 'includes/handlers/koi-schedule-handlers.php';

require_once KOI_SCHEDULE_PATH . 'includes/front-display/koi-schedule-front-display.php';

register_activation_hook(__FILE__, function () {
    // Object buffer to prevent direct output during activation.
    ob_start();
    // Create the necessary database tables.
    create_koi_streamers_table();
    create_koi_events_table();
    create_koi_schedule_table();
    update_koi_schedule_table();
    // Flush the output buffer to prevent any direct output.
    ob_end_clean();
});
