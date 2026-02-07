<?php
/**
 * Settings Handler - FREE VERSION (WordPress.org Compliant)
 *
 * Handles plugin settings page and options.
 * Limited features for Free version - no locked/disabled features shown.
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

        // Advanced Section
        add_settings_section('blc_advanced', __('Advanced', 'dead-link-checker'), null, 'blc-settings');
        add_settings_field('concurrent_requests', __('Concurrent Requests', 'dead-link-checker'), array($this, 'field_concurrent_requests'), 'blc-settings', 'blc_advanced');
        add_settings_field('user_agent', __('User Agent', 'dead-link-checker'), array($this, 'field_user_agent'), 'blc-settings', 'blc_advanced');
    }

    public function sanitize_settings($input)
    {
        $sanitized = array();

        // FREE version settings
        $sanitized['scan_frequency'] = 'weekly';
        $sanitized['timeout'] = 15;

        $sanitized['scan_posts'] = !empty($input['scan_posts']);
        $sanitized['scan_pages'] = !empty($input['scan_pages']);
        $sanitized['scan_comments'] = false;
        $sanitized['scan_widgets'] = false;
        $sanitized['scan_menus'] = false;
        $sanitized['scan_custom_fields'] = false;

        $sanitized['check_internal'] = !empty($input['check_internal']);
        $sanitized['check_external'] = !empty($input['check_external']);
        $sanitized['check_images'] = false;

        // Limit to 3 excluded domains
        $domains = array_filter(array_map('sanitize_text_field', explode("\n", $input['excluded_domains'] ?? '')));
        $sanitized['excluded_domains'] = array_slice($domains, 0, 3);

        $sanitized['email_notifications'] = false;
        $sanitized['email_frequency'] = 'weekly';
        $sanitized['email_recipients'] = array();
        $sanitized['concurrent_requests'] = 2;

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
        ?>
        <select name="blc_settings[scan_frequency]">
            <option value="weekly" selected>
                <?php esc_html_e('Weekly', 'dead-link-checker'); ?>
            </option>
        </select>
        <p class="description">
            <?php esc_html_e('Automatic scans run weekly. You can manually scan anytime from the dashboard.', 'dead-link-checker'); ?>
        </p>
        <?php
    }

    public function field_timeout()
    {
        ?>
        <input type="number" name="blc_settings[timeout]" value="15" min="15" max="15">
        <?php esc_html_e('seconds', 'dead-link-checker'); ?>
        <p class="description">
            <?php esc_html_e('Maximum time to wait for a response from each link.', 'dead-link-checker'); ?>
        </p>
        <?php
    }

    public function field_content_types()
    {
        $types = array(
            'scan_posts' => __('Posts', 'dead-link-checker'),
            'scan_pages' => __('Pages', 'dead-link-checker'),
        );

        foreach ($types as $key => $label) {
            $checked = $this->get_option($key, true);
            printf('<label><input type="checkbox" name="blc_settings[%s]" value="1" %s> %s</label><br>', esc_attr($key), checked($checked, true, false), esc_html($label));
        }
        ?>
        <p class="description" style="margin-top: 10px;">
            <?php esc_html_e('Scans links in your posts and pages content.', 'dead-link-checker'); ?>
        </p>
        <?php
    }

    public function field_link_types()
    {
        $types = array(
            'check_internal' => __('Internal Links', 'dead-link-checker'),
            'check_external' => __('External Links', 'dead-link-checker'),
        );

        foreach ($types as $key => $label) {
            $checked = $this->get_option($key, true);
            printf('<label><input type="checkbox" name="blc_settings[%s]" value="1" %s> %s</label><br>', esc_attr($key), checked($checked, true, false), esc_html($label));
        }
        ?>
        <p class="description" style="margin-top: 10px;">
            <?php esc_html_e('Choose which types of links to check.', 'dead-link-checker'); ?>
        </p>
        <?php
    }

    public function field_excluded_domains()
    {
        $value = implode("\n", (array) $this->get_option('excluded_domains', array()));
        ?>
        <textarea name="blc_settings[excluded_domains]" rows="3" cols="50"
            class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php esc_html_e('Enter one domain per line (e.g., example.com). Links to these domains will be skipped. Maximum 3 domains.', 'dead-link-checker'); ?>
        </p>
        <?php
    }

    public function field_concurrent_requests()
    {
        ?>
        <input type="number" name="blc_settings[concurrent_requests]" value="2" min="2" max="2">
        <p class="description">
            <?php esc_html_e('Number of links checked simultaneously.', 'dead-link-checker'); ?>
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
            <h1><?php esc_html_e('Broken Link Checker Settings', 'dead-link-checker'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('blc_settings_group'); ?>
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
                        <a href="#advanced">
                            <?php esc_html_e('Advanced', 'dead-link-checker'); ?>
                        </a>
                        <a href="#gopro" style="color: #667eea; font-weight: 600;">
                            ★ <?php esc_html_e('Go Pro', 'dead-link-checker'); ?>
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
                        <div id="advanced" class="blc-tab-panel">
                            <table class="form-table">
                                <?php do_settings_fields('blc-settings', 'blc_advanced'); ?>
                            </table>
                        </div>
                        <div id="gopro" class="blc-tab-panel">
                            <?php $this->render_gopro_tab(); ?>
                        </div>
                        <div id="help" class="blc-tab-panel">
                            <?php $this->render_help_tab(); ?>
                        </div>
                    </div>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function render_gopro_tab()
    {
        ?>
        <div style="max-width: 800px; margin: 0 auto; padding: 20px 0;">
            <div style="text-align: center; margin-bottom: 30px;">
                <h2 style="font-size: 28px; margin-bottom: 10px;">
                    <?php esc_html_e('Upgrade to Pro', 'dead-link-checker'); ?>
                </h2>
                <p style="font-size: 16px; color: #666;">
                    <?php esc_html_e('Unlock powerful features for comprehensive link management', 'dead-link-checker'); ?>
                </p>
            </div>

            <table class="widefat" style="border-collapse: collapse;">
                <thead>
                    <tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <th style="padding: 15px; color: #fff; text-align: left; width: 50%;">
                            <?php esc_html_e('Feature', 'dead-link-checker'); ?>
                        </th>
                        <th style="padding: 15px; color: #fff; text-align: center; width: 25%;">
                            <?php esc_html_e('Free', 'dead-link-checker'); ?>
                        </th>
                        <th style="padding: 15px; color: #fff; text-align: center; width: 25%;">
                            <?php esc_html_e('Pro', 'dead-link-checker'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="3" style="background: #f5f5f5; padding: 10px 15px; font-weight: 600;">
                            <?php esc_html_e('Scanning', 'dead-link-checker'); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 15px;"><?php esc_html_e('Scan Posts & Pages', 'dead-link-checker'); ?>
                        </td>
                        <td style="padding: 12px 15px; text-align: center; color: #28a745;">✓</td>
                        <td style="padding: 12px 15px; text-align: center; color: #28a745;">✓</td>
                    </tr>
                    <tr style="background: #fafafa;">
                        <td style="padding: 12px 15px;"><?php esc_html_e('Custom Post Types', 'dead-link-checker'); ?>
                        </td>
                        <td style="padding: 12px 15px; text-align: center; color: #999;">—</td>
                        <td style="padding: 12px 15px; text-align: center; color: #28a745;">✓</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 15px;">
                            <?php esc_html_e('Comments, Widgets, Menus', 'dead-link-checker'); ?>
                        </td>
                        <td style="padding: 12px 15px; text-align: center; color: #999;">—</td>
                        <td style="padding: 12px 15px; text-align: center; color: #28a745;">✓</td>
                    </tr>
                    <tr style="background: #fafafa;">
                        <td style="padding: 12px 15px;"><?php esc_html_e('Custom Fields (ACF)', 'dead-link-checker'); ?>
                        </td>
                        <td style="padding: 12px 15px; text-align: center; color: #999;">—</td>
                        <td style="padding: 12px 15px; text-align: center; color: #28a745;">✓</td>
                    </tr>

                    <tr>
                        <td colspan="3" style="background: #f5f5f5; padding: 10px 15px; font-weight: 600;">
                            <?php esc_html_e('Page Builders', 'dead-link-checker'); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 15px;"><?php esc_html_e('Gutenberg Blocks', 'dead-link-checker'); ?></td>
                        <td style="padding: 12px 15px; text-align: center; color: #28a745;">✓</td>
                        <td style="padding: 12px 15px; text-align: center; color: #28a745;">✓</td>
                    </tr>
                    <tr style="background: #fafafa;">
                        <td style="padding: 12px 15px;">
                            <?php esc_html_e('Elementor, Divi, WPBakery', 'dead-link-checker'); ?>
                        </td>
                        <td style="padding: 12px 15px; text-align: center; color: #999;">—</td>
                        <td style="padding: 12px 15px; text-align: center; color: #28a745;">✓</td>
                    </tr>

                    <tr>
                        <td colspan="3" style="background: #f5f5f5; padding: 10px 15px; font-weight: 600;">
                            <?php esc_html_e('Features', 'dead-link-checker'); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 15px;"><?php esc_html_e('Scan Frequency', 'dead-link-checker'); ?></td>
                        <td style="padding: 12px 15px; text-align: center;">
                            <?php esc_html_e('Weekly', 'dead-link-checker'); ?>
                        </td>
                        <td style="padding: 12px 15px; text-align: center;">
                            <?php esc_html_e('Daily / Hourly', 'dead-link-checker'); ?>
                        </td>
                    </tr>
                    <tr style="background: #fafafa;">
                        <td style="padding: 12px 15px;"><?php esc_html_e('Email Notifications', 'dead-link-checker'); ?>
                        </td>
                        <td style="padding: 12px 15px; text-align: center; color: #999;">—</td>
                        <td style="padding: 12px 15px; text-align: center; color: #28a745;">✓</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 15px;">
                            <?php esc_html_e('Create 301/302 Redirects', 'dead-link-checker'); ?>
                        </td>
                        <td style="padding: 12px 15px; text-align: center; color: #999;">—</td>
                        <td style="padding: 12px 15px; text-align: center; color: #28a745;">✓</td>
                    </tr>
                    <tr style="background: #fafafa;">
                        <td style="padding: 12px 15px;"><?php esc_html_e('Export to CSV/JSON', 'dead-link-checker'); ?>
                        </td>
                        <td style="padding: 12px 15px; text-align: center; color: #999;">—</td>
                        <td style="padding: 12px 15px; text-align: center; color: #28a745;">✓</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 15px;"><?php esc_html_e('Edit Links in Posts', 'dead-link-checker'); ?>
                        </td>
                        <td style="padding: 12px 15px; text-align: center; color: #999;">—</td>
                        <td style="padding: 12px 15px; text-align: center; color: #28a745;">✓</td>
                    </tr>
                    <tr style="background: #fafafa;">
                        <td style="padding: 12px 15px;"><?php esc_html_e('Scan Images & YouTube', 'dead-link-checker'); ?>
                        </td>
                        <td style="padding: 12px 15px; text-align: center; color: #999;">—</td>
                        <td style="padding: 12px 15px; text-align: center; color: #28a745;">✓</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 15px;"><?php esc_html_e('Multisite Support', 'dead-link-checker'); ?>
                        </td>
                        <td style="padding: 12px 15px; text-align: center; color: #999;">—</td>
                        <td style="padding: 12px 15px; text-align: center; color: #28a745;">✓</td>
                    </tr>
                    <tr style="background: #fafafa;">
                        <td style="padding: 12px 15px;"><?php esc_html_e('Priority Support', 'dead-link-checker'); ?></td>
                        <td style="padding: 12px 15px; text-align: center; color: #999;">—</td>
                        <td style="padding: 12px 15px; text-align: center; color: #28a745;">✓</td>
                    </tr>
                </tbody>
            </table>

            <div style="text-align: center; margin-top: 30px;">
                <a href="https://awplife.com/"
                    target="_blank" class="button button-primary button-hero"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 12px 40px; font-size: 16px;">
                    <?php esc_html_e('Get Pro Version', 'dead-link-checker'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    private function render_help_tab()
    {
        ?>
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
                        <dd><?php esc_html_e('OK - The link is working correctly.', 'dead-link-checker'); ?></dd>
                    </dl>
                </div>

                <div class="blc-status-category blc-status-category-redirect">
                    <h3><span class="dashicons dashicons-external"></span>
                        <?php esc_html_e('Redirects (3xx)', 'dead-link-checker'); ?></h3>
                    <dl>
                        <dt>301</dt>
                        <dd><?php esc_html_e('Permanent Redirect - Consider updating the link.', 'dead-link-checker'); ?>
                        </dd>
                        <dt>302</dt>
                        <dd><?php esc_html_e('Temporary Redirect', 'dead-link-checker'); ?></dd>
                    </dl>
                </div>

                <div class="blc-status-category blc-status-category-client">
                    <h3><span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Client Errors (4xx)', 'dead-link-checker'); ?></h3>
                    <dl>
                        <dt>404</dt>
                        <dd><?php esc_html_e('Not Found - The link is broken.', 'dead-link-checker'); ?></dd>
                        <dt>403</dt>
                        <dd><?php esc_html_e('Forbidden - Access denied.', 'dead-link-checker'); ?></dd>
                    </dl>
                </div>

                <div class="blc-status-category blc-status-category-server">
                    <h3><span class="dashicons dashicons-dismiss"></span>
                        <?php esc_html_e('Server Errors (5xx)', 'dead-link-checker'); ?></h3>
                    <dl>
                        <dt>500</dt>
                        <dd><?php esc_html_e('Internal Server Error', 'dead-link-checker'); ?></dd>
                        <dt>503</dt>
                        <dd><?php esc_html_e('Service Unavailable', 'dead-link-checker'); ?></dd>
                    </dl>
                </div>
            </div>

            <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                <h3><?php esc_html_e('Need Help?', 'dead-link-checker'); ?></h3>
                <p><?php esc_html_e('Visit our support forum or documentation for assistance.', 'dead-link-checker'); ?>
                </p>
                <p>
                    <a href="https://awplife.com/" target="_blank" class="button">
                        <?php esc_html_e('Support Forum', 'dead-link-checker'); ?>
                    </a>
                    <a href="https://awplife.com/" target="_blank" class="button">
                        <?php esc_html_e('Documentation', 'dead-link-checker'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
}
