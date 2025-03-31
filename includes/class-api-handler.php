<?php
/**
 * API handler for KeyCDN Push Zone Addon
 *
 * @package KeyCDN_Push_Enabler_Addon
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle API communication with KeyCDN
 */
class KeyCDN_Push_Enabler_Addon_API_Handler {
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;

    /**
     * Constructor
     *
     * @param array $options Plugin options
     */
    public function __construct($options) {
        $this->options = $options;
    }

    /**
     * Get API key with support for constants and environment variables
     *
     * @return string API key
     */
    public function get_api_key() {
        // Check for constant first
        if (defined('KEYCDN_API_KEY') && !empty(KEYCDN_API_KEY)) {
            return KEYCDN_API_KEY;
        }
        
        // Check for environment variable
        $env_api_key = getenv('KEYCDN_API_KEY');
        if (!empty($env_api_key)) {
            return $env_api_key;
        }
        
        // Fall back to database option
        return isset($this->options['api_key']) ? $this->options['api_key'] : '';
    }

    /**
     * Get Push Zone ID with support for constants and environment variables
     *
     * @return string Push Zone ID
     */
    public function get_push_zone_id() {
        // Check for constant first
        if (defined('KEYCDN_PUSH_ZONE_ID') && !empty(KEYCDN_PUSH_ZONE_ID)) {
            return KEYCDN_PUSH_ZONE_ID;
        }
        
        // Check for environment variable
        $env_zone_id = getenv('KEYCDN_PUSH_ZONE_ID');
        if (!empty($env_zone_id)) {
            return $env_zone_id;
        }
        
        // Fall back to database option
        return isset($this->options['push_zone_id']) ? $this->options['push_zone_id'] : '';
    }

    /**
     * Check if API settings are configured
     *
     * @return bool True if API settings are configured, false otherwise
     */
    public function is_api_configured() {
        return !empty($this->get_api_key()) && !empty($this->get_push_zone_id());
    }

    /**
     * Get authorization header
     *
     * @return string Authorization header
     */
    private function get_authorization_header() {
        return 'Basic ' . base64_encode($this->get_api_key() . ':');
    }

    /**
     * Check rate limit before making API request
     *
     * @return bool True if request is allowed, false if rate limited
     */
    private function check_rate_limit() {
        $rate_limit_option = 'keycdn_push_enabler_rate_limit';
        $current_time = time();
        
        // Get existing rate limit data
        $rate_limit_data = get_option($rate_limit_option, array(
            'count' => 0,
            'timestamp' => $current_time
        ));
        
        // Reset counter if more than a minute has passed
        if ($current_time - $rate_limit_data['timestamp'] > 60) {
            $rate_limit_data = array(
                'count' => 1,
                'timestamp' => $current_time
            );
            update_option($rate_limit_option, $rate_limit_data);
            return true;
        }
        
        // Check if we've exceeded the limit (60 requests per minute)
        if ($rate_limit_data['count'] >= 60) {
            $this->log_error('API rate limit exceeded. Try again later.');
            return false;
        }
        
        // Increment the counter
        $rate_limit_data['count']++;
        update_option($rate_limit_option, $rate_limit_data);
        
        return true;
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
                $value = str_replace(ABSPATH, '[WORDPRESS_ROOT]/', $value);
            }
            
