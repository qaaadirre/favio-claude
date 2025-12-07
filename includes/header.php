<?php
// includes/header.php
// Top Navigation Header Component
?>
<header class="top-header">
    <div class="header-left">
        <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="header-welcome">
            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?>!</h2>
            <p>Here's what's happening today</p>
        </div>
    </div>
    
    <div class="header-right">
        <!-- Dark Mode Toggle -->
        <button class="header-btn" id="darkModeToggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
            <i class="fas fa-moon"></i>
        </button>
        
        <!-- Notifications -->
        <button class="header-btn" onclick="showNotifications()" title="Notifications">
            <i class="fas fa-bell"></i>
            <span class="badge-count">3</span>
        </button>
        
        <!-- User Menu -->
        <div class="user-menu">
            <button class="user-btn" onclick="toggleUserMenu()">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></div>
                    <div class="user-role"><?php echo ucfirst($_SESSION['role'] ?? 'staff'); ?></div>
                </div>
                <i class="fas fa-chevron-down"></i>
            </button>
            
            <div class="user-dropdown" id="userDropdown">
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user-circle"></i>
                    My Profile
                </a>
                <a href="settings.php" class="dropdown-item">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>
</header>

<style>
    .top-header {
        background: white;
        padding: 20px 30px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 999;
    }

    body.dark-mode .top-header {
        background: #1e293b;
        border-bottom-color: #334155;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .menu-toggle {
        display: none;
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #64748b;
    }

    .header-welcome h2 {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .header-welcome p {
        font-size: 13px;
        color: #64748b;
    }

    .header-right {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .header-btn {
        position: relative;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: none;
        background: #f1f5f9;
        color: #64748b;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    body.dark-mode .header-btn {
        background: #334155;
    }

    .header-btn:hover {
        background: #6366f1;
        color: white;
        transform: translateY(-2px);
    }

    .badge-count {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ef4444;
        color: white;
        font-size: 10px;
        font-weight: 600;
        padding: 2px 6px;
        border-radius: 10px;
        min-width: 18px;
        text-align: center;
    }

    .user-menu {
        position: relative;
    }

    .user-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        background: none;
        border: none;
        cursor: pointer;
        padding: 8px 12px;
        border-radius: 10px;
        transition: all 0.3s ease;
    }

    .user-btn:hover {
        background: #f1f5f9;
    }

    body.dark-mode .user-btn:hover {
        background: #334155;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
    }

    .user-info {
        text-align: left;
    }

    .user-name {
        font-weight: 600;
        font-size: 14px;
    }

    .user-role {
        font-size: 12px;
        color: #64748b;
    }

    .user-dropdown {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        min-width: 200px;
        padding: 8px;
        display: none;
        z-index: 1000;
    }

    body.dark-mode .user-dropdown {
        background: #1e293b;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
    }

    .user-dropdown.active {
        display: block;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        color: #334155;
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.3s ease;
        font-size: 14px;
    }

    body.dark-mode .dropdown-item {
        color: #f1f5f9;
    }

    .dropdown-item:hover {
        background: #f1f5f9;
    }

    body.dark-mode .dropdown-item:hover {
        background: #334155;
    }

    .dropdown-divider {
        height: 1px;
        background: #e2e8f0;
        margin: 8px 0;
    }

    body.dark-mode .dropdown-divider {
        background: #334155;
    }

    @media (max-width: 768px) {
        .menu-toggle {
            display: block;
        }

        .header-welcome h2 {
            font-size: 16px;
        }

        .header-welcome p {
            display: none;
        }

        .user-info {
            display: none;
        }
    }
</style>

<script>
    function toggleUserMenu() {
        document.getElementById('userDropdown').classList.toggle('active');
    }

    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }

    function toggleDarkMode() {
        document.body.classList.toggle('dark-mode');
        const isDark = document.body.classList.contains('dark-mode');
        localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
        
        const icon = document.querySelector('#darkModeToggle i');
        icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
    }

    function showNotifications() {
        alert('Notifications feature coming soon!');
    }

    // Close user menu when clicking outside
    document.addEventListener('click', function(e) {
        const userMenu = document.querySelector('.user-menu');
        if (userMenu && !userMenu.contains(e.target)) {
            document.getElementById('userDropdown').classList.remove('active');
        }
    });

    // Load dark mode preference
    document.addEventListener('DOMContentLoaded', function() {
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.body.classList.add('dark-mode');
            const icon = document.querySelector('#darkModeToggle i');
            if (icon) icon.className = 'fas fa-sun';
        }
    });
</script>