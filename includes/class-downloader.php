<?php
/**
 * Downloader class for YT-DLP operations
 */

class YTDLP_Downloader {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_ytdlp_get_info', array($this, 'ajax_get_video_info'));
        add_action('wp_ajax_nopriv_ytdlp_get_info', array($this, 'ajax_get_video_info'));
        
        add_action('wp_ajax_ytdlp_download', array($this, 'ajax_download_video'));
        add_action('wp_ajax_nopriv_ytdlp_download', array($this, 'ajax_download_video'));
        
        add_action('wp_ajax_ytdlp_progress', array($this, 'ajax_get_progress'));
        add_action('wp_ajax_nopriv_ytdlp_progress', array($this, 'ajax_get_progress'));
        
        add_action('ytdlp_process_download', array($this, 'process_download_background'), 10, 4);
    }
    
    public function ajax_get_video_info() {
        check_ajax_referer('ytdlp_nonce', 'nonce');
        
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        
        if (empty($url)) {
            wp_send_json_error(array('message' => 'Invalid URL'));
        }
        
        $info = $this->get_video_info($url);
        
        if (is_wp_error($info)) {
            wp_send_json_error(array('message' => $info->get_error_message()));
        }
        
        wp_send_json_success($info);
    }
    
    public function ajax_download_video() {
        check_ajax_referer('ytdlp_nonce', 'nonce');
        
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'mp4';
        $quality = isset($_POST['quality']) ? sanitize_text_field($_POST['quality']) : 'best';
        
        if (empty($url)) {
            wp_send_json_error(array('message' => 'Invalid URL'));
        }
        
        $settings = get_option('ytdlp_settings');
        
        // Validate format
        if (!in_array($format, $settings['allowed_formats'])) {
            wp_send_json_error(array('message' => 'Format not allowed'));
        }
        
        // Generate progress ID
        $progress_id = uniqid('dl_', true);
        
        // Set initial progress
        set_transient('ytdlp_progress_' . $progress_id, array(
            'status' => 'starting',
            'progress' => 0,
            'message' => 'Initializing download...'
        ), 3600);
        
        // Start download in background
        wp_schedule_single_event(time(), 'ytdlp_process_download', array($url, $format, $quality, $progress_id));
        
        // Return progress ID immediately
        wp_send_json_success(array('progress_id' => $progress_id));
    }
    
    public function ajax_get_progress() {
        check_ajax_referer('ytdlp_nonce', 'nonce');
        
        $progress_id = isset($_POST['progress_id']) ? sanitize_text_field($_POST['progress_id']) : '';
        
        if (empty($progress_id)) {
            wp_send_json_error(array('message' => 'Invalid progress ID'));
        }
        
        $progress = get_transient('ytdlp_progress_' . $progress_id);
        
        if ($progress === false) {
            wp_send_json_error(array('message' => 'Progress not found'));
        }
        
        wp_send_json_success($progress);
    }
    
    private function execute_with_progress($command, $progress_id) {
        set_transient('ytdlp_progress_' . $progress_id, array(
            'status' => 'downloading',
            'progress' => 5,
            'message' => 'Starting download...'
        ), 3600);
        
        $process = popen($command, 'r');
        $output = '';
        
        if ($process) {
            while (!feof($process)) {
                $line = fgets($process);
                $output .= $line;
                
                // Parse progress from yt-dlp output
                if (preg_match('/\[download\]\s+(\d+\.\d+)%/', $line, $matches)) {
                    $progress = floatval($matches[1]);
                    set_transient('ytdlp_progress_' . $progress_id, array(
                        'status' => 'downloading',
                        'progress' => $progress,
                        'message' => 'Downloading... ' . number_format($progress, 1) . '%'
                    ), 3600);
                } elseif (strpos($line, '[download] 100%') !== false) {
                    set_transient('ytdlp_progress_' . $progress_id, array(
                        'status' => 'processing',
                        'progress' => 95,
                        'message' => 'Processing file...'
                    ), 3600);
                } elseif (strpos($line, 'Merging formats') !== false) {
                    set_transient('ytdlp_progress_' . $progress_id, array(
                        'status' => 'processing',
                        'progress' => 90,
                        'message' => 'Merging video and audio...'
                    ), 3600);
                } elseif (strpos($line, 'Deleting original file') !== false) {
                    set_transient('ytdlp_progress_' . $progress_id, array(
                        'status' => 'processing',
                        'progress' => 98,
                        'message' => 'Finalizing...'
                    ), 3600);
                }
            }
            pclose($process);
        }
        
        return $output;
    }
    
    public function process_download_background($url, $format, $quality, $progress_id) {
        $result = $this->download_video($url, $format, $quality, $progress_id);
        
        if (is_wp_error($result)) {
            set_transient('ytdlp_progress_' . $progress_id, array(
                'status' => 'error',
                'progress' => 0,
                'message' => $result->get_error_message()
            ), 3600);
        } else {
            set_transient('ytdlp_progress_' . $progress_id, array(
                'status' => 'complete',
                'progress' => 100,
                'message' => 'Download complete!',
                'download_url' => $result['download_url'],
                'file_name' => $result['file_name'],
                'file_size' => $result['file_size']
            ), 3600);
        }
    }
    
    private function get_video_info($url) {
        $settings = get_option('ytdlp_settings');
        $ytdlp_path = isset($settings['ytdlp_path']) ? $settings['ytdlp_path'] : '/usr/local/bin/yt-dlp';
        
        if (!file_exists($ytdlp_path)) {
            return new WP_Error('ytdlp_not_found', 'yt-dlp not found at specified path');
        }
        
        // Sanitize URL
        $url = escapeshellarg($url);
        
        // Get video info as JSON
        $cookies_param = '';
        if (isset($settings['cookies_file']) && !empty($settings['cookies_file']) && file_exists($settings['cookies_file'])) {
            $cookies_param = '--cookies ' . escapeshellarg($settings['cookies_file']);
            if (isset($settings['enable_logging']) && $settings['enable_logging']) {
                error_log('YT-DLP: Using cookies file: ' . $settings['cookies_file']);
            }
        } else {
            if (isset($settings['enable_logging']) && $settings['enable_logging']) {
                error_log('YT-DLP: No cookies file configured or file not found');
            }
        }
        
        $command = sprintf(
            '%s --dump-json --no-warnings --extractor-args "youtube:player_client=default" %s %s 2>&1',
            escapeshellarg($ytdlp_path),
            $cookies_param,
            $url
        );
        
        $output = shell_exec($command);
        $info = json_decode($output, true);
        
        if (empty($info)) {
            // Log the command and output for debugging
            if (isset($settings['enable_logging']) && $settings['enable_logging']) {
                error_log('YT-DLP Info Command: ' . $command);
                error_log('YT-DLP Info Output: ' . $output);
            }
            return new WP_Error('invalid_video', 'Could not retrieve video information: ' . $output);
        }
        
        // Extract relevant information
        $video_info = array(
            'title' => isset($info['title']) ? sanitize_text_field($info['title']) : 'Unknown',
            'duration' => isset($info['duration']) ? intval($info['duration']) : 0,
            'thumbnail' => isset($info['thumbnail']) ? esc_url($info['thumbnail']) : '',
            'uploader' => isset($info['uploader']) ? sanitize_text_field($info['uploader']) : 'Unknown',
            'formats' => array()
        );
        
        // Get available formats
        if (isset($info['formats']) && is_array($info['formats'])) {
            foreach ($info['formats'] as $format) {
                if (isset($format['format_id']) && isset($format['ext'])) {
                    $video_info['formats'][] = array(
                        'format_id' => $format['format_id'],
                        'ext' => $format['ext'],
                        'resolution' => isset($format['resolution']) ? $format['resolution'] : 'audio only',
                        'filesize' => isset($format['filesize']) ? $format['filesize'] : 0
                    );
                }
            }
        }
        
        return $video_info;
    }
    
    private function download_video($url, $format, $quality, $progress_id = null) {
        $settings = get_option('ytdlp_settings');
        $ytdlp_path = isset($settings['ytdlp_path']) ? $settings['ytdlp_path'] : '/usr/local/bin/yt-dlp';
        $timeout = isset($settings['download_timeout']) ? $settings['download_timeout'] : 300;
        
        if (!file_exists($ytdlp_path)) {
            return new WP_Error('ytdlp_not_found', 'yt-dlp not found at specified path');
        }
        
        // Create temp directory
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/yt-dlp-downloads/temp';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        // Generate unique filename with specific extension
        $unique_id = uniqid('ytdlp_', true);
        $output_filename = $unique_id . '.' . $format;
        $output_template = $temp_dir . '/' . $output_filename;
        
        // Build command
        $url = escapeshellarg($url);
        
        // Add cookies parameter if available
        $cookies_param = '';
        if (isset($settings['cookies_file']) && !empty($settings['cookies_file']) && file_exists($settings['cookies_file'])) {
            $cookies_param = '--cookies ' . escapeshellarg($settings['cookies_file']);
            if (isset($settings['enable_logging']) && $settings['enable_logging']) {
                error_log('YT-DLP: Using cookies file for download: ' . $settings['cookies_file']);
            }
        }
        
        if ($format === 'mp3' || $format === 'm4a' || $format === 'wav' || $format === 'flac') {
            // Audio only - extract and convert
            $audio_quality = $quality === 'best' ? '0' : '5';
            
            // Add FFmpeg path if specified
            $ffmpeg_param = '';
            if (isset($settings['ffmpeg_path']) && !empty($settings['ffmpeg_path']) && file_exists($settings['ffmpeg_path'])) {
                $ffmpeg_param = '--ffmpeg-location ' . escapeshellarg($settings['ffmpeg_path']);
            }
            
            $command = sprintf(
                '%s -x --audio-format %s --audio-quality %s --extractor-args "youtube:player_client=default" %s %s -o "%s" %s 2>&1',
                escapeshellarg($ytdlp_path),
                escapeshellarg($format),
                $audio_quality,
                $cookies_param,
                $ffmpeg_param,
                $temp_dir . '/' . $unique_id . '.%(ext)s',
                $url
            );
            
            // For audio, we need to find the actual output file after conversion
            $output_filename = $unique_id . '.' . $format;
        } else {
            // Video with audio - handle different quality options
            if ($quality === 'best') {
                $format_spec = 'best[ext=' . $format . ']/bestvideo+bestaudio';
            } elseif ($quality === 'worst') {
                $format_spec = 'worst[ext=' . $format . ']/worstvideo+worstaudio';
            } else {
                // Specific resolution (e.g., 1080p, 720p)
                $format_spec = 'best[height<=' . intval($quality) . '][ext=' . $format . ']/best[height<=' . intval($quality) . ']+bestaudio';
            }
            
            $command = sprintf(
                '%s -f %s --merge-output-format %s --extractor-args "youtube:player_client=default" %s -o %s %s 2>&1',
                escapeshellarg($ytdlp_path),
                escapeshellarg($format_spec),
                escapeshellarg($format),
                $cookies_param,
                escapeshellarg($output_template),
                $url
            );
        }
        
        // Execute download with real-time progress
        if ($progress_id) {
            $output = $this->execute_with_progress($command, $progress_id);
        } else {
            $output = shell_exec($command);
        }
        
        // For audio files, look for the converted file
        if ($format === 'mp3' || $format === 'm4a' || $format === 'wav' || $format === 'flac') {
            $expected_file = $temp_dir . '/' . $unique_id . '.' . $format;
            if (file_exists($expected_file)) {
                $files = array($expected_file);
            } else {
                // Look for any audio file with the unique ID
                $files = glob($temp_dir . '/' . $unique_id . '.*');
                $files = array_filter($files, function($file) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    return in_array($ext, array('mp3', 'm4a', 'wav', 'flac', 'aac', 'ogg'));
                });
            }
        } else {
            // For video files
            $expected_file = $temp_dir . '/' . $output_filename;
            if (file_exists($expected_file)) {
                $files = array($expected_file);
            } else {
                $files = glob($temp_dir . '/' . $unique_id . '.*');
                $files = array_filter($files, function($file) {
                    return !preg_match('/\.(part|temp|tmp)$/i', $file);
                });
            }
        }
        
        if (empty($files)) {
            if (isset($settings['enable_logging']) && $settings['enable_logging']) {
                error_log('YT-DLP Download failed: ' . $output);
            }
            return new WP_Error('download_failed', 'Download failed: ' . $output);
        }
        
        $file_path = $files[0];
        $file_size = filesize($file_path);
        
        // Check file size
        $max_file_size = isset($settings['max_file_size']) ? $settings['max_file_size'] : 500;
        if ($file_size > ($max_file_size * 1024 * 1024)) {
            unlink($file_path);
            return new WP_Error('file_too_large', 'File exceeds maximum allowed size');
        }
        
        // Generate download URL with token
        $file_name = basename($file_path);
        $token = $this->generate_download_token($file_name);
        
        $download_url = add_query_arg(array(
            'ytdlp_download' => '1',
            'file' => $file_name,
            'token' => $token
        ), site_url('/'));
        
        // Update progress - complete
        if ($progress_id) {
            set_transient('ytdlp_progress_' . $progress_id, array(
                'status' => 'complete',
                'progress' => 100,
                'message' => 'Download complete!'
            ), 3600);
        }
        
        // Schedule file cleanup after 1 hour
        wp_schedule_single_event(time() + 3600, 'ytdlp_cleanup_file', array($file_path));
        
        return array(
            'download_url' => str_replace('&amp;', '&', $download_url),
            'file_name' => $file_name,
            'file_size' => $file_size
        );
    }
    
    private function generate_download_token($file_name) {
        return wp_hash($file_name . time(), 'nonce');
    }
    
    public function verify_download_token($file_name, $token) {
        // Token is valid for 1 hour
        $expected_token = wp_hash($file_name . time(), 'nonce');
        return hash_equals($expected_token, $token);
    }
}

