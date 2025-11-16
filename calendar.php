<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_event':
                try {
                    // Create recruitment event
                    $sql = "INSERT INTO recruitment (job_title, department_id, date_posted, status, posted_by) 
                            VALUES (?, ?, ?, 'Open', ?)";

                    $stmt = $conn->prepare($sql);
                    $success = $stmt->execute([
                        $_POST['event_name'],
                        $_POST['department_id'] ?: null,
                        $_POST['event_start'],
                        $_SESSION['employee_id']
                    ]);

                    if ($success) {
                        if (isset($logger)) {
                            $logger->info('CALENDAR', 'Event added', "Event: {$_POST['event_name']}");
                        }
                        echo json_encode(['success' => true, 'message' => 'Event added successfully']);
                        exit;
                    }
                } catch (Exception $e) {
                    if (isset($logger)) {
                        $logger->error('CALENDAR', 'Failed to add event', $e->getMessage());
                    }
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
                break;

            case 'update_event':
                try {
                    $sql = "UPDATE recruitment 
                            SET job_title = ?, department_id = ?, date_posted = ? 
                            WHERE recruitment_id = ?";

                    $stmt = $conn->prepare($sql);
                    $success = $stmt->execute([
                        $_POST['event_name'],
                        $_POST['department_id'] ?: null,
                        $_POST['event_start'],
                        $_POST['event_id']
                    ]);

                    if ($success) {
                        if (isset($logger)) {
                            $logger->info('CALENDAR', 'Event updated', "ID: {$_POST['event_id']}");
                        }
                        echo json_encode(['success' => true, 'message' => 'Event updated successfully']);
                        exit;
                    }
                } catch (Exception $e) {
                    if (isset($logger)) {
                        $logger->error('CALENDAR', 'Failed to update event', $e->getMessage());
                    }
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
                break;

            case 'delete_event':
                try {
                    $sql = "DELETE FROM recruitment WHERE recruitment_id = ?";
                    $stmt = $conn->prepare($sql);
                    $success = $stmt->execute([$_POST['event_id']]);

                    if ($success) {
                        if (isset($logger)) {
                            $logger->info('CALENDAR', 'Event deleted', "ID: {$_POST['event_id']}");
                        }
                        echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
                        exit;
                    }
                } catch (Exception $e) {
                    if (isset($logger)) {
                        $logger->error('CALENDAR', 'Failed to delete event', $e->getMessage());
                    }
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
                break;
        }
    }
}

// Fetch events from database
$eventsSql = "SELECT r.recruitment_id as id, 
              r.job_title as name, 
              r.date_posted as start,
              r.date_posted as end,
              d.department_name
              FROM recruitment r
              LEFT JOIN department d ON r.department_id = d.department_id
              ORDER BY r.date_posted DESC";

$eventsData = fetchAll($conn, $eventsSql);
$events = [];

foreach ($eventsData as $event) {
    $events[] = [
        'id' => $event['id'],
        'name' => $event['name'],
        'start' => $event['start'],
        'end' => $event['end'],
        'color' => '#0d9488',
        'department' => $event['department_name']
    ];
}

