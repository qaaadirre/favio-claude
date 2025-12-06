// assets/js/expenses.js
// Expense Management JavaScript

// Open modal
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

// Close modal
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Handle expense form submission
async function handleExpenseSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('api/expenses.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Expense added successfully!');
            closeModal('addExpenseModal');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to add expense. Please try again.');
    }
    
    return false;
}

// View expense details
async function viewExpense(id) {
    try {
        const response = await fetch(`api/expenses.php?id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            const expense = result.data;
            const detailsHtml = `
                <div style="padding: 20px;">
                    <table style="width: 100%;">
                        <tr>
                            <td style="padding: 10px; font-weight: bold;">ID:</td>
                            <td style="padding: 10px;">#${expense.id}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; font-weight: bold;">Title:</td>
                            <td style="padding: 10px;">${expense.title}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; font-weight: bold;">Amount:</td>
                            <td style="padding: 10px; color: #ef4444; font-size: 18px; font-weight: bold;">₹${parseFloat(expense.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; font-weight: bold;">Date:</td>
                            <td style="padding: 10px;">${new Date(expense.date).toLocaleDateString('en-IN', {day: '2-digit', month: 'short', year: 'numeric'})}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; font-weight: bold;">Category:</td>
                            <td style="padding: 10px;"><span class="badge badge-info">${expense.category.replace('_', ' ')}</span></td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; font-weight: bold;">Branch:</td>
                            <td style="padding: 10px;">${expense.shop_name}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; font-weight: bold;">Description:</td>
                            <td style="padding: 10px;">${expense.description || 'N/A'}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; font-weight: bold;">Created By:</td>
                            <td style="padding: 10px;">${expense.created_by_name}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; font-weight: bold;">Created At:</td>
                            <td style="padding: 10px;">${new Date(expense.created_at).toLocaleString('en-IN')}</td>
                        </tr>
                    </table>
                </div>
            `;
            
            document.getElementById('expenseDetails').innerHTML = detailsHtml;
            openModal('viewExpenseModal');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to load expense details.');
    }
}

// Edit expense
function editExpense(id) {
    // Load expense data and populate form
    fetch(`api/expenses.php?id=${id}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const expense = result.data;
                
                // Populate form fields
                const form = document.getElementById('expenseForm');
                form.querySelector('[name="title"]').value = expense.title;
                form.querySelector('[name="amount"]').value = expense.amount;
                form.querySelector('[name="date"]').value = expense.date;
                form.querySelector('[name="category"]').value = expense.category;
                if (form.querySelector('[name="shop_id"]')) {
                    form.querySelector('[name="shop_id"]').value = expense.shop_id;
                }
                form.querySelector('[name="description"]').value = expense.description || '';
                
                // Change form action to update
                form.onsubmit = function(e) {
                    e.preventDefault();
                    updateExpense(id, new FormData(form));
                    return false;
                };
                
                // Change modal title
                document.querySelector('#addExpenseModal .modal-title').textContent = 'Edit Expense';
                document.querySelector('#addExpenseModal button[type="submit"]').innerHTML = '<i class="fas fa-save"></i> Update Expense';
                
                openModal('addExpenseModal');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load expense details.');
        });
}

// Update expense
async function updateExpense(id, formData) {
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch(`api/expenses.php?id=${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Expense updated successfully!');
            closeModal('addExpenseModal');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to update expense. Please try again.');
    }
}

// Delete expense
async function deleteExpense(id) {
    if (!confirm('Are you sure you want to delete this expense? This action will be logged in the audit trail.')) {
        return;
    }
    
    try {
        const response = await fetch(`api/expenses.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Expense deleted successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to delete expense. Please try again.');
    }
}

// Export expenses to CSV
function exportExpenses() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    
    window.location.href = 'api/expenses.php?' + params.toString();
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
            // Reset submit handler
            form.onsubmit = handleExpenseSubmit;
        }
        // Reset modal title
        if (modal.id === 'addExpenseModal') {
            modal.querySelector('.modal-title').textContent = 'Add New Expense';
            modal.querySelector('button[type="submit"]').innerHTML = '<i class="fas fa-save"></i> Save Expense';
        }
    });
});