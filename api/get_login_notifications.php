<?php
/**
 * API Endpoint: Get Login Notifications
 * Returns overdue fines, upcoming due dates, and admin messages for logged-in user
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Not authenticated']);
	exit;
}

$userId = (int)$_SESSION['user']['id'];
$role = $_SESSION['user']['role'] ?? 'student';

try {
	$notifications = [
		'overdue_fines' => [],
		'upcoming_due' => [],
		'admin_messages' => []
	];
	
	// Get overdue books with fines
	$overdueQuery = "SELECT ib.id, b.title, ib.issue_date, ib.due_date, ib.fine_rate,
						COALESCE(ib.due_date, DATE_ADD(ib.issue_date, INTERVAL " . LOAN_DAYS . " DAY)) AS calculated_due_date
					 FROM issued_books ib
					 JOIN books b ON b.id = ib.book_id
					 WHERE ib.user_id = ? 
					 AND ib.return_date IS NULL
					 AND COALESCE(ib.due_date, DATE_ADD(ib.issue_date, INTERVAL " . LOAN_DAYS . " DAY)) < CURDATE()";
	
	$stmt = mysqli_prepare($conn, $overdueQuery);
	mysqli_stmt_bind_param($stmt, 'i', $userId);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);
	
	while ($row = mysqli_fetch_assoc($result)) {
		$dueDate = $row['due_date'] ?: compute_due_date($row['issue_date'])->format('Y-m-d');
		$fineRate = (float)($row['fine_rate'] ?? FINE_PER_DAY);
		$fine = compute_fine($row['issue_date'], null, $dueDate, $fineRate);
		$daysOverdue = (int)(new DateTime('today'))->diff(new DateTime($dueDate))->format('%a');
		
		$notifications['overdue_fines'][] = [
			'book_title' => $row['title'],
			'due_date' => $dueDate,
			'days_overdue' => $daysOverdue,
			'fine' => round($fine, 2)
		];
	}
	mysqli_stmt_close($stmt);
	
	// Get upcoming due dates (1-2 days)
	$upcomingQuery = "SELECT ib.id, b.title, ib.issue_date, ib.due_date,
						COALESCE(ib.due_date, DATE_ADD(ib.issue_date, INTERVAL " . LOAN_DAYS . " DAY)) AS calculated_due_date
					  FROM issued_books ib
					  JOIN books b ON b.id = ib.book_id
					  WHERE ib.user_id = ?
					  AND ib.return_date IS NULL
					  AND COALESCE(ib.due_date, DATE_ADD(ib.issue_date, INTERVAL " . LOAN_DAYS . " DAY)) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)";
	
	$stmt = mysqli_prepare($conn, $upcomingQuery);
	mysqli_stmt_bind_param($stmt, 'i', $userId);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);
	
	while ($row = mysqli_fetch_assoc($result)) {
		$dueDate = $row['due_date'] ?: compute_due_date($row['issue_date'])->format('Y-m-d');
		$dueObj = new DateTime($dueDate);
		$todayObj = new DateTime('today');
		$daysUntil = (int)$todayObj->diff($dueObj)->format('%a');
		
		$notifications['upcoming_due'][] = [
			'book_title' => $row['title'],
			'due_date' => $dueDate,
			'days_until' => $daysUntil
		];
	}
	mysqli_stmt_close($stmt);
	
	// Get admin messages (custom notifications)
	$messagesQuery = "SELECT id, title, message, type, created_at
					  FROM notifications
					  WHERE user_id = ?
					  AND type IN ('warning', 'danger', 'info')
					  AND is_read = 0
					  ORDER BY created_at DESC
					  LIMIT 5";
	
	$stmt = mysqli_prepare($conn, $messagesQuery);
	mysqli_stmt_bind_param($stmt, 'i', $userId);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);
	
	while ($row = mysqli_fetch_assoc($result)) {
		$notifications['admin_messages'][] = [
			'id' => (int)$row['id'],
			'title' => $row['title'],
			'message' => $row['message'],
			'type' => $row['type'],
			'created_at' => $row['created_at']
		];
	}
	mysqli_stmt_close($stmt);
	
	// Check if there's anything to show
	$hasNotifications = !empty($notifications['overdue_fines']) || 
						 !empty($notifications['upcoming_due']) || 
						 !empty($notifications['admin_messages']);
	
	echo json_encode([
		'success' => true,
		'has_notifications' => $hasNotifications,
		'notifications' => $notifications
	]);
	
} catch (Exception $e) {
	log_message("Error in get_login_notifications: " . $e->getMessage());
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Error fetching notifications']);
}

