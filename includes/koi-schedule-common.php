<?php

// Don't allow direct access to this file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueues the styles for the Koi Schedule display.
 *
 * @return void
 */
function koi_schedule_enqueue_styles(): void
{
    wp_enqueue_style(
        'koi-schedule-style',
        plugins_url('css/koi-schedule.css', __FILE__)
    );
    wp_enqueue_style('dashicons');
}

// Action to enqueue styles for the front-end display of Koi Schedule.
add_action('wp_enqueue_scripts', 'koi_schedule_enqueue_styles');
add_action('admin_enqueue_scripts', 'koi_schedule_enqueue_styles');
