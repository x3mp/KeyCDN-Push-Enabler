<?php
/**
 * Compatibility handler for KeyCDN Push Enabler Addon
 *
 * @package KeyCDN_Push_Enabler_Addon
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle compatibility with CDN Enabler
 */
class KeyCDN_Push_Enabler_Addon_Compatibility {
    /**
     * CDN Enabler settings
     *
     * @var array
     */
    private $cdn_enabler_settings = null;

    /**
     * CDN Enabler version
     *
     * @var string
     */
    private $cdn_enabler_version = null;

    /**
     * Constructor
     */
    public function __construct() {
        // Get CDN Enabler version
        $this->get_cdn_enabler_version();
    }

    /**
     * Check if CDN Enabler plugin is active
     *
     * @return bool True if CDN Enabler is active, false otherwise
     */
    public function is_cdn_enabler_active() {
        // Check if get_plugins() function exists
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $cdn_enabler_installed = false;
        
        foreach ($all_plugins as $plugin_path => $plugin_data) {
            if (strpos($plugin_path, 'cdn-enabler.php') !== false) {
                $cdn_enabler_installed = true;
                break;
            }
        }
        
        return $cdn_enabler_installed && is_plugin_active('cdn-enabler/cdn-enabler.php');
    }

    /**
     * Show admin notice if CDN Enabler is not active
     */
    public function show_cdn_enabler_missing_notice() {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        $message = sprintf(
            __('KeyCDN Push Enabler Addon requires the CDN Enabler plugin to be installed and activated. %1$sPlease install CDN Enabler%2$s.', 'keycdn-push-enabler-addon'),
            '<a href="' . esc_url(admin_url('plugin-install.php?tab=plugin-information&plugin=cdn-enabler&TB_iframe=true&width=600&height=550')) . '" class="thickbox open-plugin-details-modal">',
            '</a>'
        );
        
        echo '<div class="error"><p>' . wp_kses_post($message) . '</p></div>';
    }

    /**
     * Get CDN Enabler version
     *
     * @return string CDN Enabler version
     */
    public function get_cdn_enabler_version() {
        if ($this->cdn_enabler_version !== null) {
            return $this->cdn_enabler_version;
        }

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        
        foreach ($all_plugins as $plugin_path => $plugin_data) {
            if (strpos($plugin_path, 'cdn-enabler.php') !== false) {
                $this->cdn_enabler_version = $plugin_data['Version'];
                break;
            }
        }
        
        return $this->cdn_enabler_version;
    }

    /**
     * Get CDN Enabler settings
     *
     * @return array CDN Enabler settings
     */
    public function get_cdn_enabler_settings() {
        if ($this->cdn_enabler_settings !== null) {
            return $this->cdn_enabler_settings;
        }

        // Try to get CDN Enabler settings
        if (class_exists('CDN_Enabler')) {
            // Method depends on CDN Enabler version
            if (method_exists('CDN_Enabler', 'get_settings')) {
                $this->cdn_enabler_settings = CDN_Enabler::get_settings();
            } else {
                $this->cdn_enabler_settings = get_option('cdn_enabler');
            }
        } else {
            $this->cdn_enabler_settings = get_option('cdn_enabler');
        }

        return $this->cdn_enabler_settings;
    }

    /**
     * Get CDN URL from CDN Enabler settings
     *
     * @return string CDN URL
     */
    public function get_cdn_url() {
        $settings = $this->get_cdn_enabler_settings();
        $cdn_url = '';
        
        if (!empty($settings)) {
            if (isset($settings['url'])) {
                $cdn_url = $settings['url'];
            } elseif (isset($settings['cdn_url'])) {
                $cdn_url = $settings['cdn_url'];
            }
        }
        
        // Make sure the CDN URL doesn't have protocol
        $cdn_url = preg_replace('#^https?://#', '', $cdn_url);
        
        return $cdn_url;
    }

    /**
     * Get included file types from CDN Enabler settings
     *
     * @return array Included file types
     */
    public function get_included_file_types() {
        $settings = $this->get_cdn_enabler_settings();
        $included_types = array('css', 'js', 'jpeg', 'jpg', 'png', 'gif', 'webp', 'svg', 'ttf', 'woff', 'woff2', 'eot', 'ico');
        
        if (!empty($settings)) {
            if (isset($settings['included_files'])) {
                $included_types = $settings['included_files'];
            } elseif (isset($settings['file_extension'])) {
                $included_types = explode(',', $settings['file_extension']);
                $included_types = array_map('trim', $included_types);
            }
        }
        
        return apply_filters('keycdn_push_addon_included_file_types', $included_types);
    }

    /**
     * Get excluded directories from CDN Enabler settings
     *
     * @return array Excluded directories
     */
    public function get_excluded_directories() {
        $settings = $this->get_cdn_enabler_settings();
        $excluded_dirs = array('wp-admin', 'wp-includes');
        
        if (!empty($settings)) {
            if (isset($settings['excluded_dirs'])) {
                $excluded_dirs = $settings['excluded_dirs'];
            } elseif (isset($settings['excludes'])) {
                $excluded_dirs = explode(',', $settings['excludes']);
                $excluded_dirs = array_map('trim', $excluded_dirs);
            }
        }
        
        return apply_filters('keycdn_push_addon_excluded_directories', $excluded_dirs);
    }
}