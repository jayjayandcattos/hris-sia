// Universal Modal System for HRIS
// Replaces alert() and confirm() with uniform modals

let confirmCallback = null;

function showAlertModal(message, type = 'info') {
    const modal = document.getElementById('alertModal');
    const modalTitle = document.getElementById('alertModalTitle');
    const modalMessage = document.getElementById('alertModalMessage');
    const modalHeader = document.querySelector('#alertModal .modal-header');
    
    if (!modal) {
        console.error('Alert modal not found in DOM');
        return;
    }
    
    modalMessage.textContent = message;
    
    // Set header color based on type
    const colors = {
        'info': 'bg-teal-700',
        'success': 'bg-green-600',
        'error': 'bg-red-600',
        'warning': 'bg-yellow-600'
    };
    
    const titles = {
        'info': 'Information',
        'success': 'Success',
        'error': 'Error',
        'warning': 'Warning'
    };
    
    modalHeader.className = colors[type] + ' text-white p-4 rounded-t-lg';
    modalTitle.textContent = titles[type];
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAlertModal() {
    const modal = document.getElementById('alertModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

function showConfirmModal(message, onConfirm, onCancel = null) {
    const modal = document.getElementById('confirmModal');
    const modalMessage = document.getElementById('confirmModalMessage');
    
    if (!modal) {
        console.error('Confirm modal not found in DOM');
        return;
    }
    
    modalMessage.textContent = message;
    confirmCallback = onConfirm;
    
    // Store cancel callback if provided
    if (onCancel) {
        modal.dataset.cancelCallback = 'true';
        window.confirmCancelCallback = onCancel;
    } else {
        delete modal.dataset.cancelCallback;
        delete window.confirmCancelCallback;
    }
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
        confirmCallback = null;
        if (window.confirmCancelCallback) {
            delete window.confirmCancelCallback;
        }
    }
}

function handleConfirm() {
    if (confirmCallback) {
        confirmCallback();
    }
    closeConfirmModal();
}

function handleCancel() {
    if (window.confirmCancelCallback) {
        window.confirmCancelCallback();
    }
    closeConfirmModal();
}

// Close modals on background click
document.addEventListener('DOMContentLoaded', function() {
    const alertModal = document.getElementById('alertModal');
    const confirmModal = document.getElementById('confirmModal');
    
    if (alertModal) {
        alertModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAlertModal();
            }
        });
    }
    
    if (confirmModal) {
        confirmModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeConfirmModal();
            }
        });
    }
    
    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAlertModal();
            closeConfirmModal();
        }
    });
});

// Override native alert and confirm (optional, for backward compatibility)
window.alert = function(message) {
    showAlertModal(message, 'info');
};

window.confirm = function(message) {
    return new Promise((resolve) => {
        showConfirmModal(message, () => resolve(true), () => resolve(false));
    });
};

