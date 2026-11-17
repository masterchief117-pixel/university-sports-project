</main>
<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> University Sports Portal</p>
</footer>
<script src="<?= htmlspecialchars(
    app_url('/assets/js/chat.js') . '?v=' . filemtime(__DIR__ . '/../assets/js/chat.js'),
    ENT_QUOTES,
    'UTF-8'
) ?>" defer></script>
</body>
</html>
