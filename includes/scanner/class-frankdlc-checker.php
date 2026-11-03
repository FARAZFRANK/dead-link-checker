<?php
/**
 * Link Checker
 *
 * Performs HTTP requests to check link status with SSRF protection.
 *
 * @package BrokenLinkChecker
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class FRANKDLC_Checker
{

    private $settings;

    public function __construct()
    {
        $this->settings = get_option('FRANKDLC_settings', array());
    }

    /**
     * Validate that a URL is safe to request (SSRF protection).
     *
     * @param string $url The URL to validate.
     * @param string &$error_message Output error message if invalid.
     * @return bool True if safe, false otherwise.
     */
    public function is_safe_url($url, &$error_message = '')
    {
        $parsed = wp_parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            $error_message = __('Invalid URL format', 'frank-dead-link-checker');
            return false;
        }

        // Scheme check: Only http and https
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, array('http', 'https'), true)) {
            $error_message = __('Unsupported URL protocol', 'frank-dead-link-checker');
            return false;
        }

        // Port check: Only allow standard web ports
        if (isset($parsed['port'])) {
            $allowed_ports = apply_filters('frankdlc_allowed_ports', array(80, 443, 8080, 8443));
            if (!in_array((int) $parsed['port'], $allowed_ports, true)) {
                $error_message = __('Port not allowed', 'frank-dead-link-checker');
                return false;
            }
        }

        $host = strtolower($parsed['host']);

        // Check if this host is the current site's own host (allows local dev testing of site's own links)
        $site_host = strtolower(wp_parse_url(home_url(), PHP_URL_HOST) ?: '');
        if (!empty($site_host) && $host === $site_host) {
            return true;
        }

        // Reject local/internal domain patterns
        $blocked_host_patterns = array(
            'localhost',
            '127.0.0.1',
            '::1',
            '0.0.0.0',
        );
        if (in_array($host, $blocked_host_patterns, true) || preg_match('/\.(local|internal|test|onion|lan)$/i', $host)) {
            $error_message = __('Internal/localhost domains are not allowed', 'frank-dead-link-checker');
            return false;
        }

        // Cloud metadata protection
        if ($host === '169.254.169.254' || $host === 'metadata.google.internal' || $host === 'instance-data') {
            $error_message = __('Cloud metadata endpoint is not allowed', 'frank-dead-link-checker');
            return false;
        }

        // Resolve IP addresses
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? array($host) : @gethostbynamel($host);
        if (empty($ips) || !is_array($ips)) {
            // If DNS cannot resolve, let it proceed to HTTP check which will register a DNS Error
            return true;
        }

        foreach ($ips as $ip) {
            // Check private and reserved ranges
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $error_message = __('Access to private or reserved IP ranges is blocked', 'frank-dead-link-checker');
                return false;
            }

            // Explicit checks for loopback and link-local ranges
            if (strpos($ip, '127.') === 0 || strpos($ip, '169.254.') === 0 || strpos($ip, '10.') === 0 || strpos($ip, '0.') === 0) {
                $error_message = __('Access to private or reserved IP ranges is blocked', 'frank-dead-link-checker');
                return false;
            }
        }

        $is_safe = true;
        return apply_filters('frankdlc_is_safe_url', $is_safe, $url, $host);
    }

    public function check_url($url)
    {
        $start_time = microtime(true);

        $result = array(
            'status_code' => null,
            'status_text' => '',
            'is_broken' => false,
            'is_warning' => false,
            'redirect_url' => null,
            'redirect_count' => 0,
            'response_time' => null,
            'error_message' => null,
        );

        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $result['is_broken'] = true;
            $result['error_message'] = __('Invalid URL format', 'frank-dead-link-checker');
            return $result;
        }

        // SSRF protection check
        $ssrf_error = '';
        if (!$this->is_safe_url($url, $ssrf_error)) {
            $result['is_broken'] = true;
            $result['status_code'] = 0;
            $result['status_text'] = 'Blocked';
            $result['error_message'] = $ssrf_error;
            return $result;
        }

        $timeout = isset($this->settings['timeout']) ? absint($this->settings['timeout']) : 30;
        $user_agent = isset($this->settings['user_agent']) && !empty($this->settings['user_agent'])
            ? $this->settings['user_agent']
            : 'Mozilla/5.0 (compatible; BrokenLinkChecker/' . FRANKDLC_VERSION . ')';
        $verify_ssl = isset($this->settings['verify_ssl']) ? (bool) $this->settings['verify_ssl'] : true;

        $args = array(
            'timeout' => $timeout,
            'redirection' => 0, // We'll handle redirects manually
            'user-agent' => $user_agent,
            'sslverify' => $verify_ssl,
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ),
        );

        // First try HEAD request (faster)
        $response = wp_remote_head($url, $args);

        // If HEAD fails or returns 405, try GET
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) === 405) {
            $args['timeout'] = min($timeout, 15); // Shorter timeout for GET
            $response = wp_remote_get($url, $args);
        }

        $result['response_time'] = round(microtime(true) - $start_time, 3);

        // Handle errors
        if (is_wp_error($response)) {
            $result['is_broken'] = true;
            $result['error_message'] = $response->get_error_message();
            $result['status_text'] = 'Error';

            // Check for specific error types
            $error_code = $response->get_error_code();
            if (strpos($error_code, 'ssl') !== false || strpos($response->get_error_message(), 'SSL') !== false) {
                $result['status_text'] = 'SSL Error';
            } elseif (strpos($error_code, 'timeout') !== false || strpos($error_code, 'timed_out') !== false) {
                $result['status_text'] = 'Timeout';
            } elseif (strpos($error_code, 'resolve') !== false || strpos($error_code, 'dns') !== false) {
                $result['status_text'] = 'DNS Error';
            }

            return $result;
        }

        // Get status code
        $status_code = wp_remote_retrieve_response_code($response);
        $result['status_code'] = $status_code;
        $result['status_text'] = wp_remote_retrieve_response_message($response);

        // Check for redirects
        $redirect_url = wp_remote_retrieve_header($response, 'location');
        if (!empty($redirect_url) && in_array($status_code, array(301, 302, 303, 307, 308), true)) {
            $result['redirect_url'] = $redirect_url;
            $result = $this->follow_redirects($url, $result, $args);
        }

        // Determine if broken or warning
        $result = $this->evaluate_status($result);

        return $result;
    }

    private function follow_redirects($original_url, $result, $args)
    {
        $max_redirects = 5;
        $current_url = $result['redirect_url'];
        $redirect_count = 0;

        while ($redirect_count < $max_redirects && !empty($current_url)) {
            $redirect_count++;

            // Handle relative redirects (including protocol-relative //example.com)
            if (strpos($current_url, '//') === 0) {
                $parsed = wp_parse_url($original_url);
                $current_url = ($parsed['scheme'] ?? 'http') . ':' . $current_url;
            } elseif (strpos($current_url, '/') === 0) {
                $parsed = wp_parse_url($original_url);
                $current_url = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '') . $current_url;
            } elseif (!preg_match('/^https?:\/\//i', $current_url)) {
                $base = dirname($original_url);
                $current_url = $base . '/' . $current_url;
            }

            // Re-validate target URL for SSRF protection
            $redirect_error = '';
            if (!$this->is_safe_url($current_url, $redirect_error)) {
                $result['is_broken'] = true;
                /* translators: %s: Reason the redirect target was blocked */
                $result['error_message'] = sprintf(__('Redirect blocked: %s', 'frank-dead-link-checker'), $redirect_error);
                $result['status_text'] = 'Blocked';
                break;
            }

            $response = wp_remote_head($current_url, $args);

            if (is_wp_error($response)) {
                $result['is_broken'] = true;
                $result['error_message'] = $response->get_error_message();
                break;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $result['status_code'] = $status_code;
            $result['status_text'] = wp_remote_retrieve_response_message($response);
            $result['redirect_url'] = $current_url;

            $next_redirect = wp_remote_retrieve_header($response, 'location');
            if (empty($next_redirect) || !in_array($status_code, array(301, 302, 303, 307, 308), true)) {
                break;
            }

            $current_url = $next_redirect;
        }

        $result['redirect_count'] = $redirect_count;

        // Mark as warning if redirected
        if ($redirect_count > 0 && !$result['is_broken']) {
            $result['is_warning'] = true;
        }

        return $result;
    }

    private function evaluate_status($result)
    {
        $status_code = (int) $result['status_code'];

        // Broken: 4xx and 5xx errors or 0
        if ($status_code >= 400 || $status_code === 0) {
            $result['is_broken'] = true;
        }
        // Warning: Redirects (already set in follow_redirects)
        elseif (in_array($status_code, array(301, 302, 303, 307, 308), true)) {
            $result['is_warning'] = true;
        }
        // Warning: Slow response (> 5 seconds)
        elseif ($result['response_time'] > 5) {
            $result['is_warning'] = true;
            if (empty($result['status_text']) || $result['status_text'] === 'OK') {
                $result['status_text'] = 'Slow';
            }
        }

        return $result;
    }

    public function check_batch($urls)
    {
        $results = array();
        foreach ($urls as $url) {
            $results[$url] = $this->check_url($url);
        }
        return $results;
    }
}