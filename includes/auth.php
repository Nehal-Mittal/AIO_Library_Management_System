<?php
require_once __DIR__ . '/../config.php';

function require_login() {
	if (!isset($_SESSION['user'])) {
		header('Location: /index.php');
		exit;
	}
}

/**
 * Require user to have one of the specified roles
 * @param string|array $role Single role string or array of allowed roles
 */
function require_role($role) {
	require_login();
	
	if (!isset($_SESSION['user']['role'])) {
		http_response_code(403);
		echo '<!doctype html><html><body><div style="padding:2rem;font-family:system-ui">Forbidden: No role assigned</div></body></html>';
		exit;
	}
	
	// Normalize user role: trim and convert to lowercase
	$userRole = strtolower(trim($_SESSION['user']['role']));
	
	// Handle array of allowed roles
	if (is_array($role)) {
		$allowedRoles = array_map(function($r) {
			return strtolower(trim((string)$r));
		}, $role);
		
		if (!in_array($userRole, $allowedRoles, true)) {
			http_response_code(403);
			echo '<!doctype html><html><body><div style="padding:2rem;font-family:system-ui">Forbidden: Insufficient permissions</div></body></html>';
			exit;
		}
	} else {
		// Handle single role string
		$requiredRole = strtolower(trim((string)$role));
		
		if ($userRole !== $requiredRole) {
			http_response_code(403);
			echo '<!doctype html><html><body><div style="padding:2rem;font-family:system-ui">Forbidden: Insufficient permissions</div></body></html>';
			exit;
		}
	}
}
?>


