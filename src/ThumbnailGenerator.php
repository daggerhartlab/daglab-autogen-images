<?php

namespace DagLabAutogenImages;

use DagLabAutogenImages\ImageRequest;

/**
 * Handles functionality to generate thumbnails based on a given image request
 */
class ThumbnailGenerator {
	/**
	 * The image request object that triggered a thumbnail to be generated
	 * @var \DagLabAutogenImages\ImageRequest
	 */
	private ImageRequest $imageRequest;

	/**
	 * The thumbnail size registered with WP core that will be used to generate the thumbnail
	 * Note: We added the `name` key, since WP stores these as array keys in a larger array
	 * @var array
	 */
	private array $thumbnailSize = [
		'name' => '',
		'width' => '',
		'height' => '',
		'crop' => false,
	];

	/**
	 * The metadata returned after generating the thumbnail
	 * @see wp_create_image_subsizes()
	 * @var array
	 */
	private array $metadata;

	/**
	 * Thumbnail generator constructor
	 * @param \DagLabAutogenImages\ImageRequest $imageRequest
	 */
	public function __construct(ImageRequest $imageRequest) {
		$this->imageRequest = $imageRequest;
	}

	/**
	 * Generate a single thumbnail based on the best matching thumbnail size registered with WP
	 * @return void
	 */
	public function generateThumbnail() {
		$this->setThumbnailSize();

		if(!$this->hasThumbnailSize()) {
			/**
			 * Special case: The thumbnail dimensions requested are the same as the parent image dimensions.
			 */
			if(
				$this->imageRequest->getParentWidth() == $this->imageRequest->getWidth() &&
				$this->imageRequest->getParentHeight() == $this->imageRequest->getHeight() &&
				file_exists($this->imageRequest->getParentFilepath())
			) {
				$this->imageRequest->setFilepath($this->imageRequest->getParentFilepath());
				$this->metadata = [
					'mime-type' => wp_get_image_mime($this->imageRequest->getParentFilepath()),
					'filesize' => filesize($this->imageRequest->getParentFilepath()),
				];
			}

			return;
		}

		$editor = wp_get_image_editor($this->imageRequest->getParentFilepath());
		$this->metadata = $editor->make_subsize( $this->thumbnailSize );

		$this->maybeSmushImage();
	}

	/**
	 * Set the thumbnailSize property based on the image request and the best match registered with WP
	 * @return void
	 */
	private function setThumbnailSize() {
		foreach(wp_get_registered_image_subsizes() as $key => $size) {
			if(
				$this->imageRequest->getWidth() === $size['width'] ||
				$this->imageRequest->getHeight() === $size['height']
			) {
				$resize_dimensions = image_resize_dimensions(
					$this->imageRequest->getParentWidth(),
					$this->imageRequest->getParentHeight(),
					$size['width'],
					$size['height'],
					$size['crop']
				);

				$resize_width = $resize_dimensions[4] ?? 0;
				$resize_height = $resize_dimensions[5] ?? 0;

				if(
					$resize_width === $this->imageRequest->getWidth() &&
					$resize_height === $this->imageRequest->getHeight()
				) {
					$this->thumbnailSize = $size;
					$this->thumbnailSize['name'] = $key;
					break;
				}
			}
		}
	}

	/**
	 * Determines whether a valid thumbnail size has been identified for the image request
	 * @return bool
	 */
	private function hasThumbnailSize(): bool {
		return !empty($this->thumbnailSize['name']);
	}

	/**
	 * Get the mime type of the generated thumbnail
	 * @return string
	 */
	public function getMimeType() {
		return $this->metadata['mime-type'] ?? '';
	}

	/**
	 * Get the file size of the generated thumbnail
	 * @return string
	 */
	public function getFileSize() {
		if(!file_exists($this->imageRequest->getFilepath())) {
			return 0;
		}
		return filesize($this->imageRequest->getFilepath());
	}

	/**
	 * Smush newly generated thumbnail if necessary
	 * @return void
	 */
	private function maybeSmushImage() {
		# Make sure we have identified a thumbnail size to be generated
		if(!$this->hasThumbnailSize()) {
			return;
		}

		# Make sure `wp-smushit` plugin is active
		if(!class_exists('WP_Smush') || !class_exists('Smush\Core\Media\Media_Item_Cache')) {
			return;
		}

		# Remove media item from Smush cache, which has a version with no sizes defined to be smushed
		\Smush\Core\Media\Media_Item_Cache::get_instance()->remove($this->imageRequest->getAttachmentId());

		# Tell Smush to allow smushing of the thumbnail size we are generating
		add_filter('wp_smush_media_image', [$this, 'filterWpSmushMediaImage'], 100, 2);

		# Smush the image - Note the `$return` parameter is true so that a JSON success response is not sent
		\WP_Smush::get_instance()->core()->mod->smush->smush_single($this->imageRequest->getAttachmentId(), true);

		# Remove the previously set filter, just to be safe
		remove_filter('wp_smush_media_image', [$this, 'filterWpSmushMediaImage'], 100);
	}

	/**
	 * Callback for the `wp_smush_media_image` filter hook
	 *
	 * By default, we are disallowing all thumbnail sizes from being smushed, since they don't exist on the server.
	 * However, we want to allow the specific size being generated so it can be smushed upon being created.
	 *
	 * @see \DagLabAutogenImages\Hook\WpSmushMediaImage
	 *
	 * @param bool $current The current value of the filter (Allow smush or not)
	 * @param string $key The image size key that is going to potentially be smushed
	 *
	 * @return bool
	 */
	public function filterWpSmushMediaImage($current, $key) {
		if($key != $this->thumbnailSize['name']) {
			return false;
		}
		return true;
	}
}
