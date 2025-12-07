<?php
// services.php
// Services & Price List Management Page

require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();

// Get filters
$shopId = $auth->isOwner() ? ($_GET['shop_id'] ?? null) : $auth->getShopId();
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Get services
$services = [];
if ($shopId) {
    $sql = "SELECT s.*, sh.name as shop_name 
            FROM services s 
            LEFT JOIN shops sh ON s.shop_id = sh.id 
            WHERE s.shop_id = ?";
    $params = [$shopId];
    
    if ($status !== 'all') {
        $sql .= " AND s.status = ?";
        $params[] = $status;
    }
    
    if (!empty($search)) {
        $sql .= " AND s.name LIKE ?";
        $params[] = "%{$search}%";
    }
    
    $sql .= " ORDER BY s.name ASC";
    $services = $db->select($sql, $params);
}

// Get shops for owner
$shops = [];
if ($auth->isOwner()) {
    $shops = $db->select("SELECT * FROM shops ORDER BY name");
}

$csrfToken = $auth->generateCsrfToken();
$currentPage = 'services';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - Salon Management System</title>
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
                        <h1 class="page-title">Services & Price List</h1>
                        <p class="breadcrumb">Home / Services</p>
                    </div>
                    <?php if ($auth->getRole() !== 'staff'): ?>
                    <button class="btn btn-success" onclick="openModal('addServiceModal')">
                        <i class="fas fa-plus"></i>
                        Add Service
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Statistics -->
                <div class="cards-grid">
                    <div class="card">
                        <div class="card-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="card-title">Active Services</div>
                        <div class="card-value"><?php echo count(array_filter($services, fn($s) => $s['status'] === 'active')); ?></div>
                    </div>
                    <div class="card">
                        <div class="card-icon primary">
                            <i class="fas fa-list"></i>
                        </div>
                        <div class="card-title">Total Services</div>
                        <div class="card-value"><?php echo count($services); ?></div>
                    </div>
                    <?php if (!empty($services)): 
                        $avgPrice = array_sum(array_column($services, 'price')) / count($services);
                    ?>
                    <div class="card">
                        <div class="card-icon info">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                        <div class="card-title">Average Price</div>
                        <div class="card-value">₹<?php echo number_format($avgPrice, 0); ?></div>
                    </div>
                    <?php endif; ?>
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
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="all" <?php echo ($status == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo ($status == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($status == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Service name..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">
                                <i class="fas fa-search"></i>
                                Search
                            </button>
                            <a href="services.php" class="btn btn-secondary">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Services Grid -->
                <?php if (empty($services)): ?>
                <div class="card" style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-scissors" style="font-size: 64px; color: #cbd5e1; margin-bottom: 16px;"></i>
                    <p style="font-size: 18px; color: #64748b; margin-bottom: 8px;">No services found</p>
                    <p style="font-size: 14px; color: #94a3b8;">Add your first service to get started</p>
                </div>
                <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                    <?php foreach ($services as $service): ?>
                    <div class="card" style="padding: 0; overflow: hidden;">
                        <div style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); padding: 20px; color: white;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 12px;">
                                        <i class="fas fa-scissors"></i>
                                    </div>
                                    <h3 style="margin-bottom: 5px; font-size: 18px;"><?php echo htmlspecialchars($service['name']); ?></h3>
                                </div>
                                <span class="badge" style="background: <?php echo $service['status'] === 'active' ? '#10b981' : '#ef4444'; ?>; color: white;">
                                    <?php echo ucfirst($service['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div style="padding: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding: 15px; background: #f0fdf4; border-radius: 10px;">
                                <div>
                                    <div style="font-size: 12px; color: #166534; margin-bottom: 3px;">Price</div>
                                    <div style="font-size: 24px; font-weight: 700; color: #10b981;">₹<?php echo number_format($service['price'], 0); ?></div>
                                </div>
                                <?php if ($service['duration']): ?>
                                <div style="text-align: right;">
                                    <div style="font-size: 12px; color: #166534; margin-bottom: 3px;">Duration</div>
                                    <div style="font-size: 18px; font-weight: 600; color: #10b981;"><?php echo $service['duration']; ?> min</div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($auth->getRole() !== 'staff'): ?>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn btn-warning" style="flex: 1; font-size: 13px;" onclick="editService(<?php echo $service['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-danger" style="flex: 1; font-size: 13px;" onclick="deleteService(<?php echo $service['id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
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

    <!-- Add/Edit Service Modal -->
    <div class="modal" id="addServiceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Service</h2>
                <button class="close-modal" onclick="closeModal('addServiceModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="serviceForm" onsubmit="return handleServiceSubmit(event)">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="shop_id" value="<?php echo $shopId; ?>">
                
                <div class="form-group">
                    <label class="form-label">Service Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Men's Haircut" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Price (₹) *</label>
                        <input type="number" name="price" class="form-control" placeholder="0.00" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Duration (minutes)</label>
                        <input type="number" name="duration" class="form-control" placeholder="30" min="5" max="480">
                    </div>
                </div>
                
                <?php if ($auth->isOwner()): ?>
                <div class="form-group">
                    <label class="form-label">Branch *</label>
                    <select name="shop_id" class="form-control" required>
                        <option value="">Select branch</option>
                        <?php foreach ($shops as $shop): ?>
                        <option value="<?php echo $shop['id']; ?>" <?php echo ($shopId == $shop['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($shop['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addServiceModal')">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        Save Service
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/services.js"></script>
</body>
</html>