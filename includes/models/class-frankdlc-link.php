<?php
/**
 * Link Model
 *
 * Represents a link entity with status constants and validation.
 *
 * @package BrokenLinkChecker
 * @since 1.0.0
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Class FRANKDLC_Link
 *
 * Link data model with constants and helper methods
 */
class FRANKDLC_Link
{

    // Status codes
    const STATUS_OK = 200;
    const STATUS_REDIRECT = 301;
    const STATUS_TEMP_REDIRECT = 302;
    const STATUS_NOT_FOUND = 404;
    const STATUS_FORBIDDEN = 403;
    const STATUS_SERVER_ERROR = 500;
    const STATUS_TIMEOUT = 0;

    // Link types
    const TYPE_INTERNAL = 'internal';
    const TYPE_EXTERNAL = 'external';
    const TYPE_IMAGE = 'image';
    const TYPE_YOUTUBE = 'youtube';
    const TYPE_IFRAME = 'iframe';

    // Source types
    const SOURCE_POST = 'post';
    const SOURCE_PAGE = 'page';
    const SOURCE_COMMENT = 'comment';
    const SOURCE_WIDGET = 'widget';
    const SOURCE_MENU = 'menu';
    const SOURCE_CUSTOM_FIELD = 'custom_field';

    /**
     * Link ID
     *
     * @var int
     */
    public $id;

    /**
     * Link URL
     *
     * @var string
     */
    public $url;

    /**
     * HTTP status code
     *
     * @var int|null
     */
    public $status_code;

    /**
     * Status text
     *
     * @var string
     */
    public $status_text;

    /**
     * Link type
     *
     * @var string
     */
    public $link_type;

    /**
     * Source post/item ID
     *
     * @var int
     */
    public $source_id;

    /**
     * Source type (post, page, comment, etc.)
     *
     * @var string
     */
    public $source_type;

    /**
     * Source field (post_content, post_excerpt, custom field name)
     *
     * @var string
     */
    public $source_field;

    /**
     * Anchor/alt text
     *
     * @var string
     */
    public $anchor_text;

    /**
     * Is link broken
     *
     * @var bool
     */
    public $is_broken;

    /**
     * Is link a warning (redirect, slow, etc.)
     *
     * @var bool
     */
    public $is_warning;

    /**
     * Is link dismissed
     *
     * @var bool
     */
    public $is_dismissed;

    /**
     * Redirect URL if redirected
     *
     * @var string|null
     */
    public $redirect_url;

    /**
     * Number of redirects
     *
     * @var int
     */
    public $redirect_count;

    /**
     * Response time in seconds
     *
     * @var float|null
     */
    public $response_time;

    /**
     * Last check datetime
     *
     * @var string|null
     */
    public $last_check;

    /**
     * First detected datetime
     *
     * @var string
     */
    public $first_detected;

    /**
     * Number of times checked
     *
     * @var int
     */
    public $check_count;

    /**
     * Error message if failed
     *
     * @var string|null
     */
    public $error_message;

    /**
     * Constructor
     *
     * @param object|array $data Link data
     */
    public function __construct($data = null)
    {
        if ($data) {
            $this->populate($data);
        }
    }

    /**
     * Populate link from data
     *
     * @param object|array $data Link data
     */
    public function populate($data)
    {
        $data = (object) $data;

        $this->id = isset($data->id) ? absint($data->id) : 0;
        $this->url = isset($data->url) ? esc_url_raw($data->url) : '';
        $this->status_code = isset($data->status_code) ? absint($data->status_code) : null;
        $this->status_text = isset($data->status_text) ? sanitize_text_field($data->status_text) : '';
        $this->link_type = isset($data->link_type) ? sanitize_key($data->link_type) : self::TYPE_INTERNAL;
        $this->source_id = isset($data->source_id) ? absint($data->source_id) : 0;
        $this->source_type = isset($data->source_type) ? sanitize_key($data->source_type) : self::SOURCE_POST;
        $this->source_field = isset($data->source_field) ? sanitize_key($data->source_field) : 'post_content';
        $this->anchor_text = isset($data->anchor_text) ? sanitize_text_field($data->anchor_text) : '';
        $this->is_broken = isset($data->is_broken) ? (bool) $data->is_broken : false;
        $this->is_warning = isset($data->is_warning) ? (bool) $data->is_warning : false;
        $this->is_dismissed = isset($data->is_dismissed) ? (bool) $data->is_dismissed : false;
        $this->redirect_url = isset($data->redirect_url) ? esc_url_raw($data->redirect_url) : null;
        $this->redirect_count = isset($data->redirect_count) ? absint($data->redirect_count) : 0;
        $this->response_time = isset($data->response_time) ? floatval($data->response_time) : null;
        $this->last_check = isset($data->last_check) ? sanitize_text_field($data->last_check) : null;
        $this->first_detected = isset($data->first_detected) ? sanitize_text_field($data->first_detected) : '';
        $this->check_count = isset($data->check_count) ? absint($data->check_count) : 0;
        $this->error_message = isset($data->error_message) ? sanitize_text_field($data->error_message) : null;
    }

