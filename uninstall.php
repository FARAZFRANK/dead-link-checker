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
function awldlc_uninstall()
{
    global $wpdb;

    // Check if user wants to keep data (future feature)
    $settings = get_option('awldlc_settings', array());
    $keep_data = isset($settings['keep_data_on_uninstall']) && $settings['keep_data_on_uninstall'];

    if ($keep_data) {
        return;
    }

    // Drop custom tables
    $table_links = $wpdb->prefix . 'awldlc_links';
    $table_scans = $wpdb->prefix . 'awldlc_scans';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query("DROP TABLE IF EXISTS {$table_links}");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query("DROP TABLE IF EXISTS {$table_scans}");

    // Delete all plugin options
    $options_to_delete = array(
        'awldlc_settings',
        'awldlc_db_version',
        'awldlc_last_scan',
        'awldlc_scan_stats',
    );

    foreach ($options_to_delete as $option) {
        delete_option($option);
    }

    // Delete all plugin transients
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_awldlc_%',
            '_transient_timeout_awldlc_%'
        )
    );

    // Delete user meta
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
            'awldlc_%'
        )
    );

    // Clear any scheduled cron events
    $cron_events = array(
        'awldlc_scheduled_scan',
        'awldlc_recheck_broken',
        'awldlc_send_digest',
        'awldlc_cleanup_old_data',
        'awldlc_process_queue',
        'awldlc_stale_scan_watchdog',
    );

    foreach ($cron_events as $event) {
        wp_clear_scheduled_hook($event);
    }

    // Delete export files
    $upload_dir = wp_upload_dir();
    $export_dir = $upload_dir['basedir'] . '/dlc-exports';
    if (is_dir($export_dir)) {
        $files = glob($export_dir . '/*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    wp_delete_file($file);
                }
            }
        }
        @rmdir($export_dir);
    }

    // Clear rewrite rules
    flush_rewrite_rules();
}

// Run uninstall
awldlc_uninstall();
