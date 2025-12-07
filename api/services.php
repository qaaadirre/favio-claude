<?php
// api/services.php
// Services Management API Endpoint

header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

try {
    switch ($method) {
        case 'GET':
            handleGet($db, $auth);
            break;
            
        case 'POST':
            handlePost($db, $auth);
            break;
            
        case 'PUT':
            handlePut($db, $auth);
            break;
            
        case 'DELETE':
            handleDelete($db, $auth);
            break;
            
        default:
            sendResponse(405, 'Method not allowed');
    }
} catch (Exception $e) {
    sendResponse(500, 'Internal server error', ['error' => $e->getMessage()]);
}

function handleGet($db, $auth) {
    // Get single service
    if (isset($_GET['id'])) {
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        if (!$id) {
            sendResponse(400, 'Invalid service ID');
        }
        
        $sql = "SELECT s.*, sh.name as shop_name 
                FROM services s 
                LEFT JOIN shops sh ON s.shop_id = sh.id 
                WHERE s.id = ?";
        $data = $db->selectOne($sql, [$id]);
        
        if (!$data) {
            sendResponse(404, 'Service not found');
        }
        
        if (!$auth->canAccessShop($data['shop_id'])) {
            sendResponse(403, 'Access denied');
        }
        
        sendResponse(200, 'Success', $data);
    }
    
    // Get all services
    $shopId = $auth->isOwner() ? ($_GET['shop_id'] ?? null) : $auth->getShopId();
    $status = $_GET['status'] ?? 'all';
    
    $sql = "SELECT s.*, sh.name as shop_name 
            FROM services s 
            LEFT JOIN shops sh ON s.shop_id = sh.id 
            WHERE 1=1";
    $params = [];
    
    if ($shopId) {
        $sql .= " AND s.shop_id = ?";
        $params[] = $shopId;
    }
    
    if ($status !== 'all') {
        $sql .= " AND s.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY s.name ASC";
    
    $data = $db->select($sql, $params);
    sendResponse(200, 'Success', $data);
}

function handlePost($db, $auth) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendResponse(400, 'Invalid JSON');
    }
    
    if (!isset($input['csrf_token']) || !$auth->verifyCsrfToken($input['csrf_token'])) {
        sendResponse(403, 'Invalid CSRF token');
    }
    
    $required = ['shop_id', 'name', 'price'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            sendResponse(400, "Missing required field: {$field}");
        }
    }
    
    if (!$auth->canAccessShop($input['shop_id'])) {
        sendResponse(403, 'Access denied');
    }
    
    if (!is_numeric($input['price']) || $input['price'] <= 0) {
        sendResponse(400, 'Invalid price');
    }
    
    $sql = "INSERT INTO services (shop_id, name, price, duration, status) 
            VALUES (?, ?, ?, ?, 'active')";
    
    $serviceId = $db->insert($sql, [
        $input['shop_id'],
        $input['name'],
        $input['price'],
        $input['duration'] ?? null
    ]);
    
    if ($serviceId) {
        $auth->logAudit(
            $auth->getUserId(),
            'create',
            'service',
            $serviceId,
            "Created service: {$input['name']}"
        );
        
        $data = $db->selectOne("SELECT * FROM services WHERE id = ?", [$serviceId]);
        sendResponse(201, 'Service created successfully', $data);
    } else {
        sendResponse(500, 'Failed to create service');
    }
}

function handlePut($db, $auth) {
    if (!isset($_GET['id'])) {
        sendResponse(400, 'Service ID required');
    }
    
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        sendResponse(400, 'Invalid service ID');
    }
    
    $existing = $db->selectOne("SELECT * FROM services WHERE id = ?", [$id]);
    if (!$existing) {
        sendResponse(404, 'Service not found');
    }
    
    if (!$auth->canAccessShop($existing['shop_id'])) {
        sendResponse(403, 'Access denied');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendResponse(400, 'Invalid JSON');
    }
    
    if (!isset($input['csrf_token']) || !$auth->verifyCsrfToken($input['csrf_token'])) {
        sendResponse(403, 'Invalid CSRF token');
    }
    
    if (isset($input['price']) && (!is_numeric($input['price']) || $input['price'] <= 0)) {
        sendResponse(400, 'Invalid price');
    }
    
    $sql = "UPDATE services 
            SET name = ?, price = ?, duration = ?, updated_at = NOW() 
            WHERE id = ?";
    
    $result = $db->update($sql, [
        $input['name'] ?? $existing['name'],
        $input['price'] ?? $existing['price'],
        $input['duration'] ?? $existing['duration'],
        $id
    ]);
    
    if ($result !== false) {
        $auth->logAudit(
            $auth->getUserId(),
            'update',
            'service',
            $id,
            "Updated service: {$input['name']}"
        );
        
        $data = $db->selectOne("SELECT * FROM services WHERE id = ?", [$id]);
        sendResponse(200, 'Service updated successfully', $data);
    } else {
        sendResponse(500, 'Failed to update service');
    }
}

function handleDelete($db, $auth) {
    if (!isset($_GET['id'])) {
        sendResponse(400, 'Service ID required');
    }
    
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        sendResponse(400, 'Invalid service ID');
    }
    
    $existing = $db->selectOne("SELECT * FROM services WHERE id = ?", [$id]);
    if (!$existing) {
        sendResponse(404, 'Service not found');
    }
    
    if (!$auth->canAccessShop($existing['shop_id'])) {
        sendResponse(403, 'Access denied');
    }
    
    if (!$auth->isOwner() && !$auth->isBranchAdmin()) {
        sendResponse(403, 'Insufficient permissions');
    }
    
    // Soft delete by setting status to inactive
    $sql = "UPDATE services SET status = 'inactive', updated_at = NOW() WHERE id = ?";
    $result = $db->update($sql, [$id]);
    
    if ($result !== false) {
        $auth->logAudit(
            $auth->getUserId(),
            'delete',
            'service',
            $id,
            "Deleted service: {$existing['name']}"
        );
        
        sendResponse(200, 'Service deleted successfully');
    } else {
        sendResponse(500, 'Failed to delete service');
    }
}

function sendResponse($statusCode, $message, $data = null) {
    http_response_code($statusCode);
    
    $response = [
        'success' => $statusCode >= 200 && $statusCode < 300,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}