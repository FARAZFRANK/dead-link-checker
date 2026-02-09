<?php
/**
 * Link Scanner
 *
 * Main scanner that orchestrates link discovery and checking.
 *
 * @package BrokenLinkChecker
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class BLC_Scanner
{

    private $parser;
    private $checker;

    public function __construct()
    {
        $this->parser = new BLC_Parser();
        $this->checker = new BLC_Checker();
        add_action('blc_scheduled_scan', array($this, 'run_scheduled_scan'));
        add_action('blc_process_queue', array($this, 'process_queue'));
        add_action('blc_recheck_broken', array($this, 'recheck_broken_links'));
    }

    public function start_scan($type = 'full')
    {
        if (blc()->database->is_scan_running()) {
            return new WP_Error('scan_running', __('A scan is already in progress.', 'dead-link-checker'));
        }

        $scan_id = blc()->database->create_scan($type);
        if (!$scan_id) {
            return new WP_Error('scan_failed', __('Failed to create scan record.', 'dead-link-checker'));
        }

        blc()->database->update_scan($scan_id, array('status' => 'running'));
        set_transient('blc_current_scan_id', $scan_id, HOUR_IN_SECONDS);

        // Discover all links
        $total_links = $this->discover_links();

        blc()->database->update_scan($scan_id, array('total_links' => $total_links));

        // Schedule queue processing using Queue Manager
        if (!BLC_Queue_Manager::is_scheduled('blc_process_queue')) {
            BLC_Queue_Manager::schedule_single(time() + 5, 'blc_process_queue');
        }

        return $scan_id;
    }

    private function discover_links()
    {
        $count = 0;
        $settings = get_option('blc_settings', array());

        // Scan posts
        if (!empty($settings['scan_posts'])) {
            $count += $this->scan_post_type('post');
        }

        // Scan pages
        if (!empty($settings['scan_pages'])) {
            $count += $this->scan_post_type('page');
        }

        // Scan menus
        if (!empty($settings['scan_menus'])) {
            $count += $this->scan_menus();
        }

        // Scan widgets
        if (!empty($settings['scan_widgets'])) {
            $count += $this->scan_widgets();
        }

        // Scan comments
        if (!empty($settings['scan_comments'])) {
            $count += $this->scan_comments();
        }

        // Scan custom fields
        if (!empty($settings['scan_custom_fields'])) {
            $count += $this->scan_all_custom_fields();
        }

        return $count;
    }

    private function scan_post_type($post_type)
    {
        $count = 0;
        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        foreach ($posts as $post_id) {
            $post = get_post($post_id);
            if (!$post)
                continue;

            // Check if this post uses any page builder - if so, use page builder parser instead of standard
            $uses_page_builder = false;

            // Check for Elementor
            if (class_exists('BLC_Parser_Elementor') && BLC_Parser_Elementor::is_active() && BLC_Parser_Elementor::is_built_with_elementor($post_id)) {
                $uses_page_builder = true;
            }
            // Check for Divi
            if (class_exists('BLC_Parser_Divi') && BLC_Parser_Divi::is_active() && BLC_Parser_Divi::is_built_with_divi($post_id)) {
                $uses_page_builder = true;
            }
            // Check for WPBakery
            if (class_exists('BLC_Parser_WPBakery') && BLC_Parser_WPBakery::is_active() && BLC_Parser_WPBakery::is_built_with_wpbakery($post_id)) {
                $uses_page_builder = true;
            }
            // Check for Gutenberg blocks
            if (class_exists('BLC_Parser_Gutenberg') && BLC_Parser_Gutenberg::is_active() && BLC_Parser_Gutenberg::has_blocks($post_id)) {
                $uses_page_builder = true;
            }

            // Only use standard parser if NOT using a page builder (to avoid duplicates)
            if (!$uses_page_builder) {
                $links = $this->parser->parse_content($post->post_content);
                foreach ($links as $link) {
                    $link['source_id'] = $post_id;
                    $link['source_type'] = $post_type;
                    $link['source_field'] = 'post_content';
                    if (blc()->database->save_link($link)) {
                        $count++;
                    }
                }
            }

            // Parse with page builder parsers (this handles content for page-builder posts)
            $count += $this->parse_page_builder_content($post_id, $post_type);

            // Parse excerpt (always parse, as this is separate from main content)
            if (!empty($post->post_excerpt)) {
                $links = $this->parser->parse_content($post->post_excerpt);
                foreach ($links as $link) {
                    $link['source_id'] = $post_id;
                    $link['source_type'] = $post_type;
                    $link['source_field'] = 'post_excerpt';
                    if (blc()->database->save_link($link)) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Parse content using page builder detection
     *
     * @param int    $post_id   Post ID
     * @param string $post_type Post type
     * @return int Number of links saved
     */
    private function parse_page_builder_content($post_id, $post_type)
    {
        $count = 0;
        $links = array();

        // Check for Elementor content
        if (class_exists('BLC_Parser_Elementor') && BLC_Parser_Elementor::is_active()) {
            if (BLC_Parser_Elementor::is_built_with_elementor($post_id)) {
                $elementor_links = BLC_Parser_Elementor::extract_links($post_id);
                foreach ($elementor_links as $link) {
                    $link['source_id'] = $post_id;
                    $link['source_type'] = $post_type;
                    $link['source_field'] = 'elementor_data';
                    if (blc()->database->save_link($link)) {
                        $count++;
                    }
                }
            }
        }

        // Check for Divi content
        if (class_exists('BLC_Parser_Divi') && BLC_Parser_Divi::is_active()) {
            if (BLC_Parser_Divi::is_built_with_divi($post_id)) {
                $divi_links = BLC_Parser_Divi::extract_links($post_id);
                foreach ($divi_links as $link) {
                    $link['source_id'] = $post_id;
                    $link['source_type'] = $post_type;
                    $link['source_field'] = 'divi_builder';
                    if (blc()->database->save_link($link)) {
                        $count++;
                    }
                }
            }
        }

        // Check for WPBakery content
        if (class_exists('BLC_Parser_WPBakery') && BLC_Parser_WPBakery::is_active()) {
            if (BLC_Parser_WPBakery::is_built_with_wpbakery($post_id)) {
                $wpbakery_links = BLC_Parser_WPBakery::extract_links($post_id);
                foreach ($wpbakery_links as $link) {
                    $link['source_id'] = $post_id;
                    $link['source_type'] = $post_type;
                    $link['source_field'] = 'wpbakery';
                    if (blc()->database->save_link($link)) {
                        $count++;
                    }
                }
            }
        }

        // Check for Gutenberg blocks
        if (class_exists('BLC_Parser_Gutenberg') && BLC_Parser_Gutenberg::is_active()) {
            if (BLC_Parser_Gutenberg::has_blocks($post_id)) {
                $gutenberg_links = BLC_Parser_Gutenberg::extract_links($post_id);
                foreach ($gutenberg_links as $link) {
                    $link['source_id'] = $post_id;
                    $link['source_type'] = $post_type;
                    $link['source_field'] = 'gutenberg_blocks';
                    if (blc()->database->save_link($link)) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    private function scan_menus()
    {
        $count = 0;
        $menus = wp_get_nav_menus();

        foreach ($menus as $menu) {
            $items = wp_get_nav_menu_items($menu->term_id);
            if (!$items)
                continue;

            foreach ($items as $item) {
                if ($item->type === 'custom' && !empty($item->url)) {
                    $link = array(
                        'url' => $item->url,
                        'link_type' => BLC_Link::determine_type($item->url, 'a'),
                        'source_id' => $menu->term_id,
                        'source_type' => 'menu',
                        'source_field' => 'menu_item',
                        'anchor_text' => $item->title,
                    );
                    if (blc()->database->save_link($link)) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    private function scan_widgets()
    {
        $count = 0;
        $sidebars = wp_get_sidebars_widgets();

        foreach ($sidebars as $sidebar_id => $widgets) {
            if ($sidebar_id === 'wp_inactive_widgets' || !is_array($widgets))
                continue;

            foreach ($widgets as $widget_id) {
                $widget_content = $this->get_widget_content($widget_id);
                if (empty($widget_content))
                    continue;

                $links = $this->parser->parse_content($widget_content);
                foreach ($links as $link) {
                    $link['source_id'] = 0;
                    $link['source_type'] = 'widget';
                    $link['source_field'] = $widget_id;
                    if (blc()->database->save_link($link)) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    private function get_widget_content($widget_id)
    {
        preg_match('/^(.+)-(\d+)$/', $widget_id, $matches);
        if (count($matches) !== 3)
            return '';

        $widget_type = $matches[1];
        $widget_index = (int) $matches[2];
        $widgets = get_option('widget_' . $widget_type);

        if (!isset($widgets[$widget_index]))
            return '';

        $widget = $widgets[$widget_index];
        return isset($widget['text']) ? $widget['text'] : (isset($widget['content']) ? $widget['content'] : '');
    }

    /**
     * Scan comments for links
     *
     * @return int Number of links found
     */
    private function scan_comments()
    {
        $count = 0;

        // Get approved comments
        $comments = get_comments(array(
            'status' => 'approve',
            'number' => 0, // Get all
        ));

        foreach ($comments as $comment) {
            if (empty($comment->comment_content)) {
                continue;
            }

            // Parse comment content for links
            $links = $this->parser->parse_content($comment->comment_content);

            foreach ($links as $link) {
                $link['source_id'] = $comment->comment_ID;
                $link['source_type'] = 'comment';
                $link['source_field'] = 'comment_content';

                if (blc()->database->save_link($link)) {
                    $count++;
                }
            }

            // Also check the comment author URL if provided
            if (!empty($comment->comment_author_url)) {
                $author_link = array(
                    'url' => $comment->comment_author_url,
                    'link_text' => $comment->comment_author,
                    'link_type' => 'hyperlink',
                    'is_internal' => $this->parser->is_internal_link($comment->comment_author_url),
                    'source_id' => $comment->comment_ID,
                    'source_type' => 'comment',
                    'source_field' => 'author_url',
                );

                if (blc()->database->save_link($author_link)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Scan all custom fields for links
     *
     * @return int Number of links found
     */
    private function scan_all_custom_fields()
    {
        $count = 0;
        $settings = get_option('blc_settings', array());

        // Post types to scan
        $post_types = array();
        if (!empty($settings['scan_posts'])) {
            $post_types[] = 'post';
        }
        if (!empty($settings['scan_pages'])) {
            $post_types[] = 'page';
        }

        if (empty($post_types)) {
            return $count;
        }

        // Get posts
        $posts = get_posts(array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids',
        ));

        foreach ($posts as $post_id) {
            $count += $this->scan_post_custom_fields($post_id);
        }

        return $count;
    }

    /**
     * Scan custom fields for a specific post
     *
     * @param int $post_id Post ID
     * @return int Number of links found
     */
    private function scan_post_custom_fields($post_id)
    {
        $count = 0;
        $meta = get_post_meta($post_id);

        if (empty($meta)) {
            return $count;
        }

        // Meta keys to skip (WordPress internal)
        $skip_keys = array(
            '_edit_last',
            '_edit_lock',
            '_wp_page_template',
            '_thumbnail_id',
            '_wp_attachment_metadata',
            '_wp_attached_file',
            '_menu_item_type',
            '_menu_item_menu_item_parent',
            '_menu_item_object_id',
            '_menu_item_object',
            '_menu_item_target',
            '_menu_item_classes',
            '_menu_item_xfn',
            '_menu_item_url',
            '_elementor_data',
            '_elementor_edit_mode',
            '_elementor_version',
            '_wpb_vc_js_status',
            '_wpb_shortcodes_custom_css',
        );

        foreach ($meta as $meta_key => $meta_values) {
            // Skip internal meta
            if (in_array($meta_key, $skip_keys, true)) {
                continue;
            }
            // Skip keys starting with underscore (usually internal)
            if (strpos($meta_key, '_') === 0 && !$this->is_acf_field($meta_key)) {
                continue;
            }

            foreach ($meta_values as $meta_value) {
                // Skip if it's serialized (complex data)
                if (is_serialized($meta_value)) {
                    // Try to unserialize and extract URLs
                    $unserialized = maybe_unserialize($meta_value);
                    if (is_string($unserialized)) {
                        $meta_value = $unserialized;
                    } else {
                        continue;
                    }
                }

                // Skip empty values
                if (empty($meta_value) || !is_string($meta_value)) {
                    continue;
                }

                // Check if value looks like it might contain a URL
                if (strpos($meta_value, 'http') === false && strpos($meta_value, 'href') === false) {
                    continue;
                }

                // Parse for links
                $links = $this->parser->parse_content($meta_value);

                foreach ($links as $link) {
                    $link['source_id'] = $post_id;
                    $link['source_type'] = 'custom_field';
                    $link['source_field'] = $meta_key;

                    if (blc()->database->save_link($link)) {
                        $count++;
                    }
                }

                // Also check if the value itself is a URL
                if (filter_var($meta_value, FILTER_VALIDATE_URL)) {
                    $direct_link = array(
                        'url' => $meta_value,
                        'link_text' => $meta_key,
                        'link_type' => 'custom_field_url',
                        'is_internal' => $this->parser->is_internal_link($meta_value),
                        'source_id' => $post_id,
                        'source_type' => 'custom_field',
                        'source_field' => $meta_key,
                    );

                    if (blc()->database->save_link($direct_link)) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Check if a meta key is an ACF field
     *
     * @param string $meta_key Meta key
     * @return bool True if ACF field
     */
    private function is_acf_field($meta_key)
    {
        // ACF stores field references with underscore prefix
        // The actual value is stored without underscore
        // So we check for corresponding ACF field key
        return function_exists('get_field_object');
    }

    public function process_queue()
    {
        $scan_id = get_transient('blc_current_scan_id');
        if (!$scan_id)
            return;

        $settings = get_option('blc_settings', array());
        $batch_size = isset($settings['concurrent_requests']) ? absint($settings['concurrent_requests']) : 3;
        $batch_size = max(1, min(10, $batch_size));

        $links = blc()->database->get_links_to_check($batch_size);

        if (empty($links)) {
            $this->complete_scan($scan_id);
            return;
        }

        $checked = 0;
        $broken = 0;
        $warnings = 0;

        foreach ($links as $link) {
            $result = $this->checker->check_url($link->url);
            blc()->database->update_link_result($link->id, $result);

            $checked++;
            if (!empty($result['is_broken']))
                $broken++;
            if (!empty($result['is_warning']))
                $warnings++;
        }

        // Update scan progress
        $scan = blc()->database->get_running_scan();
        if ($scan) {
            blc()->database->update_scan($scan_id, array(
                'checked_links' => $scan->checked_links + $checked,
                'broken_links' => $scan->broken_links + $broken,
                'warning_links' => $scan->warning_links + $warnings,
            ));
        }

        // Store progress for AJAX
        $this->update_progress($scan_id);

        // Schedule next batch using Queue Manager
        $remaining = blc()->database->get_links_to_check(1);
        if (!empty($remaining)) {
            BLC_Queue_Manager::schedule_single(time() + 2, 'blc_process_queue');
        } else {
            $this->complete_scan($scan_id);
        }
    }

    private function complete_scan($scan_id)
    {
        $scan = blc()->database->get_running_scan();
        if ($scan) {
            blc()->database->complete_scan($scan_id, array(
                'total_links' => $scan->total_links,
                'checked_links' => $scan->checked_links,
                'broken_links' => $scan->broken_links,
                'warning_links' => $scan->warning_links,
            ));
        }

        delete_transient('blc_current_scan_id');
        delete_transient('blc_scan_progress');

        // Trigger notification if broken links found
        if ($scan && $scan->broken_links > 0) {
            do_action('blc_scan_complete', $scan);
        }
    }

    private function update_progress($scan_id)
    {
        $scan = blc()->database->get_running_scan();
        if (!$scan)
            return;

        $progress = array(
            'scan_id' => $scan_id,
            'status' => $scan->status,
            'total' => (int) $scan->total_links,
            'checked' => (int) $scan->checked_links,
            'broken' => (int) $scan->broken_links,
            'warnings' => (int) $scan->warning_links,
            'percent' => $scan->total_links > 0 ? round(($scan->checked_links / $scan->total_links) * 100) : 0,
        );

        set_transient('blc_scan_progress', $progress, HOUR_IN_SECONDS);
    }

    /**
     * Stop a running scan
     *
     * @return bool True if scan was stopped, false if no scan running
     */
    public function stop_scan()
    {
        $scan_id = get_transient('blc_current_scan_id');
        if (!$scan_id) {
            return false;
        }

        // Update scan status to cancelled
        blc()->database->update_scan($scan_id, array(
            'status' => 'cancelled',
            'completed_at' => current_time('mysql'),
        ));

        // Clear transients
        delete_transient('blc_current_scan_id');
        delete_transient('blc_scan_progress');

        // Clear any scheduled queue processing (uses Queue Manager for AS/WP-Cron)
        BLC_Queue_Manager::cancel('blc_process_queue');
        wp_clear_scheduled_hook('blc_process_queue'); // Also clear WP-Cron just in case

        return true;
    }

    public function get_progress()
    {
        $progress = get_transient('blc_scan_progress');

        if (!$progress) {
            $scan = blc()->database->get_running_scan();
            if ($scan) {
                $progress = array(
                    'scan_id' => $scan->id,
                    'status' => $scan->status,
                    'total' => (int) $scan->total_links,
                    'checked' => (int) $scan->checked_links,
                    'broken' => (int) $scan->broken_links,
                    'warnings' => (int) $scan->warning_links,
                    'percent' => $scan->total_links > 0 ? round(($scan->checked_links / $scan->total_links) * 100) : 0,
                );
            } else {
                $progress = array('status' => 'idle', 'percent' => 0);
            }
        }

        return $progress;
    }

    public function run_scheduled_scan()
    {
        $settings = get_option('blc_settings', array());
        if (isset($settings['scan_frequency']) && $settings['scan_frequency'] === 'manual') {
            return;
        }
        $this->start_scan('scheduled');
    }

    /**
     * Recheck only broken and warning links
     * 
     * This is a lightweight scheduled task that runs more frequently
     * than the full scan. It only rechecks links that are already
     * marked as broken or warning to see if they've been fixed.
     */
    public function recheck_broken_links()
    {
        global $wpdb;

        // Don't run if a full scan is in progress
        if (blc()->database->is_scan_running()) {
            return;
        }

        $table = blc()->database->get_links_table();

        // Get broken and warning links that haven't been checked in the last 6 hours
        $stale_threshold = gmdate('Y-m-d H:i:s', strtotime('-6 hours'));

        $links = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE (is_broken = 1 OR is_warning = 1) 
             AND is_dismissed = 0 
             AND (last_check IS NULL OR last_check < %s)
             ORDER BY last_check ASC
             LIMIT 50",
            $stale_threshold
        ));

        if (empty($links)) {
            return;
        }

        $settings = get_option('blc_settings', array());
        $delay = isset($settings['delay_between']) ? (int) $settings['delay_between'] : 500;

        foreach ($links as $link) {
            $result = $this->checker->check_url($link->url);

            blc()->database->update_link_result($link->id, $result);

            // Small delay between requests
            if ($delay > 0) {
                usleep($delay * 1000);
            }
        }

        // Clear stats cache so dashboard shows updated counts
        blc()->database->clear_stats_cache();

        // Log the recheck for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[BLC] Auto-recheck completed: %d links rechecked',
                count($links)
            ));
        }
    }
}
