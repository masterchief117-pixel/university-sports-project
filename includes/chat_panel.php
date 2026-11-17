<?php
declare(strict_types=1);

$selectedContact = null;
foreach (is_array($chatContacts ?? null) ? $chatContacts : [] as $contact) {
    $contactType = $contact['conversation_type'] ?? $chatType;
    if (isset($chatTargetUserId)
        && (int) $contact['id'] === (int) $chatTargetUserId
        && $contactType === $chatType) {
        $selectedContact = $contact;
        break;
    }
}
if ($selectedContact === null) {
    $chatTargetUserId = null;
}
?>
<section class="chat-panel" aria-label="<?= htmlspecialchars($chatTitle, ENT_QUOTES, 'UTF-8') ?>">
    <div class="chat-workspace<?= is_array($chatContacts ?? null) ? ' has-chat-sidebar' : '' ?>">
        <?php if (is_array($chatContacts ?? null)): ?>
            <aside class="chat-sidebar" aria-label="Conversation partners">
                <h2>Conversations</h2>
                <?php if (!empty($chatTeamUrl)): ?>
                    <a class="chat-team-link"
                       href="<?= htmlspecialchars($chatTeamUrl, ENT_QUOTES, 'UTF-8') ?>">Team chat</a>
                <?php endif; ?>
                <?php if ($chatContacts === []): ?>
                    <p>No eligible conversation partners are available.</p>
                <?php else: ?>
                    <div class="chat-contact-list">
                        <?php foreach ($chatContacts as $contact): ?>
                            <?php $contactType = $contact['conversation_type'] ?? $chatType; ?>
                            <button type="button"
                                    class="chat-contact"
                                    data-chat-contact
                                    data-target-user-id="<?= (int) $contact['id'] ?>"
                                    data-conversation-type="<?= htmlspecialchars($contactType, ENT_QUOTES, 'UTF-8') ?>"
                                    aria-pressed="<?= $selectedContact === $contact ? 'true' : 'false' ?>">
                                <span><?= htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if (!empty($contact['sport_name'])): ?>
                                    <small><?= htmlspecialchars($contact['sport_name'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </aside>
        <?php endif; ?>

        <div class="chat-main">
            <button type="button" class="chat-load-older" data-chat-load-older
                    aria-controls="chat-box" hidden>
                Load earlier messages
            </button>
            <div id="chat-box" class="message-list" aria-live="polite" aria-label="Messages"></div>
            <p data-chat-status role="status" aria-live="polite"></p>
            <form id="chat-form"
                  data-chat-form
                  method="post"
                  action=""
                  data-conversation-type="<?= htmlspecialchars($chatType, ENT_QUOTES, 'UTF-8') ?>"
                  data-target-user-id="<?= $chatTargetUserId === null ? '' : (int) $chatTargetUserId ?>"
                  data-current-user-id="<?= (int) $_SESSION['user_id'] ?>"
                  data-csrf-token="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                  data-requires-contact="<?= is_array($chatContacts ?? null) ? 'true' : 'false' ?>">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="conversation_type"
                       value="<?= htmlspecialchars($chatType, ENT_QUOTES, 'UTF-8') ?>">
                <label for="chat-input">Message</label>
                <div class="chat-composer">
                    <input id="chat-input" name="body" type="text" maxlength="4000" autocomplete="off" required>
                    <button type="submit" <?= is_array($chatContacts ?? null) && $chatContacts === [] ? 'disabled' : '' ?>>
                        Send
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
