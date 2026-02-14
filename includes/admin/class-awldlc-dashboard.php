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

class AWLDLC_Dashboard
{

    public function render_page()
    {
        $stats = awldlc()->database->get_stats();
        $current_status = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'all';
        $current_type = isset($_GET['link_type']) ? sanitize_key($_GET['link_type']) : 'all';

        // Handle search from POST (to allow URLs with special characters)
        $search = '';
        if (isset($_POST['awldlc_search']) && isset($_POST['awldlc_search_nonce'])) {
            if (wp_verify_nonce($_POST['awldlc_search_nonce'], 'awldlc_search_nonce')) {
                $search = sanitize_text_field(wp_unslash($_POST['awldlc_search']));
            }
        }

        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $allowed_per_page = array(10, 20, 50, 100, 200);
        $per_page = isset($_GET['per_page']) ? absint($_GET['per_page']) : 10;
        if (!in_array($per_page, $allowed_per_page, true)) {
            $per_page = 10;
        }

        // Sorting params
        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'last_check';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Advanced filters
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $http_status = isset($_GET['http_status']) ? sanitize_text_field($_GET['http_status']) : '';

        $links = awldlc()->database->get_links(array(
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

        $total_items = awldlc()->database->get_links_count(array(
            'status' => $current_status,
            'link_type' => $current_type,
            'search' => $search,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'http_status' => $http_status,
        ));

        $total_pages = ceil($total_items / $per_page);
        $latest_scan = awldlc()->database->get_latest_scan();
        $is_scanning = awldlc()->database->is_scan_running();
        ?>
        <div class="wrap awldlc-wrap">
            <div class="awldlc-header">
                <div class="awldlc-header-left">
                    <h1>
                        <?php esc_html_e('Dead Link Checker', 'dead-link-checker'); ?>
                        <span style="font-size: 13px; font-weight: 500; background: #e8f0fe; color: #1a73e8; padding: 3px 10px; border-radius: 12px; vertical-align: middle; margin-left: 8px;">v<?php echo esc_html(AWLDLC_VERSION); ?></span>
                    </h1>
                    <?php if ($latest_scan && $latest_scan->completed_at): ?>
                        <span class="awldlc-last-scan">
                            <?php printf(esc_html__('Last scan: %s ago', 'dead-link-checker'), human_time_diff(strtotime($latest_scan->completed_at))); ?>
                        </span>
                    <?php endif; ?>
                    <?php
                    $queue_status = AWLDLC_Queue_Manager::get_status();
                    $queue_icon = $queue_status['is_reliable'] ? 'yes-alt' : 'clock';
                    $queue_class = $queue_status['is_reliable'] ? 'awldlc-queue-reliable' : 'awldlc-queue-basic';
                    ?>
                    <span class="awldlc-queue-status <?php echo esc_attr($queue_class); ?>"
                        title="<?php echo esc_attr($queue_status['is_reliable'] ? __('Reliable background processing', 'dead-link-checker') : __('Basic background processing - install Action Scheduler for better reliability', 'dead-link-checker')); ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr($queue_icon); ?>"></span>
                        <?php echo esc_html($queue_status['method_label']); ?>
                    </span>
                </div>
                <div class="awldlc-header-actions">
                    <button type="button" id="awldlc-scan-btn" class="button button-primary button-hero" <?php echo $is_scanning ? 'style="display:none;"' : ''; ?>>
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e('Scan Now', 'dead-link-checker'); ?>
                    </button>
                    <button type="button" id="awldlc-fresh-scan-btn" class="button button-hero" <?php echo $is_scanning ? 'style="display:none;"' : ''; ?>
                        title="<?php esc_attr_e('Clear all data and start fresh', 'dead-link-checker'); ?>">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Fresh Scan', 'dead-link-checker'); ?>
                    </button>
                    <button type="button" id="awldlc-stop-btn" class="button button-hero awldlc-stop-btn" <?php echo $is_scanning ? '' : 'style="display:none;"'; ?>>
                        <span class="dashicons dashicons-no"></span>
                        <?php esc_html_e('Stop Scan', 'dead-link-checker'); ?>
                    </button>
                    <div class="awldlc-export-dropdown">
                        <button type="button" id="awldlc-export-btn" class="button button-hero">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e('Export', 'dead-link-checker'); ?>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        <div class="awldlc-export-menu" style="display:none;">
                            <a href="#" class="awldlc-export-option" data-format="csv">
                                <span class="dashicons dashicons-media-spreadsheet"></span>
                                <?php esc_html_e('Export as CSV', 'dead-link-checker'); ?>
                            </a>
                            <a href="#" class="awldlc-export-option" data-format="json">
                                <span class="dashicons dashicons-media-code"></span>
                                <?php esc_html_e('Export as JSON', 'dead-link-checker'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="awldlc-stats-grid">
                <div class="awldlc-stat-card awldlc-stat-broken">
                    <div class="awldlc-stat-icon"><span class="dashicons dashicons-no-alt"></span></div>
                    <div class="awldlc-stat-content">
                        <span class="awldlc-stat-value">
                            <?php echo esc_html($stats['broken']); ?>
                        </span>
                        <span class="awldlc-stat-label">
                            <?php esc_html_e('Broken Links', 'dead-link-checker'); ?>
                        </span>
                    </div>
                </div>
                <div class="awldlc-stat-card awldlc-stat-warning">
                    <div class="awldlc-stat-icon"><span class="dashicons dashicons-warning"></span></div>
                    <div class="awldlc-stat-content">
                        <span class="awldlc-stat-value">
                            <?php echo esc_html($stats['warning']); ?>
                        </span>
                        <span class="awldlc-stat-label">
                            <?php esc_html_e('Warnings', 'dead-link-checker'); ?>
                        </span>
                    </div>
                </div>
                <div class="awldlc-stat-card awldlc-stat-working">
                    <div class="awldlc-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
                    <div class="awldlc-stat-content">
                        <span class="awldlc-stat-value">
                            <?php echo esc_html($stats['working']); ?>
                        </span>
                        <span class="awldlc-stat-label">
                            <?php esc_html_e('Working', 'dead-link-checker'); ?>
                        </span>
                    </div>
                </div>
                <div class="awldlc-stat-card awldlc-stat-total">
                    <div class="awldlc-stat-icon"><span class="dashicons dashicons-admin-links"></span></div>
                    <div class="awldlc-stat-content">
                        <span class="awldlc-stat-value">
                            <?php echo esc_html($stats['total']); ?>
                        </span>
                        <span class="awldlc-stat-label">
                            <?php esc_html_e('Total Links', 'dead-link-checker'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Scan Progress -->
            <div id="awldlc-scan-progress" class="awldlc-scan-progress" style="display: none;">
                <div class="awldlc-progress-bar">
                    <div class="awldlc-progress-fill"></div>
                </div>
                <span class="awldlc-progress-text"></span>
            </div>

            <!-- Filters -->
            <div class="awldlc-filters">
                <ul class="awldlc-filter-tabs">
                    <li><a href="<?php echo esc_url(add_query_arg('status', 'all', remove_query_arg(array('paged', 's')))); ?>"
                            class="<?php echo $current_status === 'all' ? 'active' : ''; ?>">
                            <?php esc_html_e('All', 'dead-link-checker'); ?>
                            <span>(<?php echo esc_html($stats['total']); ?>)</span>
                        </a></li>
                    <li><a href="<?php echo esc_url(add_query_arg('status', 'broken', remove_query_arg(array('paged', 's')))); ?>"
                            class="<?php echo $current_status === 'broken' ? 'active' : ''; ?>">
                            <?php esc_html_e('Broken', 'dead-link-checker'); ?>
                            <span>(<?php echo esc_html($stats['broken']); ?>)</span>
                        </a></li>
                    <li><a href="<?php echo esc_url(add_query_arg('status', 'warning', remove_query_arg(array('paged', 's')))); ?>"
                            class="<?php echo $current_status === 'warning' ? 'active' : ''; ?>">
                            <?php esc_html_e('Warnings', 'dead-link-checker'); ?>
                            <span>(<?php echo esc_html($stats['warning']); ?>)</span>
                        </a></li>
                    <li><a href="<?php echo esc_url(add_query_arg('status', 'working', remove_query_arg(array('paged', 's')))); ?>"
                            class="<?php echo $current_status === 'working' ? 'active' : ''; ?>">
                            <?php esc_html_e('Working', 'dead-link-checker'); ?>
                            <span>(<?php echo esc_html($stats['working']); ?>)</span>
                        </a></li>
                    <li><a href="<?php echo esc_url(add_query_arg('status', 'dismissed', remove_query_arg(array('paged', 's')))); ?>"
                            class="<?php echo $current_status === 'dismissed' ? 'active' : ''; ?>">
                            <?php esc_html_e('Dismissed', 'dead-link-checker'); ?>
                            <span>(<?php echo esc_html($stats['dismissed']); ?>)</span>
                        </a></li>
                </ul>
                <div class="awldlc-filter-right">
                    <form method="post" class="awldlc-search-form"
                        action="<?php echo esc_url(admin_url('admin.php?page=dead-link-checker&status=' . $current_status)); ?>">
                        <?php wp_nonce_field('awldlc_search_nonce', 'awldlc_search_nonce'); ?>
                        <input type="search" name="awldlc_search" value="<?php echo esc_attr($search); ?>"
                            placeholder="<?php esc_attr_e('Search URLs...', 'dead-link-checker'); ?>">
                        <button type="submit" class="button"><span class="dashicons dashicons-search"></span></button>
                    </form>
                </div>
            </div>

            <!-- Advanced Filters -->
            <div class="awldlc-advanced-filters">
                <form method="get" class="awldlc-filter-form">
                    <input type="hidden" name="page" value="dead-link-checker">
                    <input type="hidden" name="status" value="<?php echo esc_attr($current_status); ?>">
                    <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>">
                    <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>">

                    <div class="awldlc-filter-group">
                        <label><?php esc_html_e('Link Type:', 'dead-link-checker'); ?></label>
                        <select name="link_type">
                            <option value="all" <?php selected($current_type, 'all'); ?>>
                                <?php esc_html_e('All Types', 'dead-link-checker'); ?>
                            </option>
                            <option value="internal" <?php selected($current_type, 'internal'); ?>>
                                <?php esc_html_e('Internal', 'dead-link-checker'); ?>
                            </option>
                            <option value="external" <?php selected($current_type, 'external'); ?>>
                                <?php esc_html_e('External', 'dead-link-checker'); ?>
                            </option>
                            <option value="image" <?php selected($current_type, 'image'); ?>>
                                <?php esc_html_e('Image', 'dead-link-checker'); ?>
                            </option>
                        </select>
                    </div>

                    <div class="awldlc-filter-group">
                        <label><a href="<?php echo esc_url(admin_url('admin.php?page=awldlc-help#awldlc-http-status-codes')); ?>" target="_blank" title="<?php esc_attr_e('View HTTP Status Codes Reference', 'dead-link-checker'); ?>" style="text-decoration:none; color:inherit;"><?php esc_html_e('HTTP Status:', 'dead-link-checker'); ?> <span class="dashicons dashicons-editor-help" style="font-size:14px; width:14px; height:14px; vertical-align:middle; color:#999;"></span></a></label>
                        <select name="http_status">
                            <option value="" <?php selected($http_status, ''); ?>>
                                <?php esc_html_e('Any Status', 'dead-link-checker'); ?>
                            </option>
                            <option value="301" <?php selected($http_status, '301'); ?>><?php esc_html_e('301 Redirect', 'dead-link-checker'); ?></option>
                            <option value="302" <?php selected($http_status, '302'); ?>><?php esc_html_e('302 Redirect', 'dead-link-checker'); ?></option>
                            <option value="401" <?php selected($http_status, '401'); ?>><?php esc_html_e('401 Unauthorized', 'dead-link-checker'); ?></option>
                            <option value="403" <?php selected($http_status, '403'); ?>><?php esc_html_e('403 Forbidden', 'dead-link-checker'); ?></option>
                            <option value="404" <?php selected($http_status, '404'); ?>><?php esc_html_e('404 Not Found', 'dead-link-checker'); ?></option>
                            <option value="405" <?php selected($http_status, '405'); ?>><?php esc_html_e('405 Method Not Allowed', 'dead-link-checker'); ?></option>
                            <option value="406" <?php selected($http_status, '406'); ?>><?php esc_html_e('406 Not Acceptable', 'dead-link-checker'); ?></option>
                            <option value="410" <?php selected($http_status, '410'); ?>><?php esc_html_e('410 Gone', 'dead-link-checker'); ?></option>
                            <option value="429" <?php selected($http_status, '429'); ?>><?php esc_html_e('429 Too Many Requests', 'dead-link-checker'); ?></option>
                            <option value="500" <?php selected($http_status, '500'); ?>><?php esc_html_e('500 Server Error', 'dead-link-checker'); ?></option>
                            <option value="503" <?php selected($http_status, '503'); ?>><?php esc_html_e('503 Service Unavailable', 'dead-link-checker'); ?></option>
                            <option value="error" <?php selected($http_status, 'error'); ?>><?php esc_html_e('Error (No Response)', 'dead-link-checker'); ?></option>
                        </select>
                    </div>

                    <div class="awldlc-filter-group">
                        <label><?php esc_html_e('Date Range:', 'dead-link-checker'); ?></label>
                        <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="From">
                        <span>-</span>
                        <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="To">
                    </div>

                    <button type="submit"
                        class="button"><?php esc_html_e('Apply Filters', 'dead-link-checker'); ?></button>
                    <?php if ($current_type !== 'all' || $http_status || $date_from || $date_to): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=dead-link-checker&status=' . $current_status)); ?>"
                            class="button awldlc-clear-filters"><?php esc_html_e('Clear', 'dead-link-checker'); ?></a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Bulk Actions -->
            <div class="awldlc-bulk-actions" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                <div style="display:flex; align-items:center; gap:6px;">
                    <select id="awldlc-bulk-action">
                        <option value="">
                            <?php esc_html_e('Bulk Actions', 'dead-link-checker'); ?>
                        </option>
                        <option value="recheck">
                            <?php esc_html_e('Recheck', 'dead-link-checker'); ?>
                        </option>
                        <option value="dismiss">
                            <?php esc_html_e('Dismiss', 'dead-link-checker'); ?>
                        </option>
                        <option value="undismiss">
                            <?php esc_html_e('Restore', 'dead-link-checker'); ?>
                        </option>
                        <option value="delete">
                            <?php esc_html_e('Delete', 'dead-link-checker'); ?>
                        </option>
                    </select>
                    <button type="button" id="awldlc-bulk-apply" class="button">
                        <?php esc_html_e('Apply', 'dead-link-checker'); ?>
                    </button>
                    <span class="awldlc-bulk-result"></span>
                </div>
                <div class="awldlc-per-page" style="display:flex; align-items:center; gap:6px;">
                    <label for="awldlc-per-page-select" style="font-size:13px; white-space:nowrap;"><?php esc_html_e('Rows per page:', 'dead-link-checker'); ?></label>
                    <select id="awldlc-per-page-select" style="width:auto; min-width:60px;" onchange="var url=new window.URL(window.location.href); url.searchParams.set('per_page',this.value); url.searchParams.set('paged','1'); window.location.href=url.toString();">
                        <?php foreach (array(10, 20, 50, 100, 200) as $pp): ?>
                            <option value="<?php echo $pp; ?>" <?php selected($per_page, $pp); ?>><?php echo $pp; ?></option>
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
                    'page' => 'dead-link-checker',
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
            <table class="awldlc-links-table wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="awldlc-select-all"></th>
                        <th class="awldlc-col-status <?php echo esc_attr($sort_class('status_code')); ?>">
                            <a href="<?php echo esc_url($build_sort_url('status_code')); ?>">
                                <span><?php esc_html_e('Status', 'dead-link-checker'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="awldlc-col-url <?php echo esc_attr($sort_class('url')); ?>">
                            <a href="<?php echo esc_url($build_sort_url('url')); ?>">
                                <span><?php esc_html_e('URL', 'dead-link-checker'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="awldlc-col-source">
                            <?php esc_html_e('Source', 'dead-link-checker'); ?>
                        </th>
                        <th class="awldlc-col-type <?php echo esc_attr($sort_class('link_type')); ?>">
                            <a href="<?php echo esc_url($build_sort_url('link_type')); ?>">
                                <span><?php esc_html_e('Type', 'dead-link-checker'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="awldlc-col-checked <?php echo esc_attr($sort_class('last_check')); ?>">
                            <a href="<?php echo esc_url($build_sort_url('last_check')); ?>">
                                <span><?php esc_html_e('Last Check', 'dead-link-checker'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="awldlc-col-actions">
                            <?php esc_html_e('Actions', 'dead-link-checker'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($links)): ?>
                        <tr>
                            <td colspan="6" class="awldlc-no-items">
                                <?php if ($current_status === 'broken'): ?>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php esc_html_e('No broken links found. Great job!', 'dead-link-checker'); ?>
                                <?php else: ?>
                                    <?php esc_html_e('No links found. Run a scan to discover links.', 'dead-link-checker'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($links as $link_data):
                            $link = new AWLDLC_Link($link_data); ?>
                            <tr data-link-id="<?php echo esc_attr($link->id); ?>">
                                <td class="check-column"><input type="checkbox" class="awldlc-link-checkbox"
                                        value="<?php echo esc_attr($link->id); ?>"></td>
                                <td class="awldlc-col-status">
                                    <?php echo $link->get_status_badge(); ?>
                                </td>
                                <td class="awldlc-col-url">
                                    <div class="awldlc-url-wrap">
                                        <a href="<?php echo esc_url($link->url); ?>" target="_blank" rel="noopener"
                                            title="<?php echo esc_attr($link->url); ?>">
                                            <?php echo esc_html($link->get_display_url(50)); ?>
                                        </a>
                                        <?php if ($link->redirect_url): ?>
                                            <span class="awldlc-redirect-info">→
                                                <?php echo esc_html(sprintf(_n('%d redirect', '%d redirects', $link->redirect_count, 'dead-link-checker'), $link->redirect_count)); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($link->anchor_text): ?>
                                            <span class="awldlc-anchor-text">"<?php echo esc_html(wp_trim_words($link->anchor_text, 5)); ?>"</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="awldlc-col-source">
                                    <?php $edit_url = $link->get_source_edit_url();
                                    if ($edit_url): ?>
                                        <a href="<?php echo esc_url($edit_url); ?>">
                                            <?php echo esc_html(wp_trim_words($link->get_source_title(), 5)); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo esc_html($link->get_source_title()); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="awldlc-col-type"><span
                                        class="awldlc-type-badge awldlc-type-<?php echo esc_attr($link->link_type); ?>">
                                        <?php echo esc_html($link->get_type_label()); ?>
                                    </span></td>
                                <td class="awldlc-col-checked">
                                    <?php if ($link->last_check): ?>
                                        <span title="<?php echo esc_attr($link->last_check); ?>">
                                            <?php echo esc_html(sprintf(__('%s ago', 'dead-link-checker'), human_time_diff(strtotime($link->last_check)))); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="awldlc-unchecked"><?php esc_html_e('Never', 'dead-link-checker'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="awldlc-col-actions">
                                    <div class="awldlc-actions-wrap">
                                        <button type="button" class="awldlc-action-btn awldlc-recheck"
                                            data-id="<?php echo esc_attr($link->id); ?>"
                                            title="<?php esc_attr_e('Recheck', 'dead-link-checker'); ?>"><span
                                                class="dashicons dashicons-update"></span></button>
                                        <button type="button" class="awldlc-action-btn awldlc-edit"
                                            data-id="<?php echo esc_attr($link->id); ?>" data-url="<?php echo esc_attr($link->url); ?>" data-anchor="<?php echo esc_attr($link->anchor_text); ?>"
                                            title="<?php esc_attr_e('Edit Link', 'dead-link-checker'); ?>"><span
                                                class="dashicons dashicons-edit"></span></button>
                                        <?php if ($link->is_broken): ?>
                                            <button type="button" class="awldlc-action-btn awldlc-redirect"
                                                data-id="<?php echo esc_attr($link->id); ?>" data-url="<?php echo esc_attr($link->url); ?>"
                                                title="<?php esc_attr_e('Create Redirect', 'dead-link-checker'); ?>"><span
                                                    class="dashicons dashicons-randomize"></span></button>
                                        <?php endif; ?>
                                        <?php if ($link->is_dismissed): ?>
                                            <button type="button" class="awldlc-action-btn awldlc-undismiss"
                                                data-id="<?php echo esc_attr($link->id); ?>"
                                                title="<?php esc_attr_e('Restore', 'dead-link-checker'); ?>"><span
                                                    class="dashicons dashicons-visibility"></span></button>
                                        <?php else: ?>
                                            <button type="button" class="awldlc-action-btn awldlc-dismiss"
                                                data-id="<?php echo esc_attr($link->id); ?>"
                                                title="<?php esc_attr_e('Dismiss', 'dead-link-checker'); ?>"><span
                                                    class="dashicons dashicons-hidden"></span></button>
                                        <?php endif; ?>
                                        <button type="button" class="awldlc-action-btn awldlc-delete"
                                            data-id="<?php echo esc_attr($link->id); ?>"
                                            title="<?php esc_attr_e('Delete', 'dead-link-checker'); ?>"><span
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
                <div class="awldlc-pagination">
                    <?php
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
            <div id="awldlc-edit-modal" class="awldlc-modal" style="display:none;">
                <div class="awldlc-modal-content">
                    <div class="awldlc-modal-header">
                        <h3>
                            <?php esc_html_e('Edit Link', 'dead-link-checker'); ?>
                        </h3>
                        <button type="button" class="awldlc-modal-close">&times;</button>
                    </div>
                    <div class="awldlc-modal-body">
                        <p><label>
                                <?php esc_html_e('Current URL:', 'dead-link-checker'); ?>
                            </label><input type="text" id="awldlc-edit-old-url" readonly></p>
                        <p><label>
                                <?php esc_html_e('New URL:', 'dead-link-checker'); ?>
                            </label><input type="url" id="awldlc-edit-new-url" placeholder="https://"></p>
                        <p><label>
                                <?php esc_html_e('Anchor Text:', 'dead-link-checker'); ?>
                            </label><input type="text" id="awldlc-edit-anchor-text" placeholder="<?php esc_attr_e('Leave empty to keep current', 'dead-link-checker'); ?>"></p>
                        <input type="hidden" id="awldlc-edit-link-id">
                    </div>
                    <div class="awldlc-modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
                        <button type="button" class="button" id="awldlc-remove-link" style="color:#a00; border-color:#a00;">
                            <span class="dashicons dashicons-editor-unlink" style="vertical-align:middle; margin-right:2px;"></span>
                            <?php esc_html_e('Remove Link', 'dead-link-checker'); ?>
                        </button>
                        <div>
                            <button type="button" class="button awldlc-modal-cancel">
                                <?php esc_html_e('Cancel', 'dead-link-checker'); ?>
                            </button>
                            <button type="button" class="button button-primary" id="awldlc-edit-save">
                                <?php esc_html_e('Update Link', 'dead-link-checker'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Redirect Modal -->
            <div id="awldlc-redirect-modal" class="awldlc-modal" style="display:none;">
                <div class="awldlc-modal-content">
                    <div class="awldlc-modal-header">
                        <h3>
                            <?php esc_html_e('Create Redirect', 'dead-link-checker'); ?>
                        </h3>
                        <button type="button" class="awldlc-modal-close">&times;</button>
                    </div>
                    <div class="awldlc-modal-body">
                        <p class="awldlc-redirect-info">
                            <span class="dashicons dashicons-info"></span>
                            <?php esc_html_e('Create a redirect so visitors to the broken URL will be sent to a new working URL.', 'dead-link-checker'); ?>
                        </p>
                        <p><label>
                                <?php esc_html_e('Broken URL (From):', 'dead-link-checker'); ?>
                            </label><input type="text" id="awldlc-redirect-source-url" readonly></p>
                        <p><label>
                                <?php esc_html_e('Target URL (To):', 'dead-link-checker'); ?>
                            </label><input type="url" id="awldlc-redirect-target-url" placeholder="https://"></p>
                        <p><label>
                                <?php esc_html_e('Redirect Type:', 'dead-link-checker'); ?>
                            </label>
                            <select id="awldlc-redirect-type">
                                <option value="301">
                                    <?php esc_html_e('301 - Permanent Redirect (Recommended)', 'dead-link-checker'); ?>
                                </option>
                                <option value="302"><?php esc_html_e('302 - Temporary Redirect', 'dead-link-checker'); ?>
                                </option>
                                <option value="307">
                                    <?php esc_html_e('307 - Temporary Redirect (Strict)', 'dead-link-checker'); ?>
                                </option>
                            </select>
                        </p>
                        <input type="hidden" id="awldlc-redirect-link-id">
                    </div>
                    <div class="awldlc-modal-footer">
                        <button type="button" class="button awldlc-modal-cancel">
                            <?php esc_html_e('Cancel', 'dead-link-checker'); ?>
                        </button>
                        <button type="button" class="button button-primary" id="awldlc-redirect-save">
                            <?php esc_html_e('Create Redirect', 'dead-link-checker'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
