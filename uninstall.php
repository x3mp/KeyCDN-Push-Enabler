<?php
/**
 * Uninstall file for KeyCDN Push Zone Addon
 *
 * @package KeyCDN_Push_Enabler_Addon
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options
delete_option('keycdn_push_enabler');
delete_option('keycdn_push_enabler_progress');
delete_option('keycdn_push_enabler_total_files');
delete_option('keycdn_push_enabler_processed_files');

// Delete transients
delete_transient('keycdn_push_enabler_pushing_files');
delete_transient('keycdn_push_enabler_processing_chunk');
delete_transient('keycdn_push_enabler_files_count');
delete_transient('keycdn_push_enabler_files_list');
delete_transient('keycdn_push_enabler_rate_limit');

// Clear scheduled tasks
wp_clear_scheduled_hook('keycdn_push_enabler_push_static_files');
wp_clear_scheduled_hook('keycdn_push_enabler_process_file_chunk');

// Remove custom capabilities
$admin_role = get_role('administrator');
if ($admin_role) {
    $admin_role->remove_cap('manage_keycdn_push');
}