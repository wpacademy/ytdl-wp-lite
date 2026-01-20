# YT-DLP WordPress Plugin

A simple and secure WordPress plugin that integrates yt-dlp for video downloading with a minimal backend settings page and feature-rich frontend UI.

<img width="1200" height="630" alt="banner-1" src="https://github.com/user-attachments/assets/ec88a75e-315f-4c9b-a91a-fa243c15aaf6" />

# YTDLP-WP Pro Version
## [Buy the premium version and unlock more features and improved downloading](https://msrbuilds.com/product/ytdlwp-pro/)
<img width="1280" height="720" alt="banner" src="https://github.com/user-attachments/assets/aaebff57-1159-4763-af03-0173fa2131b3" />


## Features

- **Simple Backend**: Minimal settings page for easy configuration
- **Rich Frontend UI**: Beautiful, responsive download interface with shortcode support
- **Multiple Formats**: Support for MP4, WebM, MKV, MP3, M4A, WAV, and FLAC
- **Quality Options**: Choose between best quality or smaller file sizes
- **Video Preview**: Display video thumbnail, title, uploader, and duration before downloading
- **Secure Downloads**: Token-based download system with automatic file cleanup
- **File Size Limits**: Configurable maximum file size to prevent abuse
- **AJAX-Powered**: Smooth user experience without page reloads

## Server Requirements

### Required
- **PHP**: 7.4 or higher
- **WordPress**: 5.8 or higher
- **yt-dlp**: Must be installed on the server
- **Shell Access**: PHP must be able to execute shell commands (`shell_exec`, `exec`)
- **FFmpeg**: Required for format conversion (audio extraction, format merging)

### Permissions
- Write permissions for WordPress uploads directory
- Execute permissions for yt-dlp binary

## Installation

### Step 1: Install yt-dlp

On Ubuntu/Debian:
```bash
sudo wget https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -O /usr/local/bin/yt-dlp
sudo chmod a+rx /usr/local/bin/yt-dlp
```

Or using pip:
```bash
sudo pip install yt-dlp
```

Verify installation:
```bash
yt-dlp --version
```

### Step 2: Install FFmpeg

On Ubuntu/Debian:
```bash
sudo apt-get update
sudo apt-get install ffmpeg
```

Verify installation:
```bash
ffmpeg -version
```

### Step 3: Install WordPress Plugin

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Settings → YT-DLP Downloader** to configure

## Configuration

### Backend Settings

Navigate to **Settings → YT-DLP Downloader** in WordPress admin:

1. **YT-DLP Path**: Full path to yt-dlp binary (default: `/usr/local/bin/yt-dlp`)
2. **Max File Size**: Maximum allowed download size in MB (default: 500 MB)
3. **Download Timeout**: Maximum time for a single download in seconds (default: 300s)
4. **Allowed Formats**: Select which formats users can download
5. **Enable Logging**: Turn on for debugging download issues

### Frontend Usage

Add the shortcode to any page or post:

```
[ytdlp_downloader]
```

**With custom attributes:**
```
[ytdlp_downloader title="Download Videos" placeholder="Paste YouTube URL here"]
```

## Security Features

### Input Validation
- URL sanitization and validation
- Format whitelist enforcement
- File size limits

### Download Security
- Token-based download URLs
- Temporary file storage
- Automatic cleanup after 1 hour
- .htaccess protection for download directory

### PHP Security
- Nonce verification for all AJAX requests
- Capability checks for admin functions
- Escaped output for all user-facing content
- Command injection prevention via `escapeshellarg()`

## File Structure

```
yt-dlp-wordpress-plugin/
├── yt-dlp-downloader.php       # Main plugin file
├── includes/
│   ├── class-settings.php       # Admin settings page
│   ├── class-downloader.php     # Download handler
│   └── class-frontend.php       # Shortcode & frontend
├── assets/
│   ├── css/
│   │   └── frontend.css         # Frontend styles
│   └── js/
│       └── frontend.js          # Frontend JavaScript
└── README.md                    # This file
```

## Usage Flow

1. User enters video URL and clicks "Get Video Info"
2. Plugin fetches video metadata using yt-dlp
3. Video information is displayed (thumbnail, title, duration, uploader)
4. User selects format and quality preferences
5. User clicks "Download" button
6. Video is downloaded to temporary directory
7. Secure download link is generated
8. File is automatically deleted after 1 hour

## Supported Platforms

The plugin works with any platform supported by yt-dlp, including:
- YouTube
- Vimeo
- Facebook
- Twitter
- Instagram
- TikTok
- And 1000+ other sites

## Troubleshooting

### "yt-dlp not found" Error
- Verify yt-dlp is installed: `which yt-dlp`
- Update the path in plugin settings
- Check file permissions

### "Download failed" Error
- Check PHP `shell_exec` is not disabled
- Verify FFmpeg is installed for format conversion
- Check upload directory permissions
- Enable logging in settings to see detailed errors

### Timeout Issues
- Increase timeout value in settings
- Check server PHP `max_execution_time`
- Consider server resources for large files

### Format Not Available
- Some formats may not be available for all videos
- Try selecting "Best Quality" with MP4 format
- Check video source restrictions

## Performance Considerations

- Downloads are processed server-side, consuming server resources
- Large files require adequate disk space
- Consider implementing rate limiting for public sites
- Monitor temporary directory size

## Uninstallation

The plugin cleans up temporary files on deactivation. To fully remove:

1. Deactivate the plugin
2. Delete the plugin files
3. Manually remove `/wp-uploads/yt-dlp-downloads/` if desired

## License

GPL v2 or later

## Credits

- Built with [yt-dlp](https://github.com/yt-dlp/yt-dlp)
- Requires FFmpeg for format conversion

## Support

For issues related to:
- **Plugin bugs**: Check WordPress debug logs
- **yt-dlp issues**: Visit [yt-dlp GitHub](https://github.com/yt-dlp/yt-dlp)
- **Specific sites**: Check yt-dlp supported sites list

## Changelog

### Version 1.0.0
- Initial release
- Basic download functionality
- Admin settings page
- Frontend shortcode with AJAX
- Security features implemented
