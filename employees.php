<?php
// employees.php
// Employee Management Page

require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/Employee.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$employee = new Employee();

// Get filters
$shopId = $auth->isOwner() ? ($_GET['shop_id'] ?? null) : $auth->getShopId();
$status = $_GET['status'] ?? 'active';
$search = $_GET['search'] ?? '';

// Get employees
$employees = $employee->getAll($shopId, $status, $search);

// Get shops for owner
$shops = [];
if ($auth->isOwner()) {
    $shops = $db->select("SELECT * FROM shops ORDER BY name");
}

$csrfToken = $auth->generateCsrfToken();
$currentPage = 'employees';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees - Salon Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        <?php include 'assets/css/main-style.css'; ?>
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="content">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Employee Management</h1>
                        <p class="breadcrumb">Home / Employees</p>
                    </div>
                    <?php if ($auth->getRole() !== 'staff'): ?>
                    <button class="btn btn-success" onclick="openModal('addEmployeeModal')">
                        <i class="fas fa-user-plus"></i>
                        Add Employee
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Statistics -->
                <div class="cards-grid">
                    <div class="card">
                        <div class="card-icon success">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="card-title">Active Employees</div>
                        <div class="card-value"><?php echo count(array_filter($employees, fn($e) => $e['status'] === 'active')); ?></div>
                    </div>
                    <div class="card">
                        <div class="card-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="card-title">Total Employees</div>
                        <div class="card-value"><?php echo count($employees); ?></div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card" style="margin-bottom: 20px;">
                    <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                        <?php if ($auth->isOwner()): ?>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Branch</label>
                            <select name="shop_id" class="form-control">
                                <option value="">All Branches</option>
                                <?php foreach ($shops as $shop): ?>
                                <option value="<?php echo $shop['id']; ?>" <?php echo ($shopId == $shop['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($shop['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="all" <?php echo ($status == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo ($status == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($status == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Name, phone, role..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">
                                <i class="fas fa-search"></i>
                                Search
                            </button>
                            <a href="employees.php" class="btn btn-secondary">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Employees Grid -->
                <?php if (empty($employees)): ?>
                <div class="card" style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-users" style="font-size: 64px; color: #cbd5e1; margin-bottom: 16px;"></i>
                    <p style="font-size: 18px; color: #64748b; margin-bottom: 8px;">No employees found</p>
                    <p style="font-size: 14px; color: #94a3b8;">Add your first employee to get started</p>
                </div>
                <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
                    <?php foreach ($employees as $emp): 
                        $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM deductions WHERE employee_id = ? AND is_repaid = 0";
                        $deductions = $db->selectOne($sql, [$emp['id']]);
                        $pendingAmount = $deductions['total'];
                        
                        $sql = "SELECT COUNT(*) as total FROM tasks WHERE employee_id = ? AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())";
                        $tasks = $db->selectOne($sql, [$emp['id']]);
                        $monthlyTasks = $tasks['total'];
                    ?>
                    <div class="card" style="padding: 0; overflow: hidden;">
                        <div style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); padding: 20px; color: white;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <span class="badge" style="background: <?php echo $emp['status'] === 'active' ? '#10b981' : '#ef4444'; ?>; color: white;">
                                    <?php echo ucfirst($emp['status']); ?>
                                </span>
                            </div>
                            <h3 style="margin: 15px 0 5px; font-size: 20px;"><?php echo htmlspecialchars($emp['name']); ?></h3>
                            <p style="opacity: 0.9; font-size: 14px;"><?php echo htmlspecialchars($emp['role']); ?></p>
                        </div>
                        
                        <div style="padding: 20px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Monthly Salary</div>
                                    <div style="font-size: 18px; font-weight: 700; color: #10b981;">₹<?php echo number_format($emp['monthly_salary'], 0); ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Pending Deductions</div>
                                    <div style="font-size: 18px; font-weight: 700; color: #ef4444;">₹<?php echo number_format($pendingAmount, 0); ?></div>
                                </div>
                            </div>
                            
                            <div style="padding: 12px; background: #f8fafc; border-radius: 8px; margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span style="font-size: 13px; color: #64748b;">
                                        <i class="fas fa-phone" style="margin-right: 5px;"></i>
                                        <?php echo htmlspecialchars($emp['phone']); ?>
                                    </span>
                                    <span style="font-size: 13px; color: #64748b;">
                                        <i class="fas fa-birthday-cake" style="margin-right: 5px;"></i>
                                        <?php echo $emp['age']; ?> yrs
                                    </span>
                                </div>
                                <div style="font-size: 13px; color: #64748b;">
                                    <i class="fas fa-calendar" style="margin-right: 5px;"></i>
                                    Joined: <?php echo date('d M Y', strtotime($emp['join_date'])); ?>
                                </div>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #eff6ff; border-radius: 8px; margin-bottom: 15px;">
                                <span style="font-size: 13px; color: #3b82f6;">
                                    <i class="fas fa-tasks"></i> This Month Tasks
                                </span>
                                <span style="font-weight: 700; color: #3b82f6;"><?php echo $monthlyTasks; ?></span>
                            </div>
                            
                            <?php if ($auth->getRole() !== 'staff'): ?>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn btn-primary" style="flex: 1; font-size: 13px;" onclick="viewEmployee(<?php echo $emp['id']; ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="btn btn-warning" style="flex: 1; font-size: 13px;" onclick="editEmployee(<?php echo $emp['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-info" style="flex: 1; font-size: 13px;" onclick="addDeduction(<?php echo $emp['id']; ?>)">
                                    <i class="fas fa-money-bill"></i> Advance
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php include 'includes/footer.php'; ?>
        </main>
    </div>

    <!-- Add Employee Modal -->
    <div class="modal" id="addEmployeeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Employee</h2>
                <button class="close-modal" onclick="closeModal('addEmployeeModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="employeeForm" onsubmit="return handleEmployeeSubmit(event)">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="shop_id" value="<?php echo $auth->getShopId(); ?>">
                
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="Enter full name" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Phone *</label>
                        <input type="tel" name="phone" class="form-control" placeholder="Phone number" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Age *</label>
                        <input type="number" name="age" class="form-control" placeholder="Age" min="18" max="70" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Role/Position *</label>
                        <input type="text" name="role" class="form-control" placeholder="e.g. Senior Stylist" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Monthly Salary *</label>
                        <input type="number" name="monthly_salary" class="form-control" placeholder="0.00" step="0.01" required>
                    </div>
                </div>
                
                <?php if ($auth->isOwner()): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Branch *</label>
                        <select name="shop_id" class="form-control" required>
                            <option value="">Select branch</option>
                            <?php foreach ($shops as $shop): ?>
                            <option value="<?php echo $shop['id']; ?>"><?php echo htmlspecialchars($shop['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Joining Date *</label>
                        <input type="date" name="join_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <?php else: ?>
                <div class="form-group">
                    <label class="form-label">Joining Date *</label>
                    <input type="date" name="join_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <?php endif; ?>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addEmployeeModal')">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-user-plus"></i>
                        Add Employee
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Employee Modal -->
    <div class="modal" id="viewEmployeeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Employee Details</h2>
                <button class="close-modal" onclick="closeModal('viewEmployeeModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="employeeDetails"></div>
        </div>
    </div>

    <!-- Add Deduction Modal -->
    <div class="modal" id="addDeductionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add Deduction/Advance</h2>
                <button class="close-modal" onclick="closeModal('addDeductionModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="deductionForm" onsubmit="return handleDeductionSubmit(event)">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="employee_id" id="deduction_employee_id">
                <input type="hidden" name="shop_id" value="<?php echo $auth->getShopId(); ?>">
                
                <div class="form-group">
                    <label class="form-label">Type *</label>
                    <select name="type" class="form-control" required>
                        <option value="advance">Salary Advance</option>
                        <option value="loan">Loan</option>
                        <option value="manual">Manual Deduction</option>
                        <option value="late_fee">Late Fee</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Amount *</label>
                        <input type="number" name="amount" class="form-control" placeholder="0.00" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date *</label>
                        <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Note</label>
                    <textarea name="note" class="form-control" rows="3" placeholder="Reason for deduction/advance"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addDeductionModal')">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Add Deduction
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/employees.js"></script>
</body>
</html>