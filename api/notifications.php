<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json');

$userId = (int)$_SESSION['user']['id'];
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	if ($action === 'count') {
		$count = get_unread_notification_count($conn, $userId);
		echo json_encode(['count' => $count]);
	} elseif ($action === 'list') {
		$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
		$unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === '1';
		$notifications = get_notifications($conn, $userId, $limit, $unreadOnly);
		echo json_encode(['notifications' => $notifications]);
	} else {
		http_response_code(400);
		echo json_encode(['error' => 'Invalid action']);
	}
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$input = json_decode(file_get_contents('php://input'), true);
	$action = $input['action'] ?? '';
	
	if ($action === 'mark_read') {
		$notificationId = (int)($input['id'] ?? 0);
		if ($notificationId > 0) {
			$result = mark_notification_read($conn, $notificationId, $userId);
			echo json_encode(['success' => $result]);
		} else {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid notification ID']);
		}
	} elseif ($action === 'mark_all_read') {
		$result = mark_all_notifications_read($conn, $userId);
		echo json_encode(['success' => $result]);
	} else {
		http_response_code(400);
		echo json_encode(['error' => 'Invalid action']);
	}
} else {
	http_response_code(405);
	echo json_encode(['error' => 'Method not allowed']);
}
?>
