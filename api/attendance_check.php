<?php
/**
 * Attendance Check API
 * Provides attendance data for a given employee and pay period
 * Used by the employee dashboard to verify attendance when salary is negative
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once '../config/database.php';

try {
    $action = $_GET['action'] ?? '';

    if ($action !== 'get_period_attendance') {
        throw new Exception('Invalid action');
    }

    $employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
    $period_start = $_GET['period_start'] ?? '';
    $period_end = $_GET['period_end'] ?? '';

    if ($employee_id <= 0) {
        throw new Exception('Invalid employee ID');
    }

    if (empty($period_start) || empty($period_end)) {
        throw new Exception('Period start and end dates are required');
    }

    // Validate date formats
    $startDate = DateTime::createFromFormat('Y-m-d', $period_start);
    $endDate = DateTime::createFromFormat('Y-m-d', $period_end);

    if (!$startDate || !$endDate) {
        throw new Exception('Invalid date format. Use YYYY-MM-DD');
    }

    // Fetch attendance records for the given employee and period
    $sql = "SELECT date, time_in, time_out, status, total_hours
            FROM attendance
            WHERE employee_id = ? 
              AND DATE(date) >= ? 
              AND DATE(date) <= ?
            ORDER BY date ASC";

    $records = fetchAll($conn, $sql, [$employee_id, $period_start, $period_end]);

    echo json_encode([
        'success' => true,
        'data' => $records ?: [],
        'employee_id' => $employee_id,
        'period_start' => $period_start,
        'period_end' => $period_end,
        'count' => count($records ?: [])
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'data' => []
    ]);
}
?>
