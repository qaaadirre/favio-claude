// assets/js/services.js
// Services Management JavaScript

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

async function handleServiceSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    // Determine if it's create or update based on presence of service_id
    const serviceId = form.dataset.serviceId;
    const url = serviceId ? `api/services.php?id=${serviceId}` : 'api/services.php';
    const method = serviceId ? 'PUT' : 'POST';
    
    try {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(serviceId ? 'Service updated successfully!' : 'Service added successfully!');
            closeModal('addServiceModal');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to save service. Please try again.');
    }
    
    return false;
}

async function editService(id) {
    try {
        const response = await fetch(`api/services.php?id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            const service = result.data;
            
            // Populate form
            const form = document.getElementById('serviceForm');
            form.dataset.serviceId = id;
            form.querySelector('[name="name"]').value = service.name;
            form.querySelector('[name="price"]').value = service.price;
            form.querySelector('[name="duration"]').value = service.duration || '';
            
            if (form.querySelector('[name="shop_id"]')) {
                form.querySelector('[name="shop_id"]').value = service.shop_id;
            }
            
            // Change modal title
            document.querySelector('#addServiceModal .modal-title').textContent = 'Edit Service';
            document.querySelector('#addServiceModal button[type="submit"]').innerHTML = '<i class="fas fa-save"></i> Update Service';
            
            openModal('addServiceModal');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to load service details.');
    }
}

async function deleteService(id) {
    if (!confirm('Are you sure you want to delete this service? This action cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch(`api/services.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Service deleted successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to delete service. Please try again.');
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

// Reset form when modal closes
document.querySelectorAll('.close-modal').forEach(btn => {
    btn.addEventListener('click', function() {
        const modal = this.closest('.modal');
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
            delete form.dataset.serviceId;
        }
        // Reset modal title
        if (modal.id === 'addServiceModal') {
            modal.querySelector('.modal-title').textContent = 'Add New Service';
            modal.querySelector('button[type="submit"]').innerHTML = '<i class="fas fa-save"></i> Save Service';
        }
    });
});