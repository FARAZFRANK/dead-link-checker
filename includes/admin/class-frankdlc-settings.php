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

class FRANKDLC_Settings
{

    public function __construct()
    {
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings()
    {
        register_setting('FRANKDLC_settings_group', 'FRANKDLC_settings', array($this, 'sanitize_settings'));

        // General Section
        add_settings_section('FRANKDLC_general', __('General Settings', 'frank-dead-link-checker'), null, 'frankdlc-settings');
        add_settings_field('scan_type', __('Scan Type', 'frank-dead-link-checker'), array($this, 'field_scan_type'), 'frankdlc-settings', 'FRANKDLC_general');
        add_settings_field('scan_frequency', __('Scan Frequency', 'frank-dead-link-checker'), array($this, 'field_scan_frequency'), 'frankdlc-settings', 'FRANKDLC_general');
        add_settings_field('timeout', __('Request Timeout', 'frank-dead-link-checker'), array($this, 'field_timeout'), 'frankdlc-settings', 'FRANKDLC_general');

        // Scan Scope Section
        add_settings_section('FRANKDLC_scope', __('Scan Scope', 'frank-dead-link-checker'), null, 'frankdlc-settings');
        add_settings_field('content_types', __('Content to Scan', 'frank-dead-link-checker'), array($this, 'field_content_types'), 'frankdlc-settings', 'FRANKDLC_scope');
        add_settings_field('link_types', __('Link Types', 'frank-dead-link-checker'), array($this, 'field_link_types'), 'frankdlc-settings', 'FRANKDLC_scope');

        // Exclusions Section
        add_settings_section('FRANKDLC_exclusions', __('Exclusions', 'frank-dead-link-checker'), null, 'frankdlc-settings');
        add_settings_field('excluded_domains', __('Excluded Domains', 'frank-dead-link-checker'), array($this, 'field_excluded_domains'), 'frankdlc-settings', 'FRANKDLC_exclusions');

        // Notifications Section
        add_settings_section('FRANKDLC_notifications', __('Notifications', 'frank-dead-link-checker'), null, 'frankdlc-settings');
        add_settings_field('email_notifications', __('Email Notifications', 'frank-dead-link-checker'), array($this, 'field_email_notifications'), 'frankdlc-settings', 'FRANKDLC_notifications');

        // Advanced Section
        add_settings_section('FRANKDLC_advanced', __('Advanced', 'frank-dead-link-checker'), null, 'frankdlc-settings');
        add_settings_field('concurrent_requests', __('Concurrent Requests', 'frank-dead-link-checker'), array($this, 'field_concurrent_requests'), 'frankdlc-settings', 'FRANKDLC_advanced');
        add_settings_field('user_agent', __('User Agent', 'frank-dead-link-checker'), array($this, 'field_user_agent'), 'frankdlc-settings', 'FRANKDLC_advanced');
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
        $options = get_option('FRANKDLC_settings', array());
        return isset($options[$key]) ? $options[$key] : $default;
    }

    private function get_pro_badge()
    {
        return ' <span class="frankdlc-pro-badge" style="background:#2271b1; color:#fff; padding:2px 6px; border-radius:4px; font-size:10px; vertical-align:middle; margin-left:5px;">' . __('PRO', 'frank-dead-link-checker') . '</span> <a href="https://awplife.com/wordpress-plugins/dead-link-checker-pro/" target="_blank" style="text-decoration:none; font-size:12px; margin-left:5px;">' . __('Upgrade', 'frank-dead-link-checker') . '</a>';
    }

    public function field_scan_type()
    {
        $value = $this->get_option('scan_type', 'automatic');
        ?>
        <fieldset>
            <label>
                <input type="radio" name="FRANKDLC_settings[scan_type]" value="automatic" <?php checked($value, 'automatic'); ?> />
                <?php esc_html_e('Automatic', 'frank-dead-link-checker'); ?>
                <span class="description"><?php esc_html_e('Scans run automatically based on the frequency below.', 'frank-dead-link-checker'); ?></span>
            </label><br/>
            <label>
                <input type="radio" name="FRANKDLC_settings[scan_type]" value="manual" <?php checked($value, 'manual'); ?> />
                <?php esc_html_e('Manual', 'frank-dead-link-checker'); ?>
                <span class="description"><?php esc_html_e('Scans only run when you click the Scan button.', 'frank-dead-link-checker'); ?></span>
            </label>
        </fieldset>
        <?php
    }

    public function field_scan_frequency()
    {
        $value = $this->get_option('scan_frequency', 'weekly');
        ?>
        <select name="FRANKDLC_settings[scan_frequency]">
            <option value="hourly" disabled>
                <?php esc_html_e('Hourly', 'frank-dead-link-checker'); echo $this->get_pro_badge(); ?>
            </option>
            <option value="twicedaily" disabled>
                <?php esc_html_e('Twice Daily', 'frank-dead-link-checker'); echo $this->get_pro_badge(); ?>
            </option>
            <option value="daily" disabled>
                <?php esc_html_e('Daily', 'frank-dead-link-checker'); echo $this->get_pro_badge(); ?>
            </option>
            <option value="weekly" <?php selected($value, 'weekly'); ?>>
                <?php esc_html_e('Weekly', 'frank-dead-link-checker'); ?>
            </option>
            <option value="manual" <?php selected($value, 'manual'); ?>>
                <?php esc_html_e('Manual Only', 'frank-dead-link-checker'); ?>
            </option>
        </select>
        <?php
    }

    public function field_timeout()
    {
        $value = $this->get_option('timeout', 30);
        ?>
        <input type="number" name="FRANKDLC_settings[timeout]" value="<?php echo esc_attr($value); ?>" min="5" max="120" step="1">
        <?php esc_html_e('seconds', 'frank-dead-link-checker'); ?>
        <p class="description">
            <?php esc_html_e('Maximum time to wait for a response from a link.', 'frank-dead-link-checker'); ?>
        </p>
        <?php
    }

    public function field_content_types()
    {
        $types = array(
            'scan_posts' => __('Posts', 'frank-dead-link-checker'),
            'scan_pages' => __('Pages', 'frank-dead-link-checker'),
            'scan_comments' => __('Comments', 'frank-dead-link-checker'),
            'scan_widgets' => __('Widgets', 'frank-dead-link-checker'),
            'scan_menus' => __('Menus', 'frank-dead-link-checker'),
            'scan_custom_fields' => __('Custom Fields (ACF)', 'frank-dead-link-checker'),
        );
        foreach ($types as $key => $label) {
            $is_pro = in_array($key, array('scan_comments', 'scan_widgets', 'scan_menus', 'scan_custom_fields'), true);
            $disabled = $is_pro ? 'disabled' : '';
            $badge = $is_pro ? $this->get_pro_badge() : '';
            $checked = $is_pro ? false : $this->get_option($key, in_array($key, array('scan_posts', 'scan_pages'), true));
            printf('<label><input type="checkbox" name="FRANKDLC_settings[%s]" value="1" %s %s> %s%s</label><br>', esc_attr($key), checked($checked, true, false), $disabled, esc_html($label), $badge);
        }
    }

    public function field_link_types()
    {
        $types = array('check_internal' => __('Internal Links', 'frank-dead-link-checker'), 'check_external' => __('External Links', 'frank-dead-link-checker'), 'check_images' => __('Images', 'frank-dead-link-checker'));
        foreach ($types as $key => $label) {
            $is_pro = ($key === 'check_images');
            $disabled = $is_pro ? 'disabled' : '';
            $badge = $is_pro ? $this->get_pro_badge() : '';
            $checked = $is_pro ? false : $this->get_option($key, true);
            printf('<label><input type="checkbox" name="FRANKDLC_settings[%s]" value="1" %s %s> %s%s</label><br>', esc_attr($key), checked($checked, true, false), $disabled, esc_html($label), $badge);
        }
    }

    public function field_excluded_domains()
    {
        $value = implode("\n", (array) $this->get_option('excluded_domains', array()));
        ?>
        <textarea name="FRANKDLC_settings[excluded_domains]" rows="5" cols="50"
            class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php esc_html_e('Enter one domain per line (e.g., example.com). Links to these domains will be ignored.', 'frank-dead-link-checker'); ?>
        </p>
        <?php
    }

    public function field_email_notifications()
    {
        $enabled = $this->get_option('email_notifications', true);
        $frequency = $this->get_option('email_frequency', 'weekly');
        $recipients = implode("\n", (array) $this->get_option('email_recipients', array(get_option('admin_email'))));
        ?>
        <label><input type="checkbox" name="FRANKDLC_settings[email_notifications]" value="1" disabled>
            <?php esc_html_e('Enable email notifications', 'frank-dead-link-checker'); echo $this->get_pro_badge(); ?>
        </label>
        <br><br>
        <label>
            <?php esc_html_e('Frequency:', 'frank-dead-link-checker'); ?>
            <select name="FRANKDLC_settings[email_frequency]">
                <option value="daily" <?php selected($frequency, 'daily'); ?>>
                    <?php esc_html_e('Daily', 'frank-dead-link-checker'); ?>
                </option>
                <option value="weekly" <?php selected($frequency, 'weekly'); ?>>
                    <?php esc_html_e('Weekly', 'frank-dead-link-checker'); ?>
                </option>
            </select>
        </label>
        <br><br>
        <label>
            <?php esc_html_e('Recipients:', 'frank-dead-link-checker'); ?>
        </label><br>
        <textarea name="FRANKDLC_settings[email_recipients]" rows="3" cols="40"><?php echo esc_textarea($recipients); ?></textarea>
        <p class="description">
            <?php esc_html_e('One email per line.', 'frank-dead-link-checker'); ?>
        </p>
        <?php
    }

    public function field_concurrent_requests()
    {
        $value = $this->get_option('concurrent_requests', 3);
        ?>
        <input type="number" name="FRANKDLC_settings[concurrent_requests]" value="<?php echo esc_attr($value); ?>" min="1" max="3" disabled>
        <?php echo $this->get_pro_badge(); ?>
        <p class="description">
            <?php esc_html_e('Number of links to check simultaneously. Higher values are faster but may overload your server.', 'frank-dead-link-checker'); ?>
        </p>
        <?php
    }

    public function field_user_agent()
    {
        $value = $this->get_option('user_agent', 'Mozilla/5.0 (compatible; BrokenLinkChecker/' . FRANKDLC_VERSION . ')');
        $verify = $this->get_option('verify_ssl', true);
        ?>
        <input type="text" name="FRANKDLC_settings[user_agent]" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">
            <?php esc_html_e('User agent string sent with HTTP requests.', 'frank-dead-link-checker'); ?>
        </p>
        <br>
        <label><input type="checkbox" name="FRANKDLC_settings[verify_ssl]" value="1" <?php checked($verify); ?>>
            <?php esc_html_e('Verify SSL certificates', 'frank-dead-link-checker'); ?>
        </label>
        <?php
    }

    public function render_page()
    {
        ?>
        <div class="wrap frankdlc-wrap frankdlc-settings-page">
            <h1>
                <?php esc_html_e('Frank Dead Link Checker Settings', 'frank-dead-link-checker'); ?>
            </h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('FRANKDLC_settings_group');
                ?>
                <div class="frankdlc-settings-tabs">
                    <nav class="frankdlc-tabs-nav">
                        <a href="#general" class="active">
                            <?php esc_html_e('General', 'frank-dead-link-checker'); ?>
                        </a>
                        <a href="#scope">
                            <?php esc_html_e('Scan Scope', 'frank-dead-link-checker'); ?>
                        </a>
                        <a href="#exclusions">
                            <?php esc_html_e('Exclusions', 'frank-dead-link-checker'); ?>
                        </a>
                        <a href="#notifications">
                            <?php esc_html_e('Notifications', 'frank-dead-link-checker'); ?>
                        </a>
                        <a href="#advanced">
                            <?php esc_html_e('Advanced', 'frank-dead-link-checker'); ?>
                        </a>
                        <a href="#tools">
                            <?php esc_html_e('Tools', 'frank-dead-link-checker'); ?>
                        </a>
                        <a href="#free-pro" style="color: #2271b1; font-weight: 600;">
                            <?php esc_html_e('Free vs Pro', 'frank-dead-link-checker'); ?>
                        </a>

                    </nav>
                    <div class="frankdlc-tabs-content">
                        <div id="general" class="frankdlc-tab-panel active">
                            <table class="form-table">
                                <?php do_settings_fields('frankdlc-settings', 'FRANKDLC_general'); ?>
                            </table>
                            <?php submit_button(); ?>
                        </div>
                        <div id="scope" class="frankdlc-tab-panel">
                            <table class="form-table">
                                <?php do_settings_fields('frankdlc-settings', 'FRANKDLC_scope'); ?>
                            </table>
                            <?php submit_button(); ?>
                        </div>
                        <div id="exclusions" class="frankdlc-tab-panel">
                            <table class="form-table">
                                <?php do_settings_fields('frankdlc-settings', 'FRANKDLC_exclusions'); ?>
                            </table>
                            <?php submit_button(); ?>
                        </div>
                        <div id="notifications" class="frankdlc-tab-panel">
                            <table class="form-table">
                                <?php do_settings_fields('frankdlc-settings', 'FRANKDLC_notifications'); ?>
                            </table>
                            <?php submit_button(); ?>
                        </div>
                        <div id="advanced" class="frankdlc-tab-panel">
                            <table class="form-table">
                                <?php do_settings_fields('frankdlc-settings', 'FRANKDLC_advanced'); ?>
                            </table>
                            <?php submit_button(); ?>
                        </div>
                        <div id="tools" class="frankdlc-tab-panel">
                            <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                                <span class="dashicons dashicons-update" style="color: #E91E63;"></span>
                                <?php esc_html_e('Reset & Maintenance Options', 'frank-dead-link-checker'); ?>
                            </h2>
                            <table class="widefat">
                                <tr>
                                    <td style="width:180px;"><strong><?php esc_html_e('Force Stop Scan', 'frank-dead-link-checker'); ?></strong></td>
                                    <td><?php esc_html_e('Forcefully stops all running/pending scans, clears the scan queue, and resets the scan state. Use when a scan appears stuck.', 'frank-dead-link-checker'); ?></td>
                                    <td style="width:160px; text-align:right;">
                                        <button type="button" id="frankdlc-force-stop-btn" class="button" style="color:#FF9800; border-color:#FF9800;">
                                            <span class="dashicons dashicons-dismiss" style="margin-top:4px;"></span> <?php esc_html_e('Force Stop', 'frank-dead-link-checker'); ?>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong><?php esc_html_e('Clear Scan History', 'frank-dead-link-checker'); ?></strong></td>
                                    <td><?php esc_html_e('Deletes all scan history records but keeps your link data intact.', 'frank-dead-link-checker'); ?></td>
                                    <td style="text-align:right;">
                                        <button type="button" id="frankdlc-clear-history-btn" class="button">
                                            <span class="dashicons dashicons-trash" style="margin-top:4px;"></span> <?php esc_html_e('Clear History', 'frank-dead-link-checker'); ?>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong><?php esc_html_e('Reset Settings', 'frank-dead-link-checker'); ?></strong></td>
                                    <td><?php esc_html_e('Resets all plugin settings to their default values without affecting link data or scan history.', 'frank-dead-link-checker'); ?></td>
                                    <td style="text-align:right;">
                                        <button type="button" id="frankdlc-reset-settings-btn" class="button">
                                            <span class="dashicons dashicons-undo" style="margin-top:4px;"></span> <?php esc_html_e('Reset Settings', 'frank-dead-link-checker'); ?>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong><?php esc_html_e('Cleanup Exports', 'frank-dead-link-checker'); ?></strong></td>
                                    <td><?php esc_html_e('Deletes all exported CSV/JSON files from the uploads directory to free up disk space.', 'frank-dead-link-checker'); ?></td>
                                    <td style="text-align:right;">
                                        <button type="button" id="frankdlc-cleanup-exports-btn" class="button">
                                            <span class="dashicons dashicons-trash" style="margin-top:4px;"></span> <?php esc_html_e('Cleanup Exports', 'frank-dead-link-checker'); ?>
                                        </button>
                                    </td>
                                </tr>
                                <tr style="background:#fff5f5;">
                                    <td><strong style="color:#dc3545;"><?php esc_html_e('Full Plugin Reset', 'frank-dead-link-checker'); ?></strong></td>
                                    <td><?php esc_html_e('Resets EVERYTHING — link data, scan history, settings, and export files — back to factory defaults. Use with caution!', 'frank-dead-link-checker'); ?></td>
                                    <td style="text-align:right;">
                                        <button type="button" id="frankdlc-full-reset-btn" class="button" style="color:#dc3545; border-color:#dc3545;">
                                            <span class="dashicons dashicons-warning" style="margin-top:4px;"></span> <?php esc_html_e('Full Reset', 'frank-dead-link-checker'); ?>
                                        </button>
                                    </td>
                                </tr>
                            </table>
                        </div>


                    <div id="free-pro" class="frankdlc-tab-panel">
                        <div class="frankdlc-pricing-container">
                            <h2><?php esc_html_e('Upgrade to Pro', 'frank-dead-link-checker'); ?></h2>
                            <p class="frankdlc-pricing-subtitle"><?php esc_html_e('Unlock powerful features for comprehensive link management', 'frank-dead-link-checker'); ?></p>
                            
                            <table class="frankdlc-pricing-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Feature', 'frank-dead-link-checker'); ?></th>
                                        <th><?php esc_html_e('Free', 'frank-dead-link-checker'); ?></th>
                                        <th><?php esc_html_e('Pro', 'frank-dead-link-checker'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="frankdlc-section-header"><td colspan="3"><?php esc_html_e('Scanning', 'frank-dead-link-checker'); ?></td></tr>
                                    <tr>
                                        <td><?php esc_html_e('Scan Posts & Pages', 'frank-dead-link-checker'); ?></td>
                                        <td><span class="dashicons dashicons-yes frankdlc-yes"></span></td>
                                        <td><span class="dashicons dashicons-yes frankdlc-yes"></span></td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Custom Post Types', 'frank-dead-link-checker'); ?></td>
                                        <td><span class="dashicons dashicons-minus frankdlc-no"></span></td>
                                        <td><span class="dashicons dashicons-yes frankdlc-yes"></span></td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Comments, Widgets, Menus', 'frank-dead-link-checker'); ?></td>
                                        <td><span class="dashicons dashicons-minus frankdlc-no"></span></td>
                                        <td><span class="dashicons dashicons-yes frankdlc-yes"></span></td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Custom Fields (ACF)', 'frank-dead-link-checker'); ?></td>
                                        <td><span class="dashicons dashicons-minus frankdlc-no"></span></td>
                                        <td><span class="dashicons dashicons-yes frankdlc-yes"></span></td>
                                    </tr>

                                    <tr class="frankdlc-section-header"><td colspan="3"><?php esc_html_e('Page Builders', 'frank-dead-link-checker'); ?></td></tr>
                                    <tr>
                                        <td><?php esc_html_e('Gutenberg Blocks', 'frank-dead-link-checker'); ?></td>
                                        <td><span class="dashicons dashicons-yes frankdlc-yes"></span></td>
                                        <td><span class="dashicons dashicons-yes frankdlc-yes"></span></td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Elementor, Divi, WPBakery', 'frank-dead-link-checker'); ?></td>
                                        <td><span class="dashicons dashicons-minus frankdlc-no"></span></td>
                                        <td><span class="dashicons dashicons-yes frankdlc-yes"></span></td>
                                    </tr>

                                    <tr class="frankdlc-section-header"><td colspan="3"><?php esc_html_e('Features', 'frank-dead-link-checker'); ?></td></tr>
                                    <tr>
                                        <td><?php esc_html_e('Scan Frequency', 'frank-dead-link-checker'); ?></td>
                                        <td><?php esc_html_e('Weekly', 'frank-dead-link-checker'); ?></td>
                                        <td><?php esc_html_e('Daily / Hourly', 'frank-dead-link-checker'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Email Notifications', 'frank-dead-link-checker'); ?></td>
                                        <td><span class="dashicons dashicons-minus frankdlc-no"></span></td>
                                        <td><span class="dashicons dashicons-yes frankdlc-yes"></span></td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Create 301/302 Redirects', 'frank-dead-link-checker'); ?></td>
                                        <td><span class="dashicons dashicons-minus frankdlc-no"></span></td>
                                        <td><span class="dashicons dashicons-yes frankdlc-yes"></span></td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Export to CSV/JSON', 'frank-dead-link-checker'); ?></td>
                                        <td><span class="dashicons dashicons-minus frankdlc-no"></span></td>
                                        <td><span class="dashicons dashicons-yes frankdlc-yes"></span></td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Edit Links in Posts', 'frank-dead-link-checker'); ?></td>
                                        <td><span class="dashicons dashicons-minus frankdlc-no"></span></td>
                                        <td><span class="dashicons dashicons-yes frankdlc-yes"></span></td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Scan Images & YouTube', 'frank-dead-link-checker'); ?></td>
                                        <td><span class="dashicons dashicons-minus frankdlc-no"></span></td>
                                        <td><span class="dashicons dashicons-yes frankdlc-yes"></span></td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Multisite Support', 'frank-dead-link-checker'); ?></td>
                                        <td><span class="dashicons dashicons-minus frankdlc-no"></span></td>
                                        <td><span class="dashicons dashicons-yes frankdlc-yes"></span></td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Priority Support', 'frank-dead-link-checker'); ?></td>
                                        <td><span class="dashicons dashicons-minus frankdlc-no"></span></td>
                                        <td><span class="dashicons dashicons-yes frankdlc-yes"></span></td>
                                    </tr>
                                </tbody>
                            </table>

                            <div style="margin-top: 30px;">
                                <a href="https://awplife.com/wordpress-plugins/dead-link-checker-pro/" target="_blank" class="frankdlc-upgrade-btn">
                                    <?php esc_html_e('Get Pro Version', 'frank-dead-link-checker'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </form>
        </div>
        <?php
    }
}
