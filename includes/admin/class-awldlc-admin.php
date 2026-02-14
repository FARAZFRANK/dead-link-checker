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
 * Class AWLDLC_Admin
 */
class AWLDLC_Admin
{

    /** @var AWLDLC_Dashboard */
    public $dashboard;

    /** @var AWLDLC_Settings */
    public $settings;

    public function __construct()
    {
        $this->dashboard = new AWLDLC_Dashboard();
        $this->settings = new AWLDLC_Settings();
        $this->init_hooks();
    }

    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_awldlc_start_scan', array($this, 'ajax_start_scan'));
        add_action('wp_ajax_awldlc_stop_scan', array($this, 'ajax_stop_scan'));
        add_action('wp_ajax_awldlc_get_scan_progress', array($this, 'ajax_get_scan_progress'));
        add_action('wp_ajax_awldlc_dismiss_link', array($this, 'ajax_dismiss_link'));
        add_action('wp_ajax_awldlc_undismiss_link', array($this, 'ajax_undismiss_link'));
        add_action('wp_ajax_awldlc_recheck_link', array($this, 'ajax_recheck_link'));
        add_action('wp_ajax_awldlc_delete_link', array($this, 'ajax_delete_link'));
        add_action('wp_ajax_awldlc_edit_link', array($this, 'ajax_edit_link'));
        add_action('wp_ajax_awldlc_remove_link', array($this, 'ajax_remove_link'));
        add_action('wp_ajax_awldlc_bulk_action', array($this, 'ajax_bulk_action'));
        add_action('wp_ajax_awldlc_export_links', array($this, 'ajax_export_links'));
        add_action('wp_ajax_awldlc_fresh_scan', array($this, 'ajax_fresh_scan'));
        add_action('wp_ajax_awldlc_force_stop_scan', array($this, 'ajax_force_stop_scan'));
        add_action('wp_ajax_awldlc_reset_settings', array($this, 'ajax_reset_settings'));
        add_action('wp_ajax_awldlc_clear_scan_history', array($this, 'ajax_clear_scan_history'));
        add_action('wp_ajax_awldlc_full_reset', array($this, 'ajax_full_reset'));
        add_action('wp_ajax_awldlc_cleanup_exports', array($this, 'ajax_cleanup_exports'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('admin_init', array($this, 'activation_redirect'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            __('Dead Link Checker', 'dead-link-checker'),
            __('Link Checker', 'dead-link-checker'),
            'manage_options',
            'dead-link-checker',
            array($this->dashboard, 'render_page'),
            'dashicons-admin-links',
            80
        );

        add_submenu_page('dead-link-checker', __('Dashboard', 'dead-link-checker'), __('Dashboard', 'dead-link-checker'), 'manage_options', 'dead-link-checker', array($this->dashboard, 'render_page'));
        add_submenu_page('dead-link-checker', __('Settings', 'dead-link-checker'), __('Settings', 'dead-link-checker'), 'manage_options', 'awldlc-settings', array($this->settings, 'render_page'));
        add_submenu_page('dead-link-checker', __('Scan History', 'dead-link-checker'), __('Scan History', 'dead-link-checker'), 'manage_options', 'awldlc-logs', array($this, 'render_logs_page'));
        add_submenu_page('dead-link-checker', __('Help', 'dead-link-checker'), __('Help', 'dead-link-checker'), 'manage_options', 'awldlc-help', array($this, 'render_help_page'));
    }

    public function enqueue_assets($hook)
    {
        if (strpos($hook, 'dead-link-checker') === false && strpos($hook, 'awldlc-') === false) {
            return;
        }

        wp_enqueue_style('awldlc-admin', AWLDLC_PLUGIN_URL . 'assets/css/admin.css', array(), AWLDLC_VERSION);
        wp_enqueue_script('awldlc-admin', AWLDLC_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), AWLDLC_VERSION, true);

        wp_localize_script('awldlc-admin', 'awldlcAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('awldlc_admin_nonce'),
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
                // Button labels
                'stopScan' => __('Stop Scan', 'dead-link-checker'),
                'stopping' => __('Stopping...', 'dead-link-checker'),
                'clearing' => __('Clearing...', 'dead-link-checker'),
                'freshScan' => __('Fresh Scan', 'dead-link-checker'),
                'updateLink' => __('Update Link', 'dead-link-checker'),
                'removeLink' => __('Remove Link', 'dead-link-checker'),
                'createRedirect' => __('Create Redirect', 'dead-link-checker'),
                'apply' => __('Apply', 'dead-link-checker'),
                'forceStop' => __('Force Stop', 'dead-link-checker'),
                'forceStopping' => __('Force Stopping...', 'dead-link-checker'),
                'resetting' => __('Resetting...', 'dead-link-checker'),
                'resetSettings' => __('Reset Settings', 'dead-link-checker'),
                'clearHistory' => __('Clear History', 'dead-link-checker'),
                'fullReset' => __('Full Reset', 'dead-link-checker'),
                'resettingEverything' => __('Resetting Everything...', 'dead-link-checker'),
                'cleaning' => __('Cleaning...', 'dead-link-checker'),
                'cleanupExports' => __('Cleanup Exports', 'dead-link-checker'),
                // Validation messages
                'enterUrlOrAnchor' => __('Please enter a new URL or anchor text', 'dead-link-checker'),
                'enterTargetUrl' => __('Please enter a target URL', 'dead-link-checker'),
                'selectAction' => __('Please select an action', 'dead-link-checker'),
                'selectLink' => __('Please select at least one link', 'dead-link-checker'),
                // Confirm dialogs
                'confirmRemoveLink' => __('Are you sure you want to remove this link? The anchor text will be kept but the link will be unlinked.', 'dead-link-checker'),
                'confirmForceStop' => __('This will forcefully stop ALL running and pending scans. Are you sure?', 'dead-link-checker'),
                'confirmResetSettings' => __('This will reset ALL plugin settings to their factory defaults. Your scan data will NOT be affected. Continue?', 'dead-link-checker'),
                'confirmClearHistory' => __('This will delete all scan history records. Your link data will NOT be affected. Continue?', 'dead-link-checker'),
                'confirmFullReset' => __('⚠️ WARNING: This will DELETE all plugin data including links, scan history, and settings. This action cannot be undone! Are you absolutely sure?', 'dead-link-checker'),
                'confirmFullResetDouble' => __('Please confirm again: ALL data will be permanently deleted and settings reset to factory defaults.', 'dead-link-checker'),
                'confirmCleanupExports' => __('This will delete all exported CSV/JSON files. Continue?', 'dead-link-checker'),
                // Toast fallbacks
                'allScansForceStopped' => __('All scans force stopped.', 'dead-link-checker'),
                'failedForceStop' => __('Failed to force stop.', 'dead-link-checker'),
                'settingsResetDefaults' => __('Settings reset to defaults.', 'dead-link-checker'),
                'failedResetSettings' => __('Failed to reset settings.', 'dead-link-checker'),
                'scanHistoryCleared' => __('Scan history cleared.', 'dead-link-checker'),
                'failedClearHistory' => __('Failed to clear history.', 'dead-link-checker'),
                'pluginFullyReset' => __('Plugin fully reset.', 'dead-link-checker'),
                'failedResetPlugin' => __('Failed to reset plugin.', 'dead-link-checker'),
                'exportFilesCleaned' => __('Export files cleaned up.', 'dead-link-checker'),
                'failedCleanupExports' => __('Failed to cleanup exports.', 'dead-link-checker'),
                'redirectSuccess' => __('Redirect created successfully!', 'dead-link-checker'),
                /* translators: 1: checked count, 2: total count, 3: percentage, 4: broken count, 5: warnings count */
                'progressText' => __('Checked %1$s of %2$s links (%3$s%%) — %4$s broken, %5$s warnings', 'dead-link-checker'),
            ),
        ));
    }

    public function ajax_start_scan()
    {
        check_ajax_referer('awldlc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        }
        $result = awldlc()->scanner->start_scan();
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success(array('message' => __('Scan started.', 'dead-link-checker'), 'scan_id' => $result));
    }

    public function ajax_stop_scan()
    {
        check_ajax_referer('awldlc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        }
        $result = awldlc()->scanner->stop_scan();
        if ($result) {
            wp_send_json_success(__('Scan stopped.', 'dead-link-checker'));
        } else {
            wp_send_json_error(__('No scan is running.', 'dead-link-checker'));
        }
    }

    public function ajax_get_scan_progress()
    {
        check_ajax_referer('awldlc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        }
        wp_send_json_success(awldlc()->scanner->get_progress());
    }

    public function ajax_dismiss_link()
    {
        check_ajax_referer('awldlc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        $link_id = absint($_POST['link_id'] ?? 0);
        if (!$link_id)
            wp_send_json_error(__('Invalid link ID.', 'dead-link-checker'));
        $result = awldlc()->database->dismiss_link($link_id);
        $result ? wp_send_json_success(__('Link dismissed.', 'dead-link-checker')) : wp_send_json_error(__('Failed.', 'dead-link-checker'));
    }

    public function ajax_undismiss_link()
    {
        check_ajax_referer('awldlc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        $link_id = absint($_POST['link_id'] ?? 0);
        if (!$link_id)
            wp_send_json_error(__('Invalid link ID.', 'dead-link-checker'));
        $result = awldlc()->database->undismiss_link($link_id);
        $result ? wp_send_json_success(__('Link restored.', 'dead-link-checker')) : wp_send_json_error(__('Failed.', 'dead-link-checker'));
    }

    public function ajax_recheck_link()
    {
        check_ajax_referer('awldlc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        $link_id = absint($_POST['link_id'] ?? 0);
        if (!$link_id)
            wp_send_json_error(__('Invalid link ID.', 'dead-link-checker'));
        $link = awldlc()->database->get_link($link_id);
        if (!$link)
            wp_send_json_error(__('Link not found.', 'dead-link-checker'));

        // Check if URL still exists in source content (detect manual edits)
        if (!empty($link->source_id) && $link->source_type !== 'menu' && $link->source_type !== 'widget') {
            $post = get_post($link->source_id);
            if ($post && !empty($post->post_content)) {
                // Check if the URL is still present in the post content
                if (strpos($post->post_content, $link->url) === false) {
                    // URL was removed or changed — delete the stale entry
                    awldlc()->database->delete_link($link_id);
                    wp_send_json_success(array(
                        'message' => __('Link was fixed/removed from the source. Entry deleted.', 'dead-link-checker'),
                        'removed' => true,
                    ));
                }
            }
        }

        $checker = new AWLDLC_Checker();
        $result = $checker->check_url($link->url);
        awldlc()->database->update_link_result($link_id, $result);
        wp_send_json_success(array('message' => __('Link rechecked.', 'dead-link-checker'), 'result' => $result));
    }

    public function ajax_delete_link()
    {
        check_ajax_referer('awldlc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        $link_id = absint($_POST['link_id'] ?? 0);
        if (!$link_id)
            wp_send_json_error(__('Invalid link ID.', 'dead-link-checker'));
        $result = awldlc()->database->delete_link($link_id);
        $result ? wp_send_json_success(__('Link deleted.', 'dead-link-checker')) : wp_send_json_error(__('Failed.', 'dead-link-checker'));
    }

    public function ajax_edit_link()
    {
        check_ajax_referer('awldlc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));

        $link_id = absint($_POST['link_id'] ?? 0);
        $new_url = isset($_POST['new_url']) ? esc_url_raw($_POST['new_url']) : '';
        $new_anchor_text = isset($_POST['new_anchor_text']) ? sanitize_text_field(wp_unslash($_POST['new_anchor_text'])) : '';

        if (!$link_id || (!$new_url && $new_anchor_text === ''))
            wp_send_json_error(__('Invalid parameters.', 'dead-link-checker'));

        $link = awldlc()->database->get_link($link_id);
        if (!$link)
            wp_send_json_error(__('Link not found.', 'dead-link-checker'));

        if (!in_array($link->source_type, array('menu', 'widget', 'comment', 'custom_field'), true)) {
            $post = get_post($link->source_id);
            if ($post) {
                $content = $post->post_content;
                $old_url_escaped = preg_quote($link->url, '/');

                // Build replacement based on what changed
                if ($new_url && $new_anchor_text !== '') {
                    // Replace both URL and anchor text
                    $content = preg_replace(
                        '/(<a\s[^>]*href=["\'])' . $old_url_escaped . '(["\'][^>]*>)(.*?)(<\/a>)/si',
                        '${1}' . $new_url . '${2}' . esc_html($new_anchor_text) . '${4}',
                        $content
                    );
                } elseif ($new_url) {
                    // Replace URL only
                    $content = str_replace($link->url, $new_url, $content);
                } elseif ($new_anchor_text !== '') {
                    // Replace anchor text only
                    $content = preg_replace(
                        '/(<a\s[^>]*href=["\'])' . $old_url_escaped . '(["\'][^>]*>)(.*?)(<\/a>)/si',
                        '${1}' . $link->url . '${2}' . esc_html($new_anchor_text) . '${4}',
                        $content
                    );
                }

                wp_update_post(array('ID' => $post->ID, 'post_content' => $content));

                // Update database record
                $update_data = array();
                if ($new_url) {
                    $update_data['url'] = $new_url;
                }
                if ($new_anchor_text !== '') {
                    $update_data['anchor_text'] = $new_anchor_text;
                }
                if (!empty($update_data)) {
                    awldlc()->database->update_link($link_id, $update_data);
                }

                wp_send_json_success(__('Link updated.', 'dead-link-checker'));
            }
        }
        wp_send_json_error(__('Could not update.', 'dead-link-checker'));
    }

    public function ajax_remove_link()
    {
        check_ajax_referer('awldlc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));

        $link_id = absint($_POST['link_id'] ?? 0);
        if (!$link_id)
            wp_send_json_error(__('Invalid link ID.', 'dead-link-checker'));

        $link = awldlc()->database->get_link($link_id);
        if (!$link)
            wp_send_json_error(__('Link not found.', 'dead-link-checker'));

        // Try to remove the link from post content if source is a post type
        if (!in_array($link->source_type, array('menu', 'widget', 'comment', 'custom_field'), true)) {
            if (!empty($link->source_id)) {
                $post = get_post($link->source_id);
                if ($post) {
                    $old_url_escaped = preg_quote($link->url, '/');
                    // Replace <a href="URL">text</a> with just the text (unwrap the link)
                    $content = preg_replace(
                        '/<a\s[^>]*href=["\']' . $old_url_escaped . '["\'][^>]*>(.*?)<\/a>/si',
                        '$1',
                        $post->post_content
                    );
                    wp_update_post(array('ID' => $post->ID, 'post_content' => $content));
                }
            }
        }

        // Always delete the link record from the database
        awldlc()->database->delete_link($link_id);
        wp_send_json_success(__('Link removed.', 'dead-link-checker'));
    }

    public function ajax_bulk_action()
    {
        check_ajax_referer('awldlc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        $action = sanitize_key($_POST['bulk_action'] ?? '');
        $link_ids = array_map('absint', (array) ($_POST['link_ids'] ?? array()));
        if (!$action || empty($link_ids))
            wp_send_json_error(__('Invalid parameters.', 'dead-link-checker'));
        $db = awldlc()->database;
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
                        $checker = new AWLDLC_Checker();
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
        check_ajax_referer('awldlc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        $format = sanitize_key($_POST['format'] ?? 'csv');
        $status = sanitize_key($_POST['status'] ?? 'all');
        $export = new AWLDLC_Export();
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
        check_ajax_referer('awldlc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        }

        // Clear all existing data
        $cleared = awldlc()->database->clear_all_data();

        if (!$cleared) {
            wp_send_json_error(__('Failed to clear existing data.', 'dead-link-checker'));
        }

        // Start a new scan
        $result = awldlc()->scanner->start_scan('full');

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'message' => __('All data cleared. Fresh scan started.', 'dead-link-checker'),
            'scan_id' => $result,
        ));
    }

    /**
     * AJAX handler for Force Stop Scan
     */
    public function ajax_force_stop_scan()
    {
        check_ajax_referer('awldlc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        }
        awldlc()->scanner->force_stop_scan();
        wp_send_json_success(__('All scans force stopped and scan state reset.', 'dead-link-checker'));
    }

    /**
     * AJAX handler for Reset Settings to Default
     */
    public function ajax_reset_settings()
    {
        check_ajax_referer('awldlc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        }
        $defaults = AWLDLC_Database::get_default_settings();
        update_option('awldlc_settings', $defaults);
        wp_send_json_success(__('Settings reset to defaults.', 'dead-link-checker'));
    }

    /**
     * AJAX handler for Clear Scan History
     */
    public function ajax_clear_scan_history()
    {
        check_ajax_referer('awldlc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        }
        $result = awldlc()->database->clear_scan_history();
        if ($result) {
            wp_send_json_success(__('Scan history cleared.', 'dead-link-checker'));
        } else {
            wp_send_json_error(__('Failed to clear scan history.', 'dead-link-checker'));
        }
    }

    /**
     * AJAX handler for Full Plugin Reset
     */
    public function ajax_full_reset()
    {
        check_ajax_referer('awldlc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        }

        // Stop any running scans
        awldlc()->scanner->force_stop_scan();

        // Clear all data
        awldlc()->database->clear_all_data();

        // Reset settings
        $defaults = AWLDLC_Database::get_default_settings();
        update_option('awldlc_settings', $defaults);

        // Clear scheduled events
        wp_clear_scheduled_hook('awldlc_scheduled_scan');
        wp_clear_scheduled_hook('awldlc_recheck_broken');
        wp_clear_scheduled_hook('awldlc_send_digest');
        wp_clear_scheduled_hook('awldlc_cleanup_old_data');

        // Clean export files
        $this->cleanup_export_files();

        wp_send_json_success(__('Plugin fully reset to factory defaults.', 'dead-link-checker'));
    }

    /**
     * AJAX handler for Cleanup Export Files
     */
    public function ajax_cleanup_exports()
    {
        check_ajax_referer('awldlc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'dead-link-checker'));
        }
        $count = $this->cleanup_export_files();
        wp_send_json_success(sprintf(__('%d export file(s) deleted.', 'dead-link-checker'), $count));
    }

    /**
     * Clean up export files from the uploads directory
     *
     * @return int Number of files deleted
     */
    private function cleanup_export_files()
    {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/dlc-exports';
        $count = 0;

        if (is_dir($export_dir)) {
            $files = glob($export_dir . '/*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        wp_delete_file($file);
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    public function admin_notices()
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'dead-link-checker') === false)
            return;
        $stats = awldlc()->database->get_stats();
        if ($stats['broken'] > 0) {
            printf('<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>', sprintf(esc_html__('%d broken links detected!', 'dead-link-checker'), $stats['broken']), esc_html__('Review and fix them.', 'dead-link-checker'));
        }
    }

    public function activation_redirect()
    {
        if (get_transient('awldlc_activation_redirect')) {
            delete_transient('awldlc_activation_redirect');
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
            'id' => 'awldlc_overview',
            'title' => __('Overview', 'dead-link-checker'),
            'content' => '<h3>' . __('Dead Link Checker', 'dead-link-checker') . '</h3>' .
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
            'id' => 'awldlc_howto',
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
            'id' => 'awldlc_status',
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
            'id' => 'awldlc_settings_help',
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
        wp_add_dashboard_widget('awldlc_dashboard_widget', __('Dead Link Checker', 'dead-link-checker'), array($this, 'render_dashboard_widget'));
    }

    public function render_dashboard_widget()
    {
        $stats = awldlc()->database->get_stats();
        ?>
        <div class="awldlc-widget">
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
        $stats = awldlc()->database->get_stats();
        if ($stats['broken'] === 0)
            return;
        $wp_admin_bar->add_node(array('id' => 'awldlc-broken-links', 'title' => '<span class="ab-icon dashicons dashicons-warning"></span> ' . $stats['broken'], 'href' => admin_url('admin.php?page=dead-link-checker&status=broken')));
    }

    public function render_logs_page()
    {
        $scans = awldlc()->database->get_scan_history(50);
        ?>
        <div class="wrap awldlc-wrap awldlc-settings-page">
            <h1>
                <?php esc_html_e('Scan History', 'dead-link-checker'); ?>
            </h1>
            <div class="awldlc-settings-tabs">
                    <table class="awldlc-links-table awldlc-scan-history-table" width="100%">
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
                                        <td><span class="awldlc-scan-<?php echo esc_attr($scan->status); ?>">
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
        <div class="wrap awldlc-wrap">
            <h1><span class="dashicons dashicons-editor-help"
                    style="font-size: 30px; margin-right: 10px;"></span><?php esc_html_e('Help & Documentation', 'dead-link-checker'); ?>
            </h1>

            <div class="awldlc-help-container" style="max-width: 960px; margin-top: 20px;">

                <!-- How Plugin Works -->
                <div class="awldlc-card" style="background: #fff; padding: 24px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 2px solid #667eea; padding-bottom: 10px; color: #333;">
                        <span class="dashicons dashicons-admin-generic" style="color: #667eea;"></span>
                        <?php esc_html_e('How Plugin Works?', 'dead-link-checker'); ?>
                    </h2>
                    <p style="font-size: 14px; line-height: 1.8; color: #555;">
                        <?php esc_html_e('Dead Link Checker scans your entire WordPress website to find broken links, redirects, and other link issues. It checks links inside posts, pages, menus, widgets, comments, custom fields, and even page builder content (Elementor, Divi, WPBakery, Gutenberg). Here is how it works:', 'dead-link-checker'); ?>
                    </p>
                </div>

                <!-- Scanning Modes -->
                <div class="awldlc-card" style="background: #fff; padding: 24px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-search" style="color: #4CAF50;"></span>
                        <?php esc_html_e('Scanning Modes', 'dead-link-checker'); ?>
                    </h2>

                    <h3 style="color: #333; margin-top: 15px;">1. <?php esc_html_e('Manual Scan (Scan Now)', 'dead-link-checker'); ?></h3>
                    <p style="color: #555; line-height: 1.7;">
                        <?php esc_html_e('Click the "Scan Now" button on the Dashboard to start a full scan manually. The plugin will:', 'dead-link-checker'); ?>
                    </p>
                    <ol style="line-height: 2; color: #555;">
                        <li><?php esc_html_e('Discover all links from your selected content types (Posts, Pages, Menus, Widgets, Comments, Custom Fields).', 'dead-link-checker'); ?></li>
                        <li><?php esc_html_e('Parse page builder content (Elementor, Divi, WPBakery, Gutenberg blocks) to find embedded links.', 'dead-link-checker'); ?></li>
                        <li><?php esc_html_e('Check each link by sending HTTP HEAD/GET requests to verify if the URL responds correctly.', 'dead-link-checker'); ?></li>
                        <li><?php esc_html_e('Categorize links as Working (200 OK), Broken (4xx/5xx errors), or Warning (redirects, slow responses).', 'dead-link-checker'); ?></li>
                        <li><?php esc_html_e('Display results on the Dashboard with filters for easy review.', 'dead-link-checker'); ?></li>
                    </ol>

                    <h3 style="color: #333; margin-top: 20px;">2. <?php esc_html_e('Automatic Scan (Scheduled)', 'dead-link-checker'); ?></h3>
                    <p style="color: #555; line-height: 1.7;">
                        <?php esc_html_e('When Scan Type is set to "Automatic" in Settings, the plugin will automatically scan your website at the frequency you choose (Hourly, Twice Daily, Daily, or Weekly). This runs in the background using WordPress Cron — no manual action needed. Set Scan Type to "Manual" to disable automatic scanning entirely.', 'dead-link-checker'); ?>
                    </p>

                    <h3 style="color: #333; margin-top: 20px;">3. <?php esc_html_e('Auto-Recheck (Broken Links Only)', 'dead-link-checker'); ?></h3>
                    <p style="color: #555; line-height: 1.7;">
                        <?php esc_html_e('A lightweight background task that automatically rechecks links already marked as broken or warning every 6 hours. This helps detect when a previously broken link has been fixed — without running a full scan. It checks up to 50 links at a time and skips dismissed links.', 'dead-link-checker'); ?>
                    </p>

                    <h3 style="color: #333; margin-top: 20px;">4. <?php esc_html_e('Fresh Scan', 'dead-link-checker'); ?></h3>
                    <p style="color: #555; line-height: 1.7;">
                        <?php esc_html_e('Use the "Fresh Scan" button to clear ALL existing link data and scan history, then start a completely new scan from scratch. This is useful when you want a clean slate — for example, after making major changes to your website structure.', 'dead-link-checker'); ?>
                    </p>
                </div>

                <!-- Handling Stuck Scans -->
                <div class="awldlc-card" style="background: #fff; padding: 24px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-warning" style="color: #FF9800;"></span>
                        <?php esc_html_e('What If a Scan Gets Stuck?', 'dead-link-checker'); ?>
                    </h2>
                    <p style="color: #555; line-height: 1.7;">
                        <?php esc_html_e('Sometimes a scan may appear stuck due to slow server responses, timeout issues, or server restarts. Here is what the plugin does to handle this:', 'dead-link-checker'); ?>
                    </p>
                    <ul style="line-height: 2; color: #555;">
                        <li><strong><?php esc_html_e('Stop Scan Button:', 'dead-link-checker'); ?></strong> <?php esc_html_e('Click "Stop Scan" on the Dashboard to gracefully stop a running scan.', 'dead-link-checker'); ?></li>
                        <li><strong><?php esc_html_e('Force Stop:', 'dead-link-checker'); ?></strong> <?php esc_html_e('If the Stop button does not work, use "Force Stop" from the Dashboard Tools section. This forcefully cancels all running scans, clears the scan queue, and resets the scan state.', 'dead-link-checker'); ?></li>
                        <li><strong><?php esc_html_e('Auto-Recovery:', 'dead-link-checker'); ?></strong> <?php esc_html_e('The plugin automatically detects scans that have been running for over 30 minutes without progress and marks them as "timed out". This prevents stuck scans from blocking new ones.', 'dead-link-checker'); ?></li>
                    </ul>
                </div>

                <!-- Settings Explained -->
                <div class="awldlc-card" style="background: #fff; padding: 24px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-admin-settings" style="color: #2196F3;"></span>
                        <?php esc_html_e('Settings Explained', 'dead-link-checker'); ?>
                    </h2>

                    <h3 style="color: #444; background: #f0f6ff; padding: 8px 12px; border-radius: 4px;"><?php esc_html_e('General Tab', 'dead-link-checker'); ?></h3>
                    <table class="widefat" style="margin-bottom: 20px;">
                        <tr><td style="width:180px;"><strong><?php esc_html_e('Scan Type', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Choose "Manual" (scan only when you click the button) or "Automatic" (scan runs on a schedule automatically).', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('Scan Frequency', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('How often automatic scans run: Hourly, Twice Daily, Daily, or Weekly. Only applies when Scan Type is "Automatic".', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('Request Timeout', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Maximum time (in seconds) to wait for a URL response before marking it as timed out. Default: 30 seconds. Range: 5–120 seconds.', 'dead-link-checker'); ?></td></tr>
                    </table>

                    <h3 style="color: #444; background: #f0f6ff; padding: 8px 12px; border-radius: 4px;"><?php esc_html_e('Scan Scope Tab', 'dead-link-checker'); ?></h3>
                    <table class="widefat" style="margin-bottom: 20px;">
                        <tr><td style="width:180px;"><strong><?php esc_html_e('Content Types', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Select which content types to scan: Posts, Pages, Comments, Widgets, Menus, Custom Fields, and Custom Post Types (if any registered).', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('Link Types', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Choose to check Internal Links (your own site), External Links (other websites), and/or Image URLs.', 'dead-link-checker'); ?></td></tr>
                    </table>

                    <h3 style="color: #444; background: #f0f6ff; padding: 8px 12px; border-radius: 4px;"><?php esc_html_e('Exclusions Tab', 'dead-link-checker'); ?></h3>
                    <table class="widefat" style="margin-bottom: 20px;">
                        <tr><td style="width:180px;"><strong><?php esc_html_e('Excluded Domains', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Enter domain names (one per line) that should be skipped during scanning. For example: facebook.com, twitter.com. Links to these domains will not be checked.', 'dead-link-checker'); ?></td></tr>
                    </table>

                    <h3 style="color: #444; background: #f0f6ff; padding: 8px 12px; border-radius: 4px;"><?php esc_html_e('Notifications Tab', 'dead-link-checker'); ?></h3>
                    <table class="widefat" style="margin-bottom: 20px;">
                        <tr><td style="width:180px;"><strong><?php esc_html_e('Email Notifications', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Enable to receive email alerts when broken links are found.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('Email Frequency', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('How often to send notification emails: Immediately, Daily, or Weekly digest.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('Recipients', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Email addresses to receive notifications (one per line). Defaults to the admin email.', 'dead-link-checker'); ?></td></tr>
                    </table>

                    <h3 style="color: #444; background: #f0f6ff; padding: 8px 12px; border-radius: 4px;"><?php esc_html_e('Advanced Tab', 'dead-link-checker'); ?></h3>
                    <table class="widefat" style="margin-bottom: 20px;">
                        <tr><td style="width:180px;"><strong><?php esc_html_e('Concurrent Requests', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Number of links to check simultaneously (1–10). Higher values speed up scanning but use more server resources. Default: 3.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('Delay Between Requests', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Milliseconds to wait between checking each link (0–5000). Helps prevent overloading your server or getting rate-limited by external sites. Default: 500ms.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('User Agent', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('The User-Agent string sent with HTTP requests. Some websites may block requests from unknown user agents. The default mimics a standard browser.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('Verify SSL', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('When enabled, the plugin verifies SSL certificates. Disable only if you are experiencing SSL-related false positives.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('Auto Cleanup', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Automatically delete old scan history records after a specified number of days. Default: 90 days.', 'dead-link-checker'); ?></td></tr>
                    </table>
                </div>

                <!-- Link Status -->
                <div class="awldlc-card" style="background: #fff; padding: 24px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-info" style="color: #2196F3;"></span>
                        <?php esc_html_e('Understanding Link Status', 'dead-link-checker'); ?>
                    </h2>
                    <table class="widefat" style="margin-top: 10px;">
                        <tr>
                            <td style="width: 130px;"><span style="color: #dc3545; font-size: 20px;">●</span>
                                <strong><?php esc_html_e('Broken', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('HTTP 4xx or 5xx error. The link is dead or the server returned an error. Common codes: 404 (Not Found), 403 (Forbidden), 500 (Server Error).', 'dead-link-checker'); ?></td>
                        </tr>
                        <tr>
                            <td><span style="color: #ffc107; font-size: 20px;">●</span>
                                <strong><?php esc_html_e('Warning', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('The link works but has issues: it redirects (301/302), responds slowly (over 5 seconds), or returned a suspicious status code. Review these to decide if action is needed.', 'dead-link-checker'); ?></td>
                        </tr>
                        <tr>
                            <td><span style="color: #28a745; font-size: 20px;">●</span>
                                <strong><?php esc_html_e('Working', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('HTTP 200 OK. The link is working perfectly — no action needed.', 'dead-link-checker'); ?></td>
                        </tr>
                        <tr>
                            <td><span style="color: #6c757d; font-size: 20px;">●</span>
                                <strong><?php esc_html_e('Dismissed', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Manually ignored by admin. These links are excluded from broken/warning counts and are not rechecked automatically.', 'dead-link-checker'); ?></td>
                        </tr>
                    </table>

                    <h3 id="awldlc-http-status-codes" style="margin-top: 20px; color: #555;"><?php esc_html_e('Common HTTP Status Codes', 'dead-link-checker'); ?></h3>
                    <table class="widefat">
                        <tr><td style="width:80px;"><strong>200</strong></td><td><?php esc_html_e('OK — Link is working.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong>301</strong></td><td><?php esc_html_e('Moved Permanently — The URL has been permanently redirected to a new location.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong>302</strong></td><td><?php esc_html_e('Found — Temporary redirect. The URL is temporarily pointing elsewhere.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong>401</strong></td><td><?php esc_html_e('Unauthorized — Authentication is required. The server needs valid credentials to access this URL.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong>403</strong></td><td><?php esc_html_e('Forbidden — Access denied. The server refuses to serve this URL.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong>404</strong></td><td><?php esc_html_e('Not Found — The page does not exist. This is the most common broken link.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong>405</strong></td><td><?php esc_html_e('Method Not Allowed — The HTTP method used is not supported by this URL.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong>406</strong></td><td><?php esc_html_e('Not Acceptable — The server cannot produce a response matching the request headers.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong>410</strong></td><td><?php esc_html_e('Gone — The page has been permanently removed. Unlike 404, the server knows it was intentionally deleted.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong>429</strong></td><td><?php esc_html_e('Too Many Requests — Rate limit exceeded. The server is blocking requests due to too many in a short time.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong>500</strong></td><td><?php esc_html_e('Server Error — Something went wrong on the target server.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong>503</strong></td><td><?php esc_html_e('Service Unavailable — The server is temporarily down, often for maintenance.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong>Timeout</strong></td><td><?php esc_html_e('No response — The server did not respond within the configured timeout period.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong>DNS Error</strong></td><td><?php esc_html_e('Domain does not exist — The domain name could not be resolved.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong>SSL Error</strong></td><td><?php esc_html_e('SSL certificate issue — The website has an invalid or expired SSL certificate.', 'dead-link-checker'); ?></td></tr>
                        <tr><td><strong>Error</strong></td><td><?php esc_html_e('Connection Error — A generic error occurred while trying to reach the URL (e.g., connection refused, reset, or unknown failure).', 'dead-link-checker'); ?></td></tr>
                    </table>
                </div>

                <!-- Reset & Maintenance -->
                <div class="awldlc-card" style="background: #fff; padding: 24px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-update" style="color: #E91E63;"></span>
                        <?php esc_html_e('Reset & Maintenance Options', 'dead-link-checker'); ?>
                    </h2>
                    <table class="widefat">
                        <tr>
                            <td style="width:180px;"><strong><?php esc_html_e('Force Stop Scan', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Forcefully stops all running/pending scans, clears the scan queue, and resets the scan state. Use when a scan appears stuck.', 'dead-link-checker'); ?></td>
                            <td style="width:160px; text-align:right;">
                                <button type="button" id="awldlc-force-stop-btn" class="button" style="color:#FF9800; border-color:#FF9800;">
                                    <span class="dashicons dashicons-dismiss" style="margin-top:4px;"></span> <?php esc_html_e('Force Stop', 'dead-link-checker'); ?>
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Clear Scan History', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Deletes all scan history records but keeps your link data intact.', 'dead-link-checker'); ?></td>
                            <td style="text-align:right;">
                                <button type="button" id="awldlc-clear-history-btn" class="button">
                                    <span class="dashicons dashicons-trash" style="margin-top:4px;"></span> <?php esc_html_e('Clear History', 'dead-link-checker'); ?>
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Reset Settings', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Resets all plugin settings to their default values without affecting link data or scan history.', 'dead-link-checker'); ?></td>
                            <td style="text-align:right;">
                                <button type="button" id="awldlc-reset-settings-btn" class="button">
                                    <span class="dashicons dashicons-undo" style="margin-top:4px;"></span> <?php esc_html_e('Reset Settings', 'dead-link-checker'); ?>
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Cleanup Exports', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Deletes all exported CSV/JSON files from the uploads directory to free up disk space.', 'dead-link-checker'); ?></td>
                            <td style="text-align:right;">
                                <button type="button" id="awldlc-cleanup-exports-btn" class="button">
                                    <span class="dashicons dashicons-trash" style="margin-top:4px;"></span> <?php esc_html_e('Cleanup Exports', 'dead-link-checker'); ?>
                                </button>
                            </td>
                        </tr>
                        <tr style="background:#fff5f5;">
                            <td><strong style="color:#dc3545;"><?php esc_html_e('Full Plugin Reset', 'dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Resets EVERYTHING — link data, scan history, settings, and export files — back to factory defaults. Use with caution!', 'dead-link-checker'); ?></td>
                            <td style="text-align:right;">
                                <button type="button" id="awldlc-full-reset-btn" class="button" style="color:#dc3545; border-color:#dc3545;">
                                    <span class="dashicons dashicons-warning" style="margin-top:4px;"></span> <?php esc_html_e('Full Reset', 'dead-link-checker'); ?>
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Tips -->
                <div class="awldlc-card" style="background: #fff; padding: 24px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-lightbulb" style="color: #9C27B0;"></span>
                        <?php esc_html_e('Tips & Best Practices', 'dead-link-checker'); ?>
                    </h2>
                    <ul style="line-height: 2; color: #555;">
                        <li><strong><?php esc_html_e('Start with a Manual Scan:', 'dead-link-checker'); ?></strong> <?php esc_html_e('Run your first scan manually to see how long it takes on your site before enabling automatic scanning.', 'dead-link-checker'); ?></li>
                        <li><strong><?php esc_html_e('Adjust Concurrent Requests:', 'dead-link-checker'); ?></strong> <?php esc_html_e('If your server is slow or shared hosting, reduce concurrent requests to 1–2. For dedicated servers, you can increase to 5–10.', 'dead-link-checker'); ?></li>
                        <li><strong><?php esc_html_e('Use Exclusions:', 'dead-link-checker'); ?></strong> <?php esc_html_e('Add domains that frequently block automated checks (like social media sites) to your exclusion list to reduce false positives.', 'dead-link-checker'); ?></li>
                        <li><strong><?php esc_html_e('Review Warnings:', 'dead-link-checker'); ?></strong> <?php esc_html_e('Warning links (redirects) are not broken, but you may want to update them to point directly to the final URL for better SEO.', 'dead-link-checker'); ?></li>
                        <li><strong><?php esc_html_e('Export Regularly:', 'dead-link-checker'); ?></strong> <?php esc_html_e('Export your broken link reports as CSV for record-keeping or to share with your team.', 'dead-link-checker'); ?></li>
                        <li><strong><?php esc_html_e('Use Bulk Actions:', 'dead-link-checker'); ?></strong> <?php esc_html_e('Select multiple links with checkboxes and use bulk Dismiss, Delete, or Recheck to manage them efficiently.', 'dead-link-checker'); ?></li>
                    </ul>
                </div>

                <!-- How to Translate -->
                <div class="awldlc-card" style="background: #fff; padding: 24px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-translation" style="color: #00BCD4;"></span>
                        <?php esc_html_e('How to Translate This Plugin', 'dead-link-checker'); ?>
                    </h2>
                    <p style="color: #555; line-height: 1.7;">
                        <?php esc_html_e('Dead Link Checker is fully translation-ready. You can translate it into your language using any of the methods below.', 'dead-link-checker'); ?>
                    </p>

                    <h3 style="color: #444; background: #e0f7fa; padding: 8px 12px; border-radius: 4px;">
                        <?php esc_html_e('Method 1: Using Loco Translate Plugin (Recommended)', 'dead-link-checker'); ?>
                    </h3>
                    <ol style="line-height: 2; color: #555;">
                        <li><?php
                            printf(
                                /* translators: %s: link to Loco Translate plugin page */
                                esc_html__('Install and activate the %s plugin from the WordPress plugin repository.', 'dead-link-checker'),
                                '<a href="' . esc_url(admin_url('plugin-install.php?s=loco+translate&tab=search&type=term')) . '" target="_blank"><strong>Loco Translate</strong></a>'
                            );
                        ?></li>
                        <li><?php
                            printf(
                                /* translators: %s: menu path */
                                esc_html__('Go to %s in your WordPress admin menu.', 'dead-link-checker'),
                                '<strong>' . esc_html__('Loco Translate → Plugins', 'dead-link-checker') . '</strong>'
                            );
                        ?></li>
                        <li><?php esc_html_e('Find "Dead Link Checker" in the list and click on it.', 'dead-link-checker'); ?></li>
                        <li><?php esc_html_e('Click "New Language" and select your desired language.', 'dead-link-checker'); ?></li>
                        <li><?php esc_html_e('Translate the strings one by one using the visual editor. You can search and filter strings to find them quickly.', 'dead-link-checker'); ?></li>
                        <li><?php esc_html_e('Click "Save" when done. Your translations will take effect immediately.', 'dead-link-checker'); ?></li>
                    </ol>

                    <h3 style="color: #444; background: #e0f7fa; padding: 8px 12px; border-radius: 4px; margin-top: 20px;">
                        <?php esc_html_e('Method 2: Using Poedit (Manual PO/MO Files)', 'dead-link-checker'); ?>
                    </h3>
                    <ol style="line-height: 2; color: #555;">
                        <li><?php
                            printf(
                                /* translators: %s: link to Poedit website */
                                esc_html__('Download and install %s on your computer (free desktop application).', 'dead-link-checker'),
                                '<a href="https://poedit.net/" target="_blank"><strong>Poedit</strong></a>'
                            );
                        ?></li>
                        <li><?php
                            printf(
                                /* translators: %s: file path */
                                esc_html__('Open the POT template file located at: %s', 'dead-link-checker'),
                                '<code>wp-content/plugins/dead-link-checker-pro/languages/dead-link-checker.pot</code>'
                            );
                        ?></li>
                        <li><?php esc_html_e('In Poedit, go to "Create New Translation" and select your language.', 'dead-link-checker'); ?></li>
                        <li><?php esc_html_e('Translate each string, then save the file.', 'dead-link-checker'); ?></li>
                        <li><?php
                            printf(
                                /* translators: %1$s: example PO filename, %2$s: example MO filename, %3$s: directory path */
                                esc_html__('Poedit will generate two files (e.g., %1$s and %2$s). Upload both to: %3$s', 'dead-link-checker'),
                                '<code>dead-link-checker-fr_FR.po</code>',
                                '<code>dead-link-checker-fr_FR.mo</code>',
                                '<code>wp-content/languages/plugins/</code>'
                            );
                        ?></li>
                    </ol>

                    <h3 style="color: #444; background: #e0f7fa; padding: 8px 12px; border-radius: 4px; margin-top: 20px;">
                        <?php esc_html_e('Method 3: Contribute on translate.wordpress.org', 'dead-link-checker'); ?>
                    </h3>
                    <p style="color: #555; line-height: 1.7;">
                        <?php esc_html_e('You can also contribute translations to the official WordPress translation platform. Your translations will be available to all users of this plugin who speak your language.', 'dead-link-checker'); ?>
                    </p>
                    <a href="https://translate.wordpress.org/" target="_blank"
                        style="display: inline-block; background: #00BCD4; color: #fff; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 500;">
                        <?php esc_html_e('Visit translate.wordpress.org', 'dead-link-checker'); ?>
                    </a>

                    <div style="margin-top: 20px; padding: 12px 16px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                        <strong><?php esc_html_e('Note:', 'dead-link-checker'); ?></strong>
                        <?php esc_html_e('After translating, make sure your WordPress site language is set to your target language. Go to Settings → General → Site Language and select your language. The plugin will automatically load the matching translation file.', 'dead-link-checker'); ?>
                    </div>
                </div>

                <!-- Support -->
                <div class="awldlc-card"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 24px; margin-bottom: 20px; border-radius: 8px; color: #fff;">
                    <h2 style="margin-top: 0; color: #fff;">
                        <span class="dashicons dashicons-sos"></span>
                        <?php esc_html_e('Need Help?', 'dead-link-checker'); ?>
                    </h2>
                    <p><?php esc_html_e('If you have questions or need support, please visit:', 'dead-link-checker'); ?></p>
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
