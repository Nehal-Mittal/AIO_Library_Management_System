<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
include __DIR__ . '/../includes/header.php';

$user = current_user();
$uid = (int)$user['id'];
$userRole = $user['role'];

// Handle delete action (only uploader or admin can delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$error = 'Invalid request. Please refresh.';
	} else {
		$noteId = (int)($_POST['note_id'] ?? 0);
		
		if ($noteId > 0) {
			// Check if user can delete (uploader or admin)
			$stmt = mysqli_prepare($conn, "SELECT id, user_id, file_path FROM uploaded_notes WHERE id=? AND status='approved' LIMIT 1");
			mysqli_stmt_bind_param($stmt, 'i', $noteId);
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$note = mysqli_fetch_assoc($result);
			mysqli_stmt_close($stmt);
			
			if ($note && ($note['user_id'] == $uid || $userRole === 'admin')) {
				// Delete file
				$filePath = __DIR__ . '/..' . $note['file_path'];
				if (file_exists($filePath)) {
					@unlink($filePath);
				}
				
				// Delete record
				$deleteStmt = mysqli_prepare($conn, "DELETE FROM uploaded_notes WHERE id=?");
				mysqli_stmt_bind_param($deleteStmt, 'i', $noteId);
				mysqli_stmt_execute($deleteStmt);
				mysqli_stmt_close($deleteStmt);
				
				$success = 'Note deleted successfully.';
			} else {
				$error = 'You do not have permission to delete this note.';
			}
		}
	}
}

// Get filter parameters
$searchQuery = trim($_GET['search'] ?? '');
$subjectFilter = trim($_GET['subject'] ?? '');
$teacherFilter = trim($_GET['teacher'] ?? '');
$uploaderTypeFilter = trim($_GET['uploader_type'] ?? '');

// Build query
$whereConditions = ["un.status='approved'"];
$params = [];
$types = '';

if (!empty($searchQuery)) {
	$whereConditions[] = "(title LIKE ? OR description LIKE ?)";
	$searchParam = "%{$searchQuery}%";
	$params[] = $searchParam;
	$params[] = $searchParam;
	$types .= 'ss';
}

if (!empty($subjectFilter)) {
	$whereConditions[] = "subject LIKE ?";
	$params[] = "%{$subjectFilter}%";
	$types .= 's';
}

if (!empty($teacherFilter)) {
	$whereConditions[] = "teacher_name LIKE ?";
	$params[] = "%{$teacherFilter}%";
	$types .= 's';
}

if (!empty($uploaderTypeFilter) && in_array($uploaderTypeFilter, ['student', 'teacher', 'admin'])) {
	$whereConditions[] = "uploader_type = ?";
	$params[] = $uploaderTypeFilter;
	$types .= 's';
}

$whereClause = implode(' AND ', $whereConditions);

// Get all approved notes with user info
$sql = "SELECT un.id, un.user_id, un.title, un.description, un.subject, 
        un.teacher_name, un.uploader_type, un.file_path, un.file_type, 
        un.file_size, un.created_at,
        u.name AS uploader_name, u.role AS uploader_role
        FROM uploaded_notes un
        JOIN users u ON u.id = un.user_id
        WHERE {$whereClause}
        ORDER BY un.created_at DESC";

$notes = [];
if (empty($params)) {
	$result = mysqli_query($conn, $sql);
	while ($row = mysqli_fetch_assoc($result)) {
		$notes[] = $row;
	}
} else {
	$stmt = mysqli_prepare($conn, $sql);
	if ($stmt) {
		mysqli_stmt_bind_param($stmt, $types, ...$params);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);
		while ($row = mysqli_fetch_assoc($result)) {
			$notes[] = $row;
		}
		mysqli_stmt_close($stmt);
	}
}

// Get unique subjects and teachers for filter dropdowns
$subjectsResult = mysqli_query($conn, "SELECT DISTINCT subject FROM uploaded_notes WHERE status='approved' AND subject IS NOT NULL AND subject != '' ORDER BY subject");
$subjects = [];
while ($row = mysqli_fetch_assoc($subjectsResult)) {
	$subjects[] = $row['subject'];
}

$teachersResult = mysqli_query($conn, "SELECT DISTINCT teacher_name FROM uploaded_notes WHERE status='approved' AND teacher_name IS NOT NULL AND teacher_name != '' ORDER BY teacher_name");
$teachers = [];
while ($row = mysqli_fetch_assoc($teachersResult)) {
	$teachers[] = $row['teacher_name'];
}
?>

<h3 class="mb-4"><i class="bi bi-folder2-open me-2"></i>Shared Notes / All Uploaded Notes</h3>

<?php if (isset($error)): ?>
	<div class="alert alert-danger alert-dismissible fade show">
		<?php echo h($error); ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	</div>
<?php endif; ?>

<?php if (isset($success)): ?>
	<div class="alert alert-success alert-dismissible fade show">
		<?php echo h($success); ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	</div>
<?php endif; ?>

