<?php
// attendance.php
// Attendance Management Page

require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/Attendance.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$attendance = new Attendance();

// Get filters
$shopId = $auth->isOwner() ? ($_GET['shop_id'] ?? null) : $auth->getShopId();
$date = $_GET['date'] ?? date('Y-m-d');
$view = $_GET['view'] ?? 'daily';

// Get daily report if daily view
$dailyReport = [];
if ($view === 'daily' && $shopId) {
    $dailyReport = $attendance->getDailyReport($shopId, $date);
}

// Get shops for owner
$shops = [];
if ($auth->isOwner()) {
    $shops = $db->select("SELECT * FROM shops ORDER BY name");
}

// Get current month attendance if monthly view
$monthlyData = [];
if ($view === 'monthly' && $shopId) {
    $month = $_GET['month'] ?? date('m');
    $year = $_GET['year'] ?? date('Y');
    
    $sql = "SELECT e.id, e.name, e.role, a.*
            FROM employees e
            LEFT JOIN attendance a ON e.id = a.employee_id 
                AND MONTH(a.date) = ? AND YEAR(a.date) = ?
            WHERE e.shop_id = ? AND e.status = 'active'
            ORDER BY e.name, a.date";
    $monthlyData = $db->select($sql, [$month, $year, $shopId]);
}

