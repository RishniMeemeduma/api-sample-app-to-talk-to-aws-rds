<?php 
require_once 'Database.php';

class Cache {
    protected static $instance = null;
    protected $redis = null;
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Cache();
        }
        return self::$instance;
    }

    private function connect() {
        // Create Redis object first
        $this->redis = new Redis();
        
        try {
            // Get connection details
            $host = getenv('CACHE_ENDPOINT') ?: 'tif-portal-dev-elasticache-asy7kk.serverless.euw2.cache.amazonaws.com';
            $port = 6379;
            $username = 'app-user';
            
            // Get credentials
            $raw_creds = getenv('ELASTICACHE_PASSWORD');
            $credentials = json_decode($raw_creds, true);
            $password = is_array($credentials) ? ($credentials['password'] ?? null) : ($credentials->password ?? null);
            
            if (empty($password)) {
                error_log("FATAL: ELASTICACHE_PASSWORD env var not set or invalid.");
                return false;
            }
            
            // TLS Options
            $options = [
                'auth' => [$username, $password],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ];
            
            // Call connect() but DON'T reassign $this->redis
            $connected = $this->redis->connect('tls://' . $host, $port, 1.5, null, 100, 1.5, $options);
            
            if (!$connected) {
                $this->redis = null;  // Clear reference if connection failed
                error_log("Redis connection failed");
                return false;
            }
            
            error_log("ElastiCache connection successful!");
            
            // Test connection works
            $testResult = $this->redis->set('test:key', 'Connected!');
            error_log("Test SET result: " . ($testResult ? "SUCCESS" : "FAILED"));
            
            return true;
        } catch (Exception $e) {
            error_log("Redis connection error: " . $e->getMessage());
            $this->redis = null;
            return false;
        }
    }
    
    public function isConnected() {
        return $this->redis !== null;
    }
    
    public function get($key) {
        try {
            if (!$this->isConnected()) {
                error_log("Cache not connected");
                return null;
            }
            
            $value = $this->redis->get($key);
            error_log("GET result for key '$key': " . ($value !== false ? "found" : "not found"));
            
            return $value;
        } catch (Exception $e) {
            error_log("Cache get error: " . $e->getMessage());
            return null;
        }
    }
    
    public function set($key, $value, $ttl = 3600) {
        try {
            if (!$this->isConnected()) {
                error_log("Cannot set key '$key': Cache not connected ");
                return false;
            }
            
            // Set with TTL
            $result = $this->redis->setex($key, $ttl, $value);
            error_log("SET result for key '$key': " . ($result ? "SUCCESS" : "FAILED"));
            
            return $result;
        } catch (Exception $e) {
            error_log("Cache set error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getStatus() {
        if (!$this->isConnected()) {
            return [
                'redis_version' => phpversion('redis'),
                'configured' => true,
                'endpoint' => getenv('CACHE_ENDPOINT'),
                'connected' => 'no',
                'error' => 'Not connected to cache'
            ];
        }
        
        try {
            $ping = $this->redis->ping();
            return [
                'redis_version' => phpversion('redis'),
                'configured' => true,
                'endpoint' => getenv('CACHE_ENDPOINT'),
                'connected' => 'yes',
                'ping' => $ping ? 'success' : 'failed',
                'engine' => 'Valkey',
                'version' => '8.0',
                'user_group' => 'tif-portal-dev-users',
                'tls_enabled' => true,
                'serverless' => true
            ];
        } catch (Exception $e) {
            return [
                'connected' => 'no',
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function testOperations() {
        if (!$this->isConnected()) {
            return ['error' => 'Not connected'];
        }
        
        $results = [];
        
        try {
            // Test SET
            $setResult = $this->redis->setex('test:valkey', 300, 'Hello from Valkey Serverless!');
            $results['set'] = $setResult ? 'SUCCESS' : 'FAILED';
            
            // Test GET
            $getValue = $this->redis->get('test:valkey');
            $results['get'] = $getValue ?: 'FAILED';
            
            // Test TTL
            $ttl = $this->redis->ttl('test:valkey');
            $results['ttl'] = $ttl;
            
            // Test DELETE
            $delResult = $this->redis->del('test:valkey');
            $results['delete'] = $delResult ? 'SUCCESS' : 'FAILED';
            
        } catch (Exception $e) {
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    public function getRedisInstance() {
        return $this->redis;
    }
}