<!-- Search and Filter Section -->
<div class="card mb-4">
	<div class="card-body">
		<form method="get" action="" class="row g-3">
			<div class="col-md-4">
				<label class="form-label">Search</label>
				<input type="text" name="search" class="form-control" placeholder="Search by title or description..." value="<?php echo h($searchQuery); ?>">
			</div>
			<div class="col-md-2">
				<label class="form-label">Subject</label>
				<select name="subject" class="form-select">
					<option value="">All Subjects</option>
					<?php foreach ($subjects as $subject): ?>
						<option value="<?php echo h($subject); ?>" <?php echo $subjectFilter === $subject ? 'selected' : ''; ?>>
							<?php echo h($subject); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-2">
				<label class="form-label">Teacher</label>
				<select name="teacher" class="form-select">
					<option value="">All Teachers</option>
					<?php foreach ($teachers as $teacher): ?>
						<option value="<?php echo h($teacher); ?>" <?php echo $teacherFilter === $teacher ? 'selected' : ''; ?>>
							<?php echo h($teacher); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-2">
				<label class="form-label">Uploader Type</label>
				<select name="uploader_type" class="form-select">
					<option value="">All Types</option>
					<option value="student" <?php echo $uploaderTypeFilter === 'student' ? 'selected' : ''; ?>>Student</option>
					<option value="teacher" <?php echo $uploaderTypeFilter === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
					<option value="admin" <?php echo $uploaderTypeFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
				</select>
			</div>
			<div class="col-md-2 d-flex align-items-end">
				<button type="submit" class="btn btn-primary w-100">
					<i class="bi bi-search me-1"></i>Filter
				</button>
			</div>
		</form>
		<?php if (!empty($searchQuery) || !empty($subjectFilter) || !empty($teacherFilter) || !empty($uploaderTypeFilter)): ?>
			<div class="mt-2">
				<a href="/notes/shared_notes.php" class="btn btn-sm btn-outline-secondary">
					<i class="bi bi-x-circle me-1"></i>Clear Filters
				</a>
			</div>
		<?php endif; ?>
	</div>
</div>

<!-- Notes Grid -->
<?php if (empty($notes)): ?>
	<div class="card">
		<div class="card-body text-center py-5">
			<i class="bi bi-inbox fs-1 text-muted mb-3"></i>
			<p class="text-muted">No notes found. <?php echo !empty($searchQuery) || !empty($subjectFilter) || !empty($teacherFilter) || !empty($uploaderTypeFilter) ? 'Try adjusting your filters.' : 'Be the first to upload notes!'; ?></p>
		</div>
	</div>
<?php else: ?>
	<div class="row g-4">
		<?php foreach ($notes as $note): ?>
			<div class="col-md-6 col-lg-4">
				<div class="card h-100">
					<div class="card-body">
						<div class="d-flex justify-content-between align-items-start mb-2">
							<h5 class="card-title mb-0"><?php echo h($note['title']); ?></h5>
							<span class="badge text-bg-<?php echo $note['file_type'] === 'pdf' ? 'danger' : 'info'; ?>">
								<?php echo strtoupper($note['file_type']); ?>
							</span>
						</div>
						
						<?php if (!empty($note['description'])): ?>
							<p class="text-muted small mb-2"><?php echo h(mb_substr($note['description'], 0, 100)) . (mb_strlen($note['description']) > 100 ? '...' : ''); ?></p>
						<?php endif; ?>
						
						<div class="mb-2">
							<?php if (!empty($note['subject'])): ?>
								<span class="badge text-bg-secondary me-1">
									<i class="bi bi-bookmark me-1"></i><?php echo h($note['subject']); ?>
								</span>
							<?php endif; ?>
							<?php if (!empty($note['teacher_name'])): ?>
								<span class="badge text-bg-info me-1">
									<i class="bi bi-person me-1"></i><?php echo h($note['teacher_name']); ?>
								</span>
							<?php endif; ?>
						</div>
						
						<div class="small text-muted mb-3">
							<div><i class="bi bi-person-circle me-1"></i>Uploaded by: <?php echo h($note['uploader_name']); ?> (<?php echo h($note['uploader_role']); ?>)</div>
							<div><i class="bi bi-calendar me-1"></i><?php echo date('M d, Y', strtotime($note['created_at'])); ?></div>
							<div><i class="bi bi-file-earmark me-1"></i><?php echo number_format($note['file_size'] / 1024, 2); ?> KB</div>
						</div>
						
						<div class="d-flex gap-2">
							<?php if ($note['file_type'] === 'pdf'): ?>
								<a href="<?php echo h($note['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary flex-fill">
									<i class="bi bi-eye me-1"></i>Preview
								</a>
							<?php else: ?>
								<a href="<?php echo h($note['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary flex-fill">
									<i class="bi bi-image me-1"></i>View
								</a>
							<?php endif; ?>
							<a href="<?php echo h($note['file_path']); ?>" download class="btn btn-sm btn-outline-success">
								<i class="bi bi-download"></i>
							</a>
							<?php if ($note['user_id'] == $uid || $userRole === 'admin'): ?>
								<form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this note? This action cannot be undone.');">
									<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
									<input type="hidden" name="action" value="delete">
									<input type="hidden" name="note_id" value="<?php echo (int)$note['id']; ?>">
									<button type="submit" class="btn btn-sm btn-outline-danger">
										<i class="bi bi-trash"></i>
									</button>
								</form>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

