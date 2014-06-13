<?

if (!defined('MEDIAWIKI')) die();

class S3Images {

	/**
	 * Hook called when a new file is uploaded.
	 */
// Reuben note to self: test when new file uploaded via wikiphoto
	public static function onFileUpload( $localFile, $reupload, $titleExists ) {
		global $wgLanguageCode;
		$jobTitle = $localFile->getTitle();
		$buckets = array(WH_AWS_BUCKET_IMAGE_STORAGE, WH_AWS_BUCKET_IMAGE_BACKUPS);
		foreach ($buckets as $bucket) {
			$jobParams = array(
				'file' => $localFile->getLocalRefPath(),
				'bucket' => $bucket,
				'uploadPath' => "/images_$wgLanguageCode/" . $localFile->getRel(),
				'mimeType' => $localFile->getMimeType(),
			);
			$job = Job::factory('UploadS3FileJob', $jobTitle, $jobParams);
			JobQueueGroup::singleton()->push($job);
		}

		return true;
	}

/*
	// Called when a previously non-existent image thumbnail is created/transformed
	// Still called even when transform via 404 is on??
	public static function onFileTransformed( $file, $thumb, $tmpThumbPath, $thumbPath ) {
		return true;
	}

	// Called when the image file is moved on doh
	// Called on image delete
	// should we do thumbnail purge of S3 here?
	public static function onLocalFilePurgeThumbnails( $localFile, $archiveName ) {
		return true;
	}

	// Called on image file move
	// Called when image is uploaded
	public static function onNewRevisionFromEditComplete( $wikiPage, $nullRevision, $latest, $user ) {
		return true;
	}
*/

}

