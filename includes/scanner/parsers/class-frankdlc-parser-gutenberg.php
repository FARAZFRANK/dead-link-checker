<?php
/**
 * Gutenberg Content Parser
 *
 * Extracts links from Gutenberg block content.
 *
 * @package BrokenLinkChecker
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

class FRANKDLC_Parser_Gutenberg
{
    /**
     * Check if Gutenberg is available
     *
     * @return bool
     */
    public static function is_active()
    {
        return function_exists('parse_blocks');
    }

    /**
     * Check if content contains Gutenberg blocks
     *
     * @param int $post_id Post ID
     * @return bool
     */
    public static function has_blocks($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        return has_blocks($post->post_content);
    }

    /**
     * Extract links from Gutenberg content
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

        // Parse blocks
        $blocks = parse_blocks($post->post_content);

        // Recursively process blocks
        self::process_blocks($blocks, $links);

        return $links;
    }

    /**
     * Recursively process blocks for links
     *
     * @param array $blocks Array of blocks
     * @param array &$links Links array to populate
     */
    private static function process_blocks($blocks, &$links)
    {
        foreach ($blocks as $block) {
            // Process this block
            self::extract_block_links($block, $links);

            // Process inner blocks
            if (!empty($block['innerBlocks'])) {
                self::process_blocks($block['innerBlocks'], $links);
            }
        }
    }

    /**
     * Extract links from a single block
     *
     * @param array $block Block data
     * @param array &$links Links array
     */
    private static function extract_block_links($block, &$links)
    {
        $block_name = $block['blockName'] ?? '';
        $attrs = $block['attrs'] ?? array();
        $inner_html = $block['innerHTML'] ?? '';

        switch ($block_name) {
            case 'core/button':
            case 'core/buttons':
                self::parse_button_block($attrs, $inner_html, $links);
                break;

            case 'core/image':
                self::parse_image_block($attrs, $inner_html, $links);
                break;

            case 'core/cover':
                self::parse_cover_block($attrs, $inner_html, $links);
                break;

            case 'core/media-text':
                self::parse_media_text_block($attrs, $inner_html, $links);
                break;

            case 'core/gallery':
                self::parse_gallery_block($attrs, $inner_html, $links);
                break;

            case 'core/file':
                self::parse_file_block($attrs, $inner_html, $links);
                break;

            case 'core/video':
                self::parse_video_block($attrs, $inner_html, $links);
                break;

            case 'core/audio':
                self::parse_audio_block($attrs, $inner_html, $links);
                break;

            case 'core/embed':
            case 'core-embed/youtube':
            case 'core-embed/vimeo':
                self::parse_embed_block($attrs, $inner_html, $links);
                break;

            case 'core/social-link':
                self::parse_social_link_block($attrs, $links);
                break;

            case 'core/navigation-link':
                self::parse_navigation_link_block($attrs, $links);
                break;

            default:
                // For all other blocks, parse HTML for links
                if (!empty($inner_html)) {
                    self::parse_html_for_links($inner_html, $links);
                }
                break;
        }
    }

    /**
     * Parse button block
     */
    private static function parse_button_block($attrs, $inner_html, &$links)
    {
        // Get URL from attributes
        $url = $attrs['url'] ?? '';

        if (empty($url)) {
            // Try to extract from HTML
            preg_match('/href=([\'"])([^\'"]+)\1/', $inner_html, $match);
            $url = $match[2] ?? '';
        }

        if (!empty($url)) {
            // Get text from HTML
            $text = wp_strip_all_tags($inner_html);

            $links[] = array(
                'url' => $url,
                'anchor_text' => trim($text) ?: 'Button',
                'link_type' => self::determine_link_type($url),
            );
        }
    }

    /**
     * Parse image block
     */
    private static function parse_image_block($attrs, $inner_html, &$links)
    {
        // Image URL from attributes
        $url = $attrs['url'] ?? '';
        $alt = $attrs['alt'] ?? '';
        $link = $attrs['href'] ?? '';

        if (!empty($url)) {
            $links[] = array(
                'url' => $url,
                'anchor_text' => $alt,
                'link_type' => 'image',
            );
        }

        // Image link
        if (!empty($link)) {
            $links[] = array(
                'url' => $link,
                'anchor_text' => $alt ?: 'Image Link',
                'link_type' => self::determine_link_type($link),
            );
        }
    }

    /**
     * Parse cover block
     */
    private static function parse_cover_block($attrs, $inner_html, &$links)
    {
        $url = $attrs['url'] ?? '';

        if (!empty($url)) {
            $links[] = array(
                'url' => $url,
                'anchor_text' => 'Cover Image',
                'link_type' => 'image',
            );
        }

        // Parse inner HTML for links
        self::parse_html_for_links($inner_html, $links);
    }

    /**
     * Parse media-text block
     */
    private static function parse_media_text_block($attrs, $inner_html, &$links)
    {
        $url = $attrs['mediaUrl'] ?? '';

        if (!empty($url)) {
            $type = $attrs['mediaType'] ?? 'image';
            $links[] = array(
                'url' => $url,
                'anchor_text' => '',
                'link_type' => $type === 'image' ? 'image' : 'external',
            );
        }

        // Parse inner HTML
        self::parse_html_for_links($inner_html, $links);
    }

    /**
     * Parse gallery block
     */
    private static function parse_gallery_block($attrs, $inner_html, &$links)
    {
        // Gallery images from attributes
        $images = $attrs['images'] ?? array();

        foreach ($images as $image) {
            if (!empty($image['url'])) {
                $links[] = array(
                    'url' => $image['url'],
                    'anchor_text' => $image['alt'] ?? '',
                    'link_type' => 'image',
                );
            }

            if (!empty($image['link'])) {
                $links[] = array(
                    'url' => $image['link'],
                    'anchor_text' => $image['alt'] ?? 'Gallery Image Link',
                    'link_type' => self::determine_link_type($image['link']),
                );
            }
        }

        // Also parse from HTML (newer gallery format)
        preg_match_all('/<img[^>]+src=([\'"])([^\'"]+)\1[^>]*>/i', $inner_html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $links[] = array(
                'url' => $match[2],
                'anchor_text' => '',
                'link_type' => 'image',
            );
        }
    }

    /**
     * Parse file block
     */
    private static function parse_file_block($attrs, $inner_html, &$links)
    {
        $url = $attrs['href'] ?? '';

        if (!empty($url)) {
            $links[] = array(
                'url' => $url,
                'anchor_text' => $attrs['fileName'] ?? 'File Download',
                'link_type' => self::determine_link_type($url),
            );
        }
    }

    /**
     * Parse video block
     */
    private static function parse_video_block($attrs, $inner_html, &$links)
    {
        $url = $attrs['src'] ?? '';

        if (!empty($url)) {
            $links[] = array(
                'url' => $url,
                'anchor_text' => 'Video',
                'link_type' => self::determine_link_type($url),
            );
        }

        // Poster image
        if (!empty($attrs['poster'])) {
            $links[] = array(
                'url' => $attrs['poster'],
                'anchor_text' => 'Video Poster',
                'link_type' => 'image',
            );
        }
    }

    /**
     * Parse audio block
     */
    private static function parse_audio_block($attrs, $inner_html, &$links)
    {
        $url = $attrs['src'] ?? '';

        if (!empty($url)) {
            $links[] = array(
                'url' => $url,
                'anchor_text' => 'Audio',
                'link_type' => self::determine_link_type($url),
            );
        }
    }

    /**
     * Parse embed block
     */
    private static function parse_embed_block($attrs, $inner_html, &$links)
    {
        $url = $attrs['url'] ?? '';

        if (!empty($url)) {
            $links[] = array(
                'url' => $url,
                'anchor_text' => $attrs['caption'] ?? 'Embedded Content',
                'link_type' => 'external',
            );
        }
    }

    /**
     * Parse social link block
     */
    private static function parse_social_link_block($attrs, &$links)
    {
        $url = $attrs['url'] ?? '';
        $service = $attrs['service'] ?? '';

        if (!empty($url)) {
            $links[] = array(
                'url' => $url,
                'anchor_text' => ucfirst($service) ?: 'Social Link',
                'link_type' => 'external',
            );
        }
    }

    /**
     * Parse navigation link block
     */
    private static function parse_navigation_link_block($attrs, &$links)
    {
        $url = $attrs['url'] ?? '';
        $label = $attrs['label'] ?? '';

        if (!empty($url)) {
            $links[] = array(
                'url' => $url,
                'anchor_text' => $label ?: 'Navigation Link',
                'link_type' => self::determine_link_type($url),
            );
        }
    }

    /**
     * Parse HTML content for links
     */
    private static function parse_html_for_links($html, &$links)
    {
        if (empty($html)) {
            return;
        }

        // Extract anchor tags
        preg_match_all('/<a[^>]+href=([\'"])([^\'"]+)\1[^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $url = $match[2];
            // Skip anchors and javascript
            if (strpos($url, '#') === 0 || strpos($url, 'javascript:') === 0) {
                continue;
            }

            $links[] = array(
                'url' => $url,
                'anchor_text' => wp_strip_all_tags($match[3]),
                'link_type' => self::determine_link_type($url),
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
    }

    /**
     * Determine link type from URL
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