// Fetch departments for dropdown
$departments = fetchAll($conn, "SELECT * FROM department ORDER BY department_name");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRIS - Calendar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/styles.css">

    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeInModal 0.3s ease;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 0;
            border-radius: 0.5rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s ease;
        }

        @keyframes fadeInModal {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="min-h-screen lg:ml-64">
        <header class="gradient-bg text-white p-4 lg:p-6 shadow-lg">
            <div class="flex items-center justify-between pl-14 lg:pl-0">
                <?php include 'includes/sidebar.php'; ?>
                <h1 class="text-lg sm:text-xl lg:text-2xl font-bold">Calendar</h1>
                <button onclick="openLogoutModal()"
                    class="bg-white px-3 py-2 rounded-lg font-medium text-red-600 hover:text-red-700 hover:bg-gray-100 text-xs sm:text-sm">
                    Logout
                </button>
            </div>
        </header>

        <main class="p-3 sm:p-4 lg:p-8">
            <?php if ($message): ?>
                <div class="mb-4 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Calendar Controls -->
            <div class="bg-white rounded-lg shadow-lg p-4 lg:p-6 mb-4 lg:mb-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                    <div class="flex items-center gap-3">
                        <button onclick="changeView('yearly')" id="viewYearlyBtn" class="bg-teal-700 text-white px-4 py-2 rounded-lg font-medium text-sm">
                            Yearly
                        </button>
                        <button onclick="changeView('monthly')" id="viewMonthlyBtn" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium text-sm">
                            Monthly
                        </button>
                    </div>

                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <button onclick="previousPeriod()" class="bg-teal-700 hover:bg-teal-800 text-white px-3 py-2 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                        </button>
                        <h2 id="calendarTitle" class="text-xl sm:text-2xl font-bold text-gray-800 flex-1 text-center">2025</h2>
                        <button onclick="nextPeriod()" class="bg-teal-700 hover:bg-teal-800 text-white px-3 py-2 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strok e-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                        <button onclick="openAddEventModal()" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-lg font-medium text-sm whitespace-nowrap">
                            + Add Event
                        </button>
                    </div>
                </div>

                <!-- Yearly View -->
                <div id="yearlyView" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"></div>

                <!-- Monthly View -->
                <div id="monthlyView" class="hidden">
                    <div class="grid grid-cols-7 gap-2 mb-4">
                        <div class="text-center font-semibold text-sm text-gray-700 py-2">Sun</div>
                        <div class="text-center font-semibold text-sm text-gray-700 py-2">Mon</div>
                        <div class="text-center font-semibold text-sm text-gray-700 py-2">Tue</div>
                        <div class="text-center font-semibold text-sm text-gray-700 py-2">Wed</div>
                        <div class="text-center font-semibold text-sm text-gray-700 py-2">Thu</div>
                        <div class="text-center font-semibold text-sm text-gray-700 py-2">Fri</div>
                        <div class="text-center font-semibold text-sm text-gray-700 py-2">Sat</div>
                    </div>
                    <div id="monthlyCalendar" class="grid grid-cols-7 gap-2"></div>

                    <!-- This Month's Events -->
                    <div class="mt-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">This Month's Events</h3>
                        <div id="monthEvents" class="space-y-2"></div>
                        <div id="noEvents" class="text-gray-500 text-sm italic hidden">No events this month</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Event Modal -->
    <div id="addEventModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Add Event</h3>
                <form id="addEventForm" onsubmit="submitEvent(event)">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Event Name</label>
                        <input type="text" id="eventName" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                        <select id="eventDepartment" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                        <input type="date" id="eventStart" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 bg-teal-700 hover:bg-teal-800 text-white px-4 py-3 rounded-lg font-medium">
                            Add Event
                        </button>
                        <button type="button" onclick="closeAddEventModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-3 rounded-lg font-medium">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Event Modal -->
    <div id="editEventModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Edit Event</h3>
                <form id="editEventForm" onsubmit="updateEvent(event)">
                    <input type="hidden" id="editEventId">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Event Name</label>
                        <input type="text" id="editEventName" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                        <select id="editEventDepartment" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                        <input type="date" id="editEventStart" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 bg-teal-700 hover:bg-teal-800 text-white px-4 py-3 rounded-lg font-medium">
                            Update
                        </button>
                        <button type="button" onclick="deleteEvent()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-3 rounded-lg font-medium">
                            Delete
                        </button>
                        <button type="button" onclick="closeEditEventModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-3 rounded-lg font-medium">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6 text-center">
            <div class="mb-4">
                <svg class="w-16 h-16 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-2">Success!</h3>
            <p id="successMessage" class="text-gray-600 mb-4">Event has been added successfully.</p>
            <button onclick="closeSuccessModal()" class="bg-teal-700 hover:bg-teal-800 text-white px-6 py-2 rounded-lg font-medium">
                OK
            </button>
        </div>
    </div>

    <script>
        let events = <?php echo json_encode($events); ?>;
        let currentView = 'yearly';
        let currentYear = new Date().getFullYear();
        let currentMonth = new Date().getMonth();

        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        document.addEventListener('DOMContentLoaded', function() {
            renderYearlyView();
        });

        function changeView(view) {
            currentView = view;
            document.getElementById('viewYearlyBtn').className = view === 'yearly' ?
                'bg-teal-700 text-white px-4 py-2 rounded-lg font-medium text-sm' :
                'bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium text-sm';
            document.getElementById('viewMonthlyBtn').className = view === 'monthly' ?
                'bg-teal-700 text-white px-4 py-2 rounded-lg font-medium text-sm' :
                'bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium text-sm';

            if (view === 'yearly') {
                document.getElementById('yearlyView').classList.remove('hidden');
                document.getElementById('monthlyView').classList.add('hidden');
                renderYearlyView();
            } else {
                document.getElementById('yearlyView').classList.add('hidden');
                document.getElementById('monthlyView').classList.remove('hidden');
                renderMonthlyView();
            }
        }

        function renderYearlyView() {
            document.getElementById('calendarTitle').textContent = currentYear;
            const container = document.getElementById('yearlyView');
            container.innerHTML = '';

            for (let month = 0; month < 12; month++) {
                const monthDiv = document.createElement('div');
                monthDiv.className = 'border border-gray-200 rounded-lg p-4 bg-white hover:shadow-md transition-shadow cursor-pointer';
                monthDiv.onclick = () => {
                    currentMonth = month;
                    changeView('monthly');
                };

                const monthHtml = `
                    <h3 class="font-semibold text-gray-800 mb-3">${monthNames[month]}</h3>
                    <div class="grid grid-cols-7 gap-1">
                        <div class="text-xs text-gray-500 text-center">S</div>
                        <div class="text-xs text-gray-500 text-center">M</div>
                        <div class="text-xs text-gray-500 text-center">T</div>
                        <div class="text-xs text-gray-500 text-center">W</div>
                        <div class="text-xs text-gray-500 text-center">T</div>
                        <div class="text-xs text-gray-500 text-center">F</div>
                        <div class="text-xs text-gray-500 text-center">S</div>
                        ${generateMiniMonth(currentYear, month)}
                    </div>
                `;
                monthDiv.innerHTML = monthHtml;
                container.appendChild(monthDiv);
            }
        }

        function generateMiniMonth(year, month) {
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            let html = '';

            for (let i = 0; i < firstDay; i++) {
                html += '<div></div>';
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const hasEvent = events.some(e => dateStr >= e.start && dateStr <= e.end);
                html += `<div class="text-xs text-center py-1 ${hasEvent ? 'bg-teal-700 text-white rounded-full' : 'text-gray-700'}">${day}</div>`;
            }

            return html;
        }

        function renderMonthlyView() {
            document.getElementById('calendarTitle').textContent = `${monthNames[currentMonth]} ${currentYear}`;
            const container = document.getElementById('monthlyCalendar');
            container.innerHTML = '';

            const firstDay = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();

            for (let i = 0; i < firstDay; i++) {
                container.innerHTML += '<div></div>';
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const dayEvents = events.filter(e => dateStr >= e.start && dateStr <= e.end);

                const dayDiv = document.createElement('div');
                dayDiv.className = 'border border-gray-200 rounded-lg p-2 min-h-20 bg-white hover:bg-gray-50 transition-colors';
                dayDiv.innerHTML = `
                    <div class="font-semibold text-sm text-gray-700 mb-1">${day}</div>
                    ${dayEvents.map(e => `<div class="text-xs bg-teal-700 text-white rounded px-1 py-0.5 mb-1 truncate">${e.name}</div>`).join('')}
                `;
                container.appendChild(dayDiv);
            }

            renderMonthEvents();
        }

        function renderMonthEvents() {
            const monthStart = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-01`;
            const monthEnd = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-31`;

            const monthEvents = events.filter(e =>
                (e.start >= monthStart && e.start <= monthEnd) ||
                (e.end >= monthStart && e.end <= monthEnd) ||
                (e.start <= monthStart && e.end >= monthEnd)
            );

            const container = document.getElementById('monthEvents');
            const noEvents = document.getElementById('noEvents');

            if (monthEvents.length === 0) {
                container.innerHTML = '';
                noEvents.classList.remove('hidden');
            } else {
                noEvents.classList.add('hidden');
                container.innerHTML = monthEvents.map(e => `
                    <div class="bg-teal-800 text-white rounded-lg p-4 hover:bg-teal-700 transition-colors cursor-pointer" onclick="openEditEventModal(${e.id})">
                        <h4 class="font-semibold text-lg mb-1">${e.name}</h4>
                        <p class="text-sm opacity-90">${formatDate(e.start)}</p>
                        ${e.department ? `<p class="text-xs opacity-75 mt-1">${e.department}</p>` : ''}
                    </div>
                `).join('');
            }
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const month = monthNames[date.getMonth()].substring(0, 3);
            return `${month} ${date.getDate()}, ${date.getFullYear()}`;
        }

        function previousPeriod() {
            if (currentView === 'yearly') {
                currentYear--;
                renderYearlyView();
            } else {
                currentMonth--;
                if (currentMonth < 0) {
                    currentMonth = 11;
                    currentYear--;
                }
                renderMonthlyView();
            }
        }

        function nextPeriod() {
            if (currentView === 'yearly') {
                currentYear++;
                renderYearlyView();
            } else {
                currentMonth++;
                if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                }
                renderMonthlyView();
            }
        }

        function openAddEventModal() {
            document.getElementById('addEventModal').classList.remove('hidden');
            document.getElementById('eventName').value = '';
            document.getElementById('eventDepartment').value = '';
            document.getElementById('eventStart').value = '';
        }

        function closeAddEventModal() {
            document.getElementById('addEventModal').classList.add('hidden');
        }

        async function submitEvent(e) {
            e.preventDefault();

            const formData = new FormData();
            formData.append('action', 'add_event');
            formData.append('event_name', document.getElementById('eventName').value);
            formData.append('department_id', document.getElementById('eventDepartment').value);
            formData.append('event_start', document.getElementById('eventStart').value);

            try {
                const response = await fetch('calendar.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    closeAddEventModal();
                    showSuccess('Event has been added successfully.');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error adding event: ' + error.message);
            }
        }

        function openEditEventModal(eventId) {
            const event = events.find(e => e.id === eventId);
            if (event) {
                document.getElementById('editEventId').value = event.id;
                document.getElementById('editEventName').value = event.name;
                document.getElementById('editEventStart').value = event.start;
                document.getElementById('editEventModal').classList.remove('hidden');
            }
        }

        function closeEditEventModal() {
            document.getElementById('editEventModal').classList.add('hidden');
        }

        async function updateEvent(e) {
            e.preventDefault();

            const formData = new FormData();
            formData.append('action', 'update_event');
            formData.append('event_id', document.getElementById('editEventId').value);
            formData.append('event_name', document.getElementById('editEventName').value);
            formData.append('department_id', document.getElementById('editEventDepartment').value);
            formData.append('event_start', document.getElementById('editEventStart').value);

            try {
                const response = await fetch('calendar.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    closeEditEventModal();
                    showSuccess('Event has been updated successfully.');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error updating event: ' + error.message);
            }
        }

        async function deleteEvent() {
            if (!confirm('Are you sure you want to delete this event?')) return;

            const formData = new FormData();
            formData.append('action', 'delete_event');
            formData.append('event_id', document.getElementById('editEventId').value);

            try {
                const response = await fetch('calendar.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    closeEditEventModal();
                    showSuccess('Event has been deleted successfully.');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error deleting event: ' + error.message);
            }
        }

        function showSuccess(message) {
            document.getElementById('successMessage').textContent = message;
            document.getElementById('successModal').classList.remove('hidden');
        }

        function closeSuccessModal() {
            document.getElementById('successModal').classList.add('hidden');
        }

        function openLogoutModal() {
            document.getElementById('logoutModal').classList.add('active');
        }

        function closeLogoutModal() {
            document.getElementById('logoutModal').classList.remove('active');
        }

        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLogoutModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLogoutModal();
            }
        });
    </script>

    <div id="logoutModal" class="modal">
        <div class="modal-content max-w-md w-full mx-4">
            <div class="bg-red-600 text-white p-4 rounded-t-lg">
                <h2 class="text-xl font-bold">Confirm Logout</h2>
            </div>
            <div class="p-6">
                <p class="text-gray-700 mb-6">Are you sure you want to logout?</p>
                <div class="flex gap-3 justify-end">
                    <button onclick="closeLogoutModal()"
                        class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition">
                        Cancel
                    </button>
                    <a href="logout.php"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>