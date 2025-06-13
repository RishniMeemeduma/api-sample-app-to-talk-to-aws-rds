<?php
require_once 'Database.php';

class Api {
    private $db;
    
    public function __construct() {
        // Initialize database connection
        try {
            $this->db = Database::getInstance();
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
        }
    }
    
    public function getHello() {
        return json_encode([
            'message' => 'Hello from Aurora MySQL API!',
            'timestamp' => time()
        ]);
    }
    
    public function getUser($id) {
        try {
            // Query user from Aurora database
            $stmt = $this->db->query("SELECT * FROM users WHERE id = ?", [$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
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
}
?>