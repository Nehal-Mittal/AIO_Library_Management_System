<?php
require_once __DIR__ . '/../config.php';
$user = current_user();
$role = $user['role'] ?? null;
$currentPage = basename($_SERVER['PHP_SELF']);

// Determine active menu item based on current page
function isActive($page, $current) {
	return $page === $current ? 'active' : '';
}

// Get dashboard URL based on role
$dashboardUrl = match($role) {
	'admin' => '/admin/admin_dashboard.php',
	'teacher' => '/teacher/teacher_dashboard.php',
	'student' => '/student/student_dashboard.php',
	default => '/index.php'
};
?>

<!-- Sidebar Navigation -->
<aside class="sidebar" id="sidebar">
	<div class="sidebar-header">
		<a href="<?php echo $dashboardUrl; ?>" class="sidebar-brand">
			<i class="bi bi-journal-bookmark me-2"></i>
			<span class="sidebar-brand-text">Library LMS</span>
		</a>
		<button class="sidebar-toggle d-lg-none" id="sidebarToggle" aria-label="Toggle sidebar">
			<i class="bi bi-x-lg"></i>
		</button>
	</div>
	
	<nav class="sidebar-nav">
		<ul class="sidebar-menu">
			<!-- Dashboard -->
			<li class="sidebar-item">
				<a href="<?php echo $dashboardUrl; ?>" class="sidebar-link <?php echo isActive('admin_dashboard.php', $currentPage) || isActive('teacher_dashboard.php', $currentPage) || isActive('student_dashboard.php', $currentPage); ?>">
					<i class="bi bi-speedometer2"></i>
					<span>Dashboard</span>
				</a>
			</li>
			
			<?php if ($role === 'admin'): ?>
				<!-- Admin Menu -->
				<li class="sidebar-item">
					<a href="/admin/manage_books.php" class="sidebar-link <?php echo isActive('manage_books.php', $currentPage); ?>">
						<i class="bi bi-book"></i>
						<span>Books</span>
					</a>
				</li>
				<li class="sidebar-item">
					<a href="/admin/manage_requests.php" class="sidebar-link <?php echo isActive('manage_requests.php', $currentPage); ?>">
						<i class="bi bi-inbox"></i>
						<span>Issue/Return</span>
					</a>
				</li>
				<li class="sidebar-item">
					<a href="/admin/manage_users.php" class="sidebar-link <?php echo isActive('manage_users.php', $currentPage); ?>">
						<i class="bi bi-people"></i>
						<span>Users</span>
					</a>
				</li>
				<li class="sidebar-item">
					<a href="/admin/manage_uploads.php" class="sidebar-link <?php echo isActive('manage_uploads.php', $currentPage); ?>">
						<i class="bi bi-file-earmark-text"></i>
						<span>Uploads</span>
					</a>
				</li>
				<li class="sidebar-item">
					<a href="/admin/manage_notices.php" class="sidebar-link <?php echo isActive('manage_notices.php', $currentPage); ?>">
						<i class="bi bi-megaphone"></i>
						<span>Notices</span>
					</a>
				</li>
				<li class="sidebar-item">
					<a href="/admin/manage_suggestions.php" class="sidebar-link <?php echo isActive('manage_suggestions.php', $currentPage); ?>">
						<i class="bi bi-lightbulb"></i>
						<span>Suggestions</span>
					</a>
				</li>
				<li class="sidebar-item">
					<a href="/admin/generate_reports.php" class="sidebar-link <?php echo isActive('generate_reports.php', $currentPage); ?>">
						<i class="bi bi-graph-up"></i>
						<span>Reports</span>
					</a>
				</li>
			<?php elseif ($role === 'teacher'): ?>
				<!-- Teacher Menu -->
				<li class="sidebar-item">
					<a href="/teacher/available_books.php" class="sidebar-link <?php echo isActive('available_books.php', $currentPage); ?>">
						<i class="bi bi-book"></i>
						<span>Books</span>
					</a>
				</li>
				<li class="sidebar-item">
					<a href="/teacher/issued_books.php" class="sidebar-link <?php echo isActive('issued_books.php', $currentPage); ?>">
						<i class="bi bi-journal-check"></i>
						<span>Issue/Return</span>
					</a>
				</li>
				<li class="sidebar-item">
					<a href="/teacher/book_request.php" class="sidebar-link <?php echo isActive('book_request.php', $currentPage); ?>">
						<i class="bi bi-bookmark-plus"></i>
						<span>Request Book</span>
					</a>
				</li>
				<li class="sidebar-item">
					<a href="/teacher/post_notice.php" class="sidebar-link <?php echo isActive('post_notice.php', $currentPage); ?>">
						<i class="bi bi-megaphone"></i>
						<span>Post Notice</span>
					</a>
				</li>
			<?php elseif ($role === 'student'): ?>
				<!-- Student Menu -->
				<li class="sidebar-item">
					<a href="/student/available_books.php" class="sidebar-link <?php echo isActive('available_books.php', $currentPage); ?>">
						<i class="bi bi-book"></i>
						<span>Books</span>
					</a>
				</li>
				<li class="sidebar-item">
					<a href="/student/my_books.php" class="sidebar-link <?php echo isActive('my_books.php', $currentPage); ?>">
						<i class="bi bi-journal-check"></i>
						<span>Issue/Return</span>
					</a>
				</li>
				<li class="sidebar-item">
					<a href="/student/book_request.php" class="sidebar-link <?php echo isActive('book_request.php', $currentPage); ?>">
						<i class="bi bi-bookmark-plus"></i>
						<span>Request Book</span>
					</a>
				</li>
			<?php endif; ?>
			
			<!-- Common Menu Items -->
			<?php if ($role !== 'admin'): ?>
				<li class="sidebar-item">
					<a href="/student/upload_notes.php" class="sidebar-link <?php echo isActive('upload_notes.php', $currentPage); ?>">
						<i class="bi bi-cloud-upload"></i>
						<span>Upload Notes</span>
					</a>
				</li>
			<?php endif; ?>
			
			<li class="sidebar-item">
				<a href="/notes/shared_notes.php" class="sidebar-link <?php echo isActive('shared_notes.php', $currentPage); ?>">
					<i class="bi bi-folder2-open"></i>
					<span>Shared Notes</span>
				</a>
			</li>
			
			<li class="sidebar-item">
				<a href="/notices/notice_board.php" class="sidebar-link <?php echo isActive('notice_board.php', $currentPage); ?>">
					<i class="bi bi-pin-angle"></i>
					<span>Notice Board</span>
				</a>
			</li>
			
			<?php if ($role !== 'admin'): ?>
				<li class="sidebar-item">
					<a href="/student/suggest_book.php" class="sidebar-link <?php echo isActive('suggest_book.php', $currentPage); ?>">
						<i class="bi bi-lightbulb"></i>
						<span>Suggest Book</span>
					</a>
				</li>
			<?php endif; ?>
			
			<!-- Divider -->
			<li class="sidebar-divider"></li>
			
			<!-- Security & Settings -->
			<li class="sidebar-item">
				<a href="/security/fingerprint.php" class="sidebar-link <?php echo isActive('fingerprint.php', $currentPage); ?>">
					<i class="bi bi-shield-check"></i>
					<span>Security & Account</span>
				</a>
			</li>
			
			<!-- Logout -->
			<li class="sidebar-item">
				<a href="/logout.php" class="sidebar-link text-danger">
					<i class="bi bi-box-arrow-right"></i>
					<span>Logout</span>
				</a>
			</li>
		</ul>
	</nav>
</aside>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

