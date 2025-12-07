<?php
// api/attendance.php
// Attendance Management API Endpoint

header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Attendance.php';

$auth = new Auth();
$auth->requireLogin();

$attendance = new Attendance();
$method = $_SERVER['REQUEST_METHOD'];

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

try {
    switch ($method) {
        case 'GET':
            handleGet($attendance, $auth);
            break;
            
        case 'POST':
            handlePost($attendance, $auth);
            break;
            
        case 'PUT':
            handlePut($attendance, $auth);
            break;
            
        case 'DELETE':
            handleDelete($attendance, $auth);
            break;
            
        default:
            sendResponse(405, 'Method not allowed');
    }
} catch (Exception $e) {
    sendResponse(500, 'Internal server error', ['error' => $e->getMessage()]);
}

function handleGet($attendance, $auth) {
    // Get daily report
    if (isset($_GET['daily_report'])) {
        $shopId = filter_var($_GET['shop_id'], FILTER_VALIDATE_INT);
        $date = $_GET['date'] ?? date('Y-m-d');
        
        if (!$shopId) {
            sendResponse(400, 'Invalid shop ID');
        }
        
        if (!$auth->canAccessShop($shopId)) {
            sendResponse(403, 'Access denied');
        }
        
        $data = $attendance->getDailyReport($shopId, $date);
        sendResponse(200, 'Success', $data);
    }
    
    // Get employee summary
    if (isset($_GET['summary'])) {
        $employeeId = filter_var($_GET['employee_id'], FILTER_VALIDATE_INT);
        $month = filter_var($_GET['month'], FILTER_VALIDATE_INT);
        $year = filter_var($_GET['year'], FILTER_VALIDATE_INT);
        
        if (!$employeeId || !$month || !$year) {
            sendResponse(400, 'Invalid parameters');
        }
        
        $data = $attendance->getSummary($employeeId, $month, $year);
        sendResponse(200, 'Success', $data);
    }
    
    // Get attendance list
    $filters = [
        'shop_id' => $auth->isOwner() ? ($_GET['shop_id'] ?? null) : $auth->getShopId(),
        'employee_id' => $_GET['employee_id'] ?? null,
        'date' => $_GET['date'] ?? null,
        'start_date' => $_GET['start_date'] ?? null,
        'end_date' => $_GET['end_date'] ?? null,
        'status' => $_GET['status'] ?? null
    ];
    
    $data = $attendance->getAll($filters);
    sendResponse(200, 'Success', $data);
}

function handlePost($attendance, $auth) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendResponse(400, 'Invalid JSON');
    }
    
    if (!isset($input['csrf_token']) || !$auth->verifyCsrfToken($input['csrf_token'])) {
        sendResponse(403, 'Invalid CSRF token');
    }
    
    $required = ['employee_id', 'shop_id', 'date', 'status'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            sendResponse(400, "Missing required field: {$field}");
        }
    }
    
    if (!$auth->canAccessShop($input['shop_id'])) {
        sendResponse(403, 'Access denied');
    }
    
    $validStatuses = ['full_day', 'half_day', 'absent'];
    if (!in_array($input['status'], $validStatuses)) {
        sendResponse(400, 'Invalid status');
    }
    
    $result = $attendance->markAttendance($input);
    
    if ($result) {
        sendResponse(201, 'Attendance marked successfully', ['id' => $result]);
    } else {
        sendResponse(500, 'Failed to mark attendance');
    }
}

function handlePut($attendance, $auth) {
    if (!isset($_GET['id'])) {
        sendResponse(400, 'Attendance ID required');
    }
    
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        sendResponse(400, 'Invalid attendance ID');
    }
    
    $existing = $attendance->getById($id);
    if (!$existing) {
        sendResponse(404, 'Attendance record not found');
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
    
    $result = $attendance->updateAttendance($id, $input);
    
    if ($result !== false) {
        sendResponse(200, 'Attendance updated successfully');
    } else {
        sendResponse(500, 'Failed to update attendance');
    }
}

function handleDelete($attendance, $auth) {
    if (!isset($_GET['id'])) {
        sendResponse(400, 'Attendance ID required');
    }
    
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        sendResponse(400, 'Invalid attendance ID');
    }
    
    $existing = $attendance->getById($id);
    if (!$existing) {
        sendResponse(404, 'Attendance record not found');
    }
    
    if (!$auth->canAccessShop($existing['shop_id'])) {
        sendResponse(403, 'Access denied');
    }
    
    if (!$auth->isOwner() && !$auth->isBranchAdmin()) {
        sendResponse(403, 'Insufficient permissions');
    }
    
    $db = Database::getInstance();
    $sql = "DELETE FROM attendance WHERE id = ?";
    $result = $db->delete($sql, [$id]);
    
    if ($result !== false) {
        sendResponse(200, 'Attendance record deleted successfully');
    } else {
        sendResponse(500, 'Failed to delete attendance record');
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