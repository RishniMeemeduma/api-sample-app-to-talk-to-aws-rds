<?php
require_once 'Database.php';
require_once 'Cache.php';
class Api {
    private $db;
    private $cache;
    
    public function __construct() {
        // Initialize database connection
        try {
            $this->db = Database::getInstance();
            $this->cache = Cache::getInstance();

            //  echo "Cache initialization: " . ($this->cache ? "SUCCESS" : "FAILED") . "\n";
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
        }
    }
    public function healthCheck() {
    // Don't connect to database for health checks
    return json_encode([
        'status' => 'healthy',
        'timestamp' => time()
    ]);
}
    
    public function getHello() {
        return json_encode([
            'message' => 'Hello from Aurora MySQL API!',
            'timestamp' => time()
        ]);
    }

    public function getCacheStatus() {
       return json_encode([$this->cache->getStatus()]);

    }
    
    public function getUser($id) {
        // Try to get from cache first
        $cacheKey = "user:{$id}";
        echo "Attempting GET for key: $cacheKey";
        $cachedUser = $this->cache->get($cacheKey);

        if ($cachedUser) {
            echo "Cache hit for user ID {$id}\n";
    
            // Make sure to decode JSON if that's how you stored it
            $decodedUser = json_decode($cachedUser, true);
            
            // Return in the same format as your database path
            return json_encode($decodedUser);
        }
        
        try {
            // Query user from Aurora database
            $stmt = $this->db->query("SELECT * FROM users WHERE id = ?", [$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $response = $this->cache->set($cacheKey, json_encode($user), 300); // Cache for 5 minutes
                echo 'cache set response' . json_encode($response);
                return json_encode($user);
            } else {
                http_response_code(404);
                return json_encode(['error' => 'User not found']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            return json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
        }
    }
    
    public function getStatus() {
        $dbStatus = "unknown";
        
        try {
            // Test database connection
            $this->db->query("SELECT 1");
            $dbStatus = "connected";
        } catch (Exception $e) {
            $dbStatus = "error: " . $e->getMessage();
        }
        
        return json_encode([
            'status' => 'healthy',
            'database' => $dbStatus,
            'engine' => 'Aurora MySQL',
            'time' => date('Y-m-d H:i:s')
        ]);
    }

    public function insertUser($name, $email) {
        try {
            // Insert user into Aurora database
            $this->db->query("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE
            )");
            $stmt = $this->db->query("INSERT INTO users (name, email) VALUES (?, ?)", [$name, $email]);
            return json_encode(['status' => 'success', 'message' => 'User inserted']);
        } catch (Exception $e) {
            http_response_code(500);
            return json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
        }
    }

    public function getAllCacheData() {    
        $result = [
            'success' => false,
            'data' => [],
            'error' => null
        ];
        
        try {
            
            
            // Check if Redis is connected
            if (! $this->cache->isConnected()) {
                $result['error'] = 'Redis not connected';
                return json_encode($result);
            }
            
            // Get all keys (with reasonable limit)
            $redis =  $this->cache->getRedisInstance();
            if (!$redis) {
                $result['error'] = 'Redis instance is null';
                echo 'Redis instance is null';
                return json_encode($result);
            }

            $keys = $redis->keys('*');
            
            if (!$keys || !is_array($keys)) {
                $result['error'] = 'No keys found or unable to retrieve keys';
                return json_encode($result);
            }
            
            // Get all values
            foreach ($keys as $key) {
                $value = $redis->get($key);
                $ttl = $redis->ttl($key);
                $result['data'][] = [
                    'key' => $key,
                    'value' => $value,
                    'ttl' => $ttl
                ];
            }
            
            $result['success'] = true;
            
        } catch (Exception $e) {
            $result['error'] = 'Exception: ' . $e->getMessage();
        }
        
        return json_encode($result, JSON_PRETTY_PRINT);
    }


public function testRedisOperations() {
    try {
        $redis =  $this->cache->testOperations();
        if (!$redis) {
            echo "Redis instance is null";
            return;
        }

    } catch (Exception $e) {
        error_log("Redis test error: " . $e->getMessage());
    }

}
}
?>