<?php
/**
 * Get User Profile API
 * Returns comprehensive user information for admin view
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Require admin role
require_role('admin');

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($userId <= 0) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
	exit;
}

// Get user basic info
$stmt = mysqli_prepare($conn, "SELECT id, name, email, phone, role, status, email_verified, created_at FROM users WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
	http_response_code(404);
	echo json_encode(['success' => false, 'message' => 'User not found.']);
	exit;
}
mysqli_stmt_close($stmt);

// Get uploaded notes count
$notesStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS count FROM uploaded_notes WHERE user_id=?");
mysqli_stmt_bind_param($notesStmt, 'i', $userId);
mysqli_stmt_execute($notesStmt);
$notesResult = mysqli_stmt_get_result($notesStmt);
$notesRow = mysqli_fetch_assoc($notesResult);
$user['notes_count'] = (int)$notesRow['count'];
mysqli_stmt_close($notesStmt);

// Get issued books count
$issuedStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS count FROM issued_books WHERE user_id=? AND return_date IS NULL");
mysqli_stmt_bind_param($issuedStmt, 'i', $userId);
mysqli_stmt_execute($issuedStmt);
$issuedResult = mysqli_stmt_get_result($issuedStmt);
$issuedRow = mysqli_fetch_assoc($issuedResult);
$user['issued_count'] = (int)$issuedRow['count'];
mysqli_stmt_close($issuedStmt);

// Get returned books count
$returnedStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS count FROM issued_books WHERE user_id=? AND return_date IS NOT NULL");
mysqli_stmt_bind_param($returnedStmt, 'i', $userId);
mysqli_stmt_execute($returnedStmt);
$returnedResult = mysqli_stmt_get_result($returnedStmt);
$returnedRow = mysqli_fetch_assoc($returnedResult);
$user['returned_count'] = (int)$returnedRow['count'];
mysqli_stmt_close($returnedStmt);

// Get trusted devices
$devicesStmt = mysqli_prepare($conn, "SELECT id, device_label, created_at, last_used_at FROM user_fingerprints WHERE user_id=? AND is_active=1 ORDER BY created_at DESC");
mysqli_stmt_bind_param($devicesStmt, 'i', $userId);
mysqli_stmt_execute($devicesStmt);
$devicesResult = mysqli_stmt_get_result($devicesStmt);
$devices = [];
while ($row = mysqli_fetch_assoc($devicesResult)) {
	$devices[] = [
		'device_label' => $row['device_label'],
		'created_at' => date('M d, Y H:i', strtotime($row['created_at'])),
		'last_used_at' => $row['last_used_at'] ? date('M d, Y H:i', strtotime($row['last_used_at'])) : 'Never'
	];
}
mysqli_stmt_close($devicesStmt);
$user['trusted_devices'] = $devices;
$user['trusted_devices_count'] = count($devices);

// Format dates
$user['created_at'] = date('M d, Y H:i', strtotime($user['created_at']));

echo json_encode(['success' => true, 'user' => $user]);

