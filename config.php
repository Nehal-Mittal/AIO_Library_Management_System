<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = mysqli_connect('localhost', 'root', 'Mish@0408', 'library_db');
if (!$conn) {
	die('Connection failed: ' . mysqli_connect_error());
}
mysqli_set_charset($conn, 'utf8mb4');

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

date_default_timezone_set('Asia/Kolkata');

// Core constants
if (!defined('BASE_URL')) define('BASE_URL', '/');
if (!defined('LOAN_DAYS')) define('LOAN_DAYS', 14);
if (!defined('FINE_PER_DAY')) define('FINE_PER_DAY', 5.00);
if (!defined('FINE_INTEREST_RATE_PER_DAY')) define('FINE_INTEREST_RATE_PER_DAY', 0.00);
if (!defined('OTP_EXPIRY_MINUTES')) define('OTP_EXPIRY_MINUTES', 10);
if (!defined('OTP_RESEND_SECONDS')) define('OTP_RESEND_SECONDS', 60);
if (!defined('OTP_MAX_ATTEMPTS')) define('OTP_MAX_ATTEMPTS', 5);
if (!defined('MAX_STUDENT_ISSUES')) define('MAX_STUDENT_ISSUES', 2);
if (!defined('MAX_TEACHER_ISSUES')) define('MAX_TEACHER_ISSUES', 6);

// Paths
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('BOOK_UPLOAD_DIR', UPLOAD_DIR . '/books');
define('PROFILE_UPLOAD_DIR', UPLOAD_DIR . '/profiles');
define('LOG_DIR', __DIR__ . '/storage/logs');

foreach ([UPLOAD_DIR, BOOK_UPLOAD_DIR, PROFILE_UPLOAD_DIR, LOG_DIR] as $dir) {
	if (!is_dir($dir)) {
		mkdir($dir, 0775, true);
	}
}

// Lightweight autoload for PHPMailer (downloaded vendor files)
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

// ---------- Database helpers ----------
/**
 * Ensure a column exists on a table. Uses safe escaping for identifiers.
 *
 * Note: prepared statements for SHOW ... LIKE ? may fail on some setups,
 * so this uses a direct, escaped query and validates the table name.
 */
function ensure_column(mysqli $conn, string $table, string $column, string $definition): void {
	// Basic validation for table name to avoid injection via table identifier
	if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
		throw new InvalidArgumentException("Invalid table name: {$table}");
	}

	$tableEsc = mysqli_real_escape_string($conn, $table);
	$columnEsc = mysqli_real_escape_string($conn, $column);

	$sql = "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'";
	$res = mysqli_query($conn, $sql);
	$exists = $res ? mysqli_fetch_assoc($res) : false;
	if ($res) mysqli_free_result($res);

	if (!$exists) {
		// Use backticks for table/column identifiers and append the raw definition
		$ddl = "ALTER TABLE `{$tableEsc}` ADD COLUMN `{$columnEsc}` {$definition}";
		mysqli_query($conn, $ddl);
	}
}

function ensure_table(mysqli $conn, string $ddl): void {
	mysqli_query($conn, $ddl);
}

// Schema adjustments
ensure_column($conn, 'users', 'status', "ENUM('pending','active','blacklisted') NOT NULL DEFAULT 'pending'");
ensure_column($conn, 'users', 'email_verified', "TINYINT(1) NOT NULL DEFAULT 0");
ensure_column($conn, 'users', 'email_otp', "VARCHAR(255) DEFAULT NULL");
ensure_column($conn, 'users', 'email_otp_expires_at', "DATETIME DEFAULT NULL");
ensure_column($conn, 'users', 'otp_attempts', "TINYINT DEFAULT 0");
ensure_column($conn, 'users', 'otp_last_sent_at', "DATETIME DEFAULT NULL");
ensure_column($conn, 'users', 'fingerprint_token', "VARCHAR(255) DEFAULT NULL");
ensure_column($conn, 'users', 'fingerprint_registered_at', "DATETIME DEFAULT NULL");
ensure_column($conn, 'users', 'phone', "VARCHAR(20) DEFAULT NULL");
ensure_column($conn, 'users', 'profile_picture', "VARCHAR(255) DEFAULT NULL");

ensure_column($conn, 'user_fingerprints', 'device_label', "VARCHAR(120) DEFAULT NULL");
ensure_column($conn, 'user_fingerprints', 'last_used_at', "DATETIME DEFAULT NULL");
ensure_column($conn, 'user_fingerprints', 'is_active', "TINYINT(1) NOT NULL DEFAULT 1");

ensure_column($conn, 'uploaded_notes', 'subject', "VARCHAR(200) DEFAULT NULL");
ensure_column($conn, 'uploaded_notes', 'teacher_name', "VARCHAR(150) DEFAULT NULL");
ensure_column($conn, 'uploaded_notes', 'uploader_type', "ENUM('student','teacher','admin') DEFAULT NULL");

ensure_column($conn, 'books', 'category', "VARCHAR(120) DEFAULT NULL");
ensure_column($conn, 'books', 'genre', "VARCHAR(120) DEFAULT NULL");
ensure_column($conn, 'books', 'isbn', "VARCHAR(30) DEFAULT NULL");
ensure_column($conn, 'books', 'cover_image', "VARCHAR(255) DEFAULT NULL");
ensure_column($conn, 'books', 'description', "TEXT DEFAULT NULL");
ensure_column($conn, 'books', 'quantity', "INT NOT NULL DEFAULT 1");
ensure_column($conn, 'books', 'available_copies', "INT NOT NULL DEFAULT 1");
ensure_column($conn, 'books', 'tags', "VARCHAR(255) DEFAULT NULL");
ensure_column($conn, 'books', 'created_by', "INT DEFAULT NULL");

ensure_column($conn, 'issued_books', 'fine_status', "ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid'");
ensure_column($conn, 'issued_books', 'due_date', "DATE DEFAULT NULL");
ensure_column($conn, 'issued_books', 'notified_due', "TINYINT(1) NOT NULL DEFAULT 0");
ensure_column($conn, 'issued_books', 'notified_overdue', "TINYINT(1) NOT NULL DEFAULT 0");
ensure_column($conn, 'issued_books', 'fine_rate', "DECIMAL(10,2) NOT NULL DEFAULT 5.00");

