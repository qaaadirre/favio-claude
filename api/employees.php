<?php
// api/employees.php
// Employee Management API Endpoint

header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Employee.php';

$auth = new Auth();
$auth->requireLogin();

$employee = new Employee();
$method = $_SERVER['REQUEST_METHOD'];

// Enable CORS for local development (remove in production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

try {
    switch ($method) {
        case 'GET':
            handleGet($employee, $auth);
            break;
            
        case 'POST':
            handlePost($employee, $auth);
            break;
            
        case 'PUT':
            handlePut($employee, $auth);
            break;
            
        case 'DELETE':
            handleDelete($employee, $auth);
            break;
            
        default:
            sendResponse(405, 'Method not allowed');
    }
} catch (Exception $e) {
    sendResponse(500, 'Internal server error', ['error' => $e->getMessage()]);
}

/**
 * Handle GET requests
 */
function handleGet($employee, $auth) {
    // Get single employee
    if (isset($_GET['id'])) {
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        if (!$id) {
            sendResponse(400, 'Invalid employee ID');
        }
        
        $data = $employee->getById($id);
        
        if (!$data) {
            sendResponse(404, 'Employee not found');
        }
        
        // Check access permission
        if (!$auth->canAccessShop($data['shop_id'])) {
            sendResponse(403, 'Access denied');
        }
        
        sendResponse(200, 'Success', $data);
    }
    
    // Get salary calculation
    if (isset($_GET['salary_calc'])) {
        $id = filter_var($_GET['employee_id'], FILTER_VALIDATE_INT);
        $month = filter_var($_GET['month'], FILTER_VALIDATE_INT);
        $year = filter_var($_GET['year'], FILTER_VALIDATE_INT);
        
        if (!$id || !$month || !$year) {
            sendResponse(400, 'Invalid parameters');
        }
        
        $data = $employee->calculateMonthlySalary($id, $month, $year);
        
        if (!$data) {
            sendResponse(404, 'Employee not found');
        }
        
        sendResponse(200, 'Success', $data);
    }
    
    // Get deductions
    if (isset($_GET['deductions'])) {
        $id = filter_var($_GET['employee_id'], FILTER_VALIDATE_INT);
        
        if (!$id) {
            sendResponse(400, 'Invalid employee ID');
        }
        
        $includeRepaid = isset($_GET['include_repaid']) && $_GET['include_repaid'] == '1';
        $data = $employee->getDeductions($id, $includeRepaid);
        
        sendResponse(200, 'Success', $data);
    }
    
    // Get all employees
    $shopId = $auth->isOwner() ? null : $auth->getShopId();
    $status = $_GET['status'] ?? 'active';
    $search = $_GET['search'] ?? '';
    
    $data = $employee->getAll($shopId, $status, $search);
    sendResponse(200, 'Success', $data);
}

/**
 * Handle POST requests
 */
