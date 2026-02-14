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
function FRANKDLC_uninstall()
{
    global $wpdb;

    // Check if user wants to keep data (future feature)
    $settings = get_option('FRANKDLC_settings', array());
    $keep_data = isset($settings['keep_data_on_uninstall']) && $settings['keep_data_on_uninstall'];

    if ($keep_data) {
        return;
    }

    // Drop custom tables
    $table_links = $wpdb->prefix . 'FRANKDLC_links';
    $table_scans = $wpdb->prefix . 'FRANKDLC_scans';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query("DROP TABLE IF EXISTS {$table_links}");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query("DROP TABLE IF EXISTS {$table_scans}");

    // Delete all plugin options
    $options_to_delete = array(
        'FRANKDLC_settings',
        'FRANKDLC_db_version',
        'FRANKDLC_last_scan',
        'FRANKDLC_scan_stats',
    );

    foreach ($options_to_delete as $option) {
        delete_option($option);
    }

    // Delete all plugin transients
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_FRANKDLC_%',
            '_transient_timeout_FRANKDLC_%'
        )
    );

    // Delete user meta
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
            'FRANKDLC_%'
        )
    );

    // Clear any scheduled cron events
    $cron_events = array(
        'FRANKDLC_scheduled_scan',
        'FRANKDLC_recheck_broken',
        'FRANKDLC_send_digest',
        'FRANKDLC_cleanup_old_data',
        'FRANKDLC_process_queue',
        'FRANKDLC_stale_scan_watchdog',
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
FRANKDLC_uninstall();
