<?php
/**
 * Frontend class for YT-DLP Downloader
 */

class YTDLP_Frontend {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('ytdlp_downloader', array($this, 'render_downloader'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function enqueue_assets() {
        if (!is_singular() && !is_page()) {
            return;
        }
        
        global $post;
        if (!isset($post->post_content) || !has_shortcode($post->post_content, 'ytdlp_downloader')) {
            return;
        }
        
        wp_enqueue_style(
            'ytdlp-frontend',
            YTDLP_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            YTDLP_VERSION
        );
        
        wp_enqueue_script(
            'ytdlp-frontend',
            YTDLP_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            YTDLP_VERSION,
            true
        );
        
        wp_localize_script('ytdlp-frontend', 'ytdlpData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ytdlp_nonce'),
            'strings' => array(
                'loading' => __('Loading...', 'yt-dlp-downloader'),
                'error' => __('An error occurred', 'yt-dlp-downloader'),
                'invalidUrl' => __('Please enter a valid URL', 'yt-dlp-downloader'),
                'downloading' => __('Downloading...', 'yt-dlp-downloader'),
                'success' => __('Download ready!', 'yt-dlp-downloader')
            )
        ));
    }
    
    public function render_downloader($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Video Downloader',
            'placeholder' => 'Enter video URL (YouTube, Vimeo, etc.)'
        ), $atts);
        
        $settings = get_option('ytdlp_settings');
        
        // Fallback for allowed formats if not set
        $allowed_formats = isset($settings['allowed_formats']) && is_array($settings['allowed_formats']) && !empty($settings['allowed_formats']) 
            ? $settings['allowed_formats'] 
            : array('mp4', 'webm', 'mkv', 'mp3', 'm4a');
        
        ob_start();
        ?>
        <div class="ytdlp-container">
            <div class="ytdlp-wrapper">
                <?php if (!empty($atts['title'])): ?>
                    <h2 class="ytdlp-title"><?php echo esc_html($atts['title']); ?></h2>
                <?php endif; ?>
                
                <div class="ytdlp-input-section">
                    <input 
                        type="text" 
                        id="ytdlp-url-input" 
                        class="ytdlp-input" 
                        placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                    />
                    <button id="ytdlp-fetch-btn" class="ytdlp-btn ytdlp-btn-primary">
                        Get Video Info
                    </button>
                </div>
                
                <div id="ytdlp-messages" class="ytdlp-messages"></div>
                
                <div id="ytdlp-video-info" class="ytdlp-video-info" style="display: none;">
                    <div class="ytdlp-info-header">
                        <img id="ytdlp-thumbnail" class="ytdlp-thumbnail" src="" alt="Video thumbnail" />
                        <div class="ytdlp-meta">
                            <h3 id="ytdlp-video-title" class="ytdlp-video-title"></h3>
                            <p class="ytdlp-video-meta">
                                <span id="ytdlp-uploader"></span>
                                <span id="ytdlp-duration"></span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="ytdlp-download-options">
                        <h4>Download Options</h4>
                        
                        <div class="ytdlp-option-group">
                            <label for="ytdlp-format">Format:</label>
                            <select id="ytdlp-format" class="ytdlp-select">
                                <?php foreach ($allowed_formats as $format): ?>
                                    <option value="<?php echo esc_attr($format); ?>">
                                        <?php echo strtoupper($format); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="ytdlp-option-group">
                            <label for="ytdlp-quality">Quality:</label>
                            <select id="ytdlp-quality" class="ytdlp-select">
                                <option value="best">Best Quality</option>
                                <option value="2160p">4K (2160p)</option>
                                <option value="1440p">2K (1440p)</option>
                                <option value="1080p">Full HD (1080p)</option>
                                <option value="720p">HD (720p)</option>
                                <option value="480p">SD (480p)</option>
                                <option value="360p">Low (360p)</option>
                                <option value="worst">Lowest Quality</option>
                            </select>
                        </div>
                        
                        <button id="ytdlp-download-btn" class="ytdlp-btn ytdlp-btn-success">
                            <span class="ytdlp-btn-icon">⬇</span> Download
                        </button>
                    </div>
                </div>
                
                <div id="ytdlp-download-progress" class="ytdlp-progress" style="display: none;">
                    <div class="ytdlp-spinner"></div>
                    <p>Processing your download...</p>
                </div>
                
                <div id="ytdlp-download-ready" class="ytdlp-download-ready" style="display: none;">
                    <div class="ytdlp-success-icon">✓</div>
                    <p>Your download is ready!</p>
                    <a id="ytdlp-download-link" href="#" class="ytdlp-btn ytdlp-btn-download" download>
                        Download File
                    </a>
                    <p class="ytdlp-file-info">
                        <small>File: <span id="ytdlp-file-name"></span></small><br>
                        <small>Size: <span id="ytdlp-file-size"></span></small>
                    </p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
