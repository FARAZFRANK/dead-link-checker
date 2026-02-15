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
 * Class FRANKDLC_Admin
 */
class FRANKDLC_Admin
{

    /** @var FRANKDLC_Dashboard */
    public $dashboard;

    /** @var FRANKDLC_Settings */
    public $settings;

    public function __construct()
    {
        $this->dashboard = new FRANKDLC_Dashboard();
        $this->settings = new FRANKDLC_Settings();
        $this->init_hooks();
    }

    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_FRANKDLC_start_scan', array($this, 'ajax_start_scan'));
        add_action('wp_ajax_FRANKDLC_stop_scan', array($this, 'ajax_stop_scan'));
        add_action('wp_ajax_FRANKDLC_get_scan_progress', array($this, 'ajax_get_scan_progress'));
        add_action('wp_ajax_FRANKDLC_dismiss_link', array($this, 'ajax_dismiss_link'));
        add_action('wp_ajax_FRANKDLC_undismiss_link', array($this, 'ajax_undismiss_link'));
        add_action('wp_ajax_FRANKDLC_recheck_link', array($this, 'ajax_recheck_link'));
        add_action('wp_ajax_FRANKDLC_delete_link', array($this, 'ajax_delete_link'));
        add_action('wp_ajax_FRANKDLC_edit_link', array($this, 'ajax_edit_link'));
        add_action('wp_ajax_FRANKDLC_remove_link', array($this, 'ajax_remove_link'));
        add_action('wp_ajax_FRANKDLC_bulk_action', array($this, 'ajax_bulk_action'));
        add_action('wp_ajax_FRANKDLC_export_links', array($this, 'ajax_export_links'));
        add_action('wp_ajax_FRANKDLC_fresh_scan', array($this, 'ajax_fresh_scan'));
        add_action('wp_ajax_FRANKDLC_force_stop_scan', array($this, 'ajax_force_stop_scan'));
        add_action('wp_ajax_FRANKDLC_reset_settings', array($this, 'ajax_reset_settings'));
        add_action('wp_ajax_FRANKDLC_clear_scan_history', array($this, 'ajax_clear_scan_history'));
        add_action('wp_ajax_FRANKDLC_full_reset', array($this, 'ajax_full_reset'));
        add_action('wp_ajax_FRANKDLC_cleanup_exports', array($this, 'ajax_cleanup_exports'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('admin_init', array($this, 'activation_redirect'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            __('Frank Dead Link Checker', 'frank-dead-link-checker'),
            __('Link Checker', 'frank-dead-link-checker'),
            'manage_options',
            'frank-dead-link-checker',
            array($this->dashboard, 'render_page'),
            'dashicons-admin-links',
            80
        );

        add_submenu_page('frank-dead-link-checker', __('Dashboard', 'frank-dead-link-checker'), __('Dashboard', 'frank-dead-link-checker'), 'manage_options', 'frank-dead-link-checker', array($this->dashboard, 'render_page'));
        add_submenu_page('frank-dead-link-checker', __('Settings', 'frank-dead-link-checker'), __('Settings', 'frank-dead-link-checker'), 'manage_options', 'frankdlc-settings', array($this->settings, 'render_page'));
        add_submenu_page('frank-dead-link-checker', __('Scan History', 'frank-dead-link-checker'), __('Scan History', 'frank-dead-link-checker'), 'manage_options', 'frankdlc-logs', array($this, 'render_logs_page'));
        add_submenu_page('frank-dead-link-checker', __('Help', 'frank-dead-link-checker'), __('Help', 'frank-dead-link-checker'), 'manage_options', 'frankdlc-help', array($this, 'render_help_page'));
    }

    public function enqueue_assets($hook)
    {
        if (strpos($hook, 'frank-dead-link-checker') === false && strpos($hook, 'frankdlc-') === false) {
            return;
        }

        wp_enqueue_style('frankdlc-admin', FRANKDLC_PLUGIN_URL . 'assets/css/admin.css', array(), FRANKDLC_VERSION);
        wp_enqueue_script('frankdlc-admin', FRANKDLC_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), FRANKDLC_VERSION, true);

        wp_localize_script('frankdlc-admin', 'frankdlcAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('FRANKDLC_admin_nonce'),
            'strings' => array(
                'scanning' => __('Scanning...', 'frank-dead-link-checker'),
                'scanComplete' => __('Scan complete!', 'frank-dead-link-checker'),
                'scanFailed' => __('Scan failed.', 'frank-dead-link-checker'),
                'scanStopped' => __('Scan stopped.', 'frank-dead-link-checker'),
                'confirmDelete' => __('Are you sure?', 'frank-dead-link-checker'),
                'confirmStop' => __('Are you sure you want to stop the scan?', 'frank-dead-link-checker'),
                'confirmFreshScan' => __('This will DELETE all existing link data and scan history, then start a fresh scan. Are you sure?', 'frank-dead-link-checker'),
                'freshScanStarted' => __('All data cleared. Fresh scan started.', 'frank-dead-link-checker'),
                'processing' => __('Processing...', 'frank-dead-link-checker'),
                'success' => __('Success!', 'frank-dead-link-checker'),
                'error' => __('An error occurred.', 'frank-dead-link-checker'),
                'exporting' => __('Exporting...', 'frank-dead-link-checker'),
                'exportSuccess' => __('Export created successfully!', 'frank-dead-link-checker'),
                'exportFailed' => __('Export failed.', 'frank-dead-link-checker'),
                // Button labels
                'stopScan' => __('Stop Scan', 'frank-dead-link-checker'),
                'stopping' => __('Stopping...', 'frank-dead-link-checker'),
                'clearing' => __('Clearing...', 'frank-dead-link-checker'),
                'freshScan' => __('Fresh Scan', 'frank-dead-link-checker'),
                'updateLink' => __('Update Link', 'frank-dead-link-checker'),
                'removeLink' => __('Remove Link', 'frank-dead-link-checker'),
                'createRedirect' => __('Create Redirect', 'frank-dead-link-checker'),
                'apply' => __('Apply', 'frank-dead-link-checker'),
                'forceStop' => __('Force Stop', 'frank-dead-link-checker'),
                'forceStopping' => __('Force Stopping...', 'frank-dead-link-checker'),
                'resetting' => __('Resetting...', 'frank-dead-link-checker'),
                'resetSettings' => __('Reset Settings', 'frank-dead-link-checker'),
                'clearHistory' => __('Clear History', 'frank-dead-link-checker'),
                'fullReset' => __('Full Reset', 'frank-dead-link-checker'),
                'resettingEverything' => __('Resetting Everything...', 'frank-dead-link-checker'),
                'cleaning' => __('Cleaning...', 'frank-dead-link-checker'),
                'cleanupExports' => __('Cleanup Exports', 'frank-dead-link-checker'),
                // Validation messages
                'enterUrlOrAnchor' => __('Please enter a new URL or anchor text', 'frank-dead-link-checker'),
                'enterTargetUrl' => __('Please enter a target URL', 'frank-dead-link-checker'),
                'selectAction' => __('Please select an action', 'frank-dead-link-checker'),
                'selectLink' => __('Please select at least one link', 'frank-dead-link-checker'),
                // Confirm dialogs
                'confirmRemoveLink' => __('Are you sure you want to remove this link? The anchor text will be kept but the link will be unlinked.', 'frank-dead-link-checker'),
                'confirmForceStop' => __('This will forcefully stop ALL running and pending scans. Are you sure?', 'frank-dead-link-checker'),
                'confirmResetSettings' => __('This will reset ALL plugin settings to their factory defaults. Your scan data will NOT be affected. Continue?', 'frank-dead-link-checker'),
                'confirmClearHistory' => __('This will delete all scan history records. Your link data will NOT be affected. Continue?', 'frank-dead-link-checker'),
                'confirmFullReset' => __('⚠️ WARNING: This will DELETE all plugin data including links, scan history, and settings. This action cannot be undone! Are you absolutely sure?', 'frank-dead-link-checker'),
                'confirmFullResetDouble' => __('Please confirm again: ALL data will be permanently deleted and settings reset to factory defaults.', 'frank-dead-link-checker'),
                'confirmCleanupExports' => __('This will delete all exported CSV/JSON files. Continue?', 'frank-dead-link-checker'),
                // Toast fallbacks
                'allScansForceStopped' => __('All scans force stopped.', 'frank-dead-link-checker'),
                'failedForceStop' => __('Failed to force stop.', 'frank-dead-link-checker'),
                'settingsResetDefaults' => __('Settings reset to defaults.', 'frank-dead-link-checker'),
                'failedResetSettings' => __('Failed to reset settings.', 'frank-dead-link-checker'),
                'scanHistoryCleared' => __('Scan history cleared.', 'frank-dead-link-checker'),
                'failedClearHistory' => __('Failed to clear history.', 'frank-dead-link-checker'),
                'pluginFullyReset' => __('Plugin fully reset.', 'frank-dead-link-checker'),
                'failedResetPlugin' => __('Failed to reset plugin.', 'frank-dead-link-checker'),
                'exportFilesCleaned' => __('Export files cleaned up.', 'frank-dead-link-checker'),
                'failedCleanupExports' => __('Failed to cleanup exports.', 'frank-dead-link-checker'),
                'redirectSuccess' => __('Redirect created successfully!', 'frank-dead-link-checker'),
                /* translators: 1: checked count, 2: total count, 3: percentage, 4: broken count, 5: warnings count */
                'progressText' => __('Checked %1$s of %2$s links (%3$s%%) — %4$s broken, %5$s warnings', 'frank-dead-link-checker'),
            ),
        ));
    }

