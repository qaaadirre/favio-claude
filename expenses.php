<?php
// expenses.php
// Expense Management Page

require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/Expense.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$expense = new Expense();

// Get filters
$shopId = $auth->isOwner() ? ($_GET['shop_id'] ?? null) : $auth->getShopId();
$category = $_GET['category'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build filters
$filters = [
    'shop_id' => $shopId,
    'category' => $category,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'search' => $search,
    'limit' => $limit,
    'offset' => $offset
];

// Get expenses
$expenses = $expense->getAll($filters);
$totalCount = $expense->getCount($filters);
$totalPages = ceil($totalCount / $limit);

// Get statistics
$stats = $expense->getStatistics($shopId, $startDate, $endDate);

// Get shops for owner
$shops = [];
if ($auth->isOwner()) {
    $shops = $db->select("SELECT * FROM shops ORDER BY name");
}

$csrfToken = $auth->generateCsrfToken();
$currentPage = 'expenses';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses - Salon Management System</title>
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
                        <h1 class="page-title">Expense Management</h1>
                        <p class="breadcrumb">Home / Expenses</p>
                    </div>
                    <?php if ($auth->getRole() !== 'staff'): ?>
                    <button class="btn btn-success" onclick="openModal('addExpenseModal')">
                        <i class="fas fa-plus"></i>
                        Add Expense
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Statistics Cards -->
                <div class="cards-grid">
                    <?php
                    $totalExpenses = 0;
                    $transactionCount = 0;
                    foreach ($stats as $stat) {
                        $totalExpenses += $stat['category_total'];
                        $transactionCount += $stat['total_transactions'];
                    }
                    ?>
                    <div class="card">
                        <div class="card-icon danger">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="card-title">Total Expenses</div>
                        <div class="card-value">₹<?php echo number_format($totalExpenses, 2); ?></div>
                        <div class="card-change"><?php echo $transactionCount; ?> transactions</div>
                    </div>
                    
                    <div class="card">
                        <div class="card-icon warning">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="card-title">Average Expense</div>
                        <div class="card-value">₹<?php echo $transactionCount > 0 ? number_format($totalExpenses / $transactionCount, 2) : '0.00'; ?></div>
                        <div class="card-change">Per transaction</div>
                    </div>
                    
                    <div class="card">
                        <div class="card-icon info">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="card-title">This Month</div>
                        <div class="card-value">
                            <?php
                            $monthlyTotal = 0;
                            foreach ($expenses as $exp) {
                                if (date('Y-m', strtotime($exp['date'])) === date('Y-m')) {
                                    $monthlyTotal += $exp['amount'];
                                }
                            }
                            echo '₹' . number_format($monthlyTotal, 2);
                            ?>
                        </div>
                        <div class="card-change"><?php echo date('F Y'); ?></div>
                    </div>
                    
                    <div class="card">
                        <div class="card-icon primary">
                            <i class="fas fa-tag"></i>
                        </div>
                        <div class="card-title">Categories</div>
                        <div class="card-value"><?php echo count($stats); ?></div>
                        <div class="card-change">Active categories</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card" style="margin-bottom: 20px;">
                    <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: end;">
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
                            <label class="form-label">Category</label>
                            <select name="category" class="form-control">
                                <option value="">All Categories</option>
                                <option value="electricity" <?php echo $category === 'electricity' ? 'selected' : ''; ?>>Electricity</option>
                                <option value="materials" <?php echo $category === 'materials' ? 'selected' : ''; ?>>Materials</option>
                                <option value="rent" <?php echo $category === 'rent' ? 'selected' : ''; ?>>Rent</option>
                                <option value="salary_payout" <?php echo $category === 'salary_payout' ? 'selected' : ''; ?>>Salary Payout</option>
                                <option value="employee_borrowed" <?php echo $category === 'employee_borrowed' ? 'selected' : ''; ?>>Employee Borrowed</option>
                                <option value="misc" <?php echo $category === 'misc' ? 'selected' : ''; ?>>Miscellaneous</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Title, description..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">
                                <i class="fas fa-search"></i>
                            </button>
                            <a href="expenses.php" class="btn btn-secondary">
                                <i class="fas fa-redo"></i>
                            </a>
                            <button type="button" class="btn btn-info" onclick="exportExpenses()">
                                <i class="fas fa-file-csv"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Expenses Table -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Expense List</h3>
                        <div class="table-actions">
                            <span style="color: #64748b; font-size: 14px;">
                                Showing <?php echo count($expenses); ?> of <?php echo $totalCount; ?> expenses
                            </span>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Title</th>
                                <th>Category</th>
                                <?php if ($auth->isOwner()): ?>
                                <th>Branch</th>
                                <?php endif; ?>
                                <th>Amount</th>
                                <th>Created By</th>
                                <?php if ($auth->getRole() !== 'staff'): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expenses)): ?>
                            <tr>
                                <td colspan="<?php echo $auth->isOwner() ? 7 : 6; ?>" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-receipt" style="font-size: 48px; color: #cbd5e1; margin-bottom: 10px;"></i>
                                    <p style="color: #64748b;">No expenses found</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($expenses as $exp): ?>
                            <tr>
                                <td><?php echo date('d M, Y', strtotime($exp['date'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($exp['title']); ?></strong>
                                    <?php if (!empty($exp['description'])): ?>
                                    <br><small style="color: #64748b;"><?php echo htmlspecialchars(substr($exp['description'], 0, 50)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo ucfirst(str_replace('_', ' ', $exp['category'])); ?>
                                    </span>
                                </td>
                                <?php if ($auth->isOwner()): ?>
                                <td><?php echo htmlspecialchars($exp['shop_name']); ?></td>
                                <?php endif; ?>
                                <td><strong style="color: #ef4444;">₹<?php echo number_format($exp['amount'], 2); ?></strong></td>
                                <td><?php echo htmlspecialchars($exp['created_by_name'] ?? 'N/A'); ?></td>
                                <?php if ($auth->getRole() !== 'staff'): ?>
                                <td>
                                    <button class="btn-icon" onclick="viewExpense(<?php echo $exp['id']; ?>)" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon" onclick="editExpense(<?php echo $exp['id']; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($auth->isOwner() || $auth->isBranchAdmin()): ?>
                                    <button class="btn-icon" onclick="deleteExpense(<?php echo $exp['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div style="padding: 20px; display: flex; justify-content: center; gap: 10px;">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="btn btn-secondary">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                        <?php endif; ?>
                        
                        <span style="padding: 10px 20px; background: #f1f5f9; border-radius: 8px;">
                            Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                        </span>
                        
                        <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="btn btn-secondary">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php include 'includes/footer.php'; ?>
        </main>
    </div>

    <!-- Add Expense Modal -->
    <div class="modal" id="addExpenseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Expense</h2>
                <button class="close-modal" onclick="closeModal('addExpenseModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="expenseForm" onsubmit="return handleExpenseSubmit(event)">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="shop_id" value="<?php echo $auth->getShopId(); ?>">
                
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" placeholder="Expense title" required>
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
                    <label class="form-label">Category *</label>
                    <select name="category" class="form-control" required>
                        <option value="electricity">Electricity</option>
                        <option value="materials">Materials</option>
                        <option value="rent">Rent</option>
                        <option value="salary_payout">Salary Payout</option>
                        <option value="employee_borrowed">Employee Borrowed</option>
                        <option value="misc">Miscellaneous</option>
                    </select>
                </div>
                
                <?php if ($auth->isOwner()): ?>
                <div class="form-group">
                    <label class="form-label">Branch *</label>
                    <select name="shop_id" class="form-control" required>
                        <?php foreach ($shops as $shop): ?>
                        <option value="<?php echo $shop['id']; ?>"><?php echo htmlspecialchars($shop['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Additional details (optional)"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addExpenseModal')">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Expense Modal -->
    <div class="modal" id="viewExpenseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Expense Details</h2>
                <button class="close-modal" onclick="closeModal('viewExpenseModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="expenseDetails"></div>
        </div>
    </div>

    <script src="assets/js/expense.js"></script>
</body>
</html>