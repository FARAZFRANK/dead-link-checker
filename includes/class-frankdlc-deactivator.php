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
 * Class FRANKDLC_Deactivator
 *
 * Fired during plugin deactivation
 */
class FRANKDLC_Deactivator
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
            'FRANKDLC_scheduled_scan',
            'FRANKDLC_recheck_broken',
            'FRANKDLC_send_digest',
            'FRANKDLC_cleanup_old_data',
            'FRANKDLC_process_queue',
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
                '_transient_FRANKDLC_%',
                '_transient_timeout_FRANKDLC_%'
            )
        );

        // Clear specific transients
        delete_transient('FRANKDLC_activation_redirect');
        delete_transient('FRANKDLC_scan_progress');
        delete_transient('FRANKDLC_stats_cache');
    }

    /**
     * Stop any running scans
     */
    private static function stop_running_scans()
    {
        global $wpdb;

        $table_scans = $wpdb->prefix . 'FRANKDLC_scans';

        // Mark running scans as cancelled
        $wpdb->update(
            $table_scans,
            array(
                'status' => 'cancelled',
                'completed_at' => current_time('mysql'),
                'error_message' => __('Scan cancelled: Plugin deactivated', 'frank-dead-link-checker'),
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
                'error_message' => __('Scan cancelled: Plugin deactivated', 'frank-dead-link-checker'),
            ),
            array('status' => 'pending'),
            array('%s', '%s'),
            array('%s')
        );
    }
}
