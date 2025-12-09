/**
 * One-click Book Request Handler
 * Makes AJAX request without page reload
 */
document.addEventListener('DOMContentLoaded', function() {
	// Handle all request buttons
	document.querySelectorAll('[data-book-request]').forEach(button => {
		button.addEventListener('click', function(e) {
			e.preventDefault();
			
			const bookId = this.getAttribute('data-book-id');
			const bookTitle = this.getAttribute('data-book-title') || 'this book';
			
			if (!bookId) {
				showAlert('error', 'Invalid book ID');
				return;
			}
			
			// Disable button
			const originalText = this.innerHTML;
			this.disabled = true;
			this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Requesting...';
			
			// Get CSRF token
			const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
							  document.querySelector('input[name="csrf_token"]')?.value || '';
			
			// Make AJAX request
			fetch('/api/request_book.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					book_id: bookId,
					csrf_token: csrfToken
				})
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					showSuccessModal(bookTitle);
					// Optionally update button state
					this.innerHTML = '<i class="bi bi-check-circle me-1"></i>Requested';
					this.classList.remove('btn-primary');
					this.classList.add('btn-success');
				} else {
					showAlert(data.limit_reached ? 'warning' : 'error', data.message);
					this.disabled = false;
					this.innerHTML = originalText;
				}
			})
			.catch(error => {
				console.error('Request error:', error);
				showAlert('error', 'Failed to submit request. Please try again.');
				this.disabled = false;
				this.innerHTML = originalText;
			});
		});
	});
});

function showSuccessModal(bookTitle) {
	const modal = document.createElement('div');
	modal.className = 'modal fade';
	modal.id = 'requestSuccessModal';
	modal.setAttribute('tabindex', '-1');
	
	modal.innerHTML = `
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header bg-success text-white">
					<h5 class="modal-title">
						<i class="bi bi-check-circle-fill me-2"></i>Request Submitted!
					</h5>
					<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body text-center">
					<i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
					<p class="mt-3 mb-0">Your request for <strong>${escapeHtml(bookTitle)}</strong> has been submitted successfully!</p>
					<p class="text-muted small mt-2">Waiting for admin approval.</p>
				</div>
				<div class="modal-footer justify-content-center">
					<button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
				</div>
			</div>
		</div>
	`;
	
	document.body.appendChild(modal);
	const bsModal = new bootstrap.Modal(modal);
	bsModal.show();
	
	modal.addEventListener('hidden.bs.modal', function() {
		document.body.removeChild(modal);
	});
}

function showAlert(type, message) {
	const alertDiv = document.createElement('div');
	alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
	alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
	alertDiv.innerHTML = `
		${escapeHtml(message)}
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	`;
	
	document.body.appendChild(alertDiv);
	
	setTimeout(() => {
		if (alertDiv.parentNode) {
			alertDiv.remove();
		}
	}, 5000);
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

