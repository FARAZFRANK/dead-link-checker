<?php
/**
 * Admin Controller
 *
 * Main admin class that handles menu registration, asset loading, and AJAX handlers.
 *
 * @package BrokenLinkChecker
 * @since 1.0.0
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Class BLC_Admin
 */
class BLC_Admin
{

    /** @var BLC_Dashboard */
    public $dashboard;

    /** @var BLC_Settings */
    public $settings;

    public function __construct()
    {
        $this->dashboard = new BLC_Dashboard();
        $this->settings = new BLC_Settings();
        $this->init_hooks();
    }

    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_blc_start_scan', array($this, 'ajax_start_scan'));
        add_action('wp_ajax_blc_stop_scan', array($this, 'ajax_stop_scan'));
        add_action('wp_ajax_blc_get_scan_progress', array($this, 'ajax_get_scan_progress'));
        add_action('wp_ajax_blc_dismiss_link', array($this, 'ajax_dismiss_link'));
        add_action('wp_ajax_blc_undismiss_link', array($this, 'ajax_undismiss_link'));
        add_action('wp_ajax_blc_recheck_link', array($this, 'ajax_recheck_link'));
        add_action('wp_ajax_blc_delete_link', array($this, 'ajax_delete_link'));
        add_action('wp_ajax_blc_edit_link', array($this, 'ajax_edit_link'));
        add_action('wp_ajax_blc_bulk_action', array($this, 'ajax_bulk_action'));
        add_action('wp_ajax_blc_export_links', array($this, 'ajax_export_links'));
        add_action('wp_ajax_blc_fresh_scan', array($this, 'ajax_fresh_scan'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('admin_init', array($this, 'activation_redirect'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            __('Dead Link Checker Pro', 'dead-link-checker'),
            __('Link Checker', 'dead-link-checker'),
            'manage_options',
            'dead-link-checker',
            array($this->dashboard, 'render_page'),
            'dashicons-admin-links',
            80
        );

        add_submenu_page('dead-link-checker', __('Dashboard', 'dead-link-checker'), __('Dashboard', 'dead-link-checker'), 'manage_options', 'dead-link-checker', array($this->dashboard, 'render_page'));
        add_submenu_page('dead-link-checker', __('Settings', 'dead-link-checker'), __('Settings', 'dead-link-checker'), 'manage_options', 'blc-settings', array($this->settings, 'render_page'));
        add_submenu_page('dead-link-checker', __('Scan History', 'dead-link-checker'), __('Scan History', 'dead-link-checker'), 'manage_options', 'blc-logs', array($this, 'render_logs_page'));
        add_submenu_page('dead-link-checker', __('Help', 'dead-link-checker'), __('Help', 'dead-link-checker'), 'manage_options', 'blc-help', array($this, 'render_help_page'));
    }

    public function enqueue_assets($hook)
    {
        if (strpos($hook, 'dead-link-checker') === false && strpos($hook, 'blc-') === false) {
            return;
        }

        wp_enqueue_style('blc-admin', BLC_PLUGIN_URL . 'assets/css/admin.css', array(), BLC_VERSION);
        wp_enqueue_script('blc-admin', BLC_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), BLC_VERSION, true);

        wp_localize_script('blc-admin', 'blcAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('blc_admin_nonce'),
            'strings' => array(
                'scanning' => __('Scanning...', 'dead-link-checker'),
                'scanComplete' => __('Scan complete!', 'dead-link-checker'),
                'scanFailed' => __('Scan failed.', 'dead-link-checker'),
                'scanStopped' => __('Scan stopped.', 'dead-link-checker'),
                'confirmDelete' => __('Are you sure?', 'dead-link-checker'),
                'confirmStop' => __('Are you sure you want to stop the scan?', 'dead-link-checker'),
                'confirmFreshScan' => __('This will DELETE all existing link data and scan history, then start a fresh scan. Are you sure?', 'dead-link-checker'),
                'freshScanStarted' => __('All data cleared. Fresh scan started.', 'dead-link-checker'),
                'processing' => __('Processing...', 'dead-link-checker'),
                'success' => __('Success!', 'dead-link-checker'),
                'error' => __('An error occurred.', 'dead-link-checker'),
                'exporting' => __('Exporting...', 'dead-link-checker'),
                'exportSuccess' => __('Export created successfully!', 'dead-link-checker'),
                'exportFailed' => __('Export failed.', 'dead-link-checker'),
            ),
        ));
    }

