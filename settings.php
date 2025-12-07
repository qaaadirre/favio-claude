<?php
// settings.php
// Shop Settings & Configuration Page

require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole(['owner', 'branch_admin']);

$db = Database::getInstance();

// Get shop info
if ($auth->isOwner()) {
    $shopId = $_GET['shop_id'] ?? null;
    if ($shopId) {
        $shop = $db->selectOne("SELECT * FROM shops WHERE id = ?", [$shopId]);
    }
    $shops = $db->select("SELECT * FROM shops ORDER BY name");
} else {
    $shopId = $auth->getShopId();
    $shop = $db->selectOne("SELECT * FROM shops WHERE id = ?", [$shopId]);
    $shops = [$shop];
}

// Handle settings update
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    
    if (!$auth->verifyCsrfToken($csrf)) {
        $error = 'Invalid security token';
    } else {
        $settingShopId = $_POST['shop_id'] ?? $shopId;
        
        try {
            // Update shop basic info
            if (isset($_POST['shop_name'])) {
                $sql = "UPDATE shops SET name = ?, address = ?, phone = ? WHERE id = ?";
                $db->update($sql, [
                    $_POST['shop_name'],
                    $_POST['shop_address'],
                    $_POST['shop_phone'],
                    $settingShopId
                ]);
            }
            
            // Update half-day deduction setting
            if (isset($_POST['half_day_deduction'])) {
                $sql = "INSERT INTO shop_settings (shop_id, setting_key, setting_value) 
                        VALUES (?, 'half_day_deduction_percent', ?) 
                        ON DUPLICATE KEY UPDATE setting_value = ?";
                $db->query($sql, [
                    $settingShopId,
                    $_POST['half_day_deduction'],
                    $_POST['half_day_deduction']
                ]);
            }
            
            // Update bonus per task setting
            if (isset($_POST['bonus_per_task'])) {
                $sql = "INSERT INTO shop_settings (shop_id, setting_key, setting_value) 
                        VALUES (?, 'bonus_per_task', ?) 
                        ON DUPLICATE KEY UPDATE setting_value = ?";
                $db->query($sql, [
                    $settingShopId,
                    $_POST['bonus_per_task'],
                    $_POST['bonus_per_task']
                ]);
            }
            
            // Handle logo upload
            if (isset($_FILES['shop_logo']) && $_FILES['shop_logo']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/jpg'];
                $fileType = $_FILES['shop_logo']['type'];
                
                if (in_array($fileType, $allowed) && $_FILES['shop_logo']['size'] <= 2097152) {
                    $extension = pathinfo($_FILES['shop_logo']['name'], PATHINFO_EXTENSION);
                    $filename = 'shop_' . $settingShopId . '_' . time() . '.' . $extension;
                    $uploadPath = LOGO_PATH . $filename;
                    
                    if (move_uploaded_file($_FILES['shop_logo']['tmp_name'], $uploadPath)) {
                        $sql = "UPDATE shops SET logo_path = ? WHERE id = ?";
                        $db->update($sql, [$uploadPath, $settingShopId]);
                    }
                }
            }
            
            $auth->logAudit($auth->getUserId(), 'update_settings', 'shop', $settingShopId, 'Updated shop settings');
            $success = 'Settings updated successfully!';
            
            // Reload shop data
            $shop = $db->selectOne("SELECT * FROM shops WHERE id = ?", [$settingShopId]);
            
        } catch (Exception $e) {
            $error = 'Failed to update settings: ' . $e->getMessage();
        }
    }
}

