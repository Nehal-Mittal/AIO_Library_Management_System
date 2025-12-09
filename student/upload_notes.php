<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student', 'teacher']);
include __DIR__ . '/../includes/header.php';

$uid = (int)$_SESSION['user']['id'];
$role = $_SESSION['user']['role'];
$error = '';
$success = '';

// Funny success messages for upload
$funnyUploadMessages = [
	"Thanks for your masterpiece! Even Shakespeare would be jealous 📚😆",
	"Your notes have entered the Library Multiverse! 🚀",
	"Upload successful! May the admin approve it faster than your exam results 😜",
	"Your brain has contributed something valuable. Proud of you! 🤓🔥",
	"Admin will now judge your notes… may luck be on your side 😈📄",
	"Your upload is here! If admin rejects it, we riot 🚩🔥",
	"Your notes have entered the chat. Autocorrect is scared 😳📱",
	"Amazing! Even ChatGPT wants to copy your notes 🤫😆",
	"Notes uploaded! Expect admin approval sometime before the next ice age ❄️😂",
	"Your file is safely stored… unlike your GPA 😜📉",
	"You contributed knowledge! Now go take a nap 😴📘",
	"Your notes are now public… don't worry, we won't expose your handwriting 🤣✍️",
	"Congrats! You're officially smarter than yesterday 🧠🔥",
	"If brains were WiFi, yours would have full bars 📶🤓",
	"Your notes have been uploaded! Even Google is nervous now 🤖📚",
	"Great job! Your brain just flexed a little 💪🧠",
	"Upload successful! This belongs in a museum… or at least the library 😄",
	"Your notes have joined the digital universe. They are immortal now 🌌",
	"Boom! Your knowledge just exploded into the system 💥📘",
	"Your upload is smoother than 5G internet ⚡📡",
	"Congrats! You've fed the hungry Library Database 🍽️📚",
	"Your notes just graduated with honors 🎓📄",
	"Library gods bless you for your contribution 🙏📚",
	"Your notes just became part of Library History 🏛️📚",
	"Upload successful — may these notes help someone pass an exam they didn't study for 😌📝",
	"These notes will save a student someday… maybe even you 👀📖",
	"Your file is now available. Knowledge level +100 📈📘",
	"Notes uploaded! The library fairy is proud of you 🧚📚",
	"Thanks! You just made the library 1% smarter 📊📚",
	"Your notes could end world hunger… study hunger at least 😋📖",
	"Well done! This is the kind of academic heroism we appreciate 🦸‍♂️📘"
];

