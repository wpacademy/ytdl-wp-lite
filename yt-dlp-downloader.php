<?php
/**
 * Plugin Name: YT-DLP Video Downloader
 * Plugin URI: https://github.com/wpacademy/yt-dlp-wordpress-plugin
 * Description: Download videos using yt-dlp with a simple backend and feature-rich frontend UI
 * Version: 1.0.0
 * Author: Mian Shahzad Raza
 * License: GPL v2 or later
 * Text Domain: yt-dlp-downloader
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('YTDLP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YTDLP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('YTDLP_VERSION', '1.0.0');

// Include required files
require_once YTDLP_PLUGIN_DIR . 'includes/class-settings.php';
require_once YTDLP_PLUGIN_DIR . 'includes/class-downloader.php';
require_once YTDLP_PLUGIN_DIR . 'includes/class-frontend.php';

// Initialize the plugin
class YTDLP_Main {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize components
        YTDLP_Settings::get_instance();
        YTDLP_Downloader::get_instance();
        YTDLP_Frontend::get_instance();
        
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function activate() {
        // Create downloads directory
        $upload_dir = wp_upload_dir();
        $downloads_dir = $upload_dir['basedir'] . '/yt-dlp-downloads';
        
        if (!file_exists($downloads_dir)) {
            wp_mkdir_p($downloads_dir);
            // Add .htaccess to prevent direct access
            file_put_contents($downloads_dir . '/.htaccess', 'Options -Indexes');
        }
        
        // Set default options
        $default_settings = array(
            'ytdlp_path' => 'yt-dlp',
            'ffmpeg_path' => '',
            'cookies_file' => '',
            'max_file_size' => 500,
            'allowed_formats' => array('mp4', 'webm', 'mkv', 'mp3', 'm4a'),
            'download_timeout' => 300,
            'enable_logging' => false
        );
        
        $current_settings = get_option('ytdlp_settings', array());
        $merged_settings = array_merge($default_settings, $current_settings);
        update_option('ytdlp_settings', $merged_settings);
    }
    
    public function deactivate() {
        // Clean up temporary files if needed
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/yt-dlp-downloads/temp';
        
        if (file_exists($temp_dir)) {
            $files = glob($temp_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}

// Initialize plugin
add_action('plugins_loaded', array('YTDLP_Main', 'get_instance'));