            $safe_context[$key] = $value;
        }
        
        // Use WordPress error logging
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log('KeyCDN Push Addon Error: ' . $message . ' ' . json_encode($safe_context));
        }
    }

    /**
     * Push a file to KeyCDN via API
     *
     * @param string $file_path The full file path
     * @param string $relative_path The path relative to uploads directory
     * @return bool True on success, false on failure
     */
    public function push_file($file_path, $relative_path) {
        if (!file_exists($file_path)) {
            $this->log_error("File does not exist", array('path' => $file_path));
            return false;
        }

        if (!$this->is_api_configured()) {
            $this->log_error("API not configured");
            return false;
        }

        if (!$this->check_rate_limit()) {
            return false;
        }

        // KeyCDN API endpoint for file upload
        $api_url = 'https://api.keycdn.com/zones/pushfiles/' . $this->get_push_zone_id() . '.json';
        
        // Prepare file for upload
        $file_contents = file_get_contents($file_path);
        $file_name = basename($file_path);
        
        // Get file mime type
        $mime_type = mime_content_type($file_path);
        
        // Prepare the request
        $boundary = wp_generate_password(24, false);
        $headers = array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            'Authorization' => $this->get_authorization_header()
        );
        
        // Build multipart body
        $body = '';
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . $file_name . '"' . "\r\n";
        $body .= 'Content-Type: ' . $mime_type . "\r\n\r\n";
        $body .= $file_contents . "\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="destination"' . "\r\n\r\n";
        $body .= dirname($relative_path) . "\r\n";
        $body .= '--' . $boundary . '--';
        
        // Send request to KeyCDN API
        $response = wp_remote_post($api_url, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => $headers,
            'body' => $body,
            'cookies' => array()
        ));
        
        if (is_wp_error($response)) {
            $this->log_error('KeyCDN Push Error', array('error' => $response->get_error_message()));
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $this->log_error('KeyCDN Push Error', array(
                'code' => $response_code,
                'response' => wp_remote_retrieve_body($response)
            ));
            return false;
        }
        
        return true;
    }

    /**
     * Purge URLs from KeyCDN cache
     *
     * @param array $urls URLs to purge
     * @return bool True on success, false on failure
     */
    public function purge_urls($urls) {
        if (!$this->is_api_configured()) {
            $this->log_error("API not configured");
            return false;
        }

        if (!$this->check_rate_limit()) {
            return false;
        }

        if (empty($urls)) {
            return true;
        }

        // KeyCDN API endpoint for purging URLs
        $api_url = 'https://api.keycdn.com/zones/purgeurl/' . $this->get_push_zone_id() . '.json';
        
        // Prepare the request
        $headers = array(
            'Authorization' => $this->get_authorization_header()
        );
        
        // Send request to KeyCDN API
        $response = wp_remote_post($api_url, array(
            'method' => 'DELETE',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => $headers,
            'body' => array('urls' => $urls),
            'cookies' => array()
        ));
        
        if (is_wp_error($response)) {
            $this->log_error('KeyCDN Purge Error', array('error' => $response->get_error_message()));
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $this->log_error('KeyCDN Purge Error', array(
                'code' => $response_code,
                'response' => wp_remote_retrieve_body($response)
            ));
            return false;
        }
        
        return true;
    }

    /**
     * Purge a single URL from KeyCDN cache
     *
     * @param string $url URL to purge
     * @return bool True on success, false on failure
     */
    public function purge_url($url) {
        return $this->purge_urls(array($url));
    }

    /**
     * Purge entire zone cache
     *
     * @return bool True on success, false on failure
     */
    public function purge_zone_cache() {
        if (!$this->is_api_configured()) {
            $this->log_error("API not configured");
            return false;
        }

        if (!$this->check_rate_limit()) {
            return false;
        }

        // KeyCDN API endpoint for purging zone
        $api_url = 'https://api.keycdn.com/zones/purge/' . $this->get_push_zone_id() . '.json';
        
        // Prepare the request
        $headers = array(
            'Authorization' => $this->get_authorization_header()
        );
        
        // Send request to KeyCDN API
        $response = wp_remote_get($api_url, array(
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => $headers,
            'cookies' => array()
        ));
        
        if (is_wp_error($response)) {
            $this->log_error('KeyCDN Purge Zone Error', array('error' => $response->get_error_message()));
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $this->log_error('KeyCDN Purge Zone Error', array(
                'code' => $response_code,
                'response' => wp_remote_retrieve_body($response)
            ));
            return false;
        }
        
        return true;
    }
}