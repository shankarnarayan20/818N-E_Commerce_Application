<?php
require __DIR__ . '/../vendor/autoload.php'; // Adjust the path if necessary

use Aws\Sdk;
use Aws\Exception\AwsException;

// Function to create a database connection
function createDatabaseConnection()
{
    try {
        // Create an AWS SDK instance
        $sdk = new Sdk([
            'region' => 'us-east-1', // Replace with your region
            'version' => 'latest'
        ]);

        // Create a Secrets Manager client
        $secretsManager = $sdk->createSecretsManager();

        // Your secret name
        $secretName = 'MyDatabaseCredentials';

        // Retrieve the secret
        $result = $secretsManager->getSecretValue([
            'SecretId' => $secretName,
        ]);

        // Decode the secret JSON string into an array
        if (isset($result['SecretString'])) {
            $secret = json_decode($result['SecretString'], true);

            // Access your database credentials from the secret
            $host = $secret['DB_HOST'];
            $username = $secret['DB_USER'];
            $password = $secret['DB_PASS'];
            $db_name = $secret['DB_NAME'];
            $port = $secret['DB_PORT'];
            $cdn_url = $secret['CDN_URL'];

            $con = mysqli_init();
            mysqli_ssl_set($con, NULL, NULL, "/etc/ssl/certs/us-east-1-bundle.pem", NULL, NULL);
            $con->options(MYSQLI_CLIENT_SSL_VERIFY_SERVER_CERT, true);

            $con->real_connect($host, $username, $password, $db_name, $port);

            // Check the connection
            if ($con->connect_error) {
                die("Connection failed: " . $con->connect_error);
            }

            // After establishing connection
            // $result = $con->query("SHOW STATUS LIKE 'Ssl_cipher'");
            // $row = $result->fetch_array();
            // if (!empty($row[1])) {
            //     echo "SSL is enabled. Cipher in use: " . $row[1];
            // } else {
            //     echo "SSL is not enabled";
            // }

            return [$con, $cdn_url]; // Return the connection object
        }
    } catch (AwsException $e) {
        // Output error message if fails
        die("Error retrieving secret: " . htmlspecialchars($e->getMessage()));
    } catch (Exception $e) {
        die("An error occurred: " . htmlspecialchars($e->getMessage()));
    }

    return null; // Return null if the connection could not be established
}

// Example usage
[$con, $cdn_url] = createDatabaseConnection();

if (!$con) {
    throw new Exception('Unable to establish a connection with the database');
}
