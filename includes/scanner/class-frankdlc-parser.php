<?php
/**
 * Content Parser
 *
 * Extracts links from HTML content.
 *
 * @package BrokenLinkChecker
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class FRANKDLC_Parser
{

    private $settings;

    public function __construct()
    {
        $this->settings = get_option('FRANKDLC_settings', array());
    }

    public function parse_content($content)
    {
        $links = array();

        if (empty($content)) {
            return $links;
        }

        // Process shortcodes first
        $content = do_shortcode($content);

        // Parse anchor tags
        $links = array_merge($links, $this->parse_anchors($content));

        // Parse images
        if (!empty($this->settings['check_images'])) {
            $links = array_merge($links, $this->parse_images($content));
        }

        // Remove duplicates
        $unique = array();
        foreach ($links as $link) {
            $key = md5($link['url']);
            if (!isset($unique[$key])) {
                $unique[$key] = $link;
            }
        }

        return array_values($unique);
    }

    private function parse_anchors($content)
    {
        $links = array();
        $pattern = '/<a\s[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is';

        if (!preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return $links;
        }

        foreach ($matches as $match) {
            $url = $this->normalize_url($match[1]);
            if (!$this->is_valid_url($url)) {
                continue;
            }

            if ($this->is_excluded($url)) {
                continue;
            }

            $anchor_text = wp_strip_all_tags($match[2]);
            $link_type = FRANKDLC_Link::determine_type($url, 'a');

            // Check if type is enabled
            if (!$this->is_type_enabled($link_type)) {
                continue;
            }

            $links[] = array(
                'url' => $url,
                'link_type' => $link_type,
                'anchor_text' => mb_substr($anchor_text, 0, 500),
            );
        }

        return $links;
    }

    private function parse_images($content)
    {
        $links = array();
        $pattern = '/<img\s[^>]*src\s*=\s*["\']([^"\']+)["\'][^>]*>/is';

        if (!preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return $links;
        }

        foreach ($matches as $match) {
            $url = $this->normalize_url($match[1]);
            if (!$this->is_valid_url($url)) {
                continue;
            }

            if ($this->is_excluded($url)) {
                continue;
            }

            // Get alt text
            $alt = '';
            if (preg_match('/alt\s*=\s*["\']([^"\']*)["\']/', $match[0], $alt_match)) {
                $alt = $alt_match[1];
            }

            $links[] = array(
                'url' => $url,
                'link_type' => FRANKDLC_Link::TYPE_IMAGE,
                'anchor_text' => mb_substr($alt, 0, 500),
            );
        }

        return $links;
    }

    private function normalize_url($url)
    {
        $url = trim($url);

        // Skip empty, anchors, javascript, mailto, tel
        if (empty($url) || $url[0] === '#') {
            return '';
        }

        $skip_protocols = array('javascript:', 'mailto:', 'tel:', 'data:', 'blob:');
        foreach ($skip_protocols as $protocol) {
            if (stripos($url, $protocol) === 0) {
                return '';
            }
        }

        // Convert relative URLs to absolute
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        } elseif (strpos($url, '/') === 0) {
            $url = home_url($url);
        } elseif (!preg_match('/^https?:\/\//i', $url)) {
            $url = home_url('/' . $url);
        }

        // Clean up URL
        $url = esc_url_raw($url);

        return $url;
    }

    private function is_valid_url($url)
    {
        if (empty($url)) {
            return false;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function is_excluded($url)
    {
        $excluded_domains = isset($this->settings['excluded_domains']) ? (array) $this->settings['excluded_domains'] : array();

        if (empty($excluded_domains)) {
            return false;
        }

        $url_host = wp_parse_url($url, PHP_URL_HOST);
        if (!$url_host) {
            return false;
        }

        foreach ($excluded_domains as $domain) {
            $domain = trim(strtolower($domain));
            if (empty($domain))
                continue;

            // Remove protocol if present
            $domain = preg_replace('/^https?:\/\//', '', $domain);
            $domain = rtrim($domain, '/');

            if (strcasecmp($url_host, $domain) === 0 || stripos($url_host, '.' . $domain) !== false) {
                return true;
            }
        }

        return false;
    }

    private function is_type_enabled($link_type)
    {
        switch ($link_type) {
            case FRANKDLC_Link::TYPE_INTERNAL:
                return !empty($this->settings['check_internal']);
            case FRANKDLC_Link::TYPE_EXTERNAL:
                return !empty($this->settings['check_external']);
            case FRANKDLC_Link::TYPE_IMAGE:
                return !empty($this->settings['check_images']);
            default:
                return true;
        }
    }
}
