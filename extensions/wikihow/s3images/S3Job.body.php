<?

if (!defined('MEDIAWIKI')) exit;

class UploadS3FileJob extends Job {

	public function __construct( Title $title, array $params, $id = 0 ) {
        parent::__construct( 'UploadS3FileJob', $title, $params, $id );
    }

    /**
     * Execute this job to upload an file to S3 from the local filesystem
     *
     * @return bool
     */
    public function run() {
        $file = $this->params['file'];
        $bucket = $this->params['bucket'];
        $uploadPath = $this->params['uploadPath'];
		$mimeType = $this->params['mimeType'];

		AwsFiles::uploadFile($file, $bucket, $uploadPath, $mimeType);

		return true;
	}

}

class DeleteS3FileJob extends Job {

	public function __construct( Title $title, array $params, $id = 0 ) {
        parent::__construct( 'DeleteS3FileJob', $title, $params, $id );
    }

    /**
     * Execute this job to delete an S3 file
     *
     * @return bool
     */
    public function run() {
        $bucket = $this->params['bucket'];
        $deletePath = $this->params['path'];

		AwsFiles::deleteFile($bucket, $deletePath);

		return true;
	}

}