// Get settings
$settings = [];
if ($shopId) {
    $settingsData = $db->select("SELECT * FROM shop_settings WHERE shop_id = ?", [$shopId]);
    foreach ($settingsData as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
}

$csrfToken = $auth->generateCsrfToken();
$currentPage = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Salon Management System</title>
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
                        <h1 class="page-title">Shop Settings</h1>
                        <p class="breadcrumb">Home / Settings</p>
                    </div>
                </div>

                <?php if ($success): ?>
                <div style="padding: 15px; background: #d1fae5; border: 1px solid #86efac; border-radius: 8px; margin-bottom: 20px; color: #065f46;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div style="padding: 15px; background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; margin-bottom: 20px; color: #dc2626;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Shop Selector for Owner -->
                <?php if ($auth->isOwner()): ?>
                <div class="card" style="margin-bottom: 20px;">
                    <form method="GET" action="">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Select Branch to Configure</label>
                            <select name="shop_id" class="form-control" onchange="this.form.submit()">
                                <option value="">Choose a branch</option>
                                <?php foreach ($shops as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($shopId == $s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($shop): ?>
                <!-- Settings Form -->
                <div class="card">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="shop_id" value="<?php echo $shop['id']; ?>">
                        <input type="hidden" name="update_settings" value="1">
                        
                        <!-- Basic Info Section -->
                        <div style="margin-bottom: 30px;">
                            <h3 style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;">
                                <i class="fas fa-store"></i> Basic Information
                            </h3>
                            
                            <div class="form-group">
                                <label class="form-label">Shop Name *</label>
                                <input type="text" name="shop_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($shop['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Address</label>
                                <textarea name="shop_address" class="form-control" rows="3"><?php echo htmlspecialchars($shop['address']); ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="shop_phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($shop['phone']); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Timezone</label>
                                    <select name="timezone" class="form-control">
                                        <option value="Asia/Kolkata" <?php echo ($shop['timezone'] === 'Asia/Kolkata') ? 'selected' : ''; ?>>Asia/Kolkata (IST)</option>
                                        <option value="Asia/Dubai">Asia/Dubai (GST)</option>
                                        <option value="America/New_York">America/New_York (EST)</option>
                                        <option value="Europe/London">Europe/London (GMT)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Shop Logo</label>
                                <?php if (!empty($shop['logo_path']) && file_exists($shop['logo_path'])): ?>
                                <div style="margin-bottom: 10px;">
                                    <img src="<?php echo htmlspecialchars($shop['logo_path']); ?>" 
                                         style="max-width: 200px; height: auto; border-radius: 8px; border: 1px solid #e2e8f0;">
                                </div>
                                <?php endif; ?>
                                <input type="file" name="shop_logo" class="form-control" accept="image/jpeg,image/png,image/jpg">
                                <small style="color: #64748b; font-size: 12px;">Maximum file size: 2MB. Supported formats: JPG, JPEG, PNG</small>
                            </div>
                        </div>
                        
                        <!-- Salary & Deduction Settings -->
                        <div style="margin-bottom: 30px;">
                            <h3 style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;">
                                <i class="fas fa-money-bill-wave"></i> Salary & Deduction Settings
                            </h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Half-Day Deduction (%)</label>
                                    <input type="number" name="half_day_deduction" class="form-control" 
                                           value="<?php echo $settings['half_day_deduction_percent'] ?? 50; ?>" 
                                           min="0" max="100" step="1">
                                    <small style="color: #64748b; font-size: 12px;">
                                        Percentage of daily salary to deduct for half-day attendance
                                    </small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Bonus per Task (₹)</label>
                                    <input type="number" name="bonus_per_task" class="form-control" 
                                           value="<?php echo $settings['bonus_per_task'] ?? 50; ?>" 
                                           min="0" step="0.01">
                                    <small style="color: #64748b; font-size: 12px;">
                                        Bonus amount awarded for each task completed by employee
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notification Settings -->
                        <div style="margin-bottom: 30px;">
                            <h3 style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;">
                                <i class="fas fa-bell"></i> Notification Settings
                            </h3>
                            
                            <div class="form-group">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" name="notify_low_balance" value="1" 
                                           <?php echo !empty($settings['notify_low_balance']) ? 'checked' : ''; ?>>
                                    <span>Send alerts when employee has high outstanding deductions</span>
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" name="notify_attendance" value="1" 
                                           <?php echo !empty($settings['notify_attendance']) ? 'checked' : ''; ?>>
                                    <span>Daily reminder to mark employee attendance</span>
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" name="notify_salary_due" value="1" 
                                           <?php echo !empty($settings['notify_salary_due']) ? 'checked' : ''; ?>>
                                    <span>Reminder when monthly salary processing is due</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Working Hours -->
                        <div style="margin-bottom: 30px;">
                            <h3 style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;">
                                <i class="fas fa-clock"></i> Working Hours
                            </h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Opening Time</label>
                                    <input type="time" name="opening_time" class="form-control" 
                                           value="<?php echo $settings['opening_time'] ?? '09:00'; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Closing Time</label>
                                    <input type="time" name="closing_time" class="form-control" 
                                           value="<?php echo $settings['closing_time'] ?? '18:00'; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Weekly Off Day</label>
                                <select name="weekly_off" class="form-control">
                                    <option value="">None</option>
                                    <option value="monday" <?php echo ($settings['weekly_off'] ?? '') === 'monday' ? 'selected' : ''; ?>>Monday</option>
                                    <option value="tuesday" <?php echo ($settings['weekly_off'] ?? '') === 'tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                    <option value="wednesday" <?php echo ($settings['weekly_off'] ?? '') === 'wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                    <option value="thursday" <?php echo ($settings['weekly_off'] ?? '') === 'thursday' ? 'selected' : ''; ?>>Thursday</option>
                                    <option value="friday" <?php echo ($settings['weekly_off'] ?? '') === 'friday' ? 'selected' : ''; ?>>Friday</option>
                                    <option value="saturday" <?php echo ($settings['weekly_off'] ?? '') === 'saturday' ? 'selected' : ''; ?>>Saturday</option>
                                    <option value="sunday" <?php echo ($settings['weekly_off'] ?? '') === 'sunday' ? 'selected' : ''; ?>>Sunday</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Save Button -->
                        <div style="display: flex; justify-content: flex-end; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                            <button type="submit" class="btn btn-primary" style="min-width: 200px;">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Danger Zone -->
                <?php if ($auth->isOwner()): ?>
                <div class="card" style="margin-top: 30px; border: 2px solid #ef4444;">
                    <h3 style="color: #ef4444; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle"></i> Danger Zone
                    </h3>
                    
                    <div style="display: grid; gap: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #fef2f2; border-radius: 8px;">
                            <div>
                                <strong style="display: block; margin-bottom: 5px;">Database Backup</strong>
                                <span style="font-size: 13px; color: #64748b;">Download a complete backup of your database</span>
                            </div>
                            <a href="backup.php" class="btn btn-secondary">
                                <i class="fas fa-download"></i> Download Backup
                            </a>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #fef2f2; border-radius: 8px;">
                            <div>
                                <strong style="display: block; margin-bottom: 5px;">Restore Database</strong>
                                <span style="font-size: 13px; color: #64748b;">Upload and restore from a previous backup</span>
                            </div>
                            <button class="btn btn-warning" onclick="openModal('restoreModal')">
                                <i class="fas fa-upload"></i> Restore
                            </button>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #fef2f2; border-radius: 8px;">
                            <div>
                                <strong style="display: block; margin-bottom: 5px; color: #ef4444;">Delete This Branch</strong>
                                <span style="font-size: 13px; color: #64748b;">Permanently delete this branch and all its data</span>
                            </div>
                            <button class="btn btn-danger" onclick="confirmDeleteBranch()">
                                <i class="fas fa-trash"></i> Delete Branch
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="card" style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-cog" style="font-size: 64px; color: #cbd5e1; margin-bottom: 16px;"></i>
                    <p style="font-size: 18px; color: #64748b;">Please select a branch to configure settings</p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php include 'includes/footer.php'; ?>
        </main>
    </div>

    <script>
        function confirmDeleteBranch() {
            if (confirm('WARNING: This will permanently delete this branch and ALL associated data including employees, expenses, attendance records, etc. This action cannot be undone!\n\nType "DELETE" to confirm.')) {
                const confirmation = prompt('Please type "DELETE" to confirm:');
                if (confirmation === 'DELETE') {
                    alert('Branch deletion feature will be implemented in production version.');
                }
            }
        }
    </script>
</body>
</html>