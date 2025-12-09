(() => {
	function initWithIds() {
		const dept = document.getElementById('department');
		const title = document.getElementById('book_title');
		const author = document.getElementById('book_author') || document.getElementById('author');
		const bookId = document.getElementById('book_id');
		// If title is a SELECT, populate options by department
		if (!dept || !title || !author) return false;
		let controller = null;
		const clearFields = () => { if (title.tagName === 'SELECT') { title.innerHTML = '<option value="">Select book</option>'; } else { title.value = ''; } author.value = ''; if (bookId) bookId.value = ''; };
		dept.addEventListener('change', async () => {
			clearFields();
			const department = dept.value.trim();
			if (!department) return;
			if (controller) controller.abort();
			controller = new AbortController();
			try {
				const res = await fetch(`/student/fetch_books.php?department=${encodeURIComponent(department)}`, { signal: controller.signal });
				if (!res.ok) return;
				const data = await res.json();
				if (title.tagName === 'SELECT') {
					let opts = '<option value="">Select book</option>';
					data.forEach(item => { opts += `<option value="${item.book_id}" data-title="${item.title}" data-author="${item.author}">${item.title}</option>`; });
					title.innerHTML = opts;
				}
			} catch (_) {}
		});
		if (title.tagName === 'SELECT') {
			title.addEventListener('change', () => {
				const sel = title.options[title.selectedIndex];
				const t = sel.getAttribute('data-title') || '';
				const a = sel.getAttribute('data-author') || '';
				const id = title.value || '';
				if (author) author.value = a;
				if (bookId) bookId.value = id;
			});
		}
		return true;
	}

	function initAutocomplete(container) {
		if (container.dataset.autocompleteInitialized === '1') return; // guard
		container.dataset.autocompleteInitialized = '1';
		const deptSelect = container.querySelector('[data-dept]');
		const titleInput = container.querySelector('[data-title]');
		const authorInput = container.querySelector('[data-author]');
		const list = document.createElement('div');
		list.className = 'list-group position-absolute w-100';
		list.style.zIndex = '1000';
		titleInput.parentElement.style.position = 'relative';
		titleInput.parentElement.appendChild(list);
		let controller = null;
		function clearList() { list.innerHTML = ''; list.style.display = 'none'; }
		function clearFields() { titleInput.value = ''; if (authorInput) authorInput.value=''; clearList(); }
		deptSelect.addEventListener('change', clearFields);
		titleInput.addEventListener('input', async () => {
			const q = titleInput.value.trim();
			const department = deptSelect.value.trim();
			if (q.length < 1 || !department) { clearList(); return; }
			if (controller) controller.abort();
			controller = new AbortController();
			try {
				const res = await fetch(`/student/fetch_books.php?q=${encodeURIComponent(q)}&department=${encodeURIComponent(department)}`, { signal: controller.signal });
				if (!res.ok) { clearList(); return; }
				const data = await res.json();
				list.innerHTML = '';
				if (!Array.isArray(data) || data.length === 0) { clearList(); return; }
				data.forEach(item => {
					const a = document.createElement('a');
					a.href = '#';
					a.className = 'list-group-item list-group-item-action';
					a.textContent = `${item.title} — ${item.author}`;
					a.addEventListener('click', (e) => {
						e.preventDefault();
						titleInput.value = item.title;
						if (authorInput) authorInput.value = item.author;
						clearList();
					});
					list.appendChild(a);
				});
				list.style.display = 'block';
			} catch (_) { clearList(); }
		});
		document.addEventListener('click', (e) => { if (!container.contains(e.target)) clearList(); });
	}

	// Prefer explicit IDs if present on page; otherwise fallback to data-attribute initializer
	if (!initWithIds()) {
		document.addEventListener('DOMContentLoaded', () => {
			document.querySelectorAll('[data-book-autocomplete]').forEach(initAutocomplete);
		});
	}
})();


