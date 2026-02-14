<?php
/**
 * Plugin Activator
 *
 * Handles all activation tasks including database table creation
 * and default settings initialization.
 *
 * @package BrokenLinkChecker
 * @since 1.0.0
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Class FRANKDLC_Activator
 *
 * Fired during plugin activation
 */
class FRANKDLC_Activator
{

    /**
     * Database version for migrations
     *
     * @var string
     */
    const DB_VERSION = '1.0.0';

    /**
     * Activate the plugin
     *
     * Creates database tables, sets default options,
     * and schedules cron events.
     */
    public static function activate()
    {
        self::check_requirements();
        self::create_tables();
        self::set_default_options();
        self::schedule_events();
        self::set_activation_flag();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Check system requirements
     *
     * @throws Exception If requirements are not met
     */
    private static function check_requirements()
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(FRANKDLC_PLUGIN_FILE));
            wp_die(
                esc_html__('Frank Dead Link Checker requires PHP 7.4 or higher.', 'frank-dead-link-checker'),
                esc_html__('Plugin Activation Error', 'frank-dead-link-checker'),
                array('back_link' => true)
            );
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '5.8', '<')) {
            deactivate_plugins(plugin_basename(FRANKDLC_PLUGIN_FILE));
            wp_die(
                esc_html__('Frank Dead Link Checker requires WordPress 5.8 or higher.', 'frank-dead-link-checker'),
                esc_html__('Plugin Activation Error', 'frank-dead-link-checker'),
                array('back_link' => true)
            );
        }
    }

    /**
     * Create custom database tables
     */
    private static function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Links table - stores all discovered links
        $table_links = $wpdb->prefix . 'FRANKDLC_links';
        $sql_links = "CREATE TABLE {$table_links} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            url varchar(2048) NOT NULL,
            url_hash char(32) NOT NULL,
            status_code smallint(6) DEFAULT NULL,
            status_text varchar(100) DEFAULT NULL,
            link_type varchar(20) NOT NULL DEFAULT 'internal',
            source_id bigint(20) UNSIGNED NOT NULL,
            source_type varchar(50) NOT NULL DEFAULT 'post',
            source_field varchar(50) NOT NULL DEFAULT 'post_content',
            anchor_text varchar(500) DEFAULT NULL,
            is_broken tinyint(1) NOT NULL DEFAULT 0,
            is_warning tinyint(1) NOT NULL DEFAULT 0,
            is_dismissed tinyint(1) NOT NULL DEFAULT 0,
            redirect_url varchar(2048) DEFAULT NULL,
            redirect_count tinyint(3) UNSIGNED DEFAULT 0,
            response_time float DEFAULT NULL,
            last_check datetime DEFAULT NULL,
            first_detected datetime NOT NULL,
            check_count int(11) UNSIGNED NOT NULL DEFAULT 0,
            error_message varchar(500) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY url_source (url_hash, source_id, source_type, source_field),
            KEY status_code (status_code),
            KEY is_broken (is_broken),
            KEY is_warning (is_warning),
            KEY source_id (source_id),
            KEY link_type (link_type),
            KEY last_check (last_check)
        ) {$charset_collate};";

        // Scans table - stores scan history
        $table_scans = $wpdb->prefix . 'FRANKDLC_scans';
        $sql_scans = "CREATE TABLE {$table_scans} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            scan_type varchar(20) NOT NULL DEFAULT 'full',
            status varchar(20) NOT NULL DEFAULT 'pending',
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            total_links int(11) UNSIGNED NOT NULL DEFAULT 0,
            checked_links int(11) UNSIGNED NOT NULL DEFAULT 0,
            broken_links int(11) UNSIGNED NOT NULL DEFAULT 0,
            warning_links int(11) UNSIGNED NOT NULL DEFAULT 0,
            error_message text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY started_at (started_at)
        ) {$charset_collate};";

        // Redirects table - stores URL redirects
        $table_redirects = $wpdb->prefix . 'FRANKDLC_redirects';
        $sql_redirects = "CREATE TABLE {$table_redirects} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source_url varchar(2048) NOT NULL,
            source_url_hash char(32) NOT NULL,
            target_url varchar(2048) NOT NULL,
            redirect_type smallint(3) NOT NULL DEFAULT 301,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            hit_count bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            last_hit datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            created_from_link_id bigint(20) UNSIGNED DEFAULT NULL,
            notes text DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY source_url_hash (source_url_hash),
            KEY is_active (is_active),
            KEY redirect_type (redirect_type)
        ) {$charset_collate};";

        // Include WordPress upgrade functions
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Create/update tables
        dbDelta($sql_links);
        dbDelta($sql_scans);
        dbDelta($sql_redirects);

        // Store database version
        update_option('FRANKDLC_db_version', self::DB_VERSION);
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options()
    {
        $defaults = array(
            // General settings
            'scan_frequency' => 'daily',
            'links_per_page' => 20,
            'timeout' => 30,
            'max_redirects' => 5,

            // Scan scope
            'scan_posts' => true,
            'scan_pages' => true,
            'scan_custom_types' => array(),
            'scan_comments' => false,
            'scan_widgets' => true,
            'scan_menus' => true,

            // Link types
            'check_internal' => true,
            'check_external' => true,
            'check_images' => true,
            'check_youtube' => true,
            'check_iframes' => false,

            // Exclusions
            'excluded_domains' => array(),
            'excluded_patterns' => array(),

            // Advanced
            'concurrent_requests' => 3,
            'delay_between' => 500, // milliseconds
            'user_agent' => 'Mozilla/5.0 (compatible; BrokenLinkChecker/' . FRANKDLC_VERSION . '; +https://wordpress.org/)',
            'verify_ssl' => true,
            'mark_broken_after' => 3, // consecutive failures

            // Notifications
            'email_notifications' => true,
            'email_frequency' => 'weekly',
            'email_recipients' => array(get_option('admin_email')),
            'notify_threshold' => 1,
        );

        // Only set if not already exists
        if (!get_option('FRANKDLC_settings')) {
            add_option('FRANKDLC_settings', $defaults);
        }
    }

    /**
     * Schedule cron events
     * Note: First scan must be triggered manually
     */
    private static function schedule_events()
    {
        // Schedule automatic scanning - start TOMORROW, not immediately
        // First scan should always be manual
        if (!wp_next_scheduled('FRANKDLC_scheduled_scan')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'FRANKDLC_scheduled_scan');
        }

        // Schedule auto-recheck of broken/warning links (lightweight daily check)
        if (!wp_next_scheduled('FRANKDLC_recheck_broken')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS * 12, 'twicedaily', 'FRANKDLC_recheck_broken');
        }

        // Schedule notification digest - start next week
        if (!wp_next_scheduled('FRANKDLC_send_digest')) {
            wp_schedule_event(time() + WEEK_IN_SECONDS, 'weekly', 'FRANKDLC_send_digest');
        }

        // Schedule cleanup of old data - start next week
        if (!wp_next_scheduled('FRANKDLC_cleanup_old_data')) {
            wp_schedule_event(time() + WEEK_IN_SECONDS, 'weekly', 'FRANKDLC_cleanup_old_data');
        }
    }

    /**
     * Set activation flag for redirect
     */
    private static function set_activation_flag()
    {
        set_transient('FRANKDLC_activation_redirect', true, 30);
    }
}
