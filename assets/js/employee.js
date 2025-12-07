// assets/js/employees.js
// Employee Management JavaScript

// Open modal
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

// Close modal
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Handle employee form submission
async function handleEmployeeSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
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
            alert('Employee added successfully!');
            closeModal('addEmployeeModal');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to add employee. Please try again.');
    }
    
    return false;
}

// View employee details
async function viewEmployee(id) {
    try {
        const response = await fetch(`api/employees.php?id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            const emp = result.data;
            
            // Get deductions
            const deductionsResponse = await fetch(`api/employees.php?deductions=1&employee_id=${id}`);
            const deductionsResult = await deductionsResponse.json();
            const deductions = deductionsResult.data || [];
            
            let deductionsHtml = '<p style="color: #64748b;">No pending deductions</p>';
            if (deductions.length > 0) {
                deductionsHtml = '<table style="width: 100%; font-size: 13px;">';
                deductions.forEach(d => {
                    deductionsHtml += `
                        <tr>
                            <td style="padding: 5px;">${d.type}</td>
                            <td style="padding: 5px;">₹${parseFloat(d.amount).toFixed(2)}</td>
                            <td style="padding: 5px;">${new Date(d.date).toLocaleDateString()}</td>
                        </tr>
                    `;
                });
                deductionsHtml += '</table>';
            }
            
            const detailsHtml = `
                <div style="padding: 20px;">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 40px; color: white; margin-bottom: 15px;">
                            <i class="fas fa-user"></i>
                        </div>
                        <h3 style="font-size: 24px; margin-bottom: 5px;">${emp.name}</h3>
                        <p style="color: #64748b; font-size: 16px;">${emp.role}</p>
                        <span class="badge badge-${emp.status === 'active' ? 'success' : 'danger'}">${emp.status}</span>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                        <div style="text-align: center; padding: 20px; background: #f8fafc; border-radius: 10px;">
                            <div style="font-size: 14px; color: #64748b; margin-bottom: 5px;">Monthly Salary</div>
                            <div style="font-size: 24px; font-weight: 700; color: #10b981;">₹${parseFloat(emp.monthly_salary).toLocaleString()}</div>
                        </div>
                        <div style="text-align: center; padding: 20px; background: #f8fafc; border-radius: 10px;">
                            <div style="font-size: 14px; color: #64748b; margin-bottom: 5px;">Branch</div>
                            <div style="font-size: 18px; font-weight: 600;">${emp.shop_name}</div>
                        </div>
                    </div>
                    
                    <div style="background: #f8fafc; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                        <h4 style="margin-bottom: 15px; font-size: 16px;">Personal Information</h4>
                        <table style="width: 100%;">
                            <tr>
                                <td style="padding: 8px; color: #64748b;">Phone:</td>
                                <td style="padding: 8px; font-weight: 600;">${emp.phone}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; color: #64748b;">Age:</td>
                                <td style="padding: 8px; font-weight: 600;">${emp.age} years</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; color: #64748b;">Joining Date:</td>
                                <td style="padding: 8px; font-weight: 600;">${new Date(emp.join_date).toLocaleDateString('en-IN', {day: '2-digit', month: 'long', year: 'numeric'})}</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div style="background: #fef2f2; padding: 20px; border-radius: 10px;">
                        <h4 style="margin-bottom: 15px; font-size: 16px; color: #ef4444;">Pending Deductions</h4>
                        ${deductionsHtml}
                    </div>
                </div>
            `;
            
            document.getElementById('employeeDetails').innerHTML = detailsHtml;
            openModal('viewEmployeeModal');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to load employee details.');
    }
}

// Edit employee
function editEmployee(id) {
    fetch(`api/employees.php?id=${id}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const emp = result.data;
                
                // Populate form
                const form = document.getElementById('employeeForm');
                form.querySelector('[name="name"]').value = emp.name;
                form.querySelector('[name="phone"]').value = emp.phone;
                form.querySelector('[name="age"]').value = emp.age;
                form.querySelector('[name="role"]').value = emp.role;
                form.querySelector('[name="monthly_salary"]').value = emp.monthly_salary;
                form.querySelector('[name="join_date"]').value = emp.join_date;
                if (form.querySelector('[name="shop_id"]')) {
                    form.querySelector('[name="shop_id"]').value = emp.shop_id;
                }
                
                // Change form action to update
                form.onsubmit = function(e) {
                    e.preventDefault();
                    updateEmployee(id, new FormData(form));
                    return false;
                };
                
                // Change modal title
                document.querySelector('#addEmployeeModal .modal-title').textContent = 'Edit Employee';
                document.querySelector('#addEmployeeModal button[type="submit"]').innerHTML = '<i class="fas fa-save"></i> Update Employee';
                
                openModal('addEmployeeModal');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load employee details.');
        });
}

// Update employee
async function updateEmployee(id, formData) {
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch(`api/employees.php?id=${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Employee updated successfully!');
            closeModal('addEmployeeModal');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to update employee. Please try again.');
    }
}

// Add deduction/advance
function addDeduction(employeeId) {
    document.getElementById('deduction_employee_id').value = employeeId;
    openModal('addDeductionModal');
}

// Handle deduction form submission
async function handleDeductionSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    data.action = 'add_deduction';
    
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
            alert('Deduction added successfully!');
            closeModal('addDeductionModal');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to add deduction. Please try again.');
    }
    
    return false;
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Reset form when modal closes
document.querySelectorAll('.close-modal').forEach(btn => {
    btn.addEventListener('click', function() {
        const modal = this.closest('.modal');
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
            if (modal.id === 'employeeForm') {
                form.onsubmit = handleEmployeeSubmit;
            }
        }
        // Reset modal title
        if (modal.id === 'addEmployeeModal') {
            modal.querySelector('.modal-title').textContent = 'Add New Employee';
            modal.querySelector('button[type="submit"]').innerHTML = '<i class="fas fa-user-plus"></i> Add Employee';
        }
    });
});