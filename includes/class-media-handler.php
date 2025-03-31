<?php
/**
 * Media handler for KeyCDN Push Zone Addon
 *
 * @package KeyCDN_Push_Enabler_Addon
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle media operations
 */
class KeyCDN_Push_Enabler_Addon_Media_Handler {
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
        // Media hooks
        add_action('add_attachment', array($this, 'push_attachment'));
        add_action('edit_attachment', array($this, 'push_attachment'));
        add_action('delete_attachment', array($this, 'delete_attachment'));
    }

    /**
     * Push attachment to CDN
     *
     * @param int $attachment_id Attachment ID
     */
    public function push_attachment($attachment_id) {
        // Check if API is configured
        if (!$this->api_handler->is_api_configured()) {
            return;
        }

        // Get file path
        $file_path = get_attached_file($attachment_id);
        if (!file_exists($file_path)) {
            return;
        }

        // Get relative path to upload directory
        $upload_dir = wp_upload_dir();
        $relative_path = str_replace($upload_dir['basedir'] . '/', '', $file_path);

        // Push file to KeyCDN
        $this->file_handler->push_file($file_path, $relative_path);

        // If this is an image, also push the various sizes
        if (wp_attachment_is_image($attachment_id)) {
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                $upload_dir_path = $upload_dir['basedir'] . '/' . dirname($metadata['file']) . '/';
                
                foreach ($metadata['sizes'] as $size => $size_info) {
                    $size_file_path = $upload_dir_path . $size_info['file'];
                    $size_relative_path = dirname($relative_path) . '/' . $size_info['file'];
                    
                    $this->file_handler->push_file($size_file_path, $size_relative_path);
                }
            }
        }
    }

    /**
     * Delete attachment from CDN
     *
     * @param int $attachment_id Attachment ID
     */
    public function delete_attachment($attachment_id) {
        // Check if API is configured
        if (!$this->api_handler->is_api_configured()) {
            return;
        }

        // Get file path
        $file_path = get_attached_file($attachment_id);
        
        // Get relative path to upload directory
        $upload_dir = wp_upload_dir();
        $relative_path = str_replace($upload_dir['basedir'] . '/', '', $file_path);

        // Delete file from KeyCDN
        $this->file_handler->delete_file($relative_path);

        // If this is an image, also delete the various sizes
        if (wp_attachment_is_image($attachment_id)) {
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size => $size_info) {
                    $size_relative_path = dirname($relative_path) . '/' . $size_info['file'];
                    $this->file_handler->delete_file($size_relative_path);
                }
            }
        }
    }

    /**
     * Get attachments for pushing
     *
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Attachments
     */
    public function get_attachments_for_pushing($limit = 20, $offset = 0) {
        // Query attachments
        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => array(
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
                'application/pdf',
                'text/css', 'text/javascript',
                'application/javascript',
                'audio/mpeg', 'audio/mp3', 'audio/wav',
                'video/mp4', 'video/webm'
            ),
            'posts_per_page' => $limit,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC'
        );
        
        $query = new WP_Query($args);
        
        return $query->posts;
    }

    /**
     * Get total attachments count
     *
     * @return int Total attachments
     */
    public function get_total_attachments() {
        // Query attachments
        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => array(
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
                'application/pdf',
                'text/css', 'text/javascript',
                'application/javascript',
                'audio/mpeg', 'audio/mp3', 'audio/wav',
                'video/mp4', 'video/webm'
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        $query = new WP_Query($args);
        
        return $query->found_posts;
    }

    /**
     * Push media in batches
     *
     * @param int $batch_size Batch size
     * @param int $offset Offset
     * @return array Status information
     */
    public function push_media_batch($batch_size = 20, $offset = 0) {
        // Get a batch of attachments
        $attachments = $this->get_attachments_for_pushing($batch_size, $offset);
        
        $results = array(
            'processed' => 0,
            'success' => 0,
            'errors' => 0
        );
        
        // Process each attachment
        foreach ($attachments as $attachment) {
            $results['processed']++;
            
            try {
                $this->push_attachment($attachment->ID);
                $results['success']++;
            } catch (Exception $e) {
                $results['errors']++;
            }
        }
        
        // Return results and next offset
        return array(
            'results' => $results,
            'next_offset' => $offset + $batch_size,
            'has_more' => count($attachments) >= $batch_size
        );
    }
}