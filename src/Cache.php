<?php
class Cache {
    private static $instance = null;
    private $redis = null;
    
    private function __construct() {
    try {
        $endpoint = getenv('CACHE_ENDPOINT');
        if (empty($endpoint)) {
            error_log("Cache endpoint not configured");
            return;
        }

        $port = getenv('CACHE_PORT') ?: 6380;
        $useTLS = getenv('ELASTICACHE_TLS') === 'true';

        error_log("Attempting Redis connection to $endpoint:$port (TLS: " . ($useTLS ? 'yes' : 'no') . ")");

        $this->redis = new Redis();

        if ($useTLS) {
            $context = [                      // Context array (not resource)
            'stream' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
            ];
            
            if (!$this->redis->connect('tls://' . $endpoint, (int)$port, 10.0, null, 0, 0, $context)) {
                throw new Exception("Redis TLS connection failed");
            }
        } else {
            if (!$this->redis->connect($endpoint, (int)$port, 10.0)) {
                throw new Exception("Redis connection failed");
            }
        }

        // Test the connection immediately
        $this->redis->ping();
        error_log("Redis connection successful");

    } catch (Exception $e) {
        error_log("Cache connection error: " . $e->getMessage());
        $this->redis = null;
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
            return $this->redis ? $this->redis->get($key) : null;
        } catch (Exception $e) {
            error_log("Cache get error: " . $e->getMessage());
            return null;
        }
    }
    
    public function set($key, $value, $ttl = 3600) {
        try {
            return $this->redis ? $this->redis->setex($key,  $ttl, $value) : false;
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