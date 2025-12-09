<?php
/**
 * Fingerprint Login Endpoint
 */

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Allow: POST');
	http_response_code(405);
	echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
	exit;
}

// Parse JSON payload
$input = file_get_contents('php://input');
$payload = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
	exit;
}

$fingerprint = trim($payload['fingerprint'] ?? '');
if (strlen($fingerprint) < 10) {
	http_response_code(422);
	echo json_encode(['success' => false, 'message' => 'Invalid fingerprint payload.']);
	exit;
}

// Find user by fingerprint
$user = find_user_by_fingerprint($conn, $fingerprint);
if (!$user) {
	http_response_code(404);
	echo json_encode([
		'success' => false,
		'message' => 'Fingerprint not recognised. Please register this device first or use email login.'
	]);
	exit;
}

// Check account status safely
$status = $user['status'] ?? 'pending';
$emailVerified = $user['email_verified'] ?? 0;

if ($status !== 'active') {
	http_response_code(403);
	$statusMsg = match($status) {
		'pending' => 'Your account is pending approval. Please contact admin.',
		'blacklisted' => 'Your account is blacklisted. Please contact admin.',
		default => 'Your account is not active.'
	};
	echo json_encode(['success' => false, 'message' => $statusMsg]);
	exit;
}

if (!$emailVerified) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Please verify your email before using fingerprint login.']);
	exit;
}

// Update last_used_at safely
$hash = fingerprint_signature($fingerprint);
$updateStmt = mysqli_prepare($conn, "UPDATE user_fingerprints SET last_used_at = NOW() WHERE fingerprint_hash = ? AND is_active=1");
mysqli_stmt_bind_param($updateStmt, 's', $hash);
mysqli_stmt_execute($updateStmt);
mysqli_stmt_close($updateStmt);

// Create session
$_SESSION['user'] = [
	'id' => (int)($user['id'] ?? 0),
	'name' => $user['name'] ?? '',
	'email' => $user['email'] ?? '',
	'role' => $user['role'] ?? 'student',
];

// Send optional funny notification
if (function_exists('sendFunnyNotification')) {
	sendFunnyNotification($conn, (int)$_SESSION['user']['id'], 'visit');
}

// Determine redirect URL based on role
$role = $_SESSION['user']['role'];
$redirect = match($role) {
	'admin' => '/admin/admin_dashboard.php',
	'teacher' => '/teacher/teacher_dashboard.php',
	default => '/student/student_dashboard.php',
};

// Log successful login
if (function_exists('log_message')) {
	log_message("Fingerprint login successful for user ID: {$_SESSION['user']['id']} ({$_SESSION['user']['email']})");
}

http_response_code(200);
echo json_encode(['success' => true, 'redirect' => $redirect, 'message' => 'Login successful!']);