    public function ajax_start_scan()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));
        }
        $result = FRANKDLC()->scanner->start_scan();
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success(array('message' => __('Scan started.', 'frank-dead-link-checker'), 'scan_id' => $result));
    }

    public function ajax_stop_scan()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));
        }
        $result = FRANKDLC()->scanner->stop_scan();
        if ($result) {
            wp_send_json_success(__('Scan stopped.', 'frank-dead-link-checker'));
        } else {
            wp_send_json_error(__('No scan is running.', 'frank-dead-link-checker'));
        }
    }

    public function ajax_get_scan_progress()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));
        }
        wp_send_json_success(FRANKDLC()->scanner->get_progress());
    }

    public function ajax_dismiss_link()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));
        $link_id = isset( $_POST['link_id'] ) ? absint( wp_unslash( $_POST['link_id'] ) ) : 0;
        if (!$link_id)
            wp_send_json_error(__('Invalid link ID.', 'frank-dead-link-checker'));
        $result = FRANKDLC()->database->dismiss_link($link_id);
        $result ? wp_send_json_success(__('Link dismissed.', 'frank-dead-link-checker')) : wp_send_json_error(__('Failed.', 'frank-dead-link-checker'));
    }

    public function ajax_undismiss_link()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));
        $link_id = isset( $_POST['link_id'] ) ? absint( wp_unslash( $_POST['link_id'] ) ) : 0;
        if (!$link_id)
            wp_send_json_error(__('Invalid link ID.', 'frank-dead-link-checker'));
        $result = FRANKDLC()->database->undismiss_link($link_id);
        $result ? wp_send_json_success(__('Link restored.', 'frank-dead-link-checker')) : wp_send_json_error(__('Failed.', 'frank-dead-link-checker'));
    }

    public function ajax_recheck_link()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));
        $link_id = isset( $_POST['link_id'] ) ? absint( wp_unslash( $_POST['link_id'] ) ) : 0;
        if (!$link_id)
            wp_send_json_error(__('Invalid link ID.', 'frank-dead-link-checker'));
        $link = FRANKDLC()->database->get_link($link_id);
        if (!$link)
            wp_send_json_error(__('Link not found.', 'frank-dead-link-checker'));

        // Check if URL still exists in source content (detect manual edits)
        if (!empty($link->source_id) && $link->source_type !== 'menu' && $link->source_type !== 'widget') {
            $post = get_post($link->source_id);
            if ($post && !empty($post->post_content)) {
                // Check if the URL is still present in the post content
                if (strpos($post->post_content, $link->url) === false) {
                    // URL was removed or changed — delete the stale entry
                    FRANKDLC()->database->delete_link($link_id);
                    wp_send_json_success(array(
                        'message' => __('Link was fixed/removed from the source. Entry deleted.', 'frank-dead-link-checker'),
                        'removed' => true,
                    ));
                }
            }
        }

        $checker = new FRANKDLC_Checker();
        $result = $checker->check_url($link->url);
        FRANKDLC()->database->update_link_result($link_id, $result);
        wp_send_json_success(array('message' => __('Link rechecked.', 'frank-dead-link-checker'), 'result' => $result));
    }

    public function ajax_delete_link()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));
        $link_id = isset( $_POST['link_id'] ) ? absint( wp_unslash( $_POST['link_id'] ) ) : 0;
        if (!$link_id)
            wp_send_json_error(__('Invalid link ID.', 'frank-dead-link-checker'));
        $result = FRANKDLC()->database->delete_link($link_id);
        $result ? wp_send_json_success(__('Link deleted.', 'frank-dead-link-checker')) : wp_send_json_error(__('Failed.', 'frank-dead-link-checker'));
    }

    public function ajax_edit_link()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));

        $link_id = isset( $_POST['link_id'] ) ? absint( wp_unslash( $_POST['link_id'] ) ) : 0;
        $new_url = isset($_POST['new_url']) ? esc_url_raw(wp_unslash($_POST['new_url'])) : '';
        $new_anchor_text = isset($_POST['new_anchor_text']) ? sanitize_text_field(wp_unslash($_POST['new_anchor_text'])) : '';

        if (!$link_id || (!$new_url && $new_anchor_text === ''))
            wp_send_json_error(__('Invalid parameters.', 'frank-dead-link-checker'));

        $link = FRANKDLC()->database->get_link($link_id);
        if (!$link)
            wp_send_json_error(__('Link not found.', 'frank-dead-link-checker'));

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
                    FRANKDLC()->database->update_link($link_id, $update_data);
                }

                // Auto-recheck the new URL so status updates immediately
                if ($new_url) {
                    $checker = new FRANKDLC_Checker();
                    $result = $checker->check_url($new_url);
                    FRANKDLC()->database->update_link_result($link_id, $result);
                }

                wp_send_json_success(__('Link updated.', 'frank-dead-link-checker'));
            }
        }
        wp_send_json_error(__('Could not update.', 'frank-dead-link-checker'));
    }

    public function ajax_remove_link()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));
        }

        $link_id = isset( $_POST['link_id'] ) ? absint( wp_unslash( $_POST['link_id'] ) ) : 0;
        if (!$link_id) {
            wp_send_json_error(__('Invalid link ID.', 'frank-dead-link-checker'));
        }

        $link = FRANKDLC()->database->get_link($link_id);
        if (!$link) {
            // Link may have been already deleted — treat as success
            wp_send_json_success(__('Link already removed.', 'frank-dead-link-checker'));
        }

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
        $deleted = FRANKDLC()->database->delete_link($link_id);
        if ($deleted) {
            wp_send_json_success(__('Link removed.', 'frank-dead-link-checker'));
        } else {
            wp_send_json_error(__('Failed to remove link from database.', 'frank-dead-link-checker'));
        }
    }

    public function ajax_bulk_action()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));
        $action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
        $link_ids = isset( $_POST['link_ids'] ) ? array_map( 'absint', wp_unslash( (array) $_POST['link_ids'] ) ) : array();
        if (!$action || empty($link_ids))
            wp_send_json_error(__('Invalid parameters.', 'frank-dead-link-checker'));
        $db = FRANKDLC()->database;
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
                        $checker = new FRANKDLC_Checker();
                        $db->update_link_result($id, $checker->check_url($link->url));
                        $count++;
                    }
                    break;
            }
        }
        /* translators: %d: number of links processed */
        wp_send_json_success(array('message' => sprintf(__('%d links processed.', 'frank-dead-link-checker'), $count), 'count' => $count));
    }

    public function ajax_export_links()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');
        if (!current_user_can('manage_options'))
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));
        $format = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'csv';
        $status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'all';
        $export = new FRANKDLC_Export();
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
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));
        }

        // Clear all existing data
        $cleared = FRANKDLC()->database->clear_all_data();

        if (!$cleared) {
            wp_send_json_error(__('Failed to clear existing data.', 'frank-dead-link-checker'));
        }

        // Start a new scan
        $result = FRANKDLC()->scanner->start_scan('full');

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'message' => __('All data cleared. Fresh scan started.', 'frank-dead-link-checker'),
            'scan_id' => $result,
        ));
    }

    /**
     * AJAX handler for Force Stop Scan
     */
    public function ajax_force_stop_scan()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));
        }
        FRANKDLC()->scanner->force_stop_scan();
        wp_send_json_success(__('All scans force stopped and scan state reset.', 'frank-dead-link-checker'));
    }

    /**
     * AJAX handler for Reset Settings to Default
     */
    public function ajax_reset_settings()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));
        }
        $defaults = FRANKDLC_Database::get_default_settings();
        update_option('FRANKDLC_settings', $defaults);
        wp_send_json_success(__('Settings reset to defaults.', 'frank-dead-link-checker'));
    }

    /**
     * AJAX handler for Clear Scan History
     */
    public function ajax_clear_scan_history()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));
        }
        $result = FRANKDLC()->database->clear_scan_history();
        if ($result) {
            wp_send_json_success(__('Scan history cleared.', 'frank-dead-link-checker'));
        } else {
            wp_send_json_error(__('Failed to clear scan history.', 'frank-dead-link-checker'));
        }
    }

    /**
     * AJAX handler for Full Plugin Reset
     */
    public function ajax_full_reset()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));
        }

        // Stop any running scans
        FRANKDLC()->scanner->force_stop_scan();

        // Clear all data
        FRANKDLC()->database->clear_all_data();

        // Reset settings
        $defaults = FRANKDLC_Database::get_default_settings();
        update_option('FRANKDLC_settings', $defaults);

        // Clear scheduled events
        wp_clear_scheduled_hook('FRANKDLC_scheduled_scan');
        wp_clear_scheduled_hook('FRANKDLC_recheck_broken');
        wp_clear_scheduled_hook('FRANKDLC_send_digest');
        wp_clear_scheduled_hook('FRANKDLC_cleanup_old_data');

        // Clean export files
        $this->cleanup_export_files();

        wp_send_json_success(__('Plugin fully reset to factory defaults.', 'frank-dead-link-checker'));
    }

    /**
     * AJAX handler for Cleanup Export Files
     */
    public function ajax_cleanup_exports()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'frank-dead-link-checker'));
        }
        $count = $this->cleanup_export_files();
        /* translators: %d: number of export files deleted */
        wp_send_json_success(sprintf(__('%d export file(s) deleted.', 'frank-dead-link-checker'), $count));
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
        if (!$screen || strpos($screen->id, 'frank-dead-link-checker') === false)
            return;
        $stats = FRANKDLC()->database->get_stats();
        if ($stats['broken'] > 0) {
            /* translators: %d: number of broken links */
            printf('<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>', sprintf(esc_html__('%d broken links detected!', 'frank-dead-link-checker'), intval($stats['broken'])), esc_html__('Review and fix them.', 'frank-dead-link-checker'));
        }
    }

    public function activation_redirect()
    {
        if (get_transient('FRANKDLC_activation_redirect')) {
            delete_transient('FRANKDLC_activation_redirect');
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking query parameter on activation redirect, not form processing
            if (!isset($_GET['activate-multi'])) {
                wp_safe_redirect(admin_url('admin.php?page=frank-dead-link-checker'));
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

        if (!$screen || strpos($screen->id, 'frank-dead-link-checker') === false) {
            return;
        }

        // Overview Tab
        $screen->add_help_tab(array(
            'id' => 'FRANKDLC_overview',
            'title' => __('Overview', 'frank-dead-link-checker'),
            'content' => '<h3>' . __('Frank Dead Link Checker', 'frank-dead-link-checker') . '</h3>' .
                '<p>' . __('This plugin scans your website for broken links and helps you fix them quickly. It checks all links in your posts, pages, comments, and custom fields.', 'frank-dead-link-checker') . '</p>' .
                '<h4>' . __('Features:', 'frank-dead-link-checker') . '</h4>' .
                '<ul>' .
                '<li>' . __('<strong>Link Scanning</strong> - Automatically discover and check all links on your site.', 'frank-dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Page Builder Support</strong> - Scan links in Elementor, Divi, WPBakery, and Gutenberg.', 'frank-dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Email Notifications</strong> - Get notified when broken links are found.', 'frank-dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Redirect Manager</strong> - Create 301/302/307 redirects for broken URLs.', 'frank-dead-link-checker') . '</li>' .
                '</ul>',
        ));

        // How to Use Tab
        $screen->add_help_tab(array(
            'id' => 'FRANKDLC_howto',
            'title' => __('How to Use', 'frank-dead-link-checker'),
            'content' => '<h3>' . __('Getting Started', 'frank-dead-link-checker') . '</h3>' .
                '<ol>' .
                '<li>' . __('<strong>Start a Scan</strong> - Click the "Scan Now" button to scan your entire website for broken links.', 'frank-dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Review Results</strong> - View broken, warning, and working links in the dashboard table.', 'frank-dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Fix Links</strong> - Click "Edit" to update a broken link directly, or use bulk actions.', 'frank-dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Dismiss Links</strong> - Ignore false positives by clicking "Dismiss".', 'frank-dead-link-checker') . '</li>' .
                '</ol>' .
                '<h4>' . __('Tips:', 'frank-dead-link-checker') . '</h4>' .
                '<ul>' .
                '<li>' . __('Use "Fresh Scan" to clear all data and start from scratch.', 'frank-dead-link-checker') . '</li>' .
                '<li>' . __('Use filters to narrow down results by status, type, or date.', 'frank-dead-link-checker') . '</li>' .
                '<li>' . __('Export your link data for external analysis.', 'frank-dead-link-checker') . '</li>' .
                '</ul>',
        ));

        // Link Status Tab
        $screen->add_help_tab(array(
            'id' => 'FRANKDLC_status',
            'title' => __('Link Status', 'frank-dead-link-checker'),
            'content' => '<h3>' . __('Understanding Link Status', 'frank-dead-link-checker') . '</h3>' .
                '<table class="widefat">' .
                '<tr><td><span style="color:#dc3545;">●</span> <strong>' . __('Broken', 'frank-dead-link-checker') . '</strong></td><td>' . __('HTTP 4xx/5xx errors - Link is not working.', 'frank-dead-link-checker') . '</td></tr>' .
                '<tr><td><span style="color:#ffc107;">●</span> <strong>' . __('Warning', 'frank-dead-link-checker') . '</strong></td><td>' . __('Redirects, timeouts, or suspicious responses.', 'frank-dead-link-checker') . '</td></tr>' .
                '<tr><td><span style="color:#28a745;">●</span> <strong>' . __('Working', 'frank-dead-link-checker') . '</strong></td><td>' . __('HTTP 200 OK - Link is working properly.', 'frank-dead-link-checker') . '</td></tr>' .
                '<tr><td><span style="color:#6c757d;">●</span> <strong>' . __('Dismissed', 'frank-dead-link-checker') . '</strong></td><td>' . __('Manually ignored by admin.', 'frank-dead-link-checker') . '</td></tr>' .
                '</table>',
        ));

        // Settings Tab
        $screen->add_help_tab(array(
            'id' => 'FRANKDLC_settings_help',
            'title' => __('Settings', 'frank-dead-link-checker'),
            'content' => '<h3>' . __('Configuration Options', 'frank-dead-link-checker') . '</h3>' .
                '<ul>' .
                '<li>' . __('<strong>Scan Post Types</strong> - Choose which post types to include in scans.', 'frank-dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Check External Links</strong> - Enable/disable checking of external URLs.', 'frank-dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Email Notifications</strong> - Configure when and how to receive alerts.', 'frank-dead-link-checker') . '</li>' .
                '<li>' . __('<strong>Connection Timeout</strong> - How long to wait for link responses.', 'frank-dead-link-checker') . '</li>' .
                '</ul>',
        ));

        // Sidebar
        $screen->set_help_sidebar(
            '<p><strong>' . __('For more help:', 'frank-dead-link-checker') . '</strong></p>' .
            '<p><a href="https://developer.wordpress.org/plugins/" target="_blank">' . __('Plugin Documentation', 'frank-dead-link-checker') . '</a></p>' .
            '<p><a href="https://wordpress.org/support/" target="_blank">' . __('Support Forums', 'frank-dead-link-checker') . '</a></p>'
        );
    }

    public function add_dashboard_widget()
    {
        wp_add_dashboard_widget('FRANKDLC_dashboard_widget', __('Frank Dead Link Checker', 'frank-dead-link-checker'), array($this, 'render_dashboard_widget'));
    }

    public function render_dashboard_widget()
    {
        $stats = FRANKDLC()->database->get_stats();
        ?>
        <div class="frankdlc-widget">
            <p><strong>
                    <?php echo esc_html($stats['broken']); ?>
                </strong>
                <?php esc_html_e('Broken', 'frank-dead-link-checker'); ?> | <strong>
                    <?php echo esc_html($stats['warning']); ?>
                </strong>
                <?php esc_html_e('Warnings', 'frank-dead-link-checker'); ?> | <strong>
                    <?php echo esc_html($stats['total']); ?>
                </strong>
                <?php esc_html_e('Total', 'frank-dead-link-checker'); ?>
            </p>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=frank-dead-link-checker')); ?>" class="button">
                    <?php esc_html_e('View Links', 'frank-dead-link-checker'); ?>
                </a></p>
        </div>
        <?php
    }

    public function add_admin_bar_menu($wp_admin_bar)
    {
        if (!current_user_can('manage_options'))
            return;
        $stats = FRANKDLC()->database->get_stats();
        if ($stats['broken'] === 0)
            return;
        $wp_admin_bar->add_node(array('id' => 'frankdlc-broken-links', 'title' => '<span class="ab-icon dashicons dashicons-warning"></span> ' . esc_html($stats['broken']), 'href' => admin_url('admin.php?page=frank-dead-link-checker&status=broken')));
    }

    public function render_logs_page()
    {
        $scans = FRANKDLC()->database->get_scan_history(50);
        ?>
        <div class="wrap frankdlc-wrap frankdlc-settings-page">
            <h1>
                <?php esc_html_e('Scan History', 'frank-dead-link-checker'); ?>
            </h1>
            <div class="frankdlc-settings-tabs">
                    <table class="frankdlc-links-table frankdlc-scan-history-table" width="100%">
                        <thead>
                            <tr>
                                <th>
                                    <?php esc_html_e('ID', 'frank-dead-link-checker'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Type', 'frank-dead-link-checker'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Status', 'frank-dead-link-checker'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Started', 'frank-dead-link-checker'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Completed', 'frank-dead-link-checker'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Links', 'frank-dead-link-checker'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Broken', 'frank-dead-link-checker'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($scans)): ?>
                                <tr>
                                    <td colspan="7">
                                        <?php esc_html_e('No scans yet.', 'frank-dead-link-checker'); ?>
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
                                        <td><span class="frankdlc-scan-<?php echo esc_attr($scan->status); ?>">
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
        <div class="wrap frankdlc-wrap">
            <h1><span class="dashicons dashicons-editor-help"
                    style="font-size: 30px; margin-right: 10px;"></span><?php esc_html_e('Help & Documentation', 'frank-dead-link-checker'); ?>
            </h1>

            <div class="frankdlc-help-container" style="max-width: 960px; margin-top: 20px;">

                <!-- How Plugin Works -->
                <div class="frankdlc-card" style="background: #fff; padding: 24px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 2px solid #667eea; padding-bottom: 10px; color: #333;">
                        <span class="dashicons dashicons-admin-generic" style="color: #667eea;"></span>
                        <?php esc_html_e('How Plugin Works?', 'frank-dead-link-checker'); ?>
                    </h2>
                    <p style="font-size: 14px; line-height: 1.8; color: #555;">
                        <?php esc_html_e('Frank Dead Link Checker scans your entire WordPress website to find broken links, redirects, and other link issues. It checks links inside posts, pages, menus, widgets, comments, custom fields, and even page builder content (Elementor, Divi, WPBakery, Gutenberg). Here is how it works:', 'frank-dead-link-checker'); ?>
                    </p>
                </div>

                <!-- Scanning Modes -->
                <div class="frankdlc-card" style="background: #fff; padding: 24px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-search" style="color: #4CAF50;"></span>
                        <?php esc_html_e('Scanning Modes', 'frank-dead-link-checker'); ?>
                    </h2>

                    <h3 style="color: #333; margin-top: 15px;">1. <?php esc_html_e('Manual Scan (Scan Now)', 'frank-dead-link-checker'); ?></h3>
                    <p style="color: #555; line-height: 1.7;">
                        <?php esc_html_e('Click the "Scan Now" button on the Dashboard to start a full scan manually. The plugin will:', 'frank-dead-link-checker'); ?>
                    </p>
                    <ol style="line-height: 2; color: #555;">
                        <li><?php esc_html_e('Discover all links from your selected content types (Posts, Pages, Menus, Widgets, Comments, Custom Fields).', 'frank-dead-link-checker'); ?></li>
                        <li><?php esc_html_e('Parse page builder content (Elementor, Divi, WPBakery, Gutenberg blocks) to find embedded links.', 'frank-dead-link-checker'); ?></li>
                        <li><?php esc_html_e('Check each link by sending HTTP HEAD/GET requests to verify if the URL responds correctly.', 'frank-dead-link-checker'); ?></li>
                        <li><?php esc_html_e('Categorize links as Working (200 OK), Broken (4xx/5xx errors), or Warning (redirects, slow responses).', 'frank-dead-link-checker'); ?></li>
                        <li><?php esc_html_e('Display results on the Dashboard with filters for easy review.', 'frank-dead-link-checker'); ?></li>
                    </ol>

                    <h3 style="color: #333; margin-top: 20px;">2. <?php esc_html_e('Automatic Scan (Scheduled)', 'frank-dead-link-checker'); ?></h3>
                    <p style="color: #555; line-height: 1.7;">
                        <?php esc_html_e('When Scan Type is set to "Automatic" in Settings, the plugin will automatically scan your website at the frequency you choose (Hourly, Twice Daily, Daily, or Weekly). This runs in the background using WordPress Cron — no manual action needed. Set Scan Type to "Manual" to disable automatic scanning entirely.', 'frank-dead-link-checker'); ?>
                    </p>

                    <h3 style="color: #333; margin-top: 20px;">3. <?php esc_html_e('Auto-Recheck (Broken Links Only)', 'frank-dead-link-checker'); ?></h3>
                    <p style="color: #555; line-height: 1.7;">
                        <?php esc_html_e('A lightweight background task that automatically rechecks links already marked as broken or warning every 6 hours. This helps detect when a previously broken link has been fixed — without running a full scan. It checks up to 50 links at a time and skips dismissed links.', 'frank-dead-link-checker'); ?>
                    </p>

                    <h3 style="color: #333; margin-top: 20px;">4. <?php esc_html_e('Fresh Scan', 'frank-dead-link-checker'); ?></h3>
                    <p style="color: #555; line-height: 1.7;">
                        <?php esc_html_e('Use the "Fresh Scan" button to clear ALL existing link data and scan history, then start a completely new scan from scratch. This is useful when you want a clean slate — for example, after making major changes to your website structure.', 'frank-dead-link-checker'); ?>
                    </p>
                </div>

                <!-- Handling Stuck Scans -->
                <div class="frankdlc-card" style="background: #fff; padding: 24px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-warning" style="color: #FF9800;"></span>
                        <?php esc_html_e('What If a Scan Gets Stuck?', 'frank-dead-link-checker'); ?>
                    </h2>
                    <p style="color: #555; line-height: 1.7;">
                        <?php esc_html_e('Sometimes a scan may appear stuck due to slow server responses, timeout issues, or server restarts. Here is what the plugin does to handle this:', 'frank-dead-link-checker'); ?>
                    </p>
                    <ul style="line-height: 2; color: #555;">
                        <li><strong><?php esc_html_e('Stop Scan Button:', 'frank-dead-link-checker'); ?></strong> <?php esc_html_e('Click "Stop Scan" on the Dashboard to gracefully stop a running scan.', 'frank-dead-link-checker'); ?></li>
                        <li><strong><?php esc_html_e('Force Stop:', 'frank-dead-link-checker'); ?></strong> <?php esc_html_e('If the Stop button does not work, use "Force Stop" from the Dashboard Tools section. This forcefully cancels all running scans, clears the scan queue, and resets the scan state.', 'frank-dead-link-checker'); ?></li>
                        <li><strong><?php esc_html_e('Auto-Recovery:', 'frank-dead-link-checker'); ?></strong> <?php esc_html_e('The plugin automatically detects scans that have been running for over 30 minutes without progress and marks them as "timed out". This prevents stuck scans from blocking new ones.', 'frank-dead-link-checker'); ?></li>
                    </ul>
                </div>

                <!-- Settings Explained -->
                <div class="frankdlc-card" style="background: #fff; padding: 24px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-admin-settings" style="color: #2196F3;"></span>
                        <?php esc_html_e('Settings Explained', 'frank-dead-link-checker'); ?>
                    </h2>

                    <h3 style="color: #444; background: #f0f6ff; padding: 8px 12px; border-radius: 4px;"><?php esc_html_e('General Tab', 'frank-dead-link-checker'); ?></h3>
                    <table class="widefat" style="margin-bottom: 20px;">
                        <tr><td style="width:180px;"><strong><?php esc_html_e('Scan Type', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Choose "Manual" (scan only when you click the button) or "Automatic" (scan runs on a schedule automatically).', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('Scan Frequency', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('How often automatic scans run: Hourly, Twice Daily, Daily, or Weekly. Only applies when Scan Type is "Automatic".', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('Request Timeout', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Maximum time (in seconds) to wait for a URL response before marking it as timed out. Default: 30 seconds. Range: 5–120 seconds.', 'frank-dead-link-checker'); ?></td></tr>
                    </table>

                    <h3 style="color: #444; background: #f0f6ff; padding: 8px 12px; border-radius: 4px;"><?php esc_html_e('Scan Scope Tab', 'frank-dead-link-checker'); ?></h3>
                    <table class="widefat" style="margin-bottom: 20px;">
                        <tr><td style="width:180px;"><strong><?php esc_html_e('Content Types', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Select which content types to scan: Posts, Pages, Comments, Widgets, Menus, Custom Fields, and Custom Post Types (if any registered).', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('Link Types', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Choose to check Internal Links (your own site), External Links (other websites), and/or Image URLs.', 'frank-dead-link-checker'); ?></td></tr>
                    </table>

                    <h3 style="color: #444; background: #f0f6ff; padding: 8px 12px; border-radius: 4px;"><?php esc_html_e('Exclusions Tab', 'frank-dead-link-checker'); ?></h3>
                    <table class="widefat" style="margin-bottom: 20px;">
                        <tr><td style="width:180px;"><strong><?php esc_html_e('Excluded Domains', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Enter domain names (one per line) that should be skipped during scanning. For example: facebook.com, twitter.com. Links to these domains will not be checked.', 'frank-dead-link-checker'); ?></td></tr>
                    </table>

                    <h3 style="color: #444; background: #f0f6ff; padding: 8px 12px; border-radius: 4px;"><?php esc_html_e('Notifications Tab', 'frank-dead-link-checker'); ?></h3>
                    <table class="widefat" style="margin-bottom: 20px;">
                        <tr><td style="width:180px;"><strong><?php esc_html_e('Email Notifications', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Enable to receive email alerts when broken links are found.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('Email Frequency', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('How often to send notification emails: Immediately, Daily, or Weekly digest.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('Recipients', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Email addresses to receive notifications (one per line). Defaults to the admin email.', 'frank-dead-link-checker'); ?></td></tr>
                    </table>

                    <h3 style="color: #444; background: #f0f6ff; padding: 8px 12px; border-radius: 4px;"><?php esc_html_e('Advanced Tab', 'frank-dead-link-checker'); ?></h3>
                    <table class="widefat" style="margin-bottom: 20px;">
                        <tr><td style="width:180px;"><strong><?php esc_html_e('Concurrent Requests', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Number of links to check simultaneously (1–10). Higher values speed up scanning but use more server resources. Default: 3.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('Delay Between Requests', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Milliseconds to wait between checking each link (0–5000). Helps prevent overloading your server or getting rate-limited by external sites. Default: 500ms.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('User Agent', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('The User-Agent string sent with HTTP requests. Some websites may block requests from unknown user agents. The default mimics a standard browser.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('Verify SSL', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('When enabled, the plugin verifies SSL certificates. Disable only if you are experiencing SSL-related false positives.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong><?php esc_html_e('Auto Cleanup', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Automatically delete old scan history records after a specified number of days. Default: 90 days.', 'frank-dead-link-checker'); ?></td></tr>
                    </table>
                </div>

                <!-- Link Status -->
                <div class="frankdlc-card" style="background: #fff; padding: 24px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-info" style="color: #2196F3;"></span>
                        <?php esc_html_e('Understanding Link Status', 'frank-dead-link-checker'); ?>
                    </h2>
                    <table class="widefat" style="margin-top: 10px;">
                        <tr>
                            <td style="width: 130px;"><span style="color: #dc3545; font-size: 20px;">●</span>
                                <strong><?php esc_html_e('Broken', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('HTTP 4xx or 5xx error. The link is dead or the server returned an error. Common codes: 404 (Not Found), 403 (Forbidden), 500 (Server Error).', 'frank-dead-link-checker'); ?></td>
                        </tr>
                        <tr>
                            <td><span style="color: #ffc107; font-size: 20px;">●</span>
                                <strong><?php esc_html_e('Warning', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('The link works but has issues: it redirects (301/302), responds slowly (over 5 seconds), or returned a suspicious status code. Review these to decide if action is needed.', 'frank-dead-link-checker'); ?></td>
                        </tr>
                        <tr>
                            <td><span style="color: #28a745; font-size: 20px;">●</span>
                                <strong><?php esc_html_e('Working', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('HTTP 200 OK. The link is working perfectly — no action needed.', 'frank-dead-link-checker'); ?></td>
                        </tr>
                        <tr>
                            <td><span style="color: #6c757d; font-size: 20px;">●</span>
                                <strong><?php esc_html_e('Dismissed', 'frank-dead-link-checker'); ?></strong></td>
                            <td><?php esc_html_e('Manually ignored by admin. These links are excluded from broken/warning counts and are not rechecked automatically.', 'frank-dead-link-checker'); ?></td>
                        </tr>
                    </table>

                    <h3 id="frankdlc-http-status-codes" style="margin-top: 20px; color: #555;"><?php esc_html_e('Common HTTP Status Codes', 'frank-dead-link-checker'); ?></h3>
                    <table class="widefat">
                        <tr><td style="width:80px;"><strong>200</strong></td><td><?php esc_html_e('OK — Link is working.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong>301</strong></td><td><?php esc_html_e('Moved Permanently — The URL has been permanently redirected to a new location.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong>302</strong></td><td><?php esc_html_e('Found — Temporary redirect. The URL is temporarily pointing elsewhere.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong>401</strong></td><td><?php esc_html_e('Unauthorized — Authentication is required. The server needs valid credentials to access this URL.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong>403</strong></td><td><?php esc_html_e('Forbidden — Access denied. The server refuses to serve this URL.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong>404</strong></td><td><?php esc_html_e('Not Found — The page does not exist. This is the most common broken link.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong>405</strong></td><td><?php esc_html_e('Method Not Allowed — The HTTP method used is not supported by this URL.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong>406</strong></td><td><?php esc_html_e('Not Acceptable — The server cannot produce a response matching the request headers.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong>410</strong></td><td><?php esc_html_e('Gone — The page has been permanently removed. Unlike 404, the server knows it was intentionally deleted.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong>429</strong></td><td><?php esc_html_e('Too Many Requests — Rate limit exceeded. The server is blocking requests due to too many in a short time.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong>500</strong></td><td><?php esc_html_e('Server Error — Something went wrong on the target server.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong>503</strong></td><td><?php esc_html_e('Service Unavailable — The server is temporarily down, often for maintenance.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong>Timeout</strong></td><td><?php esc_html_e('No response — The server did not respond within the configured timeout period.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong>DNS Error</strong></td><td><?php esc_html_e('Domain does not exist — The domain name could not be resolved.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong>SSL Error</strong></td><td><?php esc_html_e('SSL certificate issue — The website has an invalid or expired SSL certificate.', 'frank-dead-link-checker'); ?></td></tr>
                        <tr><td><strong>Error</strong></td><td><?php esc_html_e('Connection Error — A generic error occurred while trying to reach the URL (e.g., connection refused, reset, or unknown failure).', 'frank-dead-link-checker'); ?></td></tr>
                    </table>
                </div>

                <!-- Tips -->
                <div class="frankdlc-card" style="background: #fff; padding: 24px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-lightbulb" style="color: #9C27B0;"></span>
                        <?php esc_html_e('Tips & Best Practices', 'frank-dead-link-checker'); ?>
                    </h2>
                    <ul style="line-height: 2; color: #555;">
                        <li><strong><?php esc_html_e('Start with a Manual Scan:', 'frank-dead-link-checker'); ?></strong> <?php esc_html_e('Run your first scan manually to see how long it takes on your site before enabling automatic scanning.', 'frank-dead-link-checker'); ?></li>
                        <li><strong><?php esc_html_e('Adjust Concurrent Requests:', 'frank-dead-link-checker'); ?></strong> <?php esc_html_e('If your server is slow or shared hosting, reduce concurrent requests to 1–2. For dedicated servers, you can increase to 5–10.', 'frank-dead-link-checker'); ?></li>
                        <li><strong><?php esc_html_e('Use Exclusions:', 'frank-dead-link-checker'); ?></strong> <?php esc_html_e('Add domains that frequently block automated checks (like social media sites) to your exclusion list to reduce false positives.', 'frank-dead-link-checker'); ?></li>
                        <li><strong><?php esc_html_e('Review Warnings:', 'frank-dead-link-checker'); ?></strong> <?php esc_html_e('Warning links (redirects) are not broken, but you may want to update them to point directly to the final URL for better SEO.', 'frank-dead-link-checker'); ?></li>
                        <li><strong><?php esc_html_e('Export Regularly:', 'frank-dead-link-checker'); ?></strong> <?php esc_html_e('Export your broken link reports as CSV for record-keeping or to share with your team.', 'frank-dead-link-checker'); ?></li>
                        <li><strong><?php esc_html_e('Use Bulk Actions:', 'frank-dead-link-checker'); ?></strong> <?php esc_html_e('Select multiple links with checkboxes and use bulk Dismiss, Delete, or Recheck to manage them efficiently.', 'frank-dead-link-checker'); ?></li>
                    </ul>
                </div>

                <!-- How to Translate -->
                <div class="frankdlc-card" style="background: #fff; padding: 24px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span class="dashicons dashicons-translation" style="color: #00BCD4;"></span>
                        <?php esc_html_e('How to Translate This Plugin', 'frank-dead-link-checker'); ?>
                    </h2>
                    <p style="color: #555; line-height: 1.7;">
                        <?php esc_html_e('Frank Dead Link Checker is fully translation-ready. You can translate it into your language using any of the methods below.', 'frank-dead-link-checker'); ?>
                    </p>

                    <h3 style="color: #444; background: #e0f7fa; padding: 8px 12px; border-radius: 4px;">
                        <?php esc_html_e('Method 1: Using Loco Translate Plugin (Recommended)', 'frank-dead-link-checker'); ?>
                    </h3>
                    <ol style="line-height: 2; color: #555;">
                        <li><?php
                            printf(
                                /* translators: %s: link to Loco Translate plugin page */
                                esc_html__('Install and activate the %s plugin from the WordPress plugin repository.', 'frank-dead-link-checker'),
                                '<a href="' . esc_url(admin_url('plugin-install.php?s=loco+translate&tab=search&type=term')) . '" target="_blank"><strong>Loco Translate</strong></a>'
                            );
                        ?></li>
                        <li><?php
                            printf(
                                /* translators: %s: menu path */
                                esc_html__('Go to %s in your WordPress admin menu.', 'frank-dead-link-checker'),
                                '<strong>' . esc_html__('Loco Translate → Plugins', 'frank-dead-link-checker') . '</strong>'
                            );
                        ?></li>
                        <li><?php esc_html_e('Find "Frank Dead Link Checker" in the list and click on it.', 'frank-dead-link-checker'); ?></li>
                        <li><?php esc_html_e('Click "New Language" and select your desired language.', 'frank-dead-link-checker'); ?></li>
                        <li><?php esc_html_e('Translate the strings one by one using the visual editor. You can search and filter strings to find them quickly.', 'frank-dead-link-checker'); ?></li>
                        <li><?php esc_html_e('Click "Save" when done. Your translations will take effect immediately.', 'frank-dead-link-checker'); ?></li>
                    </ol>

                    <h3 style="color: #444; background: #e0f7fa; padding: 8px 12px; border-radius: 4px; margin-top: 20px;">
                        <?php esc_html_e('Method 2: Using Poedit (Manual PO/MO Files)', 'frank-dead-link-checker'); ?>
                    </h3>
                    <ol style="line-height: 2; color: #555;">
                        <li><?php
                            printf(
                                /* translators: %s: link to Poedit website */
                                esc_html__('Download and install %s on your computer (free desktop application).', 'frank-dead-link-checker'),
                                '<a href="https://poedit.net/" target="_blank"><strong>Poedit</strong></a>'
                            );
                        ?></li>
                        <li><?php
                            printf(
                                /* translators: %s: file path */
                                esc_html__('Open the POT template file located at: %s', 'frank-dead-link-checker'),
                                '<code>wp-content/plugins/dead-link-checker-pro/languages/dead-link-checker.pot</code>'
                            );
                        ?></li>
                        <li><?php esc_html_e('In Poedit, go to "Create New Translation" and select your language.', 'frank-dead-link-checker'); ?></li>
                        <li><?php esc_html_e('Translate each string, then save the file.', 'frank-dead-link-checker'); ?></li>
                        <li><?php
                            printf(
                                /* translators: %1$s: example PO filename, %2$s: example MO filename, %3$s: directory path */
                                esc_html__('Poedit will generate two files (e.g., %1$s and %2$s). Upload both to: %3$s', 'frank-dead-link-checker'),
                                '<code>dead-link-checker-fr_FR.po</code>',
                                '<code>dead-link-checker-fr_FR.mo</code>',
                                '<code>wp-content/languages/plugins/</code>'
                            );
                        ?></li>
                    </ol>

                    <h3 style="color: #444; background: #e0f7fa; padding: 8px 12px; border-radius: 4px; margin-top: 20px;">
                        <?php esc_html_e('Method 3: Contribute on translate.wordpress.org', 'frank-dead-link-checker'); ?>
                    </h3>
                    <p style="color: #555; line-height: 1.7;">
                        <?php esc_html_e('You can also contribute translations to the official WordPress translation platform. Your translations will be available to all users of this plugin who speak your language.', 'frank-dead-link-checker'); ?>
                    </p>
                    <a href="https://translate.wordpress.org/" target="_blank"
                        style="display: inline-block; background: #00BCD4; color: #fff; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 500;">
                        <?php esc_html_e('Visit translate.wordpress.org', 'frank-dead-link-checker'); ?>
                    </a>

                    <div style="margin-top: 20px; padding: 12px 16px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                        <strong><?php esc_html_e('Note:', 'frank-dead-link-checker'); ?></strong>
                        <?php esc_html_e('After translating, make sure your WordPress site language is set to your target language. Go to Settings → General → Site Language and select your language. The plugin will automatically load the matching translation file.', 'frank-dead-link-checker'); ?>
                    </div>
                </div>

                <!-- Support -->
                <div class="frankdlc-card"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 24px; margin-bottom: 20px; border-radius: 8px; color: #fff;">
                    <h2 style="margin-top: 0; color: #fff;">
                        <span class="dashicons dashicons-sos"></span>
                        <?php esc_html_e('Need Help?', 'frank-dead-link-checker'); ?>
                    </h2>
                    <p><?php esc_html_e('If you have questions or need support, please visit:', 'frank-dead-link-checker'); ?></p>
                    <a href="https://wordpress.org/support/" target="_blank"
                        style="display: inline-block; background: #fff; color: #667eea; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold;">
                        <?php esc_html_e('WordPress Support Forums', 'frank-dead-link-checker'); ?>
                    </a>
                </div>

            </div>
        </div>
        <?php
    }
}
