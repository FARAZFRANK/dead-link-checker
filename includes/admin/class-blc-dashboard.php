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

class BLC_Dashboard
{

    public function render_page()
    {
        $stats = blc()->database->get_stats();
        $current_status = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'all';
        $current_type = isset($_GET['link_type']) ? sanitize_key($_GET['link_type']) : 'all';

        // Handle search from POST (to allow URLs with special characters)
        $search = '';
        if (isset($_POST['blc_search']) && isset($_POST['blc_search_nonce'])) {
            if (wp_verify_nonce($_POST['blc_search_nonce'], 'blc_search_nonce')) {
                $search = sanitize_text_field(wp_unslash($_POST['blc_search']));
            }
        }

        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 20;

        // Sorting params
        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'last_check';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Advanced filters
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $http_status = isset($_GET['http_status']) ? sanitize_text_field($_GET['http_status']) : '';

        $links = blc()->database->get_links(array(
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

        $total_items = blc()->database->get_links_count(array(
            'status' => $current_status,
            'link_type' => $current_type,
            'search' => $search,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'http_status' => $http_status,
        ));

        $total_pages = ceil($total_items / $per_page);
        $latest_scan = blc()->database->get_latest_scan();
        $is_scanning = blc()->database->is_scan_running();
        ?>
        <div class="wrap blc-wrap">
            <div class="blc-header">
                <div class="blc-header-left">
                    <h1>
                        <?php esc_html_e('Dead Link Checker Pro', 'dead-link-checker'); ?>
                    </h1>
                    <?php if ($latest_scan && $latest_scan->completed_at): ?>
                        <span class="blc-last-scan">
                            <?php printf(esc_html__('Last scan: %s', 'dead-link-checker'), human_time_diff(strtotime($latest_scan->completed_at)) . ' ago'); ?>
                        </span>
                    <?php endif; ?>
                    <?php
                    $queue_status = BLC_Queue_Manager::get_status();
                    $queue_icon = $queue_status['is_reliable'] ? 'yes-alt' : 'clock';
                    $queue_class = $queue_status['is_reliable'] ? 'blc-queue-reliable' : 'blc-queue-basic';
                    ?>
                    <span class="blc-queue-status <?php echo esc_attr($queue_class); ?>"
                        title="<?php echo esc_attr($queue_status['is_reliable'] ? __('Reliable background processing', 'dead-link-checker') : __('Basic background processing - install Action Scheduler for better reliability', 'dead-link-checker')); ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr($queue_icon); ?>"></span>
                        <?php echo esc_html($queue_status['method_label']); ?>
                    </span>
                </div>
                <div class="blc-header-actions">
                    <button type="button" id="blc-scan-btn" class="button button-primary button-hero" <?php echo $is_scanning ? 'style="display:none;"' : ''; ?>>
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e('Scan Now', 'dead-link-checker'); ?>
                    </button>
                    <button type="button" id="blc-fresh-scan-btn" class="button button-hero" <?php echo $is_scanning ? 'style="display:none;"' : ''; ?>
                        title="<?php esc_attr_e('Clear all data and start fresh', 'dead-link-checker'); ?>">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Fresh Scan', 'dead-link-checker'); ?>
                    </button>
                    <button type="button" id="blc-stop-btn" class="button button-hero blc-stop-btn" <?php echo $is_scanning ? '' : 'style="display:none;"'; ?>>
                        <span class="dashicons dashicons-no"></span>
                        <?php esc_html_e('Stop Scan', 'dead-link-checker'); ?>
                    </button>
                    <div class="blc-export-dropdown">
                        <button type="button" id="blc-export-btn" class="button button-hero">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e('Export', 'dead-link-checker'); ?>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        <div class="blc-export-menu" style="display:none;">
                            <a href="#" class="blc-export-option" data-format="csv">
                                <span class="dashicons dashicons-media-spreadsheet"></span>
                                <?php esc_html_e('Export as CSV', 'dead-link-checker'); ?>
                            </a>
                            <a href="#" class="blc-export-option" data-format="json">
                                <span class="dashicons dashicons-media-code"></span>
                                <?php esc_html_e('Export as JSON', 'dead-link-checker'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="blc-stats-grid">
                <div class="blc-stat-card blc-stat-broken">
                    <div class="blc-stat-icon"><span class="dashicons dashicons-no-alt"></span></div>
                    <div class="blc-stat-content">
                        <span class="blc-stat-value">
                            <?php echo esc_html($stats['broken']); ?>
                        </span>
                        <span class="blc-stat-label">
                            <?php esc_html_e('Broken Links', 'dead-link-checker'); ?>
                        </span>
                    </div>
                </div>
                <div class="blc-stat-card blc-stat-warning">
                    <div class="blc-stat-icon"><span class="dashicons dashicons-warning"></span></div>
                    <div class="blc-stat-content">
                        <span class="blc-stat-value">
                            <?php echo esc_html($stats['warning']); ?>
                        </span>
                        <span class="blc-stat-label">
                            <?php esc_html_e('Warnings', 'dead-link-checker'); ?>
                        </span>
                    </div>
                </div>
                <div class="blc-stat-card blc-stat-working">
                    <div class="blc-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
                    <div class="blc-stat-content">
                        <span class="blc-stat-value">
                            <?php echo esc_html($stats['working']); ?>
                        </span>
                        <span class="blc-stat-label">
                            <?php esc_html_e('Working', 'dead-link-checker'); ?>
                        </span>
                    </div>
                </div>
                <div class="blc-stat-card blc-stat-total">
                    <div class="blc-stat-icon"><span class="dashicons dashicons-admin-links"></span></div>
                    <div class="blc-stat-content">
                        <span class="blc-stat-value">
                            <?php echo esc_html($stats['total']); ?>
                        </span>
                        <span class="blc-stat-label">
                            <?php esc_html_e('Total Links', 'dead-link-checker'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Scan Progress -->
            <div id="blc-scan-progress" class="blc-scan-progress" style="display: none;">
                <div class="blc-progress-bar">
                    <div class="blc-progress-fill"></div>
                </div>
                <span class="blc-progress-text"></span>
            </div>

            <!-- Filters -->
            <div class="blc-filters">
                <ul class="blc-filter-tabs">
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
                <div class="blc-filter-right">
                    <form method="post" class="blc-search-form"
                        action="<?php echo esc_url(admin_url('admin.php?page=dead-link-checker&status=' . $current_status)); ?>">
                        <?php wp_nonce_field('blc_search_nonce', 'blc_search_nonce'); ?>
                        <input type="search" name="blc_search" value="<?php echo esc_attr($search); ?>"
                            placeholder="<?php esc_attr_e('Search URLs...', 'dead-link-checker'); ?>">
                        <button type="submit" class="button"><span class="dashicons dashicons-search"></span></button>
                    </form>
                </div>
            </div>

            <!-- Advanced Filters -->
            <div class="blc-advanced-filters">
                <form method="get" class="blc-filter-form">
                    <input type="hidden" name="page" value="dead-link-checker">
                    <input type="hidden" name="status" value="<?php echo esc_attr($current_status); ?>">
                    <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>">
                    <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>">

                    <div class="blc-filter-group">
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

                    <div class="blc-filter-group">
                        <label><?php esc_html_e('HTTP Status:', 'dead-link-checker'); ?></label>
                        <select name="http_status">
                            <option value="" <?php selected($http_status, ''); ?>>
                                <?php esc_html_e('Any Status', 'dead-link-checker'); ?>
                            </option>
                            <option value="404" <?php selected($http_status, '404'); ?>>404 Not Found</option>
                            <option value="403" <?php selected($http_status, '403'); ?>>403 Forbidden</option>
                            <option value="500" <?php selected($http_status, '500'); ?>>500 Server Error</option>
                            <option value="301" <?php selected($http_status, '301'); ?>>301 Redirect</option>
                            <option value="302" <?php selected($http_status, '302'); ?>>302 Redirect</option>
                        </select>
                    </div>

                    <div class="blc-filter-group">
                        <label><?php esc_html_e('Date Range:', 'dead-link-checker'); ?></label>
                        <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="From">
                        <span>-</span>
                        <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="To">
                    </div>

                    <button type="submit"
                        class="button"><?php esc_html_e('Apply Filters', 'dead-link-checker'); ?></button>
                    <?php if ($current_type !== 'all' || $http_status || $date_from || $date_to): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=dead-link-checker&status=' . $current_status)); ?>"
                            class="button blc-clear-filters"><?php esc_html_e('Clear', 'dead-link-checker'); ?></a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Bulk Actions -->
            <div class="blc-bulk-actions">
                <select id="blc-bulk-action">
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
                <button type="button" id="blc-bulk-apply" class="button">
                    <?php esc_html_e('Apply', 'dead-link-checker'); ?>
                </button>
                <span class="blc-bulk-result"></span>
            </div>

            <!-- Links Table -->
            <?php
            // Helper function to build sortable header
            $build_sort_url = function ($column) use ($orderby, $order, $current_status, $current_type, $search, $date_from, $date_to, $http_status) {
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
                ));
            };
            $sort_class = function ($column) use ($orderby, $order) {
                if ($orderby !== $column)
                    return 'sortable';
                return 'sorted ' . strtolower($order);
            };
            ?>
            <table class="blc-links-table wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="blc-select-all"></th>
                        <th class="blc-col-status <?php echo esc_attr($sort_class('status_code')); ?>">
                            <a href="<?php echo esc_url($build_sort_url('status_code')); ?>">
                                <span><?php esc_html_e('Status', 'dead-link-checker'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="blc-col-url <?php echo esc_attr($sort_class('url')); ?>">
                            <a href="<?php echo esc_url($build_sort_url('url')); ?>">
                                <span><?php esc_html_e('URL', 'dead-link-checker'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="blc-col-source">
                            <?php esc_html_e('Source', 'dead-link-checker'); ?>
                        </th>
                        <th class="blc-col-type <?php echo esc_attr($sort_class('link_type')); ?>">
                            <a href="<?php echo esc_url($build_sort_url('link_type')); ?>">
                                <span><?php esc_html_e('Type', 'dead-link-checker'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="blc-col-checked <?php echo esc_attr($sort_class('last_check')); ?>">
                            <a href="<?php echo esc_url($build_sort_url('last_check')); ?>">
                                <span><?php esc_html_e('Last Check', 'dead-link-checker'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="blc-col-actions">
                            <?php esc_html_e('Actions', 'dead-link-checker'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($links)): ?>
                        <tr>
                            <td colspan="6" class="blc-no-items">
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
                            $link = new BLC_Link($link_data); ?>
                            <tr data-link-id="<?php echo esc_attr($link->id); ?>">
                                <td class="check-column"><input type="checkbox" class="blc-link-checkbox"
                                        value="<?php echo esc_attr($link->id); ?>"></td>
                                <td class="blc-col-status">
                                    <?php echo $link->get_status_badge(); ?>
                                </td>
                                <td class="blc-col-url">
                                    <div class="blc-url-wrap">
                                        <a href="<?php echo esc_url($link->url); ?>" target="_blank" rel="noopener"
                                            title="<?php echo esc_attr($link->url); ?>">
                                            <?php echo esc_html($link->get_display_url(50)); ?>
                                        </a>
                                        <?php if ($link->redirect_url): ?>
                                            <span class="blc-redirect-info">→
                                                <?php echo esc_html($link->redirect_count); ?> redirects
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($link->anchor_text): ?>
                                            <span class="blc-anchor-text">"<?php echo esc_html(wp_trim_words($link->anchor_text, 5)); ?>"</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="blc-col-source">
                                    <?php $edit_url = $link->get_source_edit_url();
                                    if ($edit_url): ?>
                                        <a href="<?php echo esc_url($edit_url); ?>">
                                            <?php echo esc_html(wp_trim_words($link->get_source_title(), 5)); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo esc_html($link->get_source_title()); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="blc-col-type"><span
                                        class="blc-type-badge blc-type-<?php echo esc_attr($link->link_type); ?>">
                                        <?php echo esc_html($link->get_type_label()); ?>
                                    </span></td>
                                <td class="blc-col-checked">
                                    <?php if ($link->last_check): ?>
                                        <span title="<?php echo esc_attr($link->last_check); ?>">
                                            <?php echo esc_html(human_time_diff(strtotime($link->last_check)) . ' ago'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="blc-unchecked"><?php esc_html_e('Never', 'dead-link-checker'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="blc-col-actions">
                                    <div class="blc-actions-wrap">
                                        <button type="button" class="blc-action-btn blc-recheck"
                                            data-id="<?php echo esc_attr($link->id); ?>"
                                            title="<?php esc_attr_e('Recheck', 'dead-link-checker'); ?>"><span
                                                class="dashicons dashicons-update"></span></button>
                                        <button type="button" class="blc-action-btn blc-edit"
                                            data-id="<?php echo esc_attr($link->id); ?>" data-url="<?php echo esc_attr($link->url); ?>"
                                            title="<?php esc_attr_e('Edit URL', 'dead-link-checker'); ?>"><span
                                                class="dashicons dashicons-edit"></span></button>
                                        <?php if ($link->is_broken): ?>
                                            <button type="button" class="blc-action-btn blc-redirect"
                                                data-id="<?php echo esc_attr($link->id); ?>" data-url="<?php echo esc_attr($link->url); ?>"
                                                title="<?php esc_attr_e('Create Redirect', 'dead-link-checker'); ?>"><span
                                                    class="dashicons dashicons-randomize"></span></button>
                                        <?php endif; ?>
                                        <?php if ($link->is_dismissed): ?>
                                            <button type="button" class="blc-action-btn blc-undismiss"
                                                data-id="<?php echo esc_attr($link->id); ?>"
                                                title="<?php esc_attr_e('Restore', 'dead-link-checker'); ?>"><span
                                                    class="dashicons dashicons-visibility"></span></button>
                                        <?php else: ?>
                                            <button type="button" class="blc-action-btn blc-dismiss"
                                                data-id="<?php echo esc_attr($link->id); ?>"
                                                title="<?php esc_attr_e('Dismiss', 'dead-link-checker'); ?>"><span
                                                    class="dashicons dashicons-hidden"></span></button>
                                        <?php endif; ?>
                                        <button type="button" class="blc-action-btn blc-delete"
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
                <div class="blc-pagination">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
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
            <div id="blc-edit-modal" class="blc-modal" style="display:none;">
                <div class="blc-modal-content">
                    <div class="blc-modal-header">
                        <h3>
                            <?php esc_html_e('Edit Link URL', 'dead-link-checker'); ?>
                        </h3>
                        <button type="button" class="blc-modal-close">&times;</button>
                    </div>
                    <div class="blc-modal-body">
                        <p><label>
                                <?php esc_html_e('Current URL:', 'dead-link-checker'); ?>
                            </label><input type="text" id="blc-edit-old-url" readonly></p>
                        <p><label>
                                <?php esc_html_e('New URL:', 'dead-link-checker'); ?>
                            </label><input type="url" id="blc-edit-new-url" placeholder="https://"></p>
                        <input type="hidden" id="blc-edit-link-id">
                    </div>
                    <div class="blc-modal-footer">
                        <button type="button" class="button blc-modal-cancel">
                            <?php esc_html_e('Cancel', 'dead-link-checker'); ?>
                        </button>
                        <button type="button" class="button button-primary" id="blc-edit-save">
                            <?php esc_html_e('Update Link', 'dead-link-checker'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Redirect Modal -->
            <div id="blc-redirect-modal" class="blc-modal" style="display:none;">
                <div class="blc-modal-content">
                    <div class="blc-modal-header">
                        <h3>
                            <?php esc_html_e('Create Redirect', 'dead-link-checker'); ?>
                        </h3>
                        <button type="button" class="blc-modal-close">&times;</button>
                    </div>
                    <div class="blc-modal-body">
                        <p class="blc-redirect-info">
                            <span class="dashicons dashicons-info"></span>
                            <?php esc_html_e('Create a redirect so visitors to the broken URL will be sent to a new working URL.', 'dead-link-checker'); ?>
                        </p>
                        <p><label>
                                <?php esc_html_e('Broken URL (From):', 'dead-link-checker'); ?>
                            </label><input type="text" id="blc-redirect-source-url" readonly></p>
                        <p><label>
                                <?php esc_html_e('Target URL (To):', 'dead-link-checker'); ?>
                            </label><input type="url" id="blc-redirect-target-url" placeholder="https://"></p>
                        <p><label>
                                <?php esc_html_e('Redirect Type:', 'dead-link-checker'); ?>
                            </label>
                            <select id="blc-redirect-type">
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
                        <input type="hidden" id="blc-redirect-link-id">
                    </div>
                    <div class="blc-modal-footer">
                        <button type="button" class="button blc-modal-cancel">
                            <?php esc_html_e('Cancel', 'dead-link-checker'); ?>
                        </button>
                        <button type="button" class="button button-primary" id="blc-redirect-save">
                            <?php esc_html_e('Create Redirect', 'dead-link-checker'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
