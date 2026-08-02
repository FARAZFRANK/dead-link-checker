<?php
/**
 * Link Checker
 *
 * Performs HTTP requests to check link status.
 *
 * @package BrokenLinkChecker
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class FRANKDLC_Checker {

	/**
	 * Plugin settings, used for timeout/user-agent/SSL configuration.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'FRANKDLC_settings', array() );
	}

	/**
	 * Check a single URL's HTTP status, following redirects as needed.
	 *
	 * @param string $url URL to check.
	 * @return array {
	 *     Check result.
	 *
	 *     @type int|null  $status_code    HTTP status code, or null on request failure.
	 *     @type string    $status_text    Human-readable status/response message.
	 *     @type bool      $is_broken      Whether the link is considered broken.
	 *     @type bool      $is_warning     Whether the link is considered a warning (redirect/slow).
	 *     @type string|null $redirect_url Final redirect target URL, if any.
	 *     @type int       $redirect_count Number of redirects followed.
	 *     @type float|null $response_time Response time in seconds.
	 *     @type string|null $error_message Error message on failure.
	 * }
	 */
	public function check_url( $url ) {
		$start_time = microtime( true );

		$result = array(
			'status_code'    => null,
			'status_text'    => '',
			'is_broken'      => false,
			'is_warning'     => false,
			'redirect_url'   => null,
			'redirect_count' => 0,
			'response_time'  => null,
			'error_message'  => null,
		);

		// Validate URL
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			$result['is_broken']     = true;
			$result['error_message'] = __( 'Invalid URL format', 'frank-dead-link-checker' );
			return $result;
		}

		if ( ! $this->is_safe_url( $url ) ) {
			$result['is_broken']     = true;
			$result['error_message'] = __( 'This URL is not allowed to be checked.', 'frank-dead-link-checker' );
			return $result;
		}

		$timeout    = isset( $this->settings['timeout'] ) ? absint( $this->settings['timeout'] ) : 30;
		$user_agent = isset( $this->settings['user_agent'] ) && ! empty( $this->settings['user_agent'] )
			? $this->settings['user_agent']
			: 'Mozilla/5.0 (compatible; BrokenLinkChecker/' . FRANKDLC_VERSION . ')';
		$verify_ssl = isset( $this->settings['verify_ssl'] ) ? (bool) $this->settings['verify_ssl'] : true;

		$args = array(
			'timeout'            => $timeout,
			'redirection'        => 0, // We'll handle redirects manually
			'user-agent'         => $user_agent,
			'sslverify'          => $verify_ssl,
			'reject_unsafe_urls' => true, // Block loopback/private/link-local targets (SSRF hardening)
			'headers'            => array(
				'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			),
		);

		// First try HEAD request (faster)
		$response = wp_remote_head( $url, $args );

		// If HEAD fails or returns 405, try GET
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) === 405 ) {
			$args['timeout'] = min( $timeout, 15 ); // Shorter timeout for GET
			$response        = wp_remote_get( $url, $args );
		}

		$result['response_time'] = round( microtime( true ) - $start_time, 3 );

		// Handle errors
		if ( is_wp_error( $response ) ) {
			$result['is_broken']     = true;
			$result['error_message'] = $response->get_error_message();
			$result['status_text']   = 'Error';

			// Check for specific error types
			$error_code = $response->get_error_code();
			if ( strpos( $error_code, 'ssl' ) !== false || strpos( $response->get_error_message(), 'SSL' ) !== false ) {
				$result['status_text'] = 'SSL Error';
			} elseif ( strpos( $error_code, 'timeout' ) !== false || strpos( $error_code, 'timed_out' ) !== false ) {
				$result['status_text'] = 'Timeout';
			} elseif ( strpos( $error_code, 'resolve' ) !== false || strpos( $error_code, 'dns' ) !== false ) {
				$result['status_text'] = 'DNS Error';
			}

			return $result;
		}

		// Get status code
		$status_code           = wp_remote_retrieve_response_code( $response );
		$result['status_code'] = $status_code;
		$result['status_text'] = wp_remote_retrieve_response_message( $response );

		// Check for redirects
		$redirect_url = wp_remote_retrieve_header( $response, 'location' );
		if ( ! empty( $redirect_url ) && in_array( $status_code, array( 301, 302, 303, 307, 308 ), true ) ) {
			$result['redirect_url'] = $redirect_url;
			$result                 = $this->follow_redirects( $url, $result, $args );
		}

		// Determine if broken or warning
		$result = $this->evaluate_status( $result );

		return $result;
	}

	/**
	 * Manually follow a chain of HTTP redirects, revalidating the host on every hop.
	 *
	 * @param string $original_url Original URL that produced the first redirect.
	 * @param array  $result       Result array as built by check_url(), already carrying the first redirect_url.
	 * @param array  $args         wp_remote_head() request args to reuse for each hop.
	 * @return array Updated result array.
	 */
	private function follow_redirects( $original_url, $result, $args ) {
		$max_redirects  = 5;
		$current_url    = $result['redirect_url'];
		$redirect_count = 0;

		while ( $redirect_count < $max_redirects && ! empty( $current_url ) ) {
			++$redirect_count;

			// Handle relative redirects
			if ( strpos( $current_url, '/' ) === 0 ) {
				$parsed      = wp_parse_url( $original_url );
				$current_url = $parsed['scheme'] . '://' . $parsed['host'] . $current_url;
			} elseif ( ! preg_match( '/^https?:\/\//i', $current_url ) ) {
				$base        = dirname( $original_url );
				$current_url = $base . '/' . $current_url;
			}

			// Re-validate the resolved host on every hop; 'reject_unsafe_urls' in $args
			// only covers the initial request WP_Http::request() makes, not URLs we
			// dereference ourselves from a Location header (SSRF hardening).
			$safe_url = $this->is_safe_url( $current_url );
			if ( ! $safe_url ) {
				$result['is_broken']     = true;
				$result['error_message'] = __( 'Redirect target is not an allowed URL.', 'frank-dead-link-checker' );
				break;
			}
			$current_url = $safe_url;

			$response = wp_remote_head( $current_url, $args );

			if ( is_wp_error( $response ) ) {
				$result['is_broken']     = true;
				$result['error_message'] = $response->get_error_message();
				break;
			}

			$status_code            = wp_remote_retrieve_response_code( $response );
			$result['status_code']  = $status_code;
			$result['status_text']  = wp_remote_retrieve_response_message( $response );
			$result['redirect_url'] = $current_url;

			$next_redirect = wp_remote_retrieve_header( $response, 'location' );
			if ( empty( $next_redirect ) || ! in_array( $status_code, array( 301, 302, 303, 307, 308 ), true ) ) {
				break;
			}

			$current_url = $next_redirect;
		}

		$result['redirect_count'] = $redirect_count;

		// Mark as warning if redirected
		if ( $redirect_count > 0 && ! $result['is_broken'] ) {
			$result['is_warning'] = true;
		}

		return $result;
	}

	/**
	 * Check whether a URL is safe to request: valid scheme/format per wp_http_validate_url()
	 * (which blocks loopback and RFC1918 private ranges), plus an additional check for
	 * link-local addresses (169.254.0.0/16 and IPv6 equivalents) — the range cloud
	 * providers use for instance metadata services and a common real-world SSRF target
	 * that wp_http_validate_url() does not cover.
	 *
	 * Like wp_http_validate_url(), requests back to the site's own host are exempted from
	 * the IP-range checks: this plugin's core purpose is checking a site's own internal
	 * links, and local/dev/intranet sites commonly resolve their own host to a private or
	 * loopback address.
	 *
	 * @param string $url URL to check.
	 * @return string|false The validated URL, or false if it should be blocked.
	 */
	private function is_safe_url( $url ) {
		$validated_url = wp_http_validate_url( $url );
		if ( ! $validated_url ) {
			return false;
		}

		$host = wp_parse_url( $validated_url, PHP_URL_HOST );
		if ( ! $host ) {
			return false;
		}

		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $home_host && 0 === strcasecmp( $home_host, $host ) ) {
			return $validated_url;
		}

		$ip = filter_var( $host, FILTER_VALIDATE_IP ) ? $host : gethostbyname( $host );

		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}

		return $validated_url;
	}

	/**
	 * Classify a check result as broken, a warning, or OK based on status code and timing.
	 *
	 * @param array $result Result array as built by check_url()/follow_redirects().
	 * @return array Updated result array with is_broken/is_warning/status_text set.
	 */
	private function evaluate_status( $result ) {
		$status_code = $result['status_code'];

		if ( $status_code >= 400 ) {
			// Broken: 4xx and 5xx errors.
			$result['is_broken'] = true;
		} elseif ( in_array( $status_code, array( 301, 302, 303, 307, 308 ), true ) ) {
			// Warning: Redirects (already set in follow_redirects).
			$result['is_warning'] = true;
		} elseif ( $result['response_time'] > 5 ) {
			// Warning: Slow response (> 5 seconds).
			$result['is_warning'] = true;
			if ( empty( $result['status_text'] ) || 'OK' === $result['status_text'] ) {
				$result['status_text'] = 'Slow';
			}
		}

		return $result;
	}

	/**
	 * Check a batch of URLs.
	 *
	 * @param string[] $urls URLs to check.
	 * @return array Map of URL => check_url() result.
	 */
	public function check_batch( $urls ) {
		$results = array();
		foreach ( $urls as $url ) {
			$results[ $url ] = $this->check_url( $url );
		}
		return $results;
	}
}
