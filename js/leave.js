
function openModal() {
    document.getElementById('leaveModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden'; 
}


function closeModal() {
    document.getElementById('leaveModal').classList.add('hidden');
    document.body.style.overflow = 'auto'; 
    document.getElementById('leaveForm').reset();
}


document.getElementById('leaveModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});


document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});


function approveLeave(id) {
    if (confirm('Are you sure you want to approve this leave request?')) {
        
        
        alert('Leave request #' + id + ' approved!');
        location.reload();
    }
}


function rejectLeave(id) {
    if (confirm('Are you sure you want to reject this leave request?')) {
        
        
        alert('Leave request #' + id + ' rejected!');
        location.reload();
    }
}


function viewLeave(id) {
    
    alert('View details for leave request #' + id);
}


function calculateDays() {
    const startDate = document.querySelector('input[name="start_date"]').value;
    const endDate = document.querySelector('input[name="end_date"]').value;
    
    if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        const diffTime = Math.abs(end - start);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        
        
        console.log('Number of days:', diffDays);
    }
}


document.querySelector('input[name="start_date"]')?.addEventListener('change', calculateDays);
document.querySelector('input[name="end_date"]')?.addEventListener('change', calculateDays);