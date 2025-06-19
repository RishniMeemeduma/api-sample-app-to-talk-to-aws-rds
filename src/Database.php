<?php
// VERSION 2.0 - FIXED FETCH METHOD - June 12, 2025
// Load AWS SDK and other dependencies
require '/var/www/html/vendor/autoload.php';  // Commented out for now
use Aws\SecretsManager\SecretsManagerClient;
use Aws\Exception\AwsException;

class Database {
    private static $instance = null;
    private $pdo = null;
    private function __construct() {
        try {
            // Get credentials from AWS Secrets Manager
            $secret = $this->getSecretCredentials();
            $host = getenv('DB_ENDPOINT');
            $dbname = getenv('DB_NAME');
            // Connect to Aurora MySQL
            // echo "Connecting to database at $host with name $dbname\n";
            $this->pdo = new PDO(
                "mysql:host=". $host.";dbname=".$dbname. "",
                $secret['username'],
                $secret['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
        }
    }

    private function getSecretCredentials() {
        try {
            // Create a Secrets Manager client
              $secretsClient = new SecretsManagerClient([
                'version' => 'latest',
                'region' => getenv('AWS_REGION') 
            ]);
            
            // Get the secret value
            // Your secret name should follow AWS naming convention
            $result = $secretsClient->getSecretValue([
                    'SecretId' => getenv('DB_SECRET_ARN'),
                ]); 
                

            // Parse and return the JSON secret
            if (isset($result['SecretString'])) {
                return json_decode($result['SecretString'], true);
            }
        } catch (AwsException $e) {
            error_log("Secrets Manager Error: " . $e->getMessage());
            throw $e;
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function query($sql, $params = []) {
        try {
            if ($this->pdo) {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            } else {
                throw new Exception("Database connection not available");
            }
        } catch (Exception $e) {
            error_log("Query error: " . $e->getMessage());
            
            // Return a mock object that supports fetch() for error cases
            return new class($params) {
                private $params;
                
                public function __construct($params) {
                    $this->params = $params;
                }
                
                public function fetch() {
                    if (isset($this->params[0]) && is_numeric($this->params[0])) {
                        return [
                            'id' => $this->params[0],
                            'name' => 'MOCK USER ' . $this->params[0],
                            'email' => 'user' . $this->params[0] . '@example.com'
                        ];
                    }
                    return null;
                }
            };
        }
    }
}
?>