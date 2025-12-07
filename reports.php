<?php
// reports.php
// Reports Generation & Export Page

require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/PDFGenerator.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();

// Get shops for owner
$shops = [];
if ($auth->isOwner()) {
    $shops = $db->select("SELECT * FROM shops ORDER BY name");
} else {
    $shopId = $auth->getShopId();
    $shops = $db->select("SELECT * FROM shops WHERE id = ?", [$shopId]);
}

$csrfToken = $auth->generateCsrfToken();
$currentPage = 'reports';

// Handle PDF generation
if (isset($_POST['generate_report'])) {
    $reportType = $_POST['report_type'] ?? '';
    $shopId = $_POST['shop_id'] ?? null;
    $month = $_POST['month'] ?? date('m');
    $year = $_POST['year'] ?? date('Y');
    
    try {
        $reportGen = new ReportGenerator();
        
        switch ($reportType) {
            case 'monthly_expense':
                $result = $reportGen->generateMonthlyExpenseReport($shopId, $month, $year);
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
                readfile($result['filepath']);
                exit;
                
            case 'salary_slip':
                $employeeId = $_POST['employee_id'] ?? null;
                if ($employeeId) {
                    $result = $reportGen->generateSalaryReport($employeeId, $month, $year);
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
                    readfile($result['filepath']);
                    exit;
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Salon Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
                        <h1 class="page-title">Reports & Analytics</h1>
                        <p class="breadcrumb">Home / Reports</p>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                <div style="padding: 15px; background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; margin-bottom: 20px; color: #dc2626;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Report Types Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <!-- Monthly Expense Report -->
                    <div class="card" style="cursor: pointer;" onclick="openModal('monthlyExpenseModal')">
                        <div style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 20px; border-radius: 12px 12px 0 0; color: white;">
                            <i class="fas fa-file-invoice-dollar" style="font-size: 40px; margin-bottom: 10px;"></i>
                            <h3 style="margin: 0; font-size: 18px;">Monthly Expense Report</h3>
                        </div>
                        <div style="padding: 20px;">
                            <p style="color: #64748b; font-size: 14px; margin-bottom: 15px;">
                                Generate comprehensive expense breakdown for any month with category-wise analysis
                            </p>
                            <button class="btn btn-primary" style="width: 100%;" onclick="event.stopPropagation(); openModal('monthlyExpenseModal')">
                                <i class="fas fa-file-pdf"></i> Generate PDF
                            </button>
                        </div>
                    </div>

                    <!-- Salary Report -->
                    <div class="card" style="cursor: pointer;" onclick="openModal('salaryReportModal')">
                        <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 20px; border-radius: 12px 12px 0 0; color: white;">
                            <i class="fas fa-money-check-alt" style="font-size: 40px; margin-bottom: 10px;"></i>
                            <h3 style="margin: 0; font-size: 18px;">Salary Report</h3>
                        </div>
                        <div style="padding: 20px;">
                            <p style="color: #64748b; font-size: 14px; margin-bottom: 15px;">
                                Generate employee salary slips with attendance, deductions, and bonus details
                            </p>
                            <button class="btn btn-success" style="width: 100%;" onclick="event.stopPropagation(); openModal('salaryReportModal')">
                                <i class="fas fa-file-pdf"></i> Generate PDF
                            </button>
                        </div>
                    </div>

                    <!-- Attendance Report -->
                    <div class="card" style="cursor: pointer;" onclick="openModal('attendanceReportModal')">
                        <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 20px; border-radius: 12px 12px 0 0; color: white;">
                            <i class="fas fa-calendar-check" style="font-size: 40px; margin-bottom: 10px;"></i>
                            <h3 style="margin: 0; font-size: 18px;">Attendance Report</h3>
                        </div>
                        <div style="padding: 20px;">
                            <p style="color: #64748b; font-size: 14px; margin-bottom: 15px;">
                                Monthly attendance summary with full-day, half-day, and absent tracking
                            </p>
                            <button class="btn btn-info" style="width: 100%;" onclick="event.stopPropagation(); openModal('attendanceReportModal')">
                                <i class="fas fa-file-pdf"></i> Generate PDF
                            </button>
                        </div>
                    </div>

                    <!-- Performance Report -->
                    <div class="card" style="cursor: pointer;" onclick="openModal('performanceReportModal')">
                        <div style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); padding: 20px; border-radius: 12px 12px 0 0; color: white;">
                            <i class="fas fa-chart-line" style="font-size: 40px; margin-bottom: 10px;"></i>
                            <h3 style="margin: 0; font-size: 18px;">Performance Report</h3>
                        </div>
                        <div style="padding: 20px;">
                            <p style="color: #64748b; font-size: 14px; margin-bottom: 15px;">
                                Employee task completion, bonus calculation, and productivity metrics
                            </p>
                            <button class="btn btn-secondary" style="width: 100%;" onclick="event.stopPropagation(); openModal('performanceReportModal')">
                                <i class="fas fa-file-pdf"></i> Generate PDF
                            </button>
                        </div>
                    </div>

                    <!-- Branch Comparison -->
                    <?php if ($auth->isOwner()): ?>
                    <div class="card" style="cursor: pointer;" onclick="openModal('branchComparisonModal')">
                        <div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 20px; border-radius: 12px 12px 0 0; color: white;">
                            <i class="fas fa-code-branch" style="font-size: 40px; margin-bottom: 10px;"></i>
                            <h3 style="margin: 0; font-size: 18px;">Branch Comparison</h3>
                        </div>
                        <div style="padding: 20px;">
                            <p style="color: #64748b; font-size: 14px; margin-bottom: 15px;">
                                Compare performance, expenses, and revenue across all branches
                            </p>
                            <button class="btn btn-warning" style="width: 100%;" onclick="event.stopPropagation(); openModal('branchComparisonModal')">
                                <i class="fas fa-file-pdf"></i> Generate PDF
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- CSV Export -->
                    <div class="card" style="cursor: pointer;" onclick="exportData()">
                        <div style="background: linear-gradient(135deg, #64748b 0%, #475569 100%); padding: 20px; border-radius: 12px 12px 0 0; color: white;">
                            <i class="fas fa-file-csv" style="font-size: 40px; margin-bottom: 10px;"></i>
                            <h3 style="margin: 0; font-size: 18px;">CSV Export</h3>
                        </div>
                        <div style="padding: 20px;">
                            <p style="color: #64748b; font-size: 14px; margin-bottom: 15px;">
                                Export raw data in CSV format for external analysis and backup
                            </p>
                            <button class="btn btn-secondary" style="width: 100%;" onclick="event.stopPropagation(); exportData()">
                                <i class="fas fa-download"></i> Export CSV
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quick Analytics Dashboard -->
                <div class="card">
                    <h3 style="margin-bottom: 20px;">Quick Analytics</h3>
                    <div class="charts-grid">
                        <div>
                            <h4 style="font-size: 16px; margin-bottom: 15px;">Expense Trends (Last 30 Days)</h4>
                            <canvas id="expenseTrendChart" height="250"></canvas>
                        </div>
                        <div>
                            <h4 style="font-size: 16px; margin-bottom: 15px;">Category Breakdown</h4>
                            <canvas id="categoryChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php include 'includes/footer.php'; ?>
        </main>
    </div>

    <!-- Monthly Expense Report Modal -->
    <div class="modal" id="monthlyExpenseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Monthly Expense Report</h2>
                <button class="close-modal" onclick="closeModal('monthlyExpenseModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="report_type" value="monthly_expense">
                <input type="hidden" name="generate_report" value="1">
                
                <div class="form-group">
                    <label class="form-label">Select Branch *</label>
                    <select name="shop_id" class="form-control" required>
                        <?php foreach ($shops as $shop): ?>
                        <option value="<?php echo $shop['id']; ?>"><?php echo htmlspecialchars($shop['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Month *</label>
                        <select name="month" class="form-control" required>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo (date('n') == $i) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Year *</label>
                        <select name="year" class="form-control" required>
                            <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('monthlyExpenseModal')">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-pdf"></i> Generate PDF
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Salary Report Modal -->
    <div class="modal" id="salaryReportModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Salary Report</h2>
                <button class="close-modal" onclick="closeModal('salaryReportModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="report_type" value="salary_slip">
                <input type="hidden" name="generate_report" value="1">
                
                <div class="form-group">
                    <label class="form-label">Select Branch *</label>
                    <select name="shop_id" id="salary_shop_id" class="form-control" required onchange="loadEmployees(this.value, 'salary_employee_id')">
                        <option value="">Choose branch first</option>
                        <?php foreach ($shops as $shop): ?>
                        <option value="<?php echo $shop['id']; ?>"><?php echo htmlspecialchars($shop['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Select Employee *</label>
                    <select name="employee_id" id="salary_employee_id" class="form-control" required disabled>
                        <option value="">Select branch first</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Month *</label>
                        <select name="month" class="form-control" required>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo (date('n') == $i) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Year *</label>
                        <select name="year" class="form-control" required>
                            <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('salaryReportModal')">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-file-pdf"></i> Generate Salary Slip
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/reports.js"></script>
    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        async function loadEmployees(shopId, targetId) {
            const select = document.getElementById(targetId);
            select.disabled = true;
            select.innerHTML = '<option value="">Loading...</option>';
            
            if (!shopId) {
                select.innerHTML = '<option value="">Select branch first</option>';
                return;
            }
            
            try {
                const response = await fetch(`api/employees.php?shop_id=${shopId}&status=active`);
                const result = await response.json();
                
                if (result.success && result.data) {
                    select.innerHTML = '<option value="">Choose employee</option>';
                    result.data.forEach(emp => {
                        const option = document.createElement('option');
                        option.value = emp.id;
                        option.textContent = `${emp.name} (${emp.role})`;
                        select.appendChild(option);
                    });
                    select.disabled = false;
                }
            } catch (error) {
                console.error('Error loading employees:', error);
                select.innerHTML = '<option value="">Error loading employees</option>';
            }
        }
        
        function exportData() {
            alert('CSV export functionality - Select data type to export');
        }
    </script>
</body>
</html>