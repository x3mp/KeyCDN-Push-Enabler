<?php
/**
 * File handler for KeyCDN Push Zone Addon
 *
 * @package KeyCDN_Push_Enabler_Addon
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle file operations
 */
class KeyCDN_Push_Enabler_Addon_File_Handler {
    /**
     * API handler
     *
     * @var KeyCDN_Push_Enabler_Addon_API_Handler
     */
    private $api_handler;

    /**
     * Compatibility handler
     *
     * @var KeyCDN_Push_Enabler_Addon_Compatibility
     */
    private $compatibility;

    /**
     * Constructor
     *
     * @param KeyCDN_Push_Enabler_Addon_API_Handler $api_handler API handler
     * @param KeyCDN_Push_Enabler_Addon_Compatibility $compatibility Compatibility handler
     */
    public function __construct($api_handler, $compatibility) {
        $this->api_handler = $api_handler;
        $this->compatibility = $compatibility;
    }

    /**
     * Get allowed MIME types
     *
     * @return array Allowed MIME types
     */
    public function get_allowed_mime_types() {
        $default_mime_types = array(
            'text/css',
            'text/javascript', 'application/javascript',
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'application/font-woff', 'application/font-woff2', 'application/x-font-ttf',
            'application/vnd.ms-fontobject',
            'image/x-icon'
        );
        
        return apply_filters('keycdn_push_enabler_allowed_mime_types', $default_mime_types);
    }

    /**
     * Validate file before upload
     * 
     * @param string $file_path The full file path
     * @return bool True if file is valid, false otherwise
     */
    public function validate_file($file_path) {
        // Check if file exists
        if (!file_exists($file_path)) {
            $this->log_error("File does not exist: " . $this->sanitize_path_for_log($file_path));
            return false;
        }
        
        // Check file size (limit to 100MB)
        $max_size = apply_filters('keycdn_push_enabler_max_file_size', 100 * 1024 * 1024);
        if (filesize($file_path) > $max_size) {
            $this->log_error("File exceeds maximum size: " . $this->sanitize_path_for_log($file_path));
            return false;
        }
        
        // Get file extension
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // Check if extension is allowed
        $included_types = $this->compatibility->get_included_file_types();
        if (!in_array($extension, $included_types)) {
            $this->log_error("File type not allowed: " . $extension);
            return false;
        }
        
        // Check MIME type for extra security
        $mime_type = mime_content_type($file_path);
        $allowed_mime_types = $this->get_allowed_mime_types();
        
        if (!in_array($mime_type, $allowed_mime_types)) {
            $this->log_error("MIME type not allowed: " . $mime_type);
            return false;
        }
        
        return true;
    }

    /**
     * Sanitize file path for logging
     * 
     * @param string $path File path
     * @return string Sanitized path
     */
    private function sanitize_path_for_log($path) {
        // Remove WordPress root path
        return str_replace(ABSPATH, '[WORDPRESS_ROOT]/', $path);
    }

