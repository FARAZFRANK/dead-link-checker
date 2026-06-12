=== Dead Link Checker - SEO and 404 error Fix  ===
Contributors: awordpresslife, razipathhan, hanif0991, muhammadshahid, fkfaisalkhan007, sharikkhan007, zishlife, FARAZFRANK
Tags: broken link, dead link, link checker, 404 error, seo
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Scan your WordPress site for broken links and dead URLs. Detect 404 errors, fix link issues, and keep your SEO healthy.

== Description ==

Broken links frustrate your visitors and hurt your search rankings. When someone clicks a link on your site and lands on a 404 page, they lose trust — and so does Google.

**Dead Link Checker** scans your WordPress posts and pages, finds broken links, and gives you a clear report so you can fix them quickly. It runs in the background without slowing down your site.

Whether you have a personal blog or a large content site, keeping your links healthy is one of the simplest things you can do for on-page SEO. This plugin handles the heavy lifting so you don't have to check every link by hand.

= How It Works =

1. Go to **Dead Link Checker → Dashboard** in your WordPress admin.
2. Click **Scan Now** to start scanning your content.
3. The plugin checks every link in your posts and pages.
4. Broken links, redirects, and slow responses show up in a visual report.
5. Fix or dismiss links directly from the dashboard.

The scanner runs in small batches. This keeps your server running fast. It will not slow down your website for visitors.

== Why Fixing a 404 Error Improves Your SEO ==

Search engines prefer websites with working links. Regular scans help keep your pages healthy. 

* The scanner checks both internal and external links.
* It finds every dead link in your text.
* It lists all 404 error status codes.
* You can see redirects and response times.
* The plugin works with both block and classic editors.
* You can dismiss links to skip them in future scans.

== How to Clean Up Your Website Links ==

1. Go to the Dead Link Checker menu in your admin dashboard.
2. Click the Scan Now button to start.
3. The plugin will check your content in the background.
4. Open the report to see the results.
5. Edit your posts to fix the broken links.
6. Dismiss links that do not need changes.

== Pro Features ==

Take your link management further with [Dead Link Checker Pro](https://awplife.com/wordpress-plugins/dead-link-checker-pro/):

* **Extended Content Scanning** – Scan Custom Post Types, Comments, Navigation Menus, Widgets, and Custom Fields (including ACF).
* **Page Builder Support** – Full compatibility with Elementor, Divi, and WPBakery page builders.
* **Redirect Manager** – Create 301, 302, and 307 redirects directly from the broken link report. Includes a hit counter and import/export functionality.
* **Email Notifications** – Receive email alerts when new broken links are found. Set up weekly or monthly digest reports.
* **Export Reports** – Download your broken link reports as CSV or JSON files for offline review or sharing with your team.
* **Multisite Support** – Manage broken links across your entire WordPress multisite network.
* **Priority Support** – Get dedicated help from the development team with faster response times.
* **Lifetime Updates** – One-time purchase with lifetime access to plugin updates.

[Learn more about Pro](https://awplife.com/wordpress-plugins/dead-link-checker-pro/)

== Free vs Pro Comparison ==

* **Post & Page Scanning** – Free ✓ | Pro ✓
* **Internal & External Links** – Free ✓ | Pro ✓
* **Visual Dashboard** – Free ✓ | Pro ✓
* **Dismiss Links** – Free ✓ | Pro ✓
* **Scan History** – Free ✓ | Pro ✓
* **Custom Post Types** – Free ✗ | Pro ✓
* **Comments & Menus** – Free ✗ | Pro ✓
* **Page Builder Support** – Free ✗ | Pro ✓
* **Redirect Manager** – Free ✗ | Pro ✓
* **Email Notifications** – Free ✗ | Pro ✓
* **CSV/JSON Export** – Free ✗ | Pro ✓
* **Multisite Support** – Free ✗ | Pro ✓
* **Priority Support** – Free ✗ | Pro ✓

== Installation ==

1. Upload the plugin folder to your plugins directory.
2. Activate the plugin in your WordPress admin.
3. Open the Dead Link Checker page.
4. Click Scan Now to start checking your site.

== Frequently Asked Questions ==

= How does a dead link or broken link hurt my SEO? =
Search engines want to give users a good experience. A site with many errors gets crawled less often. This hurts your visibility. Fixing every 404 error keeps your rankings safe.

= Will this scanner cause server errors? =
No. The tool checks links in small groups. It uses very little server power. Your website stays fast for all visitors.

= Can I check external links for errors? =
Yes. The plugin checks links pointing to other sites. It also checks internal links on your own site.

= Does it find 404 error status codes? =
Yes. It reports all 404 error pages. It also flags other server issues like 500 or 403 status codes.

== Screenshots ==

1. **Dashboard** – Overview of your site's link health with status cards and scan controls.
2. **Broken Link Report** – Detailed list of broken, working, and warning links with source information.
3. **Settings Panel** – Configure scan options, link types, and scanner behavior.
4. **Redirects**: (Pro) Manage 301 and 302 redirects easily.

== Changelog ==

= 1.0.4 =
* Fixed wpdb::prepare query missing placeholder notice.
* Added 'Recheck' and 'Edit' buttons to link actions with 'Upgrade to Pro' prompt.
* Added 'Export' button dropdown (CSV & JSON) as a PRO preview feature.
* Added 'Notifications' tab in Settings displaying disabled email notification options for PRO preview.
* Replaced native browser confirm alerts with stylized custom HTML modals across the dashboard.


= 1.0.3 =
* Improved UI consistency: border-radius, height, padding, and spacing now match across all admin pages.
* Replaced hardcoded border-radius values with CSS token variables for global consistency.
* Normalized settings page inputs and selects to uniform 32px height with consistent focus styles.
* Standardized Tools tab maintenance buttons to fixed 32px height and 150px width.
* Improved header action buttons: explicit 44px height with proper secondary/primary button distinction.
* Fixed action button and stat icon radius to use design tokens instead of hardcoded values.
* Removed remaining !important overrides from check-column, clear-filters, and no-items cell.
* Added white-space: nowrap to status badges to prevent wrapping on narrow columns.
* Added sortable column anchor padding for full-cell click area.

= 1.0.2 =
* Removed edit button and edit link functionality from the dashboard.
* Removed recheck option and its functionality.
* Removed images option from link type settings.
* Added "Upgrade to Pro" submenu page with feature highlights and comparison table.
* Centered help page content for improved readability.
* Fixed SQL prepared statement warnings for WordPress Plugin Check compliance.
* Fixed unescaped database parameter warnings in scanner and uninstall files.
* Rewrote readme.txt for WordPress.org guideline compliance.
* Updated plugin name to "Dead Link Checker" for consistency.

= 1.0.1 =
* Maintenance release.
* Updated readme for WordPress.org compliance.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.4 =
Bug fixes and UI improvements, including custom confirmation modals and new PRO preview features.

= 1.0.3 =
UI polish release. Consistent heights, padding, spacing, and border-radius across all admin pages.

= 1.0.2 =
Improved WordPress.org compliance, added Upgrade to Pro page, and fixed database query warnings.
