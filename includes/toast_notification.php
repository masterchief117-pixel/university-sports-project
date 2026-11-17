<?php
declare(strict_types=1);

$flash = get_flash();
$toastMessage = $flash['message'];
$toastType = $flash['type'];
?>
<?php if ($toastMessage !== ''): ?>
    <div class="toast-stack" aria-live="polite" aria-atomic="true">
        <div class="toast is-<?= htmlspecialchars(
            in_array($toastType, ['error', 'warning'], true) ? $toastType : 'success',
            ENT_QUOTES,
            'UTF-8'
        ) ?>" data-toast>
            <span class="toast-icon" aria-hidden="true">
                <?= htmlspecialchars(
                    $toastType === 'error' || $toastType === 'warning' ? '!' : '✓',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>
            <div class="toast-body">
                <span class="toast-title"><?= htmlspecialchars(
                    $toastType === 'error' ? 'Heads up' : ($toastType === 'warning' ? 'Notice' : 'Success'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></span>
                <p class="toast-message"><?= htmlspecialchars($toastMessage, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toast = document.querySelector('[data-toast]');
            if (!toast) {
                return;
            }
            window.setTimeout(() => {
                toast.classList.add('fade-out');
                window.setTimeout(() => toast.remove(), 280);
            }, 3000);
        });
    </script>
<?php endif; ?>