function handlePost($employee, $auth) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendResponse(400, 'Invalid JSON');
    }
    
    // Verify CSRF token
    if (!isset($input['csrf_token']) || !$auth->verifyCsrfToken($input['csrf_token'])) {
        sendResponse(403, 'Invalid CSRF token');
    }
    
    // Add deduction
    if (isset($input['action']) && $input['action'] === 'add_deduction') {
        $required = ['employee_id', 'shop_id', 'type', 'amount', 'date'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                sendResponse(400, "Missing required field: {$field}");
            }
        }
        
        // Check access permission
        if (!$auth->canAccessShop($input['shop_id'])) {
            sendResponse(403, 'Access denied');
        }
        
        $deductionId = $employee->addDeduction($input);
        
        if ($deductionId) {
            sendResponse(201, 'Deduction added successfully', ['id' => $deductionId]);
        } else {
            sendResponse(500, 'Failed to add deduction');
        }
    }
    
    // Process salary payment
    if (isset($input['action']) && $input['action'] === 'process_salary') {
        $required = ['employee_id', 'shop_id', 'period_start', 'period_end', 
                     'gross_salary', 'total_deductions', 'bonuses', 'net_paid', 
                     'paid_on', 'payment_method'];
        
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                sendResponse(400, "Missing required field: {$field}");
            }
        }
        
        // Check access permission
        if (!$auth->canAccessShop($input['shop_id'])) {
            sendResponse(403, 'Access denied');
        }
        
        // Require admin role
        if (!$auth->isOwner() && !$auth->isBranchAdmin()) {
            sendResponse(403, 'Insufficient permissions');
        }
        
        $paymentId = $employee->processSalaryPayment($input['employee_id'], $input);
        
        if ($paymentId) {
            sendResponse(201, 'Salary processed successfully', ['id' => $paymentId]);
        } else {
            sendResponse(500, 'Failed to process salary');
        }
    }
    
    // Create new employee
    $required = ['shop_id', 'name', 'phone', 'role', 'monthly_salary', 'join_date'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            sendResponse(400, "Missing required field: {$field}");
        }
    }
    
    // Check access permission
    if (!$auth->canAccessShop($input['shop_id'])) {
        sendResponse(403, 'Access denied');
    }
    
    // Validate data
    if (!filter_var($input['monthly_salary'], FILTER_VALIDATE_FLOAT)) {
        sendResponse(400, 'Invalid salary amount');
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['join_date'])) {
        sendResponse(400, 'Invalid date format');
    }
    
    $employeeId = $employee->create($input);
    
    if ($employeeId) {
        $data = $employee->getById($employeeId);
        sendResponse(201, 'Employee created successfully', $data);
    } else {
        sendResponse(500, 'Failed to create employee');
    }
}

/**
 * Handle PUT requests
 */
function handlePut($employee, $auth) {
    if (!isset($_GET['id'])) {
        sendResponse(400, 'Employee ID required');
    }
    
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        sendResponse(400, 'Invalid employee ID');
    }
    
    // Get existing employee
    $existing = $employee->getById($id);
    if (!$existing) {
        sendResponse(404, 'Employee not found');
    }
    
    // Check access permission
    if (!$auth->canAccessShop($existing['shop_id'])) {
        sendResponse(403, 'Access denied');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendResponse(400, 'Invalid JSON');
    }
    
    // Verify CSRF token
    if (!isset($input['csrf_token']) || !$auth->verifyCsrfToken($input['csrf_token'])) {
        sendResponse(403, 'Invalid CSRF token');
    }
    
    // Validate data
    if (isset($input['monthly_salary']) && !filter_var($input['monthly_salary'], FILTER_VALIDATE_FLOAT)) {
        sendResponse(400, 'Invalid salary amount');
    }
    
    $result = $employee->update($id, $input);
    
    if ($result !== false) {
        $data = $employee->getById($id);
        sendResponse(200, 'Employee updated successfully', $data);
    } else {
        sendResponse(500, 'Failed to update employee');
    }
}

/**
 * Handle DELETE requests
 */
function handleDelete($employee, $auth) {
    if (!isset($_GET['id'])) {
        sendResponse(400, 'Employee ID required');
    }
    
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        sendResponse(400, 'Invalid employee ID');
    }
    
    // Get existing employee
    $existing = $employee->getById($id);
    if (!$existing) {
        sendResponse(404, 'Employee not found');
    }
    
    // Check access permission
    if (!$auth->canAccessShop($existing['shop_id'])) {
        sendResponse(403, 'Access denied');
    }
    
    // Require admin role
    if (!$auth->isOwner() && !$auth->isBranchAdmin()) {
        sendResponse(403, 'Insufficient permissions');
    }
    
    $result = $employee->delete($id);
    
    if ($result !== false) {
        sendResponse(200, 'Employee deleted successfully');
    } else {
        sendResponse(500, 'Failed to delete employee');
    }
}

/**
 * Send JSON response
 */
function sendResponse($statusCode, $message, $data = null) {
    http_response_code($statusCode);
    
    $response = [
        'success' => $statusCode >= 200 && $statusCode < 300,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}