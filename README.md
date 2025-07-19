# Koi Schedule

The **Koi Schedule** plugin allows you to display a schedule of streams and streamer events on your WordPress site. It provides filtering by week, streamer, day, and hour.

## Features

- Display streamer schedules in a clear table.
- Filter by streamer, day of the week, and hour.
- Support for events linked to streams.
- Week navigation.
- Shortcode for easy schedule placement on any page.

## Installation

1. Copy the plugin folder to the `wp-content/plugins/` directory.
2. Activate the plugin in the WordPress admin panel (`Plugins` → `Installed Plugins`).
3. Make sure the database tables (`koi_schedule`, `koi_streamers`, `koi_events`) are correctly created.

## Usage

### Displaying the schedule

To display the schedule on a page or post, use the shortcode: [koi_schedule_display]

### Admin panel

1. Go to the WordPress admin panel.
2. Add streamers, events, and schedule streams.
3. Configure the schedule as needed.

### Schedule filtering

On the schedule page, the following filters are available:
- **Streamer** – select a specific streamer or all.
- **Day of the week** – select a day or all days.
- **Hour** – select a specific hour or all.
- **Week navigation** – move between weeks.

## Requirements

- WordPress 5.0 or newer
- PHP 7.4 or newer

## Help

If you encounter problems, check the WordPress error logs or contact the developer (ME).

---

**Author:** KoiCorp  
**License:** MIT
