<?php
require_once __DIR__ . '/config.php';

if (current_user()) {
	// Already logged in; redirect to dashboard
	$role = $_SESSION['user']['role'];
	if ($role === 'admin') header('Location: /admin/admin_dashboard.php');
	elseif ($role === 'teacher') header('Location: /teacher/teacher_dashboard.php');
	else header('Location: /student/student_dashboard.php');
	exit;
}

 $error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$error = 'Invalid request. Please reload the page.';
	} else {
		$name = trim($_POST['name'] ?? '');
		$email = trim($_POST['email'] ?? '');
		$phone = trim($_POST['phone'] ?? '');
		$password = (string)($_POST['password'] ?? '');
		$role = trim($_POST['role'] ?? '');

		// Validate phone number (10 digits, optional country code)
		$phoneClean = preg_replace('/[^0-9+]/', '', $phone);
		if (!empty($phoneClean) && !preg_match('/^(\+?[0-9]{10,15})$/', $phoneClean)) {
			$error = 'Please enter a valid phone number (10-15 digits).';
		} elseif ($name === '' || $email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['student','teacher'], true)) {
			$error = 'Please fill all required fields correctly.';
		} else {
			// Check if email exists
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
					$success = 'Registration submitted. Check your email for OTP verification. Admin approval is required before you can borrow books.';
					if (!$otpStatus['success']) {
						$error = $otpStatus['message'];
						$success = '';
					}
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
				<h4 class="mb-3">Register</h4>
				<?php if ($error): ?>
					<div class="alert alert-danger alert-dismissible fade show" role="alert">
						<?php echo h($error); ?>
						<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
					</div>
				<?php endif; ?>
				<?php if ($success): ?>
					<div class="alert alert-success alert-dismissible fade show" role="alert">
						<?php echo h($success); ?>
						<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
					</div>
				<?php endif; ?>
				<form method="post" autocomplete="on">
					<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
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
					<button type="submit" class="btn btn-primary w-100 mb-2">Submit</button>
					<div class="text-center"><a href="/index.php">Back to Login</a></div>
					<p class="small text-muted mt-2 mb-0">We will send an OTP to your email for verification. Admin must activate the account before you can login.</p>
				</form>
			</div>
		</div>
	</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
// Show/Hide Password Toggle for Registration
document.addEventListener('DOMContentLoaded', function() {
	const toggleBtn = document.getElementById('toggleRegisterPassword');
	const passwordInput = document.getElementById('registerPassword');
	const toggleIcon = document.getElementById('toggleRegisterPasswordIcon');
	
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



