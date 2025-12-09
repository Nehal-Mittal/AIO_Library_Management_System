<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$uid = (int)$user['id'];

// FIX: avoid undefined array key warnings
$status = $user['status'] ?? 'pending';
$emailVerified = $user['email_verified'] ?? 0;


// Handle delete trusted device
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_device') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$error = 'Invalid request. Please refresh.';
	} else {
			$deviceId = (int)($_POST['device_id'] ?? 0);

			if ($deviceId > 0) {
					$stmt = mysqli_prepare($conn, "DELETE FROM user_fingerprints WHERE id=? AND user_id=?");
					mysqli_stmt_bind_param($stmt, 'ii', $deviceId, $uid);
					if (mysqli_stmt_execute($stmt)) {
							$success = 'Trusted device removed successfully.';
					} else {
							$error = 'Failed to remove device.';
					}
					mysqli_stmt_close($stmt);
			}
	}
}


// Handle update device label
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_label') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$error = 'Invalid request. Please refresh.';
	} else {
			$deviceId = (int)($_POST['device_id'] ?? 0);
			$label = trim($_POST['device_label'] ?? '');

			if ($deviceId > 0) {
					$stmt = mysqli_prepare($conn, "UPDATE user_fingerprints SET device_label=? WHERE id=? AND user_id=?");
					mysqli_stmt_bind_param($stmt, 'sii', $label, $deviceId, $uid);
					if (mysqli_stmt_execute($stmt)) {
							$success = 'Device label updated successfully.';
					} else {
							$error = 'Failed to update label.';
					}
					mysqli_stmt_close($stmt);
			}
	}
}


// Check if user has a registered fingerprint
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) c FROM user_fingerprints WHERE user_id=? AND is_active=1");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$hasFingerprint = (int)mysqli_fetch_assoc($res)['c'] > 0;
mysqli_stmt_close($stmt);


// Get all trusted devices
$devicesStmt = mysqli_prepare($conn, "SELECT id, fingerprint_hash, device_label, created_at, last_used_at, is_active 
                                      FROM user_fingerprints 
                                      WHERE user_id=? 
                                      ORDER BY last_used_at DESC, created_at DESC");
mysqli_stmt_bind_param($devicesStmt, 'i', $uid);
mysqli_stmt_execute($devicesStmt);
$devicesResult = mysqli_stmt_get_result($devicesStmt);
$devices = [];
while ($row = mysqli_fetch_assoc($devicesResult)) {
	$devices[] = $row;
}
mysqli_stmt_close($devicesStmt);

include __DIR__ . '/../includes/header.php';
?>


<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="card-title mb-2"><i class="bi bi-shield-check me-2"></i>Security Center</h4>
                <p class="text-muted">Link your device fingerprint for one-click login. Only active and OTP-verified accounts can enable this feature.</p>
                
                <!-- Account Status -->
                <ul class="list-group list-group-flush mb-4">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Account Status
                        <span class="badge text-bg-<?php echo $status === 'active' ? 'success' : 'warning'; ?>"><?php echo h($status); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Email Verified
                        <span class="badge text-bg-<?php echo $emailVerified ? 'success' : 'warning'; ?>"><?php echo $emailVerified ? 'Yes' : 'Pending'; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Fingerprint Linked
                        <span class="badge text-bg-<?php echo $hasFingerprint?'success':'secondary'; ?>"><?php echo $hasFingerprint?'Enabled':'Not set'; ?></span>
                    </li>
                </ul>

                <?php if ($status !== 'active' || !$emailVerified): ?>
                    <div class="alert alert-warning">Please complete email verification and wait for admin approval before setting up fingerprint login.</div>
                <?php endif; ?>

                <!-- Fingerprint Registration Button -->
                <div class="d-grid gap-2">
                    <button 
                        class="btn btn-primary"
                        id="fingerprintRegisterBtn"
                        data-token="<?php echo csrf_token(); ?>"
                        <?php echo ($status !== 'active' || !$emailVerified) ? 'disabled' : ''; ?>

                    >
                        <i class="bi bi-fingerprint me-2"></i><?php echo $hasFingerprint ? 'Re-register this device' : 'Register fingerprint on this device'; ?>
                    </button>
                    <p class="small text-muted mb-0">We use a privacy-friendly browser fingerprint (powered by FingerprintJS) to recognise your trusted devices. You can still log in with email + password anytime.</p>
                </div>

                <!-- Alert Placeholder -->
                <div class="alert alert-info mt-3 d-none" id="fingerprintRegisterAlert"></div>
            </div>
        </div>
        
        <!-- Trusted Devices Management -->
        <?php if (!empty($devices)): ?>
        <div class="card shadow-sm mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-device-hdd me-2"></i>My Trusted Devices</h5>
            </div>
            <div class="card-body">
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
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Device Label</th>
                                <th>Registered</th>
                                <th>Last Used</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($devices as $device): ?>
                                <tr>
                                    <td>
                                        <form method="post" class="d-inline" id="labelForm<?php echo $device['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                            <input type="hidden" name="action" value="update_label">
                                            <input type="hidden" name="device_id" value="<?php echo (int)$device['id']; ?>">
                                            <input type="text" name="device_label" class="form-control form-control-sm d-inline-block" 
                                                   value="<?php echo h($device['device_label'] ?: 'Unnamed Device'); ?>" 
                                                   style="width: 200px;"
                                                   onchange="document.getElementById('labelForm<?php echo $device['id']; ?>').submit();"
                                                   placeholder="Enter device name">
                                        </form>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($device['created_at'])); ?></td>
                                    <td>
                                        <?php echo $device['last_used_at'] ? date('M d, Y H:i', strtotime($device['last_used_at'])) : 'Never'; ?>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-<?php echo $device['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $device['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to remove this trusted device? You will need to register it again to use fingerprint login.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                            <input type="hidden" name="action" value="delete_device">
                                            <input type="hidden" name="device_id" value="<?php echo (int)$device['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> Remove
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<!-- FingerprintJS & AJAX Registration Script -->
<script src="https://cdn.jsdelivr.net/npm/@fingerprintjs/fingerprintjs@3/dist/fp.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('fingerprintRegisterBtn');
    const alertBox = document.getElementById('fingerprintRegisterAlert');

    btn?.addEventListener('click', async () => {
        btn.disabled = true;
        alertBox.classList.add('d-none');

        try {
            // Load FingerprintJS
            const fp = await FingerprintJS.load();
            const result = await fp.get();
            const fingerprint = result.visitorId;
            const csrfToken = btn.dataset.token;

            // Send fingerprint to backend
            const response = await fetch('/security/fingerprint_register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ fingerprint, csrf_token: csrfToken })
            });

            const data = await response.json();

            // Show response message
            alertBox.textContent = data.message;
            alertBox.classList.remove('d-none');
            alertBox.className = 'alert mt-3 ' + (data.success ? 'alert-success' : 'alert-danger');

        } catch (err) {
            console.error(err);
            alertBox.textContent = 'An error occurred while registering fingerprint.';
            alertBox.classList.remove('d-none');
            alertBox.className = 'alert mt-3 alert-danger';
        }

        btn.disabled = false;
    });
});
</script>
