<?php
// dashboard.php
// Main Dashboard Page

require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();

// Get user info
$userId = $auth->getUserId();
$shopId = $auth->getShopId();
$role = $auth->getRole();
$isOwner = $auth->isOwner();

// Get today's date
$today = date('Y-m-d');
$currentMonth = date('m');
$currentYear = date('Y');

// Initialize statistics
$stats = [
    'today_expenses' => 0,
    'monthly_expenses' => 0,
    'yearly_expenses' => 0,
    'total_employees' => 0,
    'active_employees' => 0,
    'pending_salaries' => 0,
    'today_revenue' => 0
];

// Get shop-specific or aggregated statistics
if ($isOwner) {
    // Owner sees aggregated data from all shops
    
    // Today's expenses across all shops
    $sql = "SELECT COALESCE(SUM(amount), 0) as total 
            FROM expenses 
            WHERE date = ? AND is_deleted = 0";
    $result = $db->selectOne($sql, [$today]);
    $stats['today_expenses'] = $result['total'];
    
    // Monthly expenses
    $sql = "SELECT COALESCE(SUM(amount), 0) as total 
            FROM expenses 
            WHERE MONTH(date) = ? AND YEAR(date) = ? AND is_deleted = 0";
    $result = $db->selectOne($sql, [$currentMonth, $currentYear]);
    $stats['monthly_expenses'] = $result['total'];
    
    // Yearly expenses
    $sql = "SELECT COALESCE(SUM(amount), 0) as total 
            FROM expenses 
            WHERE YEAR(date) = ? AND is_deleted = 0";
    $result = $db->selectOne($sql, [$currentYear]);
    $stats['yearly_expenses'] = $result['total'];
    
    // Employee counts
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
            FROM employees";
    $result = $db->selectOne($sql);
    $stats['total_employees'] = $result['total'];
    $stats['active_employees'] = $result['active'];
    
    // Get all shops
    $shops = $db->select("SELECT * FROM shops ORDER BY name");
    
} else {
    // Branch admin/staff sees only their shop data
    
    // Today's expenses
    $sql = "SELECT COALESCE(SUM(amount), 0) as total 
            FROM expenses 
            WHERE shop_id = ? AND date = ? AND is_deleted = 0";
    $result = $db->selectOne($sql, [$shopId, $today]);
    $stats['today_expenses'] = $result['total'];
    
    // Monthly expenses
    $sql = "SELECT COALESCE(SUM(amount), 0) as total 
            FROM expenses 
            WHERE shop_id = ? AND MONTH(date) = ? AND YEAR(date) = ? AND is_deleted = 0";
    $result = $db->selectOne($sql, [$shopId, $currentMonth, $currentYear]);
    $stats['monthly_expenses'] = $result['total'];
    
    // Yearly expenses
    $sql = "SELECT COALESCE(SUM(amount), 0) as total 
            FROM expenses 
            WHERE shop_id = ? AND YEAR(date) = ? AND is_deleted = 0";
    $result = $db->selectOne($sql, [$shopId, $currentYear]);
    $stats['yearly_expenses'] = $result['total'];
    
    // Employee counts
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
            FROM employees 
            WHERE shop_id = ?";
    $result = $db->selectOne($sql, [$shopId]);
    $stats['total_employees'] = $result['total'];
    $stats['active_employees'] = $result['active'];
    
    $shops = null;
}

// Get pending salary deductions
$sql = "SELECT COALESCE(SUM(amount), 0) as total FROM deductions WHERE is_repaid = 0";
if (!$isOwner) {
    $sql .= " AND shop_id = ?";
    $result = $db->selectOne($sql, [$shopId]);
} else {
    $result = $db->selectOne($sql);
}
$stats['pending_salaries'] = $result['total'];

// Get recent expenses for table
$sql = "SELECT e.*, s.name as shop_name, u.name as created_by_name 
        FROM expenses e 
        LEFT JOIN shops s ON e.shop_id = s.id 
        LEFT JOIN users u ON e.created_by = u.id 
        WHERE e.is_deleted = 0";

