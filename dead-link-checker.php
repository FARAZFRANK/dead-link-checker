<?php
/**
 * Plugin Name: Dead Link Checker
 * Plugin URI: https://awplife.com/
 * Description: Dead Link Checker for WordPress. Scan posts, pages, custom post types, page builders, menus, widgets, and comments with email notifications, redirects, and export features.
 * Version: 3.0.8
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: A WP Life
 * Author URI: https://awplife.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dead-link-checker
 * Domain Path: /languages
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Plugin Constants
 */
define('AWLDLC_VERSION', '3.0.8');
define('AWLDLC_PLUGIN_FILE', __FILE__);
define('AWLDLC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AWLDLC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AWLDLC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Autoloader for plugin classes
 * 
 * @param string $class_name The class name to load
 */
spl_autoload_register(function ($class_name) {
    // Only handle our plugin classes
    if (strpos($class_name, 'AWLDLC_') !== 0) {
        return;
    }

    // Convert class name to file path
    // Class AWLDLC_Admin → class-awldlc-admin.php
    $class_file = strtolower(str_replace('_', '-', $class_name));
    // File names now use awldlc- prefix directly
    $class_file = 'class-' . $class_file . '.php';

    // Define possible locations
    $locations = array(
        AWLDLC_PLUGIN_DIR . 'includes/',
        AWLDLC_PLUGIN_DIR . 'includes/admin/',
        AWLDLC_PLUGIN_DIR . 'includes/scanner/',
        AWLDLC_PLUGIN_DIR . 'includes/scanner/parsers/',
        AWLDLC_PLUGIN_DIR . 'includes/models/',
    );

    // Search for the class file
    foreach ($locations as $location) {
        $file_path = $location . $class_file;
        if (file_exists($file_path)) {
            require_once $file_path;
            return;
        }
    }
});

/**
 * Plugin Activation Hook
 */
register_activation_hook(__FILE__, function () {
    require_once AWLDLC_PLUGIN_DIR . 'includes/class-awldlc-activator.php';
    AWLDLC_Activator::activate();
});

/**
 * Plugin Deactivation Hook
 */
register_deactivation_hook(__FILE__, function () {
    require_once AWLDLC_PLUGIN_DIR . 'includes/class-awldlc-deactivator.php';
    AWLDLC_Deactivator::deactivate();
});

/**
 * Main Plugin Class
 */
final class Broken_Link_Checker
{

    /**
     * Single instance of the plugin
     *
     * @var Broken_Link_Checker|null
     */
    private static $instance = null;

    /**
     * Admin instance
     *
     * @var AWLDLC_Admin|null
     */
    public $admin = null;

    /**
     * Scanner instance
     *
     * @var AWLDLC_Scanner|null
     */
    public $scanner = null;

    /**
     * Database instance
     *
     * @var AWLDLC_Database|null
     */
    public $database = null;

    /**
     * Get single instance of the plugin
     *
     * @return Broken_Link_Checker
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Initialize the plugin
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Initialize plugin components
     */
    public function init()
    {
        // Initialize database
        $this->database = new AWLDLC_Database();

        // Initialize scanner
        $this->scanner = new AWLDLC_Scanner();

        // Initialize admin (only in admin area)
        if (is_admin()) {
            $this->admin = new AWLDLC_Admin();
        }

        // Initialize notifications
        new AWLDLC_Notifications();

        // Initialize redirects
        new AWLDLC_Redirects();

        // Initialize multisite support
        if (is_multisite()) {
            new AWLDLC_Multisite();
        }

        /**
         * Fires after the plugin is fully initialized
         *
         * @param Broken_Link_Checker $this The plugin instance
         */
        do_action('awldlc_init', $this);
    }

    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'dead-link-checker',
            false,
            dirname(AWLDLC_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Get plugin option with default value
     *
     * @param string $key     Option key
     * @param mixed  $default Default value
     * @return mixed
     */
    public static function get_option($key, $default = null)
    {
        $options = get_option('awldlc_settings', array());
        return isset($options[$key]) ? $options[$key] : $default;
    }

    /**
     * Update plugin option
     *
     * @param string $key   Option key
     * @param mixed  $value Option value
     * @return bool
     */
    public static function update_option($key, $value)
    {
        $options = get_option('awldlc_settings', array());
        $options[$key] = $value;
        return update_option('awldlc_settings', $options);
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton');
    }
}

/**
 * Get the main plugin instance
 *
 * @return Broken_Link_Checker
 */
function awldlc()
{
    return Broken_Link_Checker::get_instance();
}

// Initialize the plugin
awldlc();