ensure_table($conn, "CREATE TABLE IF NOT EXISTS book_reviews (
	id INT AUTO_INCREMENT PRIMARY KEY,
	book_id INT NOT NULL,
	user_id INT NOT NULL,
	rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
	review TEXT DEFAULT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uniq_book_user (book_id, user_id),
	FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

ensure_table($conn, "CREATE TABLE IF NOT EXISTS due_notifications (
	id INT AUTO_INCREMENT PRIMARY KEY,
	issued_book_id INT NOT NULL,
	notification_type ENUM('due_soon_2','due_soon_1','due','overdue') NOT NULL,
	notified_on DATE NOT NULL,
	sent_via VARCHAR(50) DEFAULT 'email',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uniq_notification (issued_book_id, notification_type, notified_on),
	FOREIGN KEY (issued_book_id) REFERENCES issued_books(id) ON DELETE CASCADE
)");

// Update existing due_notifications table to support new notification types
try {
	$alterResult = mysqli_query($conn, "ALTER TABLE due_notifications MODIFY COLUMN notification_type ENUM('due_soon_2','due_soon_1','due','overdue') NOT NULL");
	if (!$alterResult) {
		// If ALTER fails, it might be because the table doesn't exist yet or column already has the right type
		// This is fine, the table creation above will handle it
	}
} catch (Exception $e) {
	// Ignore errors - table might not exist or already be updated
}

ensure_table($conn, "CREATE TABLE IF NOT EXISTS book_categories (
	id INT AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(120) NOT NULL UNIQUE
)");

ensure_table($conn, "CREATE TABLE IF NOT EXISTS user_fingerprints (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	fingerprint_hash VARCHAR(255) NOT NULL UNIQUE,
	device_label VARCHAR(120) DEFAULT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	last_used_at DATETIME DEFAULT NULL,
	is_active TINYINT(1) NOT NULL DEFAULT 1,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

ensure_table($conn, "CREATE TABLE IF NOT EXISTS uploaded_notes (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	title VARCHAR(200) NOT NULL,
	description TEXT DEFAULT NULL,
	subject VARCHAR(200) DEFAULT NULL,
	teacher_name VARCHAR(150) DEFAULT NULL,
	uploader_type ENUM('student','teacher','admin') DEFAULT NULL,
	file_path VARCHAR(500) NOT NULL,
	file_type ENUM('image', 'pdf') NOT NULL,
	file_size INT NOT NULL,
	status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	INDEX idx_user_status (user_id, status),
	INDEX idx_status (status),
	INDEX idx_subject (subject),
	INDEX idx_teacher (teacher_name)
)");

ensure_table($conn, "CREATE TABLE IF NOT EXISTS notifications (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	title VARCHAR(200) NOT NULL,
	message TEXT NOT NULL,
	type ENUM('info','warning','success','danger','funny') NOT NULL DEFAULT 'info',
	is_read TINYINT(1) NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	INDEX idx_user_read (user_id, is_read),
	INDEX idx_created (created_at)
)");

ensure_table($conn, "CREATE TABLE IF NOT EXISTS search_logs (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	keyword VARCHAR(255) NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	INDEX idx_user_created (user_id, created_at)
)");

ensure_table($conn, "CREATE TABLE IF NOT EXISTS book_views (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	book_id INT NOT NULL,
	viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
	INDEX idx_user_book (user_id, book_id),
	INDEX idx_viewed_at (viewed_at)
)");

// Seed default categories if table empty
$catCountRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM book_categories");
$catCountRow = mysqli_fetch_assoc($catCountRes);
if ((int)$catCountRow['c'] === 0) {
	$defaultCats = ['Computer Science','Electronics','Mechanical','Civil','Mathematics','Fiction','Non Fiction'];
	$catStmt = mysqli_prepare($conn, "INSERT INTO book_categories (name) VALUES (?)");
	foreach ($defaultCats as $cat) {
		mysqli_stmt_bind_param($catStmt, 's', $cat);
		mysqli_stmt_execute($catStmt);
	}
	mysqli_stmt_close($catStmt);
}

// ---------- Utility helpers ----------
function current_user(): ?array {
	return $_SESSION['user'] ?? null;
}

function refresh_current_user(mysqli $conn): void {
	if (!isset($_SESSION['user']['id'])) {
		return;
	}
	$stmt = mysqli_prepare($conn, "SELECT id, name, email, role, status, email_verified FROM users WHERE id=? LIMIT 1");
	mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user']['id']);
	mysqli_stmt_execute($stmt);
	$res = mysqli_stmt_get_result($stmt);
	$user = mysqli_fetch_assoc($res);
	mysqli_stmt_close($stmt);
	if ($user) {
		$_SESSION['user'] = $user;
	}
}

function user_is_role(string $role): bool {
	return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === $role;
}

function h($value): string {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf_token'];
}

function validate_csrf(string $token): bool {
	return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function log_message(string $message): void {
	$line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
	file_put_contents(LOG_DIR . '/app.log', $line, FILE_APPEND);
}

function app_mailer(): PHPMailer {
	$mail = new PHPMailer(true);
	
	// Enable SMTP debugging (set to 0 for production, 2 for verbose debugging)
	$mail->SMTPDebug = 0;
	
	// Try to get SMTP settings from environment variables first, then fallback to defaults
	$smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
	$smtpUsername = getenv('SMTP_USERNAME') ?: '';
	$smtpPassword = getenv('SMTP_PASSWORD') ?: '';
	$smtpPort = (int)(getenv('SMTP_PORT') ?: 587);
	$smtpSecure = getenv('SMTP_SECURE') ?: 'tls'; // 'tls' or 'ssl'
	
	// Always use SMTP for better reliability
	$mail->isSMTP();
	$mail->Host = $smtpHost;
	$mail->SMTPAuth = true;
	$mail->Username = $smtpUsername;
	$mail->Password = $smtpPassword;
	
	// Set encryption based on port
	if ($smtpPort === 465) {
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
	} else {
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
	}
	
	$mail->Port = $smtpPort;
	
	// Additional SMTP options for better compatibility
	$mail->SMTPOptions = [
		'ssl' => [
			'verify_peer' => false,
			'verify_peer_name' => false,
			'allow_self_signed' => true
		]
	];
	
	$fromEmail = getenv('MAIL_FROM_ADDRESS') ?: ($smtpUsername ?: 'no-reply@library.local');
	$fromName = getenv('MAIL_FROM_NAME') ?: 'Library Management System';
	$mail->setFrom($fromEmail, $fromName);
	$mail->isHTML(true);
	$mail->CharSet = 'UTF-8';
	
	return $mail;
}

function send_email(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool {
	try {
		$mail = app_mailer();
		$mail->addAddress($to);
		$mail->Subject = $subject;
		$mail->Body = $htmlBody;
		$mail->AltBody = $textBody ?: strip_tags($htmlBody);
		$mail->send();
		log_message("Email sent successfully to: {$to} - Subject: {$subject}");
		return true;
	} catch (Exception $e) {
		$errorMsg = 'Email send failed to ' . $to . ': ' . $mail->ErrorInfo . ' | Exception: ' . $e->getMessage();
		log_message($errorMsg);
		return false;
	}
}


function generate_otp(): string {
	return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function hash_secret(string $value): string {
	return password_hash($value, PASSWORD_DEFAULT);
}

function verify_secret(string $value, ?string $hash): bool {
	return $hash ? password_verify($value, $hash) : false;
}

function compute_due_date(string $issueDate, ?string $dueDate = null): DateTime {
	if ($dueDate) {
		return new DateTime($dueDate);
	}
	$issue = new DateTime($issueDate);
	$issue->modify('+' . LOAN_DAYS . ' days');
	return $issue;
}

function compute_fine(string $issueDate, ?string $returnedAt, ?string $dueDate = null, float $rate = FINE_PER_DAY): float {
	$due = compute_due_date($issueDate, $dueDate);
	$endDate = $returnedAt ? new DateTime($returnedAt) : new DateTime('today');
	if ($endDate <= $due) {
		return 0.0;
	}
	$daysOverdue = (int)$due->diff($endDate)->format('%a');
	$baseFine = $daysOverdue * $rate;
	if (FINE_INTEREST_RATE_PER_DAY > 0) {
		$interest = $baseFine * FINE_INTEREST_RATE_PER_DAY * $daysOverdue;
		return round($baseFine + $interest, 2);
	}
	return round($baseFine, 2);
}

function user_issue_limit(string $role): int {
	return $role === 'teacher' ? MAX_TEACHER_ISSUES : MAX_STUDENT_ISSUES;
}

function open_issue_count(mysqli $conn, int $userId): int {
	$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM issued_books WHERE user_id=? AND return_date IS NULL");
	mysqli_stmt_bind_param($stmt, 'i', $userId);
	mysqli_stmt_execute($stmt);
	$res = mysqli_stmt_get_result($stmt);
	$row = mysqli_fetch_assoc($res);
	mysqli_stmt_close($stmt);
	return (int)$row['c'];
}

function can_issue_more(mysqli $conn, int $userId, string $role): bool {
	return open_issue_count($conn, $userId) < user_issue_limit($role);
}

function fingerprint_signature(string $raw): string {
	return hash('sha256', $raw);
}

function store_fingerprint(mysqli $conn, int $userId, string $rawFingerprint, ?string $label = null): bool {
	$hash = fingerprint_signature($rawFingerprint);
	$stmt = mysqli_prepare($conn, "INSERT INTO user_fingerprints (user_id, fingerprint_hash, device_label) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), device_label=VALUES(device_label)");
	mysqli_stmt_bind_param($stmt, 'iss', $userId, $hash, $label);
	$result = mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	$upd = mysqli_prepare($conn, "UPDATE users SET fingerprint_token=?, fingerprint_registered_at=NOW() WHERE id=?");
	mysqli_stmt_bind_param($upd, 'si', $hash, $userId);
	mysqli_stmt_execute($upd);
	mysqli_stmt_close($upd);
	return $result;
}

function find_user_by_fingerprint(mysqli $conn, string $rawFingerprint): ?array {
	$hash = fingerprint_signature($rawFingerprint);
	$stmt = mysqli_prepare($conn, "SELECT u.id, u.name, u.email, u.role, u.status, u.email_verified FROM user_fingerprints uf JOIN users u ON u.id=uf.user_id WHERE uf.fingerprint_hash=? LIMIT 1");
	mysqli_stmt_bind_param($stmt, 's', $hash);
	mysqli_stmt_execute($stmt);
	$res = mysqli_stmt_get_result($stmt);
	$user = mysqli_fetch_assoc($res);
	mysqli_stmt_close($stmt);
	return $user ?: null;
}

function find_user_by_id(mysqli $conn, int $userId): ?array {
	$stmt = mysqli_prepare($conn, "SELECT id, name, email, role, status, email_verified, email_otp, email_otp_expires_at, otp_attempts, otp_last_sent_at FROM users WHERE id=? LIMIT 1");
	mysqli_stmt_bind_param($stmt, 'i', $userId);
	mysqli_stmt_execute($stmt);
	$res = mysqli_stmt_get_result($stmt);
	$user = mysqli_fetch_assoc($res);
	mysqli_stmt_close($stmt);
	return $user ?: null;
}

function find_user_by_email(mysqli $conn, string $email): ?array {
	$stmt = mysqli_prepare($conn, "SELECT id, name, email, role, status, email_verified, email_otp, email_otp_expires_at, otp_attempts, otp_last_sent_at FROM users WHERE email=? LIMIT 1");
	mysqli_stmt_bind_param($stmt, 's', $email);
	mysqli_stmt_execute($stmt);
	$res = mysqli_stmt_get_result($stmt);
	$user = mysqli_fetch_assoc($res);
	mysqli_stmt_close($stmt);
	return $user ?: null;
}


function can_send_otp_again(?string $lastSent, bool $force): bool {
	if ($force || !$lastSent) {
		return true;
	}
	$seconds = time() - strtotime($lastSent);
	return $seconds >= OTP_RESEND_SECONDS;
}

function send_verification_codes(mysqli $conn, int $userId, bool $force = false): array {
    $user = find_user_by_id($conn, $userId);
    if (!$user) return ['success' => false, 'message' => 'User not found.'];

    // Rate limit check
    if (!can_send_otp_again($user['otp_last_sent_at'], $force)) {
        return ['success' => false, 'message' => 'Please wait before requesting a new OTP.'];
    }

    $emailOtp = generate_otp();
    $expires = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);

    $stmt = mysqli_prepare($conn, "
        UPDATE users SET
            email_otp=?, email_otp_expires_at=?,
            otp_attempts=0, otp_last_sent_at=NOW()
        WHERE id=?
    ");
    mysqli_stmt_bind_param($stmt, 'ssi', $emailOtp, $expires, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Send email OTP with better formatting
    $emailSubject = "Your Email OTP Code - Library Management System";
    $emailBody = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <h2 style='color: #333;'>Library Management System</h2>
        <p>Hello,</p>
        <p>Your One-Time Password (OTP) for email verification is:</p>
        <div style='background-color: #f4f4f4; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px;'>
            {$emailOtp}
        </div>
        <p>This OTP will expire in " . OTP_EXPIRY_MINUTES . " minutes.</p>
        <p style='color: #666; font-size: 12px;'>If you didn't request this code, please ignore this email.</p>
        <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
        <p style='color: #999; font-size: 11px;'>This is an automated message. Please do not reply.</p>
    </div>
    ";
    
    $emailResult = send_email($user['email'], $emailSubject, $emailBody);

    if (!$emailResult) {
        return ['success' => false, 'message' => 'Failed to send Email OTP.'];
    }

    return ['success' => true, 'message' => 'Email OTP sent successfully.'];
}

function verify_email_code(mysqli $conn, string $email, string $otp): array {
    $user = find_user_by_email($conn, $email);
    if (!$user) return ['success' => false, 'message' => 'No account found with this email.'];

    if (!$user['email_otp'] || strtotime($user['email_otp_expires_at']) < time()) {
        return ['success' => false, 'message' => 'OTP expired or not found.'];
    }

    if ($user['email_otp'] !== $otp) {
        increment_otp_attempts($conn, $user['id']);
        return ['success' => false, 'message' => 'Incorrect OTP.'];
    }

    $stmt = mysqli_prepare($conn, "
        UPDATE users SET email_verified=1, email_otp=NULL, email_otp_expires_at=NULL
        WHERE id=?
    ");
    mysqli_stmt_bind_param($stmt, 'i', $user['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    refresh_current_user($conn);
    return ['success' => true, 'message' => 'Email verified successfully.'];
}

function registration_otp_session_key(string $email): string {
	return strtolower(trim($email));
}

function build_otp_email_body(string $otp): string {
	return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <h2 style='color: #333;'>Library Management System</h2>
        <p>Hello,</p>
        <p>Your One-Time Password (OTP) for email verification is:</p>
        <div style='background-color: #f4f4f4; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px;'>
            {$otp}
        </div>
        <p>This OTP will expire in " . OTP_EXPIRY_MINUTES . " minutes.</p>
        <p style='color: #666; font-size: 12px;'>If you didn't request this code, please ignore this email.</p>
        <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
        <p style='color: #999; font-size: 11px;'>This is an automated message. Please do not reply.</p>
    </div>
    ";
}

function send_registration_email_otp(mysqli $conn, string $email, bool $force = false): array {
	$email = strtolower(trim($email));
	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		return ['success' => false, 'message' => 'Please enter a valid email address.'];
	}
	if (find_user_by_email($conn, $email)) {
		return ['success' => false, 'message' => 'Email already registered.'];
	}

	if (!isset($_SESSION['registration_otps'])) {
		$_SESSION['registration_otps'] = [];
	}

	$key = registration_otp_session_key($email);
	$existing = $_SESSION['registration_otps'][$key] ?? null;
	if ($existing && !can_send_otp_again($existing['sent_at'] ?? null, $force)) {
		return ['success' => false, 'message' => 'Please wait before requesting a new OTP.'];
	}

	$otp = generate_otp();
	$_SESSION['registration_otps'][$key] = [
		'otp' => $otp,
		'expires_at' => time() + OTP_EXPIRY_MINUTES * 60,
		'sent_at' => date('Y-m-d H:i:s'),
		'attempts' => 0,
		'verified' => false,
	];
	unset($_SESSION['verified_registration_emails'][$key]);

	$emailResult = send_email(
		$email,
		'Your Email OTP Code - Library Management System',
		build_otp_email_body($otp)
	);

	if (!$emailResult) {
		unset($_SESSION['registration_otps'][$key]);
		return ['success' => false, 'message' => 'Failed to send Email OTP.'];
	}

	return ['success' => true, 'message' => 'OTP sent to your email.'];
}

function verify_registration_email_otp(string $email, string $otp): array {
	$email = strtolower(trim($email));
	$key = registration_otp_session_key($email);
	$data = $_SESSION['registration_otps'][$key] ?? null;

	if (!$data) {
		return ['success' => false, 'message' => 'OTP expired or not found. Click Verify to send a new code.'];
	}
	if ($data['expires_at'] < time()) {
		unset($_SESSION['registration_otps'][$key]);
		return ['success' => false, 'message' => 'OTP expired. Click Verify to send a new code.'];
	}
	if (($data['attempts'] ?? 0) >= OTP_MAX_ATTEMPTS) {
		return ['success' => false, 'message' => 'Too many incorrect attempts. Click Verify to request a new OTP.'];
	}
	if ($data['otp'] !== $otp) {
		$_SESSION['registration_otps'][$key]['attempts'] = ($data['attempts'] ?? 0) + 1;
		return ['success' => false, 'message' => 'Incorrect OTP.'];
	}

	if (!isset($_SESSION['verified_registration_emails'])) {
		$_SESSION['verified_registration_emails'] = [];
	}
	$_SESSION['registration_otps'][$key]['verified'] = true;
	$_SESSION['verified_registration_emails'][$key] = true;

	return ['success' => true, 'message' => 'Email verified successfully.'];
}

function is_registration_email_verified(string $email): bool {
	$key = registration_otp_session_key($email);
	return !empty($_SESSION['verified_registration_emails'][$key]);
}

function clear_registration_email_verification(string $email): void {
	$key = registration_otp_session_key($email);
	unset($_SESSION['registration_otps'][$key], $_SESSION['verified_registration_emails'][$key]);
}


function increment_otp_attempts(mysqli $conn, int $userId): void {
	$stmt = mysqli_prepare($conn, "UPDATE users SET otp_attempts = otp_attempts + 1 WHERE id=?");
	mysqli_stmt_bind_param($stmt, 'i', $userId);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
}

/**
 * Get trending books (most issued in last 30 days)
 */
function get_trending_books(mysqli $conn, int $limit = 5): array {
	$sql = "SELECT b.id, b.title, b.author, b.category, b.cover_image, b.description,
				COALESCE(AVG(br.rating), 0) AS avg_rating,
				COUNT(DISTINCT br.id) AS review_count,
				COUNT(DISTINCT ib.id) AS issue_count
			FROM books b
			LEFT JOIN book_reviews br ON br.book_id = b.id
			LEFT JOIN issued_books ib ON ib.book_id = b.id AND ib.issue_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			WHERE b.available_copies > 0
			GROUP BY b.id
			ORDER BY issue_count DESC, avg_rating DESC, review_count DESC
			LIMIT ?";
	$stmt = mysqli_prepare($conn, $sql);
	mysqli_stmt_bind_param($stmt, 'i', $limit);
	mysqli_stmt_execute($stmt);
	$res = mysqli_stmt_get_result($stmt);
	$books = [];
	while ($row = mysqli_fetch_assoc($res)) {
		$books[] = $row;
	}
	mysqli_stmt_close($stmt);
	return $books;
}

/**
 * Find similar users (collaborative filtering)
 * Users who issued the same books as the current user
 */
function find_similar_users(mysqli $conn, int $userId, int $limit = 10): array {
	$sql = "SELECT DISTINCT ib2.user_id, COUNT(DISTINCT ib2.book_id) AS common_books
			FROM issued_books ib1
			INNER JOIN issued_books ib2 ON ib1.book_id = ib2.book_id AND ib1.user_id != ib2.user_id
			WHERE ib1.user_id = ?
			GROUP BY ib2.user_id
			HAVING common_books >= 1
			ORDER BY common_books DESC
			LIMIT ?";
	$stmt = mysqli_prepare($conn, $sql);
	mysqli_stmt_bind_param($stmt, 'ii', $userId, $limit);
	mysqli_stmt_execute($stmt);
	$res = mysqli_stmt_get_result($stmt);
	$similarUsers = [];
	while ($row = mysqli_fetch_assoc($res)) {
		$similarUsers[] = (int)$row['user_id'];
	}
	mysqli_stmt_close($stmt);
	return $similarUsers;
}

/**
 * Get books liked by similar users (collaborative filtering)
 */
function get_books_from_similar_users(mysqli $conn, int $userId, int $limit = 5): array {
	$similarUsers = find_similar_users($conn, $userId, 10);
	if (empty($similarUsers)) {
		return [];
	}
	
	$placeholders = implode(',', array_fill(0, count($similarUsers), '?'));
	$sql = "SELECT b.id, b.title, b.author, b.category, b.cover_image, b.description,
				COALESCE(AVG(br.rating), 0) AS avg_rating,
				COUNT(DISTINCT br.id) AS review_count,
				COUNT(DISTINCT ib.id) AS issue_count
			FROM books b
			LEFT JOIN book_reviews br ON br.book_id = b.id
			LEFT JOIN issued_books ib ON ib.book_id = b.id AND ib.user_id IN ($placeholders)
			WHERE b.available_copies > 0
				AND b.id NOT IN (
					SELECT DISTINCT book_id FROM issued_books WHERE user_id = ?
				)
			GROUP BY b.id
			HAVING issue_count > 0
			ORDER BY issue_count DESC, avg_rating DESC
			LIMIT ?";
	
	$params = array_merge($similarUsers, [$userId, $limit]);
	$types = str_repeat('i', count($similarUsers)) . 'ii'; // Fixed: Only 2 more parameters (userId and limit)
	$stmt = mysqli_prepare($conn, $sql);
	mysqli_stmt_bind_param($stmt, $types, ...$params);
	mysqli_stmt_execute($stmt);
	$res = mysqli_stmt_get_result($stmt);
	$books = [];
	while ($row = mysqli_fetch_assoc($res)) {
		$books[] = $row;
	}
	mysqli_stmt_close($stmt);
	return $books;
}

/**
 * Get book recommendations based on user activity
 * Implements multiple recommendation strategies
 */
function getRecommendations(mysqli $conn, int $userId, int $limit = 20): array {
	return recommended_books($conn, $userId, $limit);
}

/**
 * Enhanced book recommendations with multiple strategies
 */
function recommended_books(mysqli $conn, int $userId, int $limit = 10): array {
	$recommendations = [];
	$bookIds = [];
	
	// Get user's already issued book IDs
	$issuedStmt = mysqli_prepare($conn, "SELECT DISTINCT book_id FROM issued_books WHERE user_id=?");
	mysqli_stmt_bind_param($issuedStmt, 'i', $userId);
	mysqli_stmt_execute($issuedStmt);
	$issuedRes = mysqli_stmt_get_result($issuedStmt);
	$issuedBookIds = [];
	while ($row = mysqli_fetch_assoc($issuedRes)) {
		$issuedBookIds[] = (int)$row['book_id'];
	}
	mysqli_stmt_close($issuedStmt);
	$issuedPlaceholder = empty($issuedBookIds) ? '0' : implode(',', $issuedBookIds);
	
	// Strategy 1: Based on user's borrowed genres/categories
	$genres = [];
	$categories = [];
	$stmt = mysqli_prepare($conn, "SELECT DISTINCT b.genre, b.category FROM issued_books ib JOIN books b ON b.id=ib.book_id WHERE ib.user_id=? AND (b.genre IS NOT NULL OR b.category IS NOT NULL)");
	mysqli_stmt_bind_param($stmt, 'i', $userId);
	mysqli_stmt_execute($stmt);
	$res = mysqli_stmt_get_result($stmt);
	while ($row = mysqli_fetch_assoc($res)) {
		if (!empty($row['genre'])) {
			$genres[] = $row['genre'];
		}
		if (!empty($row['category'])) {
			$categories[] = $row['category'];
		}
	}
	mysqli_stmt_close($stmt);
	
	// Also get genres from authors
	$authors = [];
	$stmt = mysqli_prepare($conn, "SELECT DISTINCT b.author FROM issued_books ib JOIN books b ON b.id=ib.book_id WHERE ib.user_id=? AND b.author IS NOT NULL LIMIT 5");
	mysqli_stmt_bind_param($stmt, 'i', $userId);
	mysqli_stmt_execute($stmt);
	$res = mysqli_stmt_get_result($stmt);
	while ($row = mysqli_fetch_assoc($res)) {
		if (!empty($row['author'])) {
			$authors[] = $row['author'];
		}
	}
	mysqli_stmt_close($stmt);
	
	// Strategy 1A: Books from same genres
	if (!empty($genres)) {
		$escaped = array_map(static fn($g) => "'" . mysqli_real_escape_string($conn, $g) . "'", array_unique($genres));
		$sql = "SELECT b.id, b.title, b.author, b.category, b.genre, b.cover_image, b.description,
					COALESCE(AVG(br.rating),0) AS avg_rating, COUNT(br.id) AS review_count
				FROM books b
				LEFT JOIN book_reviews br ON br.book_id=b.id
				WHERE b.available_copies > 0 AND b.genre IN (" . implode(',', $escaped) . ")
					AND b.id NOT IN ({$issuedPlaceholder})
				GROUP BY b.id
				ORDER BY avg_rating DESC, review_count DESC
				LIMIT 5";
		$result = mysqli_query($conn, $sql);
		while ($row = mysqli_fetch_assoc($result)) {
			if (!in_array($row['id'], $bookIds) && count($recommendations) < $limit) {
				$recommendations[] = $row;
				$bookIds[] = $row['id'];
			}
		}
	}
	
	// Strategy 1B: Books from same categories
	if (!empty($categories) && count($recommendations) < $limit) {
		$escaped = array_map(static fn($cat) => "'" . mysqli_real_escape_string($conn, $cat) . "'", array_unique($categories));
		$sql = "SELECT b.id, b.title, b.author, b.category, b.genre, b.cover_image, b.description,
					COALESCE(AVG(br.rating),0) AS avg_rating, COUNT(br.id) AS review_count
				FROM books b
				LEFT JOIN book_reviews br ON br.book_id=b.id
				WHERE b.available_copies > 0 AND b.category IN (" . implode(',', $escaped) . ")
					AND b.id NOT IN ({$issuedPlaceholder})
				GROUP BY b.id
				ORDER BY avg_rating DESC, review_count DESC
				LIMIT 5";
		$result = mysqli_query($conn, $sql);
		while ($row = mysqli_fetch_assoc($result)) {
			if (!in_array($row['id'], $bookIds) && count($recommendations) < $limit) {
				$recommendations[] = $row;
				$bookIds[] = $row['id'];
			}
		}
	}
	
	// Strategy 1C: Books from same authors
	if (!empty($authors) && count($recommendations) < $limit) {
		$escaped = array_map(static fn($a) => "'" . mysqli_real_escape_string($conn, $a) . "'", array_unique($authors));
		$sql = "SELECT b.id, b.title, b.author, b.category, b.genre, b.cover_image, b.description,
					COALESCE(AVG(br.rating),0) AS avg_rating, COUNT(br.id) AS review_count
				FROM books b
				LEFT JOIN book_reviews br ON br.book_id=b.id
				WHERE b.available_copies > 0 AND b.author IN (" . implode(',', $escaped) . ")
					AND b.id NOT IN ({$issuedPlaceholder})
				GROUP BY b.id
				ORDER BY avg_rating DESC, review_count DESC
				LIMIT 5";
		$result = mysqli_query($conn, $sql);
		while ($row = mysqli_fetch_assoc($result)) {
			if (!in_array($row['id'], $bookIds) && count($recommendations) < $limit) {
				$recommendations[] = $row;
				$bookIds[] = $row['id'];
			}
		}
	}
	
	// Strategy 2: Based on user search history
	if (count($recommendations) < $limit) {
		$stmt = mysqli_prepare($conn, "
			SELECT keyword, MAX(created_at) AS max_created_at
			FROM search_logs
			WHERE user_id=? AND keyword <> ''
			GROUP BY keyword
			ORDER BY max_created_at DESC
			LIMIT 10
		");
		
		mysqli_stmt_bind_param($stmt, 'i', $userId);
		mysqli_stmt_execute($stmt);
		$res = mysqli_stmt_get_result($stmt);
		
		$searchKeywords = [];
		while ($row = mysqli_fetch_assoc($res)) {
			if (!empty($row['keyword'])) {
				$searchKeywords[] = $row['keyword'];
			}
		}
		mysqli_stmt_close($stmt);
		
		if (!empty($searchKeywords)) {
			$conditions = [];
			foreach ($searchKeywords as $keyword) {
				$escaped = mysqli_real_escape_string($conn, $keyword);
				$conditions[] = "(b.title LIKE '%{$escaped}%' OR b.author LIKE '%{$escaped}%' OR b.description LIKE '%{$escaped}%')";
			}
			if (!empty($conditions)) {
				$where = "b.available_copies > 0 AND (" . implode(' OR ', $conditions) . ") AND b.id NOT IN ({$issuedPlaceholder})";
				$sql = "SELECT b.id, b.title, b.author, b.category, b.genre, b.cover_image, b.description,
							COALESCE(AVG(br.rating),0) AS avg_rating, COUNT(br.id) AS review_count
						FROM books b
						LEFT JOIN book_reviews br ON br.book_id=b.id
						WHERE {$where}
						GROUP BY b.id
						ORDER BY avg_rating DESC, review_count DESC
						LIMIT 5";
				$result = mysqli_query($conn, $sql);
				while ($row = mysqli_fetch_assoc($result)) {
					if (!in_array($row['id'], $bookIds) && count($recommendations) < $limit) {
						$recommendations[] = $row;
						$bookIds[] = $row['id'];
					}
				}
			}
		}
	}
	
	// Strategy 3: Books frequently viewed by user
	if (count($recommendations) < $limit) {
		$stmt = mysqli_prepare($conn, "SELECT bv.book_id, COUNT(*) AS view_count FROM book_views bv WHERE bv.user_id=? AND bv.viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY bv.book_id HAVING view_count >= 2 ORDER BY view_count DESC LIMIT 5");
		mysqli_stmt_bind_param($stmt, 'i', $userId);
		mysqli_stmt_execute($stmt);
		$res = mysqli_stmt_get_result($stmt);
		$viewedBookIds = [];
		while ($row = mysqli_fetch_assoc($res)) {
			$viewedBookIds[] = (int)$row['book_id'];
		}
		mysqli_stmt_close($stmt);
		
		if (!empty($viewedBookIds)) {
			$viewedPlaceholder = implode(',', $viewedBookIds);
			$sql = "SELECT b.id, b.title, b.author, b.category, b.genre, b.cover_image, b.description,
						COALESCE(AVG(br.rating),0) AS avg_rating, COUNT(br.id) AS review_count
					FROM books b
					LEFT JOIN book_reviews br ON br.book_id=b.id
					WHERE b.available_copies > 0 AND b.id IN ({$viewedPlaceholder})
						AND b.id NOT IN ({$issuedPlaceholder})
					GROUP BY b.id
					ORDER BY avg_rating DESC
					LIMIT 5";
			$result = mysqli_query($conn, $sql);
			while ($row = mysqli_fetch_assoc($result)) {
				if (!in_array($row['id'], $bookIds) && count($recommendations) < $limit) {
					$recommendations[] = $row;
					$bookIds[] = $row['id'];
				}
			}
		}
	}
	
	// Strategy 4: Collaborative filtering - books from similar users
	$similarBooks = get_books_from_similar_users($conn, $userId, 5);
	foreach ($similarBooks as $book) {
		if (!in_array($book['id'], $bookIds) && count($recommendations) < $limit) {
			$recommendations[] = $book;
			$bookIds[] = $book['id'];
		}
	}
	
	// Strategy 5: Trending books (most issued last 30 days)
	$trending = get_trending_books($conn, 5);
	foreach ($trending as $book) {
		if (!in_array($book['id'], $bookIds) && count($recommendations) < $limit) {
			$recommendations[] = $book;
			$bookIds[] = $book['id'];
		}
	}
	
	// Strategy 4: High-rated books (fill remaining slots)
	$remaining = $limit - count($recommendations);
	if ($remaining > 0) {
		$sql = "SELECT b.id, b.title, b.author, b.category, b.cover_image, b.description,
					COALESCE(AVG(br.rating),0) AS avg_rating, COUNT(br.id) AS review_count
				FROM books b
				LEFT JOIN book_reviews br ON br.book_id=b.id
				WHERE b.available_copies > 0
					AND b.id NOT IN (" . (empty($bookIds) ? '0' : implode(',', $bookIds)) . ")
				GROUP BY b.id
				HAVING avg_rating >= 4.0 AND review_count >= 2
				ORDER BY avg_rating DESC, review_count DESC
				LIMIT ?";
		$stmt = mysqli_prepare($conn, $sql);
		mysqli_stmt_bind_param($stmt, 'i', $remaining);
		mysqli_stmt_execute($stmt);
		$res = mysqli_stmt_get_result($stmt);
		while ($row = mysqli_fetch_assoc($res)) {
			if (count($recommendations) < $limit) {
				$recommendations[] = $row;
			}
		}
		mysqli_stmt_close($stmt);
	}
	
	// Strategy 5: Random surprise book (if we still have space)
	if (count($recommendations) < $limit) {
		$sql = "SELECT b.id, b.title, b.author, b.category, b.cover_image, b.description,
					COALESCE(AVG(br.rating),0) AS avg_rating, COUNT(br.id) AS review_count
				FROM books b
				LEFT JOIN book_reviews br ON br.book_id=b.id
				WHERE b.available_copies > 0
					AND b.id NOT IN (" . (empty($bookIds) ? '0' : implode(',', $bookIds)) . ")
				GROUP BY b.id
				ORDER BY RAND()
				LIMIT 1";
		$result = mysqli_query($conn, $sql);
		if ($row = mysqli_fetch_assoc($result)) {
			$recommendations[] = $row;
		}
	}
	
	return array_slice($recommendations, 0, $limit);
}

/**
 * Generate funny notification messages for book recommendations
 */
function get_funny_recommendation_message(): string {
	$messages = [
		"Your brain might like these books. Your professor definitely will 😆",
		"These books got 5 stars. You got… let's not talk about it 🤣",
		"Everyone is reading this book… don't be last again 😂",
		"A wild book appears! Catch it before it escapes 😁",
		"Books recommended for your taste… and no, we didn't judge you.",
		"Hot take: These books are actually good. Shocking, I know! 🔥",
		"Your reading history says you're smart. These books say you're smarter 📚",
		"Warning: These books may cause excessive knowledge. Read at your own risk! ⚠️",
		"Plot twist: You're going to love these books. We called it! 🎭",
		"These books are trending harder than your last social media post 📈"
	];
	return $messages[array_rand($messages)];
}

/**
 * Create funny notification when new recommendations are ready
 */
function notify_new_recommendations(mysqli $conn, int $userId, int $count): void {
	$message = get_funny_recommendation_message();
	$title = "New Book Recommendations Available!";
	$fullMessage = "We found {$count} amazing books just for you! " . $message;
	create_notification($conn, $userId, $title, $fullMessage, 'funny');
}

function create_notification(mysqli $conn, int $userId, string $title, string $message, string $type = 'info'): bool {
	$stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
	mysqli_stmt_bind_param($stmt, 'isss', $userId, $title, $message, $type);
	$result = mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	return $result;
}

function get_unread_notification_count(mysqli $conn, int $userId): int {
	$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM notifications WHERE user_id=? AND is_read=0");
	mysqli_stmt_bind_param($stmt, 'i', $userId);
	mysqli_stmt_execute($stmt);
	$res = mysqli_stmt_get_result($stmt);
	$row = mysqli_fetch_assoc($res);
	mysqli_stmt_close($stmt);
	return (int)$row['c'];
}

function get_notifications(mysqli $conn, int $userId, int $limit = 50, bool $unreadOnly = false): array {
	$sql = "SELECT id, title, message, type, is_read, created_at FROM notifications WHERE user_id=?";
	if ($unreadOnly) {
		$sql .= " AND is_read=0";
	}
	$sql .= " ORDER BY created_at DESC LIMIT ?";
	$stmt = mysqli_prepare($conn, $sql);
	mysqli_stmt_bind_param($stmt, 'ii', $userId, $limit);
	mysqli_stmt_execute($stmt);
	$res = mysqli_stmt_get_result($stmt);
	$notifications = [];
	while ($row = mysqli_fetch_assoc($res)) {
		$notifications[] = $row;
	}
	mysqli_stmt_close($stmt);
	return $notifications;
}

function mark_notification_read(mysqli $conn, int $notificationId, int $userId): bool {
	$stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
	mysqli_stmt_bind_param($stmt, 'ii', $notificationId, $userId);
	$result = mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	return $result;
}

function mark_all_notifications_read(mysqli $conn, int $userId): bool {
	$stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0");
	mysqli_stmt_bind_param($stmt, 'i', $userId);
	$result = mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	return $result;
}

/**
 * Send funny notification based on activity type
 */
function sendFunnyNotification(mysqli $conn, int $userId, string $type, array $data = []): bool {
	$messages = [];
	$title = '';
	
	switch ($type) {
		case 'issued':
			$bookTitle = $data['title'] ?? 'a book';
			$messages = [
				"You grabbed {$bookTitle} — just don't forget to return it before it becomes a fossil! 🦕",
				"You grabbed {$bookTitle} — return it before it becomes vintage 😆",
				"Book issued! May the plot twists be ever in your favor.",
				"You got a new book! Remember: Reading > Reels.",
				"Congrats! You now own this book… temporarily 😏",
				"New book secured. Your brain is proud.",
				"Congratulations! You've successfully borrowed {$bookTitle}. Now the real challenge begins: reading it! 📚",
				"{$bookTitle} is now in your possession. Treat it well, or the library gods will be displeased! ⚡",
				"You've issued {$bookTitle}. Remember: books are like friends, return them before they get lonely! 👥",
				"Book issued: {$bookTitle}. Warning: May cause excessive knowledge and sudden bursts of wisdom! 🧠"
			];
			$title = "Book Issued Successfully! 📖";
			break;
			
		case 'returned':
			$bookTitle = $data['title'] ?? 'a book';
			$fine = $data['fine'] ?? 0;
			if ($fine > 0) {
				$messages = [
					"Wow! You returned {$bookTitle}, but there's a fine of ₹{$fine}. At least you didn't lose it! 💰",
					"Book returned: {$bookTitle}. Fine: ₹{$fine}. Consider it a donation to the library fund! 😅",
					"You returned {$bookTitle} with a fine. Better late than never... right? 🤷"
				];
			} else {
				$messages = [
					"Wow! You actually returned {$bookTitle} on time. Librarians everywhere are proud! 🎉",
					"You actually returned a book ON TIME? Nobel Prize nominee! 👏",
					"Book returned. Librarians everywhere are crying tears of joy.",
					"Farewell book. You'll be missed (mostly by us).",
					"Book returned — responsible adult level unlocked.",
					"Book returned successfully! {$bookTitle} is back home. You're a model citizen! ⭐",
					"Returned {$bookTitle} on time? Who are you and what have you done with the real user? 😱",
					"Amazing! You returned {$bookTitle} without a fine. The library gods smile upon you! ✨",
					"Book returned: {$bookTitle}. No fine! You're officially a library superhero! 🦸"
				];
			}
			$title = "Book Returned! ✅";
			break;
			
		case 'visit':
			$messages = [
				"Back again? You love books more than textbooks love exams! 📚",
				"Back again? Your dedication scares me 😳",
				"You visit this dashboard more than your classes.",
				"Welcome back! Did you miss me or the books?",
				"At this point, you deserve a VIP pass.",
				"Dashboard again? You're more consistent than my diet.",
				"Welcome back! Your reading addiction is showing... and we love it! 😍",
				"Another visit? Someone's clearly avoiding their assignments! 😂",
				"You're here again? The library missed you... or did you miss the library? 🤔",
				"Back so soon? We're not complaining, but your bookshelf might be! 📖"
			];
			$title = "Welcome Back! 👋";
			break;
			
		case 'recommendation':
			$count = $data['count'] ?? 0;
			$subtype = $data['subtype'] ?? 'new';
			if ($subtype === 'trending') {
				$messages = [
					"Everyone is reading this book… Don't be the last one again 😂",
					"Hot Pick! This book is more popular than your college crush 😏",
					"This book is trending harder than your Instagram reel.",
					"🔥 Alert: This book is literally on fire. (Not literally. Chill.)",
					"People are issuing this book like it's free pizza 🍕",
					"Breaking News: This book just broke the popularity meter.",
					"Trending Book! Add it now before your friends brag about it.",
					"This book is having its main-character moment ✨",
					"Another trending book… and here you are, scrolling.",
					"This one is famous. Like 'influencer with 1M followers' famous."
				];
			} elseif ($subtype === 'rated') {
				$messages = [
					"Highly rated books incoming! Unlike your last semester GPA 😜",
					"These books got 5 stars. You got… let's not talk about it 🤣",
					"Top-rated reads for sophisticated minds… or just bored ones.",
					"These books scored better than your group project presentation.",
					"Critics loved these books. Critics don't love anything. So that's big.",
					"5-star books landing! No fake ratings like Amazon reviews.",
					"These books got more stars than the night sky 🌟",
					"High rating alert — these books passed vibe check.",
					"Here are books that people ACTUALLY finished reading 😅",
					"Rated excellent. Unlike your attendance."
				];
			} elseif ($subtype === 'surprise') {
				$messages = [
					"A wild book appears! Catch it before it escapes 😁",
					"Here's a random recommendation. I felt cute, might delete later.",
					"Surprise! A book suggestion jumped into your library like a ninja.",
					"You didn't ask, but here's a book. You're welcome.",
					"Boom! Random book drop — because life needs surprises.",
					"Thought you might like this… or not. Who knows?",
					"Random recommendation unlocked — achievement earned 🏆",
					"This book wants your attention. Give it love ❤️",
					"A mysterious book has entered the chat.",
					"Surprise book! Because chaos is fun."
				];
			} else {
				$messages = [
					"Your brain might like these books. Your professor definitely will 😆",
					"Books recommended for your taste… and no, we didn't judge you. Much.",
					"Hey genius, here are some books smarter than you 😜",
					"Recommended Reads: Trust me, I'm an AI… I know things 🤓",
					"Your reading destiny has arrived. Dramatic music not included.",
					"New book suggestions dropping like your motivation during exams.",
					"Books selected specially for you. Yes, YOU. Feel special 😌",
					"Recommendations updated: Your bookshelf is screaming with joy.",
					"These books were handpicked by algorithms who believe in you. I don't.",
					"New reads for you! Don't worry, none of them bite.",
					"New books picked specially for your brain's taste buds! 🤓📚",
					"We found {$count} amazing books just for you! Your future self will thank us! 🎁",
					"Fresh recommendations alert! These books are calling your name... literally! 📢",
					"Your personalized book recommendations are ready! Time to expand that reading list! 📋",
					"Hot off the algorithm: {$count} books that match your reading style! 🔥"
				];
			}
			$title = "New Recommendations Available! ⭐";
			break;
			
		case 'viewed_multiple':
			$bookTitle = $data['title'] ?? 'this book';
			$count = $data['count'] ?? 0;
			$messages = [
				"You're checking this book again? Just marry it already 😄",
				"Still stalking *that* book? 👀",
				"You and this book have a bond stronger than WiFi.",
				"This book is flattered by your attention 😌",
				"You've viewed this book more times than your syllabus.",
				"Just issue it! Don't be shy 😏",
				"Someone's stalking {$bookTitle} 👀 Just issue it already!",
				"You've viewed {$bookTitle} {$count} times. We think you like it! 💕",
				"Still looking at {$bookTitle}? It's clearly meant to be! 💫",
				"{$bookTitle} has been viewed {$count} times by you. The book is blushing! 😊",
				"Stop stalking {$bookTitle} and just request it already! We know you want it! 🎯"
			];
			$title = "Book Viewing Alert! 👁️";
			break;
			
		default:
			$messages = ["You have a new notification! 🎉"];
			$title = "Notification";
	}
	
	$message = $messages[array_rand($messages)];
	return create_notification($conn, $userId, $title, $message, 'funny');
}

/**
 * Log user search for recommendations
 */
function log_search(mysqli $conn, int $userId, string $keyword): void {
	if (empty(trim($keyword))) return;
	$keyword = substr(trim($keyword), 0, 255);
	$stmt = mysqli_prepare($conn, "INSERT INTO search_logs (user_id, keyword) VALUES (?, ?)");
	mysqli_stmt_bind_param($stmt, 'is', $userId, $keyword);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
}

/**
 * Log book view for recommendations
 */
function log_book_view(mysqli $conn, int $userId, int $bookId): void {
	$stmt = mysqli_prepare($conn, "INSERT INTO book_views (user_id, book_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE viewed_at=CURRENT_TIMESTAMP");
	mysqli_stmt_bind_param($stmt, 'ii', $userId, $bookId);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
}

function pick_funny_message(string $category): string {
	$messages = [
		'welcome' => [
			'Back again? You love books more than textbooks love exams! 📚',
			'Welcome back! Did you miss me or the books?',
			'Welcome back! Your reading addiction is showing... and we love it! 😍',
			'Dashboard again? You\'re more consistent than my diet.',
			'At this point, you deserve a VIP pass.',
		],
		'overdue' => [
			'🚨 Bro, your book has been in vacation mode longer than you. Return it ASAP!',
			'📖 That book wasn\'t issued for adoption. Please bring it back. 😭',
			'⚠️ Your borrowed book is ghosting the library. We need closure.',
			'👀 The library wants its book back. This isn\'t a long-distance relationship.',
			'💀 Your book is officially older in your bag than some friendships. Return it!',
			'📚 Library checking in: Are you reading the book or raising it as your child?',
			'🚔 Book Police Alert: Your due date left the chat days ago.',
			'🔥 The book is overdue. The librarian is trying very hard to stay calm.',
			'🤨 You borrowed a book, not a lifetime subscription. Return it now.',
			'📢 Plot twist: The main character returned the book on time. Be like them.',
			'📚 Return the book before it becomes library folklore.',
			'💀 Due date expired. Character development needed.',
			'🚨 Delulu is not the solulu. Return the book.',
			'📖 Book borrowed: ✅ Returned: ❌ Excuses: Unlimited.',
			'🔥 You\'re not late, you\'re just aggressively overdue.',
			'👁️ The librarian sees everything. Return the book.',
			'🚔 Stop hiding. The book knows where you live. 😭',
		],
		'fine_pending' => [
			'💰 Your fine is growing faster than your screen time.',
			'🚨 Outstanding Fine Detected. Your wallet is in danger.',
			'😭 The fine keeps increasing while you keep ignoring us. Who\'s winning?',
			'💸 Your fine has entered its "influencer growth era." Pay it ASAP.',
			'📈 Fine Status: Stonks. Every day you wait, it gets bigger.',
			'☕ Skip one coffee and pay your library fine. Problem solved.',
			'😎 Your fine is collecting interest in your absence. Respectfully, pay it.',
			'🚨 Financial Responsibility Challenge: Library Edition.',
			'💀 At this point, the fine misses you more than the library does.',
			'🤑 The fine called. It wants attention and payment.',
			'💸 Your fine is giving "premium subscription" vibes. Cancel it now.',
		],
		'due_tomorrow' => [
			'⏰ Last free day alert! Return your book tomorrow or your wallet starts taking damage. 💸',
			'🚨 Tomorrow is the due date. After that, the fine enters the chat.',
			'📖 Your book\'s checkout era ends tomorrow. Return it before the fine era begins.',
			'💀 Tomorrow is your final boss level. Defeat it by returning the book.',
			'⚠️ Return the book tomorrow. Future You will thank Present You. Your wallet too.',
			'🚔 Return the book tomorrow or the fine will start its villain arc.',
			'💸 Your book has one day of immunity left. Use it wisely.',
			'📚 Reminder: Tomorrow = Return Book. Day After Tomorrow = Pay Fine. Choose your fighter.',
			'😭 We are giving you one last chance before the fine starts multiplying like group project problems.',
			'🔥 Return tomorrow or unlock the premium feature: Daily Fine Charges™.',
			'POV: You have 24 hours to return the book before your balance says "goodbye." 💸',
			'📢 Tomorrow is the due date. Don\'t let a ₹5 fine become a ₹50 character-development story.',
			'👀 The library is watching. Return the book tomorrow and nobody gets fined.',
			'💀 Due Tomorrow. Fine After Tomorrow. Mathematics is unavoidable.',
			'🤝 Let\'s keep this friendship healthy—return the book tomorrow.',
			'📚 Return tomorrow. Fine starts the next day.',
			'⏰ 24 hours left to avoid a fine!',
			'💸 Tomorrow = Free. After that = Fee.',
			'🚨 Last reminder before fine activation!',
			'📖 Return tomorrow, save your money.',
			'🔥 One day left. Your wallet is counting on you.',
			'😎 Be the main character. Return it on time.',
		],
	];
	$pool = $messages[$category] ?? ['You have a new notification! 🎉'];
	return $pool[array_rand($pool)];
}

function calculate_user_pending_fine(mysqli $conn, int $userId): float {
	$total = 0.0;
	$stmt = mysqli_prepare($conn, "SELECT issue_date, return_date, due_date, fine, fine_status, fine_rate FROM issued_books WHERE user_id=?");
	mysqli_stmt_bind_param($stmt, 'i', $userId);
	mysqli_stmt_execute($stmt);
	$res = mysqli_stmt_get_result($stmt);
	while ($row = mysqli_fetch_assoc($res)) {
		if (($row['fine_status'] ?? 'unpaid') === 'paid') {
			continue;
		}
		$dueDate = $row['due_date'] ?: compute_due_date($row['issue_date'])->format('Y-m-d');
		$fineRate = (float)($row['fine_rate'] ?? FINE_PER_DAY);
		if (empty($row['return_date'])) {
			$total += compute_fine($row['issue_date'], null, $dueDate, $fineRate);
		} else {
			$storedFine = (float)($row['fine'] ?? 0);
			$total += $storedFine > 0 ? $storedFine : compute_fine($row['issue_date'], $row['return_date'], $dueDate, $fineRate);
		}
	}
	mysqli_stmt_close($stmt);
	return round($total, 2);
}

function get_user_profile_data(mysqli $conn, int $userId): ?array {
	$stmt = mysqli_prepare($conn, "SELECT id, name, email, phone, role, status, email_verified, profile_picture, created_at FROM users WHERE id=? LIMIT 1");
	mysqli_stmt_bind_param($stmt, 'i', $userId);
	mysqli_stmt_execute($stmt);
	$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
	mysqli_stmt_close($stmt);
	if (!$user) {
		return null;
	}

	$notesStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM uploaded_notes WHERE user_id=?");
	mysqli_stmt_bind_param($notesStmt, 'i', $userId);
	mysqli_stmt_execute($notesStmt);
	$user['shared_notes_count'] = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($notesStmt))['c'];
	mysqli_stmt_close($notesStmt);

	$approvedNotesStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM uploaded_notes WHERE user_id=? AND status='approved'");
	mysqli_stmt_bind_param($approvedNotesStmt, 'i', $userId);
	mysqli_stmt_execute($approvedNotesStmt);
	$user['approved_notes_count'] = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($approvedNotesStmt))['c'];
	mysqli_stmt_close($approvedNotesStmt);

	$issuedStmt = mysqli_prepare($conn, "
		SELECT ib.id, b.title, ib.issue_date, ib.return_date, ib.due_date, ib.fine, ib.fine_status, ib.fine_rate
		FROM issued_books ib
		JOIN books b ON b.id = ib.book_id
		WHERE ib.user_id=?
		ORDER BY ib.issue_date DESC
	");
	mysqli_stmt_bind_param($issuedStmt, 'i', $userId);
	mysqli_stmt_execute($issuedStmt);
	$issuedBooks = [];
	$openCount = 0;
	$res = mysqli_stmt_get_result($issuedStmt);
	while ($row = mysqli_fetch_assoc($res)) {
		$dueDate = $row['due_date'] ?: compute_due_date($row['issue_date'])->format('Y-m-d');
		$fineRate = (float)($row['fine_rate'] ?? FINE_PER_DAY);
		$row['due_date'] = $dueDate;
		$row['current_fine'] = empty($row['return_date'])
			? compute_fine($row['issue_date'], null, $dueDate, $fineRate)
			: ((float)($row['fine'] ?? 0) ?: compute_fine($row['issue_date'], $row['return_date'], $dueDate, $fineRate));
		$row['is_open'] = empty($row['return_date']);
		if ($row['is_open']) {
			$openCount++;
		}
		$issuedBooks[] = $row;
	}
	mysqli_stmt_close($issuedStmt);

	$user['issued_books'] = $issuedBooks;
	$user['open_books_count'] = $openCount;
	$user['total_pending_fine'] = calculate_user_pending_fine($conn, $userId);

	return $user;
}

/**
 * Update fines for all overdue books daily
 * This function should be called daily (e.g., via cron job or on admin dashboard load)
 */
function update_overdue_fines(mysqli $conn): array {
	$stats = ['updated' => 0, 'errors' => 0];
	$today = (new DateTime('today'))->format('Y-m-d');
	
	try {
		// Get all overdue books that haven't been returned
		$query = "SELECT ib.id, ib.issue_date, ib.due_date, ib.fine_rate, ib.fine
		          FROM issued_books ib
		          WHERE ib.return_date IS NULL
		          AND COALESCE(ib.due_date, DATE_ADD(ib.issue_date, INTERVAL " . LOAN_DAYS . " DAY)) < ?";
		
		$stmt = mysqli_prepare($conn, $query);
		if (!$stmt) {
			log_message("Error preparing statement in update_overdue_fines: " . mysqli_error($conn));
			return $stats;
		}
		
		mysqli_stmt_bind_param($stmt, 's', $today);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);
		
		while ($row = mysqli_fetch_assoc($result)) {
			try {
				$dueDate = $row['due_date'] ?: compute_due_date($row['issue_date'])->format('Y-m-d');
				$fineRate = (float)($row['fine_rate'] ?? FINE_PER_DAY);
				$calculatedFine = compute_fine($row['issue_date'], null, $dueDate, $fineRate);
				
				// Only update if the calculated fine is different from stored fine
				if (abs($calculatedFine - (float)$row['fine']) > 0.01) {
					$updateStmt = mysqli_prepare($conn, "UPDATE issued_books SET fine = ? WHERE id = ?");
					if ($updateStmt) {
						mysqli_stmt_bind_param($updateStmt, 'di', $calculatedFine, $row['id']);
						if (mysqli_stmt_execute($updateStmt)) {
							$stats['updated']++;
						} else {
							$stats['errors']++;
							log_message("Error updating fine for issued_book id {$row['id']}: " . mysqli_stmt_error($updateStmt));
						}
						mysqli_stmt_close($updateStmt);
					} else {
						$stats['errors']++;
						log_message("Error preparing update statement for issued_book id {$row['id']}: " . mysqli_error($conn));
					}
				}
			} catch (Exception $e) {
				$stats['errors']++;
				log_message("Exception in update_overdue_fines for issued_book id {$row['id']}: " . $e->getMessage());
			}
		}
		
		mysqli_stmt_close($stmt);
	} catch (Exception $e) {
		log_message("Fatal error in update_overdue_fines: " . $e->getMessage());
		$stats['errors']++;
	}
	
	return $stats;
}

/**
 * Enhanced due notifications with pre-due date reminders
 * Sends notifications:
 * - 2 days before due date
 * - 1 day before due date
 * - On due date
 * - After overdue (daily)
 */
function ensure_due_notifications(mysqli $conn): array {
	$stats = ['due_soon' => 0, 'due_today' => 0, 'overdue' => 0, 'errors' => 0];
	$today = (new DateTime('today'))->format('Y-m-d');
	
	try {
	$query = "SELECT ib.id, ib.user_id, ib.issue_date, ib.due_date, ib.notified_due, ib.notified_overdue,
	                u.email, u.name, b.title
	          FROM issued_books ib
	          JOIN users u ON u.id = ib.user_id
	          JOIN books b ON b.id = ib.book_id
	          WHERE ib.return_date IS NULL";
		
	$result = mysqli_query($conn, $query);
		if (!$result) {
			log_message("Error querying issued_books in ensure_due_notifications: " . mysqli_error($conn));
			return $stats;
		}
		
	while ($row = mysqli_fetch_assoc($result)) {
			try {
		$dueDate = $row['due_date'] ?: compute_due_date($row['issue_date'])->format('Y-m-d');
		$dueObj = new DateTime($dueDate);
		$todayObj = new DateTime($today);
		$diff = (int)$todayObj->diff($dueObj)->format('%r%a');
				
				// Helper function to check if notification already sent
				$checkNotificationSent = function($issuedBookId, $notifType, $notifiedOn) use ($conn) {
					$checkStmt = mysqli_prepare($conn, 
						"SELECT COUNT(*) AS cnt FROM due_notifications 
						 WHERE issued_book_id = ? AND notification_type = ? AND notified_on = ?");
					if (!$checkStmt) return true; // If we can't check, assume sent to avoid duplicates
					mysqli_stmt_bind_param($checkStmt, 'iss', $issuedBookId, $notifType, $notifiedOn);
					mysqli_stmt_execute($checkStmt);
					$checkRes = mysqli_stmt_get_result($checkStmt);
					$checkRow = mysqli_fetch_assoc($checkRes);
					mysqli_stmt_close($checkStmt);
					return (int)$checkRow['cnt'] > 0;
				};
				
				// Notification 2 days before due date
				if ($diff === 2) {
					if (!$checkNotificationSent($row['id'], 'due_soon_2', $today)) {
						$funny = pick_funny_message('due_tomorrow');
						$emailSubject = 'Book Due Soon - Reminder';
						$emailBody = "<p>Hi {$row['name']},<br><br>{$funny}<br><br>The book <strong>{$row['title']}</strong> is due in 2 days (on {$dueDate}).</p>";
						send_email($row['email'], $emailSubject, $emailBody);
						$stats['due_soon']++;
						$ins = mysqli_prepare($conn, "INSERT IGNORE INTO due_notifications (issued_book_id, notification_type, notified_on) VALUES (?, 'due_soon_2', ?)");
						if ($ins) {
							mysqli_stmt_bind_param($ins, 'is', $row['id'], $today);
							mysqli_stmt_execute($ins);
							mysqli_stmt_close($ins);
						}
						create_notification($conn, (int)$row['user_id'], '📚 Friendly Warning', "{$funny} ({$row['title']} due in 2 days)", 'warning');
					}
				}
				// Notification 1 day before due date
				elseif ($diff === 1) {
					if (!$checkNotificationSent($row['id'], 'due_soon_1', $today)) {
						$funny = pick_funny_message('due_tomorrow');
						$emailSubject = 'Book Due Tomorrow - Important Reminder';
						$emailBody = "<p>Hi {$row['name']},<br><br>{$funny}<br><br>The book <strong>{$row['title']}</strong> is due tomorrow ({$dueDate}).</p>";
						send_email($row['email'], $emailSubject, $emailBody);
						$stats['due_soon']++;
						$ins = mysqli_prepare($conn, "INSERT IGNORE INTO due_notifications (issued_book_id, notification_type, notified_on) VALUES (?, 'due_soon_1', ?)");
						if ($ins) {
							mysqli_stmt_bind_param($ins, 'is', $row['id'], $today);
							mysqli_stmt_execute($ins);
							mysqli_stmt_close($ins);
						}
						create_notification($conn, (int)$row['user_id'], '⏰ Due Tomorrow', "{$funny} ({$row['title']})", 'warning');
					}
				}
				// Notification on due date
				elseif ($diff === 0 && !(int)$row['notified_due']) {
					$funny = pick_funny_message('due_tomorrow');
					$emailSubject = 'Book Due Today';
					$emailBody = "<p>Hi {$row['name']},<br><br>{$funny}<br><br>The book <strong>{$row['title']}</strong> is due today ({$dueDate}).</p>";
					send_email($row['email'], $emailSubject, $emailBody);
					$stats['due_today']++;
					$updStmt = mysqli_prepare($conn, "UPDATE issued_books SET notified_due = 1 WHERE id = ?");
					if ($updStmt) {
						mysqli_stmt_bind_param($updStmt, 'i', $row['id']);
						mysqli_stmt_execute($updStmt);
						mysqli_stmt_close($updStmt);
					}
					$ins = mysqli_prepare($conn, "INSERT IGNORE INTO due_notifications (issued_book_id, notification_type, notified_on) VALUES (?, 'due', ?)");
					if ($ins) {
						mysqli_stmt_bind_param($ins, 'is', $row['id'], $today);
						mysqli_stmt_execute($ins);
						mysqli_stmt_close($ins);
					}
					create_notification($conn, (int)$row['user_id'], '📚 Due Today', "{$funny} ({$row['title']})", 'warning');
				}
				// Notification for overdue books (daily)
				elseif ($diff < 0) {
					if (!$checkNotificationSent($row['id'], 'overdue', $today)) {
						$fine = compute_fine($row['issue_date'], null, $row['due_date']);
						$daysOverdue = abs($diff);
						$funny = pick_funny_message('overdue');
						$emailSubject = 'Book Overdue - Action Required';
						$emailBody = "<p>Hi {$row['name']},<br><br>{$funny}<br><br>The book <strong>{$row['title']}</strong> is overdue by {$daysOverdue} day(s). Fine: ₹" . number_format($fine, 2) . "</p>";
						send_email($row['email'], $emailSubject, $emailBody);
						$stats['overdue']++;
						if (!(int)$row['notified_overdue']) {
							$updStmt = mysqli_prepare($conn, "UPDATE issued_books SET notified_overdue = 1 WHERE id = ?");
							if ($updStmt) {
								mysqli_stmt_bind_param($updStmt, 'i', $row['id']);
								mysqli_stmt_execute($updStmt);
								mysqli_stmt_close($updStmt);
							}
						}
						$ins = mysqli_prepare($conn, "INSERT IGNORE INTO due_notifications (issued_book_id, notification_type, notified_on) VALUES (?, 'overdue', ?)");
						if ($ins) {
							mysqli_stmt_bind_param($ins, 'is', $row['id'], $today);
							mysqli_stmt_execute($ins);
							mysqli_stmt_close($ins);
						}
						create_notification($conn, (int)$row['user_id'], '🚨 Overdue Book', "{$funny} ({$row['title']} — ₹" . number_format($fine, 2) . " fine)", 'danger');
					}
				}
			} catch (Exception $e) {
				$stats['errors']++;
				log_message("Exception processing notification for issued_book id {$row['id']}: " . $e->getMessage());
			}
		}
		
		mysqli_free_result($result);
	} catch (Exception $e) {
		log_message("Fatal error in ensure_due_notifications: " . $e->getMessage());
		$stats['errors']++;
	}
	
	return $stats;
}
?>