if (!$isOwner) {
    $sql .= " AND e.shop_id = ?";
    $recentExpenses = $db->select($sql . " ORDER BY e.date DESC, e.created_at DESC LIMIT 10", [$shopId]);
} else {
    $recentExpenses = $db->select($sql . " ORDER BY e.date DESC, e.created_at DESC LIMIT 10");
}

// Get expense chart data (last 7 days)
$chartLabels = [];
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = date('M d', strtotime($date));
    
    $sql = "SELECT COALESCE(SUM(amount), 0) as total 
            FROM expenses 
            WHERE date = ? AND is_deleted = 0";
    
    if (!$isOwner) {
        $sql .= " AND shop_id = ?";
        $result = $db->selectOne($sql, [$date, $shopId]);
    } else {
        $result = $db->selectOne($sql, [$date]);
    }
    
    $chartData[] = $result['total'];
}

// Get category-wise expenses for pie chart
$sql = "SELECT category, COALESCE(SUM(amount), 0) as total 
        FROM expenses 
        WHERE MONTH(date) = ? AND YEAR(date) = ? AND is_deleted = 0";

if (!$isOwner) {
    $sql .= " AND shop_id = ?";
    $categoryData = $db->select($sql . " GROUP BY category", [$currentMonth, $currentYear, $shopId]);
} else {
    $categoryData = $db->select($sql . " GROUP BY category", [$currentMonth, $currentYear]);
}

$categoryLabels = [];
$categoryValues = [];
foreach ($categoryData as $row) {
    $categoryLabels[] = ucfirst(str_replace('_', ' ', $row['category']));
    $categoryValues[] = $row['total'];
}

