const FingerprintHelper = (() => {
	let fpInstance = null;
	async function ensureInstance() {
		if (!window.FingerprintJS) {
			throw new Error('Fingerprint library not loaded');
		}
		if (!fpInstance) {
			fpInstance = await FingerprintJS.load();
		}
		return fpInstance;
	}
	return {
		async getVisitorId() {
			const inst = await ensureInstance();
			const result = await inst.get();
			return result.visitorId;
		}
	};
})();

async function postJSON(url, data) {
	const res = await fetch(url, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify(data)
	});
	const payload = await res.json();
	if (!res.ok || !payload.success) {
		throw new Error(payload.message || 'Request failed');
	}
	return payload;
}

function bindFingerprintLogin() {
	const btn = document.getElementById('fingerprintLoginBtn');
	const status = document.getElementById('fingerprintStatus');
	if (!btn) return;
	btn.addEventListener('click', async () => {
		if (status) status.textContent = 'Scanning this device…';
		btn.disabled = true;
		try {
			const visitorId = await FingerprintHelper.getVisitorId();
			const response = await postJSON('/security/fingerprint_login.php', { fingerprint: visitorId });
			if (status) status.textContent = 'Success! Redirecting…';
			window.location.href = response.redirect || '/index.php';
		} catch (error) {
			if (status) status.textContent = error.message;
		} finally {
			btn.disabled = false;
		}
	});
}

function bindFingerprintRegister() {
	const btn = document.querySelector('[data-fingerprint-register]');
	const alertBox = document.getElementById('fingerprintRegisterAlert');
	if (!btn) return;
	btn.addEventListener('click', async () => {
		if (alertBox) {
			alertBox.classList.add('d-none');
			alertBox.textContent = '';
		}
		btn.disabled = true;
		try {
			const visitorId = await FingerprintHelper.getVisitorId();
			const csrf = btn.getAttribute('data-token');
			const response = await postJSON('/security/fingerprint_register.php', { fingerprint: visitorId, label: navigator.userAgent, csrf_token: csrf });
			if (alertBox) {
				alertBox.classList.remove('d-none');
				alertBox.classList.add('alert-success');
				alertBox.textContent = response.message;
			}
		} catch (error) {
			if (alertBox) {
				alertBox.classList.remove('d-none');
				alertBox.classList.remove('alert-success');
				alertBox.classList.add('alert-danger');
				alertBox.textContent = error.message;
			}
		} finally {
			btn.disabled = false;
		}
	});
}

// Toast Notification System
function showToast(message, type = 'info', duration = 3500) {
	// Ensure toast container exists
	let container = document.getElementById('toastContainer');
	if (!container) {
		container = document.createElement('div');
		container.id = 'toastContainer';
		document.body.appendChild(container);
	}
	
	// Create toast element
	const toast = document.createElement('div');
	toast.className = `toast ${type}`;
	toast.innerHTML = `<div>${message}</div>`;
	
	// Add click to dismiss
	toast.addEventListener('click', () => {
		toast.classList.add('fade-out');
		setTimeout(() => toast.remove(), 300);
	});
	
	// Add to container
	container.appendChild(toast);
	
	// Auto remove after duration
	setTimeout(() => {
		if (toast.parentNode) {
			toast.classList.add('fade-out');
			setTimeout(() => toast.remove(), 300);
		}
	}, duration);
	
	return toast;
}