// Funny success messages for delete
$funnyDeleteMessages = [
	"Your notes were successfully removed. They won't be missed 😄",
	"Deleted! Even recycle bin is proud of you 🗑️🤣"
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$error = 'Invalid request. Please refresh.';
	} else {
		$action = $_POST['action'] ?? '';
		
		// Handle delete action
		if ($action === 'delete') {
			$uploadId = (int)($_POST['upload_id'] ?? 0);
			
			if ($uploadId <= 0) {
				$error = 'Invalid upload ID.';
			} else {
				// Verify ownership and get file path
				$stmt = mysqli_prepare($conn, "SELECT id, file_path FROM uploaded_notes WHERE id=? AND user_id=? LIMIT 1");
				mysqli_stmt_bind_param($stmt, 'ii', $uploadId, $uid);
				mysqli_stmt_execute($stmt);
				$result = mysqli_stmt_get_result($stmt);
				$upload = mysqli_fetch_assoc($result);
				mysqli_stmt_close($stmt);
				
				if (!$upload) {
					$error = 'Upload not found or you do not have permission to delete it.';
				} else {
					// Delete file from server
					$filePath = __DIR__ . '/..' . $upload['file_path'];
					$fileDeleted = true;
					if (file_exists($filePath)) {
						$fileDeleted = @unlink($filePath);
						if (!$fileDeleted) {
							log_message("Failed to delete file: {$filePath}");
						}
					}
					
					// Delete database record
					$deleteStmt = mysqli_prepare($conn, "DELETE FROM uploaded_notes WHERE id=? AND user_id=?");
					mysqli_stmt_bind_param($deleteStmt, 'ii', $uploadId, $uid);
					
					if (mysqli_stmt_execute($deleteStmt)) {
						$success = $funnyDeleteMessages[array_rand($funnyDeleteMessages)];
						create_notification($conn, $uid, 'Notes Deleted', "Your uploaded notes have been successfully deleted.", 'info');
					} else {
						$error = 'Failed to delete record from database.';
						log_message("Failed to delete uploaded_notes record ID: {$uploadId}");
					}
					mysqli_stmt_close($deleteStmt);
				}
			}
		}
		// Handle upload action
		elseif ($action === 'upload') {
			$title = trim($_POST['title'] ?? '');
			$description = trim($_POST['description'] ?? '');
			$subject = trim($_POST['subject'] ?? '');
			$teacherName = trim($_POST['teacher_name'] ?? '');
			$file = $_FILES['file'] ?? null;
			
			if (empty($title) || !$file || $file['error'] !== UPLOAD_ERR_OK) {
				$error = 'Please provide title and select a valid file.';
			} else {
				// Validate file type
				$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
				$fileType = mime_content_type($file['tmp_name']);
				$fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
				
				if (!in_array($fileType, $allowedTypes) || !in_array($fileExt, ['jpg', 'jpeg', 'png', 'pdf'])) {
					$error = 'Invalid file type. Only JPG, PNG images and PDF files are allowed.';
				} elseif ($file['size'] > 500 * 1024 * 1024) { // 500 MB
					$error = 'File size exceeds 500 MB limit.';
				} else {
					// Create upload directory if not exists
					$uploadDir = __DIR__ . '/../uploads/notes';
					if (!is_dir($uploadDir)) {
						if (!@mkdir($uploadDir, 0775, true)) {
							$error = 'Failed to create upload directory. Please contact administrator.';
							log_message("Failed to create upload directory: {$uploadDir}");
						}
					}
					
					// Ensure directory is writable
					if (is_dir($uploadDir) && !is_writable($uploadDir)) {
						@chmod($uploadDir, 0775);
						if (!is_writable($uploadDir)) {
							$error = 'Upload directory is not writable. Please contact administrator.';
							log_message("Upload directory not writable: {$uploadDir}");
						}
					}
					
					// Proceed only if directory is ready
					if (empty($error)) {
						// Generate unique filename
						$fileName = uniqid('note_', true) . '_' . time() . '.' . $fileExt;
						$filePath = $uploadDir . '/' . $fileName;
						
						if (move_uploaded_file($file['tmp_name'], $filePath)) {
							$relativePath = '/uploads/notes/' . $fileName;
							
							$stmt = mysqli_prepare($conn, "INSERT INTO uploaded_notes (user_id, title, description, subject, teacher_name, uploader_type, file_path, file_type, file_size, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
							$fileTypeDb = $fileExt === 'pdf' ? 'pdf' : 'image';
							$uploaderType = $role;
							mysqli_stmt_bind_param($stmt, 'isssssssi', $uid, $title, $description, $subject, $teacherName, $uploaderType, $relativePath, $fileTypeDb, $file['size']);
							
							if (mysqli_stmt_execute($stmt)) {
								$success = $funnyUploadMessages[array_rand($funnyUploadMessages)];
								create_notification($conn, $uid, 'Notes Uploaded', "Your notes '{$title}' have been uploaded and are pending admin approval.", 'info');
							} else {
								$error = 'Failed to save upload record.';
								@unlink($filePath); // Clean up uploaded file
							}
							mysqli_stmt_close($stmt);
						} else {
							$error = 'Failed to upload file. Please try again.';
						}
					}
				}
			}
		}
	}
}

