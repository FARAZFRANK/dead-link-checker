<?php

/**
 * Link Scanner
 *
 * Main scanner that orchestrates link discovery and checking.
 *
 * @package BrokenLinkChecker
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class FRANKDLC_Scanner
{

    private $parser;
    private $checker;

    public function __construct()
    {
        $this->parser = new FRANKDLC_Parser();
        $this->checker = new FRANKDLC_Checker();
        add_action('FRANKDLC_scheduled_scan', array($this, 'run_scheduled_scan'));
        add_action('FRANKDLC_process_queue', array($this, 'process_queue'));
        add_action('FRANKDLC_recheck_broken', array($this, 'recheck_broken_links'));
    }

    public function start_scan($type = 'full')
    {
        if (get_transient('FRANKDLC_scan_starting')) {
            return new WP_Error('scan_starting', __('A scan is currently initializing. Please try again in a moment.', 'frank-dead-link-checker'));
        }

        if (FRANKDLC()->database->is_scan_running()) {
            return new WP_Error('scan_running', __('A scan is already in progress.', 'frank-dead-link-checker'));
        }

        set_transient('FRANKDLC_scan_starting', true, 30);

        $scan_id = FRANKDLC()->database->create_scan($type);
        if (!$scan_id) {
            delete_transient('FRANKDLC_scan_starting');
            return new WP_Error('scan_failed', __('Failed to create scan record.', 'frank-dead-link-checker'));
        }

        FRANKDLC()->database->update_scan($scan_id, array('status' => 'running'));
        set_transient('FRANKDLC_current_scan_id', $scan_id, HOUR_IN_SECONDS);

        // Discover all links
        $total_links = $this->discover_links();

        FRANKDLC()->database->update_scan($scan_id, array('total_links' => $total_links));
        delete_transient('FRANKDLC_scan_starting');

        // Schedule queue processing using Queue Manager
        if (!FRANKDLC_Queue_Manager::is_scheduled('FRANKDLC_process_queue')) {
            FRANKDLC_Queue_Manager::schedule_single(time() + 5, 'FRANKDLC_process_queue');
        }

        return $scan_id;
    }

    private function discover_links()
    {
        $count = 0;
        $settings = get_option('FRANKDLC_settings', array());

        // Scan posts
        if (!empty($settings['scan_posts'])) {
            $count += $this->scan_post_type('post');
        }

        // Scan pages
        if (!empty($settings['scan_pages'])) {
            $count += $this->scan_post_type('page');
        }

        return $count;
    }

    private function scan_post_type($post_type)
    {
        $count = 0;
        $posts_per_page = 50;
        $paged = 1;

        while (true) {
            $posts = get_posts(array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => $posts_per_page,
                'paged'          => $paged,
                'fields'         => 'ids',
            ));

            if (empty($posts)) {
                break;
            }

            foreach ($posts as $post_id) {
                $post = get_post($post_id);
                if (!$post) {
                    continue;
                }

                // Check if this post uses Gutenberg blocks
                $uses_page_builder = false;

                if (class_exists('FRANKDLC_Parser_Gutenberg') && FRANKDLC_Parser_Gutenberg::is_active() && FRANKDLC_Parser_Gutenberg::has_blocks($post_id)) {
                    $uses_page_builder = true;
                }

                // Only use standard parser if NOT using a page builder (to avoid duplicates)
                if (!$uses_page_builder) {
                    $links = $this->parser->parse_content($post->post_content);
                    foreach ($links as $link) {
                        $link['source_id'] = $post_id;
                        $link['source_type'] = $post_type;
                        $link['source_field'] = 'post_content';
                        if (FRANKDLC()->database->save_link($link)) {
                            $count++;
                        }
                    }
                }

                // Parse with Gutenberg parser
                $count += $this->parse_page_builder_content($post_id, $post_type);

                // Parse excerpt (always parse, as this is separate from main content)
                if (!empty($post->post_excerpt)) {
                    $links = $this->parser->parse_content($post->post_excerpt);
                    foreach ($links as $link) {
                        $link['source_id'] = $post_id;
                        $link['source_type'] = $post_type;
                        $link['source_field'] = 'post_excerpt';
                        if (FRANKDLC()->database->save_link($link)) {
                            $count++;
                        }
                    }
                }

                clean_post_cache($post_id);
            }

            $paged++;
        }

        return $count;
    }

    /**
     * Parse content using Gutenberg block detection
     *
     * @param int    $post_id   Post ID
     * @param string $post_type Post type
     * @return int Number of links saved
     */
    private function parse_page_builder_content($post_id, $post_type)
    {
        $count = 0;

        // Check for Gutenberg blocks
        if (class_exists('FRANKDLC_Parser_Gutenberg') && FRANKDLC_Parser_Gutenberg::is_active()) {
            if (FRANKDLC_Parser_Gutenberg::has_blocks($post_id)) {
                $gutenberg_links = FRANKDLC_Parser_Gutenberg::extract_links($post_id);
                foreach ($gutenberg_links as $link) {
                    $link['source_id'] = $post_id;
                    $link['source_type'] = $post_type;
                    $link['source_field'] = 'gutenberg_blocks';
                    if (FRANKDLC()->database->save_link($link)) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    public function process_queue()
    {
        $scan_id = get_transient('FRANKDLC_current_scan_id');
        if (!$scan_id)
            return;

        $settings = get_option('FRANKDLC_settings', array());
        $batch_size = isset($settings['concurrent_requests']) ? absint($settings['concurrent_requests']) : 3;
        $batch_size = max(1, min(3, $batch_size));

        $links = FRANKDLC()->database->get_links_to_check($batch_size);

        if (empty($links)) {
            $this->complete_scan($scan_id);
            return;
        }

        $checked = 0;
        $broken = 0;
        $warnings = 0;

        foreach ($links as $link) {
            $result = $this->checker->check_url($link->url);
            FRANKDLC()->database->update_link_result($link->id, $result);

            $checked++;
            if (!empty($result['is_broken']))
                $broken++;
            if (!empty($result['is_warning']))
                $warnings++;
        }

        // Update scan progress
        $scan = FRANKDLC()->database->get_running_scan();
        if ($scan) {
            FRANKDLC()->database->update_scan($scan_id, array(
                'checked_links' => $scan->checked_links + $checked,
                'broken_links' => $scan->broken_links + $broken,
                'warning_links' => $scan->warning_links + $warnings,
            ));
        }

        // Store progress for AJAX
        $this->update_progress($scan_id);

        // Schedule next batch using Queue Manager
        $remaining = FRANKDLC()->database->get_links_to_check(1);
        if (!empty($remaining)) {
            FRANKDLC_Queue_Manager::schedule_single(time() + 2, 'FRANKDLC_process_queue');
        } else {
            $this->complete_scan($scan_id);
        }
    }

    private function complete_scan($scan_id)
    {
        $scan = FRANKDLC()->database->get_running_scan();
        if ($scan) {
            FRANKDLC()->database->complete_scan($scan_id, array(
                'total_links' => $scan->total_links,
                'checked_links' => $scan->checked_links,
                'broken_links' => $scan->broken_links,
                'warning_links' => $scan->warning_links,
            ));
        }

        delete_transient('FRANKDLC_current_scan_id');
        delete_transient('FRANKDLC_scan_progress');

        // Trigger notification if broken links found
        if ($scan && $scan->broken_links > 0) {
            do_action('FRANKDLC_scan_complete', $scan);
        }
    }

    private function update_progress($scan_id)
    {
        $scan = FRANKDLC()->database->get_running_scan();
        if (!$scan)
            return;

        $progress = array(
            'scan_id' => $scan_id,
            'status' => $scan->status,
            'total' => (int) $scan->total_links,
            'checked' => (int) $scan->checked_links,
            'broken' => (int) $scan->broken_links,
            'warnings' => (int) $scan->warning_links,
            'percent' => $scan->total_links > 0 ? round(($scan->checked_links / $scan->total_links) * 100) : 0,
        );

        set_transient('FRANKDLC_scan_progress', $progress, HOUR_IN_SECONDS);
    }

    /**
     * Stop a running scan
     *
     * @return bool True if scan was stopped, false if no scan running
     */
    public function stop_scan()
    {
        $scan_id = get_transient('FRANKDLC_current_scan_id');

        // Also check database for running scans (transient may have expired)
        if (!$scan_id) {
            $running_scan = FRANKDLC()->database->get_running_scan();
            if ($running_scan) {
                $scan_id = $running_scan->id;
            } else {
                return false;
            }
        }

        // Update scan status to cancelled
        FRANKDLC()->database->update_scan($scan_id, array(
            'status' => 'cancelled',
            'completed_at' => current_time('mysql'),
        ));

        // Clear transients
        delete_transient('FRANKDLC_current_scan_id');
        delete_transient('FRANKDLC_scan_progress');

        // Clear any scheduled queue processing (uses Queue Manager for AS/WP-Cron)
        FRANKDLC_Queue_Manager::cancel('FRANKDLC_process_queue');
        wp_clear_scheduled_hook('FRANKDLC_process_queue'); // Also clear WP-Cron just in case

        return true;
    }

    /**
     * Force stop all scans and reset scan state
     *
     * Used when scan is stuck or in an inconsistent state.
     * This cancels ALL running/pending scans in the database,
     * clears all transients, and cancels all scheduled queue tasks.
     *
     * @return bool True on success
     */
    public function force_stop_scan()
    {
        global $wpdb;

        $table = FRANKDLC()->database->get_scans_table();

        // Cancel all running and pending scans in database
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table,
            array(
                'status' => 'cancelled',
                'completed_at' => current_time('mysql'),
                'error_message' => __('Force stopped by admin', 'frank-dead-link-checker'),
            ),
            array('status' => 'running'),
            array('%s', '%s', '%s'),
            array('%s')
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table,
            array(
                'status' => 'cancelled',
                'completed_at' => current_time('mysql'),
                'error_message' => __('Force stopped by admin', 'frank-dead-link-checker'),
            ),
            array('status' => 'pending'),
            array('%s', '%s', '%s'),
            array('%s')
        );

        // Clear all transients
        delete_transient('FRANKDLC_current_scan_id');
        delete_transient('FRANKDLC_scan_progress');
        delete_transient('FRANKDLC_stats_cache');

        // Clear all scheduled queue processing
        FRANKDLC_Queue_Manager::cancel('FRANKDLC_process_queue');
        FRANKDLC_Queue_Manager::cancel_group('blc');
        wp_clear_scheduled_hook('FRANKDLC_process_queue');

        return true;
    }

    /**
     * Cleanup stale/stuck scans
     *
     * Called periodically to detect scans that have been running
     * for longer than 30 minutes without progress, and auto-cancel them.
     */
    public function cleanup_stale_scans()
    {
        global $wpdb;

        $table = esc_sql(FRANKDLC()->database->get_scans_table());
        $stale_threshold = gmdate('Y-m-d H:i:s', strtotime('-30 minutes'));

        // Find scans that have been running for over 30 minutes
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $stale_scans = $wpdb->get_results($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT id FROM {$table} WHERE status IN ('running', 'pending') AND started_at < %s",
            $stale_threshold
        ));

        if (!empty($stale_scans)) {
            foreach ($stale_scans as $scan) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update(
                    $table,
                    array(
                        'status' => 'failed',
                        'completed_at' => current_time('mysql'),
                        'error_message' => __('Scan timed out (exceeded 30 minutes)', 'frank-dead-link-checker'),
                    ),
                    array('id' => $scan->id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
            }

            // Clear transients
            delete_transient('FRANKDLC_current_scan_id');
            delete_transient('FRANKDLC_scan_progress');

            // Clear queue
            FRANKDLC_Queue_Manager::cancel('FRANKDLC_process_queue');
            wp_clear_scheduled_hook('FRANKDLC_process_queue');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Only used when WP_DEBUG enabled
                error_log(sprintf('[BLC] Auto-cancelled %d stale scan(s)', count($stale_scans)));
            }
        }
    }

    public function get_progress()
    {
        $progress = get_transient('FRANKDLC_scan_progress');

        if (!$progress) {
            $scan = FRANKDLC()->database->get_running_scan();
            if ($scan) {
                // Check if scan is stale (running for over 30 minutes)
                $started_at = strtotime($scan->started_at);
                if ($started_at && (time() - $started_at) > 1800) {
                    // Auto-recover: mark as failed and return idle
                    $this->cleanup_stale_scans();
                    return array('status' => 'idle', 'percent' => 0);
                }

                $progress = array(
                    'scan_id' => $scan->id,
                    'status' => $scan->status,
                    'total' => (int) $scan->total_links,
                    'checked' => (int) $scan->checked_links,
                    'broken' => (int) $scan->broken_links,
                    'warnings' => (int) $scan->warning_links,
                    'percent' => $scan->total_links > 0 ? round(($scan->checked_links / $scan->total_links) * 100) : 0,
                );
            } else {
                $progress = array('status' => 'idle', 'percent' => 0);
            }
        }

        return $progress;
    }

    public function run_scheduled_scan()
    {
        $settings = get_option('FRANKDLC_settings', array());
        if (isset($settings['scan_frequency']) && $settings['scan_frequency'] === 'manual') {
            return;
        }
        $this->start_scan('scheduled');
    }

    /**
     * Recheck only broken and warning links
     * 
     * This is a lightweight scheduled task that runs more frequently
     * than the full scan. It only rechecks links that are already
     * marked as broken or warning to see if they've been fixed.
     */
    public function recheck_broken_links()
    {
        global $wpdb;

        // Don't run if a full scan is in progress
        if (FRANKDLC()->database->is_scan_running()) {
            return;
        }

        $table = esc_sql(FRANKDLC()->database->get_links_table());

        // Get broken and warning links that haven't been checked in the last 6 hours
        $stale_threshold = gmdate('Y-m-d H:i:s', strtotime('-6 hours'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $links = $wpdb->get_results($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT * FROM {$table} 
             WHERE (is_broken = 1 OR is_warning = 1) 
             AND is_dismissed = 0 
             AND (last_check IS NULL OR last_check < %s)
             ORDER BY last_check ASC
             LIMIT 50",
            $stale_threshold
        ));

        if (empty($links)) {
            return;
        }

        $settings = get_option('FRANKDLC_settings', array());
        $delay = isset($settings['delay_between']) ? (int) $settings['delay_between'] : 500;

        foreach ($links as $link) {
            $result = $this->checker->check_url($link->url);

            FRANKDLC()->database->update_link_result($link->id, $result);

            // Small delay between requests
            if ($delay > 0) {
                usleep($delay * 1000);
            }
        }

        // Clear stats cache so dashboard shows updated counts
        FRANKDLC()->database->clear_stats_cache();

        // Log the recheck for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Only used when WP_DEBUG enabled
            error_log(sprintf(
                '[BLC] Auto-recheck completed: %d links rechecked',
                count($links)
            ));
        }
    }
}
