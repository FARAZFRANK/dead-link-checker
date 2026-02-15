<?php
/**
 * Divi Content Parser
 *
 * Extracts links from Divi page builder content.
 *
 * @package BrokenLinkChecker
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

class FRANKDLC_Parser_Divi
{
    /**
     * Check if Divi is active
     *
     * @return bool
     */
    public static function is_active()
    {
        return defined('ET_BUILDER_VERSION') || function_exists('et_setup_theme');
    }

    /**
     * Check if a post was built with Divi
     *
     * @param int $post_id Post ID
     * @return bool
     */
    public static function is_built_with_divi($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        // Check for Divi shortcodes
        return (
            strpos($post->post_content, '[et_pb_') !== false ||
            get_post_meta($post_id, '_et_pb_use_builder', true) === 'on'
        );
    }

    /**
     * Extract links from Divi content
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

        // Parse Divi shortcodes for links
        $links = array_merge($links, self::parse_button_modules($content));
        $links = array_merge($links, self::parse_image_modules($content));
        $links = array_merge($links, self::parse_blurb_modules($content));
        $links = array_merge($links, self::parse_cta_modules($content));
        $links = array_merge($links, self::parse_text_modules($content));
        $links = array_merge($links, self::parse_video_modules($content));

        return $links;
    }

    /**
     * Parse button modules for links
     *
     * @param string $content Post content
     * @return array
     */
    private static function parse_button_modules($content)
    {
        $links = array();

        // Match et_pb_button shortcodes
        preg_match_all('/\[et_pb_button[^\]]*\]/i', $content, $matches);

        foreach ($matches[0] as $shortcode) {
            $url = self::extract_attribute($shortcode, 'button_url');
            $text = self::extract_attribute($shortcode, 'button_text');

            if (!empty($url)) {
                $links[] = array(
                    'url' => $url,
                    'anchor_text' => $text ?: 'Divi Button',
                    'link_type' => self::determine_link_type($url),
                );
            }
        }

        return $links;
    }

    /**
     * Parse image modules for links
     *
     * @param string $content Post content
     * @return array
     */
    private static function parse_image_modules($content)
    {
        $links = array();

        // Match et_pb_image shortcodes
        preg_match_all('/\[et_pb_image[^\]]*\]/i', $content, $matches);

        foreach ($matches[0] as $shortcode) {
            $src = self::extract_attribute($shortcode, 'src');
            $url = self::extract_attribute($shortcode, 'url');
            $alt = self::extract_attribute($shortcode, 'alt');

            // Image source
            if (!empty($src)) {
                $links[] = array(
                    'url' => $src,
                    'anchor_text' => $alt ?: '',
                    'link_type' => 'image',
                );
            }

            // Image link
            if (!empty($url)) {
                $links[] = array(
                    'url' => $url,
                    'anchor_text' => $alt ?: 'Image Link',
                    'link_type' => self::determine_link_type($url),
                );
            }
        }

        return $links;
    }

    /**
     * Parse blurb modules for links
     *
     * @param string $content Post content
     * @return array
     */
    private static function parse_blurb_modules($content)
    {
        $links = array();

        // Match et_pb_blurb shortcodes
        preg_match_all('/\[et_pb_blurb[^\]]*\]/i', $content, $matches);

        foreach ($matches[0] as $shortcode) {
            $url = self::extract_attribute($shortcode, 'url');
            $title = self::extract_attribute($shortcode, 'title');
            $image = self::extract_attribute($shortcode, 'image');

            if (!empty($url)) {
                $links[] = array(
                    'url' => $url,
                    'anchor_text' => $title ?: 'Blurb Link',
                    'link_type' => self::determine_link_type($url),
                );
            }

            if (!empty($image)) {
                $links[] = array(
                    'url' => $image,
                    'anchor_text' => $title ?: '',
                    'link_type' => 'image',
                );
            }
        }

        return $links;
    }

    /**
     * Parse CTA modules for links
     *
     * @param string $content Post content
     * @return array
     */
    private static function parse_cta_modules($content)
    {
        $links = array();

        // Match et_pb_cta shortcodes
        preg_match_all('/\[et_pb_cta[^\]]*\]/i', $content, $matches);

        foreach ($matches[0] as $shortcode) {
            $url = self::extract_attribute($shortcode, 'button_url');
            $text = self::extract_attribute($shortcode, 'button_text');
            $title = self::extract_attribute($shortcode, 'title');

            if (!empty($url)) {
                $links[] = array(
                    'url' => $url,
                    'anchor_text' => $text ?: $title ?: 'CTA Button',
                    'link_type' => self::determine_link_type($url),
                );
            }
        }

        return $links;
    }

    /**
     * Parse text modules for links (HTML content)
     *
     * @param string $content Post content
     * @return array
     */
    private static function parse_text_modules($content)
    {
        $links = array();

        // Match et_pb_text shortcodes with content
        preg_match_all('/\[et_pb_text[^\]]*\](.*?)\[\/et_pb_text\]/is', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $text_content = $match[1];

            // Extract anchor tags from text content
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
     * Parse video modules for links
     *
     * @param string $content Post content
     * @return array
     */
    private static function parse_video_modules($content)
    {
        $links = array();

        // Match et_pb_video shortcodes
        preg_match_all('/\[et_pb_video[^\]]*\]/i', $content, $matches);

        foreach ($matches[0] as $shortcode) {
            $src = self::extract_attribute($shortcode, 'src');

            if (!empty($src)) {
                $links[] = array(
                    'url' => $src,
                    'anchor_text' => 'Video',
                    'link_type' => 'external',
                );
            }
        }

        return $links;
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

        $site_url = wp_parse_url(home_url(), PHP_URL_HOST);
        $link_host = wp_parse_url($url, PHP_URL_HOST);

        if (empty($link_host) || $link_host === $site_url) {
            return 'internal';
        }

        return 'external';
    }
}
