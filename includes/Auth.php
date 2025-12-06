<?php
// includes/Auth.php
// Authentication and Session Management

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->startSession();
    }
    
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            
            // Check session timeout
            if (isset($_SESSION['LAST_ACTIVITY']) && 
                (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
                $this->logout();
            }
            $_SESSION['LAST_ACTIVITY'] = time();
        }
    }
    
    public function login($email, $password) {
        // Check login attempts
        if ($this->isLoginBlocked($email)) {
            return ['success' => false, 'message' => 'Too many login attempts. Try again later.'];
        }
        
        $sql = "SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1";
        $user = $this->db->selectOne($sql, [$email]);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Reset login attempts
            $this->resetLoginAttempts($email);
            
            // Set session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['shop_id'] = $user['shop_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            
            // Generate CSRF token
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
            
            // Log login
            $this->logAudit($user['id'], 'login', 'user', $user['id'], 'User logged in');
            
            return ['success' => true, 'redirect' => 'dashboard.php'];
        } else {
            // Increment login attempts
            $this->incrementLoginAttempts($email);
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logAudit($_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id'], 'User logged out');
        }
        
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    public function requireRole($roles) {
        $this->requireLogin();
        
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        if (!in_array($_SESSION['role'], $roles)) {
            header('Location: dashboard.php');
            exit;
        }
    }
    
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function getShopId() {
        return $_SESSION['shop_id'] ?? null;
    }
    
    public function getRole() {
        return $_SESSION['role'] ?? null;
    }
    
    public function isOwner() {
        return $this->getRole() === 'owner';
    }
    
    public function isBranchAdmin() {
        return $this->getRole() === 'branch_admin';
    }
    
    public function canAccessShop($shopId) {
        if ($this->isOwner()) {
            return true;
        }
        return $this->getShopId() == $shopId;
    }
    
    // CSRF Protection
    public function generateCsrfToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    public function verifyCsrfToken($token) {
        return isset($_SESSION[CSRF_TOKEN_NAME]) && 
               hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    // Login Attempts Tracking
    private function incrementLoginAttempts($email) {
        $_SESSION['login_attempts'][$email] = [
            'count' => ($_SESSION['login_attempts'][$email]['count'] ?? 0) + 1,
            'time' => time()
        ];
    }
    
    private function resetLoginAttempts($email) {
        if (isset($_SESSION['login_attempts'][$email])) {
            unset($_SESSION['login_attempts'][$email]);
        }
    }
    
    private function isLoginBlocked($email) {
        if (!isset($_SESSION['login_attempts'][$email])) {
            return false;
        }
        
        $attempts = $_SESSION['login_attempts'][$email];
        
        // Reset if timeout expired
        if (time() - $attempts['time'] > LOGIN_TIMEOUT) {
            $this->resetLoginAttempts($email);
            return false;
        }
        
        return $attempts['count'] >= MAX_LOGIN_ATTEMPTS;
    }
    
    // Audit Logging
    public function logAudit($userId, $action, $objectType, $objectId, $meta = '') {
        $sql = "INSERT INTO audit_logs (user_id, action, object_type, object_id, meta, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->insert($sql, [
            $userId,
            $action,
            $objectType,
            $objectId,
            $meta,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
}