/**
 * Login Notifications Modal
 * Shows popup once per session after login
 */
document.addEventListener('DOMContentLoaded', function() {
	// Check if we should show the notification (only once per session)
	if (sessionStorage.getItem('login_notification_shown') === 'true') {
		return;
	}
	
	// Check if user is logged in (check for user data in page)
	const userIndicator = document.querySelector('[data-user-id]');
	if (!userIndicator) {
		return;
	}
	
	// Fetch notifications
	fetch('/api/get_login_notifications.php')
		.then(response => response.json())
		.then(data => {
			if (data.success && data.has_notifications) {
				showLoginNotificationModal(data.notifications);
				sessionStorage.setItem('login_notification_shown', 'true');
			}
		})
		.catch(error => {
			console.error('Error fetching login notifications:', error);
		});
});

function showLoginNotificationModal(notifications) {
	const modal = document.createElement('div');
	modal.className = 'modal fade';
	modal.id = 'loginNotificationModal';
	modal.setAttribute('tabindex', '-1');
	modal.setAttribute('aria-labelledby', 'loginNotificationModalLabel');
	modal.setAttribute('aria-hidden', 'true');
	modal.setAttribute('data-bs-backdrop', 'static');
	modal.setAttribute('data-bs-keyboard', 'false');
	
	let content = `
		<div class="modal-dialog modal-dialog-scrollable modal-lg">
			<div class="modal-content">
				<div class="modal-header bg-primary text-white">
					<h5 class="modal-title" id="loginNotificationModalLabel">
						<i class="bi bi-bell-fill me-2"></i>Welcome Back! Important Notifications
					</h5>
					<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
	`;
	
	// Overdue Fines Section
	if (notifications.overdue_fines && notifications.overdue_fines.length > 0) {
		content += `
			<div class="alert alert-danger mb-3">
				<h6 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Overdue Books & Fines</h6>
				<ul class="mb-0">
		`;
		notifications.overdue_fines.forEach(item => {
			content += `
				<li class="mb-2">
					<strong>${escapeHtml(item.book_title)}</strong><br>
					<small class="text-muted">
						Due: ${item.due_date} | 
						${item.days_overdue} day(s) overdue | 
						Fine: ₹${item.fine.toFixed(2)}
					</small>
				</li>
			`;
		});
		content += `</ul></div>`;
	}
	
	// Upcoming Due Dates Section
	if (notifications.upcoming_due && notifications.upcoming_due.length > 0) {
		content += `
			<div class="alert alert-warning mb-3">
				<h6 class="alert-heading"><i class="bi bi-calendar-check me-2"></i>Upcoming Due Dates</h6>
				<ul class="mb-0">
		`;
		notifications.upcoming_due.forEach(item => {
			const urgency = item.days_until === 0 ? 'Today' : item.days_until === 1 ? 'Tomorrow' : `${item.days_until} days`;
			content += `
				<li class="mb-2">
					<strong>${escapeHtml(item.book_title)}</strong><br>
					<small class="text-muted">
						Due: ${item.due_date} (${urgency})
					</small>
				</li>
			`;
		});
		content += `</ul></div>`;
	}
	
	// Admin Messages Section
	if (notifications.admin_messages && notifications.admin_messages.length > 0) {
		content += `
			<div class="alert alert-info mb-3">
				<h6 class="alert-heading"><i class="bi bi-megaphone-fill me-2"></i>Admin Messages</h6>
		`;
		notifications.admin_messages.forEach(msg => {
			const alertClass = msg.type === 'danger' ? 'danger' : msg.type === 'warning' ? 'warning' : 'info';
			content += `
				<div class="alert alert-${alertClass} mb-2">
					<h6 class="mb-1">${escapeHtml(msg.title)}</h6>
					<p class="mb-0 small">${escapeHtml(msg.message)}</p>
					<small class="text-muted">${new Date(msg.created_at).toLocaleString()}</small>
				</div>
			`;
		});
		content += `</div>`;
	}
	
	content += `
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it!</button>
				</div>
			</div>
		</div>
	`;
	
	modal.innerHTML = content;
	document.body.appendChild(modal);
	
	// Show modal using Bootstrap
	const bsModal = new bootstrap.Modal(modal);
	bsModal.show();
	
	// Clean up when modal is hidden
	modal.addEventListener('hidden.bs.modal', function() {
		document.body.removeChild(modal);
	});
}

function escapeHtml(text) {
	const map = {
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;'
	};
	return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
}

