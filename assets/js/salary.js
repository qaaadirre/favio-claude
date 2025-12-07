// assets/js/salary.js
// Salary Management JavaScript

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function processSalary(employeeId, salaryData) {
    // Populate form
    document.getElementById('salary_employee_id').value = employeeId;
    document.getElementById('salary_period_start').value = salaryData.period_start;
    document.getElementById('salary_period_end').value = salaryData.period_end;
    document.getElementById('salary_gross').value = salaryData.gross_salary;
    document.getElementById('salary_deductions').value = salaryData.total_deductions;
    document.getElementById('salary_bonus').value = salaryData.bonus;
    document.getElementById('salary_net').value = salaryData.net_salary;
    
    // Show breakdown
    const breakdown = `
        <h4 style="margin-bottom: 15px;">Salary Breakdown - ${salaryData.employee.name}</h4>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span>Gross Salary:</span>
            <strong>₹${parseFloat(salaryData.gross_salary).toLocaleString('en-IN', {minimumFractionDigits: 2})}</strong>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; color: #ef4444;">
            <span>Half-Day Deductions:</span>
            <strong>- ₹${parseFloat(salaryData.half_day_deduction).toLocaleString('en-IN', {minimumFractionDigits: 2})}</strong>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; color: #ef4444;">
            <span>Advances/Loans:</span>
            <strong>- ₹${parseFloat(salaryData.other_deductions).toLocaleString('en-IN', {minimumFractionDigits: 2})}</strong>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; color: #10b981;">
            <span>Bonus (${salaryData.tasks_completed} tasks):</span>
            <strong>+ ₹${parseFloat(salaryData.bonus).toLocaleString('en-IN', {minimumFractionDigits: 2})}</strong>
        </div>
        <hr style="margin: 12px 0; border: none; border-top: 2px solid #e2e8f0;">
        <div style="display: flex; justify-content: space-between; font-size: 18px;">
            <span style="font-weight: 700;">Net Payable:</span>
            <strong style="color: #6366f1;">₹${parseFloat(salaryData.net_salary).toLocaleString('en-IN', {minimumFractionDigits: 2})}</strong>
        </div>
        <div style="margin-top: 15px; padding: 12px; background: white; border-radius: 8px;">
            <div style="font-size: 13px; color: #64748b; margin-bottom: 8px;">Attendance Summary:</div>
            <div style="display: flex; gap: 12px; font-size: 12px;">
                <span><strong>${salaryData.attendance.full_days}</strong> Full Days</span>
                <span><strong>${salaryData.attendance.half_days}</strong> Half Days</span>
                <span><strong>${salaryData.attendance.absent_days}</strong> Absent</span>
            </div>
        </div>
    `;
    
    document.getElementById('salaryBreakdown').innerHTML = breakdown;
    
    openModal('processSalaryModal');
}

async function handleSalarySubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    data.action = 'process_salary';
    
    if (!confirm('Are you sure you want to process this salary payment? This action cannot be undone.')) {
        return false;
    }
    
    try {
        const response = await fetch('api/employees.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Salary processed successfully!');
            closeModal('processSalaryModal');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to process salary. Please try again.');
    }
    
    return false;
}

async function viewPaymentDetails(paymentId) {
    try {
        const response = await fetch(`api/salary.php?id=${paymentId}`);
        const result = await response.json();
        
        if (result.success) {
            const payment = result.data;
            
            const details = `
                <div style="padding: 20px;">
                    <h3 style="margin-bottom: 20px;">Payment Details</h3>
                    <table style="width: 100%;">
                        <tr>
                            <td style="padding: 8px; color: #64748b;">Employee:</td>
                            <td style="padding: 8px; font-weight: 600;">${payment.employee_name}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; color: #64748b;">Period:</td>
                            <td style="padding: 8px; font-weight: 600;">${new Date(payment.period_start).toLocaleDateString()} - ${new Date(payment.period_end).toLocaleDateString()}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; color: #64748b;">Gross Salary:</td>
                            <td style="padding: 8px; font-weight: 600;">₹${parseFloat(payment.gross_salary).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; color: #64748b;">Total Deductions:</td>
                            <td style="padding: 8px; font-weight: 600; color: #ef4444;">₹${parseFloat(payment.total_deductions).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; color: #64748b;">Bonuses:</td>
                            <td style="padding: 8px; font-weight: 600; color: #10b981;">₹${parseFloat(payment.bonuses).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; color: #64748b;">Net Paid:</td>
                            <td style="padding: 8px; font-weight: 700; font-size: 18px; color: #6366f1;">₹${parseFloat(payment.net_paid).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; color: #64748b;">Payment Date:</td>
                            <td style="padding: 8px; font-weight: 600;">${new Date(payment.paid_on).toLocaleDateString()}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; color: #64748b;">Payment Method:</td>
                            <td style="padding: 8px; font-weight: 600;">${payment.payment_method.replace('_', ' ').toUpperCase()}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; color: #64748b;">Processed By:</td>
                            <td style="padding: 8px; font-weight: 600;">${payment.processed_by_name || 'N/A'}</td>
                        </tr>
                        ${payment.notes ? `
                        <tr>
                            <td style="padding: 8px; color: #64748b;">Notes:</td>
                            <td style="padding: 8px;">${payment.notes}</td>
                        </tr>
                        ` : ''}
                    </table>
                </div>
            `;
            
            // Create temporary modal
            const modal = document.createElement('div');
            modal.className = 'modal active';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title">Payment Details</h2>
                        <button class="close-modal" onclick="this.closest('.modal').remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    ${details}
                </div>
            `;
            document.body.appendChild(modal);
            
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.remove();
                }
            });
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to load payment details.');
    }
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});