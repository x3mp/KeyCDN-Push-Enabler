<?php
/**
 * Cron Tasks handler for KeyCDN Push Zone Addon
 *
 * @package KeyCDN_Push_Enabler_Addon
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle background processing via WordPress cron
 */
class KeyCDN_Push_Enabler_Addon_Cron_Tasks {
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
     * Constructor
     *
     * @param KeyCDN_Push_Enabler_Addon_API_Handler $api_handler API handler
     * @param KeyCDN_Push_Enabler_Addon_File_Handler $file_handler File handler
     */
    public function __construct($api_handler, $file_handler) {
        $this->api_handler = $api_handler;
        $this->file_handler = $file_handler;

        // Register hooks
        $this->register_hooks();
    }

    /**
     * Register hooks
     */
    private function register_hooks() {
        // Register cron hooks
        add_action('keycdn_push_enabler_push_static_files', array($this, 'handle_push_static_files'));
        add_action('keycdn_push_enabler_process_file_chunk', array($this, 'process_file_chunk'));

        // Plugin/theme update hook
        add_action('upgrader_process_complete', array($this, 'handle_upgrader_process_complete'), 10, 2);
    }

    /**
     * Handle upgrader process complete
     * 
     * @param object $upgrader WP_Upgrader instance
     * @param array $options Update options
     */
    public function handle_upgrader_process_complete($upgrader, $options) {
        // Check if this is a theme or plugin update
        if (!isset($options['type']) || !in_array($options['type'], array('theme', 'plugin'))) {
            return;
        }
        
        // Get options
        $push_static_files = get_option('keycdn_push_enabler');
        $push_static_files = isset($push_static_files['push_static_files']) ? $push_static_files['push_static_files'] : false;
        
        // Check if push is enabled in settings
        if (!$push_static_files || !$this->api_handler->is_api_configured()) {
            return;
        }
        
        // Schedule push
        $this->schedule_push_static_files();
    }

    /**
     * Schedule push static files
     */
    public function schedule_push_static_files() {
        // Check if already scheduled
        if (wp_next_scheduled('keycdn_push_enabler_push_static_files')) {
            return;
        }

        // Schedule the task
        wp_schedule_single_event(time(), 'keycdn_push_enabler_push_static_files');

        // Set transient to indicate pushing is in progress
        set_transient('keycdn_push_enabler_pushing_files', true, 3600); // 1 hour timeout
    }

    /**
     * Handle pushing all static files
     */
    public function handle_push_static_files() {
        // Make sure we're not already processing files
        if (get_transient('keycdn_push_enabler_processing_chunk')) {
            return;
        }

        // Clear previous progress
        delete_option('keycdn_push_enabler_processed_files');
        
        // Get total number of files to process
        $files_to_process = $this->file_handler->get_files_count();
        
        // Store total in an option for progress tracking
        update_option('keycdn_push_enabler_total_files', $files_to_process);
        update_option('keycdn_push_enabler_processed_files', 0);
        
        // Schedule the first chunk
        wp_schedule_single_event(time(), 'keycdn_push_enabler_process_file_chunk', array(0));
    }

    /**
     * Process a chunk of files
     * 
     * @param int $offset The offset to start from
     */
    public function process_file_chunk($offset) {
        // Set a transient to prevent overlapping processes
        set_transient('keycdn_push_enabler_processing_chunk', true, 300); // 5 minute timeout
        
        $chunk_size = 20; // Process 20 files at a time
        
        // Get files for this chunk
        $files = $this->file_handler->get_files_chunk($offset, $chunk_size);
        
        if (empty($files)) {
            // No more files to process, clean up
            delete_transient('keycdn_push_enabler_pushing_files');
            delete_transient('keycdn_push_enabler_processing_chunk');
            return;
        }
        
        // Process files in this chunk
        $success_count = 0;
        
        foreach ($files as $file) {
            $result = $this->file_handler->push_file($file['path'], $file['relative_path']);
            
            if ($result) {
                $success_count++;
            }
            
            // Update progress
            $processed = intval(get_option('keycdn_push_enabler_processed_files', 0));
            update_option('keycdn_push_enabler_processed_files', $processed + 1);
            
            // Small delay to prevent overwhelming the API
            usleep(100000); // 100ms delay
        }
        
        // Set progress
        $total = intval(get_option('keycdn_push_enabler_total_files', 0));
        $processed = intval(get_option('keycdn_push_enabler_processed_files', 0));
        $progress = array(
            'total' => $total,
            'processed' => $processed,
            'percentage' => $total > 0 ? round(($processed / $total) * 100) : 0,
            'last_update' => time()
        );
        
        update_option('keycdn_push_enabler_progress', $progress);
        
        // Clear processing chunk transient
        delete_transient('keycdn_push_enabler_processing_chunk');
        
        // Schedule next chunk
        $next_offset = $offset + $chunk_size;
        wp_schedule_single_event(time() + 5, 'keycdn_push_enabler_process_file_chunk', array($next_offset));
    }

    /**
     * Get current push progress
     *
     * @return array Progress information
     */
    public function get_push_progress() {
        $progress = get_option('keycdn_push_enabler_progress', array(
            'total' => 0,
            'processed' => 0,
            'percentage' => 0,
            'last_update' => 0
        ));
        
        $is_pushing = get_transient('keycdn_push_enabler_pushing_files');
        $is_processing_chunk = get_transient('keycdn_push_enabler_processing_chunk');
        
        $progress['is_active'] = ($is_pushing !== false);
        $progress['is_processing'] = ($is_processing_chunk !== false);
        
        // Check if process may be stalled
        if ($progress['is_active'] && $progress['last_update'] > 0) {
            $time_since_update = time() - $progress['last_update'];
            $progress['stalled'] = ($time_since_update > 300); // 5 minutes with no update
        } else {
            $progress['stalled'] = false;
        }
        
        return $progress;
    }

    /**
     * Reset push process
     */
    public function reset_push_process() {
        // Clear scheduled events
        wp_clear_scheduled_hook('keycdn_push_enabler_push_static_files');
        wp_clear_scheduled_hook('keycdn_push_enabler_process_file_chunk');
        
        // Clear transients
        delete_transient('keycdn_push_enabler_pushing_files');
        delete_transient('keycdn_push_enabler_processing_chunk');
        
        // Clear options
        delete_option('keycdn_push_enabler_progress');
        delete_option('keycdn_push_enabler_total_files');
        delete_option('keycdn_push_enabler_processed_files');
        
        // Clear file cache
        $this->file_handler->clear_file_cache();
    }
}