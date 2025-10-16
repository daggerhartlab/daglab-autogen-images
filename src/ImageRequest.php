<?php

namespace DagLabAutogenImages;

/**
 * Handles the detection of filepath, thumbnail dimensions, and parent file data when given a request for an image thumbnail
 */
class ImageRequest {
	/**
	 * The request URI that we are processing
	 * @var string
	 */
	private string $requestUri;

	private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

	/**
	 * Whether the request we are processing is for an image (as determined by this class)
	 * @var bool
	 */
	private bool $isImage = false;

	/**
	 * Whether the request is for a thumbnail image that has been resized (as opposed to an original image)
	 * Ex. If the request URI is `/wp-content/uploads/2023/03/my-photo.jpg`, then $isThumbnail is `false`
	 * Ex. If the request URI is `/wp-content/uploads/2023/03/my-photo-300x200.jpg`, then $isThumbnail is `true`
	 * @var bool
	 */
	private bool $isThumbnail = false;

	/**
	 * The part of the request URI between the core uploads directory and the filename
	 * Ex. If the request URI is `/wp-content/uploads/2023/03/my-photo.jpg`, then $uploadsSubpath is `2023/03`
	 * @var string
	 */
	private string $uploadSubpath;

	/**
	 * The part of the request URI between the subpath and extension
	 * Ex. If the request URI is `/wp-content/uploads/2023/03/my-photo.jpg`, then $filename is `my-photo`
	 * @var string
	 */
	private string $filename;

	/**
	 * The parent filename that a thumbnail is generated from
	 *  Ex. If the request URI is `/wp-content/uploads/2023/03/my-photo-300x200.jpg`, then $parentFilename is `my-photo`
	 * @var string
	 */
	private string $parentFilename;

	/**
	 * The complete file path for the parent image for a given thumbnail request
	 * @var string
	 */
	private string $parentFilepath;

	/**
	 * The width of the parent image from which the requested thumbnail is generated
	 * @var int
	 */
	private int $parentWidth;

	/**
	 * The height of the parent image from which the requested thumbnail is generated
	 * @var int
	 */
	private int $parentHeight;

	/**
	 * The underlying attachment ID for the requested thumbnail image
	 * @var int
	 */
	private int $attachmentId = 0;

	/**
	 * The width of the thumbnail being requested
	 *  Ex. If the request URI is `/wp-content/uploads/2023/03/my-photo-300x200.jpg`, then $width is `300`
	 * @var int
	 */
	private int $width;

	/**
	 * The height of the thumbnail being requested
	 *  Ex. If the request URI is `/wp-content/uploads/2023/03/my-photo-300x200.jpg`, then $height is `200`
	 * @var int
	 */
	private int $height;

	/**
	 * The file extension part of the request URI
	 * Ex. If the request URI is `/wp-content/uploads/2023/03/my-photo.jpg`, then $extension is `jpg`
	 * @var string
	 */
	private string $extension;

	/**
	 * The complete file path for the thumbnail being requested
	 * @var string
	 */
	private string $filepath;

	/**
	 * ImageRequest constructor
	 * @param string $requestUri Typically the value of `$_SERVER['REQUEST_URI']`
	 */
	public function __construct(string $requestUri) {
		$this->requestUri = $requestUri;

		$this->setPathData();

		if($this->isImage) {
			$this->setThumbnailData();
		}

		if($this->isThumbnail) {
			# This file contains the `wp_create_image_subsizes` and `image_resize_dimensions` functions
			require_once ABSPATH . '/wp-admin/includes/image.php';

			$this->setFilepath();
			$this->setParentData();
		}
	}

	/**
	 * Match the request URI against known patterns and set various properties to identify the file being requested
	 * @return void
	 */
	private function setPathData(): void {
		$uploads_dir = str_replace(home_url(), '', wp_upload_dir()['baseurl']);
		$allowed_extensions = join('|', $this->allowedExtensions);
		$pattern = '^' . $uploads_dir . '/(.*)\.(' . $allowed_extensions . ')$';
		$pattern = str_replace('/', '\/', $pattern);
		$pattern = "/$pattern/i";

		$matches = [];
		$this->isImage = preg_match($pattern, $this->requestUri, $matches);

		$this->filename = pathinfo($this->requestUri, PATHINFO_FILENAME);
		$this->extension = pathinfo($this->requestUri, PATHINFO_EXTENSION);

		$this->uploadSubpath = str_replace($uploads_dir . '/', '', $this->requestUri);
		$this->uploadSubpath = str_replace( $this->filename . '.' . $this->extension, '', $this->uploadSubpath);

		$this->filename = urldecode($this->filename);
	}

	/**
	 * Match the requested filename against known patterns and set various properties to help identify the thumbnail size
	 * being requested
	 * @return void
	 */
	private function setThumbnailData(): void {
		$matches = [];

		if(preg_match("/(.*)-(\d+)x(\d+)$/", $this->filename, $matches)) {
			$this->parentFilename = $matches[1] ?? '';
			$this->parentFilename = urldecode($this->parentFilename);
			$this->width = (int) $matches[2] ?? '';
			$this->height = (int) $matches[3] ?? '';

			$this->isThumbnail = true;
		}
	}

	/**
	 * Set the filepath property
	 * @param string $path
	 * @return void
	 */
	public function setFilepath(string $path = ''): void {
		if($path) {
			$this->filepath = $path;
			return;
		}
		$this->filepath = sprintf('%s.%s',
			join('/', [
				wp_upload_dir()['basedir'],
				$this->uploadSubpath,
				$this->filename,
			]),
			$this->extension
		);

		$this->filepath = str_replace('//', '/', $this->filepath);
	}

