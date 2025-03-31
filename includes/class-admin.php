<?php
/**
 * Admin interface for KeyCDN Push Zone Addon
 *
 * @package KeyCDN_Push_Enabler_Addon
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle admin interface
 */
class KeyCDN_Push_Enabler_Addon_Admin {
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;

    /**
     * API handler
     *
     * @var KeyCDN_Push_Enabler_Addon_API_Handler
     */
    private $api_handler;

    /**
     * Cron tasks handler
     *
     * @var KeyCDN_Push_Enabler_Addon_Cron_Tasks
     */
    private $cron_tasks;
    
    /**
     * File handler
     *
     * @var KeyCDN_Push_Enabler_Addon_File_Handler
     */
    private $file_handler;

    /**
     * Constructor
     *
     * @param array $options Plugin options
     * @param KeyCDN_Push_Enabler_Addon_API_Handler $api_handler API handler
     * @param KeyCDN_Push_Enabler_Addon_Cron_Tasks $cron_tasks Cron tasks handler
     * @param KeyCDN_Push_Enabler_Addon_File_Handler $file_handler File handler
     */
    public function __construct($options, $api_handler, $cron_tasks, $file_handler) {
        $this->options = $options;
        $this->api_handler = $api_handler;
        $this->cron_tasks = $cron_tasks;
        $this->file_handler = $file_handler;

        // Initialize admin hooks
        $this->init();
    }

