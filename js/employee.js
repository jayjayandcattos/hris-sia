 
        function searchEmployees() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const department = document.getElementById('departmentFilter').value.toLowerCase();

        
            const rows = document.querySelectorAll('#employeeTableBody tr');
            rows.forEach(row => {
                const position = row.cells[2].textContent.toLowerCase();
                const dept = row.cells[3].textContent.toLowerCase();

                const matchesSearch = searchTerm === '' || position.includes(searchTerm);
                const matchesDept = department === '' || dept.includes(department);

                row.style.display = (matchesSearch && matchesDept) ? '' : 'none';
            });

    
            const cards = document.querySelectorAll('.employee-card');
            cards.forEach(card => {
                const position = card.dataset.position;
                const dept = card.dataset.department;

                const matchesSearch = searchTerm === '' || position.includes(searchTerm);
                const matchesDept = department === '' || dept.includes(department);

                card.style.display = (matchesSearch && matchesDept) ? '' : 'none';
            });
        }
// Placeholder functions for Add, Edit, and Archive actions
        function addEmployee() {
            alert('backend pa');
        }

        function editEmployee(id) {
            alert('Edit Employee: ' + id + ' - backend pa');
        }

        function archiveEmployee(id) {
            if (confirm('Are you sure you want to archive this employee?')) {
                alert('Archive Employee: ' + id + ' - backend pa');
            }
        }


        document.getElementById('searchInput').addEventListener('keyup', searchEmployees);
        document.getElementById('departmentFilter').addEventListener('keyup', searchEmployees);