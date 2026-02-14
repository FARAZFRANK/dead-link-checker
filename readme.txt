=== Dead Link Checker Pro ===
Contributors: awordpresslife, razipathhan, hanif0991, muhammadshahid, fkfaisalkhan007, sharikkhan007, zishlife, FARAZFRANK
Donate link: https://awplife.com/
Tags: broken links, dead links, seo, 404 error, link checker, redirects, elementor
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.0.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional Dead Link Checker Pro for WordPress. Scan all content types, page builders, with email notifications, redirects, and export features.

== Description ==

**Dead Link Checker Pro** is the complete professional solution for finding and fixing broken links on your WordPress website. Includes all features from the free version plus advanced capabilities for power users and agencies.

= All Pro Features =

**Content Scanning:**

* Posts and Pages
* Custom Post Types
* Navigation Menus
* Text Widgets
* Comments
* Custom Fields / ACF

**Page Builder Support:**

* Gutenberg (WordPress Block Editor)
* Elementor
* Divi Builder
* WPBakery Page Builder

**Link Types:**

* Internal Links
* External Links
* Images
* YouTube Embeds

**Scan Settings:**

* Daily, weekly, or monthly scan frequency
* Configurable concurrent requests (1-10)
* Configurable request timeout (5-60 seconds)

**Actions:**

* View broken links
* Dismiss/Undismiss links
* Recheck single or multiple links
* Edit link directly in post content
* Bulk actions

**Redirect Manager:**

* Create 301 (Permanent) redirects
* Create 302 (Temporary) redirects
* Create 307 (Temporary) redirects
* Redirect hit counter
* Import/Export redirects

**Reports and Export:**

* Beautiful dashboard with real-time statistics
* Export to CSV
* Export to JSON
* Unlimited scan history

**Notifications:**

* Email notifications when broken links are found
* Weekly/monthly digest reports
* Customizable notification settings

**Advanced Features:**

* Unlimited domain exclusions
* Multisite support
* Network dashboard for multisite
* Priority support

== Installation ==

1. Upload the `dead-link-checker` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Link Checker' in your admin menu
4. Click 'Scan Now' to start your first link scan

== Frequently Asked Questions ==

= How do I activate my Pro license? =

Go to Link Checker > Settings > License tab and enter your license key. The license key was sent to your email after purchase.

= How long does a scan take? =

Scan time depends on your website size and the number of concurrent requests configured. With Pro, you can increase concurrent requests for faster scans.

= Does it work with all page builders? =

Yes! Pro supports Elementor, Divi, WPBakery, and Gutenberg. All links within page builder content are detected and checked.

= How do I create a redirect? =

Go to Link Checker > Redirects tab, click "Add Redirect", enter the source URL and destination URL, choose the redirect type, and save.

= Can I export my broken links list? =

Yes. Click the 'Export' dropdown on the dashboard and choose CSV or JSON format.

= Where can I get support? =