// Handle direct download requests
add_action('init', function() {
    if (isset($_GET['ytdlp_download']) && isset($_GET['file']) && isset($_GET['token'])) {
        $file_name = sanitize_file_name($_GET['file']);
        $token = sanitize_text_field($_GET['token']);
        
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'yt-dlp-downloads' . DIRECTORY_SEPARATOR . 'temp';
        $file_path = $temp_dir . DIRECTORY_SEPARATOR . $file_name;
        
        // If file with (ext)s template name doesn't exist, try to find the actual file
        if (!file_exists($file_path) && strpos($file_name, '(ext)s') !== false) {
            // Extract unique ID from filename
            $unique_id = preg_replace('/\.(ext)s.*$/', '', $file_name);
            $files = glob($temp_dir . DIRECTORY_SEPARATOR . $unique_id . '.*');
            
            if (!empty($files)) {
                $file_path = $files[0];
                $file_name = basename($file_path);
            }
        }
        
        if (!file_exists($file_path)) {
            wp_die('File not found or has expired');
        }
        
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Get file extension for proper MIME type
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $mime_types = array(
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac'
        );
        
        $content_type = isset($mime_types[$ext]) ? $mime_types[$ext] : 'application/octet-stream';
        
        // Send headers
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Send file
        readfile($file_path);
        exit;
    }
}, 1);

// Cleanup scheduled files
add_action('ytdlp_cleanup_file', function($file_path) {
    if (file_exists($file_path)) {
        unlink($file_path);
    }
});
