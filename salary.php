<?php
// salary.php
// Salary Management & Settlement Page

require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/Employee.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole(['owner', 'branch_admin']);

$db = Database::getInstance();
$employee = new Employee();

// Get filters
$shopId = $auth->isOwner() ? ($_GET['shop_id'] ?? null) : $auth->getShopId();
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// Get employees for selected shop
$employees = [];
if ($shopId) {
    $employees = $employee->getAll($shopId, 'active');
}

// Get shops for owner
$shops = [];
if ($auth->isOwner()) {
    $shops = $db->select("SELECT * FROM shops ORDER BY name");
}

// Get salary payments history
$salaryHistory = [];
if ($shopId) {
    $sql = "SELECT sp.*, e.name as employee_name, e.role, u.name as processed_by_name
            FROM salary_payments sp
            LEFT JOIN employees e ON sp.employee_id = e.id
            LEFT JOIN users u ON sp.created_by = u.id
            WHERE sp.shop_id = ?
            ORDER BY sp.paid_on DESC, sp.created_at DESC
            LIMIT 20";
    $salaryHistory = $db->select($sql, [$shopId]);
}

$csrfToken = $auth->generateCsrfToken();
$currentPage = 'salary';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Management - Salon Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="content">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Salary Management</h1>
                        <p class="breadcrumb">Home / Salary</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card" style="margin-bottom: 20px;">
                    <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                        <?php if ($auth->isOwner()): ?>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Branch</label>
                            <select name="shop_id" class="form-control" required>
                                <option value="">Select Branch</option>
                                <?php foreach ($shops as $shop): ?>
                                <option value="<?php echo $shop['id']; ?>" <?php echo ($shopId == $shop['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($shop['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Month & Year</label>
                            <input type="month" name="period" class="form-control" 
                                   value="<?php echo $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT); ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Load Employees
                        </button>
                    </form>
                </div>

                <?php if (!empty($employees) && $shopId): ?>
                <!-- Employees Salary Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <?php foreach ($employees as $emp): 
                        // Calculate salary for the month
                        $salaryData = $employee->calculateMonthlySalary($emp['id'], $month, $year);
                        
                        // Check if already paid
                        $sql = "SELECT * FROM salary_payments 
                                WHERE employee_id = ? 
                                  AND MONTH(period_start) = ? 
                                  AND YEAR(period_start) = ?
                                ORDER BY id DESC LIMIT 1";
                        $payment = $db->selectOne($sql, [$emp['id'], $month, $year]);
                    ?>
                    <div class="card" style="padding: 0; overflow: hidden;">
                        <div style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); padding: 20px; color: white;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <h3 style="margin-bottom: 5px; font-size: 18px;"><?php echo htmlspecialchars($emp['name']); ?></h3>
                                    <p style="opacity: 0.9; font-size: 13px;"><?php echo htmlspecialchars($emp['role']); ?></p>
                                </div>
                                <?php if ($payment): ?>
                                <span class="badge" style="background: #10b981; color: white;">
                                    <i class="fas fa-check-circle"></i> Paid
                                </span>
                                <?php else: ?>
                                <span class="badge" style="background: #f59e0b; color: white;">
                                    <i class="fas fa-clock"></i> Pending
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="padding: 20px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div style="text-align: center; padding: 12px; background: #f0fdf4; border-radius: 8px;">
                                    <div style="font-size: 11px; color: #166534; margin-bottom: 4px;">Gross Salary</div>
                                    <div style="font-size: 18px; font-weight: 700; color: #10b981;">₹<?php echo number_format($salaryData['gross_salary'], 0); ?></div>
                                </div>
                                <div style="text-align: center; padding: 12px; background: #fef2f2; border-radius: 8px;">
                                    <div style="font-size: 11px; color: #991b1b; margin-bottom: 4px;">Deductions</div>
                                    <div style="font-size: 18px; font-weight: 700; color: #ef4444;">₹<?php echo number_format($salaryData['total_deductions'], 0); ?></div>
                                </div>
                            </div>
                            
                            <!-- Breakdown -->
                            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px;">
                                    <span style="color: #64748b;">Monthly Salary:</span>
                                    <span style="font-weight: 600;">₹<?php echo number_format($salaryData['gross_salary'], 2); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px;">
                                    <span style="color: #64748b;">Half-Day Deductions:</span>
                                    <span style="font-weight: 600; color: #ef4444;">- ₹<?php echo number_format($salaryData['half_day_deduction'], 2); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px;">
                                    <span style="color: #64748b;">Advances/Loans:</span>
                                    <span style="font-weight: 600; color: #ef4444;">- ₹<?php echo number_format($salaryData['other_deductions'], 2); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px;">
                                    <span style="color: #64748b;">Bonus (<?php echo $salaryData['tasks_completed']; ?> tasks):</span>
                                    <span style="font-weight: 600; color: #10b981;">+ ₹<?php echo number_format($salaryData['bonus'], 2); ?></span>
                                </div>
                                <hr style="margin: 12px 0; border: none; border-top: 1px solid #e2e8f0;">
                                <div style="display: flex; justify-content: space-between; font-size: 15px;">
                                    <span style="font-weight: 700;">Net Payable:</span>
                                    <span style="font-weight: 700; color: #6366f1;">₹<?php echo number_format($salaryData['net_salary'], 2); ?></span>
                                </div>
                            </div>
                            
                            <!-- Attendance Summary -->
                            <div style="display: flex; gap: 8px; margin-bottom: 15px; font-size: 12px;">
                                <div style="flex: 1; text-align: center; padding: 8px; background: #dcfce7; border-radius: 6px;">
                                    <div style="font-weight: 600; color: #166534;"><?php echo $salaryData['attendance']['full_days']; ?></div>
                                    <div style="color: #166534;">Full Days</div>
                                </div>
                                <div style="flex: 1; text-align: center; padding: 8px; background: #fef3c7; border-radius: 6px;">
                                    <div style="font-weight: 600; color: #92400e;"><?php echo $salaryData['attendance']['half_days']; ?></div>
                                    <div style="color: #92400e;">Half Days</div>
                                </div>
                                <div style="flex: 1; text-align: center; padding: 8px; background: #fee2e2; border-radius: 6px;">
                                    <div style="font-weight: 600; color: #991b1b;"><?php echo $salaryData['attendance']['absent_days']; ?></div>
                                    <div style="color: #991b1b;">Absent</div>
                                </div>
                            </div>
                            
                            <?php if ($payment): ?>
                            <!-- Already Paid -->
                            <div style="padding: 12px; background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; margin-bottom: 10px;">
                                <div style="font-size: 12px; color: #166534; margin-bottom: 5px;">
                                    <i class="fas fa-info-circle"></i> Salary paid on <?php echo date('d M Y', strtotime($payment['paid_on'])); ?>
                                </div>
                                <div style="font-size: 11px; color: #166534;">
                                    Method: <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?> | 
                                    Amount: ₹<?php echo number_format($payment['net_paid'], 2); ?>
                                </div>
                            </div>
                            <button class="btn btn-secondary" style="width: 100%; font-size: 13px;" 
                                    onclick="viewPaymentDetails(<?php echo $payment['id']; ?>)">
                                <i class="fas fa-eye"></i> View Payment Details
                            </button>
                            <?php else: ?>
                            <!-- Process Payment -->
                            <button class="btn btn-success" style="width: 100%; font-size: 14px;"
                                    onclick="processSalary(<?php echo $emp['id']; ?>, <?php echo json_encode($salaryData); ?>)">
                                <i class="fas fa-money-check-alt"></i> Process Payment
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Salary Payment History -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Recent Salary Payments</h3>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Employee</th>
                                <th>Period</th>
                                <th>Gross</th>
                                <th>Deductions</th>
                                <th>Net Paid</th>
                                <th>Method</th>
                                <th>Processed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($salaryHistory)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #64748b;">
                                    No salary payments found
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($salaryHistory as $payment): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($payment['paid_on'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($payment['employee_name']); ?></strong><br>
                                    <small style="color: #64748b;"><?php echo htmlspecialchars($payment['role']); ?></small>
                                </td>
                                <td><?php echo date('M Y', strtotime($payment['period_start'])); ?></td>
                                <td>₹<?php echo number_format($payment['gross_salary'], 2); ?></td>
                                <td style="color: #ef4444;">₹<?php echo number_format($payment['total_deductions'], 2); ?></td>
                                <td><strong style="color: #10b981;">₹<?php echo number_format($payment['net_paid'], 2); ?></strong></td>
                                <td><span class="badge badge-info"><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></span></td>
                                <td><?php echo htmlspecialchars($payment['processed_by_name'] ?? 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="card" style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-money-bill-wave" style="font-size: 64px; color: #cbd5e1; margin-bottom: 16px;"></i>
                    <p style="font-size: 18px; color: #64748b;">Please select a branch and period to view salary information</p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php include 'includes/footer.php'; ?>
        </main>
    </div>

    <!-- Process Salary Modal -->
    <div class="modal" id="processSalaryModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Process Salary Payment</h2>
                <button class="close-modal" onclick="closeModal('processSalaryModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="salaryForm" onsubmit="return handleSalarySubmit(event)">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="employee_id" id="salary_employee_id">
                <input type="hidden" name="shop_id" value="<?php echo $shopId; ?>">
                <input type="hidden" name="period_start" id="salary_period_start">
                <input type="hidden" name="period_end" id="salary_period_end">
                <input type="hidden" name="gross_salary" id="salary_gross">
                <input type="hidden" name="total_deductions" id="salary_deductions">
                <input type="hidden" name="bonuses" id="salary_bonus">
                <input type="hidden" name="net_paid" id="salary_net">
                
                <div id="salaryBreakdown" style="background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 20px;"></div>
                
                <div class="form-group">
                    <label class="form-label">Payment Date *</label>
                    <input type="date" name="paid_on" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Payment Method *</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="upi">UPI</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes (optional)"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('processSalaryModal')">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Confirm Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/salary.js"></script>
</body>
</html>