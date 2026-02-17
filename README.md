# Frank Dead Link Checker

![WordPress Version](https://img.shields.io/badge/WordPress-5.8%2B-blue)
![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple)
![License](https://img.shields.io/badge/License-GPLv2-green)
![Version](https://img.shields.io/badge/Version-1.0.1-orange)

**The complete professional solution for finding and fixing broken links on your WordPress website.**

Frank Dead Link Checker scans your entire WordPress site, identifies dead links, and helps you fix them quickly—improving SEO and user experience.

---

## 🚀 Features

### Content Scanning
- ✅ Posts and Pages
- ✅ Custom Post Types
- ✅ Navigation Menus
- ✅ Text Widgets
- ✅ Comments
- ✅ Custom Fields / ACF

### Page Builder Support
- ⚡ **Gutenberg** (WordPress Block Editor)
- ⚡ **Elementor**
- ⚡ **Divi Builder**
- ⚡ **WPBakery Page Builder**

### Link Types Detected
- 🔗 Internal Links
- 🌐 External Links
- 🖼️ Images
- 📺 YouTube Embeds

### Scan Settings
- ⏰ Daily, weekly, or monthly scan frequency
- 🔄 Configurable concurrent requests (1-10)
- ⏱️ Configurable request timeout (5-60 seconds)

### Actions
- 👀 View broken links with detailed information
- ❌ Dismiss/Undismiss links
- 🔁 Recheck single or multiple links
- ✏️ Edit link directly in post content
- 📦 Bulk actions for efficiency

### Redirect Manager
- 🔀 Create **301** (Permanent) redirects
- 🔀 Create **302** (Temporary) redirects
- 🔀 Create **307** (Temporary) redirects
- 📊 Redirect hit counter
- 📥 Import/Export redirects

### Reports and Export
- 📈 Beautiful dashboard with real-time statistics
- 📄 Export to **CSV**
- 📄 Export to **JSON**
- 📚 Unlimited scan history

### Notifications
- 📧 Email notifications when broken links are found
- 📅 Weekly/monthly digest reports
- ⚙️ Customizable notification settings

### Advanced Features
- 🚫 Unlimited domain exclusions
- 🌐 **Multisite support**
- 🏢 Network dashboard for multisite
- 💬 Priority support

---

## 📦 Installation

1. Upload the `frank-dead-link-checker-pro` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Link Checker** in your admin menu
4. Click **Scan Now** to start your first link scan

---

## 🛠️ Requirements

- **WordPress:** 5.8 or higher
- **PHP:** 7.4 or higher
- **MySQL:** 5.6 or higher

---

## 📸 Screenshots

| Dashboard | Link Table |
|-----------|------------|
| Real-time link statistics | Advanced filtering options |

| Redirect Manager | Settings |
|------------------|----------|
| Create and manage redirects | Configure scan options |

---

## 🔧 Configuration

### Basic Setup
1. Navigate to **Link Checker > Settings**
2. Configure your scan frequency (daily, weekly, monthly)
3. Set the number of concurrent requests based on your server capacity
4. Enable email notifications if desired

### Email Notifications
1. Go to **Settings > Notifications**
2. Enter the email address for notifications
3. Choose notification frequency
4. Customize the email template (optional)

### Redirect Manager
1. Navigate to **Link Checker > Redirects**
2. Click **Add Redirect**
3. Enter source URL and destination URL
4. Select redirect type (301, 302, or 307)
5. Save the redirect

---

## 📝 Changelog

### 3.1.0
- Moved Reset & Maintenance Options from Help page to Settings page under new "Tools" tab
- Added Settings tab persistence — active tab remembers across page reloads and Save Changes
- Fixed activation redirect slug mismatch (7 instances across 4 files)
- Fixed Settings page CSS class mismatch (blc-settings-page → frankdlc-settings-page)
- Code quality improvements and standard compliance fixes across various files

### 3.0.9
- WordPress.org review compliance — complete plugin rebrand
- Changed plugin name to "Frank Dead Link Checker", slug to frank-dead-link-checker
- Renamed all AWLDLC/awldlc prefixes to FRANKDLC/frankdlc
- Renamed all class files and main plugin file
- Updated text domain, translation files

### 3.0.8
- WordPress.org Guideline 6 compliance — removed all "Pro" references from plugin name, descriptions, and UI strings
- Updated readme.txt, README.md, and all admin-facing text
- Updated translation template (.pot) and Hindi translation (.po) files

### 3.0.7
- Complete prefix refactoring — renamed all BLC/blc prefixes to AWLDLC/awldlc for WordPress coding standards compliance
- Renamed 19 PHP class files from class-blc-*.php to class-awldlc-*.php
- Updated all HTML element IDs, CSS classes, CSS custom properties, and jQuery selectors
- Updated all AJAX action names from blc_* to awldlc_*
- Updated autoloader and require_once paths for renamed files

### 3.0.6
- Added full internationalization (i18n) support — plugin is now translation-ready
- Generated POT template file with 405 translatable strings
- Added complete Hindi (hi_IN) translation (100% coverage)
- Localized all JavaScript strings via wp_localize_script
- Added "How to Translate This Plugin" section to Help page
- Fixed third-party admin notices breaking plugin header layout

### 3.0.5
- Fixed dashboard stats cache not clearing when link status changes
- Fixed Fresh Scan not clearing stats cache
- Fixed HTTP Status help link pointing to wrong page
- Fixed Rows per page dropdown not working
- Added 10 as default Rows per page option

### 3.0.4
- Fixed Reset Settings and Full Plugin Reset errors
- Improved Recheck link feature — detects when link is manually fixed
- Fixed sanitize_settings handling of array inputs

### 3.0.3
- Added Scan Type setting (Manual / Automatic) for controlling scheduled scans
- Added Force Stop Scan feature to forcefully cancel stuck scans
- Added Reset Settings to restore plugin defaults without losing data
- Added Clear Scan History to remove old scan records
- Added Full Plugin Reset for complete factory reset
- Added Cleanup Exports to delete old CSV/JSON export files
- Added action buttons to Help page Reset & Maintenance section
- Improved Help page with comprehensive documentation
- Stale scan auto-recovery for scans stuck over 30 minutes
- Scan timeout watchdog for automatic cleanup
- Enhanced uninstall cleanup with multisite support
- Fixed BLC_VERSION constant (was stuck at 1.0.0)

### 3.0.2
- Improved export behavior - CSV now downloads directly without leaving dashboard
- JSON export now opens in a new tab for easy viewing
- Fixed export navigation issue that was redirecting users away from plugin dashboard

### 3.0.1
- Fixed export feature - renamed folder from blc-exports to dlc-exports
- Fixed export file prefix from blc-export to dlc-export for consistent branding
- Fixed 403 Forbidden error when downloading exported CSV/JSON files
- Blog article added for plugin promotion

### 3.0.0
- Complete rebranding from "Broken Link Checker" to "Frank Dead Link Checker"
- Enhanced dashboard UI with real-time statistics
- Improved table responsiveness for mobile devices
- Fixed pagination table footer alignment
- Added professional email notifications system
- Added redirect manager with 301/302/307 support
- Page builder support for Elementor, Divi, WPBakery, and Gutenberg
- CSV and JSON export functionality
- Multisite network support with network dashboard
- Customizable scan settings with concurrent requests
- Domain exclusion management
- Performance optimizations throughout

### 1.0.0
- Initial release

---

## 🔒 Privacy Policy

Frank Dead Link Checker respects your privacy:

- **No External Connections** – The plugin only connects to URLs on your own website during scans
- **No Data Collection** – We do not collect, track, or transmit any personal data
- **Local Storage** – All scan data is stored in your WordPress database
- **GDPR Compliant** – No cookies, no tracking, no third-party services

---

## 🤝 Support

For support, please visit [AWP Life](https://awplife.com/) or email us directly.

---

## 📄 License

This project is licensed under the **GPLv2 or later** - see the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.

---

**Made with ❤️ by [AWP Life](https://awplife.com/)**
