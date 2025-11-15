<?php
/**
 * Settings page for YT-DLP Downloader
 */

class YTDLP_Settings {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_media_uploader'));
        add_action('wp_ajax_ytdlp_get_file_path', array($this, 'ajax_get_file_path'));
        add_action('admin_post_ytdlp_clear_temp', array($this, 'clear_temp_folder'));
    }
    
    public function add_settings_page() {
        // Add main menu
        add_menu_page(
            'YT-DLP WordPress',
            'YT-DLP WP',
            'manage_options',
            'ytdlp-main',
            array($this, 'render_settings_page'),
            'dashicons-video-alt3',
            30
        );
        
        // Add submenu pages
        add_submenu_page(
            'ytdlp-main',
            'Settings',
            'Settings',
            'manage_options',
            'ytdlp-main',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'ytdlp-main',
            'Tools',
            'Tools',
            'manage_options',
            'ytdlp-tools',
            array($this, 'render_tools_page')
        );
    }
    
    public function register_settings() {
        register_setting('ytdlp_settings_group', 'ytdlp_settings', array($this, 'sanitize_settings'));
    }
    
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['ytdlp_path'] = sanitize_text_field($input['ytdlp_path']);
        $sanitized['ffmpeg_path'] = sanitize_text_field($input['ffmpeg_path']);
        $sanitized['cookies_file'] = sanitize_text_field($input['cookies_file']);
        $sanitized['max_file_size'] = absint($input['max_file_size']);
        $sanitized['download_timeout'] = absint($input['download_timeout']);
        $sanitized['allowed_formats'] = isset($input['allowed_formats']) ? $input['allowed_formats'] : array();
        $sanitized['enable_logging'] = isset($input['enable_logging']) ? true : false;
        
        return $sanitized;
    }
    
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $settings = get_option('ytdlp_settings');
        
        // Check if yt-dlp is installed
        $ytdlp_installed = $this->check_ytdlp_installed($settings['ytdlp_path']);
        
        // Handle upload messages
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            switch ($message) {
                case 'cookies_uploaded':
                    echo '<div class="notice notice-success is-dismissible"><p>Cookies file uploaded successfully!</p></div>';
                    break;
                case 'upload_error':
                case 'upload_failed':
                    echo '<div class="notice notice-error is-dismissible"><p>Failed to upload cookies file.</p></div>';
                    break;
                case 'file_too_large':
                    echo '<div class="notice notice-error is-dismissible"><p>File too large. Maximum size is 1MB.</p></div>';
                    break;
                case 'invalid_file_type':
                    echo '<div class="notice notice-error is-dismissible"><p>Invalid file type. Please upload a .txt file.</p></div>';
                    break;
            }
        }
        
        ?>
        <div class="wrap">
            <h1>YT-DLP WordPress - Settings</h1>
            
            <?php if (!$ytdlp_installed): ?>
                <div class="notice notice-error">
                    <p><strong>Warning:</strong> yt-dlp is not found at the specified path. Please install it or update the path below.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-success">
                    <p><strong>Success:</strong> yt-dlp is properly configured and ready to use.</p>
                </div>
            <?php endif; ?>
            

            
            <form method="post" action="options.php">
                <?php settings_fields('ytdlp_settings_group'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ytdlp_path">YT-DLP Path</label>
                        </th>
                        <td>
                            <input type="text" id="ytdlp_path" name="ytdlp_settings[ytdlp_path]" 
                                   value="<?php echo esc_attr($settings['ytdlp_path']); ?>" 
                                   class="regular-text" />
                            <p class="description">Full path to yt-dlp binary (e.g., /usr/local/bin/yt-dlp)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ffmpeg_path">FFmpeg Path</label>
                        </th>
                        <td>
                            <input type="text" id="ffmpeg_path" name="ytdlp_settings[ffmpeg_path]" 
                                   value="<?php echo esc_attr(isset($settings['ffmpeg_path']) ? $settings['ffmpeg_path'] : ''); ?>" 
                                   class="regular-text" />
                            <p class="description">Path to FFmpeg binary (required for audio conversion, e.g., C:\ffmpeg\bin\ffmpeg.exe)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cookies_file">Cookies File</label>
                        </th>
                        <td>
                            <input type="text" id="cookies_file" name="ytdlp_settings[cookies_file]" 
                                   value="<?php echo esc_attr(isset($settings['cookies_file']) ? $settings['cookies_file'] : ''); ?>" 
                                   class="regular-text" readonly />
                            <button type="button" class="button" id="upload_cookies_button">Select Cookies File</button>
                            <button type="button" class="button" id="clear_cookies_button">Clear</button>
                            <p class="description">Upload cookies.txt file to bypass YouTube bot detection (optional)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="max_file_size">Max File Size (MB)</label>
                        </th>
                        <td>
                            <input type="number" id="max_file_size" name="ytdlp_settings[max_file_size]" 
                                   value="<?php echo esc_attr($settings['max_file_size']); ?>" 
                                   min="10" max="5000" />
                            <p class="description">Maximum allowed download file size in megabytes</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="download_timeout">Download Timeout (seconds)</label>
                        </th>
                        <td>
                            <input type="number" id="download_timeout" name="ytdlp_settings[download_timeout]" 
                                   value="<?php echo esc_attr($settings['download_timeout']); ?>" 
                                   min="60" max="3600" />
                            <p class="description">Maximum time allowed for a single download</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Allowed Formats</th>
                        <td>
                            <?php
                            $formats = array('mp4', 'webm', 'mkv', 'mp3', 'm4a', 'wav', 'flac');
                            $allowed_formats = isset($settings['allowed_formats']) ? $settings['allowed_formats'] : array();
                            
                            foreach ($formats as $format) {
                                $checked = in_array($format, $allowed_formats) ? 'checked' : '';
                                echo '<label style="margin-right: 15px;">';
                                echo '<input type="checkbox" name="ytdlp_settings[allowed_formats][]" value="' . esc_attr($format) . '" ' . $checked . ' /> ';
                                echo strtoupper($format);
                                echo '</label>';
                            }
                            ?>
                            <p class="description">Select which file formats users can download</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="enable_logging">Enable Logging</label>
                        </th>
                        <td>
                            <input type="checkbox" id="enable_logging" name="ytdlp_settings[enable_logging]" 
                                   value="1" <?php checked($settings['enable_logging'], true); ?> />
                            <p class="description">Log download attempts and errors for debugging</p>
                        </td>
                    </tr>
                </table>
                
                <h2>Usage Instructions</h2>
                <p>Use the shortcode <code>[ytdlp_downloader]</code> to display the download form on any page or post.</p>
                
                <?php submit_button(); ?>
            </form>
            

        </div>
        <?php
    }
    
    private function check_ytdlp_installed($path) {
        if (empty($path) || !file_exists($path)) {
            return false;
        }
        
        $output = shell_exec(escapeshellarg($path) . ' --version 2>&1');
        return !empty($output);
    }
    
    public function enqueue_media_uploader($hook) {
        if (!in_array($hook, array('toplevel_page_ytdlp-main', 'yt-dlp-wp_page_ytdlp-tools'))) {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_script('ytdlp-media-uploader', YTDLP_PLUGIN_URL . 'assets/js/media-uploader.js', array('jquery'), YTDLP_VERSION, true);
        wp_localize_script('ytdlp-media-uploader', 'ytdlpMedia', array(
            'nonce' => wp_create_nonce('ytdlp_file_path')
        ));
    }
    
    public function ajax_get_file_path() {
        check_ajax_referer('ytdlp_file_path', 'nonce');
        
        $attachment_id = intval($_POST['attachment_id']);
        $file_path = get_attached_file($attachment_id);
        
        if ($file_path && file_exists($file_path)) {
            wp_send_json_success(array('file_path' => $file_path));
        } else {
            wp_send_json_error(array('message' => 'File not found'));
        }
    }
    
    public function clear_temp_folder() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_admin_referer('ytdlp_clear_temp', 'ytdlp_clear_nonce');
        
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'yt-dlp-downloads' . DIRECTORY_SEPARATOR . 'temp';
        
        $success = true;
        $deleted_count = 0;
        
        if (file_exists($temp_dir)) {
            $files = glob($temp_dir . DIRECTORY_SEPARATOR . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    if (unlink($file)) {
                        $deleted_count++;
                    } else {
                        $success = false;
                    }
                }
            }
        }
        
        $message = $success ? 'temp_cleared' : 'temp_clear_failed';
        wp_redirect(add_query_arg('message', $message, admin_url('admin.php?page=ytdlp-tools')));
        exit;
    }
    
    public function render_tools_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle messages
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            switch ($message) {
                case 'temp_cleared':
                    echo '<div class="notice notice-success is-dismissible"><p>Temporary files cleared successfully!</p></div>';
                    break;
                case 'temp_clear_failed':
                    echo '<div class="notice notice-error is-dismissible"><p>Failed to clear temporary files.</p></div>';
                    break;
            }
        }
        
        ?>
        <div class="wrap">
            <h1>YT-DLP WordPress - Tools</h1>
            
            <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ccd0d4;">
                <h3>Clear Temporary Files</h3>
                <?php
                $upload_dir = wp_upload_dir();
                $temp_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'yt-dlp-downloads' . DIRECTORY_SEPARATOR . 'temp';
                $file_count = 0;
                $total_size = 0;
                
                if (file_exists($temp_dir)) {
                    $files = glob($temp_dir . DIRECTORY_SEPARATOR . '*');
                    $file_count = count($files);
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            $total_size += filesize($file);
                        }
                    }
                }
                ?>
                <p><strong>Temporary Files:</strong> <?php echo $file_count; ?> files | <strong>Size:</strong> <?php echo size_format($total_size); ?></p>
                
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('ytdlp_clear_temp', 'ytdlp_clear_nonce'); ?>
                    <input type="hidden" name="action" value="ytdlp_clear_temp" />
                    <input type="submit" value="Clear Temp Files" class="button button-primary" 
                           onclick="return confirm('Are you sure you want to delete all temporary files?');" />
                </form>
                
                <p class="description">Remove all temporary download files to free up disk space</p>
            </div>
        </div>
        <?php
    }
}
