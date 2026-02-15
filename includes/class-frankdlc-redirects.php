<?php
/**
 * Redirect Manager
 *
 * Handles URL redirects for broken links.
 *
 * @package BrokenLinkChecker
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

class FRANKDLC_Redirects
{
    /**
     * Redirects table name
     *
     * @var string
     */
    private $table;

    /**
     * Whether the table exists
     *
     * @var bool
     */
    private $table_exists = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'FRANKDLC_redirects';

        // Check if table exists, create if missing
        $this->ensure_table_exists();

        // Hook into template_redirect to handle redirects
        add_action('template_redirect', array($this, 'handle_redirect'), 1);

        // Register AJAX handlers
        add_action('wp_ajax_FRANKDLC_create_redirect', array($this, 'ajax_create_redirect'));
        add_action('wp_ajax_FRANKDLC_update_redirect', array($this, 'ajax_update_redirect'));
        add_action('wp_ajax_FRANKDLC_delete_redirect', array($this, 'ajax_delete_redirect'));
        add_action('wp_ajax_FRANKDLC_toggle_redirect', array($this, 'ajax_toggle_redirect'));
        add_action('wp_ajax_FRANKDLC_get_redirects', array($this, 'ajax_get_redirects'));
    }

    /**
     * Ensure the redirects table exists
     */
    private function ensure_table_exists()
    {
        global $wpdb;

        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->table
        ));

        if ($table_exists) {
            $this->table_exists = true;
            return;
        }

        // Create the table if it doesn't exist
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table} (
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

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        $this->table_exists = true;
    }

    /**
     * Handle incoming redirects
     */
    public function handle_redirect()
    {
        global $wpdb;

        // Get the current request URI
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        if (empty($request_uri)) {
            return;
        }

        // Check for a matching redirect
        $url_hash = md5($request_uri);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $redirect = $wpdb->get_row($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is a safe class property
            "SELECT * FROM {$this->table} WHERE source_url_hash = %s AND is_active = 1",
            $url_hash
        ));

        // Also check with full URL
        if (!$redirect) {
            $full_url = home_url($request_uri);
            $url_hash = md5($full_url);

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $redirect = $wpdb->get_row($wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is a safe class property
                "SELECT * FROM {$this->table} WHERE source_url_hash = %s AND is_active = 1",
                $url_hash
            ));
        }

        if ($redirect) {
            // Update hit count
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $this->table,
                array(
                    'hit_count' => $redirect->hit_count + 1,
                    'last_hit' => current_time('mysql'),
                ),
                array('id' => $redirect->id),
                array('%d', '%s'),
                array('%d')
            );

            // Perform redirect
            wp_safe_redirect($redirect->target_url, $redirect->redirect_type);
            exit;
        }
    }

    /**
     * Create a new redirect
     *
     * @param array $data Redirect data
     * @return int|WP_Error Redirect ID or error
     */
    public function create_redirect($data)
    {
        global $wpdb;

        $source_url = isset($data['source_url']) ? esc_url_raw($data['source_url']) : '';
        $target_url = isset($data['target_url']) ? esc_url_raw($data['target_url']) : '';
        $redirect_type = isset($data['redirect_type']) ? absint($data['redirect_type']) : 301;

        if (empty($source_url) || empty($target_url)) {
            return new WP_Error('missing_data', __('Source and target URLs are required.', 'frank-dead-link-checker'));
        }

        // Validate redirect type
        $valid_types = array(301, 302, 307);
        if (!in_array($redirect_type, $valid_types, true)) {
            $redirect_type = 301;
        }

        // Check for existing redirect
        $url_hash = md5($source_url);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing = $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is a safe class property
            "SELECT id FROM {$this->table} WHERE source_url_hash = %s",
            $url_hash
        ));

        if ($existing) {
            return new WP_Error('duplicate', __('A redirect for this URL already exists.', 'frank-dead-link-checker'));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $this->table,
            array(
                'source_url' => $source_url,
                'source_url_hash' => $url_hash,
                'target_url' => $target_url,
                'redirect_type' => $redirect_type,
                'is_active' => 1,
                'hit_count' => 0,
                'created_at' => current_time('mysql'),
                'created_from_link_id' => isset($data['link_id']) ? absint($data['link_id']) : null,
                'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : '',
            ),
            array('%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s')
        );

        if (!$result) {
            return new WP_Error('db_error', __('Failed to create redirect.', 'frank-dead-link-checker'));
        }

        return $wpdb->insert_id;
    }

    /**
     * Update a redirect
     *
     * @param int   $id   Redirect ID
     * @param array $data Update data
     * @return bool|WP_Error
     */
    public function update_redirect($id, $data)
    {
        global $wpdb;

        $update = array();
        $format = array();

        if (isset($data['target_url'])) {
            $update['target_url'] = esc_url_raw($data['target_url']);
            $format[] = '%s';
        }

        if (isset($data['redirect_type'])) {
            $update['redirect_type'] = absint($data['redirect_type']);
            $format[] = '%d';
        }

        if (isset($data['is_active'])) {
            $update['is_active'] = (int) (bool) $data['is_active'];
            $format[] = '%d';
        }

        if (isset($data['notes'])) {
            $update['notes'] = sanitize_textarea_field($data['notes']);
            $format[] = '%s';
        }

        if (empty($update)) {
            return new WP_Error('no_data', __('No data to update.', 'frank-dead-link-checker'));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $this->table,
            $update,
            array('id' => $id),
            $format,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete a redirect
     *
     * @param int $id Redirect ID
     * @return bool
     */
    public function delete_redirect($id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (bool) $wpdb->delete(
            $this->table,
            array('id' => $id),
            array('%d')
        );
    }

    /**
     * Get redirects with filtering
     *
     * @param array $args Query arguments
     * @return array
     */
    public function get_redirects($args = array())
    {
        global $wpdb;

        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => '',
            'is_active' => null,
        );

        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];

        $where = array('1=1');
        $values = array();

        if ($args['is_active'] !== null) {
            $where[] = 'is_active = %d';
            $values[] = (int) $args['is_active'];
        }

        if (!empty($args['search'])) {
            $where[] = '(source_url LIKE %s OR target_url LIKE %s)';
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search;
            $values[] = $search;
        }

        $where_sql = implode(' AND ', $where);

        // Validate orderby
        $valid_orderby = array('id', 'source_url', 'target_url', 'redirect_type', 'hit_count', 'created_at', 'last_hit');
        $orderby = in_array($args['orderby'], $valid_orderby, true) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Get total count
        if (!empty($values)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is a safe class property, $where_sql is built from validated components
            $count_sql = $wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}", $values);
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is a safe class property, $where_sql is built from validated components
            $count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}";
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- SQL is prepared above when values exist
        $total = (int) $wpdb->get_var($count_sql);

        // Get redirects
        $values[] = $args['per_page'];
        $values[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is a safe class property, $where_sql/$orderby/$order are validated
        $sql = "SELECT * FROM {$this->table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- SQL prepared in line above
        $redirects = $wpdb->get_results($wpdb->prepare($sql, $values));

        return array(
            'redirects' => $redirects,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
        );
    }

    /**
     * Get a single redirect
     *
     * @param int $id Redirect ID
     * @return object|null
     */
    public function get_redirect($id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is a safe class property
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ));
    }

    /**
     * AJAX: Create redirect
     */
    public function ajax_create_redirect()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'frank-dead-link-checker')));
        }

        $result = $this->create_redirect(array(
            'source_url' => isset($_POST['source_url']) ? sanitize_text_field(wp_unslash($_POST['source_url'])) : '',
            'target_url' => isset($_POST['target_url']) ? sanitize_text_field(wp_unslash($_POST['target_url'])) : '',
            'redirect_type' => isset($_POST['redirect_type']) ? absint(wp_unslash($_POST['redirect_type'])) : 301,
            'link_id' => isset($_POST['link_id']) ? absint(wp_unslash($_POST['link_id'])) : 0,
            'notes' => isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '',
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Redirect created successfully.', 'frank-dead-link-checker'),
            'redirect_id' => $result,
        ));
    }

    /**
     * AJAX: Update redirect
     */
    public function ajax_update_redirect()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'frank-dead-link-checker')));
        }

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid redirect ID.', 'frank-dead-link-checker')));
        }

        $result = $this->update_redirect($id, array(
            'target_url' => isset($_POST['target_url']) ? sanitize_text_field(wp_unslash($_POST['target_url'])) : '',
            'redirect_type' => isset($_POST['redirect_type']) ? absint(wp_unslash($_POST['redirect_type'])) : 301,
            'notes' => isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '',
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Redirect updated successfully.', 'frank-dead-link-checker')));
    }

    /**
     * AJAX: Delete redirect
     */
    public function ajax_delete_redirect()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'frank-dead-link-checker')));
        }

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid redirect ID.', 'frank-dead-link-checker')));
        }

        $result = $this->delete_redirect($id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to delete redirect.', 'frank-dead-link-checker')));
        }

        wp_send_json_success(array('message' => __('Redirect deleted successfully.', 'frank-dead-link-checker')));
    }

    /**
     * AJAX: Toggle redirect status
     */
    public function ajax_toggle_redirect()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'frank-dead-link-checker')));
        }

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid redirect ID.', 'frank-dead-link-checker')));
        }

        $redirect = $this->get_redirect($id);
        if (!$redirect) {
            wp_send_json_error(array('message' => __('Redirect not found.', 'frank-dead-link-checker')));
        }

        $new_status = $redirect->is_active ? 0 : 1;
        $result = $this->update_redirect($id, array('is_active' => $new_status));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => $new_status ? __('Redirect enabled.', 'frank-dead-link-checker') : __('Redirect disabled.', 'frank-dead-link-checker'),
            'is_active' => $new_status,
        ));
    }

    /**
     * AJAX: Get redirects list
     */
    public function ajax_get_redirects()
    {
        check_ajax_referer('FRANKDLC_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'frank-dead-link-checker')));
        }

        $result = $this->get_redirects(array(
            'per_page' => isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 20,
            'page' => isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1,
            'search' => isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '',
            'is_active' => isset($_POST['is_active']) && $_POST['is_active'] !== '' ? absint(wp_unslash($_POST['is_active'])) : null,
            'orderby' => isset($_POST['orderby']) ? sanitize_key(wp_unslash($_POST['orderby'])) : 'created_at',
            'order' => isset($_POST['order']) ? sanitize_key(wp_unslash($_POST['order'])) : 'DESC',
        ));

        wp_send_json_success($result);
    }

    /**
     * Get redirect type label
     *
     * @param int $type Redirect type code
     * @return string
     */
    public static function get_type_label($type)
    {
        $types = array(
            301 => __('301 Permanent', 'frank-dead-link-checker'),
            302 => __('302 Temporary', 'frank-dead-link-checker'),
            307 => __('307 Temporary', 'frank-dead-link-checker'),
        );

        return $types[$type] ?? __('Unknown', 'frank-dead-link-checker');
    }
}
