/**
 * Sidebar Navigation JavaScript
 * Handles sidebar toggle and responsive behavior
 */

document.addEventListener('DOMContentLoaded', function() {
	const sidebar = document.getElementById('sidebar');
	const sidebarToggle = document.getElementById('sidebarToggle');
	const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
	const sidebarOverlay = document.getElementById('sidebarOverlay');
	
	if (!sidebar) return;
	
	// Show sidebar on desktop by default (always visible on desktop)
	if (window.innerWidth >= 992) {
		sidebar.classList.add('show');
		// On desktop, sidebar is always visible, no overlay needed
		if (sidebarOverlay) {
			sidebarOverlay.classList.remove('show');
		}
	}
	
	// Toggle sidebar function
	function toggleSidebar() {
		sidebar.classList.toggle('show');
		sidebarOverlay.classList.toggle('show');
		document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
	}
	
	// Close sidebar function
	function closeSidebar() {
		sidebar.classList.remove('show');
		sidebarOverlay.classList.remove('show');
		document.body.style.overflow = '';
	}
	
	// Event listeners
	if (sidebarToggle) {
		sidebarToggle.addEventListener('click', closeSidebar);
	}
	
	if (mobileSidebarToggle) {
		mobileSidebarToggle.addEventListener('click', toggleSidebar);
	}
	
	if (sidebarOverlay) {
		sidebarOverlay.addEventListener('click', closeSidebar);
	}
	
	// Handle window resize
	window.addEventListener('resize', function() {
		if (window.innerWidth >= 992) {
			sidebar.classList.add('show');
			sidebarOverlay.classList.remove('show');
			document.body.style.overflow = '';
		} else {
			sidebar.classList.remove('show');
		}
	});
	
	// Close sidebar when clicking on a link (mobile only)
	if (window.innerWidth < 992) {
		const sidebarLinks = sidebar.querySelectorAll('.sidebar-link');
		sidebarLinks.forEach(link => {
			link.addEventListener('click', function() {
				setTimeout(closeSidebar, 100);
			});
		});
	}
});

