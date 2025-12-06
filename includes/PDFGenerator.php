<?php
// includes/PDFGenerator.php
// PDF Report Generation using TCPDF

require_once 'tcpdf/tcpdf.php';

class PDFGenerator extends TCPDF {
    private $shopInfo;
    private $reportTitle;
    private $reportDate;
    
    /**
     * Constructor
     */
    public function __construct($shopInfo, $reportTitle = '', $reportDate = '') {
        parent::__construct(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $this->shopInfo = $shopInfo;
        $this->reportTitle = $reportTitle;
        $this->reportDate = $reportDate;
        
        // Set document information
        $this->SetCreator('Salon Management System');
        $this->SetAuthor($shopInfo['name']);
        $this->SetTitle($reportTitle);
        
        // Set margins
        $this->SetMargins(15, 40, 15);
        $this->SetHeaderMargin(10);
        $this->SetFooterMargin(15);
        $this->SetAutoPageBreak(TRUE, 25);
        
        // Set font
        $this->SetFont('helvetica', '', 10);
    }
    
    /**
     * Custom header
     */
    public function Header() {
        // Logo
        if (!empty($this->shopInfo['logo_path']) && file_exists($this->shopInfo['logo_path'])) {
            $this->Image($this->shopInfo['logo_path'], 15, 10, 25, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        // Shop info
        $this->SetFont('helvetica', 'B', 16);
        $this->SetXY(45, 12);
        $this->Cell(0, 8, $this->shopInfo['name'], 0, 1, 'L');
        
        $this->SetFont('helvetica', '', 9);
        $this->SetX(45);
        $this->Cell(0, 5, $this->shopInfo['address'], 0, 1, 'L');
        
        $this->SetX(45);
        $contactInfo = 'Phone: ' . $this->shopInfo['phone'];
        $this->Cell(0, 5, $contactInfo, 0, 1, 'L');
        
        // Report title
        $this->Ln(5);
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 8, $this->reportTitle, 0, 1, 'C');
        
        if (!empty($this->reportDate)) {
            $this->SetFont('helvetica', '', 10);
            $this->Cell(0, 5, $this->reportDate, 0, 1, 'C');
        }
        
        // Line
        $this->Line(15, $this->GetY() + 2, $this->getPageWidth() - 15, $this->GetY() + 2);
        $this->Ln(5);
    }
    
    /**
     * Custom footer
     */
    public function Footer() {
        $this->SetY(-20);
        
        // Developer credits
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(0, 5, 'Developed by Abc tech', 0, 1, 'C');
        
        $this->SetFont('helvetica', '', 8);
        $this->Cell(0, 4, 'Mob: 91****** | Email: abctech@gmail.com | Instagram: @abctech', 0, 1, 'C');
        
        // Page number and timestamp
        $this->Ln(2);
        $this->SetFont('helvetica', 'I', 8);
        $pageText = 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages();
        $dateText = 'Generated: ' . date('d-M-Y h:i A');
        
        $this->Cell(0, 4, $pageText . ' | ' . $dateText, 0, 0, 'C');
    }
    
    /**
     * Add summary section
     */
    public function addSummarySection($title, $data) {
        $this->SetFont('helvetica', 'B', 12);
        $this->SetFillColor(99, 102, 241);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 8, $title, 0, 1, 'L', true);
        
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('helvetica', '', 10);
        $this->Ln(2);
        
        foreach ($data as $label => $value) {
            $this->Cell(80, 6, $label . ':', 0, 0, 'L');
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(0, 6, $value, 0, 1, 'R');
            $this->SetFont('helvetica', '', 10);
        }
        
        $this->Ln(5);
    }
    
    /**
     * Add table
     */
    public function addTable($headers, $rows, $colWidths = null) {
        // Set column widths
        if ($colWidths === null) {
            $tableWidth = $this->getPageWidth() - 30;
            $colWidth = $tableWidth / count($headers);
            $colWidths = array_fill(0, count($headers), $colWidth);
        }
        
        // Header
        $this->SetFont('helvetica', 'B', 9);
        $this->SetFillColor(240, 240, 240);
        
        foreach ($headers as $i => $header) {
            $this->Cell($colWidths[$i], 7, $header, 1, 0, 'C', true);
        }
        $this->Ln();
        
        // Rows
        $this->SetFont('helvetica', '', 9);
        $this->SetFillColor(255, 255, 255);
        
        $fill = false;
        foreach ($rows as $row) {
            $this->SetFillColor($fill ? 250 : 255, $fill ? 250 : 255, $fill ? 250 : 255);
            
            foreach ($row as $i => $cell) {
                $this->Cell($colWidths[$i], 6, $cell, 1, 0, 'L', true);
            }
            $this->Ln();
            $fill = !$fill;
        }
        
        $this->Ln(5);
    }
    
    /**
     * Add chart image
     */
    public function addChartImage($imagePath, $title = '') {
        if (!empty($title)) {
            $this->SetFont('helvetica', 'B', 11);
            $this->Cell(0, 6, $title, 0, 1, 'L');
            $this->Ln(2);
        }
        
        if (file_exists($imagePath)) {
            $this->Image($imagePath, 15, $this->GetY(), 180, 0, '', '', '', false, 300, '', false, false, 0);
            $this->Ln(100);
        }
    }
}

/**
 * Report Generator Class
 */
class ReportGenerator {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }
    
