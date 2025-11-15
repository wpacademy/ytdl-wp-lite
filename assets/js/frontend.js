(function($) {
    'use strict';
    
    let currentVideoUrl = '';
    
    $(document).ready(function() {
        initDownloader();
    });
    
    function initDownloader() {
        // Fetch video info button
        $('#ytdlp-fetch-btn').on('click', function() {
            const url = $('#ytdlp-url-input').val().trim();
            
            if (!url) {
                showMessage('error', ytdlpData.strings.invalidUrl);
                return;
            }
            
            if (!isValidUrl(url)) {
                showMessage('error', 'Please enter a valid video URL');
                return;
            }
            
            fetchVideoInfo(url);
        });
        
        // Download button
        $('#ytdlp-download-btn').on('click', function() {
            const format = $('#ytdlp-format').val();
            const quality = $('#ytdlp-quality').val();
            
            downloadVideo(currentVideoUrl, format, quality);
        });
        
        // Enter key support
        $('#ytdlp-url-input').on('keypress', function(e) {
            if (e.which === 13) {
                $('#ytdlp-fetch-btn').trigger('click');
            }
        });
        
        // Format change handler
        $('#ytdlp-format').on('change', function() {
            const format = $(this).val();
            const audioFormats = ['mp3', 'm4a', 'wav', 'flac'];
            
            if (audioFormats.includes(format)) {
                $('#ytdlp-quality').html(
                    '<option value="best">Best Quality</option>' +
                    '<option value="worst">Lowest Quality</option>'
                );
            } else {
                $('#ytdlp-quality').html(
                    '<option value="best">Best Quality</option>' +
                    '<option value="2160p">4K (2160p)</option>' +
                    '<option value="1440p">2K (1440p)</option>' +
                    '<option value="1080p">Full HD (1080p)</option>' +
                    '<option value="720p">HD (720p)</option>' +
                    '<option value="480p">SD (480p)</option>' +
                    '<option value="360p">Low (360p)</option>' +
                    '<option value="worst">Lowest Quality</option>'
                );
            }
        });
    }
    
    function fetchVideoInfo(url) {
        clearMessages();
        hideAllSections();
        
        $('#ytdlp-fetch-btn').prop('disabled', true).text(ytdlpData.strings.loading);
        
        $.ajax({
            url: ytdlpData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ytdlp_get_info',
                nonce: ytdlpData.nonce,
                url: url
            },
            success: function(response) {
                if (response.success) {
                    currentVideoUrl = url;
                    displayVideoInfo(response.data);
                } else {
                    showMessage('error', response.data.message || ytdlpData.strings.error);
                }
            },
            error: function() {
                showMessage('error', 'Failed to connect to server. Please try again.');
            },
            complete: function() {
                $('#ytdlp-fetch-btn').prop('disabled', false).text('Get Video Info');
            }
        });
    }
    
    function downloadVideo(url, format, quality) {
        clearMessages();
        hideSection('#ytdlp-video-info');
        showSection('#ytdlp-download-progress');
        
        $.ajax({
            url: ytdlpData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ytdlp_download',
                nonce: ytdlpData.nonce,
                url: url,
                format: format,
                quality: quality
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.progress_id) {
                        trackProgress(response.data.progress_id);
                    } else {
                        displayDownloadReady(response.data);
                    }
                } else {
                    hideSection('#ytdlp-download-progress');
                    showSection('#ytdlp-video-info');
                    showMessage('error', response.data.message || ytdlpData.strings.error);
                }
            },
            error: function() {
                hideSection('#ytdlp-download-progress');
                showSection('#ytdlp-video-info');
                showMessage('error', 'Download failed. Please try again.');
            }
        });
    }
    
    function trackProgress(progressId) {
        const progressInterval = setInterval(function() {
            $.ajax({
                url: ytdlpData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ytdlp_progress',
                    nonce: ytdlpData.nonce,
                    progress_id: progressId
                },
                success: function(response) {
                    if (response.success) {
                        const progress = response.data;
                        updateProgressDisplay(progress);
                        
                        if (progress.status === 'complete') {
                            clearInterval(progressInterval);
                            // Display download ready with file info
                            setTimeout(function() {
                                displayDownloadReady({
                                    download_url: progress.download_url,
                                    file_name: progress.file_name,
                                    file_size: progress.file_size
                                });
                            }, 1000);
                        } else if (progress.status === 'error') {
                            clearInterval(progressInterval);
                            hideSection('#ytdlp-download-progress');
                            showSection('#ytdlp-video-info');
                            showMessage('error', progress.message);
                        }
                    }
                },
                error: function() {
                    clearInterval(progressInterval);
                }
            });
        }, 1000);
    }
    
    function updateProgressDisplay(progress) {
        const progressText = $('#ytdlp-download-progress p');
        progressText.text(progress.message + ' (' + progress.progress + '%)');
        
        // Add progress bar if it doesn't exist
        if ($('#ytdlp-progress-bar').length === 0) {
            $('#ytdlp-download-progress').append(
                '<div id="ytdlp-progress-bar" style="width: 100%; background: #f0f0f0; border-radius: 4px; margin-top: 10px;">' +
                '<div id="ytdlp-progress-fill" style="height: 20px; background: #4a90e2; border-radius: 4px; width: 0%; transition: width 0.3s;"></div>' +
                '</div>'
            );
        }
        
        $('#ytdlp-progress-fill').css('width', progress.progress + '%');
    }
    
    function displayVideoInfo(data) {
        $('#ytdlp-thumbnail').attr('src', data.thumbnail);
        $('#ytdlp-video-title').text(data.title);
        $('#ytdlp-uploader').text('By: ' + data.uploader);
        $('#ytdlp-duration').text('Duration: ' + formatDuration(data.duration));
        
        showSection('#ytdlp-video-info');
    }
    
    function displayDownloadReady(data) {
        hideSection('#ytdlp-download-progress');
        
        // Decode HTML entities in URL (handle multiple levels)
        let downloadUrl = data.download_url;
        downloadUrl = downloadUrl.replace(/&amp;/g, '&');
        downloadUrl = downloadUrl.replace(/&amp;/g, '&'); // Second pass for double encoding
        
        $('#ytdlp-download-link').attr('href', downloadUrl);
        $('#ytdlp-file-name').text(data.file_name);
        $('#ytdlp-file-size').text(formatFileSize(data.file_size));
        
        showSection('#ytdlp-download-ready');
    }
    
    function showMessage(type, message) {
        const messageClass = 'ytdlp-message-' + type;
        const messageHtml = '<div class="ytdlp-message ' + messageClass + '">' + 
                           escapeHtml(message) + '</div>';
        
        $('#ytdlp-messages').html(messageHtml);
    }
    
    function clearMessages() {
        $('#ytdlp-messages').empty();
    }
    
    function showSection(selector) {
        $(selector).slideDown(300);
    }
    
    function hideSection(selector) {
        $(selector).slideUp(300);
    }
    
    function hideAllSections() {
        $('#ytdlp-video-info, #ytdlp-download-progress, #ytdlp-download-ready').hide();
    }
    
    function isValidUrl(string) {
        try {
            const url = new URL(string);
            return url.protocol === 'http:' || url.protocol === 'https:';
        } catch (_) {
            return false;
        }
    }
    
    function formatDuration(seconds) {
        if (!seconds) return 'Unknown';
        
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        if (hours > 0) {
            return hours + ':' + pad(minutes) + ':' + pad(secs);
        }
        return minutes + ':' + pad(secs);
    }
    
    function formatFileSize(bytes) {
        if (!bytes) return 'Unknown';
        
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unitIndex = 0;
        
        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }
        
        return size.toFixed(2) + ' ' + units[unitIndex];
    }
    
    function pad(num) {
        return num.toString().padStart(2, '0');
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
})(jQuery);
