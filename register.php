<?php
require_once __DIR__ . '/config.php';

if (current_user()) {
	$role = $_SESSION['user']['role'];
	if ($role === 'admin') header('Location: /admin/admin_dashboard.php');
	elseif ($role === 'teacher') header('Location: /teacher/teacher_dashboard.php');
	else header('Location: /student/student_dashboard.php');
	exit;
}

$error = '';
$info = '';
$success = '';
$step = 'form';
$verifyEmail = '';

if (!empty($_SESSION['pending_register_email'])) {
	$pendingUser = find_user_by_email($conn, $_SESSION['pending_register_email']);
	if ($pendingUser && !empty($pendingUser['email_verified'])) {
		unset($_SESSION['pending_register_email']);
		$step = 'complete';
	} elseif ($pendingUser) {
		$step = 'verify';
		$verifyEmail = $pendingUser['email'];
	} else {
		unset($_SESSION['pending_register_email']);
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$error = 'Invalid request. Please reload the page.';
	} else {
		$action = $_POST['action'] ?? 'register';

		if ($action === 'register') {
			$name = trim($_POST['name'] ?? '');
			$email = trim($_POST['email'] ?? '');
			$phone = trim($_POST['phone'] ?? '');
			$password = (string)($_POST['password'] ?? '');
			$role = trim($_POST['role'] ?? '');

			$phoneClean = preg_replace('/[^0-9+]/', '', $phone);
			if (!empty($phoneClean) && !preg_match('/^(\+?[0-9]{10,15})$/', $phoneClean)) {
				$error = 'Please enter a valid phone number (10-15 digits).';
			} elseif ($name === '' || $email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['student', 'teacher'], true)) {
				$error = 'Please fill all required fields correctly.';
			} else {
				$check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
				mysqli_stmt_bind_param($check, 's', $email);
				mysqli_stmt_execute($check);
				$res = mysqli_stmt_get_result($check);
				$exists = mysqli_fetch_assoc($res);
				mysqli_stmt_close($check);

				if ($exists) {
					$error = 'Email already registered.';
				} else {
					$hash = password_hash($password, PASSWORD_DEFAULT);
					$status = 'pending';
					$phoneValue = !empty($phoneClean) ? $phoneClean : null;
					$stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, phone, password, role, status) VALUES (?,?,?,?,?,?)");
					mysqli_stmt_bind_param($stmt, 'ssssss', $name, $email, $phoneValue, $hash, $role, $status);
					if (mysqli_stmt_execute($stmt)) {
						$newUserId = mysqli_insert_id($conn);
						$otpStatus = send_verification_codes($conn, $newUserId, true);
						$_SESSION['pending_register_email'] = $email;
						$step = 'verify';
						$verifyEmail = $email;
						if ($otpStatus['success']) {
							$info = 'Account created. Enter the 6-digit OTP sent to your email to complete registration.';
						} else {
							$error = $otpStatus['message'] . ' You can resend the OTP below.';
						}
					} else {
						$error = 'Registration failed. Please try again.';
					}
					mysqli_stmt_close($stmt);
				}
			}
		} elseif ($action === 'verify_email') {
			$email = trim($_POST['email'] ?? '');
			$otp = trim($_POST['email_otp'] ?? '');

			if ($email === '' || $otp === '') {
				$error = 'Please enter the OTP sent to your email.';
				$step = 'verify';
				$verifyEmail = $email ?: ($_SESSION['pending_register_email'] ?? '');
			} else {
				$result = verify_email_code($conn, $email, $otp);
				if ($result['success']) {
					unset($_SESSION['pending_register_email']);
					$step = 'complete';
					$success = 'Email verified successfully! Your account is pending admin approval. You can log in once the admin activates your account.';
				} else {
					$error = $result['message'];
					$step = 'verify';
					$verifyEmail = $email;
					$_SESSION['pending_register_email'] = $email;
				}
			}
		} elseif ($action === 'resend_otp') {
			$email = trim($_POST['email'] ?? $_SESSION['pending_register_email'] ?? '');
			$user = $email ? find_user_by_email($conn, $email) : null;
			if (!$user) {
				$error = 'No account found for that email.';
				$step = 'form';
				unset($_SESSION['pending_register_email']);
			} elseif (!empty($user['email_verified'])) {
				unset($_SESSION['pending_register_email']);
				$step = 'complete';
				$success = 'Your email is already verified. Please wait for admin approval before logging in.';
			} else {
				$result = send_verification_codes($conn, (int)$user['id']);
				$step = 'verify';
				$verifyEmail = $email;
				$_SESSION['pending_register_email'] = $email;
				if ($result['success']) {
					$info = $result['message'];
				} else {
					$error = $result['message'];
				}
			}
		}
	}
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="row justify-content-center">
	<div class="col-md-7 col-lg-6">
		<div class="card">
			<div class="card-body p-4">
				<?php if ($step === 'complete'): ?>
					<h4 class="mb-3">Registration Complete</h4>
					<?php if ($success): ?>
						<div class="alert alert-success alert-dismissible fade show" role="alert">
							<?php echo h($success); ?>
							<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
						</div>
					<?php endif; ?>
					<p class="text-muted mb-4">We have verified your email address. An administrator will review and activate your account shortly.</p>
					<a href="/index.php" class="btn btn-primary w-100">Go to Login</a>
				<?php elseif ($step === 'verify'): ?>
					<h4 class="mb-3">Verify Your Email</h4>
					<p class="text-muted">Enter the 6-digit code sent to <strong><?php echo h($verifyEmail); ?></strong></p>
					<?php if ($error): ?>
						<div class="alert alert-danger alert-dismissible fade show" role="alert">
							<?php echo h($error); ?>
							<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
						</div>
					<?php endif; ?>
					<?php if ($info): ?>
						<div class="alert alert-info alert-dismissible fade show" role="alert">
							<?php echo h($info); ?>
							<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
						</div>
					<?php endif; ?>
					<form method="post" autocomplete="off" class="mb-3">
						<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
						<input type="hidden" name="action" value="verify_email">
						<input type="hidden" name="email" value="<?php echo h($verifyEmail); ?>">
						<div class="mb-3">
							<label class="form-label" for="registerOtp">Email OTP</label>
							<input type="text" id="registerOtp" name="email_otp" class="form-control text-center fs-4 letter-spacing-2" maxlength="6" minlength="6" pattern="[0-9]{6}" inputmode="numeric" required autofocus>
						</div>
						<button type="submit" class="btn btn-primary w-100 mb-2">Verify Email</button>
					</form>
					<form method="post" class="text-center">
						<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
						<input type="hidden" name="action" value="resend_otp">
						<input type="hidden" name="email" value="<?php echo h($verifyEmail); ?>">
						<button type="submit" class="btn btn-link p-0">Resend OTP</button>
						<p class="small text-muted mb-0">You can request a new code every <?php echo OTP_RESEND_SECONDS; ?> seconds. OTP expires in <?php echo OTP_EXPIRY_MINUTES; ?> minutes.</p>
					</form>
				<?php else: ?>
					<h4 class="mb-3">Register</h4>
					<?php if ($error): ?>
						<div class="alert alert-danger alert-dismissible fade show" role="alert">
							<?php echo h($error); ?>
							<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
						</div>
					<?php endif; ?>
					<form method="post" autocomplete="on">
						<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
						<input type="hidden" name="action" value="register">
						<div class="mb-3">
							<label class="form-label" for="registerName">Full Name</label>
							<input type="text" id="registerName" name="name" class="form-control" autocomplete="name" required>
						</div>
						<div class="mb-3">
							<label class="form-label" for="registerEmail">Email</label>
							<input type="email" id="registerEmail" name="email" class="form-control" autocomplete="email" required>
						</div>
						<div class="mb-3">
							<label class="form-label" for="registerPhone">Phone Number <span class="text-muted small">(Optional)</span></label>
							<input type="tel" id="registerPhone" name="phone" class="form-control" autocomplete="tel" placeholder="e.g., 9876543210 or +919876543210">
							<small class="text-muted">10-15 digits with optional country code</small>
						</div>
						<div class="mb-3">
							<label class="form-label" for="registerPassword">Password</label>
							<div class="input-group">
								<input type="password" id="registerPassword" name="password" class="form-control" autocomplete="new-password" minlength="6" required>
								<button type="button" class="btn btn-outline-secondary" id="toggleRegisterPassword" aria-label="Show password">
									<i class="bi bi-eye" id="toggleRegisterPasswordIcon"></i>
								</button>
							</div>
						</div>
						<div class="mb-3">
							<label class="form-label">Register As</label>
							<select name="role" class="form-select" required>
								<option value="">Select role</option>
								<option value="student">Student</option>
								<option value="teacher">Teacher</option>
							</select>
						</div>
						<button type="submit" class="btn btn-primary w-100 mb-2">Continue to Email Verification</button>
						<div class="text-center"><a href="/index.php">Back to Login</a></div>
						<p class="small text-muted mt-2 mb-0">After you verify your email with OTP, your account will wait for admin approval before you can log in.</p>
					</form>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const toggleBtn = document.getElementById('toggleRegisterPassword');
	const passwordInput = document.getElementById('registerPassword');
	const toggleIcon = document.getElementById('toggleRegisterPasswordIcon');

	if (toggleBtn && passwordInput) {
		toggleBtn.addEventListener('click', function() {
			const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
			passwordInput.setAttribute('type', type);
			if (toggleIcon) {
				toggleIcon.classList.toggle('bi-eye');
				toggleIcon.classList.toggle('bi-eye-slash');
			}
		});
	}
});
</script>
