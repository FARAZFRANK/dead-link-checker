<?php
/**
 * Plugin Name: Frank Dead Link Checker
 * Plugin URI: https://wordpress.org/plugins/frank-dead-link-checker
 * Description: Frank Dead Link Checker for WordPress. Scan posts, pages, custom post types, page builders, menus, widgets, and comments with email notifications, redirects, and export features.
 * Version: 1.0.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: A WP Life
 * Author URI: https://wordpress.org/plugins/frank-dead-link-checker
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: frank-dead-link-checker
 * Domain Path: /languages
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Plugin Constants
 */
define('FRANKDLC_VERSION', '1.0.1');
define('FRANKDLC_PLUGIN_FILE', __FILE__);
define('FRANKDLC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FRANKDLC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FRANKDLC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Autoloader for plugin classes
 * 
 * @param string $class_name The class name to load
 */
spl_autoload_register(function ($class_name) {
    // Only handle our plugin classes
    if (strpos($class_name, 'FRANKDLC_') !== 0) {
        return;
    }

    // Convert class name to file path
    // Class FRANKDLC_Admin → class-frankdlc-admin.php
    $class_file = strtolower(str_replace('_', '-', $class_name));
    // File names now use frankdlc- prefix directly
    $class_file = 'class-' . $class_file . '.php';

    // Define possible locations
    $locations = array(
        FRANKDLC_PLUGIN_DIR . 'includes/',
        FRANKDLC_PLUGIN_DIR . 'includes/admin/',
        FRANKDLC_PLUGIN_DIR . 'includes/scanner/',
        FRANKDLC_PLUGIN_DIR . 'includes/scanner/parsers/',
        FRANKDLC_PLUGIN_DIR . 'includes/models/',
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
    require_once FRANKDLC_PLUGIN_DIR . 'includes/class-frankdlc-activator.php';
    FRANKDLC_Activator::activate();
});

/**
 * Plugin Deactivation Hook
 */
register_deactivation_hook(__FILE__, function () {
    require_once FRANKDLC_PLUGIN_DIR . 'includes/class-frankdlc-deactivator.php';
    FRANKDLC_Deactivator::deactivate();
});

/**
 * Main Plugin Class
 */
final class FRANKDLC_Plugin
{

    /**
     * Single instance of the plugin
     *
     * @var FRANKDLC_Plugin|null
     */
    private static $instance = null;

    /**
     * Admin instance
     *
     * @var FRANKDLC_Admin|null
     */
    public $admin = null;

    /**
     * Scanner instance
     *
     * @var FRANKDLC_Scanner|null
     */
    public $scanner = null;

    /**
     * Database instance
     *
     * @var FRANKDLC_Database|null
     */
    public $database = null;

    /**
     * Get single instance of the plugin
     *
     * @return FRANKDLC_Plugin
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

    }

    /**
     * Initialize plugin components
     */
    public function init()
    {
        // Initialize database
        $this->database = new FRANKDLC_Database();

        // Initialize scanner
        $this->scanner = new FRANKDLC_Scanner();

        // Initialize admin (only in admin area)
        if (is_admin()) {
            $this->admin = new FRANKDLC_Admin();
        }

        // Initialize notifications
        // new FRANKDLC_Notifications();

        // Initialize redirects
        // new FRANKDLC_Redirects();

        // Initialize multisite support
        if (is_multisite()) {
            // new FRANKDLC_Multisite();
        }

        /**
         * Fires after the plugin is fully initialized
         *
         * @param FRANKDLC_Plugin $this The plugin instance
         */
        do_action('FRANKDLC_init', $this);
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
        $options = get_option('FRANKDLC_settings', array());
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
        $options = get_option('FRANKDLC_settings', array());
        $options[$key] = $value;
        return update_option('FRANKDLC_settings', $options);
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
 * @return FRANKDLC_Plugin
 */
function FRANKDLC()
{
    return FRANKDLC_Plugin::get_instance();
}

// Initialize the plugin
FRANKDLC();
