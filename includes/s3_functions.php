<?php
require __DIR__ . '/../vendor/autoload.php';

use Aws\Sdk;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// Function to create an S3 client with credentials from Secrets Manager
function createS3Client()
{
    try {
        $sdk = new Sdk([
            'region' => 'us-east-1', // Replace with your AWS region
            'version' => 'latest',
        ]);

        $secretsManager = $sdk->createSecretsManager();
        $secretName = 'MyS3Credentials';

        $result = $secretsManager->getSecretValue([
            'SecretId' => $secretName,
        ]);

        if (isset($result['SecretString'])) {
            $secret = json_decode($result['SecretString'], true);

            $accessKey = $secret['AWS_ACCESS_KEY_ID'];
            $secretKey = $secret['AWS_SECRET_ACCESS_KEY'];
            $bucketName = $secret['S3_BUCKET_NAME'];

            $s3Client = new S3Client([
                'region' => 'us-east-1',
                'version' => 'latest',
                'credentials' => [
                    'key'    => $accessKey,
                    'secret' => $secretKey,
                ],
            ]);

            return [$s3Client, $bucketName];
        } else {
            throw new Exception("Secret string not found.");
        }
    } catch (AwsException $e) {
        die("Error retrieving secret from AWS: " . htmlspecialchars($e->getMessage()));
    } catch (Exception $e) {
        die("An error occurred: " . htmlspecialchars($e->getMessage()));
    }
    return null;
}

// Function to upload an object to S3
function uploadToS3($fileTmpName, $fileName, $folder)
{
    [$s3Client, $bucketName] = createS3Client();
    
    try {
        $result = $s3Client->putObject([
            'Bucket' => $bucketName,
            'Key'    => "$folder/$fileName",
            'SourceFile' => $imageTmpPath,
            'ACL'    => 'public-read',
        ]);
        return $result['ObjectURL']; // Return the file URL on success
    } catch (AwsException $e) {
        die("Error uploading file: " . htmlspecialchars($e->getMessage()));
    }
}

// Function to retrieve an object from S3
function getFromS3($imageName)
{
    [$s3Client, $bucketName] = createS3Client();
    
    try {
        $result = $s3Client->getObject([
            'Bucket' => $bucketName,
            'Key'    => 'user_images/' . basename($imageName),
        ]);
        return $result['Body']; // Returns the file content as a stream
    } catch (AwsException $e) {
        die("Error retrieving file: " . htmlspecialchars($e->getMessage()));
    }
}

// Function to generate a pre-signed URL for a private S3 object
function getPresignedUrl($key, $expiresIn = '+20 minutes')
{
    [$s3Client, $bucketName] = createS3Client();
    
    // Create a pre-signed URL
    $cmd = $s3Client->getCommand('GetObject', [
        'Bucket' => $bucketName,
        'Key'    => $key,
    ]);

    // Generate the pre-signed URL
    $request = $s3Client->createPresignedRequest($cmd, $expiresIn);
    return (string) $request->getUri(); // Return the URL as a string
}


?>
