<?php
// includes/Expense.php
// Expense Management Class

class Expense {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }
    
    /**
     * Get all expenses with filtering
     */
    public function getAll($filters = []) {
        $sql = "SELECT e.*, s.name as shop_name, u.name as created_by_name 
                FROM expenses e 
                LEFT JOIN shops s ON e.shop_id = s.id 
                LEFT JOIN users u ON e.created_by = u.id 
                WHERE e.is_deleted = 0";
        $params = [];
        
        // Filter by shop
        if (isset($filters['shop_id']) && $filters['shop_id'] !== null) {
            $sql .= " AND e.shop_id = ?";
            $params[] = $filters['shop_id'];
        }
        
        // Filter by category
        if (isset($filters['category']) && !empty($filters['category'])) {
            $sql .= " AND e.category = ?";
            $params[] = $filters['category'];
        }
        
        // Filter by date range
        if (isset($filters['start_date']) && !empty($filters['start_date'])) {
            $sql .= " AND e.date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (isset($filters['end_date']) && !empty($filters['end_date'])) {
            $sql .= " AND e.date <= ?";
            $params[] = $filters['end_date'];
        }
        
        // Search
        if (isset($filters['search']) && !empty($filters['search'])) {
            $sql .= " AND (e.title LIKE ? OR e.description LIKE ?)";
            $searchParam = "%{$filters['search']}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        // Ordering
        $sql .= " ORDER BY e.date DESC, e.created_at DESC";
        
        // Pagination
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
     * Get total count for pagination
     */
    public function getCount($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM expenses e WHERE is_deleted = 0";
        $params = [];
        
        if (isset($filters['shop_id']) && $filters['shop_id'] !== null) {
            $sql .= " AND e.shop_id = ?";
            $params[] = $filters['shop_id'];
        }
        
        if (isset($filters['category']) && !empty($filters['category'])) {
            $sql .= " AND e.category = ?";
            $params[] = $filters['category'];
        }
        
        if (isset($filters['start_date']) && !empty($filters['start_date'])) {
            $sql .= " AND e.date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (isset($filters['end_date']) && !empty($filters['end_date'])) {
            $sql .= " AND e.date <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (isset($filters['search']) && !empty($filters['search'])) {
            $sql .= " AND (e.title LIKE ? OR e.description LIKE ?)";
            $searchParam = "%{$filters['search']}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        $result = $this->db->selectOne($sql, $params);
        return $result['total'];
    }
    
    /**
     * Get single expense by ID
     */
    public function getById($id) {
        $sql = "SELECT e.*, s.name as shop_name, u.name as created_by_name 
                FROM expenses e 
                LEFT JOIN shops s ON e.shop_id = s.id 
                LEFT JOIN users u ON e.created_by = u.id 
                WHERE e.id = ?";
        return $this->db->selectOne($sql, [$id]);
    }
    
    /**
     * Create new expense
     */
    public function create($data) {
        $sql = "INSERT INTO expenses 
                (shop_id, title, amount, date, time, category, description, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $expenseId = $this->db->insert($sql, [
            $data['shop_id'],
            $data['title'],
            $data['amount'],
            $data['date'],
            $data['time'] ?? date('H:i:s'),
            $data['category'],
            $data['description'] ?? '',
            $this->auth->getUserId()
        ]);
        
        if ($expenseId) {
            $this->auth->logAudit(
                $this->auth->getUserId(),
                'create',
                'expense',
                $expenseId,
                "Created expense: {$data['title']} - ₹{$data['amount']}"
            );
        }
        
        return $expenseId;
    }
    
    /**
     * Update expense
     */
    public function update($id, $data) {
        $sql = "UPDATE expenses 
                SET title = ?, amount = ?, date = ?, time = ?, 
                    category = ?, description = ?, updated_at = NOW() 
                WHERE id = ?";
        
        $result = $this->db->update($sql, [
            $data['title'],
            $data['amount'],
            $data['date'],
            $data['time'] ?? date('H:i:s'),
            $data['category'],
            $data['description'] ?? '',
            $id
        ]);
        
        if ($result) {
            $this->auth->logAudit(
                $this->auth->getUserId(),
                'update',
                'expense',
                $id,
                "Updated expense: {$data['title']} - ₹{$data['amount']}"
            );
        }
        
        return $result;
    }
    
    /**
     * Delete expense (soft delete)
     */
    public function delete($id) {
        $expense = $this->getById($id);
        
        $sql = "UPDATE expenses 
                SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? 
                WHERE id = ?";
        
        $result = $this->db->update($sql, [$this->auth->getUserId(), $id]);
        
        if ($result && $expense) {
            $this->auth->logAudit(
                $this->auth->getUserId(),
                'delete',
                'expense',
                $id,
                "Deleted expense: {$expense['title']} - ₹{$expense['amount']}"
            );
        }
        
        return $result;
    }
    
    /**
     * Get deleted expenses (visible to owner only)
     */
    public function getDeleted($shopId = null) {
        $sql = "SELECT e.*, s.name as shop_name, 
                       u1.name as created_by_name, 
                       u2.name as deleted_by_name 
                FROM expenses e 
                LEFT JOIN shops s ON e.shop_id = s.id 
                LEFT JOIN users u1 ON e.created_by = u1.id 
                LEFT JOIN users u2 ON e.deleted_by = u2.id 
                WHERE e.is_deleted = 1";
        
        $params = [];
        if ($shopId !== null) {
            $sql .= " AND e.shop_id = ?";
            $params[] = $shopId;
        }
        
        $sql .= " ORDER BY e.deleted_at DESC";
        
        return $this->db->select($sql, $params);
    }
    
    /**
     * Get expense statistics
     */
    public function getStatistics($shopId = null, $startDate = null, $endDate = null) {
        $sql = "SELECT 
                    COUNT(*) as total_transactions,
                    COALESCE(SUM(amount), 0) as total_amount,
                    COALESCE(AVG(amount), 0) as average_amount,
                    category,
                    COALESCE(SUM(amount), 0) as category_total
                FROM expenses 
                WHERE is_deleted = 0";
        
        $params = [];
        
        if ($shopId !== null) {
            $sql .= " AND shop_id = ?";
            $params[] = $shopId;
        }
        
        if ($startDate) {
            $sql .= " AND date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " GROUP BY category";
        
        return $this->db->select($sql, $params);
    }
    
    /**
     * Get expense trends (daily/monthly)
     */
    public function getTrends($shopId = null, $period = 'daily', $limit = 30) {
        if ($period === 'daily') {
            $sql = "SELECT 
                        DATE(date) as period,
                        COALESCE(SUM(amount), 0) as total
                    FROM expenses 
                    WHERE is_deleted = 0";
            
            $params = [];
            
            if ($shopId !== null) {
                $sql .= " AND shop_id = ?";
                $params[] = $shopId;
            }
            
            $sql .= " AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                     GROUP BY DATE(date)
                     ORDER BY date DESC";
            
            $params[] = $limit;
            
        } else { // monthly
            $sql = "SELECT 
                        DATE_FORMAT(date, '%Y-%m') as period,
                        COALESCE(SUM(amount), 0) as total
                    FROM expenses 
                    WHERE is_deleted = 0";
            
            $params = [];
            
            if ($shopId !== null) {
                $sql .= " AND shop_id = ?";
                $params[] = $shopId;
            }
            
            $sql .= " AND date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                     GROUP BY DATE_FORMAT(date, '%Y-%m')
                     ORDER BY period DESC";
            
            $params[] = $limit;
        }
        
        return $this->db->select($sql, $params);
    }
    
    /**
     * Get category-wise breakdown
     */
    public function getCategoryBreakdown($shopId = null, $month = null, $year = null) {
        $sql = "SELECT 
                    category,
                    COUNT(*) as transaction_count,
                    COALESCE(SUM(amount), 0) as total_amount
                FROM expenses 
                WHERE is_deleted = 0";
        
        $params = [];
        
        if ($shopId !== null) {
            $sql .= " AND shop_id = ?";
            $params[] = $shopId;
        }
        
        if ($month && $year) {
            $sql .= " AND MONTH(date) = ? AND YEAR(date) = ?";
            $params[] = $month;
            $params[] = $year;
        }
        
        $sql .= " GROUP BY category ORDER BY total_amount DESC";
        
        return $this->db->select($sql, $params);
    }
    
    /**
     * Export expenses to CSV
     */
    public function exportToCSV($filters = []) {
        $expenses = $this->getAll($filters);
        
        $filename = 'expenses_export_' . date('Y-m-d_His') . '.csv';
        $filepath = UPLOAD_PATH . 'exports/' . $filename;
        
        // Create exports directory if it doesn't exist
        if (!file_exists(UPLOAD_PATH . 'exports/')) {
            mkdir(UPLOAD_PATH . 'exports/', 0755, true);
        }
        
        $file = fopen($filepath, 'w');
        
        // Add BOM for Excel UTF-8 support
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($file, [
            'ID', 'Date', 'Time', 'Title', 'Category', 
            'Amount', 'Shop', 'Description', 'Created By', 'Created At'
        ]);
        
        // Data rows
        foreach ($expenses as $expense) {
            fputcsv($file, [
                $expense['id'],
                $expense['date'],
                $expense['time'],
                $expense['title'],
                $expense['category'],
                $expense['amount'],
                $expense['shop_name'],
                $expense['description'],
                $expense['created_by_name'],
                $expense['created_at']
            ]);
        }
        
        fclose($file);
        
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => BASE_URL . '/uploads/exports/' . $filename
        ];
    }
}