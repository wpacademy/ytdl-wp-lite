jQuery(document).ready(function($) {
    var mediaUploader;
    
    $('#upload_cookies_button').click(function(e) {
        e.preventDefault();
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: 'Select Cookies File',
            button: {
                text: 'Use this file'
            },
            library: {
                type: 'text/plain'
            },
            multiple: false
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            
            // Validate file extension
            if (!attachment.filename.toLowerCase().endsWith('.txt')) {
                alert('Please select a .txt file');
                return;
            }
            
            // Use the attachment ID to get server path via AJAX
            $.post(ajaxurl, {
                action: 'ytdlp_get_file_path',
                attachment_id: attachment.id,
                nonce: ytdlpMedia.nonce
            }, function(response) {
                if (response.success) {
                    $('#cookies_file').val(response.data.file_path);
                } else {
                    alert('Error getting file path');
                }
            });
        });
        
        mediaUploader.open();
    });
    
    $('#clear_cookies_button').click(function(e) {
        e.preventDefault();
        $('#cookies_file').val('');
    });
});