Pro users receive priority support. Visit [AWP Life](https://awplife.com/) or email us directly for assistance.

== Screenshots ==

1. Dashboard with real-time link statistics
2. Link table with advanced filtering
3. Edit link modal
4. Redirect Manager
5. Settings page with Pro options
6. Email notification settings

== Changelog ==

= 3.0.7 =
* Complete prefix refactoring — renamed all BLC/blc prefixes to AWLDLC/awldlc for WordPress coding standards compliance
* Renamed 19 PHP class files from class-blc-*.php to class-awldlc-*.php
* Updated all HTML element IDs, CSS classes, CSS custom properties, and jQuery selectors
* Updated all AJAX action names from blc_* to awldlc_*
* Updated autoloader and require_once paths for renamed files
* Fixed help tab IDs and internal URL hash references

= 3.0.6 =
* Added full internationalization (i18n) support — plugin is now translation-ready
* Generated POT template file with 405 translatable strings
* Added complete Hindi (hi_IN) translation — PO and MO files (100% coverage)
* Localized all JavaScript strings via wp_localize_script for proper translation
* Wrapped remaining hardcoded PHP strings with translation functions (__(), esc_html__(), etc.)
* Added "How to Translate This Plugin" section to Help page with Loco Translate, Poedit, and WordPress.org instructions
* Fixed third-party admin notices breaking plugin header layout

= 3.0.5 =
* Fixed dashboard stats cache not clearing when link status changes — broken link count now updates immediately
* Fixed Fresh Scan not clearing stats cache — stale broken link counts no longer persist after clearing all data
* Fixed HTTP Status help link pointing to wrong page (dead-link-checker-help → blc-help)
* Fixed Rows per page dropdown not working due to JavaScript URL constructor scope conflict in WordPress
* Added 10 as a Rows per page option and set it as the default
* Changed default Rows per page from 20 to 10 for better usability

= 3.0.4 =
* Fixed Reset Settings and Full Plugin Reset errors (PHP TypeError in sanitize_settings)
* Improved Recheck link feature — now detects when a link is manually fixed in the post editor
* Recheck automatically removes stale entries when the URL no longer exists in source content
* Fixed sanitize_settings handling of array inputs for excluded_domains and email_recipients
* Improved table row removal animation when rechecked link is found fixed

= 3.0.3 =
* Added Scan Type setting (Manual / Automatic) for controlling scheduled scans
* Added Force Stop Scan feature to forcefully cancel stuck scans
* Added Reset Settings to restore plugin defaults without losing data
* Added Clear Scan History to remove old scan records
* Added Full Plugin Reset for complete factory reset
* Added Cleanup Exports to delete old CSV/JSON export files
* Added action buttons to Help page Reset & Maintenance section
* Improved Help page with comprehensive documentation
* Stale scan auto-recovery for scans stuck over 30 minutes
* Scan timeout watchdog for automatic cleanup
* Enhanced uninstall cleanup with multisite support
* Fixed BLC_VERSION constant (was stuck at 1.0.0)

= 3.0.2 =
* Improved export behavior - CSV now downloads directly without leaving dashboard
* JSON export now opens in a new tab for easy viewing
* Fixed export navigation issue that was redirecting users away from plugin dashboard

= 3.0.1 =
* Fixed export feature - renamed export folder from blc-exports to dlc-exports
* Fixed export file prefix from blc-export to dlc-export for consistent branding
* Fixed 403 Forbidden error when downloading exported CSV/JSON files
* Blog article added for plugin promotion

= 3.0.0 =
* Complete rebranding from "Broken Link Checker" to "Dead Link Checker Pro"
* Enhanced dashboard UI with real-time statistics
* Improved table responsiveness for mobile devices
* Fixed pagination table footer alignment
* Added professional email notifications system
* Added redirect manager with 301/302/307 support
* Page builder support for Elementor, Divi, WPBakery, and Gutenberg
* CSV and JSON export functionality
* Multisite network support with network dashboard
* Customizable scan settings with concurrent requests
* Domain exclusion management
* Performance optimizations throughout

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 3.0.7 =
Complete prefix refactoring for WordPress coding standards. All internal identifiers updated from BLC to AWLDLC.

= 3.0.6 =
Full translation support added. POT template and Hindi translation included. JS strings localized. Help page now has translation instructions.

= 3.0.5 =
Fixed broken link count sync issue, stats cache improvements, and corrected help page link.

= 3.0.4 =
Fixed Reset Settings errors, improved Recheck to detect manually fixed links, and sanitize_settings array handling fix.

= 3.0.3 =
New scan controls, reset & maintenance tools, Help page improvements, and scan stability fixes.

= 3.0.2 =
Improved export download behavior - CSV downloads directly, JSON opens in new tab.

= 3.0.1 =
Fixed export feature with proper branding and resolved 403 download error.

= 3.0.0 =
Major rebranding release. Dead Link Checker Pro now includes all premium features with improved UI and performance.

= 1.0.0 =
First release of Dead Link Checker Pro.

== Privacy Policy ==

Dead Link Checker Pro respects your privacy:

* **No External Connections** – The plugin only connects to URLs on your own website during scans
* **No Data Collection** – We do not collect, track, or transmit any personal data
* **Local Storage** – All scan data is stored in your WordPress database
* **GDPR Compliant** – No cookies, no tracking, no third-party services

For more information, visit [AWP Life](https://awplife.com/)
