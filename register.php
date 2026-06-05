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
$success = '';
$registered = false;
$formValues = [
	'name' => '',
	'email' => '',
	'phone' => '',
	'role' => '',
];
$emailPreverified = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$error = 'Invalid request. Please reload the page.';
	} else {
		$name = trim($_POST['name'] ?? '');
		$email = strtolower(trim($_POST['email'] ?? ''));
		$phone = trim($_POST['phone'] ?? '');
		$password = (string)($_POST['password'] ?? '');
		$role = trim($_POST['role'] ?? '');
		$formValues = compact('name', 'email', 'phone', 'role');
		$emailPreverified = is_registration_email_verified($email);

		$phoneClean = preg_replace('/[^0-9+]/', '', $phone);
		if (!is_registration_email_verified($email)) {
			$error = 'Please verify your email using the Verify button before submitting.';
		} elseif (!empty($phoneClean) && !preg_match('/^(\+?[0-9]{10,15})$/', $phoneClean)) {
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
				clear_registration_email_verification($email);
			} else {
				$hash = password_hash($password, PASSWORD_DEFAULT);
				$status = 'pending';
				$emailVerified = 1;
				$phoneValue = !empty($phoneClean) ? $phoneClean : null;
				$stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, phone, password, role, status, email_verified) VALUES (?,?,?,?,?,?,?)");
				mysqli_stmt_bind_param($stmt, 'ssssssi', $name, $email, $phoneValue, $hash, $role, $status, $emailVerified);
				if (mysqli_stmt_execute($stmt)) {
					clear_registration_email_verification($email);
					$registered = true;
					$success = 'Registration submitted successfully! Your email is verified and your account is pending admin approval.';
				} else {
					$error = 'Registration failed. Please try again.';
				}
				mysqli_stmt_close($stmt);
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
				<?php if ($registered): ?>
					<h4 class="mb-3">Registration Complete</h4>
					<div class="alert alert-success alert-dismissible fade show" role="alert">
						<?php echo h($success); ?>
						<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
					</div>
					<p class="text-muted mb-4">An administrator will review and activate your account. You can log in once approved.</p>
					<a href="/index.php" class="btn btn-primary w-100">Go to Login</a>
				<?php else: ?>
					<h4 class="mb-3">Register</h4>
					<?php if ($error): ?>
						<div class="alert alert-danger alert-dismissible fade show" role="alert">
							<?php echo h($error); ?>
							<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
						</div>
					<?php endif; ?>
					<form method="post" id="registerForm" autocomplete="on">
						<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
						<input type="hidden" name="action" value="register">
						<input type="hidden" name="email_verified_flag" id="emailVerifiedFlag" value="0">
						<div class="mb-3">
							<label class="form-label" for="registerName">Full Name</label>
							<input type="text" id="registerName" name="name" class="form-control" autocomplete="name" value="<?php echo h($formValues['name']); ?>" required>
						</div>
						<div class="mb-3">
							<label class="form-label" for="registerEmail">Email</label>
							<div class="input-group">
								<input type="email" id="registerEmail" name="email" class="form-control" autocomplete="email" value="<?php echo h($formValues['email']); ?>" required>
								<button type="button" class="btn btn-outline-primary" id="sendEmailOtpBtn">Verify</button>
								<span class="input-group-text text-success d-none" id="emailVerifiedTick" title="Email verified">
									<i class="bi bi-check-circle-fill"></i>
								</span>
							</div>
							<div id="otpSection" class="mt-2 d-none">
								<label class="form-label small text-muted mb-1" for="registerOtp">Enter OTP sent to your email</label>
								<div class="input-group">
									<input type="text" id="registerOtp" class="form-control" maxlength="6" minlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="6-digit OTP" autocomplete="one-time-code">
									<button type="button" class="btn btn-primary" id="confirmEmailOtpBtn">Verify</button>
								</div>
								<div id="otpStatus" class="form-text"></div>
							</div>
						</div>
						<div class="mb-3">
							<label class="form-label" for="registerPhone">Phone Number <span class="text-muted small">(Optional)</span></label>
							<input type="tel" id="registerPhone" name="phone" class="form-control" autocomplete="tel" value="<?php echo h($formValues['phone']); ?>" placeholder="e.g., 9876543210 or +919876543210">
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
								<option value="student" <?php echo $formValues['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
								<option value="teacher" <?php echo $formValues['role'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
							</select>
						</div>
						<button type="submit" class="btn btn-primary w-100 mb-2" id="registerSubmitBtn" disabled>Submit Registration</button>
						<div class="text-center"><a href="/index.php">Back to Login</a></div>
						<p class="small text-muted mt-2 mb-0">Verify your email first, then submit. Admin approval is required before you can log in.</p>
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
	const emailInput = document.getElementById('registerEmail');
	const sendOtpBtn = document.getElementById('sendEmailOtpBtn');
	const confirmOtpBtn = document.getElementById('confirmEmailOtpBtn');
	const otpSection = document.getElementById('otpSection');
	const otpInput = document.getElementById('registerOtp');
	const otpStatus = document.getElementById('otpStatus');
	const verifiedTick = document.getElementById('emailVerifiedTick');
	const submitBtn = document.getElementById('registerSubmitBtn');
	const verifiedFlag = document.getElementById('emailVerifiedFlag');
	const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

	let emailVerified = <?php echo $emailPreverified ? 'true' : 'false'; ?>;
	let verifiedEmail = <?php echo $emailPreverified ? json_encode($formValues['email']) : "''"; ?>;

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

	function setOtpStatus(message, type) {
		if (!otpStatus) return;
		otpStatus.textContent = message || '';
		otpStatus.className = 'form-text' + (type ? ' text-' + type : '');
	}

	function markEmailVerified() {
		emailVerified = true;
		verifiedEmail = emailInput.value.trim().toLowerCase();
		if (verifiedFlag) verifiedFlag.value = '1';
		if (submitBtn) submitBtn.disabled = false;
		if (verifiedTick) verifiedTick.classList.remove('d-none');
		if (sendOtpBtn) sendOtpBtn.classList.add('d-none');
		if (emailInput) emailInput.readOnly = true;
		if (otpSection) otpSection.classList.add('d-none');
		if (otpInput) otpInput.value = '';
		setOtpStatus('Email verified.', 'success');
	}

	function resetEmailVerification() {
		emailVerified = false;
		verifiedEmail = '';
		if (verifiedFlag) verifiedFlag.value = '0';
		if (submitBtn) submitBtn.disabled = true;
		if (verifiedTick) verifiedTick.classList.add('d-none');
		if (sendOtpBtn) {
			sendOtpBtn.classList.remove('d-none');
			sendOtpBtn.disabled = false;
		}
		if (emailInput) emailInput.readOnly = false;
		if (otpSection) otpSection.classList.add('d-none');
		if (otpInput) otpInput.value = '';
		setOtpStatus('');
	}

	if (emailVerified) {
		markEmailVerified();
	}

	if (emailInput) {
		emailInput.addEventListener('input', function() {
			const current = emailInput.value.trim().toLowerCase();
			if (emailVerified && current !== verifiedEmail) {
				resetEmailVerification();
			}
		});
	}

	async function postOtpAction(action, extra) {
		const formData = new FormData();
		formData.append('csrf_token', csrfToken);
		formData.append('action', action);
		formData.append('email', emailInput.value.trim());
		if (extra) {
			Object.keys(extra).forEach(function(key) {
				formData.append(key, extra[key]);
			});
		}
		const response = await fetch('/api/register_email_otp.php', {
			method: 'POST',
			body: formData
		});
		return response.json();
	}

	if (sendOtpBtn) {
		sendOtpBtn.addEventListener('click', async function() {
			const email = emailInput.value.trim();
			if (!email || !emailInput.checkValidity()) {
				emailInput.reportValidity();
				return;
			}
			if (emailVerified) return;

			sendOtpBtn.disabled = true;
			setOtpStatus('Sending OTP...', 'muted');

			try {
				const data = await postOtpAction('send_otp');
				if (data.success) {
					otpSection.classList.remove('d-none');
					setOtpStatus(data.message, 'success');
					otpInput.focus();
				} else {
					setOtpStatus(data.message || 'Failed to send OTP.', 'danger');
				}
			} catch (err) {
				setOtpStatus('Could not send OTP. Please try again.', 'danger');
			}

			sendOtpBtn.disabled = false;
		});
	}

	if (confirmOtpBtn) {
		confirmOtpBtn.addEventListener('click', async function() {
			const otp = otpInput.value.trim();
			if (!/^[0-9]{6}$/.test(otp)) {
				setOtpStatus('Please enter a valid 6-digit OTP.', 'danger');
				return;
			}

			confirmOtpBtn.disabled = true;
			setOtpStatus('Verifying...', 'muted');

			try {
				const data = await postOtpAction('verify_otp', { otp: otp });
				if (data.success) {
					markEmailVerified();
				} else {
					setOtpStatus(data.message || 'Verification failed.', 'danger');
				}
			} catch (err) {
				setOtpStatus('Could not verify OTP. Please try again.', 'danger');
			}

			confirmOtpBtn.disabled = false;
		});
	}

	if (otpInput) {
		otpInput.addEventListener('keydown', function(event) {
			if (event.key === 'Enter') {
				event.preventDefault();
				confirmOtpBtn?.click();
			}
		});
	}
});
</script>
