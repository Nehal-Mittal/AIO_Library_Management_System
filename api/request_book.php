<?php
/**
 * API Endpoint: One-click Book Request
 * Handles instant book requests without redirect
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Not authenticated']);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['success' => false, 'message' => 'Method not allowed']);
	exit;
}

$userId = (int)$_SESSION['user']['id'];
$role = $_SESSION['user']['role'] ?? 'student';

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
	exit;
}

$bookId = (int)($_POST['book_id'] ?? 0);

if ($bookId <= 0) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid book ID']);
	exit;
}

try {
	// Check if user can request more books
	if (!can_issue_more($conn, $userId, $role)) {
		$limit = user_issue_limit($role);
		$funnyMessages = [
			"No more books allowed! Your bag is already heavier than your future 😆",
			"Return some books first, book hoarder! 📚😜",
			"Library says: bas karo! pehle purane return karo 😄",
			"You've hit the limit! Time to return before you request 📖",
			"Maximum books reached! Your bookshelf is crying for help 😂",
			"Can't issue more! Your current books are feeling neglected 😅"
		];
		$message = $funnyMessages[array_rand($funnyMessages)];
		
		http_response_code(400);
		echo json_encode([
			'success' => false, 
			'message' => $message,
			'limit_reached' => true,
			'current_limit' => $limit
		]);
		exit;
	}
	
	// Get book details
	$bookStmt = mysqli_prepare($conn, "SELECT id, title, author, department FROM books WHERE id=? AND available_copies > 0 LIMIT 1");
	mysqli_stmt_bind_param($bookStmt, 'i', $bookId);
	mysqli_stmt_execute($bookStmt);
	$bookResult = mysqli_stmt_get_result($bookStmt);
	$book = mysqli_fetch_assoc($bookResult);
	mysqli_stmt_close($bookStmt);
	
	if (!$book) {
		http_response_code(404);
		echo json_encode(['success' => false, 'message' => 'Book not found or not available']);
		exit;
	}
	
	// Check if user already has a pending request for this book
	$checkStmt = mysqli_prepare($conn, "SELECT id FROM book_requests WHERE user_id=? AND book_id=? AND status='pending' LIMIT 1");
	mysqli_stmt_bind_param($checkStmt, 'ii', $userId, $bookId);
	mysqli_stmt_execute($checkStmt);
	$checkResult = mysqli_stmt_get_result($checkStmt);
	$existing = mysqli_fetch_assoc($checkResult);
	mysqli_stmt_close($checkStmt);
	
	if ($existing) {
		http_response_code(400);
		echo json_encode(['success' => false, 'message' => 'You already have a pending request for this book']);
		exit;
	}
	
	// Auto-fill department from user's profile or book's department
	$department = $book['department'] ?? 'General';
	
	// Create request
	$insertStmt = mysqli_prepare($conn, "INSERT INTO book_requests (user_id, book_id, title, author, department, status) VALUES (?, ?, ?, ?, ?, 'pending')");
	mysqli_stmt_bind_param($insertStmt, 'iisss', $userId, $bookId, $book['title'], $book['author'], $department);
	
	if (mysqli_stmt_execute($insertStmt)) {
		mysqli_stmt_close($insertStmt);
		
		// Send notification
		create_notification($conn, $userId, 'Book Request Submitted', "Your request for '{$book['title']}' has been submitted. Waiting for admin approval.", 'info');
		
		echo json_encode([
			'success' => true,
			'message' => 'Book request submitted successfully!',
			'book_title' => $book['title']
		]);
	} else {
		mysqli_stmt_close($insertStmt);
		http_response_code(500);
		echo json_encode(['success' => false, 'message' => 'Failed to submit request']);
	}
	
} catch (Exception $e) {
	log_message("Error in request_book API: " . $e->getMessage());
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

