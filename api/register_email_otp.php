<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['success' => false, 'message' => 'Method not allowed']);
	exit;
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page.']);
	exit;
}

$action = $_POST['action'] ?? '';
$email = strtolower(trim($_POST['email'] ?? ''));

if ($action === 'send_otp') {
	$result = send_registration_email_otp($conn, $email, !empty($_POST['force']));
	echo json_encode($result);
	exit;
}

if ($action === 'verify_otp') {
	$otp = trim($_POST['otp'] ?? '');
	if ($email === '' || $otp === '') {
		http_response_code(400);
		echo json_encode(['success' => false, 'message' => 'Email and OTP are required.']);
		exit;
	}
	$result = verify_registration_email_otp($email, $otp);
	echo json_encode($result);
	exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action.']);