    /**
     * Log error securely
     *
     * @param string $message Error message
     * @param array $context Additional context (will be sanitized)
     */
    private function log_error($message, $context = array()) {
        // Sanitize context data
        $safe_context = array();
        foreach ($context as $key => $value) {
            if (is_string($value)) {
                // Remove sensitive information
                $value = preg_replace('/api[_\-]?key\s*[:=]\s*["\']?([^"\'\s]+)["\']?/i', 'api_key: [REDACTED]', $value);
                $value = preg_replace('/password\s*[:=]\s*["\']?([^"\'\s]+)["\']?/i', 'password: [REDACTED]', $value);
                
                // Sanitize paths
                $value = $this->sanitize_path_for_log($value);
            }
            
            $safe_context[$key] = $value;
        }
        
        // Use WordPress error logging
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log('KeyCDN Push Addon Error: ' . $message . ' ' . json_encode($safe_context));
        }
    }

    /**
     * Push a file to KeyCDN
     *
     * @param string $file_path Full file path
     * @param string $relative_path Relative path
     * @return bool True on success, false on failure
     */
    public function push_file($file_path, $relative_path) {
        // Validate file before pushing
        if (!$this->validate_file($file_path)) {
            return false;
        }

        // Use API handler to push file
        return $this->api_handler->push_file($file_path, $relative_path);
    }

    /**
     * Delete a file from KeyCDN (actually purge URL)
     *
     * @param string $relative_path Relative path
     * @param string $cdn_url CDN URL
     * @return bool True on success, false on failure
     */
    public function delete_file($relative_path, $cdn_url = null) {
        if (empty($cdn_url)) {
            $cdn_url = $this->compatibility->get_cdn_url();
        }

        if (empty($cdn_url)) {
            $this->log_error("CDN URL not configured");
            return false;
        }

        // Full path in the CDN
        $cdn_path = 'https://' . $cdn_url . '/wp-content/uploads/' . $relative_path;
        
        // Use API handler to purge URL
        return $this->api_handler->purge_url($cdn_path);
    }

    /**
     * Get total number of files to process
     *
     * @return int Number of files
     */
    public function get_files_count() {
        $transient_key = 'keycdn_push_enabler_files_count';
        $files_count = get_transient($transient_key);
        
        if ($files_count !== false) {
            return $files_count;
        }
        
        // Count files recursively
        $files_count = 0;
        $this->scan_directory_count(ABSPATH, $files_count);
        
        // Cache for 1 hour
        set_transient($transient_key, $files_count, HOUR_IN_SECONDS);
        
        return $files_count;
    }

    /**
     * Count files in directory recursively
     *
     * @param string $dir Directory path
     * @param int $count Counter variable passed by reference
     */
    private function scan_directory_count($dir, &$count) {
        // Get excluded directories
        $excluded_dirs = $this->compatibility->get_excluded_directories();
        
        // Get included file types
        $included_types = $this->compatibility->get_included_file_types();
        
        // Check if directory should be excluded
        foreach ($excluded_dirs as $excluded_dir) {
            if (strpos($dir, $excluded_dir) !== false) {
                return;
            }
        }
        
        // Check if we should scan this directory based on custom directory settings
        if (!$this->should_scan_directory($dir)) {
            return;
        }
        
        // Open directory
        $handle = opendir($dir);
        if (!$handle) {
            return;
        }
        
        // Iterate through files
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $path = trailingslashit($dir) . $file;
            
            if (is_dir($path)) {
                // Recursively scan subdirectories
                $this->scan_directory_count($path, $count);
            } elseif (is_file($path)) {
                // Check if file type is included
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($extension, $included_types)) {
                    $count++;
                }
            }
        }
        
        closedir($handle);
    }

    /**
     * Get files chunk for processing
     *
     * @param int $offset Offset
     * @param int $limit Limit
     * @return array Files in chunk
     */
    public function get_files_chunk($offset, $limit) {
        $transient_key = 'keycdn_push_enabler_files_list';
        $file_list = get_transient($transient_key);
        
        // Build the full file list if not cached
        if ($file_list === false) {
            $file_list = array();
            $this->scan_directory(ABSPATH, $file_list);
            
            // Cache for 1 hour
            set_transient($transient_key, $file_list, HOUR_IN_SECONDS);
        }
        
        return array_slice($file_list, $offset, $limit);
    }

    /**
     * Scan directory and add matching files to the list
     *
     * @param string $dir Directory path
     * @param array $file_list File list passed by reference
     */
    private function scan_directory($dir, &$file_list) {
        // Get excluded directories
        $excluded_dirs = $this->compatibility->get_excluded_directories();
        
        // Get included file types
        $included_types = $this->compatibility->get_included_file_types();
        
        // Check if directory should be excluded
        foreach ($excluded_dirs as $excluded_dir) {
            if (strpos($dir, $excluded_dir) !== false) {
                return;
            }
        }
        
        // Check if we should scan this directory based on custom directory settings
        if (!$this->should_scan_directory($dir)) {
            return;
        }
        
        // Open directory
        $handle = opendir($dir);
        if (!$handle) {
            return;
        }
        
        // Iterate through files
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $path = trailingslashit($dir) . $file;
            
            if (is_dir($path)) {
                // Recursively scan subdirectories
                $this->scan_directory($path, $file_list);
            } elseif (is_file($path)) {
                // Check if file type is included
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($extension, $included_types)) {
                    $file_list[] = array(
                        'path' => $path,
                        'relative_path' => str_replace(ABSPATH, '', $path)
                    );
                }
            }
        }
        
        closedir($handle);
    }
    
    /**
     * Check if a directory should be scanned based on custom directory settings
     *
     * @param string $dir Directory path
     * @return bool True if directory should be scanned, false otherwise
     */
    private function should_scan_directory($dir) {
        $relative_dir = str_replace(ABSPATH, '', $dir);
        $relative_dir = trailingslashit($relative_dir);
        
        // Get custom directories setting
        $options = get_option('keycdn_push_enabler', array());
        $include_default_upload_dir = isset($options['include_default_upload_dir']) ? $options['include_default_upload_dir'] : true;
        $custom_directories = isset($options['custom_directories']) ? $options['custom_directories'] : array();
        
        // If no custom directories are set, use default behavior
        if (empty($custom_directories) && $include_default_upload_dir) {
            return true;
        }
        
        // Check if this directory is the default upload directory or a subdirectory of it
        $upload_dir = wp_upload_dir();
        $relative_upload_dir = str_replace(ABSPATH, '', $upload_dir['basedir']);
        $relative_upload_dir = trailingslashit($relative_upload_dir);
        
        if ($include_default_upload_dir && strpos($relative_dir, $relative_upload_dir) === 0) {
            return true;
        }
        
        // Check if this directory is one of the custom directories or a subdirectory of one
        foreach ($custom_directories as $custom_dir => $enabled) {
            if (!$enabled) {
                continue;
            }
            
            $custom_dir = trailingslashit($custom_dir);
            
            if (strpos($relative_dir, $custom_dir) === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Clear file cache
     */
    public function clear_file_cache() {
        delete_transient('keycdn_push_enabler_files_count');
        delete_transient('keycdn_push_enabler_files_list');
    }
}