	/**
	 * Get the filepath property
	 * @return string
	 */
	public function getFilepath(): string {
		return $this->filepath;
	}

	/**
	 * Set properties pertaining to the parent image from which the requested thumbnail is generated
	 * @return void
	 */
	private function setParentData(): void {
		global $wpdb;

		$this->parentFilepath = sprintf('%s.%s',
			join('/',
				[
					wp_get_upload_dir()['basedir'],
					$this->uploadSubpath,
					$this->parentFilename
				]
			),
			$this->extension
		);

		$this->parentFilepath = str_replace('//', '/', $this->parentFilepath);

		$parentFileUrl = sprintf('%s.%s',
			join('/',
				[
					wp_get_upload_dir()['baseurl'],
					$this->uploadSubpath,
					$this->parentFilename
				]
			),
			$this->extension
		);

		$this->attachmentId = attachment_url_to_postid($parentFileUrl);

		/**
		 * If no attachment is found, try appending `-scaled` to the filename which happens for large images
		 */
		if(!$this->attachmentId) {
			$parentFileUrl = sprintf('%s-scaled.%s',
				join('/',
					[
						wp_get_upload_dir()['baseurl'],
						$this->uploadSubpath,
						$this->parentFilename
					]
				),
				$this->extension
			);
			$this->attachmentId = attachment_url_to_postid($parentFileUrl);
		}

		/**
		 * If still no attachment ID is found, see if we can isolate a single post ID that has the filename
		 * we are looking for in its `_wp_attachment_metadata` value.
		 *
		 * This seems less reliable/obscure than using WP core functions to get the ID, so it's treated as a last resort.
		 * Note that we give up if more than one attachment is found, since we don't want to serve the wrong image.
		 *
		 * This case is needed whenever the image is edited via WP Admin and the resulting image size set is a mix of
		 * the original and the edited version of the image. For example, if a large image is cropped in the image
		 * editor to a smaller size that wouldn't have its own "Large" size thumbnail, WP keeps the original "Large" size
		 * as an option that can be used when editing content.
		 */
		if(!$this->attachmentId) {
			$filename = sanitize_text_field($this->filename);
			$extension = sanitize_text_field($this->extension);

			$query = "
				SELECT post_id FROM {$wpdb->prefix}postmeta
				WHERE meta_key='_wp_attachment_metadata' AND meta_value LIKE %s
			";
			$query = $wpdb->prepare($query, "%{$filename}.{$extension}%");

			$results = $wpdb->get_results($query);

			if(count($results) === 1) {
				$this->attachmentId = $results[0]->post_id;
			}
		}

		/**
		 * In some cases, we have the `-scaled` version but not the original image. When this happens, we need the
		 * original image in place because this is what WP uses to create derivatives from.
		 *
		 * It is sufficient to symlink to the `-scaled` version from the original image location. This would save a lot
		 * of disk space and does work locally, but Pantheon does not implement the `symlink` PHP funciton.
		 *
		 * Instead, we copy the `-scaled` version to the original image location so that WP can have the original file
		 * in the location it expects. Technically, this is not the true original file, which was likely much larger.
		 * But the `-scaled` version works in place of the original because the aspect ratio is preserved when creating
		 * the `-scaled` version, hence this substitution generates derivatives reliably and does save some disk space.
		 */
		if($this->attachmentId && !file_exists($this->parentFilepath)) {

			$scaledFilepath = sprintf('%s-scaled.%s',
				join('/',
					[
						wp_get_upload_dir()['basedir'],
						$this->uploadSubpath,
						$this->parentFilename
					]
				),
				$this->extension
			);

			/**
			 * This is not currently used, but left in place to indicate/document the symlink approach
			 *
			 * As long as we check that the full path exists first, we can use a relative filename for the symlink to
			 * make it server-agnostic (i.e. the full file path from the server root is not referenced in the link)
			 */
			$scaledFilename = sprintf('%s-scaled.%s', $this->parentFilename, $this->extension);

			if(file_exists($scaledFilepath)) {
				# Copy the scaled file to the original file location
				copy($scaledFilepath, $this->parentFilepath);

				# Inactive: create a symlink in the original file location whose target is the scaled version
				#symlink($scaledFilename, $this->parentFilepath);
			}
		}

		$this->parentWidth = 0;
		$this->parentHeight = 0;

		/**
		 * Attempt to correct parent filepath we have an attachment ID but parent filepath is not what we expect from the URL
		 */
		if(!file_exists($this->parentFilepath) && $this->attachmentId) {
			$this->parentFilepath = get_attached_file($this->attachmentId);
		}

		if(file_exists($this->parentFilepath)) {
			$imageSize = wp_getimagesize($this->parentFilepath);
			$this->parentWidth = $imageSize[0] ?? 0;
			$this->parentHeight = $imageSize[1] ?? 0;
		}
	}

	public function getParentWidth(): int {
		return $this->parentWidth;
	}

	public function getParentHeight(): int {
		return $this->parentHeight;
	}

	/**
	 * Get the width property
	 * @return int
	 */
	public function getWidth(): int {
		return $this->width;
	}

	/**
	 * Get the height property
	 * @return int
	 */
	public function getHeight(): int {
		return $this->height;
	}

	/**
	 * Get the attachmentId property
	 * @return int
	 */
	public function getAttachmentId(): int {
		return $this->attachmentId;
	}

	/**
	 * Get the parentFilepath property
	 * @return string
	 */
	public function getParentFilepath(): string {
		return $this->parentFilepath;
	}
}
