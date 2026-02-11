<?php
/**
 * Plugin Name: Dead Link Checker Pro
 * Plugin URI: https://awplife.com/
 * Description: Professional Dead Link Checker Pro for WordPress. Scan posts, pages, custom post types, page builders, menus, widgets, and comments with email notifications, redirects, and export features.
 * Version: 3.0.4
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
define('BLC_VERSION', '3.0.4');
define('BLC_PLUGIN_FILE', __FILE__);
define('BLC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BLC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BLC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Autoloader for plugin classes
 * 
 * @param string $class_name The class name to load
 */
spl_autoload_register(function ($class_name) {
    // Only handle our plugin classes
    if (strpos($class_name, 'BLC_') !== 0) {
        return;
    }

    // Convert class name to file path
    $class_file = strtolower(str_replace('_', '-', $class_name));
    $class_file = 'class-' . $class_file . '.php';

    // Define possible locations
    $locations = array(
        BLC_PLUGIN_DIR . 'includes/',
        BLC_PLUGIN_DIR . 'includes/admin/',
        BLC_PLUGIN_DIR . 'includes/scanner/',
        BLC_PLUGIN_DIR . 'includes/scanner/parsers/',
        BLC_PLUGIN_DIR . 'includes/models/',
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
    require_once BLC_PLUGIN_DIR . 'includes/class-blc-activator.php';
    BLC_Activator::activate();
});

/**
 * Plugin Deactivation Hook
 */
register_deactivation_hook(__FILE__, function () {
    require_once BLC_PLUGIN_DIR . 'includes/class-blc-deactivator.php';
    BLC_Deactivator::deactivate();
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
     * @var BLC_Admin|null
     */
    public $admin = null;

    /**
     * Scanner instance
     *
     * @var BLC_Scanner|null
     */
    public $scanner = null;

    /**
     * Database instance
     *
     * @var BLC_Database|null
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
        $this->database = new BLC_Database();

        // Initialize scanner
        $this->scanner = new BLC_Scanner();

        // Initialize admin (only in admin area)
        if (is_admin()) {
            $this->admin = new BLC_Admin();
        }

        // Initialize notifications
        new BLC_Notifications();

        // Initialize redirects
        new BLC_Redirects();

        // Initialize multisite support
        if (is_multisite()) {
            new BLC_Multisite();
        }

        /**
         * Fires after the plugin is fully initialized
         *
         * @param Broken_Link_Checker $this The plugin instance
         */
        do_action('blc_init', $this);
    }

    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'dead-link-checker',
            false,
            dirname(BLC_PLUGIN_BASENAME) . '/languages'
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
        $options = get_option('blc_settings', array());
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
        $options = get_option('blc_settings', array());
        $options[$key] = $value;
        return update_option('blc_settings', $options);
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
function blc()
{
    return Broken_Link_Checker::get_instance();
}

// Initialize the plugin
blc();
