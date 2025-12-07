<?php
// includes/sidebar.php
// Sidebar Navigation Component

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-cut"></i>
            <span>Salon Pro</span>
        </div>
    </div>
    <nav class="sidebar-menu">
        <a href="dashboard.php" class="menu-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="employees.php" class="menu-item <?php echo $currentPage === 'employees' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Employees</span>
        </a>
        
        <a href="attendance.php" class="menu-item <?php echo $currentPage === 'attendance' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <span>Attendance</span>
        </a>
        
        <a href="expenses.php" class="menu-item <?php echo $currentPage === 'expenses' ? 'active' : ''; ?>">
            <i class="fas fa-receipt"></i>
            <span>Expenses</span>
        </a>
        
        <a href="salary.php" class="menu-item <?php echo $currentPage === 'salary' ? 'active' : ''; ?>">
            <i class="fas fa-money-bill-wave"></i>
            <span>Salary</span>
        </a>
        
        <a href="services.php" class="menu-item <?php echo $currentPage === 'services' ? 'active' : ''; ?>">
            <i class="fas fa-scissors"></i>
            <span>Services</span>
        </a>
        
        <a href="reports.php" class="menu-item <?php echo $currentPage === 'reports' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </a>
        
        <?php if ($auth->isOwner()): ?>
        <a href="audit-logs.php" class="menu-item <?php echo $currentPage === 'audit-logs' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span>Audit Logs</span>
        </a>
        <?php endif; ?>
        
        <a href="settings.php" class="menu-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
    </nav>
</aside>

<style>
    .sidebar {
        width: 260px;
        background: white;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        position: fixed;
        left: 0;
        top: 0;
        bottom: 0;
        z-index: 1000;
        overflow-y: auto;
        transition: transform 0.3s ease;
    }

    body.dark-mode .sidebar {
        background: #1e293b;
    }

    .sidebar-header {
        padding: 20px;
        border-bottom: 1px solid #e2e8f0;
        text-align: center;
    }

    .logo {
        font-size: 24px;
        font-weight: bold;
        color: #6366f1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .logo i {
        font-size: 32px;
    }

    .sidebar-menu {
        padding: 20px 0;
    }

    .menu-item {
        padding: 14px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        color: #64748b;
        text-decoration: none;
        transition: all 0.3s ease;
        cursor: pointer;
        border-left: 3px solid transparent;
    }

    .menu-item:hover {
        background: rgba(99, 102, 241, 0.1);
        color: #6366f1;
        border-left-color: #6366f1;
    }

    .menu-item.active {
        background: rgba(99, 102, 241, 0.1);
        color: #6366f1;
        border-left-color: #6366f1;
        font-weight: 600;
    }

    .menu-item i {
        width: 20px;
        text-align: center;
        font-size: 16px;
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.open {
            transform: translateX(0);
        }
    }
</style>