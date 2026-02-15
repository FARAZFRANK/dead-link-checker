<?php
/**
 * Multisite Support
 *
 * Handles network-wide functionality for WordPress multisite installations.
 *
 * @package BrokenLinkChecker
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class FRANKDLC_Multisite
{
    /**
     * Constructor
     */
    public function __construct()
    {
        if (!is_multisite()) {
            return;
        }

        // Add network admin menu
        add_action('network_admin_menu', array($this, 'add_network_menu'));

        // Enqueue network admin styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_network_styles'));
    }

    /**
     * Add network admin menu
     */
    public function add_network_menu()
    {
        add_menu_page(
            __('Link Checker Network', 'frank-dead-link-checker'),
            __('Link Checker', 'frank-dead-link-checker'),
            'manage_network',
            'frankdlc-network',
            array($this, 'render_network_dashboard'),
            'dashicons-admin-links',
            100
        );
    }

    /**
     * Enqueue network admin styles
     */
    public function enqueue_network_styles($hook)
    {
        if ($hook !== 'toplevel_page_blc-network') {
            return;
        }

        wp_enqueue_style(
            'frankdlc-admin',
            FRANKDLC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FRANKDLC_VERSION
        );
    }

    /**
     * Render network dashboard
     */
    public function render_network_dashboard()
    {
        $sites = $this->get_network_stats();
        ?>
        <div class="wrap frankdlc-wrap blc-network-dashboard">
            <h1>
                <?php esc_html_e('Frank Dead Link Checker - Network Overview', 'frank-dead-link-checker'); ?>
            </h1>

            <!-- Network Summary -->
            <div class="frankdlc-network-summary">
                <div class="frankdlc-network-stat blc-network-stat-total">
                    <span class="frankdlc-stat-number">
                        <?php echo esc_html($sites['total_links']); ?>
                    </span>
                    <span class="frankdlc-stat-label">
                        <?php esc_html_e('Total Links', 'frank-dead-link-checker'); ?>
                    </span>
                </div>
                <div class="frankdlc-network-stat blc-network-stat-broken">
                    <span class="frankdlc-stat-number">
                        <?php echo esc_html($sites['total_broken']); ?>
                    </span>
                    <span class="frankdlc-stat-label">
                        <?php esc_html_e('Broken Links', 'frank-dead-link-checker'); ?>
                    </span>
                </div>
                <div class="frankdlc-network-stat blc-network-stat-sites">
                    <span class="frankdlc-stat-number">
                        <?php echo esc_html(count($sites['sites'])); ?>
                    </span>
                    <span class="frankdlc-stat-label">
                        <?php esc_html_e('Sites', 'frank-dead-link-checker'); ?>
                    </span>
                </div>
            </div>

            <!-- Sites Table -->
            <div class="frankdlc-network-sites">
                <h2>
                    <?php esc_html_e('Sites Overview', 'frank-dead-link-checker'); ?>
                </h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>
                                <?php esc_html_e('Site', 'frank-dead-link-checker'); ?>
                            </th>
                            <th>
                                <?php esc_html_e('URL', 'frank-dead-link-checker'); ?>
                            </th>
                            <th>
                                <?php esc_html_e('Total Links', 'frank-dead-link-checker'); ?>
                            </th>
                            <th>
                                <?php esc_html_e('Broken', 'frank-dead-link-checker'); ?>
                            </th>
                            <th>
                                <?php esc_html_e('Warnings', 'frank-dead-link-checker'); ?>
                            </th>
                            <th>
                                <?php esc_html_e('Last Scan', 'frank-dead-link-checker'); ?>
                            </th>
                            <th>
                                <?php esc_html_e('Actions', 'frank-dead-link-checker'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sites['sites'])): ?>
                            <tr>
                                <td colspan="7">
                                    <?php esc_html_e('No sites found.', 'frank-dead-link-checker'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sites['sites'] as $site): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?php echo esc_html($site['name']); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url($site['url']); ?>" target="_blank">
                                            <?php echo esc_html($site['url']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php echo esc_html($site['total']); ?>
                                    </td>
                                    <td>
                                        <?php if ($site['broken'] > 0): ?>
                                            <span class="frankdlc-badge blc-badge-error">
                                                <?php echo esc_html($site['broken']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="frankdlc-badge blc-badge-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($site['warnings'] > 0): ?>
                                            <span class="frankdlc-badge blc-badge-warning">
                                                <?php echo esc_html($site['warnings']); ?>
                                            </span>
                                        <?php else: ?>
                                            0
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($site['last_scan']): ?>
                                            <?php echo esc_html(human_time_diff(strtotime($site['last_scan']), current_time('timestamp'))); ?>
                                            <?php esc_html_e('ago', 'frank-dead-link-checker'); ?>
                                        <?php else: ?>
                                            <?php esc_html_e('Never', 'frank-dead-link-checker'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url($site['admin_url']); ?>" class="button button-small">
                                            <?php esc_html_e('View Dashboard', 'frank-dead-link-checker'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Get network-wide statistics
     *
     * @return array Network stats
     */
    private function get_network_stats()
    {
        $stats = array(
            'total_links' => 0,
            'total_broken' => 0,
            'sites' => array(),
        );

        $sites = get_sites(array('number' => 0));

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            global $wpdb;
            $table_name = $wpdb->prefix . 'FRANKDLC_links';

            // Check if table exists
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
                restore_current_blog();
                continue;
            }

            $site_stats = array(
                'id' => $site->blog_id,
                'name' => get_bloginfo('name'),
                'url' => get_bloginfo('url'),
                'admin_url' => admin_url('admin.php?page=frank-dead-link-checker'),
                'total' => 0,
                'broken' => 0,
                'warnings' => 0,
                'last_scan' => null,
            );

            // Get link counts
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $site_stats['total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $site_stats['broken'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE is_broken = 1 AND is_dismissed = 0" );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $site_stats['warnings'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status_code BETWEEN 300 AND 399 AND is_dismissed = 0" );

            // Get last scan
            $scans_table = $wpdb->prefix . 'FRANKDLC_scans';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $scans_table ) ) === $scans_table ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $site_stats['last_scan'] = $wpdb->get_var( "SELECT end_time FROM {$scans_table} WHERE status = 'completed' ORDER BY id DESC LIMIT 1" );
            }

            $stats['total_links'] += $site_stats['total'];
            $stats['total_broken'] += $site_stats['broken'];
            $stats['sites'][] = $site_stats;

            restore_current_blog();
        }

        return $stats;
    }
}
