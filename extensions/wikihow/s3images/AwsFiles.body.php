<?

if (!defined('MEDIAWIKI')) exit;

use Aws\Common\Aws;
use Guzzle\Http\EntityBody;

class AwsFiles {

	static $aws = null;

	// Create a new AWS object connected to the account we want.
	// Copied from maintenance/transcoding/...
	private static function getAws() {
		global $IP;
		if (is_null(self::$aws)) {
			// Create a service builder using a configuration file
			self::$aws = Aws::factory(array(
				'key'    => WH_AWS_BACKUP_ACCESS_KEY,
				'secret' => WH_AWS_BACKUP_SECRET_KEY,
				'region' => 'us-east-1'
			));
		}  
		return self::$aws;
	}
	
	// Get a reference to the AWS/S3 service connection
	// Taken from maintenance/transcoding/...
	private static function getS3Service() {
		$aws = self::getAws();
		return $aws->get('S3');
	}

	/**
	 * Uploads a file from a local filesystem path to S3.
	 *
	 * Created using documentation here:
	 * http://docs.aws.amazon.com/aws-sdk-php/guide/latest/service-s3.html
	 *
	 * @param string $filePath full path to local filesystem file
	 * @param string $bucket bucket on S3 of destination file
	 * @param string $uploadPath path/key on S3 of destination
	 * @param string $mimeType mime type of file to be returned with requests to S3
	 */
	public static function uploadFile($filePath, $bucket, $uploadPath, $mimeType) {
		$svc = self::getS3Service();

		$result = $svc->putObject(array(
			'Bucket' => $bucket,
			'Key'    => $uploadPath,
			'SourceFile' => $filePath,
			'ContentType' => $mimeType,
			'ACL'    => 'public-read'
		));

		$svc->waitUntil('ObjectExists', array(
			'Bucket' => $bucket,
			'Key'    => $uploadPath
		));

		if ($result['ObjectURL']) {
			print "Uploaded $filePath to s3://$bucket$uploadPath, url {$result['ObjectURL']}\n"; 
		} else {
			print "Error: uploading $filePath to s3://$bucket$uploadPath\n";
		}

		return true;
	}

	/**
	 * Deletes a file on S3.
	 *
	 * From: 
	 * http://docs.aws.amazon.com/aws-sdk-php/latest/class-Aws.S3.S3Client.html#_deleteObject
	 *
	 * Created using documentation here:
	 * http://docs.aws.amazon.com/aws-sdk-php/guide/latest/service-s3.html
	 *
	 * @param string $bucket bucket on S3 of destination file
	 * @param string $s3path path/key on S3 of destination
	 */
	public static function deleteFile($bucket, $s3path) {
		$svc = self::getS3Service();
		 
		$result = $svc->deleteObject(array(
			'Bucket' => $bucket,
			'Key'    => $s3path
		));

		if ($result->DeleteMarker) {
			print "Deleted S3 file: s3://$bucket$s3path\n";
		} else {
			print "Error deleting S3 file: s3://$bucket$s3path\n";
		}

		return true;
	}

}

