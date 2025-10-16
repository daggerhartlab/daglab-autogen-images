<?php

namespace DagLabAutogenImages;

class Plugin {
	/**
	 * Track initialization.
	 * @var bool
	 */
	protected bool $init = false;

	/**
	 * Plugin singleton instance.
	 * @var Plugin
	 */
	protected static $instance;

	/**
	 * Plugin constructor.
	 *
	 * Private to enforce the singleton.
	 */
	private function __construct() {}

	/**
	 * Get the singleton instance
	 * @return self
	 */
	public static function instance(): self {
		if (is_null(self::$instance)) {
			self::$instance = new static;
		}

		return self::$instance;
	}

	/**
	 * Bootstrap the plugin
	 * @return void
	 */
	public static function bootstrap(): void {
		$plugin = static::instance();

		// Prevent multiple initializations.
		if ($plugin->init) {
			return;
		}

		$plugin->init = true;

		// Register hooks - Most things we need to do will occur after plugins have been loaded
		add_action('plugins_loaded', [ $plugin, 'wpPluginsLoaded' ] );
	}

	/**
	 * Get plugin option value with compatibility for both multisite and single site
	 * @param string $option_name
	 * @param mixed $default
	 * @return mixed
	 */
	public function getOption(string $option_name, $default = false) {
		if (is_multisite()) {
			return get_site_option($option_name, $default);
		}
		return get_option($option_name, $default);
	}

	/**
	 * Update plugin option value with compatibility for both multisite and single site
	 * @param string $option_name
	 * @param mixed $value
	 * @return bool
	 */
	public function updateOption(string $option_name, $value): bool {
		if (is_multisite()) {
			return update_site_option($option_name, $value);
		}
		return update_option($option_name, $value);
	}

	/**
	 * Get the required capability for managing plugin settings
	 * @return string
	 */
	public function getRequiredCapability(): string {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}

	/**
	 * Get the admin URL for plugin settings
	 * @param string $path
	 * @return string
	 */
	public function getAdminUrl(string $path = 'settings.php'): string {
		if (is_multisite()) {
			return network_admin_url($path);
		}
		return admin_url($path);
	}

	/**
	 * Callback for plugins_loaded hook
	 * @return void
	 */
	public function wpPluginsLoaded(): void {
		// Creates settings page to turn auto generation on/off
		\DagLabAutogenImages\Admin\SettingsPage::bootstrap();

		/**
		 * Don't do anything if plugin features are not enabled
		 * @see "Network Admin > Settings > Autogenerate Images" (multisite) or "Settings > Autogenerate Images" (single site)
		 */
		$plugin_features_enabled = $this->getOption('daglab_autogenerate_images', '0');

		if($plugin_features_enabled !== '1') {
			return;
		}

		// Hooks into attachment metadata generation to delete image sizes after creation or editing
		\DagLabAutogenImages\Hook\AttachmentMetadata::bootstrap();

		// Hooks into template redirect process in order to intercept 404 response to check if images need to be generated
		\DagLabAutogenImages\Hook\TemplateRedirect::bootstrap();

		// Hooks into WP Smush filter to disable image sub-size smushing
		\DagLabAutogenImages\Hook\WpSmushMediaImage::bootstrap();
	}
}