    /**
     * Generate monthly expense report
     */
    public function generateMonthlyExpenseReport($shopId, $month, $year) {
        // Get shop info
        $shop = $this->getShopInfo($shopId);
        
        // Get expenses for the month
        $sql = "SELECT * FROM expenses 
                WHERE shop_id = ? 
                  AND MONTH(date) = ? 
                  AND YEAR(date) = ?
                  AND is_deleted = 0
                ORDER BY date DESC";
        $expenses = $this->db->select($sql, [$shopId, $month, $year]);
        
        // Calculate totals by category
        $categoryTotals = [];
        $grandTotal = 0;
        
        foreach ($expenses as $expense) {
            $category = $expense['category'];
            if (!isset($categoryTotals[$category])) {
                $categoryTotals[$category] = 0;
            }
            $categoryTotals[$category] += $expense['amount'];
            $grandTotal += $expense['amount'];
        }
        
        // Create PDF
        $reportTitle = 'Monthly Expense Report';
        $reportDate = date('F Y', strtotime("{$year}-{$month}-01"));
        
        $pdf = new PDFGenerator($shop, $reportTitle, $reportDate);
        $pdf->AddPage();
        
        // Summary section
        $summary = [
            'Total Expenses' => '₹' . number_format($grandTotal, 2),
            'Total Transactions' => count($expenses),
            'Reporting Period' => $reportDate
        ];
        $pdf->addSummarySection('Summary', $summary);
        
        // Category-wise breakdown
        $categoryData = [
            'Category Breakdown' => ''
        ];
        foreach ($categoryTotals as $category => $total) {
            $percentage = ($grandTotal > 0) ? round(($total / $grandTotal) * 100, 1) : 0;
            $categoryData[ucfirst(str_replace('_', ' ', $category))] = 
                '₹' . number_format($total, 2) . ' (' . $percentage . '%)';
        }
        $pdf->addSummarySection('Category Breakdown', $categoryData);
        
        // Detailed expense table
        if (!empty($expenses)) {
            $headers = ['Date', 'Title', 'Category', 'Amount', 'Description'];
            $rows = [];
            
            foreach ($expenses as $expense) {
                $rows[] = [
                    date('d-M-Y', strtotime($expense['date'])),
                    substr($expense['title'], 0, 30),
                    ucfirst(str_replace('_', ' ', $expense['category'])),
                    '₹' . number_format($expense['amount'], 2),
                    substr($expense['description'] ?? '-', 0, 40)
                ];
            }
            
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 6, 'Detailed Expense List', 0, 1, 'L');
            $pdf->Ln(2);
            
            $pdf->addTable($headers, $rows, [25, 50, 30, 30, 45]);
        }
        
        // Output PDF
        $filename = "expense_report_{$shopId}_{$year}_{$month}_" . time() . ".pdf";
        $filepath = UPLOAD_PATH . 'reports/' . $filename;
        
        // Create reports directory if it doesn't exist
        if (!file_exists(UPLOAD_PATH . 'reports/')) {
            mkdir(UPLOAD_PATH . 'reports/', 0755, true);
        }
        
        $pdf->Output($filepath, 'F');
        
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => BASE_URL . '/uploads/reports/' . $filename
        ];
    }
    
    /**
     * Generate employee salary report
     */
    public function generateSalaryReport($employeeId, $month, $year) {
        $employee = new Employee();
        $salaryData = $employee->calculateMonthlySalary($employeeId, $month, $year);
        
        if (!$salaryData) {
            return false;
        }
        
        // Get shop info
        $shop = $this->getShopInfo($salaryData['employee']['shop_id']);
        
        // Create PDF
        $reportTitle = 'Salary Slip';
        $reportDate = date('F Y', strtotime("{$year}-{$month}-01"));
        
        $pdf = new PDFGenerator($shop, $reportTitle, $reportDate);
        $pdf->AddPage();
        
        // Employee info
        $employeeInfo = [
            'Employee Name' => $salaryData['employee']['name'],
            'Employee ID' => $salaryData['employee']['id'],
            'Role' => $salaryData['employee']['role'],
            'Salary Month' => $reportDate
        ];
        $pdf->addSummarySection('Employee Information', $employeeInfo);
        
        // Attendance summary
        $attendance = $salaryData['attendance'];
        $attendanceInfo = [
            'Working Days' => $attendance['full_days'] + $attendance['half_days'],
            'Full Days' => $attendance['full_days'],
            'Half Days' => $attendance['half_days'],
            'Absent Days' => $attendance['absent_days']
        ];
        $pdf->addSummarySection('Attendance Summary', $attendanceInfo);
        
        // Salary breakdown
        $salaryInfo = [
            'Gross Salary' => '₹' . number_format($salaryData['gross_salary'], 2),
            'Half-Day Deductions' => '- ₹' . number_format($salaryData['half_day_deduction'], 2),
            'Other Deductions' => '- ₹' . number_format($salaryData['other_deductions'], 2),
            'Total Deductions' => '- ₹' . number_format($salaryData['total_deductions'], 2),
            'Bonus (Tasks: ' . $salaryData['tasks_completed'] . ')' => '+ ₹' . number_format($salaryData['bonus'], 2),
            'Net Payable' => '₹' . number_format($salaryData['net_salary'], 2)
        ];
        $pdf->addSummarySection('Salary Breakdown', $salaryInfo);
        
        // Output PDF
        $filename = "salary_slip_{$employeeId}_{$year}_{$month}_" . time() . ".pdf";
        $filepath = UPLOAD_PATH . 'reports/' . $filename;
        
        $pdf->Output($filepath, 'F');
        
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => BASE_URL . '/uploads/reports/' . $filename
        ];
    }
    
    /**
     * Get shop information
     */
    private function getShopInfo($shopId) {
        $sql = "SELECT * FROM shops WHERE id = ?";
        $shop = $this->db->selectOne($sql, [$shopId]);
        
        if (!$shop) {
            throw new Exception("Shop not found");
        }
        
        return $shop;
    }
}