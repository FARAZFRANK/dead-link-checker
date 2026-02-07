<?php
/**
 * Database Handler
 *
 * Handles all database operations for the plugin.
 *
 * @package BrokenLinkChecker
 * @since 1.0.0
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Class BLC_Database
 *
 * Database abstraction layer for links and scans
 */
class BLC_Database
{

    /**
     * Links table name
     *
     * @var string
     */
    private $table_links;

    /**
     * Scans table name
     *
     * @var string
     */
    private $table_scans;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_links = $wpdb->prefix . 'blc_links';
        $this->table_scans = $wpdb->prefix . 'blc_scans';
    }

    /**
     * Get links table name
     *
     * @return string
     */
    public function get_links_table()
    {
        return $this->table_links;
    }

    /**
     * Get scans table name
     *
     * @return string
     */
    public function get_scans_table()
    {
        return $this->table_scans;
    }

    // =========================================================================
    // LINK OPERATIONS
    // =========================================================================

    /**
     * Insert or update a link
     *
     * @param array $data Link data
     * @return int|false Link ID on success, false on failure
     */
    public function save_link($data)
    {
        global $wpdb;

        // Generate URL hash for indexing
        $data['url_hash'] = md5($data['url']);

        // Check if link already exists
        $existing = $this->get_link_by_url_and_source(
            $data['url'],
            $data['source_id'],
            $data['source_type'],
            $data['source_field'] ?? 'post_content'
        );

        if ($existing) {
            // Update existing link
            $update_data = array(
                'anchor_text' => $data['anchor_text'] ?? $existing->anchor_text,
                'last_check' => current_time('mysql'),
            );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, data changes frequently
            $wpdb->update(
                $this->table_links,
                $update_data,
                array('id' => $existing->id),
                array('%s', '%s'),
                array('%d')
            );

            return $existing->id;
        }

        // Insert new link
        $insert_data = array(
            'url' => $data['url'],
            'url_hash' => $data['url_hash'],
            'link_type' => $data['link_type'] ?? 'internal',
            'source_id' => $data['source_id'],
            'source_type' => $data['source_type'] ?? 'post',
            'source_field' => $data['source_field'] ?? 'post_content',
            'anchor_text' => $data['anchor_text'] ?? null,
            'first_detected' => current_time('mysql'),
            'is_broken' => 0,
            'is_warning' => 0,
            'is_dismissed' => 0,
            'check_count' => 0,
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, no WP function available
        $wpdb->insert($this->table_links, $insert_data);

        return $wpdb->insert_id ?: false;
    }

    /**
     * Update link check result
     *
     * @param int   $link_id Link ID
     * @param array $result  Check result data
     * @return bool
     */
    public function update_link_result($link_id, $result)
    {
        global $wpdb;

        $data = array(
            'status_code' => $result['status_code'] ?? null,
            'status_text' => $result['status_text'] ?? null,
            'is_broken' => $result['is_broken'] ?? 0,
            'is_warning' => $result['is_warning'] ?? 0,
            'redirect_url' => $result['redirect_url'] ?? null,
            'redirect_count' => $result['redirect_count'] ?? 0,
            'response_time' => $result['response_time'] ?? null,
            'last_check' => current_time('mysql'),
            'error_message' => $result['error_message'] ?? null,
        );

        // Increment check count
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_links} SET check_count = check_count + 1 WHERE id = %d",
                $link_id
            )
        );
        // phpcs:enable

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, data changes frequently
        return (bool) $wpdb->update(
            $this->table_links,
            $data,
            array('id' => $link_id)
        );
    }

    /**
     * Get link by ID
     *
     * @param int $link_id Link ID
     * @return object|null
     */
    public function get_link($link_id)
    {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_links} WHERE id = %d",
                $link_id
            )
        );
        // phpcs:enable
    }

    /**
     * Get link by URL and source
     *
     * @param string $url          Link URL
     * @param int    $source_id    Source post ID
     * @param string $source_type  Source type
     * @param string $source_field Source field
     * @return object|null
     */
    public function get_link_by_url_and_source($url, $source_id, $source_type, $source_field)
    {
        global $wpdb;

        $url_hash = md5($url);

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_links} 
                WHERE url_hash = %s 
                AND source_id = %d 
                AND source_type = %s 
                AND source_field = %s",
                $url_hash,
                $source_id,
                $source_type,
                $source_field
            )
        );
        // phpcs:enable
    }

    /**
     * Get links with filters
     *
     * @param array $args Query arguments
     * @return array
     */
    public function get_links($args = array())
    {
        global $wpdb;

        $defaults = array(
            'status' => 'all', // all, broken, warning, working, dismissed
            'link_type' => 'all', // all, internal, external, image
            'source_type' => 'all', // all, post, page, menu, widget
            'search' => '',
            'orderby' => 'last_check',
            'order' => 'DESC',
            'per_page' => 20,
            'page' => 1,
            // New filters
            'date_from' => '',
            'date_to' => '',
            'response_time_min' => '',
            'response_time_max' => '',
            'http_status' => '', // e.g., 404, 500
        );

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where = array('1=1');
        $values = array();

        // Status filter
        switch ($args['status']) {
            case 'broken':
                $where[] = 'is_broken = 1 AND is_dismissed = 0';
                break;
            case 'warning':
                $where[] = 'is_warning = 1 AND is_broken = 0 AND is_dismissed = 0';
                break;
            case 'working':
                $where[] = 'is_broken = 0 AND is_warning = 0 AND is_dismissed = 0';
                break;
            case 'dismissed':
                $where[] = 'is_dismissed = 1';
                break;
        }

        // Link type filter
        if ($args['link_type'] !== 'all') {
            $where[] = 'link_type = %s';
            $values[] = $args['link_type'];
        }

        // Source type filter
        if ($args['source_type'] !== 'all') {
            $where[] = 'source_type = %s';
            $values[] = $args['source_type'];
        }

        // Search filter
        if (!empty($args['search'])) {
            $where[] = '(url LIKE %s OR anchor_text LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
        }

        // Date range filter
        if (!empty($args['date_from'])) {
            $where[] = 'last_check >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }
        if (!empty($args['date_to'])) {
            $where[] = 'last_check <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }

        // Response time filter
        if ($args['response_time_min'] !== '' && is_numeric($args['response_time_min'])) {
            $where[] = 'response_time >= %d';
            $values[] = intval($args['response_time_min']);
        }
        if ($args['response_time_max'] !== '' && is_numeric($args['response_time_max'])) {
            $where[] = 'response_time <= %d';
            $values[] = intval($args['response_time_max']);
        }

        // HTTP status code filter
        if (!empty($args['http_status'])) {
            $where[] = 'status_code = %d';
            $values[] = intval($args['http_status']);
        }

        // Build query
        $where_clause = implode(' AND ', $where);

        // Validate orderby - expanded list
        $allowed_orderby = array(
            'id',
            'url',
            'status_code',
            'last_check',
            'first_detected',
            'response_time',
            'redirect_count',
            'source_type',
            'link_type'
        );
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'last_check';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Calculate offset
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Build final query
        $sql = "SELECT * FROM {$this->table_links} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $values[] = $args['per_page'];
        $values[] = $offset;

        if (!empty($values)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is prepared with wpdb->prepare above
            $sql = $wpdb->prepare($sql, $values);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Using custom table, SQL is prepared, variables are from trusted sources
        return $wpdb->get_results($sql);
    }

    /**
     * Get total link count with filters
     *
     * @param array $args Query arguments (same as get_links)
     * @return int
     */
    public function get_links_count($args = array())
    {
        global $wpdb;

        $defaults = array(
            'status' => 'all',
            'link_type' => 'all',
            'source_type' => 'all',
            'search' => '',
        );

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause (same as get_links)
        $where = array('1=1');
        $values = array();

        switch ($args['status']) {
            case 'broken':
                $where[] = 'is_broken = 1 AND is_dismissed = 0';
                break;
            case 'warning':
                $where[] = 'is_warning = 1 AND is_broken = 0 AND is_dismissed = 0';
                break;
            case 'working':
                $where[] = 'is_broken = 0 AND is_warning = 0 AND is_dismissed = 0';
                break;
            case 'dismissed':
                $where[] = 'is_dismissed = 1';
                break;
        }

        if ($args['link_type'] !== 'all') {
            $where[] = 'link_type = %s';
            $values[] = $args['link_type'];
        }

        if ($args['source_type'] !== 'all') {
            $where[] = 'source_type = %s';
            $values[] = $args['source_type'];
        }

        if (!empty($args['search'])) {
            $where[] = '(url LIKE %s OR anchor_text LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM {$this->table_links} WHERE {$where_clause}";

        if (!empty($values)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is prepared with wpdb->prepare above
            $sql = $wpdb->prepare($sql, $values);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Using custom table, SQL is prepared, variables are from trusted sources
        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get link statistics
     *
     * @return array
     */
    public function get_stats()
    {
        global $wpdb;

        $cache_key = 'blc_stats_cache';
        $stats = get_transient($cache_key);

        if ($stats === false) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, results are cached via transient
            $stats = array(
                'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_links} WHERE is_dismissed = 0"),
                'broken' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_links} WHERE is_broken = 1 AND is_dismissed = 0"),
                'warning' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_links} WHERE is_warning = 1 AND is_broken = 0 AND is_dismissed = 0"),
                'working' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_links} WHERE is_broken = 0 AND is_warning = 0 AND is_dismissed = 0"),
                'dismissed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_links} WHERE is_dismissed = 1"),
                'internal' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_links} WHERE link_type = 'internal' AND is_dismissed = 0"),
                'external' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_links} WHERE link_type = 'external' AND is_dismissed = 0"),
            );
            // phpcs:enable

            set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);
        }

        return $stats;
    }

    /**
     * Clear stats cache
     */
    public function clear_stats_cache()
    {
        delete_transient('blc_stats_cache');
    }

    /**
     * Dismiss a link
     *
     * @param int $link_id Link ID
     * @return bool
     */
    public function dismiss_link($link_id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, data changes frequently
        $result = $wpdb->update(
            $this->table_links,
            array('is_dismissed' => 1),
            array('id' => $link_id),
            array('%d'),
            array('%d')
        );

        $this->clear_stats_cache();

        return (bool) $result;
    }

    /**
     * Undismiss a link
     *
     * @param int $link_id Link ID
     * @return bool
     */
    public function undismiss_link($link_id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, data changes frequently
        $result = $wpdb->update(
            $this->table_links,
            array('is_dismissed' => 0),
            array('id' => $link_id),
            array('%d'),
            array('%d')
        );

        $this->clear_stats_cache();

        return (bool) $result;
    }

    /**
     * Delete a link
     *
     * @param int $link_id Link ID
     * @return bool
     */
    public function delete_link($link_id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, data changes frequently
        $result = $wpdb->delete(
            $this->table_links,
            array('id' => $link_id),
            array('%d')
        );

        $this->clear_stats_cache();

        return (bool) $result;
    }

    /**
     * Delete links by source
     *
     * @param int    $source_id   Source ID
     * @param string $source_type Source type
     * @return int Number of deleted rows
     */
    public function delete_links_by_source($source_id, $source_type = 'post')
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, bulk delete operation
        $result = $wpdb->delete(
            $this->table_links,
            array(
                'source_id' => $source_id,
                'source_type' => $source_type,
            ),
            array('%d', '%s')
        );

        $this->clear_stats_cache();

        return $result;
    }

    /**
     * Get links that need checking
     *
     * @param int $limit Maximum number of links to return
     * @return array
     */
    public function get_links_to_check($limit = 50)
    {
        global $wpdb;

        $stale_threshold = gmdate('Y-m-d H:i:s', strtotime('-1 day'));

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_links} 
                WHERE is_dismissed = 0 
                AND (last_check IS NULL OR last_check < %s)
                ORDER BY last_check ASC, first_detected ASC
                LIMIT %d",
                $stale_threshold,
                $limit
            )
        );
        // phpcs:enable
    }

    // =========================================================================
    // SCAN OPERATIONS
    // =========================================================================

    /**
     * Create a new scan record
     *
     * @param string $scan_type Scan type (full, partial)
     * @return int|false Scan ID on success
     */
    public function create_scan($scan_type = 'full')
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, no WP function available
        $wpdb->insert(
            $this->table_scans,
            array(
                'scan_type' => $scan_type,
                'status' => 'pending',
                'started_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s')
        );

        return $wpdb->insert_id ?: false;
    }

    /**
     * Update scan progress
     *
     * @param int   $scan_id Scan ID
     * @param array $data    Update data
     * @return bool
     */
    public function update_scan($scan_id, $data)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, data changes frequently
        return (bool) $wpdb->update(
            $this->table_scans,
            $data,
            array('id' => $scan_id)
        );
    }

    /**
     * Complete a scan
     *
     * @param int   $scan_id Scan ID
     * @param array $results Scan results
     * @return bool
     */
    public function complete_scan($scan_id, $results = array())
    {
        global $wpdb;

        $data = array(
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
            'total_links' => $results['total_links'] ?? 0,
            'checked_links' => $results['checked_links'] ?? 0,
            'broken_links' => $results['broken_links'] ?? 0,
            'warning_links' => $results['warning_links'] ?? 0,
        );

        $this->clear_stats_cache();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, data changes frequently
        return (bool) $wpdb->update(
            $this->table_scans,
            $data,
            array('id' => $scan_id)
        );
    }

    /**
     * Get latest scan
     *
     * @return object|null
     */
    public function get_latest_scan()
    {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_row(
            "SELECT * FROM {$this->table_scans} ORDER BY id DESC LIMIT 1"
        );
        // phpcs:enable
    }

    /**
     * Get scan history
     * FREE VERSION: Limited to 5 scans
     *
     * @param int $limit Number of records (max 5 in Free)
     * @return array
     */
    public function get_scan_history($limit = 5)
    {
        global $wpdb;

        // FREE: Limit to 5 scan history records
        $limit = min(5, absint($limit));

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_scans} ORDER BY id DESC LIMIT %d",
                $limit
            )
        );
        // phpcs:enable
    }

    /**
     * Check if a scan is running
     *
     * @return bool
     */
    public function is_scan_running()
    {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $running = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_scans} WHERE status IN ('pending', 'running')"
        );
        // phpcs:enable

        return $running > 0;
    }

    /**
     * Get running scan
     *
     * @return object|null
     */
    public function get_running_scan()
    {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_row(
            "SELECT * FROM {$this->table_scans} WHERE status IN ('pending', 'running') ORDER BY id DESC LIMIT 1"
        );
        // phpcs:enable
    }

    /**
     * Clear all links and scan data
     *
     * Used for Fresh Scan feature to start with a clean slate.
     *
     * @return bool True on success, false on failure
     */
    public function clear_all_data()
    {
        global $wpdb;

        // Delete all links
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, TRUNCATE for performance
        $links_result = $wpdb->query("TRUNCATE TABLE {$this->table_links}");

        // Delete all scans
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, TRUNCATE for performance
        $scans_result = $wpdb->query("TRUNCATE TABLE {$this->table_scans}");

        // Clear any transients
        delete_transient('blc_current_scan_id');
        delete_transient('blc_scan_progress');

        return ($links_result !== false && $scans_result !== false);
    }
}