    /**
     * Get status badge HTML
     *
     * @return string
     */
    public function get_status_badge()
    {
        if ($this->is_broken) {
            $class = 'frankdlc-status-broken';
            // Show HTTP code if available, otherwise show 'Error' for connection failures
            if ($this->status_code !== null && $this->status_code > 0) {
                $text = $this->status_code;
            } else {
                $text = $this->error_message ? __('Error', 'frank-dead-link-checker') : __('Error', 'frank-dead-link-checker');
            }
        } elseif ($this->is_warning) {
            $class = 'frankdlc-status-warning';
            $text = ($this->status_code !== null && $this->status_code > 0) ? $this->status_code : __('Warning', 'frank-dead-link-checker');
        } elseif ($this->status_code === null) {
            $class = 'frankdlc-status-unchecked';
            $text = __('Pending', 'frank-dead-link-checker');
        } else {
            $class = 'frankdlc-status-ok';
            $text = $this->status_code;
        }

        return sprintf(
            '<span class="frankdlc-status-badge %s">%s</span>',
            esc_attr($class),
            esc_html($text)
        );
    }

    /**
     * Get link type label
     *
     * @return string
     */
    public function get_type_label()
    {
        $labels = array(
            self::TYPE_INTERNAL => __('Internal', 'frank-dead-link-checker'),
            self::TYPE_EXTERNAL => __('External', 'frank-dead-link-checker'),
            self::TYPE_IMAGE => __('Image', 'frank-dead-link-checker'),
            self::TYPE_YOUTUBE => __('YouTube', 'frank-dead-link-checker'),
            self::TYPE_IFRAME => __('iFrame', 'frank-dead-link-checker'),
        );

        return isset($labels[$this->link_type]) ? $labels[$this->link_type] : $this->link_type;
    }

    /**
     * Get source title/label
     *
     * @return string
     */
    public function get_source_title()
    {
        switch ($this->source_type) {
            case self::SOURCE_POST:
            case self::SOURCE_PAGE:
                $post = get_post($this->source_id);
                return $post ? $post->post_title : __('(Deleted)', 'frank-dead-link-checker');

            case self::SOURCE_COMMENT:
                $comment = get_comment($this->source_id);
                return $comment
                    /* translators: %s: post title */
                    ? sprintf(__('Comment on "%s"', 'frank-dead-link-checker'), get_the_title($comment->comment_post_ID))
                    : __('(Deleted)', 'frank-dead-link-checker');

            case self::SOURCE_WIDGET:
                return __('Widget', 'frank-dead-link-checker');

            case self::SOURCE_MENU:
                return __('Menu', 'frank-dead-link-checker');

            default:
                return ucfirst($this->source_type);
        }
    }

    /**
     * Get source edit URL
     *
     * @return string|null
     */
    public function get_source_edit_url()
    {
        switch ($this->source_type) {
            case self::SOURCE_POST:
            case self::SOURCE_PAGE:
                return get_edit_post_link($this->source_id, 'raw');

            case self::SOURCE_COMMENT:
                return get_edit_comment_link($this->source_id);

            default:
                return null;
        }
    }

    /**
     * Get truncated URL for display
     *
     * @param int $max_length Maximum length
     * @return string
     */
    public function get_display_url($max_length = 60)
    {
        $url = $this->url;

        if (strlen($url) > $max_length) {
            $url = substr($url, 0, $max_length - 3) . '...';
        }

        return $url;
    }

    /**
     * Check if link is external
     *
     * @return bool
     */
    public function is_external()
    {
        return $this->link_type === self::TYPE_EXTERNAL;
    }

    /**
     * Get human-readable last check time
     *
     * @return string
     */
    public function get_last_check_human()
    {
        if (!$this->last_check) {
            return __('Never', 'frank-dead-link-checker');
        }

        /* translators: %s: human-readable time difference */
        return sprintf(__('%s ago', 'frank-dead-link-checker'), human_time_diff(strtotime($this->last_check), current_time('timestamp')));
    }

    /**
     * Get response time formatted
     *
     * @return string
     */
    public function get_response_time_formatted()
    {
        if ($this->response_time === null) {
            return '-';
        }

        if ($this->response_time < 1) {
            return round($this->response_time * 1000) . 'ms';
        }

        return round($this->response_time, 2) . 's';
    }

    /**
     * Validate URL
     *
     * @param string $url URL to validate
     * @return bool
     */
    public static function is_valid_url($url)
    {
        // Check basic format
        if (empty($url) || !is_string($url)) {
            return false;
        }

        // Must have a scheme
        if (!preg_match('/^https?:\/\//i', $url)) {
            return false;
        }

        // Use filter_var for additional validation
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Determine link type from URL
     *
     * @param string $url  URL to check
     * @param string $tag  HTML tag (a, img, iframe, etc.)
     * @return string
     */
    public static function determine_type($url, $tag = 'a')
    {
        $home_url = home_url();
        $home_host = wp_parse_url($home_url, PHP_URL_HOST);
        $url_host = wp_parse_url($url, PHP_URL_HOST);

        // Image types
        if ($tag === 'img' || preg_match('/\.(jpg|jpeg|png|gif|webp|svg|bmp)(\?.*)?$/i', $url)) {
            return self::TYPE_IMAGE;
        }

        // YouTube
        if (preg_match('/youtube\.com|youtu\.be/i', $url)) {
            return self::TYPE_YOUTUBE;
        }

        // iFrame
        if ($tag === 'iframe') {
            return self::TYPE_IFRAME;
        }

        // Internal vs External
        if ($url_host && $home_host && strcasecmp($url_host, $home_host) === 0) {
            return self::TYPE_INTERNAL;
        }

        return self::TYPE_EXTERNAL;
    }
}
