<?php
require_once __DIR__ . '/../config.php';
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Library Management System</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
	<link href="/assets/css/styles.css" rel="stylesheet">
	<script defer src="https://cdn.jsdelivr.net/npm/@fingerprintjs/fingerprintjs@3/dist/fp.min.js"></script>
	<?php if (isset($_SESSION['user']['id'])): ?>
	<meta name="csrf-token" content="<?php echo csrf_token(); ?>">
	<div data-user-id="<?php echo (int)$_SESSION['user']['id']; ?>" style="display:none;"></div>
	<?php endif; ?>
	<script>
		// Initialize theme from localStorage
		(function() {
			const theme = localStorage.getItem('theme') || 'light';
			document.documentElement.setAttribute('data-theme', theme);
		})();
	</script>
</head>
<body>
	<?php 
	$user = current_user();
	if ($user): 
		include __DIR__ . '/sidebar.php'; 
	endif;
	?>
	<?php include __DIR__ . '/navbar.php'; ?>
	<div class="<?php echo $user ? 'main-content' : ''; ?>">
		<div class="container py-4">

