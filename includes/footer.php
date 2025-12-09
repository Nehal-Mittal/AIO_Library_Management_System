		</div>
	<?php 
	$user = current_user();
	if ($user): 
	?>
	</div>
	<?php endif; ?>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<script src="/assets/js/app.js"></script>
	<script src="/assets/js/book_autocomplete.js"></script>
	<?php if (isset($_SESSION['user']['id'])): ?>
	<script src="/assets/js/login_notifications.js"></script>
	<script src="/assets/js/book_request.js"></script>
	<script src="/assets/js/sidebar.js"></script>
	<script src="/assets/js/dark-mode.js"></script>
	<?php endif; ?>
</body>
</html>

