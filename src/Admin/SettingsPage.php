<?php

namespace DagLabAutogenImages\Admin;

/**
 * Handles settings page functionality for autogenerate images plugin
 * Allows autogeneration to be turned on and off at the network level
 */
class SettingsPage {
	/**
	 * Register the appropriate hooks for the settings page to appear and function
	 * @return void
	 */
	public static function bootstrap(): void {
		$self = new static;

		if (is_multisite()) {
			// Register menu item under "Network Admin > Settings" and render HTML for the page
			add_action('network_admin_menu', [$self, 'registerMenuItem'], 100);
			// Update settings when form is submitted
			add_action('network_admin_edit_daglab_autogenerate_images_submit', [$self, 'SaveSettings']);
		} else {
			// Register menu item under "Settings" for single site
			add_action('admin_menu', [$self, 'registerMenuItem'], 100);
			// Update settings when form is submitted
			add_action('admin_post_daglab_autogenerate_images_submit', [$self, 'SaveSettings']);
		}
	}

	/**
	 * Register menu item under "Network Admin > Settings" (multisite) or "Settings" (single site)
	 * @return void
	 */
	public function registerMenuItem(): void {
		$plugin = \DagLabAutogenImages\Plugin::instance();
		$parent_slug = is_multisite() ? 'settings.php' : 'options-general.php';
		
		add_submenu_page(
			$parent_slug,
			'Autogenerate Images',
			'Autogenerate Images',
			$plugin->getRequiredCapability(),
			'autogenerate-images-settings',
			[$this, 'renderSettingsPage']
		);
	}

	/**
	 * Render HTML for the settings page
	 * @return void
	 */
	public function renderSettingsPage(): void {
		$plugin = \DagLabAutogenImages\Plugin::instance();
		$is_active = $plugin->getOption('daglab_autogenerate_images', '0');
		$is_multisite = is_multisite();
		
		// Determine form action URL based on environment
		if ($is_multisite) {
			$form_action = add_query_arg('action', 'daglab_autogenerate_images_submit', 'edit.php');
		} else {
			$form_action = admin_url('admin-post.php');
		}
		?>
		<div class="wrap">
			<h1>Autogenerate Images</h1>

			<?php if(isset($_GET['settings-updated']) && $_GET['settings-updated'] === '1') { ?>
				<div class="notice updated"><p>Settings updated</p></div>
			<?php } ?>

			<p>This setting turns on/off the main features of the <code>daglab-autogenerate-images</code> plugin, which are:</p>

			<ul style="list-style: disc; list-style-position: inside;">
				<li>Prevent derivative images from being generated when images are uploaded</li>
				<li>Autogenerate a specific derivative image size whenever an HTTP request is made for it</li>
				<li>Modify WP Smush plugin to not try to smush derivative images that don't exist (avoiding errors)</li>
			</ul>
			<hr>
			<p>The following WP-CLI commands will also update the same setting being toggled via the form below:</p>
			<?php if ($is_multisite): ?>
				<p>Turn autogenerate images ON: <code>wp site option update daglab_autogenerate_images 1</code></p>
				<p>Turn autogenerate images OFF: <code>wp site option update daglab_autogenerate_images 0</code></p>
			<?php else: ?>
				<p>Turn autogenerate images ON: <code>wp option update daglab_autogenerate_images 1</code></p>
				<p>Turn autogenerate images OFF: <code>wp option update daglab_autogenerate_images 0</code></p>
			<?php endif; ?>
			<hr>
			<form method="POST" action="<?= $form_action ?>">
				<?php wp_nonce_field( 'daglab_autogenerate_images_submit_nonce' ); ?>
				<?php if (!$is_multisite): ?>
					<input type="hidden" name="action" value="daglab_autogenerate_images_submit">
				<?php endif; ?>

				<label>
					<input name="daglab_autogenerate_images" type="checkbox" value="1" <?php checked( '1', $is_active ) ?>> Yes, enable autogenerate image features
				</label>

				<?php submit_button(); ?>
			</form>
		</div>
	<?php
	}

	/**
	 * Update settings when form is submitted and redirect back to settings page
	 * @return void
	 */
	public function SaveSettings(): void {
		// First, verify that we are being referred by a valid form submission with correct nonce
		check_admin_referer( 'daglab_autogenerate_images_submit_nonce' );

		$plugin = \DagLabAutogenImages\Plugin::instance();

		// Get the submitted value - whether to enable plugin features
		$daglab_autogenerate_images = isset($_POST['daglab_autogenerate_images']) && $_POST['daglab_autogenerate_images'] === '1' ? '1' : '0';

		// Update the option accordingly (multisite or single site)
		$plugin->updateOption('daglab_autogenerate_images', $daglab_autogenerate_images);

		// Redirect back to settings page with "updated" indicator
		$admin_page = is_multisite() ? 'settings.php' : 'options-general.php';
		wp_safe_redirect(
			add_query_arg(
				[
					'page' => 'autogenerate-images-settings',
					'settings-updated' => true
				],
				$plugin->getAdminUrl($admin_page)
			)
		);

		exit;
	}
}
