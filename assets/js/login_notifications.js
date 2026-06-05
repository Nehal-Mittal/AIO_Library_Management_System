/**
 * Login Notifications Modal
 * Shows popup once after each login
 */
document.addEventListener('DOMContentLoaded', function() {
	const userIndicator = document.querySelector('[data-user-id]');
	if (!userIndicator) {
		return;
	}

	fetch('/api/get_login_notifications.php')
		.then(response => response.json())
		.then(data => {
			if (data.success && data.show_popup) {
				showLoginNotificationModal(data.notifications);
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
	modal.setAttribute('aria-hidden', 'true');
	modal.setAttribute('data-bs-backdrop', 'static');
	modal.setAttribute('data-bs-keyboard', 'false');

	let content = `
		<div class="modal-dialog modal-dialog-scrollable modal-lg">
			<div class="modal-content">
				<div class="modal-header bg-primary text-white">
					<h5 class="modal-title"><i class="bi bi-bell-fill me-2"></i>Welcome Back!</h5>
					<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
	`;

	if (notifications.welcome && notifications.welcome.length > 0) {
		notifications.welcome.forEach(item => {
			content += `
				<div class="alert alert-info mb-3">
					<h6 class="alert-heading mb-1">${escapeHtml(item.title)}</h6>
					<p class="mb-0">${escapeHtml(item.message)}</p>
				</div>
			`;
		});
	}

	if (notifications.pending_fine && notifications.pending_fine.length > 0) {
		notifications.pending_fine.forEach(item => {
			content += `
				<div class="alert alert-danger mb-3">
					<h6 class="alert-heading"><i class="bi bi-cash-coin me-2"></i>Pending Fine Notifications</h6>
					<p class="mb-1">${escapeHtml(item.message)}</p>
					<strong>Total pending fine: ₹${Number(item.total).toFixed(2)}</strong>
				</div>
			`;
		});
	}

	if (notifications.overdue_fines && notifications.overdue_fines.length > 0) {
		content += `<div class="alert alert-danger mb-3"><h6 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Overdue Book Notifications</h6><ul class="mb-0">`;
		notifications.overdue_fines.forEach(item => {
			content += `
				<li class="mb-2">
					<div>${escapeHtml(item.message)}</div>
					<small class="text-muted">
						<strong>${escapeHtml(item.book_title)}</strong> — due ${item.due_date}, ${item.days_overdue} day(s) overdue, fine ₹${Number(item.fine).toFixed(2)}
					</small>
				</li>
			`;
		});
		content += `</ul></div>`;
	}

	if (notifications.upcoming_due && notifications.upcoming_due.length > 0) {
		content += `<div class="alert alert-warning mb-3"><h6 class="alert-heading"><i class="bi bi-calendar-check me-2"></i>Return Date Reminders</h6><ul class="mb-0">`;
		notifications.upcoming_due.forEach(item => {
			const urgency = item.days_until === 0 ? 'Today' : item.days_until === 1 ? 'Tomorrow' : `${item.days_until} days`;
			content += `
				<li class="mb-2">
					<div>${escapeHtml(item.message)}</div>
					<small class="text-muted"><strong>${escapeHtml(item.book_title)}</strong> — due ${item.due_date} (${urgency})</small>
				</li>
			`;
		});
		content += `</ul></div>`;
	}

	if (notifications.admin_messages && notifications.admin_messages.length > 0) {
		content += `<div class="mb-3"><h6 class="mb-2"><i class="bi bi-megaphone-fill me-2"></i>Other Notifications</h6>`;
		notifications.admin_messages.forEach(msg => {
			const alertClass = msg.type === 'danger' ? 'danger' : msg.type === 'warning' ? 'warning' : msg.type === 'funny' ? 'info' : 'secondary';
			content += `
				<div class="alert alert-${alertClass} mb-2 py-2">
					<strong>${escapeHtml(msg.title)}</strong>
					<p class="mb-0 small">${escapeHtml(msg.message)}</p>
				</div>
			`;
		});
		content += `</div>`;
	}

	if (!notifications.welcome?.length && !notifications.pending_fine?.length &&
		!notifications.overdue_fines?.length && !notifications.upcoming_due?.length &&
		!notifications.admin_messages?.length) {
		content += `<p class="text-muted mb-0">You're all caught up. Happy reading! 📚</p>`;
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

	const bsModal = new bootstrap.Modal(modal);
	bsModal.show();

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
	return text ? String(text).replace(/[&<>"']/g, m => map[m]) : '';
}
