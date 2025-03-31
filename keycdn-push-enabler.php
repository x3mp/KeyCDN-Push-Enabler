<?php
/**
 * Plugin Name: KeyCDN Push Enabler Addon
 * Plugin URI: https://your-website.com/keycdn-push-addon
 * Description: Add-on for the CDN Enabler plugin that adds Push Zone functionality to KeyCDN integration
 * Author: x3mp
 * Author URI: https://x3mp.com
 * Version: 1.0.0
 * Text Domain: keycdn-push-enabler
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * 
 * @package KeyCDN_Push_Enabler_Addon
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('keycdn_push_enabler_VERSION', '1.0.0');
define('keycdn_push_enabler_FILE', __FILE__);
define('keycdn_push_enabler_DIR', plugin_dir_path(__FILE__));
define('keycdn_push_enabler_URL', plugin_dir_url(__FILE__));
define('keycdn_push_enabler_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class KeyCDN_Push_Enabler_Addon {
    /**
     * Instance of this class
     *
     * @var KeyCDN_Push_Enabler_Addon
     */
    private static $instance;

    /**
     * Plugin options
     *
     * @var array
     */
    private $options;

    /**
     * Compatibility handler
     *
     * @var KeyCDN_Push_Enabler_Addon_Compatibility
     */
    private $compatibility;

    /**
     * API handler
     *
     * @var KeyCDN_Push_Enabler_Addon_API_Handler
     */
    private $api_handler;

    /**
     * File handler
     *
     * @var KeyCDN_Push_Enabler_Addon_File_Handler
     */
    private $file_handler;

    /**
     * Media handler
     *
     * @var KeyCDN_Push_Enabler_Addon_Media_Handler
     */
    private $media_handler;

    /**
     * Cron tasks handler
     *
     * @var KeyCDN_Push_Enabler_Addon_Cron_Tasks
     */
    private $cron_tasks;

    /**
     * Admin interface handler
     *
     * @var KeyCDN_Push_Enabler_Addon_Admin
     */
    private $admin;

    /**
     * Get the singleton instance
     *
     * @return KeyCDN_Push_Enabler_Addon
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Load required files
        $this->load_dependencies();

        // Load plugin options
        $this->options = get_option('keycdn_push_enabler', array());

        // Initialize components
        $this->init_components();

        // Initialize plugin
        $this->init();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Load component classes
        require_once keycdn_push_enabler_DIR . 'includes/class-compatibility.php';
        require_once keycdn_push_enabler_DIR . 'includes/class-api-handler.php';
        require_once keycdn_push_enabler_DIR . 'includes/class-file-handler.php';
        require_once keycdn_push_enabler_DIR . 'includes/class-media-handler.php';
        require_once keycdn_push_enabler_DIR . 'includes/class-cron-tasks.php';
        require_once keycdn_push_enabler_DIR . 'includes/class-admin.php';
    }

    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize compatibility handler
        $this->compatibility = new KeyCDN_Push_Enabler_Addon_Compatibility();

        // Check if CDN Enabler is active
        if (!$this->compatibility->is_cdn_enabler_active()) {
            add_action('admin_notices', array($this->compatibility, 'show_cdn_enabler_missing_notice'));
            return;
        }

        // Initialize API handler
        $this->api_handler = new KeyCDN_Push_Enabler_Addon_API_Handler($this->options);

        // Initialize file handler
        $this->file_handler = new KeyCDN_Push_Enabler_Addon_File_Handler(
            $this->api_handler,
            $this->compatibility
        );

        // Initialize media handler
        $this->media_handler = new KeyCDN_Push_Enabler_Addon_Media_Handler(
            $this->api_handler,
            $this->file_handler
        );

        // Initialize cron tasks handler
        $this->cron_tasks = new KeyCDN_Push_Enabler_Addon_Cron_Tasks(
            $this->api_handler,
            $this->file_handler
        );

        // Initialize admin interface
        if (is_admin()) {
            $this->admin = new KeyCDN_Push_Enabler_Addon_Admin(
                $this->options,
                $this->api_handler,
                $this->cron_tasks
            );
        }
    }

    /**
     * Initialize the plugin
     */
    private function init() {
        // Check if CDN Enabler is active
        if (!$this->compatibility->is_cdn_enabler_active()) {
            return;
        }

        // Load text domain for translations
        add_action('plugins_loaded', array($this, 'load_text_domain'));

        // Register activation hook
        register_activation_hook(keycdn_push_enabler_FILE, array($this, 'activate'));

        // Register deactivation hook
        register_deactivation_hook(keycdn_push_enabler_FILE, array($this, 'deactivate'));
    }

    /**
     * Load text domain for translations
     */
    public function load_text_domain() {
        load_plugin_textdomain(
            'keycdn-push-addon',
            false,
            dirname(plugin_basename(keycdn_push_enabler_FILE)) . '/languages/'
        );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create default options if they don't exist
        if (!get_option('keycdn_push_enabler')) {
            $default_options = array(
                'api_key' => '',
                'push_zone_id' => '',
                'push_static_files' => true,
                'push_on_settings_update' => false
            );
            
            add_option('keycdn_push_enabler', $default_options);
        }

        // Register custom capabilities
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_keycdn_push');
        }

        // Clear scheduled tasks and reschedule them
        wp_clear_scheduled_hook('keycdn_push_enabler_push_static_files');
        wp_clear_scheduled_hook('keycdn_push_enabler_process_file_chunk');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled hooks
        wp_clear_scheduled_hook('keycdn_push_enabler_push_static_files');
        wp_clear_scheduled_hook('keycdn_push_enabler_process_file_chunk');

        // Clear any transients
        delete_transient('keycdn_push_enabler_pushing_files');
        delete_transient('keycdn_push_enabler_progress');
    }

    /**
     * Get plugin option
     *
     * @param string $key Option key
     * @param mixed $default Default value
     * @return mixed Option value
     */
    public function get_option($key, $default = null) {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }
        return $default;
    }
}

// Initialize the plugin
function keycdn_push_enabler() {
    return KeyCDN_Push_Enabler_Addon::get_instance();
}

// Start the plugin
keycdn_push_enabler();