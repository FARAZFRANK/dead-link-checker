<?php
/**
 * Elementor Content Parser
 *
 * Extracts links from Elementor page builder content.
 *
 * @package BrokenLinkChecker
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

class FRANKDLC_Parser_Elementor
{
    /**
     * Check if Elementor is active
     *
     * @return bool
     */
    public static function is_active()
    {
        return defined('ELEMENTOR_VERSION') || class_exists('Elementor\Plugin');
    }

    /**
     * Check if a post was built with Elementor
     *
     * @param int $post_id Post ID
     * @return bool
     */
    public static function is_built_with_elementor($post_id)
    {
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        return !empty($elementor_data);
    }

    /**
     * Extract links from Elementor content
     *
     * @param int $post_id Post ID
     * @return array Array of link data
     */
    public static function extract_links($post_id)
    {
        $links = array();

        $elementor_data = get_post_meta($post_id, '_elementor_data', true);

        if (empty($elementor_data)) {
            return $links;
        }

        // Elementor stores data as JSON
        if (is_string($elementor_data)) {
            $elementor_data = json_decode($elementor_data, true);
        }

        if (!is_array($elementor_data)) {
            return $links;
        }

        // Recursively parse all elements
        self::parse_elements($elementor_data, $links);

        return $links;
    }

    /**
     * Recursively parse Elementor elements for links
     *
     * @param array $elements Array of Elementor elements
     * @param array &$links   Links array to populate
     */
    private static function parse_elements($elements, &$links)
    {
        foreach ($elements as $element) {
            // Check element settings for links
            if (!empty($element['settings'])) {
                self::parse_element_settings($element['settings'], $links, $element['widgetType'] ?? '');
            }

            // Parse nested elements
            if (!empty($element['elements'])) {
                self::parse_elements($element['elements'], $links);
            }
        }
    }

    /**
     * Parse element settings for links
     *
     * @param array  $settings   Element settings
     * @param array  &$links     Links array
     * @param string $widgetType Widget type for context
     */
    private static function parse_element_settings($settings, &$links, $widgetType = '')
    {
        // Common link fields in Elementor widgets
        $link_fields = array(
            'link',               // Button, Icon, Image
            'button_link',        // Call to Action
            'website_link',       // Social Icons
            'image_link',         // Image Box
            'title_link',         // Icon Box
            'cta_link',           // Flip Box
            'pricing_button_link' // Pricing Table
        );

        foreach ($link_fields as $field) {
            if (!empty($settings[$field]['url'])) {
                $links[] = array(
                    'url' => $settings[$field]['url'],
                    'anchor_text' => self::get_anchor_text($settings, $widgetType),
                    'link_type' => self::determine_link_type($settings[$field]['url']),
                );
            }
        }

        // Handle text editor content (may contain HTML links)
        if (!empty($settings['editor'])) {
            $html_links = self::extract_links_from_html($settings['editor']);
            $links = array_merge($links, $html_links);
        }

        // Handle image URLs
        if (!empty($settings['image']['url'])) {
            $links[] = array(
                'url' => $settings['image']['url'],
                'anchor_text' => $settings['image']['alt'] ?? '',
                'link_type' => 'image',
            );
        }

        // Handle video URLs
        if (!empty($settings['youtube_url'])) {
            $links[] = array(
                'url' => $settings['youtube_url'],
                'anchor_text' => 'YouTube Video',
                'link_type' => 'external',
            );
        }

        if (!empty($settings['vimeo_url'])) {
            $links[] = array(
                'url' => $settings['vimeo_url'],
                'anchor_text' => 'Vimeo Video',
                'link_type' => 'external',
            );
        }

        // Handle social icons
        if (!empty($settings['social_icon_list']) && is_array($settings['social_icon_list'])) {
            foreach ($settings['social_icon_list'] as $icon) {
                if (!empty($icon['link']['url'])) {
                    $links[] = array(
                        'url' => $icon['link']['url'],
                        'anchor_text' => $icon['social_icon']['value'] ?? 'Social Link',
                        'link_type' => 'external',
                    );
                }
            }
        }
    }

    /**
     * Get anchor text based on widget type
     *
     * @param array  $settings   Element settings
     * @param string $widgetType Widget type
     * @return string
     */
    private static function get_anchor_text($settings, $widgetType)
    {
        // Try common text fields
        $text_fields = array('text', 'title', 'button_text', 'heading');

        foreach ($text_fields as $field) {
            if (!empty($settings[$field])) {
                return wp_strip_all_tags($settings[$field]);
            }
        }

        return $widgetType ?: 'Elementor Link';
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

    /**
     * Extract links from HTML content
     *
     * @param string $html HTML content
     * @return array Array of link data
     */
    private static function extract_links_from_html($html)
    {
        $links = array();

        if (empty($html)) {
            return $links;
        }

        // Extract anchor tags
        preg_match_all('/<a[^>]+href=([\'"])([^\'"]+)\1[^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $links[] = array(
                'url' => $match[2],
                'anchor_text' => wp_strip_all_tags($match[3]),
                'link_type' => self::determine_link_type($match[2]),
            );
        }

        // Extract image sources
        preg_match_all('/<img[^>]+src=([\'"])([^\'"]+)\1[^>]*>/i', $html, $img_matches, PREG_SET_ORDER);

        foreach ($img_matches as $match) {
            $links[] = array(
                'url' => $match[2],
                'anchor_text' => '',
                'link_type' => 'image',
            );
        }

        return $links;
    }
}
