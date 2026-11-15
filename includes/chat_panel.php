<?php
declare(strict_types=1);

$chatTargetIds = is_array($chatContacts ?? null)
    ? array_map('intval', array_column($chatContacts, 'id'))
    : [];
if (!isset($chatTargetUserId) || !in_array((int) $chatTargetUserId, $chatTargetIds, true)) {
    $chatTargetUserId = null;
}
?>
<section class="chat-panel" aria-label="<?= htmlspecialchars($chatTitle, ENT_QUOTES, 'UTF-8') ?>">
    <?php if (is_array($chatContacts ?? null)): ?>
        <?php if ($chatContacts === []): ?>
            <p>No eligible conversation partners are available.</p>
        <?php else: ?>
            <label for="chat-target">Conversation partner</label>
            <select id="chat-target" data-chat-target required>
                <option value="">Select a person</option>
                <?php foreach ($chatContacts as $contact): ?>
                    <option value="<?= (int) $contact['id'] ?>"
                        <?= $chatTargetUserId === (int) $contact['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($contact['sport_name'])): ?>
                            (<?= htmlspecialchars($contact['sport_name'], ENT_QUOTES, 'UTF-8') ?>)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    <?php endif; ?>

    <div id="chat-box" class="message-list" aria-live="polite" aria-label="Messages"></div>
    <p data-chat-status role="status" aria-live="polite"></p>
    <form id="chat-form"
          data-chat-form
          data-conversation-type="<?= htmlspecialchars($chatType, ENT_QUOTES, 'UTF-8') ?>"
          data-target-user-id="<?= $chatTargetUserId === null ? '' : (int) $chatTargetUserId ?>"
          data-current-user-id="<?= (int) $_SESSION['user_id'] ?>"
          data-csrf-token="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <label for="chat-input">Message</label>
        <input id="chat-input" name="body" type="text" maxlength="4000" autocomplete="off" required>
        <button type="submit" <?= is_array($chatContacts ?? null) && $chatContacts === [] ? 'disabled' : '' ?>>
            Send
        </button>
    </form>
</section>