    public function ajax_start_scan()
    {
        check_ajax_referer('blc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        }
        $result = blc()->scanner->start_scan();
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success(array('message' => __('Scan started.', 'dead-link-checker'), 'scan_id' => $result));
    }

    public function ajax_stop_scan()
    {
        check_ajax_referer('blc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        }
        $result = blc()->scanner->stop_scan();
        if ($result) {
            wp_send_json_success(__('Scan stopped.', 'dead-link-checker'));
        } else {
            wp_send_json_error(__('No scan is running.', 'dead-link-checker'));
        }
    }

    public function ajax_get_scan_progress()
    {
        check_ajax_referer('blc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        }
        wp_send_json_success(blc()->scanner->get_progress());
    }

    public function ajax_dismiss_link()
    {
        check_ajax_referer('blc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        $link_id = absint($_POST['link_id'] ?? 0);
        if (!$link_id)
            wp_send_json_error(__('Invalid link ID.', 'dead-link-checker'));
        $result = blc()->database->dismiss_link($link_id);
        $result ? wp_send_json_success(__('Link dismissed.', 'dead-link-checker')) : wp_send_json_error(__('Failed.', 'dead-link-checker'));
    }

    public function ajax_undismiss_link()
    {
        check_ajax_referer('blc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        $link_id = absint($_POST['link_id'] ?? 0);
        if (!$link_id)
            wp_send_json_error(__('Invalid link ID.', 'dead-link-checker'));
        $result = blc()->database->undismiss_link($link_id);
        $result ? wp_send_json_success(__('Link restored.', 'dead-link-checker')) : wp_send_json_error(__('Failed.', 'dead-link-checker'));
    }

    public function ajax_recheck_link()
    {
        check_ajax_referer('blc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        $link_id = absint($_POST['link_id'] ?? 0);
        if (!$link_id)
            wp_send_json_error(__('Invalid link ID.', 'dead-link-checker'));
        $link = blc()->database->get_link($link_id);
        if (!$link)
            wp_send_json_error(__('Link not found.', 'dead-link-checker'));
        $checker = new BLC_Checker();
        $result = $checker->check_url($link->url);
        blc()->database->update_link_result($link_id, $result);
        wp_send_json_success(array('message' => __('Link rechecked.', 'dead-link-checker'), 'result' => $result));
    }

    public function ajax_delete_link()
    {
        check_ajax_referer('blc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        $link_id = absint($_POST['link_id'] ?? 0);
        if (!$link_id)
            wp_send_json_error(__('Invalid link ID.', 'dead-link-checker'));
        $result = blc()->database->delete_link($link_id);
        $result ? wp_send_json_success(__('Link deleted.', 'dead-link-checker')) : wp_send_json_error(__('Failed.', 'dead-link-checker'));
    }

    public function ajax_edit_link()
    {
        check_ajax_referer('blc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        $link_id = absint($_POST['link_id'] ?? 0);
        $new_url = esc_url_raw($_POST['new_url'] ?? '');
        if (!$link_id || !$new_url)
            wp_send_json_error(__('Invalid parameters.', 'dead-link-checker'));
        $link = blc()->database->get_link($link_id);
        if (!$link)
            wp_send_json_error(__('Link not found.', 'dead-link-checker'));
        if (in_array($link->source_type, array('post', 'page'), true)) {
            $post = get_post($link->source_id);
            if ($post) {
                $content = str_replace($link->url, $new_url, $post->post_content);
                wp_update_post(array('ID' => $post->ID, 'post_content' => $content));
                blc()->database->delete_link($link_id);
                wp_send_json_success(__('Link updated.', 'dead-link-checker'));
            }
        }
        wp_send_json_error(__('Could not update.', 'dead-link-checker'));
    }

    public function ajax_bulk_action()
    {
        check_ajax_referer('blc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        $action = sanitize_key($_POST['bulk_action'] ?? '');
        $link_ids = array_map('absint', (array) ($_POST['link_ids'] ?? array()));
        if (!$action || empty($link_ids))
            wp_send_json_error(__('Invalid parameters.', 'dead-link-checker'));
        $db = blc()->database;
        $count = 0;
        foreach ($link_ids as $id) {
            switch ($action) {
                case 'dismiss':
                    if ($db->dismiss_link($id))
                        $count++;
                    break;
                case 'undismiss':
                    if ($db->undismiss_link($id))
                        $count++;
                    break;
                case 'delete':
                    if ($db->delete_link($id))
                        $count++;
                    break;
                case 'recheck':
                    $link = $db->get_link($id);
                    if ($link) {
                        $checker = new BLC_Checker();
                        $db->update_link_result($id, $checker->check_url($link->url));
                        $count++;
                    }
                    break;
            }
        }
        wp_send_json_success(array('message' => sprintf(__('%d links processed.', 'dead-link-checker'), $count), 'count' => $count));
    }

    public function ajax_export_links()
    {
        check_ajax_referer('blc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        $format = sanitize_key($_POST['format'] ?? 'csv');
        $status = sanitize_key($_POST['status'] ?? 'all');
        $export = new BLC_Export();
        $result = $export->export($format, array('status' => $status));
        is_wp_error($result) ? wp_send_json_error($result->get_error_message()) : wp_send_json_success(array('download_url' => $result));
    }

    /**
     * AJAX handler for Fresh Scan
     *
     * Clears all existing link data and starts a new scan from scratch.
     */
    public function ajax_fresh_scan()
    {
        check_ajax_referer('blc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        }

        // Clear all existing data
        $cleared = blc()->database->clear_all_data();

        if (!$cleared) {
            wp_send_json_error(__('Failed to clear existing data.', 'dead-link-checker'));
        }

        // Start a new scan
        $result = blc()->scanner->start_scan('full');

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'message' => __('All data cleared. Fresh scan started.', 'dead-link-checker'),
            'scan_id' => $result,
        ));
    }

    public function admin_notices()
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'dead-link-checker') === false)
            return;
        $stats = blc()->database->get_stats();
        if ($stats['broken'] > 0) {
            printf('<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>', sprintf(esc_html__('%d broken links detected!', 'dead-link-checker'), $stats['broken']), esc_html__('Review and fix them.', 'dead-link-checker'));
        }
    }

    public function activation_redirect()
    {
        if (get_transient('blc_activation_redirect')) {
            delete_transient('blc_activation_redirect');
            if (!isset($_GET['activate-multi'])) {
                wp_safe_redirect(admin_url('admin.php?page=dead-link-checker'));
                exit;
            }
        }
    }

    /**
     * Add help tab to plugin pages
     */
    public function add_help_tab()
    {
        $screen = get_current_screen();

        if (!$screen || strpos($screen->id, 'dead-link-checker') === false) {
            return;
        }

        // Overview Tab
        $screen->add_help_tab(array(
            'id' => 'blc_overview',
            'title' => __('Overview', 'dead-link-checker'),
            'content' => '<h3>' . __('Dead Link Checker Pro', 'dead-link-checker') . '</h3>' .
                '<p>' . __('This plugin scans your website for broken links and helps you fix them quickly. It checks all links in your posts, pages, comments, and custom fields.', 'dead-link-checker') . '</p>' .
                '<h4>' . __('Features:', 'dead-link-checker') . '</h4>' .
                '<ul>' .
                '<li>' . __('<strong>Link Scanning</strong> - Automatically discover and check all links on your site.', 'dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Page Builder Support</strong> - Scan links in Elementor, Divi, WPBakery, and Gutenberg.', 'dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Email Notifications</strong> - Get notified when broken links are found.', 'dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Redirect Manager</strong> - Create 301/302/307 redirects for broken URLs.', 'dead-link-checker') . '</li>' .
                '</ul>',
        ));

        // How to Use Tab
        $screen->add_help_tab(array(
            'id' => 'blc_howto',
            'title' => __('How to Use', 'dead-link-checker'),
            'content' => '<h3>' . __('Getting Started', 'dead-link-checker') . '</h3>' .
                '<ol>' .
                '<li>' . __('<strong>Start a Scan</strong> - Click the "Scan Now" button to scan your entire website for broken links.', 'dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Review Results</strong> - View broken, warning, and working links in the dashboard table.', 'dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Fix Links</strong> - Click "Edit" to update a broken link directly, or use bulk actions.', 'dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Dismiss Links</strong> - Ignore false positives by clicking "Dismiss".', 'dead-link-checker') . '</li>' .
                '</ol>' .
                '<h4>' . __('Tips:', 'dead-link-checker') . '</h4>' .
                '<ul>' .
                '<li>' . __('Use "Fresh Scan" to clear all data and start from scratch.', 'dead-link-checker') . '</li>' .
                '<li>' . __('Use filters to narrow down results by status, type, or date.', 'dead-link-checker') . '</li>' .
                '<li>' . __('Export your link data for external analysis.', 'dead-link-checker') . '</li>' .
                '</ul>',
        ));

        // Link Status Tab
        $screen->add_help_tab(array(
            'id' => 'blc_status',
            'title' => __('Link Status', 'dead-link-checker'),
            'content' => '<h3>' . __('Understanding Link Status', 'dead-link-checker') . '</h3>' .
                '<table class="widefat">' .
                '<tr><td><span style="color:#dc3545;">●</span> <strong>' . __('Broken', 'dead-link-checker') . '</strong></td><td>' . __('HTTP 4xx/5xx errors - Link is not working.', 'dead-link-checker') . '</td></tr>' .
                '<tr><td><span style="color:#ffc107;">●</span> <strong>' . __('Warning', 'dead-link-checker') . '</strong></td><td>' . __('Redirects, timeouts, or suspicious responses.', 'dead-link-checker') . '</td></tr>' .
                '<tr><td><span style="color:#28a745;">●</span> <strong>' . __('Working', 'dead-link-checker') . '</strong></td><td>' . __('HTTP 200 OK - Link is working properly.', 'dead-link-checker') . '</td></tr>' .
                '<tr><td><span style="color:#6c757d;">●</span> <strong>' . __('Dismissed', 'dead-link-checker') . '</strong></td><td>' . __('Manually ignored by admin.', 'dead-link-checker') . '</td></tr>' .
                '</table>',
        ));

        // Settings Tab
        $screen->add_help_tab(array(
            'id' => 'blc_settings_help',
            'title' => __('Settings', 'dead-link-checker'),
            'content' => '<h3>' . __('Configuration Options', 'dead-link-checker') . '</h3>' .
                '<ul>' .
                '<li>' . __('<strong>Scan Post Types</strong> - Choose which post types to include in scans.', 'dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Check External Links</strong> - Enable/disable checking of external URLs.', 'dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Email Notifications</strong> - Configure when and how to receive alerts.', 'dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Connection Timeout</strong> - How long to wait for link responses.', 'dead-link-checker') . '</li>' .
                '</ul>',
        ));

        // Sidebar
        $screen->set_help_sidebar(
            '<p><strong>' . __('For more help:', 'dead-link-checker') . '</strong></p>' .
            '<p><a href="https://developer.wordpress.org/plugins/" target="_blank">' . __('Plugin Documentation', 'dead-link-checker') . '</a></p>' .
            '<p><a href="https://wordpress.org/support/" target="_blank">' . __('Support Forums', 'dead-link-checker') . '</a></p>'
        );
    }

    public function add_dashboard_widget()
    {
        wp_add_dashboard_widget('blc_dashboard_widget', __('Dead Link Checker Pro', 'dead-link-checker'), array($this, 'render_dashboard_widget'));
    }

    public function render_dashboard_widget()
    {
        $stats = blc()->database->get_stats();
        ?>
        <div class="blc-widget">
            <p><strong>
                    <?php echo esc_html($stats['broken']); ?>
                </strong>
                <?php esc_html_e('Broken', 'dead-link-checker'); ?> | <strong>
                    <?php echo esc_html($stats['warning']); ?>
                </strong>
                <?php esc_html_e('Warnings', 'dead-link-checker'); ?> | <strong>
                    <?php echo esc_html($stats['total']); ?>
                </strong>
                <?php esc_html_e('Total', 'dead-link-checker'); ?>
            </p>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=dead-link-checker')); ?>" class="button">
                    <?php esc_html_e('View Links', 'dead-link-checker'); ?>
                </a></p>
        </div>
        <?php
    }

    public function add_admin_bar_menu($wp_admin_bar)
    {
        if (!current_user_can('manage_options'))
            return;
        $stats = blc()->database->get_stats();
        if ($stats['broken'] === 0)
            return;
        $wp_admin_bar->add_node(array('id' => 'blc-broken-links', 'title' => '<span class="ab-icon dashicons dashicons-warning"></span> ' . $stats['broken'], 'href' => admin_url('admin.php?page=dead-link-checker&status=broken')));
    }

    public function render_logs_page()
    {
        $scans = blc()->database->get_scan_history(50);
        ?>
        <div class="wrap blc-wrap blc-settings-page">
            <h1>
                <?php esc_html_e('Scan History', 'dead-link-checker'); ?>
            </h1>
            <div class="blc-settings-tabs">
                    <table class="blc-links-table blc-scan-history-table" width="100%">
                        <thead>
                            <tr>
                                <th>
                                    <?php esc_html_e('ID', 'dead-link-checker'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Type', 'dead-link-checker'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Status', 'dead-link-checker'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Started', 'dead-link-checker'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Completed', 'dead-link-checker'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Links', 'dead-link-checker'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Broken', 'dead-link-checker'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($scans)): ?>
                                <tr>
                                    <td colspan="7">
                                        <?php esc_html_e('No scans yet.', 'dead-link-checker'); ?>
                                    </td>
                                </tr>
                            <?php else:
                                foreach ($scans as $scan): ?>
                                    <tr>
                                        <td>
                                            <?php echo esc_html($scan->id); ?>
                                        </td>
                                        <td>
                                            <?php echo esc_html(ucfirst($scan->scan_type)); ?>
                                        </td>
                                        <td><span class="blc-scan-<?php echo esc_attr($scan->status); ?>">
                                                <?php echo esc_html(ucfirst($scan->status)); ?>
                                            </span></td>
                                        <td>
                                            <?php echo $scan->started_at ? esc_html(wp_date('M j, Y g:i a', strtotime($scan->started_at))) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php echo $scan->completed_at ? esc_html(wp_date('M j, Y g:i a', strtotime($scan->completed_at))) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php echo esc_html($scan->checked_links . '/' . $scan->total_links); ?>
                                        </td>
                                        <td>
                                            <?php echo esc_html($scan->broken_links); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                        </tbody>
                    </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render help page
     */
    public function render_help_page()
    {
        ?>
        <div class="wrap blc-wrap">
            <h1><span class="dashicons dashicons-editor-help"
                    style="font-size: 30px; margin-right: 10px;"></span><?php esc_html_e('Help & Documentation', 'dead-link-checker'); ?>
            </h1>

            <div class="blc-help-container" style="max-width: 900px; margin-top: 20px;">

                <!-- Getting Started -->
                <div class="blc-card"
                    style="background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-flag" style="color: #4CAF50;"></span>
                        <?php esc_html_e('Getting Started', 'dead-link-checker'); ?>
                    </h2>
                    <ol style="line-height: 2;">
                        <li><?php esc_html_e('Click "Scan Now" to scan your entire website for broken links.', 'dead-link-checker'); ?>
                        </li>
                        <li><?php esc_html_e('Review broken, warning, and working links in the dashboard.', 'dead-link-checker'); ?>
                        </li>
                        <li><?php esc_html_e('Click "Edit" to update a broken link directly in your content.', 'dead-link-checker'); ?>
                        </li>
                        <li><?php esc_html_e('Use "Dismiss" to ignore false positives.', 'dead-link-checker'); ?>
                        </li>
                    </ol>
                </div>

                <!-- Features -->
                <div class="blc-card"
                    style="background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-star-filled" style="color: #FF9800;"></span>
                        <?php esc_html_e('Features', 'dead-link-checker'); ?>
                    </h2>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div style="padding: 10px; background: #f9f9f9; border-radius: 5px;">
                            <strong><?php esc_html_e('Link Scanning', 'dead-link-checker'); ?></strong>
                            <p style="margin: 5px 0 0; color: #666;">
                                <?php esc_html_e('Automatically discover and check all links on your site.', 'dead-link-checker'); ?>
                            </p>
                        </div>
                        <div style="padding: 10px; background: #f9f9f9; border-radius: 5px;">
                            <strong><?php esc_html_e('Page Builder Support', 'dead-link-checker'); ?></strong>
                            <p style="margin: 5px 0 0; color: #666;">
                                <?php esc_html_e('Elementor, Divi, WPBakery, and Gutenberg.', 'dead-link-checker'); ?>
                            </p>
                        </div>
                        <div style="padding: 10px; background: #f9f9f9; border-radius: 5px;">
                            <strong><?php esc_html_e('Email Notifications', 'dead-link-checker'); ?></strong>
                            <p style="margin: 5px 0 0; color: #666;">
                                <?php esc_html_e('Get notified when broken links are found.', 'dead-link-checker'); ?>
                            </p>
                        </div>
                        <div style="padding: 10px; background: #f9f9f9; border-radius: 5px;">
                            <strong><?php esc_html_e('Redirect Manager', 'dead-link-checker'); ?></strong>
                            <p style="margin: 5px 0 0; color: #666;">
                                <?php esc_html_e('Create 301/302/307 redirects for broken URLs.', 'dead-link-checker'); ?>
                            </p>
                        </div>
                        <div style="padding: 10px; background: #f9f9f9; border-radius: 5px;">
                            <strong><?php esc_html_e('Advanced Filtering', 'dead-link-checker'); ?></strong>
                            <p style="margin: 5px 0 0; color: #666;">
                                <?php esc_html_e('Filter by status, type, HTTP code, and date.', 'dead-link-checker'); ?>
                            </p>
                        </div>
                        <div style="padding: 10px; background: #f9f9f9; border-radius: 5px;">
                            <strong><?php esc_html_e('Export Reports', 'dead-link-checker'); ?></strong>
                            <p style="margin: 5px 0 0; color: #666;">
                                <?php esc_html_e('Export to CSV or JSON for external analysis.', 'dead-link-checker'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Link Status -->
                <div class="blc-card"
                    style="background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-info" style="color: #2196F3;"></span>
                        <?php esc_html_e('Understanding Link Status', 'dead-link-checker'); ?>
                    </h2>
                    <table class="widefat" style="margin-top: 10px;">
                        <tr>
                            <td style="width: 120px;"><span style="color: #dc3545; font-size: 20px;">●</span>
                                <strong><?php esc_html_e('Broken', 'dead-link-checker'); ?></strong>
                            </td>
                            <td><?php esc_html_e('HTTP 4xx/5xx errors - Link is not working.', 'dead-link-checker'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td><span style="color: #ffc107; font-size: 20px;">●</span>
                                <strong><?php esc_html_e('Warning', 'dead-link-checker'); ?></strong>
                            </td>
                            <td><?php esc_html_e('Redirects, timeouts, or suspicious responses.', 'dead-link-checker'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td><span style="color: #28a745; font-size: 20px;">●</span>
                                <strong><?php esc_html_e('Working', 'dead-link-checker'); ?></strong>
                            </td>
                            <td><?php esc_html_e('HTTP 200 OK - Link is working properly.', 'dead-link-checker'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td><span style="color: #6c757d; font-size: 20px;">●</span>
                                <strong><?php esc_html_e('Dismissed', 'dead-link-checker'); ?></strong>
                            </td>
                            <td><?php esc_html_e('Manually ignored by admin.', 'dead-link-checker'); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Tips -->
                <div class="blc-card"
                    style="background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-lightbulb" style="color: #9C27B0;"></span>
                        <?php esc_html_e('Tips & Tricks', 'dead-link-checker'); ?>
                    </h2>
                    <ul style="line-height: 2;">
                        <li><strong><?php esc_html_e('Fresh Scan:', 'dead-link-checker'); ?></strong>
                            <?php esc_html_e('Clear all data and start from scratch using the "Fresh Scan" button.', 'dead-link-checker'); ?>
                        </li>
                        <li><strong><?php esc_html_e('Bulk Actions:', 'dead-link-checker'); ?></strong>
                            <?php esc_html_e('Select multiple links and perform actions in bulk.', 'dead-link-checker'); ?>
                        </li>
                        <li><strong><?php esc_html_e('Auto-Recheck:', 'dead-link-checker'); ?></strong>
                            <?php esc_html_e('Broken links are automatically rechecked twice daily.', 'dead-link-checker'); ?>
                        </li>
                        <li><strong><?php esc_html_e('Sorting:', 'dead-link-checker'); ?></strong>
                            <?php esc_html_e('Click column headers to sort by URL, status, or date.', 'dead-link-checker'); ?>
                        </li>
                    </ul>
                </div>

                <!-- Support -->
                <div class="blc-card"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; margin-bottom: 20px; border-radius: 8px; color: #fff;">
                    <h2 style="margin-top: 0; color: #fff;">
                        <span class="dashicons dashicons-sos"></span>
                        <?php esc_html_e('Need Help?', 'dead-link-checker'); ?>
                    </h2>
                    <p><?php esc_html_e('If you have questions or need support, please visit:', 'dead-link-checker'); ?>
                    </p>
                    <a href="https://wordpress.org/support/" target="_blank"
                        style="display: inline-block; background: #fff; color: #667eea; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold;">
                        <?php esc_html_e('WordPress Support Forums', 'dead-link-checker'); ?>
                    </a>
                </div>

            </div>
        </div>
        <?php
    }
}
