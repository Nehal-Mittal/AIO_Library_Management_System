<?php
require_once __DIR__ . '/config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$error = 'Invalid request. Please refresh.';
	} else {
		$email = isset($_POST['email']) ? trim($_POST['email']) : '';
		$password = isset($_POST['password']) ? (string)$_POST['password'] : '';
		if ($email === '' || $password === '') {
			$error = 'Please enter email and password.';
		} else {
			$stmt = mysqli_prepare($conn, "SELECT id, name, email, password, role, status, email_verified FROM users WHERE email = ? LIMIT 1");
			mysqli_stmt_bind_param($stmt, 's', $email);
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$user = mysqli_fetch_assoc($result);
			mysqli_stmt_close($stmt);

			if ($user && password_verify($password, $user['password'])) {
				if (!$user['email_verified']) {
					$error = 'Please complete email verification. <a href="/register.php" class="alert-link">Finish registration</a> or <a href="/verify_otp.php" class="alert-link">verify OTP</a>.';
				} elseif ($user['status'] !== 'active') {
					if ($user['status'] === 'blacklisted') {
						$error = 'Your account is blacklisted. Contact administration.';
					} elseif ($user['status'] === 'pending') {
						$error = 'Your email is verified. Your account is pending approval by admin.';
					} else {
						$error = 'Your account is not active.';
					}
				} else {
					$_SESSION['user'] = [
						'id' => (int)$user['id'],
						'name' => $user['name'],
						'email' => $user['email'],
						'role' => $user['role'],
					];
					
					// Send funny notification on login
					sendFunnyNotification($conn, (int)$user['id'], 'visit');
					
					// Get recommendations and notify if available
					$recs = getRecommendations($conn, (int)$user['id'], 20);
					if (count($recs) > 0) {
						$lastRecNotif = $_SESSION['last_recommendation_notif'] ?? null;
						$today = date('Y-m-d');
						if ($lastRecNotif !== $today) {
							sendFunnyNotification($conn, (int)$user['id'], 'recommendation', ['count' => count($recs)]);
							$_SESSION['last_recommendation_notif'] = $today;
						}
					}
					
					if ($user['role'] === 'admin') {
						header('Location: /admin/admin_dashboard.php');
					} elseif ($user['role'] === 'teacher') {
						header('Location: /teacher/teacher_dashboard.php');
					} else {
						header('Location: /student/student_dashboard.php');
					}
					exit;
				}
			} else {
				$error = 'Invalid email or password.';
			}
		}
	}
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="row justify-content-center">
	<div class="col-md-6 col-lg-5">
		<div class="card">
			<div class="card-body p-4">
				<h4 class="mb-3">Login</h4>
				<?php if ($error): ?>
					<div class="alert alert-danger alert-dismissible fade show" role="alert">
						<?php echo $error; ?>
						<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
					</div>
				<?php endif; ?>
				<form method="post" autocomplete="on" id="loginForm">
					<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
					<!-- Hidden username field for better password manager support -->
					<input type="text" name="username" autocomplete="username" style="position:absolute;left:-9999px;" tabindex="-1" aria-hidden="true">
					<div class="mb-3">
						<label class="form-label" for="loginEmail">Email</label>
						<input type="email" id="loginEmail" name="email" class="form-control" autocomplete="email" required>
					</div>
					<div class="mb-3">
						<label class="form-label" for="loginPassword">Password</label>
						<div class="input-group">
							<input type="password" id="loginPassword" name="password" class="form-control" autocomplete="current-password" required>
							<button type="button" class="btn btn-outline-secondary" id="togglePassword" aria-label="Show password">
								<i class="bi bi-eye" id="togglePasswordIcon"></i>
							</button>
						</div>
					</div>
					<button type="submit" class="btn btn-primary w-100 mb-2">Login</button>
					<button type="button" class="btn btn-outline-secondary w-100 mb-2" id="fingerprintLoginBtn">
						<i class="bi bi-fingerprint me-1"></i> Login with Fingerprint
					</button>
					<div class="text-center small text-muted mb-2" id="fingerprintStatus">
						Use fingerprint on devices where you already registered it.
					</div>
					<p class="small text-muted text-center mt-2">
						<i class="bi bi-info-circle me-1"></i>
						We use a privacy-friendly browser fingerprint to recognise your trusted devices.
					</p>
					<script>
					// Enhanced fingerprint login handler
					document.addEventListener('DOMContentLoaded', function() {
						const btn = document.getElementById('fingerprintLoginBtn');
						const status = document.getElementById('fingerprintStatus');
						
						if (!btn) return;
						
						// Check if FingerprintJS is available
						if (typeof FingerprintJS === 'undefined') {
							if (status) {
								status.textContent = 'Fingerprint library not loaded. Please refresh the page.';
								status.classList.add('text-danger');
							}
							btn.disabled = true;
							return;
						}
						
						btn.addEventListener('click', async function() {
							btn.disabled = true;
							if (status) {
								status.textContent = 'Scanning device...';
								status.classList.remove('text-danger', 'text-success');
								status.classList.add('text-info');
							}
							
							try {
								// Load FingerprintJS
								const fp = await FingerprintJS.load();
								const result = await fp.get();
								const fingerprint = result.visitorId;
								
								// Send to backend
								const response = await fetch('/security/fingerprint_login.php', {
									method: 'POST',
									headers: { 'Content-Type': 'application/json' },
									body: JSON.stringify({ fingerprint: fingerprint })
								});
								
								const data = await response.json();
								
								if (data.success) {
									if (status) {
										status.textContent = 'Login successful! Redirecting...';
										status.classList.remove('text-info', 'text-danger');
										status.classList.add('text-success');
									}
									setTimeout(() => {
										window.location.href = data.redirect || '/index.php';
									}, 500);
								} else {
									if (status) {
										status.textContent = data.message || 'Fingerprint login failed.';
										status.classList.remove('text-info', 'text-success');
										status.classList.add('text-danger');
									}
									btn.disabled = false;
								}
							} catch (error) {
								console.error('Fingerprint login error:', error);
								if (status) {
									status.textContent = 'Error: ' + (error.message || 'Failed to authenticate. Please try email login.');
									status.classList.remove('text-info', 'text-success');
									status.classList.add('text-danger');
								}
								btn.disabled = false;
							}
						});
					});
					</script>
					<div class="text-center">
						<a href="/register.php">New user? Register</a>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
// Show/Hide Password Toggle
document.addEventListener('DOMContentLoaded', function() {
	const toggleBtn = document.getElementById('togglePassword');
	const passwordInput = document.getElementById('loginPassword');
	const toggleIcon = document.getElementById('togglePasswordIcon');
	
	if (toggleBtn && passwordInput) {
		toggleBtn.addEventListener('click', function() {
			const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
			passwordInput.setAttribute('type', type);
			
			// Toggle icon
			if (toggleIcon) {
				toggleIcon.classList.toggle('bi-eye');
				toggleIcon.classList.toggle('bi-eye-slash');
			}
		});
	}
});
</script>


