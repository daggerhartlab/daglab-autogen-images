<?php

namespace DagLabAutogenImages\Hook;

use DagLabAutogenImages\ImageRequest;
use DagLabAutogenImages\ThumbnailGenerator;

/**
 * Implements WP core `template_redirect` hook callback(s)
 */
class TemplateRedirect {
	public static function bootstrap(): void {
		$self = new static;

		add_action('template_redirect', [ $self, 'interceptImage404' ] );
	}
	/**
	 * If we experience a 404 response code when attempting to serve a request for an image, then:
	 *   - Attempt to locate the parent attachment and the appropriate thumbnail size
	 *   - If successful, generate the appropriate thumbnail image
	 *   - Set headers for 200 status code and appropriate image content
	 *   - Sever the image in response to the request
	 *
	 * Note: This is put in place to save disk space resources and to make it unnecessary to generate every possible size
	 * for every image uploaded. Instead, we generate the images "on the fly" when we detect that a missing thumbnail
	 * size is requested to be served.
	 *
	 * @return void
	 */
	public function interceptImage404(): void {
		# Do nothing if the current request doesn't have a 404 status code
		if(!is_404()) {
			return;
		}

		$imageRequest = new ImageRequest($_SERVER['REQUEST_URI']);

		# Do nothing if we aren't able to locate an appropriate attachment item
		if(!$imageRequest->getAttachmentId()) {
			return;
		}

		$thumbnailGenerator = new ThumbnailGenerator($imageRequest);

		# Generate the appropriate thumbnail
		$thumbnailGenerator->generateThumbnail();

		# Make sure we have what we need before fulfilling the request
		$mimeType = $thumbnailGenerator->getMimeType();
		$fileSize = $thumbnailGenerator->getFileSize();

		if(!$mimeType || !str_starts_with($mimeType, 'image/') || !$fileSize || !file_exists($imageRequest->getFilepath())) {
			return;
		}

		# Send the appropriate status and image content headers
		status_header(200);
		header(sprintf('Content-Type: %s', $mimeType));
		header(sprintf('Content-Length: %s', $fileSize));

		echo file_get_contents($imageRequest->getFilepath());
		exit;
	}
}
