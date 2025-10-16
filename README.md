# Daggerhart Lab - Autogenerate Images

A WordPress plugin that prevents automatic generation of image sub-sizes during upload and instead generates them on-demand when first requested. This saves significant disk space by only creating thumbnails that are actually used.

## Features

- **On-demand thumbnail generation**: Creates image sizes only when requested
- **Disk space optimization**: Eliminates unused thumbnails
- **Dual compatibility**: Works with both multisite and single site WordPress installations
- **WP Smush integration**: Automatically optimizes generated thumbnails
- **Admin interface**: Simple toggle to enable/disable functionality

## Requirements

- WordPress 5.0+
- PHP 8.0+

## Installation

1. Upload the plugin files to `/wp-content/plugins/daglab-autogen-images/`
2. Activate the plugin through the WordPress admin
3. Configure settings in Network Admin (multisite) or Settings (single site)

## Configuration

### Via Admin Interface
- **Multisite**: Go to Network Admin → Settings → Autogen Images
- **Single Site**: Go to Settings → Autogen Images

### Via WP-CLI

**Multisite installations:**
```bash
# Enable
wp site option update daglab_autogenerate_images 1

# Disable
wp site option update daglab_autogenerate_images 0
```

**Single site installations:**
```bash
# Enable
wp option update daglab_autogenerate_images 1

# Disable
wp option update daglab_autogenerate_images 0
```

## How It Works

1. Plugin prevents WordPress from generating thumbnails during upload
2. When a thumbnail is requested via URL, the plugin intercepts 404 responses
3. If the requested image corresponds to a registered WordPress image size, it's generated on-demand
4. The thumbnail is served immediately and cached for future requests
5. If WP Smush is active, newly generated thumbnails are automatically optimized

## License

GPL v2 or later
