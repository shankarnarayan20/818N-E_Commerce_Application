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
        $secretName = 'localmysql';

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
            $cdn_url = "";

            // Create a new mysqli connection
            $con = new mysqli($host, $username, $password, $db_name, $port);

            // Check the connection
            if ($con->connect_error) {
                die("Connection failed: " . $con->connect_error);
            }

            // Set SSL options
            $ssl_options = [
                MYSQLI_OPT_SSL_VERIFY_SERVER_CERT => true,
                MYSQLI_OPT_SSL_CA => '/etc/ssl/certs/us-east-1-bundle.pem', // Path to your CA file
            ];

            // Apply SSL options to the connection
            foreach ($ssl_options as $option => $value) {
                if (!mysqli_options($con, $option, $value)) {
                    die('Could not set option: ' . mysqli_error($con));
                }
            }

            // Reconnect with SSL options
            if (!$con->real_connect($host, $username, $password, $db_name, $port, null, MYSQLI_CLIENT_SSL)) {
                die('Connect Error (' . $con->connect_errno . ') ' . $con->connect_error);
            }

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
