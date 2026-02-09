<?php
/**
 * Plugin Uninstaller
 *
 * Fired when the plugin is uninstalled (deleted).
 * Removes all data created by the plugin including:
 * - Database tables
 * - Options
 * - Transients
 * - User meta
 *
 * @package BrokenLinkChecker
 * @since 1.0.0
 */

// Exit if not called by WordPress uninstall process
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Uninstall the plugin completely
 */
function blc_uninstall()
{
    global $wpdb;

    // Check if user wants to keep data (future feature)
    $settings = get_option('blc_settings', array());
    $keep_data = isset($settings['keep_data_on_uninstall']) && $settings['keep_data_on_uninstall'];

    if ($keep_data) {
        return;
    }

    // Drop custom tables
    $table_links = $wpdb->prefix . 'blc_links';
    $table_scans = $wpdb->prefix . 'blc_scans';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query("DROP TABLE IF EXISTS {$table_links}");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query("DROP TABLE IF EXISTS {$table_scans}");

    // Delete all plugin options
    $options_to_delete = array(
        'blc_settings',
        'blc_db_version',
        'blc_last_scan',
        'blc_scan_stats',
    );

    foreach ($options_to_delete as $option) {
        delete_option($option);
    }

    // Delete all plugin transients
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_blc_%',
            '_transient_timeout_blc_%'
        )
    );

    // Delete user meta
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
            'blc_%'
        )
    );

    // Clear any scheduled cron events
    $cron_events = array(
        'blc_scheduled_scan',
        'blc_send_digest',
        'blc_cleanup_old_data',
        'blc_process_queue',
    );

    foreach ($cron_events as $event) {
        wp_clear_scheduled_hook($event);
    }

    // Clear rewrite rules
    flush_rewrite_rules();
}

// Run uninstall
blc_uninstall();
