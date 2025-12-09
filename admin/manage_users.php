<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

// Handle actions: approve, blacklist, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$_SESSION['flash_error'] = 'Invalid request.';
	} else {
		$action = isset($_POST['action']) ? $_POST['action'] : '';
		$uid = isset($_POST['uid']) ? (int)$_POST['uid'] : 0;
		if ($uid > 0) {
			if ($action === 'approve') {
				mysqli_query($conn, "UPDATE users SET status='active' WHERE id=$uid AND role IN ('student','teacher')");
			} elseif ($action === 'blacklist') {
				mysqli_query($conn, "UPDATE users SET status='blacklisted' WHERE id=$uid AND role IN ('student','teacher')");
			} elseif ($action === 'delete') {
				mysqli_query($conn, "DELETE FROM users WHERE id=$uid AND role IN ('student','teacher')");
			}
		}
	}
}

include __DIR__ . '/../includes/header.php';

$users = mysqli_query($conn, "SELECT id, name, email, role, status FROM users WHERE role IN ('student','teacher') ORDER BY role, name");
?>

<h3 class="mb-3">Manage Users</h3>

<div class="card">
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-striped align-middle">
				<thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
				</thead>
				<tbody>
					<?php while ($u = mysqli_fetch_assoc($users)): ?>
                    <tr>
                        <td><?php echo (int)$u['id']; ?></td>
                        <td><?php echo h($u['name']); ?></td>
                        <td><?php echo h($u['email']); ?></td>
                        <td><span class="badge text-bg-<?php echo $u['role']==='teacher'?'info':'primary'; ?>"><?php echo h($u['role']); ?></span></td>
                        <td>
                            <?php
                            $status = isset($u['status']) ? $u['status'] : 'pending';
                            $color = $status==='active'?'success':($status==='blacklisted'?'danger':'secondary');
                            ?>
                            <span class="badge text-bg-<?php echo $color; ?>"><?php echo h($status); ?></span>
                        </td>
                        <td>
							<button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#userProfileModal" onclick="loadUserProfile(<?php echo (int)$u['id']; ?>)">
								<i class="bi bi-eye"></i> View Profile
							</button>
							<form method="post" class="d-inline" onsubmit="return confirm('Approve this user?');">
								<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="uid" value="<?php echo (int)$u['id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-sm btn-success" <?php echo ($status==='active'?'disabled':''); ?>>Approve</button>
                            </form>
							<form method="post" class="d-inline" onsubmit="return confirm('Blacklist this user?');">
								<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="uid" value="<?php echo (int)$u['id']; ?>">
                                <input type="hidden" name="action" value="blacklist">
                                <button type="submit" class="btn btn-sm btn-warning" <?php echo ($status==='blacklisted'?'disabled':''); ?>>Blacklist</button>
                            </form>
							<form method="post" class="d-inline" onsubmit="return confirm('Delete this user? This cannot be undone.');">
								<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="uid" value="<?php echo (int)$u['id']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
					<?php endwhile; ?>
				</tbody>
			</table>
		</div>
        <p class="text-muted mb-0">Note: New registrations appear here as <strong>pending</strong> until approved.</p>
	</div>
</div>

<!-- User Profile Modal -->
<div class="modal fade" id="userProfileModal" tabindex="-1" aria-labelledby="userProfileModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="userProfileModalLabel">User Profile</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" id="userProfileContent">
				<div class="text-center py-4">
					<div class="spinner-border text-primary" role="status">
						<span class="visually-hidden">Loading...</span>
					</div>
					<p class="mt-2">Loading user profile...</p>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
function loadUserProfile(userId) {
	const content = document.getElementById('userProfileContent');
	content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading user profile...</p></div>';
	
	fetch(`/api/get_user_profile.php?user_id=${userId}`)
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				const user = data.user;
				content.innerHTML = `
					<div class="row g-3">
						<div class="col-md-6">
							<strong>Full Name:</strong>
							<p>${escapeHtml(user.name)}</p>
						</div>
						<div class="col-md-6">
							<strong>Email:</strong>
							<p>${escapeHtml(user.email)}</p>
						</div>
						<div class="col-md-6">
							<strong>Phone:</strong>
							<p>${user.phone ? escapeHtml(user.phone) : '<span class="text-muted">Not provided</span>'}</p>
						</div>
						<div class="col-md-6">
							<strong>Role:</strong>
							<p><span class="badge text-bg-${user.role === 'teacher' ? 'info' : 'primary'}">${escapeHtml(user.role)}</span></p>
						</div>
						<div class="col-md-6">
							<strong>Status:</strong>
							<p><span class="badge text-bg-${user.status === 'active' ? 'success' : (user.status === 'blacklisted' ? 'danger' : 'warning')}">${escapeHtml(user.status)}</span></p>
						</div>
						<div class="col-md-6">
							<strong>Email Verified:</strong>
							<p><span class="badge text-bg-${user.email_verified ? 'success' : 'warning'}">${user.email_verified ? 'Yes' : 'No'}</span></p>
						</div>
						<div class="col-md-6">
							<strong>Registration Date:</strong>
							<p>${escapeHtml(user.created_at)}</p>
						</div>
						<div class="col-md-6">
							<strong>Uploaded Notes Count:</strong>
							<p><span class="badge text-bg-info">${user.notes_count || 0}</span></p>
						</div>
						<div class="col-md-6">
							<strong>Issued Books:</strong>
							<p><span class="badge text-bg-primary">${user.issued_count || 0}</span></p>
						</div>
						<div class="col-md-6">
							<strong>Returned Books:</strong>
							<p><span class="badge text-bg-success">${user.returned_count || 0}</span></p>
						</div>
						<div class="col-md-6">
							<strong>Trusted Devices:</strong>
							<p><span class="badge text-bg-secondary">${user.trusted_devices_count || 0}</span></p>
						</div>
					</div>
					${user.trusted_devices && user.trusted_devices.length > 0 ? `
						<hr>
						<h6>Trusted Devices:</h6>
						<ul class="list-group">
							${user.trusted_devices.map(device => `
								<li class="list-group-item d-flex justify-content-between align-items-center">
									<span>${escapeHtml(device.device_label || 'Unnamed Device')}</span>
									<small class="text-muted">Registered: ${escapeHtml(device.created_at)}</small>
								</li>
							`).join('')}
						</ul>
					` : ''}
				`;
			} else {
				content.innerHTML = `<div class="alert alert-danger">${escapeHtml(data.message || 'Failed to load user profile')}</div>`;
			}
		})
		.catch(error => {
			content.innerHTML = `<div class="alert alert-danger">Error loading user profile: ${escapeHtml(error.message)}</div>`;
		});
}

function escapeHtml(text) {
	const div = document.createElement('div');
	div.textContent = text;
	return div.innerHTML;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>