$csrfToken = $auth->generateCsrfToken();
$currentPage = 'attendance';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Salon Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .attendance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        .attendance-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .attendance-card.marked { border-left: 4px solid #10b981; }
        .attendance-card.not-marked { border-left: 4px solid #f59e0b; }
        .status-badges {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .status-badge.active {
            font-weight: 600;
            transform: scale(1.05);
        }
        .status-badge.full_day { background: #dcfce7; color: #166534; }
        .status-badge.full_day.active { background: #10b981; color: white; }
        .status-badge.half_day { background: #fef3c7; color: #92400e; }
        .status-badge.half_day.active { background: #f59e0b; color: white; }
        .status-badge.absent { background: #fee2e2; color: #991b1b; }
        .status-badge.absent.active { background: #ef4444; color: white; }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 20px;
        }
        .calendar-day {
            padding: 15px;
            text-align: center;
            border-radius: 8px;
            background: #f8fafc;
            font-size: 13px;
        }
        .calendar-day.header {
            font-weight: 600;
            background: #6366f1;
            color: white;
        }
        .calendar-day .date { font-weight: 600; margin-bottom: 5px; }
        .calendar-day.full_day { background: #dcfce7; }
        .calendar-day.half_day { background: #fef3c7; }
        .calendar-day.absent { background: #fee2e2; }
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
                        <h1 class="page-title">Attendance Management</h1>
                        <p class="breadcrumb">Home / Attendance</p>
                    </div>
                </div>

                <!-- View Switcher & Filters -->
                <div class="card" style="margin-bottom: 20px;">
                    <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">View</label>
                            <select name="view" class="form-control" onchange="this.form.submit()">
                                <option value="daily" <?php echo $view === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="monthly" <?php echo $view === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            </select>
                        </div>
                        
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
                        
                        <?php if ($view === 'daily'): ?>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo $date; ?>">
                        </div>
                        <?php else: ?>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label">Month</label>
                            <input type="month" name="month_year" class="form-control" 
                                   value="<?php echo $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT); ?>">
                        </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Load
                        </button>
                    </form>
                </div>

                <?php if ($view === 'daily' && !empty($dailyReport)): ?>
                <!-- Daily Attendance -->
                <div class="attendance-grid">
                    <?php foreach ($dailyReport as $emp): ?>
                    <div class="attendance-card <?php echo $emp['attendance_status'] === 'not_marked' ? 'not-marked' : 'marked'; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <h4 style="margin-bottom: 5px;"><?php echo htmlspecialchars($emp['name']); ?></h4>
                                <p style="color: #64748b; font-size: 13px;"><?php echo htmlspecialchars($emp['role']); ?></p>
                            </div>
                            <?php if ($emp['attendance_status'] === 'not_marked'): ?>
                            <span class="badge badge-warning">Not Marked</span>
                            <?php else: ?>
                            <span class="badge badge-success">Marked</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($emp['attendance_status'] !== 'not_marked'): ?>
                        <div style="margin: 15px 0; padding: 12px; background: #f8fafc; border-radius: 8px;">
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Status</div>
                            <div style="font-weight: 600; text-transform: uppercase;">
                                <?php echo str_replace('_', ' ', $emp['status']); ?>
                            </div>
                            <?php if ($emp['check_in_time']): ?>
                            <div style="font-size: 12px; color: #64748b; margin-top: 8px;">
                                In: <?php echo date('h:i A', strtotime($emp['check_in_time'])); ?>
                                <?php if ($emp['check_out_time']): ?>
                                | Out: <?php echo date('h:i A', strtotime($emp['check_out_time'])); ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($auth->getRole() !== 'staff'): ?>
                        <div class="status-badges">
                            <div class="status-badge full_day <?php echo $emp['status'] === 'full_day' ? 'active' : ''; ?>"
                                 onclick="markAttendance(<?php echo $emp['id']; ?>, 'full_day')">
                                <i class="fas fa-check"></i> Full
                            </div>
                            <div class="status-badge half_day <?php echo $emp['status'] === 'half_day' ? 'active' : ''; ?>"
                                 onclick="markAttendance(<?php echo $emp['id']; ?>, 'half_day')">
                                <i class="fas fa-adjust"></i> Half
                            </div>
                            <div class="status-badge absent <?php echo $emp['status'] === 'absent' ? 'active' : ''; ?>"
                                 onclick="markAttendance(<?php echo $emp['id']; ?>, 'absent')">
                                <i class="fas fa-times"></i> Absent
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php elseif ($view === 'monthly'): ?>
                <!-- Monthly Calendar View -->
                <div class="card">
                    <h3 style="margin-bottom: 20px;">Monthly Attendance - <?php echo date('F Y', strtotime($year . '-' . $month . '-01')); ?></h3>
                    <p style="color: #64748b; margin-bottom: 20px;">Click on employee name to view detailed attendance</p>
                    
                    <?php
                    // Group by employee
                    $employeeAttendance = [];
                    foreach ($monthlyData as $row) {
                        $employeeAttendance[$row['id']]['name'] = $row['name'];
                        $employeeAttendance[$row['id']]['role'] = $row['role'];
                        if (!empty($row['date'])) {
                            $employeeAttendance[$row['id']]['attendance'][$row['date']] = $row['status'];
                        }
                    }
                    ?>
                    
                    <?php foreach ($employeeAttendance as $empId => $empData): ?>
                    <div style="margin-bottom: 30px; padding: 20px; background: #f8fafc; border-radius: 12px;">
                        <h4 style="margin-bottom: 5px;"><?php echo htmlspecialchars($empData['name']); ?></h4>
                        <p style="color: #64748b; font-size: 13px; margin-bottom: 15px;"><?php echo htmlspecialchars($empData['role']); ?></p>
                        
                        <?php
                        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                        $fullDays = 0;
                        $halfDays = 0;
                        $absentDays = 0;
                        
                        echo '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); gap: 8px;">';
                        for ($day = 1; $day <= $daysInMonth; $day++) {
                            $checkDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $status = $empData['attendance'][$checkDate] ?? 'not_marked';
                            
                            if ($status === 'full_day') $fullDays++;
                            elseif ($status === 'half_day') $halfDays++;
                            elseif ($status === 'absent') $absentDays++;
                            
                            $bgColor = '#f1f5f9';
                            if ($status === 'full_day') $bgColor = '#dcfce7';
                            elseif ($status === 'half_day') $bgColor = '#fef3c7';
                            elseif ($status === 'absent') $bgColor = '#fee2e2';
                            
                            echo '<div style="padding: 8px; text-align: center; border-radius: 6px; background: ' . $bgColor . '; font-size: 12px;">';
                            echo '<div style="font-weight: 600;">' . $day . '</div>';
                            echo '<div style="font-size: 10px;">' . date('D', strtotime($checkDate)) . '</div>';
                            echo '</div>';
                        }
                        echo '</div>';
                        ?>
                        
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 15px;">
                            <div style="text-align: center; padding: 10px; background: #dcfce7; border-radius: 8px;">
                                <div style="font-size: 12px; color: #166534;">Full Days</div>
                                <div style="font-size: 20px; font-weight: 700; color: #10b981;"><?php echo $fullDays; ?></div>
                            </div>
                            <div style="text-align: center; padding: 10px; background: #fef3c7; border-radius: 8px;">
                                <div style="font-size: 12px; color: #92400e;">Half Days</div>
                                <div style="font-size: 20px; font-weight: 700; color: #f59e0b;"><?php echo $halfDays; ?></div>
                            </div>
                            <div style="text-align: center; padding: 10px; background: #fee2e2; border-radius: 8px;">
                                <div style="font-size: 12px; color: #991b1b;">Absent</div>
                                <div style="font-size: 20px; font-weight: 700; color: #ef4444;"><?php echo $absentDays; ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="card" style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-calendar-check" style="font-size: 64px; color: #cbd5e1; margin-bottom: 16px;"></i>
                    <p style="font-size: 18px; color: #64748b;">Please select a branch to view attendance</p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php include 'includes/footer.php'; ?>
        </main>
    </div>

    <script>
        async function markAttendance(employeeId, status) {
            if (!confirm('Mark attendance as ' + status.replace('_', ' ').toUpperCase() + '?')) {
                return;
            }
            
            const urlParams = new URLSearchParams(window.location.search);
            const shopId = urlParams.get('shop_id') || <?php echo json_encode($auth->getShopId()); ?>;
            const date = urlParams.get('date') || '<?php echo date('Y-m-d'); ?>';
            
            try {
                const response = await fetch('api/attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        employee_id: employeeId,
                        shop_id: shopId,
                        date: date,
                        status: status,
                        check_in_time: new Date().toTimeString().split(' ')[0],
                        csrf_token: '<?php echo $csrfToken; ?>'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Attendance marked successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to mark attendance. Please try again.');
            }
        }
    </script>
</body>
</html>