<?php

namespace DagLabAutogenImages\Hook;

/**
 * Hooks into WP Smush filter to disable image sub-size smushing
 */
class WpSmushMediaImage {
	public static function bootstrap(): void {
		$self = new self;

		/**
		 * Tells Smush plugin not to smush subsizes, since the files don't exist by default
		 * Note: The plugin seems to ignore the options for which sizes to Smush, so the callback turns all sizes off
		 */
		add_filter('wp_smush_media_image', [$self, 'filterWpSmushMediaImage'], 10, 2);
	}

	/**
	 * Callback for `wp_smush_media_image` filter
	 *
	 * @param bool $current The current value of the filter (Allow smush or not)
	 * @param string $key The image size key that is going to potentially be smushed
	 *
	 * @return bool
	 */
	public function filterWpSmushMediaImage($current, $key) {
		if($key != 'full' && $key != 'wp_scaled') {
			return false;
		}
		return $current;
	}
}