// Get CSRF token for forms
$csrfToken = $auth->generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Salon Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <!-- Include sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <!-- Include header -->
            <?php include 'includes/header.php'; ?>
            
            <div class="content">
                <div class="page-header">
                    <h1 class="page-title">
                        <?php echo $isOwner ? 'Owner Dashboard' : 'Branch Dashboard'; ?>
                    </h1>
                    <p class="breadcrumb">Home / Dashboard</p>
                </div>
                
                <!-- Quick Actions -->
                <?php if ($role !== 'staff'): ?>
                <div class="quick-actions">
                    <button class="action-btn" onclick="openModal('addExpenseModal')">
                        <i class="fas fa-plus"></i>
                        Add Expense
                    </button>
                    <button class="action-btn" onclick="openModal('addEmployeeModal')">
                        <i class="fas fa-user-plus"></i>
                        Add Employee
                    </button>
                    <button class="action-btn" onclick="openModal('addAttendanceModal')">
                        <i class="fas fa-calendar-plus"></i>
                        Mark Attendance
                    </button>
                    <button class="action-btn" onclick="window.location.href='reports.php'">
                        <i class="fas fa-file-pdf"></i>
                        Generate Report
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="cards-grid">
                    <div class="card">
                        <div class="card-icon primary">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                        <div class="card-title">Today's Expenses</div>
                        <div class="card-value">₹<?php echo number_format($stats['today_expenses'], 2); ?></div>
                        <div class="card-change">
                            <i class="fas fa-calendar-day"></i>
                            <?php echo date('d M Y'); ?>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-icon success">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="card-title">Monthly Expenses</div>
                        <div class="card-value">₹<?php echo number_format($stats['monthly_expenses'], 2); ?></div>
                        <div class="card-change">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo date('F Y'); ?>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-icon warning">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="card-title">Active Employees</div>
                        <div class="card-value"><?php echo $stats['active_employees']; ?></div>
                        <div class="card-change">
                            <i class="fas fa-user-check"></i>
                            of <?php echo $stats['total_employees']; ?> total
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-icon danger">
                            <i class="fas fa-money-bill"></i>
                        </div>
                        <div class="card-title">Pending Deductions</div>
                        <div class="card-value">₹<?php echo number_format($stats['pending_salaries'], 2); ?></div>
                        <div class="card-change negative">
                            <i class="fas fa-exclamation-triangle"></i>
                            Requires attention
                        </div>
                    </div>
                </div>
                
                <!-- Branch Comparison for Owner -->
                <?php if ($isOwner && $shops): ?>
                <div class="card" style="margin-bottom: 30px;">
                    <h3 style="margin-bottom: 20px;">Branch Comparison</h3>
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Today's Expense</th>
                                <th>Monthly Expense</th>
                                <th>Employees</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shops as $shop): 
                                $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE shop_id = ? AND date = ? AND is_deleted = 0";
                                $todayExp = $db->selectOne($sql, [$shop['id'], $today]);
                                
                                $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE shop_id = ? AND MONTH(date) = ? AND YEAR(date) = ? AND is_deleted = 0";
                                $monthExp = $db->selectOne($sql, [$shop['id'], $currentMonth, $currentYear]);
                                
                                $sql = "SELECT COUNT(*) as total FROM employees WHERE shop_id = ? AND status = 'active'";
                                $empCount = $db->selectOne($sql, [$shop['id']]);
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($shop['name']); ?></strong></td>
                                <td>₹<?php echo number_format($todayExp['total'], 2); ?></td>
                                <td>₹<?php echo number_format($monthExp['total'], 2); ?></td>
                                <td><?php echo $empCount['total']; ?></td>
                                <td>
                                    <a href="?shop_id=<?php echo $shop['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <!-- Charts -->
                <div class="charts-grid">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Expense Trends (Last 7 Days)</h3>
                        </div>
                        <canvas id="expenseChart" height="250"></canvas>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Category-wise Expenses (This Month)</h3>
                        </div>
                        <canvas id="categoryChart" height="250"></canvas>
                    </div>
                </div>
                
                <!-- Recent Expenses Table -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Recent Expenses</h3>
                        <div class="table-actions">
                            <input type="text" id="searchExpenses" class="search-box" placeholder="Search expenses...">
                            <a href="expenses.php" class="btn btn-primary">
                                <i class="fas fa-list"></i>
                                View All
                            </a>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Title</th>
                                <th>Category</th>
                                <?php if ($isOwner): ?>
                                <th>Branch</th>
                                <?php endif; ?>
                                <th>Amount</th>
                                <th>Created By</th>
                                <?php if ($role !== 'staff'): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentExpenses)): ?>
                            <tr>
                                <td colspan="<?php echo $isOwner ? 7 : 6; ?>" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-inbox" style="font-size: 48px; color: var(--gray); margin-bottom: 10px;"></i>
                                    <p style="color: var(--gray);">No expenses found</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentExpenses as $expense): ?>
                            <tr>
                                <td><?php echo date('d M, Y', strtotime($expense['date'])); ?></td>
                                <td><?php echo htmlspecialchars($expense['title']); ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo ucfirst(str_replace('_', ' ', $expense['category'])); ?>
                                    </span>
                                </td>
                                <?php if ($isOwner): ?>
                                <td><?php echo htmlspecialchars($expense['shop_name']); ?></td>
                                <?php endif; ?>
                                <td><strong>₹<?php echo number_format($expense['amount'], 2); ?></strong></td>
                                <td><?php echo htmlspecialchars($expense['created_by_name'] ?? 'N/A'); ?></td>
                                <?php if ($role !== 'staff'): ?>
                                <td>
                                    <button class="btn-icon" onclick="editExpense(<?php echo $expense['id']; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon" onclick="deleteExpense(<?php echo $expense['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Footer -->
            <?php include 'includes/footer.php'; ?>
        </main>
    </div>
    
    <!-- Modals would be included here -->
    
    <script>
        // Initialize charts
        const expenseCtx = document.getElementById('expenseChart');
        if (expenseCtx) {
            new Chart(expenseCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: 'Daily Expenses',
                        data: <?php echo json_encode($chartData); ?>,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
        
        const categoryCtx = document.getElementById('categoryChart');
        if (categoryCtx) {
            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($categoryLabels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($categoryValues); ?>,
                        backgroundColor: [
                            '#ef4444',
                            '#f59e0b',
                            '#10b981',
                            '#3b82f6',
                            '#8b5cf6',
                            '#ec4899'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Search functionality
        document.getElementById('searchExpenses')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>