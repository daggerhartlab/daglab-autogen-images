<?php

namespace DagLabAutogenImages\Hook;

/**
 * Hooks into attachment metadata generation to delete image sizes after creation or editing
 */
class AttachmentMetadata {
	/**
	 * Add hooks
	 * @return void
	 */
	public static function bootstrap(): void {
		$self = new static;

		/**
		 * Delete image sub-sizes after original image is uploaded
		 * @see wp-admin/includes/image.php:185
		 */
		add_filter('wp_generate_attachment_metadata', [$self, 'wp_generate_attachment_metadata'], 10, 3);

		/**
		 * Delete image sub-sizes after image has been edited via the media editor
		 * @see wp-admin/includes/image-edit.php:1101
		 */
		add_filter('wp_update_attachment_metadata', [$self, 'wp_update_attachment_metadata'], 10, 3);
	}

	/**
	 * Delete image sub-sizes after original image is uploaded
	 * @see wp-admin/includes/image.php:185
	 *
	 * @param $metadata
	 * @param $attachment_id
	 * @param $mode
	 *
	 * @return array
	 *
	 */
	public function wp_generate_attachment_metadata($metadata, $attachment_id, $mode): array {
		// Make sure we are in the right context
		if($mode != 'create') {
			return $metadata;
		}

		$this->deleteSubsizes($attachment_id, $metadata);

		return $metadata;
	}

	/**
	 * Delete image sub-sizes after image has been edited via the media editor
	 * @see wp-admin/includes/image-edit.php:1101
	 *
	 * @param $metadata
	 * @param $attachment_id
	 *
	 * @return array
	 */
	public function wp_update_attachment_metadata($metadata, $attachment_id): array {
		// Make sure we are in the right context
		$action = $_REQUEST['action'] ?? '';
		$do = $_REQUEST['do'] ?? '';

		if($action !== 'image-editor' || $do !== 'save') {
			return $metadata;
		}

		$this->deleteSubsizes($attachment_id, $metadata);

		return $metadata;
	}

	/**
	 * Deletes all subsizes of a given attachment
	 *
	 * @param $attachment_id
	 * @param $metadata
	 *
	 * @return void
	 */
	private function deleteSubsizes($attachment_id, $metadata): void {
		$filepath = get_attached_file($attachment_id);
		$dirname  = pathinfo( $filepath, PATHINFO_DIRNAME );

		if(!$filepath || !$dirname) {
			return;
		}

		if(empty($metadata['sizes'])) {
			return;
		}

		foreach($metadata['sizes'] as $sizeData) {
			if(empty($sizeData['file'] || empty($sizeData['mime-type'] || ! str_starts_with($sizeData['mime-type'], 'image')))) {
				continue;
			}

			$subsizeFile = $dirname . '/' . $sizeData['file'];

			if(file_exists($subsizeFile)) {
				unlink($subsizeFile);
			}
		}
	}
}
