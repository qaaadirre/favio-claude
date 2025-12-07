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
     * Get all employees with optional filtering
     */
    public function getAll($shopId = null, $status = 'active', $search = '') {
        $sql = "SELECT e.*, s.name as shop_name 
                FROM employees e 
                LEFT JOIN shops s ON e.shop_id = s.id 
                WHERE 1=1";
        
        $params = [];
        
        if ($shopId !== null) {
            $sql .= " AND e.shop_id = ?";
            $params[] = $shopId;
        }
        
        if ($status !== 'all') {
            $sql .= " AND e.status = ?";
            $params[] = $status;
        }
        
        if (!empty($search)) {
            $sql .= " AND (e.name LIKE ? OR e.phone LIKE ? OR e.role LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        $sql .= " ORDER BY e.name ASC";
        
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
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
        
        $employeeId = $this->db->insert($sql, [
            $data['shop_id'],
            $data['name'],
            $data['phone'],
            $data['age'] ?? null,
            $data['role'],
            $data['monthly_salary'],
            $data['join_date']
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
        $fields = [];
        $params = [];
        
        $allowedFields = ['name', 'phone', 'age', 'role', 'monthly_salary', 'join_date', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $id;
        $sql = "UPDATE employees SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
        
        $result = $this->db->update($sql, $params);
        
        if ($result) {
            $this->auth->logAudit(
                $this->auth->getUserId(),
                'update',
                'employee',
                $id,
                "Updated employee: " . json_encode($data)
            );
        }
        
        return $result;
    }
    
    /**
     * Delete employee (soft delete by changing status)
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
     * Get employee deductions
     */
    public function getDeductions($employeeId, $includeRepaid = false) {
        $sql = "SELECT * FROM deductions 
                WHERE employee_id = ?";
        
        if (!$includeRepaid) {
            $sql .= " AND is_repaid = 0";
        }
        
        $sql .= " ORDER BY date DESC";
        
        return $this->db->select($sql, [$employeeId]);
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
                "Added deduction: {$data['type']} - ₹{$data['amount']}"
            );
        }
        
        return $deductionId;
    }
    
    /**
     * Calculate monthly salary for employee
     */
    public function calculateMonthlySalary($employeeId, $month, $year) {
        // Get employee info
        $employee = $this->getById($employeeId);
        if (!$employee) {
            return false;
        }
        
        // Calculate period
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $periodStart = sprintf('%04d-%02d-01', $year, $month);
        $periodEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
        
        // Get attendance for the month
        $sql = "SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'full_day' THEN 1 ELSE 0 END) as full_days,
                    SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_days,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days
                FROM attendance 
                WHERE employee_id = ? 
                  AND date >= ? 
                  AND date <= ?";
        
        $attendance = $this->db->selectOne($sql, [$employeeId, $periodStart, $periodEnd]);
        
        // Calculate gross salary
        $grossSalary = $employee['monthly_salary'];
        
        // Calculate half-day deductions
        $sql = "SELECT COALESCE(SUM(amount), 0) as total 
                FROM deductions 
                WHERE employee_id = ? 
                  AND type = 'half_day' 
                  AND date >= ? 
                  AND date <= ?";
        
        $halfDayDeduction = $this->db->selectOne($sql, [$employeeId, $periodStart, $periodEnd]);
        
        // Calculate other deductions (advances, loans, etc.)
        $sql = "SELECT COALESCE(SUM(amount), 0) as total 
                FROM deductions 
                WHERE employee_id = ? 
                  AND type != 'half_day' 
                  AND is_repaid = 0 
                  AND date <= ?";
        
        $otherDeductions = $this->db->selectOne($sql, [$employeeId, $periodEnd]);
        
        // Calculate total deductions
        $totalDeductions = $halfDayDeduction['total'] + $otherDeductions['total'];
        
        // Get tasks completed for bonus calculation
        $sql = "SELECT COUNT(*) as total 
                FROM tasks 
                WHERE employee_id = ? 
                  AND date >= ? 
                  AND date <= ? 
                  AND bonus_applicable = 1";
        
        $tasks = $this->db->selectOne($sql, [$employeeId, $periodStart, $periodEnd]);
        
        // Get bonus per task from settings
        $sql = "SELECT setting_value FROM shop_settings 
                WHERE shop_id = ? AND setting_key = 'bonus_per_task'";
        $bonusPerTask = $this->db->selectOne($sql, [$employee['shop_id']]);
        $bonusRate = $bonusPerTask['setting_value'] ?? 50;
        
        $bonus = $tasks['total'] * $bonusRate;
        
        // Calculate net salary
        $netSalary = $grossSalary - $totalDeductions + $bonus;
        
        return [
            'employee' => $employee,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'gross_salary' => $grossSalary,
            'half_day_deduction' => $halfDayDeduction['total'],
            'other_deductions' => $otherDeductions['total'],
            'total_deductions' => $totalDeductions,
            'tasks_completed' => $tasks['total'],
            'bonus' => $bonus,
            'net_salary' => max(0, $netSalary), // Ensure non-negative
            'attendance' => [
                'full_days' => $attendance['full_days'] ?? 0,
                'half_days' => $attendance['half_days'] ?? 0,
                'absent_days' => $attendance['absent_days'] ?? 0,
                'total_days' => $attendance['total_days'] ?? 0
            ]
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
                    WHERE employee_id = ? 
                      AND is_repaid = 0 
                      AND type != 'half_day'";
            $this->db->update($sql, [$employeeId]);
            
            // Log audit
            $this->auth->logAudit(
                $this->auth->getUserId(),
                'process_salary',
                'employee',
                $employeeId,
                "Processed salary payment: ₹{$data['net_paid']}"
            );
            
            $this->db->commit();
            return $paymentId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Salary payment error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get salary payment history
     */
    public function getSalaryHistory($employeeId, $limit = 10) {
        $sql = "SELECT sp.*, u.name as processed_by_name 
                FROM salary_payments sp 
                LEFT JOIN users u ON sp.created_by = u.id 
                WHERE sp.employee_id = ? 
                ORDER BY sp.paid_on DESC, sp.created_at DESC 
                LIMIT ?";
        
        return $this->db->select($sql, [$employeeId, $limit]);
    }
    
    /**
     * Get employee statistics
     */
    public function getStatistics($shopId = null) {
        $sql = "SELECT 
                    COUNT(*) as total_employees,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_employees,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_employees,
                    COALESCE(SUM(monthly_salary), 0) as total_monthly_salary
                FROM employees";
        
        $params = [];
        
        if ($shopId !== null) {
            $sql .= " WHERE shop_id = ?";
            $params[] = $shopId;
        }
        
        return $this->db->selectOne($sql, $params);
    }
}