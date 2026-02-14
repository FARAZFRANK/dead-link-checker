<?php
/**
 * WPBakery Content Parser
 *
 * Extracts links from WPBakery (Visual Composer) page builder content.
 *
 * @package BrokenLinkChecker
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

class FRANKDLC_Parser_WPBakery
{
    /**
     * Check if WPBakery is active
     *
     * @return bool
     */
    public static function is_active()
    {
        return defined('WPB_VC_VERSION') || class_exists('Vc_Manager');
    }

    /**
     * Check if a post was built with WPBakery
     *
     * @param int $post_id Post ID
     * @return bool
     */
    public static function is_built_with_wpbakery($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        // Check for WPBakery shortcodes
        return strpos($post->post_content, '[vc_') !== false;
    }

    /**
     * Extract links from WPBakery content
     *
     * @param int $post_id Post ID
     * @return array Array of link data
     */
    public static function extract_links($post_id)
    {
        $links = array();
        $post = get_post($post_id);

        if (!$post || empty($post->post_content)) {
            return $links;
        }

        $content = $post->post_content;

        // Parse WPBakery shortcodes for links
        $links = array_merge($links, self::parse_button_elements($content));
        $links = array_merge($links, self::parse_single_image_elements($content));
        $links = array_merge($links, self::parse_custom_heading_elements($content));
        $links = array_merge($links, self::parse_column_text_elements($content));
        $links = array_merge($links, self::parse_cta_elements($content));
        $links = array_merge($links, self::parse_video_elements($content));

        return $links;
    }

    /**
     * Parse button elements for links
     *
     * @param string $content Post content
     * @return array
     */
    private static function parse_button_elements($content)
    {
        $links = array();

        // Match vc_btn and vc_button shortcodes
        preg_match_all('/\[vc_btn[^\]]*\]/i', $content, $matches);

        foreach ($matches[0] as $shortcode) {
            $url = self::extract_attribute($shortcode, 'link');
            $title = self::extract_attribute($shortcode, 'title');

            // WPBakery stores link as url:encoded_url|title:text|target:_blank
            if (!empty($url)) {
                $parsed_link = self::parse_vc_link($url);
                if (!empty($parsed_link['url'])) {
                    $links[] = array(
                        'url' => $parsed_link['url'],
                        'anchor_text' => $title ?: $parsed_link['title'] ?: 'Button',
                        'link_type' => self::determine_link_type($parsed_link['url']),
                    );
                }
            }
        }

        return $links;
    }

    /**
     * Parse single image elements
     *
     * @param string $content Post content
     * @return array
     */
    private static function parse_single_image_elements($content)
    {
        $links = array();

        // Match vc_single_image shortcodes
        preg_match_all('/\[vc_single_image[^\]]*\]/i', $content, $matches);

        foreach ($matches[0] as $shortcode) {
            $image_id = self::extract_attribute($shortcode, 'image');
            $link = self::extract_attribute($shortcode, 'link');
            $onclick = self::extract_attribute($shortcode, 'onclick');

            // Get image URL from attachment ID
            if (!empty($image_id) && is_numeric($image_id)) {
                $image_url = wp_get_attachment_url($image_id);
                if ($image_url) {
                    $links[] = array(
                        'url' => $image_url,
                        'anchor_text' => get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: '',
                        'link_type' => 'image',
                    );
                }
            }

            // Custom link
            if ($onclick === 'custom_link' && !empty($link)) {
                $parsed_link = self::parse_vc_link($link);
                if (!empty($parsed_link['url'])) {
                    $links[] = array(
                        'url' => $parsed_link['url'],
                        'anchor_text' => $parsed_link['title'] ?: 'Image Link',
                        'link_type' => self::determine_link_type($parsed_link['url']),
                    );
                }
            }
        }

        return $links;
    }

    /**
     * Parse custom heading elements
     *
     * @param string $content Post content
     * @return array
     */
    private static function parse_custom_heading_elements($content)
    {
        $links = array();

        // Match vc_custom_heading shortcodes
        preg_match_all('/\[vc_custom_heading[^\]]*\]/i', $content, $matches);

        foreach ($matches[0] as $shortcode) {
            $link = self::extract_attribute($shortcode, 'link');
            $text = self::extract_attribute($shortcode, 'text');

            if (!empty($link)) {
                $parsed_link = self::parse_vc_link($link);
                if (!empty($parsed_link['url'])) {
                    $links[] = array(
                        'url' => $parsed_link['url'],
                        'anchor_text' => $text ?: $parsed_link['title'] ?: 'Heading Link',
                        'link_type' => self::determine_link_type($parsed_link['url']),
                    );
                }
            }
        }

        return $links;
    }

    /**
     * Parse column text elements (raw HTML)
     *
     * @param string $content Post content
     * @return array
     */
    private static function parse_column_text_elements($content)
    {
        $links = array();

        // Match vc_column_text shortcodes with content
        preg_match_all('/\[vc_column_text[^\]]*\](.*?)\[\/vc_column_text\]/is', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $text_content = $match[1];

            // Extract anchor tags
            preg_match_all('/<a[^>]+href=([\'"])([^\'"]+)\1[^>]*>(.*?)<\/a>/is', $text_content, $link_matches, PREG_SET_ORDER);

            foreach ($link_matches as $link_match) {
                $links[] = array(
                    'url' => $link_match[2],
                    'anchor_text' => wp_strip_all_tags($link_match[3]),
                    'link_type' => self::determine_link_type($link_match[2]),
                );
            }

            // Extract images
            preg_match_all('/<img[^>]+src=([\'"])([^\'"]+)\1[^>]*>/i', $text_content, $img_matches, PREG_SET_ORDER);

            foreach ($img_matches as $img_match) {
                $links[] = array(
                    'url' => $img_match[2],
                    'anchor_text' => '',
                    'link_type' => 'image',
                );
            }
        }

        return $links;
    }

    /**
     * Parse CTA elements
     *
     * @param string $content Post content
     * @return array
     */
    private static function parse_cta_elements($content)
    {
        $links = array();

        // Match vc_cta shortcodes
        preg_match_all('/\[vc_cta[^\]]*\]/i', $content, $matches);

        foreach ($matches[0] as $shortcode) {
            $link = self::extract_attribute($shortcode, 'add_button');
            $btn_link = self::extract_attribute($shortcode, 'btn_link');

            if (!empty($btn_link)) {
                $parsed_link = self::parse_vc_link($btn_link);
                if (!empty($parsed_link['url'])) {
                    $links[] = array(
                        'url' => $parsed_link['url'],
                        'anchor_text' => $parsed_link['title'] ?: 'CTA Button',
                        'link_type' => self::determine_link_type($parsed_link['url']),
                    );
                }
            }
        }

        return $links;
    }

    /**
     * Parse video elements
     *
     * @param string $content Post content
     * @return array
     */
    private static function parse_video_elements($content)
    {
        $links = array();

        // Match vc_video shortcodes
        preg_match_all('/\[vc_video[^\]]*\]/i', $content, $matches);

        foreach ($matches[0] as $shortcode) {
            $link = self::extract_attribute($shortcode, 'link');

            if (!empty($link)) {
                $links[] = array(
                    'url' => $link,
                    'anchor_text' => 'Video',
                    'link_type' => 'external',
                );
            }
        }

        return $links;
    }

    /**
     * Parse WPBakery link format
     * Format: url:http%3A%2F%2Fexample.com|title:Click%20Here|target:_blank
     *
     * @param string $link_string WPBakery link string
     * @return array Parsed link data
     */
    private static function parse_vc_link($link_string)
    {
        $result = array('url' => '', 'title' => '', 'target' => '');

        if (empty($link_string)) {
            return $result;
        }

        // Split by pipe
        $parts = explode('|', $link_string);

        foreach ($parts as $part) {
            if (strpos($part, ':') !== false) {
                list($key, $value) = explode(':', $part, 2);
                $result[$key] = urldecode($value);
            }
        }

        return $result;
    }

    /**
     * Extract attribute from shortcode string
     *
     * @param string $shortcode Shortcode string
     * @param string $attribute Attribute name
     * @return string|null
     */
    private static function extract_attribute($shortcode, $attribute)
    {
        $pattern = '/' . preg_quote($attribute, '/') . '=([\'"])([^\'"]*)\1/i';

        if (preg_match($pattern, $shortcode, $match)) {
            return $match[2];
        }

        return null;
    }

    /**
     * Determine link type from URL
     *
     * @param string $url URL to check
     * @return string Link type
     */
    private static function determine_link_type($url)
    {
        if (empty($url)) {
            return 'internal';
        }

        $site_url = parse_url(home_url(), PHP_URL_HOST);
        $link_host = parse_url($url, PHP_URL_HOST);

        if (empty($link_host) || $link_host === $site_url) {
            return 'internal';
        }

        return 'external';
    }
}