    /**
     * Initialize admin hooks
     */
    public function init() {
        // Add menu item
        add_action('admin_menu', array($this, 'add_menu_page'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . KEYCDN_PUSH_ENABLER_BASENAME, array($this, 'add_settings_link'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Add action for purging cache
        add_action('admin_post_keycdn_push_enabler_purge_cache', array($this, 'purge_cache'));
        
        // Add action for pushing all files
        add_action('admin_post_keycdn_push_enabler_push_all_files', array($this, 'push_all_files'));
        
        // Add action for resetting push process
        add_action('admin_post_keycdn_push_enabler_reset_push', array($this, 'reset_push_process'));
        
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add AJAX handlers
        add_action('wp_ajax_keycdn_push_enabler_get_progress', array($this, 'ajax_get_progress'));
    }

    /**
     * Add menu page
     */
    public function add_menu_page() {
        add_options_page(
            'KeyCDN Push Enabler Addon', 
            'KeyCDN Push Enabler', 
            'manage_options', 
            'keycdn-push-enabler', 
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('keycdn_push_enabler', 'keycdn_push_enabler', array($this, 'validate_options'));
        
        // KeyCDN API settings section
        add_settings_section(
            'keycdn_push_enabler_api',
            __('KeyCDN API Settings', 'keycdn-push-enabler'),
            array($this, 'render_api_section'),
            'keycdn-push-enabler'
        );
        
        add_settings_field(
            'api_key',
            __('API Key', 'keycdn-push-enabler'),
            array($this, 'render_text_field'),
            'keycdn-push-enabler',
            'keycdn_push_enabler_api',
            array(
                'id' => 'api_key',
                'label' => __('Your KeyCDN API key', 'keycdn-push-enabler')
            )
        );
        
        // Push Zone settings section
        add_settings_section(
            'keycdn_push_enabler_push_zone',
            __('Push Zone Settings', 'keycdn-push-enabler'),
            array($this, 'render_push_zone_section'),
            'keycdn-push-enabler'
        );
        
        add_settings_field(
            'push_zone_id',
            __('Push Zone ID', 'keycdn-push-enabler'),
            array($this, 'render_text_field'),
            'keycdn-push-enabler',
            'keycdn_push_enabler_push_zone',
            array(
                'id' => 'push_zone_id',
                'label' => __('Your KeyCDN Push Zone ID (e.g., 12345)', 'keycdn-push-enabler')
            )
        );
        
        add_settings_field(
            'push_static_files',
            __('Push Static Files', 'keycdn-push-enabler'),
            array($this, 'render_checkbox_field'),
            'keycdn-push-enabler',
            'keycdn_push_enabler_push_zone',
            array(
                'id' => 'push_static_files',
                'label' => __('Automatically push static files when themes or plugins are updated', 'keycdn-push-enabler')
            )
        );
        
        add_settings_field(
            'push_on_settings_update',
            __('Push on Settings Update', 'keycdn-push-enabler'),
            array($this, 'render_checkbox_field'),
            'keycdn-push-enabler',
            'keycdn_push_enabler_push_zone',
            array(
                'id' => 'push_on_settings_update',
                'label' => __('Push all files when settings are updated', 'keycdn-push-enabler')
            )
        );
        
        // Directory Settings section
        add_settings_section(
            'keycdn_push_enabler_directories',
            __('Directory Settings', 'keycdn-push-enabler'),
            array($this, 'render_directories_section'),
            'keycdn-push-enabler'
        );
        
        add_settings_field(
            'include_default_upload_dir',
            __('Default Upload Directory', 'keycdn-push-enabler'),
            array($this, 'render_checkbox_field'),
            'keycdn-push-enabler',
            'keycdn_push_enabler_directories',
            array(
                'id' => 'include_default_upload_dir',
                'label' => __('Include default WordPress upload directory', 'keycdn-push-enabler')
            )
        );
        
        add_settings_field(
            'custom_directories',
            __('Custom Directories', 'keycdn-push-enabler'),
            array($this, 'render_custom_directories_field'),
            'keycdn-push-enabler',
            'keycdn_push_enabler_directories',
            array(
                'id' => 'custom_directories',
                'label' => __('Specify additional directories to push (relative to WordPress root)', 'keycdn-push-enabler')
            )
        );
    }

    /**
     * Validate options
     * 
     * @param array $input The input options
     * @return array The validated options
     */
    public function validate_options($input) {
        $output = array();
        
        // API settings
        $output['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';
        
        // Push Zone settings
        $output['push_zone_id'] = isset($input['push_zone_id']) ? sanitize_text_field($input['push_zone_id']) : '';
        $output['push_static_files'] = isset($input['push_static_files']) ? (bool) $input['push_static_files'] : false;
        $output['push_on_settings_update'] = isset($input['push_on_settings_update']) ? (bool) $input['push_on_settings_update'] : false;
        
        // Directory settings
        $output['include_default_upload_dir'] = isset($input['include_default_upload_dir']) ? (bool) $input['include_default_upload_dir'] : true;
        
        // Custom directories
        $output['custom_directories'] = array();
        
        // Process custom directories from keys array to maintain order
        if (isset($input['custom_directories_keys']) && is_array($input['custom_directories_keys'])) {
            foreach ($input['custom_directories_keys'] as $directory) {
                $directory = sanitize_text_field($directory);
                $enabled = isset($input['custom_directories'][$directory]) ? (bool) $input['custom_directories'][$directory] : false;
                $output['custom_directories'][$directory] = $enabled;
            }
        }
        
        // If custom directories were submitted directly
        if (isset($input['custom_directories']) && is_array($input['custom_directories'])) {
            foreach ($input['custom_directories'] as $directory => $enabled) {
                // Skip if already processed through keys array
                if (isset($output['custom_directories'][$directory])) {
                    continue;
                }
                
                $directory = sanitize_text_field($directory);
                $output['custom_directories'][$directory] = (bool) $enabled;
            }
        }
        
        // Check if any settings have changed that would require a push
        $settings_changed = false;
        
        if ($output['api_key'] !== $this->options['api_key'] || 
            $output['push_zone_id'] !== $this->options['push_zone_id']) {
            $settings_changed = true;
        }
        
        // Check if directory settings changed
        $old_include_default = isset($this->options['include_default_upload_dir']) ? $this->options['include_default_upload_dir'] : true;
        $old_custom_directories = isset($this->options['custom_directories']) ? $this->options['custom_directories'] : array();
        
        if ($output['include_default_upload_dir'] !== $old_include_default) {
            $settings_changed = true;
        }
        
        if (count($output['custom_directories']) !== count($old_custom_directories)) {
            $settings_changed = true;
        } else {
            foreach ($output['custom_directories'] as $directory => $enabled) {
                if (!isset($old_custom_directories[$directory]) || $old_custom_directories[$directory] !== $enabled) {
                    $settings_changed = true;
                    break;
                }
            }
        }
        
        // Schedule push if needed
        if ($output['push_on_settings_update'] && $settings_changed) {
            // Clear file cache to ensure new directory settings are used
            $this->file_handler->clear_file_cache();
            
            // Schedule push all files
            $this->cron_tasks->schedule_push_static_files();
        }
        
        return $output;
    }
        

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Get push progress
        $progress = $this->cron_tasks->get_push_progress();
        ?>
        <div class="wrap">
            <h1><?php _e('KeyCDN Push Zone Addon Settings', 'keycdn-push-enabler'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php _e('This plugin extends CDN Enabler with Push Zone functionality for KeyCDN.', 'keycdn-push-enabler'); ?></p>
            </div>
            
            <?php if ($progress['is_active']): ?>
            <div class="keycdn-push-enabler-progress-wrapper">
                <h2><?php _e('File Push Progress', 'keycdn-push-enabler'); ?></h2>
                <div class="keycdn-push-enabler-progress">
                    <div class="keycdn-push-enabler-progress-bar" style="width: <?php echo esc_attr($progress['percentage']); ?>%"></div>
                </div>
                <div class="keycdn-push-enabler-progress-info">
                    <p>
                        <?php printf(
                            __('Processed %1$d of %2$d files (%3$d%%)', 'keycdn-push-enabler'),
                            $progress['processed'],
                            $progress['total'],
                            $progress['percentage']
                        ); ?>
                    </p>
                    <?php if ($progress['stalled']): ?>
                    <p class="keycdn-push-enabler-stalled">
                        <?php _e('The process appears to be stalled. You may want to reset it.', 'keycdn-push-enabler'); ?>
                    </p>
                    <?php endif; ?>
                    <p>
                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=keycdn_push_enabler_reset_push'), 'keycdn_push_enabler_reset_push'); ?>" class="button button-secondary">
                            <?php _e('Reset Push Process', 'keycdn-push-enabler'); ?>
                        </a>
                    </p>
                </div>
            </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('keycdn_push_enabler');
                do_settings_sections('keycdn-push-enabler');
                submit_button();
                ?>
            </form>
            
            <div class="keycdn-push-enabler-actions">
                <h2><?php _e('Actions', 'keycdn-push-enabler'); ?></h2>
                <p>
                    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=keycdn_push_enabler_purge_cache'), 'keycdn_push_enabler_purge_cache'); ?>" class="button button-secondary">
                        <?php _e('Purge CDN Cache', 'keycdn-push-enabler'); ?>
                    </a>
                    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=keycdn_push_enabler_push_all_files'), 'keycdn_push_enabler_push_all_files'); ?>" class="button button-secondary" <?php echo $progress['is_active'] ? 'disabled' : ''; ?>>
                        <?php _e('Push All Files', 'keycdn-push-enabler'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render API section
     */
    public function render_api_section() {
        echo '<p>' . __('Configure your KeyCDN API credentials.', 'keycdn-push-enabler') . '</p>';
        
        // Show notice if using environment variables or constants
        if (defined('KEYCDN_API_KEY') || getenv('KEYCDN_API_KEY')) {
            echo '<div class="notice notice-info inline"><p>';
            _e('API Key is being loaded from a constant or environment variable.', 'keycdn-push-enabler');
            echo '</p></div>';
        }
        
        if (defined('KEYCDN_PUSH_ZONE_ID') || getenv('KEYCDN_PUSH_ZONE_ID')) {
            echo '<div class="notice notice-info inline"><p>';
            _e('Push Zone ID is being loaded from a constant or environment variable.', 'keycdn-push-enabler');
            echo '</p></div>';
        }
    }

    /**
     * Render push zone section
     */
    public function render_push_zone_section() {
        echo '<p>' . __('Configure your Push Zone settings.', 'keycdn-push-enabler') . '</p>';
    }
    
    /**
     * Render directories section
     */
    public function render_directories_section() {
        echo '<p>' . __('Configure which directories should be pushed to KeyCDN.', 'keycdn-push-enabler') . '</p>';
    }
    
    /**
     * Render custom directories field
     * 
     * @param array $args Field arguments
     */
    public function render_custom_directories_field($args) {
        $id = $args['id'];
        $label = $args['label'];
        $custom_directories = isset($this->options[$id]) ? $this->options[$id] : array();
        
        echo '<div class="keycdn-push-enabler-custom-directories">';
        echo '<p class="description">' . esc_html($label) . '</p>';
        
        // Existing directories
        if (!empty($custom_directories)) {
            echo '<div class="keycdn-push-enabler-existing-directories">';
            foreach ($custom_directories as $directory => $enabled) {
                $this->render_directory_row($directory, $enabled);
            }
            echo '</div>';
        }
        
        // Add new directory
        echo '<div class="keycdn-push-enabler-add-directory">';
        echo '<input type="text" id="keycdn-push-enabler-new-directory" class="regular-text" placeholder="' . esc_attr__('e.g., wp-content/custom-uploads', 'keycdn-push-enabler') . '" />';
        echo ' <button type="button" class="button" id="keycdn-push-enabler-add-directory-btn">' . __('Add Directory', 'keycdn-push-enabler') . '</button>';
        echo '</div>';
        
        echo '</div>';
        
        // JavaScript for adding/removing directories
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Add directory
            $('#keycdn-push-enabler-add-directory-btn').on('click', function() {
                var directory = $('#keycdn-push-enabler-new-directory').val().trim();
                if (directory) {
                    // Check if directory already exists
                    if ($('.keycdn-push-enabler-existing-directories input[value="' + directory + '"]').length) {
                        alert('<?php echo esc_js(__('This directory already exists in the list.', 'keycdn-push-enabler')); ?>');
                        return;
                    }
                    
                    // Add directory to list
                    var html = '<div class="keycdn-push-enabler-directory-row">';
                    html += '<input type="checkbox" name="keycdn_push_enabler[custom_directories][' + directory + ']" value="1" checked="checked" />';
                    html += ' <input type="hidden" name="keycdn_push_enabler[custom_directories_keys][]" value="' + directory + '" />';
                    html += ' <span class="directory-path">' + directory + '</span>';
                    html += ' <a href="#" class="keycdn-push-enabler-remove-directory"><?php echo esc_js(__('Remove', 'keycdn-push-enabler')); ?></a>';
                    html += '</div>';
                    
                    $('.keycdn-push-enabler-existing-directories').append(html);
                    $('#keycdn-push-enabler-new-directory').val('');
                }
            });
            
            // Remove directory (for both existing and newly added rows)
            $(document).on('click', '.keycdn-push-enabler-remove-directory', function(e) {
                e.preventDefault();
                $(this).closest('.keycdn-push-enabler-directory-row').remove();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render directory row
     * 
     * @param string $directory Directory path
     * @param bool $enabled Whether the directory is enabled
     */
    private function render_directory_row($directory, $enabled) {
        echo '<div class="keycdn-push-enabler-directory-row">';
        echo '<input type="checkbox" name="keycdn_push_enabler[custom_directories][' . esc_attr($directory) . ']" value="1" ' . checked(1, $enabled, false) . ' />';
        echo ' <input type="hidden" name="keycdn_push_enabler[custom_directories_keys][]" value="' . esc_attr($directory) . '" />';
        echo ' <span class="directory-path">' . esc_html($directory) . '</span>';
        echo ' <a href="#" class="keycdn-push-enabler-remove-directory">' . __('Remove', 'keycdn-push-enabler') . '</a>';
        echo '</div>';
    }

    /**
     * Render checkbox field
     * 
     * @param array $args Field arguments
     */
    public function render_checkbox_field($args) {
        $id = $args['id'];
        $label = $args['label'];
        $checked = isset($this->options[$id]) ? $this->options[$id] : false;
        
        echo '<label>';
        echo '<input type="checkbox" id="' . esc_attr($id) . '" name="keycdn_push_enabler[' . esc_attr($id) . ']" value="1" ' . checked(1, $checked, false) . ' />';
        echo ' ' . esc_html($label);
        echo '</label>';
    }

    /**
     * Render text field
     * 
     * @param array $args Field arguments
     */
    public function render_text_field($args) {
        $id = $args['id'];
        $label = $args['label'];
        $value = isset($this->options[$id]) ? $this->options[$id] : '';
        
        // If using constants or environment variables, disable the field
        $disabled = '';
        if ($id === 'api_key' && (defined('KEYCDN_API_KEY') || getenv('KEYCDN_API_KEY'))) {
            $disabled = 'disabled';
            $value = defined('KEYCDN_API_KEY') ? KEYCDN_API_KEY : getenv('KEYCDN_API_KEY');
            $value = substr($value, 0, 5) . '***************';
        } elseif ($id === 'push_zone_id' && (defined('KEYCDN_PUSH_ZONE_ID') || getenv('KEYCDN_PUSH_ZONE_ID'))) {
            $disabled = 'disabled';
            $value = defined('KEYCDN_PUSH_ZONE_ID') ? KEYCDN_PUSH_ZONE_ID : getenv('KEYCDN_PUSH_ZONE_ID');
        }
        
        echo '<input type="text" id="' . esc_attr($id) . '" name="keycdn_push_enabler[' . esc_attr($id) . ']" value="' . esc_attr($value) . '" class="regular-text" ' . $disabled . ' />';
        echo '<p class="description">' . esc_html($label) . '</p>';
    }

    /**
     * Add settings link to plugins page
     * 
     * @param array $links Plugin action links
     * @return array Modified plugin action links
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=keycdn-push-enabler') . '">' . __('Settings', 'keycdn-push-enabler') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        if (isset($_GET['keycdn-cache-purged']) && $_GET['keycdn-cache-purged'] == 1) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('KeyCDN cache purged successfully.', 'keycdn-push-enabler') . '</p></div>';
        }
        
        if (isset($_GET['keycdn-push-started']) && $_GET['keycdn-push-started'] == 1) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Started pushing files to KeyCDN. This may take a while depending on the number of files.', 'keycdn-push-enabler') . '</p></div>';
        }
        
        if (isset($_GET['keycdn-push-reset']) && $_GET['keycdn-push-reset'] == 1) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Push process has been reset.', 'keycdn-push-enabler') . '</p></div>';
        }
        
        if (isset($_GET['keycdn-error']) && !empty($_GET['keycdn-error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($_GET['keycdn-error'])) . '</p></div>';
        }
    }

    /**
     * Purge CDN cache
     */
    public function purge_cache() {
        // Check nonce and permissions
        check_admin_referer('keycdn_push_enabler_purge_cache');
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'keycdn-push-enabler'));
        }
        
        // Check additional authorization
        if (!$this->check_additional_authorization()) {
            wp_die(__('Additional authorization failed.', 'keycdn-push-enabler'));
        }
        
        // Check if API is configured
        if (!$this->api_handler->is_api_configured()) {
            wp_redirect(add_query_arg('keycdn-error', urlencode(__('API key or Push Zone ID not configured.', 'keycdn-push-enabler')), admin_url('options-general.php?page=keycdn-push-enabler')));
            exit;
        }
        
        // Purge cache
        $result = $this->api_handler->purge_zone_cache();
        
        if (!$result) {
            wp_redirect(add_query_arg('keycdn-error', urlencode(__('Error purging cache. Please check your API key and zone settings.', 'keycdn-push-enabler')), admin_url('options-general.php?page=keycdn-push-enabler')));
            exit;
        }
        
        // Redirect back to settings page with success message
        wp_redirect(add_query_arg('keycdn-cache-purged', '1', admin_url('options-general.php?page=keycdn-push-enabler')));
        exit;
    }

    /**
     * Push all files
     */
    public function push_all_files() {
        // Check nonce and permissions
        check_admin_referer('keycdn_push_enabler_push_all_files');
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'keycdn-push-enabler'));
        }
        
        // Check additional authorization
        if (!$this->check_additional_authorization()) {
            wp_die(__('Additional authorization failed.', 'keycdn-push-enabler'));
        }
        
        // Check if API is configured
        if (!$this->api_handler->is_api_configured()) {
            wp_redirect(add_query_arg('keycdn-error', urlencode(__('API key or Push Zone ID not configured.', 'keycdn-push-enabler')), admin_url('options-general.php?page=keycdn-push-enabler')));
            exit;
        }
        
        // Check if already pushing
        $progress = $this->cron_tasks->get_push_progress();
        if ($progress['is_active']) {
            wp_redirect(add_query_arg('keycdn-error', urlencode(__('File push already in progress.', 'keycdn-push-enabler')), admin_url('options-general.php?page=keycdn-push-enabler')));
            exit;
        }
        
        // Schedule push
        $this->cron_tasks->schedule_push_static_files();
        
        // Redirect back to settings page with success message
        wp_redirect(add_query_arg('keycdn-push-started', '1', admin_url('options-general.php?page=keycdn-push-enabler')));
        exit;
    }

    /**
     * Reset push process
     */
    public function reset_push_process() {
        // Check nonce and permissions
        check_admin_referer('keycdn_push_enabler_reset_push');
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'keycdn-push-enabler'));
        }
        
        // Reset push process
        $this->cron_tasks->reset_push_process();
        
        // Redirect back to settings page with success message
        wp_redirect(add_query_arg('keycdn-push-reset', '1', admin_url('options-general.php?page=keycdn-push-enabler')));
        exit;
    }

    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook Current admin page
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our settings page
        if ($hook != 'settings_page_keycdn-push-enabler') {
            return;
        }
        
        // Check if push is active
        $progress = $this->cron_tasks->get_push_progress();
        if (!$progress['is_active']) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'keycdn-push-enabler-admin',
            KEYCDN_PUSH_ENABLER_URL . 'assets/css/admin.css',
            array(),
            KEYCDN_PUSH_ENABLER_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'keycdn-push-enabler-admin',
            KEYCDN_PUSH_ENABLER_URL . 'assets/js/admin.js',
            array('jquery'),
            KEYCDN_PUSH_ENABLER_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script(
            'keycdn-push-enabler-admin',
            'keycdnPushAddon',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('keycdn_push_enabler_ajax'),
                'i18n' => array(
                    'processed' => __('Processed %1$d of %2$d files (%3$d%%)', 'keycdn-push-enabler'),
                    'stalled' => __('The process appears to be stalled. You may want to reset it.', 'keycdn-push-enabler')
                )
            )
        );
    }

    /**
     * AJAX handler for getting progress
     */
    public function ajax_get_progress() {
        // Check nonce
        check_ajax_referer('keycdn_push_enabler_ajax', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'keycdn-push-enabler')));
        }
        
        // Get progress
        $progress = $this->cron_tasks->get_push_progress();
        
        // Send response
        wp_send_json_success($progress);
    }

    /**
     * Check additional authorization criteria beyond capability
     * 
     * @return bool True if authorized, false otherwise
     */
    private function check_additional_authorization() {
        // Already checked manage_options capability
        
        // Check if user has been an admin for at least a day
        $user = wp_get_current_user();
        if (empty($user) || !$user->ID) {
            return false;
        }
        
        // Get user registration time
        $user_data = get_userdata($user->ID);
        $registered = strtotime($user_data->user_registered);
        $one_day_ago = time() - (24 * 60 * 60);
        
        // Apply this only for actions that purge the cache or push all files
        if (isset($_REQUEST['action']) && 
            in_array($_REQUEST['action'], array('keycdn_push_enabler_purge_cache', 'keycdn_push_enabler_push_all_files'))) {
            
            if ($registered > $one_day_ago) {
                // User registered less than a day ago
                error_log('KeyCDN Push Addon: Recently registered admin attempted sensitive operation');
                return false;
            }
        }
        
        return true;
    }
}