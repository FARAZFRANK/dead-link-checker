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

class AWLDLC_Settings
{

    public function __construct()
    {
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings()
    {
        register_setting('awldlc_settings_group', 'awldlc_settings', array($this, 'sanitize_settings'));

        // General Section
        add_settings_section('awldlc_general', __('General Settings', 'dead-link-checker'), null, 'awldlc-settings');
        add_settings_field('scan_type', __('Scan Type', 'dead-link-checker'), array($this, 'field_scan_type'), 'awldlc-settings', 'awldlc_general');
        add_settings_field('scan_frequency', __('Scan Frequency', 'dead-link-checker'), array($this, 'field_scan_frequency'), 'awldlc-settings', 'awldlc_general');
        add_settings_field('timeout', __('Request Timeout', 'dead-link-checker'), array($this, 'field_timeout'), 'awldlc-settings', 'awldlc_general');

        // Scan Scope Section
        add_settings_section('awldlc_scope', __('Scan Scope', 'dead-link-checker'), null, 'awldlc-settings');
        add_settings_field('content_types', __('Content to Scan', 'dead-link-checker'), array($this, 'field_content_types'), 'awldlc-settings', 'awldlc_scope');
        add_settings_field('link_types', __('Link Types', 'dead-link-checker'), array($this, 'field_link_types'), 'awldlc-settings', 'awldlc_scope');

        // Exclusions Section
        add_settings_section('awldlc_exclusions', __('Exclusions', 'dead-link-checker'), null, 'awldlc-settings');
        add_settings_field('excluded_domains', __('Excluded Domains', 'dead-link-checker'), array($this, 'field_excluded_domains'), 'awldlc-settings', 'awldlc_exclusions');

        // Notifications Section
        add_settings_section('awldlc_notifications', __('Notifications', 'dead-link-checker'), null, 'awldlc-settings');
        add_settings_field('email_notifications', __('Email Notifications', 'dead-link-checker'), array($this, 'field_email_notifications'), 'awldlc-settings', 'awldlc_notifications');

        // Advanced Section
        add_settings_section('awldlc_advanced', __('Advanced', 'dead-link-checker'), null, 'awldlc-settings');
        add_settings_field('concurrent_requests', __('Concurrent Requests', 'dead-link-checker'), array($this, 'field_concurrent_requests'), 'awldlc-settings', 'awldlc_advanced');
        add_settings_field('user_agent', __('User Agent', 'dead-link-checker'), array($this, 'field_user_agent'), 'awldlc-settings', 'awldlc_advanced');
    }

    public function sanitize_settings($input)
    {
        $sanitized = array();
        $sanitized['scan_type'] = in_array(($input['scan_type'] ?? 'automatic'), array('manual', 'automatic'), true) ? $input['scan_type'] : 'automatic';
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
        $excluded_raw = $input['excluded_domains'] ?? '';
        $sanitized['excluded_domains'] = array_filter(array_map('sanitize_text_field', is_array($excluded_raw) ? $excluded_raw : explode("\n", $excluded_raw)));
        $sanitized['email_notifications'] = !empty($input['email_notifications']);
        $sanitized['email_frequency'] = sanitize_key($input['email_frequency'] ?? 'weekly');
        $recipients_raw = $input['email_recipients'] ?? get_option('admin_email');
        $sanitized['email_recipients'] = array_filter(array_map('sanitize_email', is_array($recipients_raw) ? $recipients_raw : explode("\n", $recipients_raw)));
        $sanitized['concurrent_requests'] = min(10, max(1, absint($input['concurrent_requests'] ?? 3)));
        $sanitized['user_agent'] = sanitize_text_field($input['user_agent'] ?? '');
        $sanitized['verify_ssl'] = !empty($input['verify_ssl']);
        return $sanitized;
    }

    private function get_option($key, $default = null)
    {
        $options = get_option('awldlc_settings', array());
        return isset($options[$key]) ? $options[$key] : $default;
    }

    public function field_scan_type()
    {
        $value = $this->get_option('scan_type', 'automatic');
        ?>
        <fieldset>
            <label>
                <input type="radio" name="awldlc_settings[scan_type]" value="automatic" <?php checked($value, 'automatic'); ?> />
                <?php esc_html_e('Automatic', 'dead-link-checker'); ?>
                <span class="description"><?php esc_html_e('Scans run automatically based on the frequency below.', 'dead-link-checker'); ?></span>
            </label><br/>
            <label>
                <input type="radio" name="awldlc_settings[scan_type]" value="manual" <?php checked($value, 'manual'); ?> />
                <?php esc_html_e('Manual', 'dead-link-checker'); ?>
                <span class="description"><?php esc_html_e('Scans only run when you click the Scan button.', 'dead-link-checker'); ?></span>
            </label>
        </fieldset>
        <?php
    }

    public function field_scan_frequency()
    {
        $value = $this->get_option('scan_frequency', 'daily');
        ?>
        <select name="awldlc_settings[scan_frequency]">
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
        <input type="number" name="awldlc_settings[timeout]" value="<?php echo esc_attr($value); ?>" min="5" max="120" step="1">
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
            printf('<label><input type="checkbox" name="awldlc_settings[%s]" value="1" %s> %s</label><br>', esc_attr($key), checked($checked, true, false), esc_html($label));
        }
    }