// Notification System
const NotificationSystem = (() => {
	let updateInterval = null;
	
	async function fetchNotificationCount() {
		try {
			const response = await fetch('/api/notifications.php?action=count');
			const data = await response.json();
			updateNotificationBadge(data.count || 0);
			return data.count || 0;
		} catch (error) {
			console.error('Failed to fetch notification count:', error);
			return 0;
		}
	}
	
	function updateNotificationBadge(count) {
		const badge = document.querySelector('#notificationDropdown .badge');
		if (badge) {
			if (count > 0) {
				badge.textContent = count > 99 ? '99+' : count;
				badge.style.display = 'inline-block';
			} else {
				badge.style.display = 'none';
			}
		}
	}
	
	async function fetchNotifications(limit = 5, unreadOnly = true) {
		try {
			const url = `/api/notifications.php?action=list&limit=${limit}&unread_only=${unreadOnly ? '1' : '0'}`;
			const response = await fetch(url);
			const data = await response.json();
			return data.notifications || [];
		} catch (error) {
			console.error('Failed to fetch notifications:', error);
			return [];
		}
	}
	
	async function markAsRead(notificationId) {
		try {
			const response = await fetch('/api/notifications.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					action: 'mark_read',
					id: notificationId
				})
			});
			const data = await response.json();
			return data.success || false;
		} catch (error) {
			console.error('Failed to mark notification as read:', error);
			return false;
		}
	}
	
	function startAutoUpdate(intervalMs = 30000) {
		if (updateInterval) {
			clearInterval(updateInterval);
		}
		updateInterval = setInterval(() => {
			fetchNotificationCount();
		}, intervalMs);
	}
	
	function stopAutoUpdate() {
		if (updateInterval) {
			clearInterval(updateInterval);
			updateInterval = null;
		}
	}
	
	return {
		fetchCount: fetchNotificationCount,
		fetchNotifications: fetchNotifications,
		markAsRead: markAsRead,
		startAutoUpdate: startAutoUpdate,
		stopAutoUpdate: stopAutoUpdate,
		updateBadge: updateNotificationBadge
	};
})();

// Apply dynamic theme based on notification type
function applyNotificationTheme(type) {
	document.body.classList.remove('theme-issued', 'theme-visited', 'theme-recommend', 'theme-returned');
	if (type) {
		document.body.classList.add(`theme-${type}`);
		// Remove theme after 5 seconds
		setTimeout(() => {
			document.body.classList.remove(`theme-${type}`);
		}, 5000);
	}
}

document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.alert-auto-dismiss').forEach(function(alert) {
		setTimeout(function() {
			alert.classList.remove('show');
		}, 3000);
	});
	bindFingerprintLogin();
	bindFingerprintRegister();
	
	// Initialize notification system if user is logged in
	if (document.getElementById('notificationDropdown')) {
		NotificationSystem.fetchCount();
		NotificationSystem.startAutoUpdate(30000); // Update every 30 seconds
		
		// Check for new notifications and show toast
		checkAndShowNewNotifications();
	}
	
	// Function to check and show new notifications
	async function checkAndShowNewNotifications() {
		try {
			const notifications = await NotificationSystem.fetchNotifications(5, true);
			if (notifications.length > 0) {
				// Show the most recent unread notification as toast
				const latest = notifications[0];
				const type = latest.type || 'info';
				const theme = getThemeFromType(latest.type, latest.title);
				if (theme) {
					applyNotificationTheme(theme);
				}
				showToast(latest.message, type, 5000);
			}
		} catch (error) {
			console.error('Failed to check notifications:', error);
		}
	}
	
	// Helper to determine theme from notification type and title
	function getThemeFromType(type, title) {
		if (type === 'funny') {
			const titleLower = (title || '').toLowerCase();
			if (titleLower.includes('issued') || titleLower.includes('book issued')) {
				return 'issued';
			} else if (titleLower.includes('returned') || titleLower.includes('book returned')) {
				return 'returned';
			} else if (titleLower.includes('recommendation') || titleLower.includes('recommend')) {
				return 'recommend';
			} else if (titleLower.includes('visit') || titleLower.includes('welcome')) {
				return 'visited';
			}
		}
		return null;
	}
	
	// Handle notification dropdown click
	const notificationDropdown = document.getElementById('notificationDropdown');
	if (notificationDropdown) {
		notificationDropdown.addEventListener('click', async function(e) {
			e.preventDefault();
			// Refresh notifications when dropdown is opened
			await NotificationSystem.fetchCount();
		});
	}
	
	// Mark notification as read when clicked
	document.addEventListener('click', async function(e) {
		const notificationItem = e.target.closest('.notification-item');
		if (notificationItem && !notificationItem.classList.contains('notification-read')) {
			const notificationId = notificationItem.getAttribute('data-id');
			if (notificationId) {
				await NotificationSystem.markAsRead(parseInt(notificationId));
				notificationItem.classList.add('notification-read');
				notificationItem.classList.remove('notification-unread');
				await NotificationSystem.fetchCount();
			}
		}
	});
});