$myUploads = mysqli_query($conn, "SELECT id, title, description, file_type, file_size, status, created_at FROM uploaded_notes WHERE user_id={$uid} ORDER BY created_at DESC");
?>
<h3 class="mb-3">Upload Notes / PDFs</h3>

<?php if ($error): ?>
	<div class="alert alert-danger alert-dismissible fade show">
		<?php echo h($error); ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	</div>
<?php endif; ?>

<?php if ($success): ?>
	<div class="alert alert-success alert-dismissible fade show">
		<?php echo h($success); ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	</div>
<?php endif; ?>

<div class="row g-3">
	<div class="col-lg-5">
		<div class="card">
			<div class="card-body">
				<h5 class="card-title">Upload New Notes</h5>
				<form method="post" enctype="multipart/form-data">
					<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
					<input type="hidden" name="action" value="upload">
					<div class="mb-3">
						<label class="form-label">Title <span class="text-danger">*</span></label>
						<input type="text" name="title" class="form-control" required maxlength="200">
					</div>
					<div class="mb-3">
						<label class="form-label">Description (Optional)</label>
						<textarea name="description" class="form-control" rows="3" maxlength="500"></textarea>
					</div>
					<div class="mb-3">
						<label class="form-label">Subject (Optional)</label>
						<input type="text" name="subject" class="form-control" maxlength="200" placeholder="e.g., Data Structures, Mathematics">
					</div>
					<div class="mb-3">
						<label class="form-label">Teacher Name (Optional)</label>
						<input type="text" name="teacher_name" class="form-control" maxlength="150" placeholder="e.g., Dr. John Smith">
					</div>
					<div class="mb-3">
						<label class="form-label">File <span class="text-danger">*</span></label>
						<input type="file" name="file" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
						<small class="text-muted">Allowed: JPG, PNG images or PDF files (max 500 MB)</small>
					</div>
					<button type="submit" class="btn btn-primary">Upload</button>
				</form>
			</div>
		</div>
	</div>
	<div class="col-lg-7">
		<div class="card">
			<div class="card-body">
				<h5 class="card-title">My Uploads</h5>
				<div class="table-responsive">
					<table class="table table-striped">
						<thead>
							<tr>
								<th>Title</th>
								<th>Type</th>
								<th>Size</th>
								<th>Status</th>
								<th>Uploaded</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php if (mysqli_num_rows($myUploads) === 0): ?>
								<tr><td colspan="6" class="text-center text-muted">No uploads yet</td></tr>
							<?php else: ?>
								<?php 
								// Reset result pointer
								mysqli_data_seek($myUploads, 0);
								while ($upload = mysqli_fetch_assoc($myUploads)): 
								?>
								<tr>
									<td><?php echo h($upload['title']); ?></td>
									<td><span class="badge text-bg-<?php echo $upload['file_type']==='pdf'?'danger':'info'; ?>"><?php echo strtoupper($upload['file_type']); ?></span></td>
									<td><?php echo number_format($upload['file_size'] / 1024, 2); ?> KB</td>
									<td>
										<span class="badge text-bg-<?php 
											echo $upload['status']==='approved'?'success':($upload['status']==='rejected'?'danger':'warning'); 
										?>">
											<?php echo h(ucfirst($upload['status'])); ?>
										</span>
									</td>
									<td><?php echo date('Y-m-d', strtotime($upload['created_at'])); ?></td>
									<td>
										<form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this upload? This action cannot be undone.');">
											<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
											<input type="hidden" name="action" value="delete">
											<input type="hidden" name="upload_id" value="<?php echo (int)$upload['id']; ?>">
											<button type="submit" class="btn btn-sm btn-danger" title="Delete this upload">
												<i class="bi bi-trash"></i> Delete
											</button>
										</form>
									</td>
								</tr>
								<?php endwhile; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

