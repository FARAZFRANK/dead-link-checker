<?php
/**
 * Settings Handler
 *
 * Handles plugin settings page and options.
 *
 * @package BrokenLinkChecker
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class BLC_Settings
{

    public function __construct()
    {
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings()
    {
        register_setting('blc_settings_group', 'blc_settings', array($this, 'sanitize_settings'));

        // General Section
        add_settings_section('blc_general', __('General Settings', 'dead-link-checker'), null, 'blc-settings');
        add_settings_field('scan_frequency', __('Scan Frequency', 'dead-link-checker'), array($this, 'field_scan_frequency'), 'blc-settings', 'blc_general');
        add_settings_field('timeout', __('Request Timeout', 'dead-link-checker'), array($this, 'field_timeout'), 'blc-settings', 'blc_general');

        // Scan Scope Section
        add_settings_section('blc_scope', __('Scan Scope', 'dead-link-checker'), null, 'blc-settings');
        add_settings_field('content_types', __('Content to Scan', 'dead-link-checker'), array($this, 'field_content_types'), 'blc-settings', 'blc_scope');
        add_settings_field('link_types', __('Link Types', 'dead-link-checker'), array($this, 'field_link_types'), 'blc-settings', 'blc_scope');

        // Exclusions Section
        add_settings_section('blc_exclusions', __('Exclusions', 'dead-link-checker'), null, 'blc-settings');
        add_settings_field('excluded_domains', __('Excluded Domains', 'dead-link-checker'), array($this, 'field_excluded_domains'), 'blc-settings', 'blc_exclusions');

        // Notifications Section
        add_settings_section('blc_notifications', __('Notifications', 'dead-link-checker'), null, 'blc-settings');
        add_settings_field('email_notifications', __('Email Notifications', 'dead-link-checker'), array($this, 'field_email_notifications'), 'blc-settings', 'blc_notifications');

        // Advanced Section
        add_settings_section('blc_advanced', __('Advanced', 'dead-link-checker'), null, 'blc-settings');
        add_settings_field('concurrent_requests', __('Concurrent Requests', 'dead-link-checker'), array($this, 'field_concurrent_requests'), 'blc-settings', 'blc_advanced');
        add_settings_field('user_agent', __('User Agent', 'dead-link-checker'), array($this, 'field_user_agent'), 'blc-settings', 'blc_advanced');
    }

    public function sanitize_settings($input)
    {
        $sanitized = array();
        $sanitized['scan_frequency'] = sanitize_key($input['scan_frequency'] ?? 'daily');
        $sanitized['timeout'] = absint($input['timeout'] ?? 30);
        $sanitized['scan_posts'] = !empty($input['scan_posts']);
        $sanitized['scan_pages'] = !empty($input['scan_pages']);
        $sanitized['scan_comments'] = !empty($input['scan_comments']);
        $sanitized['scan_widgets'] = !empty($input['scan_widgets']);
        $sanitized['scan_menus'] = !empty($input['scan_menus']);
        $sanitized['scan_custom_fields'] = !empty($input['scan_custom_fields']);
        $sanitized['check_internal'] = !empty($input['check_internal']);
        $sanitized['check_external'] = !empty($input['check_external']);
        $sanitized['check_images'] = !empty($input['check_images']);
        $sanitized['excluded_domains'] = array_filter(array_map('sanitize_text_field', explode("\n", $input['excluded_domains'] ?? '')));
        $sanitized['email_notifications'] = !empty($input['email_notifications']);
        $sanitized['email_frequency'] = sanitize_key($input['email_frequency'] ?? 'weekly');
        $sanitized['email_recipients'] = array_filter(array_map('sanitize_email', explode("\n", $input['email_recipients'] ?? get_option('admin_email'))));
        $sanitized['concurrent_requests'] = min(10, max(1, absint($input['concurrent_requests'] ?? 3)));
        $sanitized['user_agent'] = sanitize_text_field($input['user_agent'] ?? '');
        $sanitized['verify_ssl'] = !empty($input['verify_ssl']);
        return $sanitized;
    }

    private function get_option($key, $default = null)
    {
        $options = get_option('blc_settings', array());
        return isset($options[$key]) ? $options[$key] : $default;
    }

    public function field_scan_frequency()
    {
        $value = $this->get_option('scan_frequency', 'daily');
        ?>
        <select name="blc_settings[scan_frequency]">
            <option value="hourly" <?php selected($value, 'hourly'); ?>>
                <?php esc_html_e('Hourly', 'dead-link-checker'); ?>
            </option>
            <option value="twicedaily" <?php selected($value, 'twicedaily'); ?>>
                <?php esc_html_e('Twice Daily', 'dead-link-checker'); ?>
            </option>
            <option value="daily" <?php selected($value, 'daily'); ?>>
                <?php esc_html_e('Daily', 'dead-link-checker'); ?>
            </option>
            <option value="weekly" <?php selected($value, 'weekly'); ?>>
                <?php esc_html_e('Weekly', 'dead-link-checker'); ?>
            </option>
            <option value="manual" <?php selected($value, 'manual'); ?>>
                <?php esc_html_e('Manual Only', 'dead-link-checker'); ?>
            </option>
        </select>
        <?php
    }

    public function field_timeout()
    {
        $value = $this->get_option('timeout', 30);
        ?>
        <input type="number" name="blc_settings[timeout]" value="<?php echo esc_attr($value); ?>" min="5" max="120" step="1">
        <?php esc_html_e('seconds', 'dead-link-checker'); ?>
        <p class="description">
            <?php esc_html_e('Maximum time to wait for a response from a link.', 'dead-link-checker'); ?>
        </p>
        <?php
    }

    public function field_content_types()
    {
        $types = array(
            'scan_posts' => __('Posts', 'dead-link-checker'),
            'scan_pages' => __('Pages', 'dead-link-checker'),
            'scan_comments' => __('Comments', 'dead-link-checker'),
            'scan_widgets' => __('Widgets', 'dead-link-checker'),
            'scan_menus' => __('Menus', 'dead-link-checker'),
            'scan_custom_fields' => __('Custom Fields (ACF)', 'dead-link-checker'),
        );
        foreach ($types as $key => $label) {
            $checked = $this->get_option($key, in_array($key, array('scan_posts', 'scan_pages', 'scan_widgets', 'scan_menus'), true));
            printf('<label><input type="checkbox" name="blc_settings[%s]" value="1" %s> %s</label><br>', esc_attr($key), checked($checked, true, false), esc_html($label));
        }
    }

    public function field_link_types()
    {
        $types = array('check_internal' => __('Internal Links', 'dead-link-checker'), 'check_external' => __('External Links', 'dead-link-checker'), 'check_images' => __('Images', 'dead-link-checker'));
        foreach ($types as $key => $label) {
            $checked = $this->get_option($key, true);
            printf('<label><input type="checkbox" name="blc_settings[%s]" value="1" %s> %s</label><br>', esc_attr($key), checked($checked, true, false), esc_html($label));
        }
    }

    public function field_excluded_domains()
    {
        $value = implode("\n", (array) $this->get_option('excluded_domains', array()));
        ?>
        <textarea name="blc_settings[excluded_domains]" rows="5" cols="50"
            class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php esc_html_e('Enter one domain per line (e.g., example.com). Links to these domains will be ignored.', 'dead-link-checker'); ?>
        </p>
        <?php
    }

    public function field_email_notifications()
    {
        $enabled = $this->get_option('email_notifications', true);
        $frequency = $this->get_option('email_frequency', 'weekly');
        $recipients = implode("\n", (array) $this->get_option('email_recipients', array(get_option('admin_email'))));
        ?>
        <label><input type="checkbox" name="blc_settings[email_notifications]" value="1" <?php checked($enabled); ?>>
            <?php esc_html_e('Enable email notifications', 'dead-link-checker'); ?>
        </label>
        <br><br>
        <label>
            <?php esc_html_e('Frequency:', 'dead-link-checker'); ?>
            <select name="blc_settings[email_frequency]">
                <option value="daily" <?php selected($frequency, 'daily'); ?>>
                    <?php esc_html_e('Daily', 'dead-link-checker'); ?>
                </option>
                <option value="weekly" <?php selected($frequency, 'weekly'); ?>>
                    <?php esc_html_e('Weekly', 'dead-link-checker'); ?>
                </option>
            </select>
        </label>
        <br><br>
        <label>
            <?php esc_html_e('Recipients:', 'dead-link-checker'); ?>
        </label><br>
        <textarea name="blc_settings[email_recipients]" rows="3" cols="40"><?php echo esc_textarea($recipients); ?></textarea>
        <p class="description">
            <?php esc_html_e('One email per line.', 'dead-link-checker'); ?>
        </p>
        <?php
    }

    public function field_concurrent_requests()
    {
        $value = $this->get_option('concurrent_requests', 3);
        ?>
        <input type="number" name="blc_settings[concurrent_requests]" value="<?php echo esc_attr($value); ?>" min="1" max="10">
        <p class="description">
            <?php esc_html_e('Number of links to check simultaneously. Higher values are faster but may overload your server.', 'dead-link-checker'); ?>
        </p>
        <?php
    }

    public function field_user_agent()
    {
        $value = $this->get_option('user_agent', 'Mozilla/5.0 (compatible; BrokenLinkChecker/' . BLC_VERSION . ')');
        $verify = $this->get_option('verify_ssl', true);
        ?>
        <input type="text" name="blc_settings[user_agent]" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">
            <?php esc_html_e('User agent string sent with HTTP requests.', 'dead-link-checker'); ?>
        </p>
        <br>
        <label><input type="checkbox" name="blc_settings[verify_ssl]" value="1" <?php checked($verify); ?>>
            <?php esc_html_e('Verify SSL certificates', 'dead-link-checker'); ?>
        </label>
        <?php
    }

    public function render_page()
    {
        ?>
        <div class="wrap blc-wrap blc-settings-page">
            <h1>
                <?php esc_html_e('Dead Link Checker Pro Settings', 'dead-link-checker'); ?>
            </h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('blc_settings_group');
                ?>
                <div class="blc-settings-tabs">
                    <nav class="blc-tabs-nav">
                        <a href="#general" class="active">
                            <?php esc_html_e('General', 'dead-link-checker'); ?>
                        </a>
                        <a href="#scope">
                            <?php esc_html_e('Scan Scope', 'dead-link-checker'); ?>
                        </a>
                        <a href="#exclusions">
                            <?php esc_html_e('Exclusions', 'dead-link-checker'); ?>
                        </a>
                        <a href="#notifications">
                            <?php esc_html_e('Notifications', 'dead-link-checker'); ?>
                        </a>
                        <a href="#advanced">
                            <?php esc_html_e('Advanced', 'dead-link-checker'); ?>
                        </a>
                        <a href="#help">
                            <?php esc_html_e('Help', 'dead-link-checker'); ?>
                        </a>
                    </nav>
                    <div class="blc-tabs-content">
                        <div id="general" class="blc-tab-panel active">
                            <table class="form-table">
                                <?php do_settings_fields('blc-settings', 'blc_general'); ?>
                            </table>
                        </div>
                        <div id="scope" class="blc-tab-panel">
                            <table class="form-table">
                                <?php do_settings_fields('blc-settings', 'blc_scope'); ?>
                            </table>
                        </div>
                        <div id="exclusions" class="blc-tab-panel">
                            <table class="form-table">
                                <?php do_settings_fields('blc-settings', 'blc_exclusions'); ?>
                            </table>
                        </div>
                        <div id="notifications" class="blc-tab-panel">
                            <table class="form-table">
                                <?php do_settings_fields('blc-settings', 'blc_notifications'); ?>
                            </table>
                        </div>
                        <div id="advanced" class="blc-tab-panel">
                            <table class="form-table">
                                <?php do_settings_fields('blc-settings', 'blc_advanced'); ?>
                            </table>
                        </div>
                        <div id="help" class="blc-tab-panel">
                            <div class="blc-help-section">
                                <h2><?php esc_html_e('Status Codes Explained', 'dead-link-checker'); ?></h2>
                                <p class="blc-help-intro">
                                    <?php esc_html_e('When checking links, the plugin reports different status codes that indicate whether a link is working, broken, or has issues.', 'dead-link-checker'); ?>
                                </p>

                                <div class="blc-status-codes-grid">
                                    <div class="blc-status-category blc-status-category-success">
                                        <h3><span class="dashicons dashicons-yes-alt"></span>
                                            <?php esc_html_e('Working (2xx)', 'dead-link-checker'); ?></h3>
                                        <dl>
                                            <dt>200</dt>
                                            <dd><?php esc_html_e('OK - The link is working correctly and the page exists.', 'dead-link-checker'); ?>
                                            </dd>
                                        </dl>
                                    </div>

                                    <div class="blc-status-category blc-status-category-redirect">
                                        <h3><span class="dashicons dashicons-external"></span>
                                            <?php esc_html_e('Redirects (3xx)', 'dead-link-checker'); ?></h3>
                                        <dl>
                                            <dt>301</dt>
                                            <dd><?php esc_html_e('Permanent Redirect - The page has permanently moved to a new URL. Consider updating the link.', 'dead-link-checker'); ?>
                                            </dd>
                                            <dt>302</dt>
                                            <dd><?php esc_html_e('Temporary Redirect - The page is temporarily at a different URL.', 'dead-link-checker'); ?>
                                            </dd>
                                            <dt>307</dt>
                                            <dd><?php esc_html_e('Temporary Redirect (Strict) - Similar to 302, preserves the request method.', 'dead-link-checker'); ?>
                                            </dd>
                                        </dl>
                                    </div>

                                    <div class="blc-status-category blc-status-category-client">
                                        <h3><span class="dashicons dashicons-warning"></span>
                                            <?php esc_html_e('Client Errors (4xx)', 'dead-link-checker'); ?></h3>
                                        <dl>
                                            <dt>400</dt>
                                            <dd><?php esc_html_e('Bad Request - The server couldn\'t understand the request. Check the URL format.', 'dead-link-checker'); ?>
                                            </dd>
                                            <dt>401</dt>
                                            <dd><?php esc_html_e('Unauthorized - The page requires authentication/login.', 'dead-link-checker'); ?>
                                            </dd>
                                            <dt>403</dt>
                                            <dd><?php esc_html_e('Forbidden - Access to the page is denied, even with authentication.', 'dead-link-checker'); ?>
                                            </dd>
                                            <dt>404</dt>
                                            <dd><?php esc_html_e('Not Found - The page doesn\'t exist. The link is broken and should be fixed.', 'dead-link-checker'); ?>
                                            </dd>
                                            <dt>410</dt>
                                            <dd><?php esc_html_e('Gone - The page has been permanently removed and won\'t return.', 'dead-link-checker'); ?>
                                            </dd>
                                        </dl>
                                    </div>

                                    <div class="blc-status-category blc-status-category-server">
                                        <h3><span class="dashicons dashicons-dismiss"></span>
                                            <?php esc_html_e('Server Errors (5xx)', 'dead-link-checker'); ?></h3>
                                        <dl>
                                            <dt>500</dt>
                                            <dd><?php esc_html_e('Internal Server Error - The website has a problem on their end.', 'dead-link-checker'); ?>
                                            </dd>
                                            <dt>502</dt>
                                            <dd><?php esc_html_e('Bad Gateway - The server received an invalid response from upstream.', 'dead-link-checker'); ?>
                                            </dd>
                                            <dt>503</dt>
                                            <dd><?php esc_html_e('Service Unavailable - The server is temporarily down (maintenance or overload).', 'dead-link-checker'); ?>
                                            </dd>
                                            <dt>504</dt>
                                            <dd><?php esc_html_e('Gateway Timeout - The server didn\'t respond in time.', 'dead-link-checker'); ?>
                                            </dd>
                                        </dl>
                                    </div>

                                    <div class="blc-status-category blc-status-category-connection">
                                        <h3><span class="dashicons dashicons-no"></span>
                                            <?php esc_html_e('Connection Errors', 'dead-link-checker'); ?></h3>
                                        <dl>
                                            <dt><?php esc_html_e('Error', 'dead-link-checker'); ?></dt>
                                            <dd><?php esc_html_e('Connection failed - The domain doesn\'t exist, DNS lookup failed, or there\'s a network issue.', 'dead-link-checker'); ?>
                                            </dd>
                                            <dt><?php esc_html_e('Timeout', 'dead-link-checker'); ?></dt>
                                            <dd><?php esc_html_e('The server took too long to respond. Try increasing the timeout in settings.', 'dead-link-checker'); ?>
                                            </dd>
                                            <dt><?php esc_html_e('SSL Error', 'dead-link-checker'); ?></dt>
                                            <dd><?php esc_html_e('The website has an invalid or expired SSL certificate.', 'dead-link-checker'); ?>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
