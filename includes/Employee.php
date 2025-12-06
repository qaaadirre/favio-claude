<?php
// includes/Employee.php
// Employee Management Class

class Employee {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }
    
    /**
     * Get all employees (with filtering)
     */
    public function getAll($shopId = null, $status = 'active', $search = '') {
        $sql = "SELECT e.*, s.name as shop_name 
                FROM employees e 
                LEFT JOIN shops s ON e.shop_id = s.id 
                WHERE 1=1";
        $params = [];
        
        // Filter by shop if not owner
        if ($shopId !== null) {
            $sql .= " AND e.shop_id = ?";
            $params[] = $shopId;
        }
        
        // Filter by status
        if ($status !== 'all') {
            $sql .= " AND e.status = ?";
            $params[] = $status;
        }
        
        // Search functionality
        if (!empty($search)) {
            $sql .= " AND (e.name LIKE ? OR e.phone LIKE ? OR e.role LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        $sql .= " ORDER BY e.created_at DESC";
        
        return $this->db->select($sql, $params);
    }
    
    /**
     * Get single employee by ID
     */
    public function getById($id) {
        $sql = "SELECT e.*, s.name as shop_name 
                FROM employees e 
                LEFT JOIN shops s ON e.shop_id = s.id 
                WHERE e.id = ?";
        return $this->db->selectOne($sql, [$id]);
    }
    
    /**
     * Create new employee
     */
    public function create($data) {
        $sql = "INSERT INTO employees 
                (shop_id, name, phone, age, role, monthly_salary, join_date, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $employeeId = $this->db->insert($sql, [
            $data['shop_id'],
            $data['name'],
            $data['phone'],
            $data['age'] ?? null,
            $data['role'],
            $data['monthly_salary'],
            $data['join_date'],
            $data['status'] ?? 'active'
        ]);
        
        if ($employeeId) {
            $this->auth->logAudit(
                $this->auth->getUserId(),
                'create',
                'employee',
                $employeeId,
                "Created employee: {$data['name']}"
            );
        }
        
        return $employeeId;
    }
    
    /**
     * Update employee
     */
    public function update($id, $data) {
        $sql = "UPDATE employees 
                SET name = ?, phone = ?, age = ?, role = ?, 
                    monthly_salary = ?, status = ?, updated_at = NOW() 
                WHERE id = ?";
        
        $result = $this->db->update($sql, [
            $data['name'],
            $data['phone'],
            $data['age'] ?? null,
            $data['role'],
            $data['monthly_salary'],
            $data['status'] ?? 'active',
            $id
        ]);
        
        if ($result) {
            $this->auth->logAudit(
                $this->auth->getUserId(),
                'update',
                'employee',
                $id,
                "Updated employee: {$data['name']}"
            );
        }
        
        return $result;
    }
    
    /**
     * Delete employee (soft delete by marking inactive)
     */
    public function delete($id) {
        $employee = $this->getById($id);
        
        $sql = "UPDATE employees SET status = 'inactive', updated_at = NOW() WHERE id = ?";
        $result = $this->db->update($sql, [$id]);
        
        if ($result && $employee) {
            $this->auth->logAudit(
                $this->auth->getUserId(),
                'delete',
                'employee',
                $id,
                "Deleted employee: {$employee['name']}"
            );
        }
        
        return $result;
    }
    
    /**
     * Get employee salary balance (total deductions)
     */
    public function getSalaryBalance($employeeId) {
        // Get total unpaid deductions
        $sql = "SELECT COALESCE(SUM(amount), 0) as total_deductions 
                FROM deductions 
                WHERE employee_id = ? AND is_repaid = 0";
        $result = $this->db->selectOne($sql, [$employeeId]);
        
        return $result['total_deductions'] ?? 0;
    }
    
    /**
     * Get employee tasks count for bonus calculation
     */
    public function getTasksCount($employeeId, $startDate, $endDate) {
        $sql = "SELECT COUNT(*) as task_count, 
                       COALESCE(SUM(count), 0) as total_services 
                FROM tasks 
                WHERE employee_id = ? 
                  AND date BETWEEN ? AND ? 
                  AND bonus_applicable = 1";
        
        return $this->db->selectOne($sql, [$employeeId, $startDate, $endDate]);
    }
    
    /**
     * Get employee attendance summary
     */
    public function getAttendanceSummary($employeeId, $month, $year) {
        $sql = "SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'full_day' THEN 1 ELSE 0 END) as full_days,
                    SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_days,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days
                FROM attendance 
                WHERE employee_id = ? 
                  AND MONTH(date) = ? 
                  AND YEAR(date) = ?";
        
        return $this->db->selectOne($sql, [$employeeId, $month, $year]);
    }
    
    /**
     * Calculate monthly salary with deductions
     */
    public function calculateMonthlySalary($employeeId, $month, $year) {
        $employee = $this->getById($employeeId);
        if (!$employee) {
            return null;
        }
        
        $monthlySalary = $employee['monthly_salary'];
        
        // Get attendance summary
        $attendance = $this->getAttendanceSummary($employeeId, $month, $year);
        
        // Get half-day deduction setting
        $sql = "SELECT setting_value 
                FROM shop_settings 
                WHERE shop_id = ? AND setting_key = 'half_day_deduction_percent'";
        $setting = $this->db->selectOne($sql, [$employee['shop_id']]);
        $halfDayPercent = $setting['setting_value'] ?? 50;
        
        // Calculate daily salary
        $dailySalary = $monthlySalary / 30;
        
        // Calculate half-day deduction
        $halfDayDeduction = ($dailySalary * ($halfDayPercent / 100)) * ($attendance['half_days'] ?? 0);
        
        // Get other deductions
        $totalDeductions = $this->getSalaryBalance($employeeId);
        
        // Get bonus
        $tasks = $this->getTasksCount(
            $employeeId,
            "{$year}-{$month}-01",
            date("Y-m-t", strtotime("{$year}-{$month}-01"))
        );
        
        // Get bonus per task setting
        $sql = "SELECT setting_value 
                FROM shop_settings 
                WHERE shop_id = ? AND setting_key = 'bonus_per_task'";
        $bonusSetting = $this->db->selectOne($sql, [$employee['shop_id']]);
        $bonusPerTask = $bonusSetting['setting_value'] ?? 0;
        $totalBonus = ($tasks['total_services'] ?? 0) * $bonusPerTask;
        
        return [
            'employee' => $employee,
            'gross_salary' => $monthlySalary,
            'attendance' => $attendance,
            'half_day_deduction' => $halfDayDeduction,
            'other_deductions' => $totalDeductions,
            'total_deductions' => $halfDayDeduction + $totalDeductions,
            'bonus' => $totalBonus,
            'net_salary' => $monthlySalary - ($halfDayDeduction + $totalDeductions) + $totalBonus,
            'tasks_completed' => $tasks['total_services'] ?? 0
        ];
    }
    
    /**
     * Process salary payment
     */
    public function processSalaryPayment($employeeId, $data) {
        $this->db->beginTransaction();
        
        try {
            // Insert salary payment record
            $sql = "INSERT INTO salary_payments 
                    (employee_id, shop_id, period_start, period_end, 
                     gross_salary, total_deductions, bonuses, net_paid, 
                     paid_on, payment_method, notes, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $paymentId = $this->db->insert($sql, [
                $employeeId,
                $data['shop_id'],
                $data['period_start'],
                $data['period_end'],
                $data['gross_salary'],
                $data['total_deductions'],
                $data['bonuses'],
                $data['net_paid'],
                $data['paid_on'],
                $data['payment_method'],
                $data['notes'] ?? '',
                $this->auth->getUserId()
            ]);
            
            // Mark deductions as repaid
            $sql = "UPDATE deductions 
                    SET is_repaid = 1 
                    WHERE employee_id = ? AND is_repaid = 0";
            $this->db->update($sql, [$employeeId]);
            
            // Log audit
            $this->auth->logAudit(
                $this->auth->getUserId(),
                'salary_payment',
                'employee',
                $employeeId,
                "Processed salary payment: ₹{$data['net_paid']}"
            );
            
            $this->db->commit();
            return $paymentId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    /**
     * Add deduction/advance
     */
    public function addDeduction($data) {
        $sql = "INSERT INTO deductions 
                (employee_id, shop_id, type, amount, date, note, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $deductionId = $this->db->insert($sql, [
            $data['employee_id'],
            $data['shop_id'],
            $data['type'],
            $data['amount'],
            $data['date'],
            $data['note'] ?? '',
            $this->auth->getUserId()
        ]);
        
        if ($deductionId) {
            $this->auth->logAudit(
                $this->auth->getUserId(),
                'add_deduction',
                'employee',
                $data['employee_id'],
                "Added {$data['type']}: ₹{$data['amount']}"
            );
        }
        
        return $deductionId;
    }
    
    /**
     * Get employee deductions history
     */
    public function getDeductions($employeeId, $includeRepaid = false) {
        $sql = "SELECT * FROM deductions WHERE employee_id = ?";
        $params = [$employeeId];
        
        if (!$includeRepaid) {
            $sql .= " AND is_repaid = 0";
        }
        
        $sql .= " ORDER BY date DESC";
        
        return $this->db->select($sql, $params);
    }
    
    /**
     * Get employees by shop with statistics
     */
    public function getByShopWithStats($shopId) {
        $sql = "SELECT 
                    e.*,
                    (SELECT COUNT(*) FROM tasks t WHERE t.employee_id = e.id AND t.date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as tasks_30_days,
                    (SELECT COALESCE(SUM(amount), 0) FROM deductions d WHERE d.employee_id = e.id AND d.is_repaid = 0) as pending_deductions
                FROM employees e
                WHERE e.shop_id = ? AND e.status = 'active'
                ORDER BY e.name";
        
        return $this->db->select($sql, [$shopId]);
    }
}