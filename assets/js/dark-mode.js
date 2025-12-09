/**
 * Dark Mode Toggle JavaScript
 * Handles theme switching and persistence
 */

document.addEventListener('DOMContentLoaded', function() {
	const themeToggle = document.getElementById('themeToggle');
	const themeIcon = document.getElementById('themeIcon');
	
	if (!themeToggle) return;
	
	// Get current theme
	function getCurrentTheme() {
		return document.documentElement.getAttribute('data-theme') || 'light';
	}
	
	// Set theme
	function setTheme(theme) {
		document.documentElement.setAttribute('data-theme', theme);
		localStorage.setItem('theme', theme);
		
		// Update icon
		if (themeIcon) {
			if (theme === 'dark') {
				themeIcon.classList.remove('bi-moon-stars');
				themeIcon.classList.add('bi-sun');
			} else {
				themeIcon.classList.remove('bi-sun');
				themeIcon.classList.add('bi-moon-stars');
			}
		}
	}
	
	// Initialize icon based on current theme
	const currentTheme = getCurrentTheme();
	if (themeIcon) {
		if (currentTheme === 'dark') {
			themeIcon.classList.remove('bi-moon-stars');
			themeIcon.classList.add('bi-sun');
		}
	}
	
	// Toggle theme
	themeToggle.addEventListener('click', function() {
		const currentTheme = getCurrentTheme();
		const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
		setTheme(newTheme);
	});
});

