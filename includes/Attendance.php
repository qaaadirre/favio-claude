<?php
// includes/Attendance.php
// Attendance Management Class

class Attendance {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }
    
    /**
     * Mark attendance for employee
     */
    public function markAttendance($data) {
        // Check if attendance already exists for today
        $existing = $this->getByEmployeeAndDate($data['employee_id'], $data['date']);
        
        if ($existing) {
            // Update existing attendance
            return $this->updateAttendance($existing['id'], $data);
        }
        
        // Calculate deduction if half-day
        if ($data['status'] === 'half_day') {
            $this->createHalfDayDeduction($data['employee_id'], $data['shop_id'], $data['date']);
        }
        
        $sql = "INSERT INTO attendance 
                (employee_id, shop_id, date, status, check_in_time, check_out_time, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $attendanceId = $this->db->insert($sql, [
            $data['employee_id'],
            $data['shop_id'],
            $data['date'],
            $data['status'],
            $data['check_in_time'] ?? null,
            $data['check_out_time'] ?? null,
            $data['notes'] ?? ''
        ]);
        
        if ($attendanceId) {
            $this->auth->logAudit(
                $this->auth->getUserId(),
                'mark_attendance',
                'attendance',
                $attendanceId,
                "Marked attendance: Employee {$data['employee_id']} - {$data['status']}"
            );
        }
        
        return $attendanceId;
    }
    
    /**
     * Update existing attendance
     */
    public function updateAttendance($id, $data) {
        $existing = $this->getById($id);
        
        // If status changed from non-half-day to half-day, create deduction
        if ($existing['status'] !== 'half_day' && $data['status'] === 'half_day') {
            $this->createHalfDayDeduction($existing['employee_id'], $existing['shop_id'], $existing['date']);
        }
        
        // If status changed from half-day to non-half-day, remove deduction
        if ($existing['status'] === 'half_day' && $data['status'] !== 'half_day') {
            $this->removeHalfDayDeduction($existing['employee_id'], $existing['date']);
        }
        
        $sql = "UPDATE attendance 
                SET status = ?, check_in_time = ?, check_out_time = ?, notes = ?, updated_at = NOW() 
                WHERE id = ?";
        
        return $this->db->update($sql, [
            $data['status'],
            $data['check_in_time'] ?? null,
            $data['check_out_time'] ?? null,
            $data['notes'] ?? '',
            $id
        ]);
    }
    
    /**
     * Create half-day deduction
     */
    private function createHalfDayDeduction($employeeId, $shopId, $date) {
        // Get employee salary
        $sql = "SELECT monthly_salary FROM employees WHERE id = ?";
        $employee = $this->db->selectOne($sql, [$employeeId]);
        
        if (!$employee) {
            return false;
        }
        
        // Get half-day deduction percentage
        $sql = "SELECT setting_value FROM shop_settings 
                WHERE shop_id = ? AND setting_key = 'half_day_deduction_percent'";
        $setting = $this->db->selectOne($sql, [$shopId]);
        $deductionPercent = $setting['setting_value'] ?? 50;
        
        // Calculate deduction
        $dailySalary = $employee['monthly_salary'] / 30;
        $deductionAmount = ($dailySalary * $deductionPercent) / 100;
        
        // Check if deduction already exists
        $sql = "SELECT id FROM deductions 
                WHERE employee_id = ? AND date = ? AND type = 'half_day'";
        $existingDeduction = $this->db->selectOne($sql, [$employeeId, $date]);
        
        if ($existingDeduction) {
            return $existingDeduction['id'];
        }
        
        // Create deduction
        $sql = "INSERT INTO deductions 
                (employee_id, shop_id, type, amount, date, note, created_by) 
                VALUES (?, ?, 'half_day', ?, ?, 'Automatic half-day deduction', ?)";
        
        return $this->db->insert($sql, [
            $employeeId,
            $shopId,
            $deductionAmount,
            $date,
            $this->auth->getUserId()
        ]);
    }
    
    /**
     * Remove half-day deduction
     */
    private function removeHalfDayDeduction($employeeId, $date) {
        $sql = "DELETE FROM deductions 
                WHERE employee_id = ? AND date = ? AND type = 'half_day'";
        return $this->db->delete($sql, [$employeeId, $date]);
    }
    
    /**
     * Get attendance by ID
     */
    public function getById($id) {
        $sql = "SELECT a.*, e.name as employee_name, s.name as shop_name 
                FROM attendance a 
                LEFT JOIN employees e ON a.employee_id = e.id 
                LEFT JOIN shops s ON a.shop_id = s.id 
                WHERE a.id = ?";
        return $this->db->selectOne($sql, [$id]);
    }
    
    /**
     * Get attendance by employee and date
     */
    public function getByEmployeeAndDate($employeeId, $date) {
        $sql = "SELECT * FROM attendance WHERE employee_id = ? AND date = ?";
        return $this->db->selectOne($sql, [$employeeId, $date]);
    }
    
    /**
     * Get attendance list with filters
     */
    public function getAll($filters = []) {
        $sql = "SELECT a.*, e.name as employee_name, e.role, s.name as shop_name 
                FROM attendance a 
                LEFT JOIN employees e ON a.employee_id = e.id 
                LEFT JOIN shops s ON a.shop_id = s.id 
                WHERE 1=1";
        $params = [];
        
        if (isset($filters['shop_id']) && $filters['shop_id'] !== null) {
            $sql .= " AND a.shop_id = ?";
            $params[] = $filters['shop_id'];
        }
        
        if (isset($filters['employee_id'])) {
            $sql .= " AND a.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        if (isset($filters['date'])) {
            $sql .= " AND a.date = ?";
            $params[] = $filters['date'];
        }
        
        if (isset($filters['start_date'])) {
            $sql .= " AND a.date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (isset($filters['end_date'])) {
            $sql .= " AND a.date <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (isset($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY a.date DESC, e.name ASC";
        
        if (isset($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
            
            if (isset($filters['offset'])) {
                $sql .= " OFFSET ?";
                $params[] = (int)$filters['offset'];
            }
        }
        
        return $this->db->select($sql, $params);
    }
    
    /**
     * Get attendance summary for employee
     */
    public function getSummary($employeeId, $month, $year) {
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
     * Get daily attendance report
     */
    public function getDailyReport($shopId, $date) {
        $sql = "SELECT 
                    e.id, e.name, e.role,
                    a.status, a.check_in_time, a.check_out_time, a.notes,
                    CASE 
                        WHEN a.id IS NULL THEN 'not_marked'
                        ELSE a.status
                    END as attendance_status
                FROM employees e 
                LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = ?
                WHERE e.shop_id = ? AND e.status = 'active'
                ORDER BY e.name";
        
        return $this->db->select($sql, [$date, $shopId]);
    }
}