    public function field_link_types()
    {
        $types = array('check_internal' => __('Internal Links', 'dead-link-checker'), 'check_external' => __('External Links', 'dead-link-checker'), 'check_images' => __('Images', 'dead-link-checker'));
        foreach ($types as $key => $label) {
            $checked = $this->get_option($key, true);
            printf('<label><input type="checkbox" name="awldlc_settings[%s]" value="1" %s> %s</label><br>', esc_attr($key), checked($checked, true, false), esc_html($label));
        }
    }

    public function field_excluded_domains()
    {
        $value = implode("\n", (array) $this->get_option('excluded_domains', array()));
        ?>
        <textarea name="awldlc_settings[excluded_domains]" rows="5" cols="50"
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
        <label><input type="checkbox" name="awldlc_settings[email_notifications]" value="1" <?php checked($enabled); ?>>
            <?php esc_html_e('Enable email notifications', 'dead-link-checker'); ?>
        </label>
        <br><br>
        <label>
            <?php esc_html_e('Frequency:', 'dead-link-checker'); ?>
            <select name="awldlc_settings[email_frequency]">
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
        <textarea name="awldlc_settings[email_recipients]" rows="3" cols="40"><?php echo esc_textarea($recipients); ?></textarea>
        <p class="description">
            <?php esc_html_e('One email per line.', 'dead-link-checker'); ?>
        </p>
        <?php
    }

    public function field_concurrent_requests()
    {
        $value = $this->get_option('concurrent_requests', 3);
        ?>
        <input type="number" name="awldlc_settings[concurrent_requests]" value="<?php echo esc_attr($value); ?>" min="1" max="10">
        <p class="description">
            <?php esc_html_e('Number of links to check simultaneously. Higher values are faster but may overload your server.', 'dead-link-checker'); ?>
        </p>
        <?php
    }

    public function field_user_agent()
    {
        $value = $this->get_option('user_agent', 'Mozilla/5.0 (compatible; BrokenLinkChecker/' . AWLDLC_VERSION . ')');
        $verify = $this->get_option('verify_ssl', true);
        ?>
        <input type="text" name="awldlc_settings[user_agent]" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">
            <?php esc_html_e('User agent string sent with HTTP requests.', 'dead-link-checker'); ?>
        </p>
        <br>
        <label><input type="checkbox" name="awldlc_settings[verify_ssl]" value="1" <?php checked($verify); ?>>
            <?php esc_html_e('Verify SSL certificates', 'dead-link-checker'); ?>
        </label>
        <?php
    }

    public function render_page()
    {
        ?>
        <div class="wrap awldlc-wrap blc-settings-page">
            <h1>
                <?php esc_html_e('Dead Link Checker Settings', 'dead-link-checker'); ?>
            </h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('awldlc_settings_group');
                ?>
                <div class="awldlc-settings-tabs">
                    <nav class="awldlc-tabs-nav">
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

                    </nav>
                    <div class="awldlc-tabs-content">
                        <div id="general" class="awldlc-tab-panel active">
                            <table class="form-table">
                                <?php do_settings_fields('awldlc-settings', 'awldlc_general'); ?>
                            </table>
                        </div>
                        <div id="scope" class="awldlc-tab-panel">
                            <table class="form-table">
                                <?php do_settings_fields('awldlc-settings', 'awldlc_scope'); ?>
                            </table>
                        </div>
                        <div id="exclusions" class="awldlc-tab-panel">
                            <table class="form-table">
                                <?php do_settings_fields('awldlc-settings', 'awldlc_exclusions'); ?>
                            </table>
                        </div>
                        <div id="notifications" class="awldlc-tab-panel">
                            <table class="form-table">
                                <?php do_settings_fields('awldlc-settings', 'awldlc_notifications'); ?>
                            </table>
                        </div>
                        <div id="advanced" class="awldlc-tab-panel">
                            <table class="form-table">
                                <?php do_settings_fields('awldlc-settings', 'awldlc_advanced'); ?>
                            </table>
                        </div>

                    </div>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
