<?php
/**
 * Notifications Handler
 *
 * Sends email notifications about broken links.
 *
 * @package BrokenLinkChecker
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class AWLDLC_Notifications
{

    public function __construct()
    {
        add_action('awldlc_scan_complete', array($this, 'on_scan_complete'));
        add_action('awldlc_send_digest', array($this, 'send_digest'));
    }

    public function on_scan_complete($scan)
    {
        $settings = get_option('awldlc_settings', array());

        if (empty($settings['email_notifications'])) {
            return;
        }

        $threshold = isset($settings['notify_threshold']) ? absint($settings['notify_threshold']) : 1;

        if ($scan->broken_links < $threshold) {
            return;
        }

        $this->send_notification($scan);
    }

    private function send_notification($scan)
    {
        $settings = get_option('awldlc_settings', array());
        $recipients = isset($settings['email_recipients']) ? (array) $settings['email_recipients'] : array(get_option('admin_email'));

        if (empty($recipients)) {
            return;
        }

        $site_name = get_bloginfo('name');
        $subject = sprintf(
            /* translators: 1: number of broken links, 2: site name */
            __('[%2$s] %1$d Broken Links Detected', 'dead-link-checker'),
            $scan->broken_links,
            $site_name
        );

        $message = $this->build_email_message($scan);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        foreach ($recipients as $email) {
            $email = trim($email);
            if (is_email($email)) {
                wp_mail($email, $subject, $message, $headers);
            }
        }
    }

    private function build_email_message($scan)
    {
        $broken_links = awldlc()->database->get_links(array('status' => 'broken', 'per_page' => 20));
        $dashboard_url = admin_url('admin.php?page=dead-link-checker&status=broken');
        $site_name = get_bloginfo('name');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    line-height: 1.6;
                    color: #333;
                }

                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }

                .header {
                    background: linear-gradient(135deg, #e74c3c, #c0392b);
                    color: white;
                    padding: 30px;
                    border-radius: 8px 8px 0 0;
                    text-align: center;
                }

                .header h1 {
                    margin: 0;
                    font-size: 24px;
                }

                .content {
                    background: #fff;
                    padding: 30px;
                    border: 1px solid #e0e0e0;
                }

                .stats {
                    display: flex;
                    gap: 20px;
                    margin-bottom: 20px;
                }

                .stat {
                    flex: 1;
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 6px;
                    text-align: center;
                }

                .stat-value {
                    font-size: 32px;
                    font-weight: bold;
                    color: #e74c3c;
                }

                .stat-label {
                    color: #666;
                    font-size: 14px;
                }

                .links-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }

                .links-table th,
                .links-table td {
                    padding: 12px;
                    text-align: left;
                    border-bottom: 1px solid #eee;
                }

                .links-table th {
                    background: #f8f9fa;
                    font-weight: 600;
                }

                .status-badge {
                    display: inline-block;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    font-weight: 500;
                    background: #fee;
                    color: #c0392b;
                }

                .btn {
                    display: inline-block;
                    padding: 12px 24px;
                    background: #3498db;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                    font-weight: 500;
                }

                .footer {
                    padding: 20px;
                    text-align: center;
                    color: #999;
                    font-size: 12px;
                }
            </style>
        </head>

        <body>
            <div class="container">
                <div class="header">
                    <h1>🔗 <?php esc_html_e('Broken Links Report', 'dead-link-checker'); ?></h1>
                    <p>
                        <?php echo esc_html($site_name); ?>
                    </p>
                </div>
                <div class="content">
                    <p>
                        <?php esc_html_e('A scan has completed and found the following issues:', 'dead-link-checker'); ?>
                    </p>

                    <div class="stats">
                        <div class="stat">
                            <div class="stat-value">
                                <?php echo esc_html($scan->broken_links); ?>
                            </div>
                            <div class="stat-label">
                                <?php esc_html_e('Broken', 'dead-link-checker'); ?>
                            </div>
                        </div>
                        <div class="stat">
                            <div class="stat-value">
                                <?php echo esc_html($scan->warning_links); ?>
                            </div>
                            <div class="stat-label">
                                <?php esc_html_e('Warnings', 'dead-link-checker'); ?>
                            </div>
                        </div>
                        <div class="stat">
                            <div class="stat-value">
                                <?php echo esc_html($scan->checked_links); ?>
                            </div>
                            <div class="stat-label">
                                <?php esc_html_e('Checked', 'dead-link-checker'); ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($broken_links)): ?>
                        <h3>
                            <?php esc_html_e('Broken Links', 'dead-link-checker'); ?>
                        </h3>
                        <table class="links-table">
                            <thead>
                                <tr>
                                    <th>
                                        <?php esc_html_e('URL', 'dead-link-checker'); ?>
                                    </th>
                                    <th>
                                        <?php esc_html_e('Status', 'dead-link-checker'); ?>
                                    </th>
                                    <th>
                                        <?php esc_html_e('Source', 'dead-link-checker'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($broken_links as $link_data):
                                    $link = new AWLDLC_Link($link_data); ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url($link->url); ?>">
                                                <?php echo esc_html($link->get_display_url(40)); ?>
                                            </a></td>
                                        <td><span class="status-badge">
                                                <?php echo esc_html($link->status_code ?: 'Error'); ?>
                                            </span></td>
                                        <td>
                                            <?php echo esc_html($link->get_source_title()); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <p style="text-align: center; margin-top: 30px;">
                        <a href="<?php echo esc_url($dashboard_url); ?>" class="btn">
                            <?php esc_html_e('View All Broken Links', 'dead-link-checker'); ?>
                        </a>
                    </p>
                </div>
                <div class="footer">
                    <p>
                        <?php printf(esc_html__('This email was sent by Dead Link Checker Pro plugin on %s', 'dead-link-checker'), esc_html($site_name)); ?>
                    </p>
                </div>
            </div>
        </body>

        </html>
        <?php
        return ob_get_clean();
    }

    public function send_digest()
    {
        $settings = get_option('awldlc_settings', array());

        if (empty($settings['email_notifications'])) {
            return;
        }

        $stats = awldlc()->database->get_stats();

        if ($stats['broken'] === 0) {
            return;
        }

        $recipients = isset($settings['email_recipients']) ? (array) $settings['email_recipients'] : array(get_option('admin_email'));
        $site_name = get_bloginfo('name');
        $subject = sprintf(__('[%s] Weekly Broken Links Digest', 'dead-link-checker'), $site_name);

        $fake_scan = (object) array(
            'broken_links' => $stats['broken'],
            'warning_links' => $stats['warning'],
            'checked_links' => $stats['total'],
        );

        $message = $this->build_email_message($fake_scan);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        foreach ($recipients as $email) {
            $email = trim($email);
            if (is_email($email)) {
                wp_mail($email, $subject, $message, $headers);
            }
        }
    }
}
