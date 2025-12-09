<?php
require_once __DIR__ . '/config.php';

$emailMessage = '';
$resendMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';
	if (!validate_csrf($_POST['csrf_token'] ?? '')) {
		$emailMessage = $resendMessage = 'Invalid request. Please refresh the page.';
	} else {
		if ($action === 'verify_email') {
			$email = trim($_POST['email'] ?? '');
			$otp = trim($_POST['email_otp'] ?? '');
			$result = verify_email_code($conn, $email, $otp);
			$emailMessage = $result['message'];
		} elseif ($action === 'resend_otp') {
			$email = trim($_POST['email_resend'] ?? '');
			$user = $email ? find_user_by_email($conn, $email) : null;
			if (!$user) {
				$resendMessage = 'No account found for that email.';
			} else {
				$result = send_verification_codes($conn, (int)$user['id']);
				$resendMessage = $result['message'];
			}
		}
	}
}

include __DIR__ . '/includes/header.php';
?>

<div class="row g-4 justify-content-center">
	<div class="col-lg-6">
		<div class="card shadow-sm h-100">
			<div class="card-body">
				<h4 class="card-title mb-3">Verify Email</h4>
				<p class="text-muted">Enter the 6-digit code sent to your email address.</p>
				<?php if ($emailMessage): ?>
					<div class="alert alert-info alert-dismissible fade show" role="alert">
						<?php echo h($emailMessage); ?>
						<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
					</div>
				<?php endif; ?>
				<form method="post" autocomplete="off">
					<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
					<input type="hidden" name="action" value="verify_email">
					<div class="mb-3">
						<label class="form-label">Registered Email</label>
						<input type="email" name="email" class="form-control" required>
					</div>
					<div class="mb-3">
						<label class="form-label">OTP</label>
						<input type="text" name="email_otp" class="form-control" maxlength="6" minlength="6" required>
					</div>
					<button class="btn btn-primary w-100" type="submit">Verify Email</button>
				</form>
			</div>
		</div>
	</div>
	<div class="col-12 col-lg-10">
		<div class="card shadow-sm">
			<div class="card-body">
				<h5 class="card-title">Need a new OTP?</h5>
				<p class="text-muted">If your OTP expired or you never received it, request a fresh email OTP here.</p>
				<?php if ($resendMessage): ?>
					<div class="alert alert-info alert-dismissible fade show" role="alert">
						<?php echo h($resendMessage); ?>
						<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
					</div>
				<?php endif; ?>
				<form class="row g-2 align-items-end" method="post" autocomplete="off">
					<input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
					<input type="hidden" name="action" value="resend_otp">
					<div class="col-md-4">
						<label class="form-label">Registered Email</label>
						<input type="email" name="email_resend" class="form-control" required>
					</div>
					<div class="col-md-3">
						<button class="btn btn-outline-primary w-100" type="submit">Resend Email OTP</button>
					</div>
					<div class="col-md-5">
						<p class="small text-muted mb-0">Rate limited for your safety (<?php echo OTP_RESEND_SECONDS; ?> seconds between requests).</p>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

