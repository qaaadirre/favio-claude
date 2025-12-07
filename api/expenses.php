<?php
// api/expenses.php
// Expense Management API Endpoint

header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Expense.php';

$auth = new Auth();
$auth->requireLogin();

$expense = new Expense();
$method = $_SERVER['REQUEST_METHOD'];

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

try {
    switch ($method) {
        case 'GET':
            handleGet($expense, $auth);
            break;
            
        case 'POST':
            handlePost($expense, $auth);
            break;
            
        case 'PUT':
            handlePut($expense, $auth);
            break;
            
        case 'DELETE':
            handleDelete($expense, $auth);
            break;
            
        default:
            sendResponse(405, 'Method not allowed');
    }
} catch (Exception $e) {
    sendResponse(500, 'Internal server error', ['error' => $e->getMessage()]);
}

function handleGet($expense, $auth) {
    // Export to CSV
    if (isset($_GET['export'])) {
        $filters = [
            'shop_id' => $auth->isOwner() ? ($_GET['shop_id'] ?? null) : $auth->getShopId(),
            'category' => $_GET['category'] ?? null,
            'start_date' => $_GET['start_date'] ?? null,
            'end_date' => $_GET['end_date'] ?? null,
            'search' => $_GET['search'] ?? null
        ];
        
        $result = $expense->exportToCSV($filters);
        
        // Force download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        readfile($result['filepath']);
        exit;
    }
    
    // Get single expense
    if (isset($_GET['id'])) {
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        if (!$id) {
            sendResponse(400, 'Invalid expense ID');
        }
        
        $data = $expense->getById($id);
        
        if (!$data) {
            sendResponse(404, 'Expense not found');
        }
        
        if (!$auth->canAccessShop($data['shop_id'])) {
            sendResponse(403, 'Access denied');
        }
        
        sendResponse(200, 'Success', $data);
    }
    
    // Get statistics
    if (isset($_GET['statistics'])) {
        $shopId = $auth->isOwner() ? null : $auth->getShopId();
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        
        $data = $expense->getStatistics($shopId, $startDate, $endDate);
        sendResponse(200, 'Success', $data);
    }
    
    // Get category breakdown
    if (isset($_GET['category_breakdown'])) {
        $shopId = $auth->isOwner() ? null : $auth->getShopId();
        $month = $_GET['month'] ?? null;
        $year = $_GET['year'] ?? null;
        
        $data = $expense->getCategoryBreakdown($shopId, $month, $year);
        sendResponse(200, 'Success', $data);
    }
    
    // Get all expenses with pagination
    $filters = [
        'shop_id' => $auth->isOwner() ? ($_GET['shop_id'] ?? null) : $auth->getShopId(),
        'category' => $_GET['category'] ?? null,
        'start_date' => $_GET['start_date'] ?? null,
        'end_date' => $_GET['end_date'] ?? null,
        'search' => $_GET['search'] ?? null
    ];
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $filters['limit'] = $limit;
    $filters['offset'] = $offset;
    
    $data = $expense->getAll($filters);
    $total = $expense->getCount($filters);
    
    sendResponse(200, 'Success', [
        'expenses' => $data,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function handlePost($expense, $auth) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendResponse(400, 'Invalid JSON');
    }
    
    if (!isset($input['csrf_token']) || !$auth->verifyCsrfToken($input['csrf_token'])) {
        sendResponse(403, 'Invalid CSRF token');
    }
    
    $required = ['shop_id', 'title', 'amount', 'date', 'category'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            sendResponse(400, "Missing required field: {$field}");
        }
    }
    
    if (!$auth->canAccessShop($input['shop_id'])) {
        sendResponse(403, 'Access denied');
    }
    
    if (!is_numeric($input['amount']) || $input['amount'] <= 0) {
        sendResponse(400, 'Invalid amount');
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['date'])) {
        sendResponse(400, 'Invalid date format (YYYY-MM-DD required)');
    }
    
    $validCategories = ['electricity', 'materials', 'rent', 'salary_payout', 'employee_borrowed', 'misc'];
    if (!in_array($input['category'], $validCategories)) {
        sendResponse(400, 'Invalid category');
    }
    
    $expenseId = $expense->create($input);
    
    if ($expenseId) {
        $data = $expense->getById($expenseId);
        sendResponse(201, 'Expense created successfully', $data);
    } else {
        sendResponse(500, 'Failed to create expense');
    }
}

function handlePut($expense, $auth) {
    if (!isset($_GET['id'])) {
        sendResponse(400, 'Expense ID required');
    }
    
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        sendResponse(400, 'Invalid expense ID');
    }
    
    $existing = $expense->getById($id);
    if (!$existing) {
        sendResponse(404, 'Expense not found');
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
    
    if (isset($input['amount']) && (!is_numeric($input['amount']) || $input['amount'] <= 0)) {
        sendResponse(400, 'Invalid amount');
    }
    
    if (isset($input['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['date'])) {
        sendResponse(400, 'Invalid date format');
    }
    
    if (isset($input['category'])) {
        $validCategories = ['electricity', 'materials', 'rent', 'salary_payout', 'employee_borrowed', 'misc'];
        if (!in_array($input['category'], $validCategories)) {
            sendResponse(400, 'Invalid category');
        }
    }
    
    $result = $expense->update($id, $input);
    
    if ($result !== false) {
        $data = $expense->getById($id);
        sendResponse(200, 'Expense updated successfully', $data);
    } else {
        sendResponse(500, 'Failed to update expense');
    }
}

function handleDelete($expense, $auth) {
    if (!isset($_GET['id'])) {
        sendResponse(400, 'Expense ID required');
    }
    
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        sendResponse(400, 'Invalid expense ID');
    }
    
    $existing = $expense->getById($id);
    if (!$existing) {
        sendResponse(404, 'Expense not found');
    }
    
    if (!$auth->canAccessShop($existing['shop_id'])) {
        sendResponse(403, 'Access denied');
    }
    
    if (!$auth->isOwner() && !$auth->isBranchAdmin()) {
        sendResponse(403, 'Insufficient permissions');
    }
    
    $result = $expense->delete($id);
    
    if ($result !== false) {
        sendResponse(200, 'Expense deleted successfully');
    } else {
        sendResponse(500, 'Failed to delete expense');
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