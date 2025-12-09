<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$bookId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($bookId <= 0) {
    header('Location: /student/available_books.php');
    exit;
}

// Log book view for recommendations
if (isset($_SESSION['user']['id'])) {
	log_book_view($conn, (int)$_SESSION['user']['id'], $bookId);
	
	// Check if user viewed this book multiple times (for funny notification)
	$viewStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS view_count FROM book_views WHERE user_id=? AND book_id=? AND viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
	mysqli_stmt_bind_param($viewStmt, 'ii', $_SESSION['user']['id'], $bookId);
	mysqli_stmt_execute($viewStmt);
	$viewRes = mysqli_stmt_get_result($viewStmt);
	$viewData = mysqli_fetch_assoc($viewRes);
	mysqli_stmt_close($viewStmt);
	
	if ((int)$viewData['view_count'] >= 3) {
		$bookTitle = ''; // Will be set after fetching book
	}
}

// --- Fetch book info with aggregate reviews ---
$bookStmt = mysqli_prepare($conn, "
    SELECT 
        b.*,
        COALESCE(r.avg_rating, 0) AS avg_rating,
        COALESCE(r.review_count, 0) AS review_count
    FROM books b
    LEFT JOIN (
        SELECT book_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
        FROM book_reviews
        WHERE book_id = ?
        GROUP BY book_id
    ) r ON r.book_id = b.id
    WHERE b.id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($bookStmt, 'ii', $bookId, $bookId);
mysqli_stmt_execute($bookStmt);
$bookRes = mysqli_stmt_get_result($bookStmt);
$book = mysqli_fetch_assoc($bookRes);
mysqli_stmt_close($bookStmt);

if (!$book) {
    header('Location: /student/available_books.php');
    exit;
}

// Send funny notification if user viewed this book multiple times
if (isset($_SESSION['user']['id']) && isset($viewData) && (int)$viewData['view_count'] >= 3) {
	$lastNotifCheck = $_SESSION['last_view_notif_' . $bookId] ?? null;
	$today = date('Y-m-d');
	if ($lastNotifCheck !== $today) {
		sendFunnyNotification($conn, (int)$_SESSION['user']['id'], 'viewed_multiple', [
			'title' => $book['title'],
			'count' => (int)$viewData['view_count']
		]);
		$_SESSION['last_view_notif_' . $bookId] = $today;
	}
}

// --- Fetch current user's review ---
$userId = (int)$_SESSION['user']['id'];
$currentReviewStmt = mysqli_prepare($conn, "
    SELECT id, rating, review 
    FROM book_reviews 
    WHERE book_id=? AND user_id=? 
    LIMIT 1
");
mysqli_stmt_bind_param($currentReviewStmt, 'ii', $bookId, $userId);
mysqli_stmt_execute($currentReviewStmt);
$currentReviewRes = mysqli_stmt_get_result($currentReviewStmt);
$myReview = mysqli_fetch_assoc($currentReviewRes);
mysqli_stmt_close($currentReviewStmt);

// --- Handle POST requests (save/delete review) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid request token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_review') {
            $rating = (int)($_POST['rating'] ?? 0);
            $reviewText = trim($_POST['review_text'] ?? '');
            if ($rating < 1 || $rating > 5) {
                $_SESSION['flash_error'] = 'Rating must be between 1 and 5.';
            } else {
                $stmt = mysqli_prepare($conn, "
                    INSERT INTO book_reviews (book_id, user_id, rating, review)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE rating=VALUES(rating), review=VALUES(review)
                ");
                mysqli_stmt_bind_param($stmt, 'iiis', $bookId, $userId, $rating, $reviewText);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $_SESSION['flash_success'] = 'Review saved.';
            }
        } elseif ($action === 'delete_review' && $myReview) {
            $del = mysqli_prepare($conn, "DELETE FROM book_reviews WHERE id=? AND user_id=?");
            mysqli_stmt_bind_param($del, 'ii', $myReview['id'], $userId);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
            $_SESSION['flash_success'] = 'Review removed.';
        }
    }
    header('Location: /book.php?id=' . $bookId);
    exit;
}

// --- Fetch community reviews ---
$reviewsStmt = mysqli_prepare($conn, "
    SELECT br.id, br.rating, br.review, br.updated_at, u.name
    FROM book_reviews br
    JOIN users u ON u.id = br.user_id
    WHERE br.book_id = ?
    ORDER BY br.updated_at DESC
");
mysqli_stmt_bind_param($reviewsStmt, 'i', $bookId);
mysqli_stmt_execute($reviewsStmt);
$reviewsRes = mysqli_stmt_get_result($reviewsStmt);

$reviews = [];
while ($row = mysqli_fetch_assoc($reviewsRes)) {
    $row['stars'] = str_repeat('★', (int)$row['rating']); // Precompute stars
    $reviews[] = $row;
}
mysqli_stmt_close($reviewsStmt);

include __DIR__ . '/includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <?php if (!empty($book['cover_image'])): ?>
                <img src="<?php echo h($book['cover_image']); ?>" class="card-img-top" alt="<?php echo h($book['title']); ?>" style="height:300px;object-fit:cover;">
            <?php endif; ?>
            <div class="card-body">
                <h2 class="card-title"><?php echo h($book['title']); ?></h2>
                <p class="text-muted mb-1"><?php echo h($book['author']); ?></p>
                <p class="small text-muted mb-1"><?php echo h($book['category']); ?></p>
                <p><?php echo nl2br(h($book['description'] ?? 'No description available.')); ?></p>
                <div class="d-flex align-items-center gap-3">
                    <div><i class="bi bi-star-fill text-warning me-1"></i><?php echo number_format($book['avg_rating'], 1); ?> / 5</div>
                    <div class="text-muted small">(<?php echo (int)$book['review_count']; ?> reviews)</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <!-- User Review -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h4 class="card-title">Your Review</h4>
                <?php if (!empty($_SESSION['flash_error'])): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <?php echo h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($_SESSION['flash_success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo h($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="action" value="save_review">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Rating</label>
                        <select name="rating" class="form-select" required>
                            <option value="">Select</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo isset($myReview['rating']) && (int)$myReview['rating'] === $i ? 'selected' : ''; ?>><?php echo $i; ?> star<?php echo $i>1?'s':''; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Review</label>
                        <textarea name="review_text" class="form-control" rows="3" placeholder="Share your thoughts (optional)"><?php echo h($myReview['review'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Save Review</button>
                        <?php if ($myReview): ?>
                            <button class="btn btn-outline-danger" type="submit" name="action" value="delete_review">Delete Review</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Community Reviews -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="card-title mb-3">Community Reviews</h4>
                <?php if (empty($reviews)): ?>
                    <p class="text-muted mb-0">No reviews yet. Be the first to share your experience!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between">
                                <strong><?php echo h($review['name']); ?></strong>
                                <span class="text-warning"><?php echo h($review['stars']); ?></span>
                            </div>
                            <p class="mb-1"><?php echo nl2br(h($review['review'])); ?></p>
                            <small class="text-muted">Updated <?php echo h($review['updated_at']); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
