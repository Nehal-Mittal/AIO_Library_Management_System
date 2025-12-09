<?php
require_once __DIR__ . '/../config.php';
$user = current_user();
$role = $user['role'] ?? null;

// Ensure the verification field exists to avoid undefined key warnings
$emailVerified = $user['email_verified'] ?? 0;

$verificationBadge = '';
if ($user && !$emailVerified) {
    $verificationBadge = ' <a class="badge text-bg-warning ms-2 text-decoration-none" href="/verify_otp.php">Verify Email</a>';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-gradient-primary shadow-sm">
    <div class="container-fluid">
        <?php if ($user): ?>
        <button class="btn btn-link text-white me-3 d-lg-none" id="mobileSidebarToggle" aria-label="Toggle sidebar">
            <i class="bi bi-list fs-4"></i>
        </button>
        <?php endif; ?>
        <a class="navbar-brand fw-semibold" href="/index.php"><i class="bi bi-journal-bookmark me-2"></i>LMS</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if ($role === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="/admin/admin_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/manage_books.php">Books</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/manage_requests.php">Requests</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/issued_books.php">Issued</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/manage_users.php">Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/manage_notices.php">Notices</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/manage_uploads.php">Uploads</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/manage_suggestions.php">Suggestions</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/generate_reports.php">Reports</a></li>
                <?php elseif ($role === 'teacher'): ?>
                    <li class="nav-item"><a class="nav-link" href="/teacher/teacher_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/teacher/available_books.php">Available Books</a></li>
                    <li class="nav-item"><a class="nav-link" href="/teacher/issued_books.php">My Issued Books</a></li>
                    <li class="nav-item"><a class="nav-link" href="/teacher/book_request.php">Request Book</a></li>
                    <li class="nav-item"><a class="nav-link" href="/student/upload_notes.php">Upload Notes</a></li>
                    <li class="nav-item"><a class="nav-link" href="/student/suggest_book.php">Suggest Book</a></li>
                    <li class="nav-item"><a class="nav-link" href="/teacher/post_notice.php">Post Notice</a></li>
                <?php elseif ($role === 'student'): ?>
                    <li class="nav-item"><a class="nav-link" href="/student/student_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/student/available_books.php">Available Books</a></li>
                    <li class="nav-item"><a class="nav-link" href="/student/my_books.php">My Books</a></li>
                    <li class="nav-item"><a class="nav-link" href="/student/book_request.php">Request Book</a></li>
                    <li class="nav-item"><a class="nav-link" href="/student/upload_notes.php">Upload Notes</a></li>
                    <li class="nav-item"><a class="nav-link" href="/student/suggest_book.php">Suggest Book</a></li>
                    <li class="nav-item"><a class="nav-link" href="/student/view_notices.php">Notices</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="/notices/notice_board.php">Notice Board</a></li>
                <?php if ($user): ?>
                    <li class="nav-item"><a class="nav-link" href="/security/fingerprint.php"><i class="bi bi-shield-check me-1"></i>Security</a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav align-items-center">
                <?php if ($user): ?>
                    <li class="nav-item text-white-50 me-3">
                        <i class="bi bi-person-circle me-1"></i><?php echo h($user['name']); ?><?php echo $verificationBadge; ?>
                    </li>
                    <!-- Notification Bell -->
                    <li class="nav-item dropdown me-2">
                        <a class="nav-link position-relative text-white" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell fs-5"></i>
                            <?php 
                            $unreadCount = get_unread_notification_count($conn, (int)$user['id']);
                            if ($unreadCount > 0): 
                            ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                    <?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notificationDropdown" style="min-width: 350px; max-height: 400px; overflow-y: auto;">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php 
                            $recentNotifications = get_notifications($conn, (int)$user['id'], 5, true);
                            if (empty($recentNotifications)): 
                            ?>
                                <li><span class="dropdown-item-text text-muted">No new notifications</span></li>
                            <?php else: ?>
                                <?php foreach ($recentNotifications as $notif): ?>
                                    <li>
                                        <a class="dropdown-item <?php echo $notif['is_read'] ? '' : 'fw-bold'; ?>" href="/notifications.php">
                                            <div class="d-flex justify-content-between">
                                                <span class="badge text-bg-<?php 
                                                    echo match($notif['type']) {
                                                        'success' => 'success',
                                                        'warning' => 'warning',
                                                        'danger' => 'danger',
                                                        'funny' => 'info',
                                                        default => 'info'
                                                    };
                                                ?> me-2"><?php echo h(ucfirst($notif['type'])); ?></span>
                                                <small class="text-muted"><?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?></small>
                                            </div>
                                            <div class="mt-1">
                                                <strong><?php echo h($notif['title']); ?></strong>
                                                <p class="mb-0 small text-muted"><?php echo h(mb_substr($notif['message'], 0, 80)) . (mb_strlen($notif['message']) > 80 ? '...' : ''); ?></p>
                                            </div>
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <li><a class="dropdown-item text-center fw-bold" href="/notifications.php">View All Notifications</a></li>
                        </ul>
                    </li>
                    <li class="nav-item me-2">
                        <button class="btn btn-outline-light btn-sm theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
                            <i class="bi bi-moon-stars" id="themeIcon"></i>
                        </button>
                    </li>
                    <li class="nav-item me-2"><a class="btn btn-outline-light btn-sm" href="/change_password.php">Change Password</a></li>
                    <li class="nav-item"><a class="btn btn-light btn-sm text-primary" href="/logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="btn btn-outline-light" href="/index.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
