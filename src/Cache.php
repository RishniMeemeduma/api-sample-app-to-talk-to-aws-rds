<?php
class Cache {
    private static $instance = null;
    private $redis = null;
    
    private function __construct() {
// In your __construct function

        try {
            $endpoint = getenv('CACHE_ENDPOINT');
            $port = getenv('CACHE_PORT') ?: 6380; // Note: Using 6380 instead of default 6379
            $useTLS = getenv('ELASTICACHE_TLS') === 'true';
            
            error_log("Attempting Redis connection to {$endpoint}:{$port} (TLS: " . ($useTLS ? 'yes' : 'no') . ")");
            
            $this->redis = new Redis();
            
            // Connection
            if ($useTLS) {
                $context = [
                    'stream' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ];
                
                error_log("Connecting with TLS options");
                $connected = $this->redis->connect($endpoint, (int)$port, 5.0, null, 0, 0, $context);
            } else {
                error_log("Connecting without TLS");
                $connected = $this->redis->connect($endpoint, (int)$port, 5.0);
            }
            
            if (!$connected) {
                throw new Exception("Redis connection failed");
            }
            
            error_log("Connection successful");
            
            // Authentication
            $credentails = json_decode(getenv('ELASTICACHE_PASSWORD')) ;
            error_log("Auth details ". getenv('ELASTICACHE_PASSWORD'));
            
            $password = $credentails->password;

            
           try {
            // Format 2: Just password
            $authResult = $this->redis->auth(['app-user', $password]);
            
            error_log("Auth result (format 2): " . ($authResult ? "success" : "failed"));
        } catch (Exception $e) {
            error_log("Auth format 2 error: " . $e->getMessage());
        }
          
            // Set options
            error_log("Setting Redis options");
            $this->redis->setOption(Redis::OPT_READ_TIMEOUT, -1);
            
        } catch (Exception $e) {
            error_log("Cache connection error: " . $e->getMessage());
        }
}
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Cache();
        }
        return self::$instance;
    }
    
    public function get($key) {
        try {
            $value = $this->redis ? $this->redis->get($key) : null;
            error_log("GET result for key '{$key}': " . var_export($value, true));
            return $value;
        } catch (Exception $e) {
            error_log("Cache get error: " . $e->getMessage());
            return null;
        }
    }
    
    public function set($key, $value, $ttl = 3600) {
        try {
            $result = $this->redis ? $this->redis->setex($key,  $ttl, $value) : false;
            if ($result === false) {
                error_log("Cache write failed for key: $key");
            }
            return $result;
        } catch (Exception $e) {
            error_log("Cache set error: " . $e->getMessage());
            return false;
        }
    }
    public function getStatus() {
        $status = [];
        $status['redis_version'] = phpversion('redis');
    $status['configured'] = !empty(getenv('CACHE_ENDPOINT')) || !empty(getenv('ELASTICACHE_ENDPOINT'));
    $status['endpoint'] = getenv('CACHE_ENDPOINT') ?: getenv('ELASTICACHE_ENDPOINT') ?: 'not set';
    $status['connected'] = $this->redis ? 'yes' : 'no';
    
    if ($this->redis) {
        try {
            $pingResult = $this->redis->ping();
            $status['ping'] = $pingResult ?: 'failed';
        } catch (Exception $e) {
            $status['ping_error'] = $e->getMessage();
        }
    }
    
    return $status;
}
}
?>