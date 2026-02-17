<?php
/**
 * Dashboard Handler
 *
 * Renders the main dashboard with statistics and links table.
 *
 * @package BrokenLinkChecker
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class FRANKDLC_Dashboard
{

    public function render_page()
    {
        $stats = FRANKDLC()->database->get_stats();
        $current_status = isset($_GET['status']) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
        $current_type = isset($_GET['link_type']) ? sanitize_key( wp_unslash( $_GET['link_type'] ) ) : 'all';

        // Handle search from POST (to allow URLs with special characters)
        $search = '';
        if (isset($_POST['FRANKDLC_search']) && isset($_POST['FRANKDLC_search_nonce'])) {
            if (wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['FRANKDLC_search_nonce'] ) ), 'FRANKDLC_search_nonce')) {
                $search = sanitize_text_field(wp_unslash($_POST['FRANKDLC_search']));
            }
        }

        $paged = isset($_GET['paged']) ? absint( wp_unslash( $_GET['paged'] ) ) : 1;
        $allowed_per_page = array(10, 20, 50, 100, 200);
        $per_page = isset($_GET['per_page']) ? absint( wp_unslash( $_GET['per_page'] ) ) : 10;
        if (!in_array($per_page, $allowed_per_page, true)) {
            $per_page = 10;
        }

        // Sorting params
        $orderby = isset($_GET['orderby']) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'last_check';
        $order = isset($_GET['order']) && strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) === 'ASC' ? 'ASC' : 'DESC';

        // Advanced filters
        $date_from = isset($_GET['date_from']) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
        $http_status = isset($_GET['http_status']) ? sanitize_text_field( wp_unslash( $_GET['http_status'] ) ) : '';

        $links = FRANKDLC()->database->get_links(array(
            'status' => $current_status,
            'link_type' => $current_type,
            'search' => $search,
            'page' => $paged,
            'per_page' => $per_page,
            'orderby' => $orderby,
            'order' => $order,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'http_status' => $http_status,
        ));

        $total_items = FRANKDLC()->database->get_links_count(array(
            'status' => $current_status,
            'link_type' => $current_type,
            'search' => $search,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'http_status' => $http_status,
        ));

        $total_pages = ceil($total_items / $per_page);
        $latest_scan = FRANKDLC()->database->get_latest_scan();
        $is_scanning = FRANKDLC()->database->is_scan_running();
        ?>
        <div class="wrap frankdlc-wrap">
            <div class="frankdlc-header">
                <div class="frankdlc-header-left">
                    <h1>
                        <?php esc_html_e('Frank Dead Link Checker', 'frank-dead-link-checker'); ?>
                        <span style="font-size: 13px; font-weight: 500; background: #e8f0fe; color: #1a73e8; padding: 3px 10px; border-radius: 12px; vertical-align: middle; margin-left: 8px;">v<?php echo esc_html(FRANKDLC_VERSION); ?></span>
                    </h1>
                    <?php if ($latest_scan && $latest_scan->completed_at): ?>
                        <span class="frankdlc-last-scan">
                            <?php
                            /* translators: %s: human-readable time difference */
                            printf(esc_html__('Last scan: %s ago', 'frank-dead-link-checker'), esc_html(human_time_diff(strtotime($latest_scan->completed_at))));
                            ?>
                        </span>
                    <?php endif; ?>
                    <?php
                    $queue_status = FRANKDLC_Queue_Manager::get_status();
                    $queue_icon = $queue_status['is_reliable'] ? 'yes-alt' : 'clock';
                    $queue_class = $queue_status['is_reliable'] ? 'frankdlc-queue-reliable' : 'frankdlc-queue-basic';
                    ?>
                    <span class="frankdlc-queue-status <?php echo esc_attr($queue_class); ?>"
                        title="<?php echo esc_attr($queue_status['is_reliable'] ? __('Reliable background processing', 'frank-dead-link-checker') : __('Basic background processing - install Action Scheduler for better reliability', 'frank-dead-link-checker')); ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr($queue_icon); ?>"></span>
                        <?php echo esc_html($queue_status['method_label']); ?>
                    </span>
                </div>
                <div class="frankdlc-header-actions">
                    <button type="button" id="frankdlc-scan-btn" class="button button-primary button-hero" <?php echo $is_scanning ? 'style="display:none;"' : ''; ?>>
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e('Scan Now', 'frank-dead-link-checker'); ?>
                    </button>
                    <button type="button" id="frankdlc-fresh-scan-btn" class="button button-hero" <?php echo $is_scanning ? 'style="display:none;"' : ''; ?>
                        title="<?php esc_attr_e('Clear all data and start fresh', 'frank-dead-link-checker'); ?>">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Fresh Scan', 'frank-dead-link-checker'); ?>
                    </button>
                    <button type="button" id="frankdlc-stop-btn" class="button button-hero frankdlc-stop-btn" <?php echo $is_scanning ? '' : 'style="display:none;"'; ?>>
                        <span class="dashicons dashicons-no"></span>
                        <?php esc_html_e('Stop Scan', 'frank-dead-link-checker'); ?>
                    </button>
                    <div class="frankdlc-export-dropdown">
                        <button type="button" id="frankdlc-export-btn" class="button button-hero">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e('Export', 'frank-dead-link-checker'); ?>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        <div class="frankdlc-export-menu" style="display:none;">
                            <a href="#" class="frankdlc-export-option" data-format="csv">
                                <span class="dashicons dashicons-media-spreadsheet"></span>
                                <?php esc_html_e('Export as CSV', 'frank-dead-link-checker'); ?>
                            </a>
                            <a href="#" class="frankdlc-export-option" data-format="json">
                                <span class="dashicons dashicons-media-code"></span>
                                <?php esc_html_e('Export as JSON', 'frank-dead-link-checker'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="frankdlc-stats-grid">
                <div class="frankdlc-stat-card frankdlc-stat-broken">
                    <div class="frankdlc-stat-icon"><span class="dashicons dashicons-no-alt"></span></div>
                    <div class="frankdlc-stat-content">
                        <span class="frankdlc-stat-value">
                            <?php echo esc_html($stats['broken']); ?>
                        </span>
                        <span class="frankdlc-stat-label">
                            <?php esc_html_e('Broken Links', 'frank-dead-link-checker'); ?>
                        </span>
                    </div>
                </div>
                <div class="frankdlc-stat-card frankdlc-stat-warning">
                    <div class="frankdlc-stat-icon"><span class="dashicons dashicons-warning"></span></div>
                    <div class="frankdlc-stat-content">
                        <span class="frankdlc-stat-value">
                            <?php echo esc_html($stats['warning']); ?>
                        </span>
                        <span class="frankdlc-stat-label">
                            <?php esc_html_e('Warnings', 'frank-dead-link-checker'); ?>
                        </span>
                    </div>
                </div>
                <div class="frankdlc-stat-card frankdlc-stat-working">
                    <div class="frankdlc-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
                    <div class="frankdlc-stat-content">
                        <span class="frankdlc-stat-value">
                            <?php echo esc_html($stats['working']); ?>
                        </span>
                        <span class="frankdlc-stat-label">
                            <?php esc_html_e('Working', 'frank-dead-link-checker'); ?>
                        </span>
                    </div>
                </div>
                <div class="frankdlc-stat-card frankdlc-stat-total">
                    <div class="frankdlc-stat-icon"><span class="dashicons dashicons-admin-links"></span></div>
                    <div class="frankdlc-stat-content">
                        <span class="frankdlc-stat-value">
                            <?php echo esc_html($stats['total']); ?>
                        </span>
                        <span class="frankdlc-stat-label">
                            <?php esc_html_e('Total Links', 'frank-dead-link-checker'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Scan Progress -->
            <div id="frankdlc-scan-progress" class="frankdlc-scan-progress" style="display: none;">
                <div class="frankdlc-progress-bar">
                    <div class="frankdlc-progress-fill"></div>
                </div>
                <span class="frankdlc-progress-text"></span>
            </div>

            <!-- Filters -->
            <div class="frankdlc-filters">
                <ul class="frankdlc-filter-tabs">
                    <li><a href="<?php echo esc_url(add_query_arg('status', 'all', remove_query_arg(array('paged', 's')))); ?>"
                            class="<?php echo $current_status === 'all' ? 'active' : ''; ?>">
                            <?php esc_html_e('All', 'frank-dead-link-checker'); ?>
                            <span>(<?php echo esc_html($stats['total']); ?>)</span>
                        </a></li>
                    <li><a href="<?php echo esc_url(add_query_arg('status', 'broken', remove_query_arg(array('paged', 's')))); ?>"
                            class="<?php echo $current_status === 'broken' ? 'active' : ''; ?>">
                            <?php esc_html_e('Broken', 'frank-dead-link-checker'); ?>
                            <span>(<?php echo esc_html($stats['broken']); ?>)</span>
                        </a></li>
                    <li><a href="<?php echo esc_url(add_query_arg('status', 'warning', remove_query_arg(array('paged', 's')))); ?>"
                            class="<?php echo $current_status === 'warning' ? 'active' : ''; ?>">
                            <?php esc_html_e('Warnings', 'frank-dead-link-checker'); ?>
                            <span>(<?php echo esc_html($stats['warning']); ?>)</span>
                        </a></li>
                    <li><a href="<?php echo esc_url(add_query_arg('status', 'working', remove_query_arg(array('paged', 's')))); ?>"
                            class="<?php echo $current_status === 'working' ? 'active' : ''; ?>">
                            <?php esc_html_e('Working', 'frank-dead-link-checker'); ?>
                            <span>(<?php echo esc_html($stats['working']); ?>)</span>
                        </a></li>
                    <li><a href="<?php echo esc_url(add_query_arg('status', 'dismissed', remove_query_arg(array('paged', 's')))); ?>"
                            class="<?php echo $current_status === 'dismissed' ? 'active' : ''; ?>">
                            <?php esc_html_e('Dismissed', 'frank-dead-link-checker'); ?>
                            <span>(<?php echo esc_html($stats['dismissed']); ?>)</span>
                        </a></li>
                </ul>
                <div class="frankdlc-filter-right">
                    <form method="post" class="frankdlc-search-form"
                        action="<?php echo esc_url(admin_url('admin.php?page=frank-dead-link-checker&status=' . $current_status)); ?>">
                        <?php wp_nonce_field('FRANKDLC_search_nonce', 'FRANKDLC_search_nonce'); ?>
                        <input type="search" name="FRANKDLC_search" value="<?php echo esc_attr($search); ?>"
                            placeholder="<?php esc_attr_e('Search URLs...', 'frank-dead-link-checker'); ?>">
                        <button type="submit" class="button"><span class="dashicons dashicons-search"></span></button>
                    </form>
                </div>
            </div>

            <!-- Advanced Filters -->
            <div class="frankdlc-advanced-filters">
                <form method="get" class="frankdlc-filter-form">
                    <input type="hidden" name="page" value="frank-dead-link-checker">
                    <input type="hidden" name="status" value="<?php echo esc_attr($current_status); ?>">
                    <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>">
                    <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>">

                    <div class="frankdlc-filter-group">
                        <label><?php esc_html_e('Link Type:', 'frank-dead-link-checker'); ?></label>
                        <select name="link_type">
                            <option value="all" <?php selected($current_type, 'all'); ?>>
                                <?php esc_html_e('All Types', 'frank-dead-link-checker'); ?>
                            </option>
                            <option value="internal" <?php selected($current_type, 'internal'); ?>>
                                <?php esc_html_e('Internal', 'frank-dead-link-checker'); ?>
                            </option>
                            <option value="external" <?php selected($current_type, 'external'); ?>>
                                <?php esc_html_e('External', 'frank-dead-link-checker'); ?>
                            </option>
                            <option value="image" <?php selected($current_type, 'image'); ?>>
                                <?php esc_html_e('Image', 'frank-dead-link-checker'); ?>
                            </option>
                        </select>
                    </div>

                    <div class="frankdlc-filter-group">
                        <label><a href="<?php echo esc_url(admin_url('admin.php?page=frankdlc-help#frankdlc-http-status-codes')); ?>" target="_blank" title="<?php esc_attr_e('View HTTP Status Codes Reference', 'frank-dead-link-checker'); ?>" style="text-decoration:none; color:inherit;"><?php esc_html_e('HTTP Status:', 'frank-dead-link-checker'); ?> <span class="dashicons dashicons-editor-help" style="font-size:14px; width:14px; height:14px; vertical-align:middle; color:#999;"></span></a></label>
                        <select name="http_status">
                            <option value="" <?php selected($http_status, ''); ?>>
                                <?php esc_html_e('Any Status', 'frank-dead-link-checker'); ?>
                            </option>
                            <option value="301" <?php selected($http_status, '301'); ?>><?php esc_html_e('301 Redirect', 'frank-dead-link-checker'); ?></option>
                            <option value="302" <?php selected($http_status, '302'); ?>><?php esc_html_e('302 Redirect', 'frank-dead-link-checker'); ?></option>
                            <option value="401" <?php selected($http_status, '401'); ?>><?php esc_html_e('401 Unauthorized', 'frank-dead-link-checker'); ?></option>
                            <option value="403" <?php selected($http_status, '403'); ?>><?php esc_html_e('403 Forbidden', 'frank-dead-link-checker'); ?></option>
                            <option value="404" <?php selected($http_status, '404'); ?>><?php esc_html_e('404 Not Found', 'frank-dead-link-checker'); ?></option>
                            <option value="405" <?php selected($http_status, '405'); ?>><?php esc_html_e('405 Method Not Allowed', 'frank-dead-link-checker'); ?></option>
                            <option value="406" <?php selected($http_status, '406'); ?>><?php esc_html_e('406 Not Acceptable', 'frank-dead-link-checker'); ?></option>
                            <option value="410" <?php selected($http_status, '410'); ?>><?php esc_html_e('410 Gone', 'frank-dead-link-checker'); ?></option>
                            <option value="429" <?php selected($http_status, '429'); ?>><?php esc_html_e('429 Too Many Requests', 'frank-dead-link-checker'); ?></option>
                            <option value="500" <?php selected($http_status, '500'); ?>><?php esc_html_e('500 Server Error', 'frank-dead-link-checker'); ?></option>
                            <option value="503" <?php selected($http_status, '503'); ?>><?php esc_html_e('503 Service Unavailable', 'frank-dead-link-checker'); ?></option>
                            <option value="error" <?php selected($http_status, 'error'); ?>><?php esc_html_e('Error (No Response)', 'frank-dead-link-checker'); ?></option>
                        </select>
                    </div>

                    <div class="frankdlc-filter-group">
                        <label><?php esc_html_e('Date Range:', 'frank-dead-link-checker'); ?></label>
                        <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="From">
                        <span>-</span>
                        <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="To">
                    </div>

                    <button type="submit"
                        class="button"><?php esc_html_e('Apply Filters', 'frank-dead-link-checker'); ?></button>
                    <?php if ($current_type !== 'all' || $http_status || $date_from || $date_to): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=frank-dead-link-checker&status=' . $current_status)); ?>"
                            class="button frankdlc-clear-filters"><?php esc_html_e('Clear', 'frank-dead-link-checker'); ?></a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Bulk Actions -->
            <div class="frankdlc-bulk-actions" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                <div style="display:flex; align-items:center; gap:6px;">
                    <select id="frankdlc-bulk-action">
                        <option value="">
                            <?php esc_html_e('Bulk Actions', 'frank-dead-link-checker'); ?>
                        </option>
                        <option value="recheck">
                            <?php esc_html_e('Recheck', 'frank-dead-link-checker'); ?>
                        </option>
                        <option value="dismiss">
                            <?php esc_html_e('Dismiss', 'frank-dead-link-checker'); ?>
                        </option>
                        <option value="undismiss">
                            <?php esc_html_e('Restore', 'frank-dead-link-checker'); ?>
                        </option>
                        <option value="delete">
                            <?php esc_html_e('Delete', 'frank-dead-link-checker'); ?>
                        </option>
                    </select>
                    <button type="button" id="frankdlc-bulk-apply" class="button">
                        <?php esc_html_e('Apply', 'frank-dead-link-checker'); ?>
                    </button>
                    <span class="frankdlc-bulk-result"></span>
                </div>
                <div class="frankdlc-per-page" style="display:flex; align-items:center; gap:6px;">
                    <label for="frankdlc-per-page-select" style="font-size:13px; white-space:nowrap;"><?php esc_html_e('Rows per page:', 'frank-dead-link-checker'); ?></label>
                    <select id="frankdlc-per-page-select" style="width:auto; min-width:60px;" onchange="var url=new window.URL(window.location.href); url.searchParams.set('per_page',this.value); url.searchParams.set('paged','1'); window.location.href=url.toString();">
                        <?php foreach (array(10, 20, 50, 100, 200) as $pp): ?>
                            <option value="<?php echo esc_attr($pp); ?>" <?php selected($per_page, $pp); ?>><?php echo esc_html($pp); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Links Table -->
            <?php
            // Helper function to build sortable header
            $build_sort_url = function ($column) use ($orderby, $order, $current_status, $current_type, $search, $date_from, $date_to, $http_status, $per_page) {
                $new_order = ($orderby === $column && $order === 'ASC') ? 'DESC' : 'ASC';
                return add_query_arg(array(
                    'page' => 'frank-dead-link-checker',
                    'status' => $current_status,
                    'link_type' => $current_type,
                    's' => $search,
                    'orderby' => $column,
                    'order' => $new_order,
                    'date_from' => $date_from,
                    'date_to' => $date_to,
                    'http_status' => $http_status,
                    'per_page' => $per_page,
                ));
            };
            $sort_class = function ($column) use ($orderby, $order) {
                if ($orderby !== $column)
                    return 'sortable';
                return 'sorted ' . strtolower($order);
            };
            ?>
            <table class="frankdlc-links-table wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="frankdlc-select-all"></th>
                        <th class="frankdlc-col-status <?php echo esc_attr($sort_class('status_code')); ?>">
                            <a href="<?php echo esc_url($build_sort_url('status_code')); ?>">
                                <span><?php esc_html_e('Status', 'frank-dead-link-checker'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="frankdlc-col-url <?php echo esc_attr($sort_class('url')); ?>">
                            <a href="<?php echo esc_url($build_sort_url('url')); ?>">
                                <span><?php esc_html_e('URL', 'frank-dead-link-checker'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="frankdlc-col-source">
                            <?php esc_html_e('Source', 'frank-dead-link-checker'); ?>
                        </th>
                        <th class="frankdlc-col-type <?php echo esc_attr($sort_class('link_type')); ?>">
                            <a href="<?php echo esc_url($build_sort_url('link_type')); ?>">
                                <span><?php esc_html_e('Type', 'frank-dead-link-checker'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="frankdlc-col-checked <?php echo esc_attr($sort_class('last_check')); ?>">
                            <a href="<?php echo esc_url($build_sort_url('last_check')); ?>">
                                <span><?php esc_html_e('Last Check', 'frank-dead-link-checker'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="frankdlc-col-actions">
                            <?php esc_html_e('Actions', 'frank-dead-link-checker'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($links)): ?>
                        <tr>
                            <td colspan="6" class="frankdlc-no-items">
                                <?php if ($current_status === 'broken'): ?>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php esc_html_e('No broken links found. Great job!', 'frank-dead-link-checker'); ?>
                                <?php else: ?>
                                    <?php esc_html_e('No links found. Run a scan to discover links.', 'frank-dead-link-checker'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($links as $link_data):
                            $link = new FRANKDLC_Link($link_data); ?>
                            <tr data-link-id="<?php echo esc_attr($link->id); ?>">
                                <td class="check-column"><input type="checkbox" class="frankdlc-link-checkbox"
                                        value="<?php echo esc_attr($link->id); ?>"></td>
                                <td class="frankdlc-col-status">
                                    <?php
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_status_badge() returns pre-escaped HTML
                                    echo $link->get_status_badge();
                                    ?>
                                </td>
                                <td class="frankdlc-col-url">
                                    <div class="frankdlc-url-wrap">
                                        <a href="<?php echo esc_url($link->url); ?>" target="_blank" rel="noopener"
                                            title="<?php echo esc_attr($link->url); ?>">
                                            <?php echo esc_html($link->get_display_url(50)); ?>
                                        </a>
                                        <?php if ($link->redirect_url): ?>
                                            <span class="frankdlc-redirect-info">→
                                                <?php
                                                /* translators: %d: number of redirects */
                                                echo esc_html(sprintf(_n('%d redirect', '%d redirects', $link->redirect_count, 'frank-dead-link-checker'), $link->redirect_count));
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($link->anchor_text): ?>
                                            <span class="frankdlc-anchor-text">"<?php echo esc_html(wp_trim_words($link->anchor_text, 5)); ?>"</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="frankdlc-col-source">
                                    <?php $edit_url = $link->get_source_edit_url();
                                    if ($edit_url): ?>
                                        <a href="<?php echo esc_url($edit_url); ?>">
                                            <?php echo esc_html(wp_trim_words($link->get_source_title(), 5)); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo esc_html($link->get_source_title()); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="frankdlc-col-type"><span
                                        class="frankdlc-type-badge frankdlc-type-<?php echo esc_attr($link->link_type); ?>">
                                        <?php echo esc_html($link->get_type_label()); ?>
                                    </span></td>
                                <td class="frankdlc-col-checked">
                                    <?php if ($link->last_check): ?>
                                        <span title="<?php echo esc_attr($link->last_check); ?>">
                                            <?php
                                            /* translators: %s: human-readable time difference */
                                            echo esc_html(sprintf(__('%s ago', 'frank-dead-link-checker'), human_time_diff(strtotime($link->last_check)))); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="frankdlc-unchecked"><?php esc_html_e('Never', 'frank-dead-link-checker'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="frankdlc-col-actions">
                                    <div class="frankdlc-actions-wrap">
                                        <button type="button" class="frankdlc-action-btn frankdlc-recheck"
                                            data-id="<?php echo esc_attr($link->id); ?>"
                                            title="<?php esc_attr_e('Upgrade to Pro to Recheck', 'frank-dead-link-checker'); ?>" disabled style="opacity: 0.5; cursor: not-allowed;"><span
                                                class="dashicons dashicons-update"></span></button>
                                        <button type="button" class="frankdlc-action-btn frankdlc-edit"
                                            data-id="<?php echo esc_attr($link->id); ?>" data-url="<?php echo esc_attr($link->url); ?>" data-anchor="<?php echo esc_attr($link->anchor_text); ?>"
                                            title="<?php esc_attr_e('Edit Link', 'frank-dead-link-checker'); ?>"><span
                                                class="dashicons dashicons-edit"></span></button>
                                        <?php if ($link->is_broken): ?>
                                            <button type="button" class="frankdlc-action-btn frankdlc-redirect"
                                                data-id="<?php echo esc_attr($link->id); ?>" data-url="<?php echo esc_attr($link->url); ?>"
                                                title="<?php esc_attr_e('Create Redirect', 'frank-dead-link-checker'); ?>"><span
                                                    class="dashicons dashicons-randomize"></span></button>
                                        <?php endif; ?>
                                        <?php if ($link->is_dismissed): ?>
                                            <button type="button" class="frankdlc-action-btn frankdlc-undismiss"
                                                data-id="<?php echo esc_attr($link->id); ?>"
                                                title="<?php esc_attr_e('Restore', 'frank-dead-link-checker'); ?>"><span
                                                    class="dashicons dashicons-visibility"></span></button>
                                        <?php else: ?>
                                            <button type="button" class="frankdlc-action-btn frankdlc-dismiss"
                                                data-id="<?php echo esc_attr($link->id); ?>"
                                                title="<?php esc_attr_e('Dismiss', 'frank-dead-link-checker'); ?>"><span
                                                    class="dashicons dashicons-hidden"></span></button>
                                        <?php endif; ?>
                                        <button type="button" class="frankdlc-action-btn frankdlc-delete"
                                            data-id="<?php echo esc_attr($link->id); ?>"
                                            title="<?php esc_attr_e('Delete', 'frank-dead-link-checker'); ?>"><span
                                                class="dashicons dashicons-trash"></span></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="frankdlc-pagination">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links returns safe HTML
                    echo paginate_links(array(
                        'base' => add_query_arg(array('paged' => '%#%', 'per_page' => $per_page)),
                        'format' => '',
                        'current' => $paged,
                        'total' => $total_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    ));
                    ?>
                </div>
            <?php endif; ?>

            <!-- Edit Modal -->
            <div id="frankdlc-edit-modal" class="frankdlc-modal" style="display:none;">
                <div class="frankdlc-modal-content">
                    <div class="frankdlc-modal-header">
                        <h3>
                            <?php esc_html_e('Edit Link', 'frank-dead-link-checker'); ?>
                        </h3>
                        <button type="button" class="frankdlc-modal-close">&times;</button>
                    </div>
                    <div class="frankdlc-modal-body">
                        <p><label>
                                <?php esc_html_e('Current URL:', 'frank-dead-link-checker'); ?>
                            </label><input type="text" id="frankdlc-edit-old-url" readonly></p>
                        <p><label>
                                <?php esc_html_e('New URL:', 'frank-dead-link-checker'); ?>
                            </label><input type="url" id="frankdlc-edit-new-url" placeholder="https://"></p>
                        <p><label>
                                <?php esc_html_e('Anchor Text:', 'frank-dead-link-checker'); ?>
                            </label><input type="text" id="frankdlc-edit-anchor-text" placeholder="<?php esc_attr_e('Leave empty to keep current', 'frank-dead-link-checker'); ?>"></p>
                        <input type="hidden" id="frankdlc-edit-link-id">
                    </div>
                    <div class="frankdlc-modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
                        <button type="button" class="button" id="frankdlc-remove-link" style="color:#a00; border-color:#a00; opacity: 0.5; cursor: not-allowed;" disabled title="<?php esc_attr_e('Upgrade to Pro to Remove Link', 'frank-dead-link-checker'); ?>">
                            <span class="dashicons dashicons-editor-unlink" style="vertical-align:middle; margin-right:2px;"></span>
                            <?php esc_html_e('Remove Link', 'frank-dead-link-checker'); ?>
                        </button>
                        <div>
                            <button type="button" class="button frankdlc-modal-cancel">
                                <?php esc_html_e('Cancel', 'frank-dead-link-checker'); ?>
                            </button>
                            <button type="button" class="button button-primary" id="frankdlc-edit-save" disabled style="opacity: 0.5; cursor: not-allowed;" title="<?php esc_attr_e('Upgrade to Pro to Update Link', 'frank-dead-link-checker'); ?>">
                                <?php esc_html_e('Update Link', 'frank-dead-link-checker'); ?>
                            </button>
                        </div>
                    </div>
                     <div class="frankdlc-modal-footer" style="border-top: 1px solid #ddd; padding-top: 10px; margin-top: 10px; text-align: center;">
                        <span class="dashicons dashicons-lock" style="vertical-align: middle; color: #555;"></span>
                        <a href="https://awplife.com/wordpress-plugins/dead-link-checker-pro/" target="_blank" style="font-weight: bold; text-decoration: none; color: #d63638; vertical-align: middle;">
                            <?php esc_html_e('Upgrade to Pro to Edit & Remove Links', 'frank-dead-link-checker'); ?>
                        </a>
                     </div>
                </div>
            </div>

            <!-- Redirect Modal -->
            <div id="frankdlc-redirect-modal" class="frankdlc-modal" style="display:none;">
                <div class="frankdlc-modal-content">
                    <div class="frankdlc-modal-header">
                        <h3>
                            <?php esc_html_e('Create Redirect', 'frank-dead-link-checker'); ?>
                        </h3>
                        <button type="button" class="frankdlc-modal-close">&times;</button>
                    </div>
                    <div class="frankdlc-modal-body">
                        <p class="frankdlc-redirect-info">
                            <span class="dashicons dashicons-info"></span>
                            <?php esc_html_e('Create a redirect so visitors to the broken URL will be sent to a new working URL.', 'frank-dead-link-checker'); ?>
                        </p>
                        <p><label>
                                <?php esc_html_e('Broken URL (From):', 'frank-dead-link-checker'); ?>
                            </label><input type="text" id="frankdlc-redirect-source-url" readonly></p>
                        <p><label>
                                <?php esc_html_e('Target URL (To):', 'frank-dead-link-checker'); ?>
                            </label><input type="url" id="frankdlc-redirect-target-url" placeholder="https://"></p>
                        <p><label>
                                <?php esc_html_e('Redirect Type:', 'frank-dead-link-checker'); ?>
                            </label>
                            <select id="frankdlc-redirect-type">
                                <option value="301">
                                    <?php esc_html_e('301 - Permanent Redirect (Recommended)', 'frank-dead-link-checker'); ?>
                                </option>
                                <option value="302"><?php esc_html_e('302 - Temporary Redirect', 'frank-dead-link-checker'); ?>
                                </option>
                                <option value="307">
                                    <?php esc_html_e('307 - Temporary Redirect (Strict)', 'frank-dead-link-checker'); ?>
                                </option>
                            </select>
                        </p>
                        <input type="hidden" id="frankdlc-redirect-link-id">
                    </div>
                    <div class="frankdlc-modal-footer">
                        <button type="button" class="button frankdlc-modal-cancel">
                            <?php esc_html_e('Cancel', 'frank-dead-link-checker'); ?>
                        </button>
                        <button type="button" class="button button-primary" id="frankdlc-redirect-save">
                            <?php esc_html_e('Create Redirect', 'frank-dead-link-checker'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
