<?php
/**
 * Plugin Deactivator
 *
 * Handles all deactivation tasks including clearing scheduled events.
 *
 * @package BrokenLinkChecker
 * @since 1.0.0
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Class BLC_Deactivator
 *
 * Fired during plugin deactivation
 */
class BLC_Deactivator
{

    /**
     * Deactivate the plugin
     *
     * Clears scheduled events and temporary data.
     * Does NOT remove database tables or settings.
     */
    public static function deactivate()
    {
        self::clear_scheduled_events();
        self::clear_transients();
        self::stop_running_scans();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Clear all scheduled cron events
     */
    private static function clear_scheduled_events()
    {
        $events = array(
            'blc_scheduled_scan',
            'blc_recheck_broken',
            'blc_send_digest',
            'blc_cleanup_old_data',
            'blc_process_queue',
        );

        foreach ($events as $event) {
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
            }
            // Clear all instances of the event
            wp_clear_scheduled_hook($event);
        }
    }

    /**
     * Clear plugin transients
     */
    private static function clear_transients()
    {
        global $wpdb;

        // Delete all plugin transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_blc_%',
                '_transient_timeout_blc_%'
            )
        );

        // Clear specific transients
        delete_transient('blc_activation_redirect');
        delete_transient('blc_scan_progress');
        delete_transient('blc_stats_cache');
    }

    /**
     * Stop any running scans
     */
    private static function stop_running_scans()
    {
        global $wpdb;

        $table_scans = $wpdb->prefix . 'blc_scans';

        // Mark running scans as cancelled
        $wpdb->update(
            $table_scans,
            array(
                'status' => 'cancelled',
                'completed_at' => current_time('mysql'),
                'error_message' => __('Scan cancelled: Plugin deactivated', 'dead-link-checker'),
            ),
            array('status' => 'running'),
            array('%s', '%s', '%s'),
            array('%s')
        );

        // Also mark pending scans
        $wpdb->update(
            $table_scans,
            array(
                'status' => 'cancelled',
                'error_message' => __('Scan cancelled: Plugin deactivated', 'dead-link-checker'),
            ),
            array('status' => 'pending'),
            array('%s', '%s'),
            array('%s')
        );
    }
}
