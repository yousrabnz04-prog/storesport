<?php
/**
 * Renders a conversation. Expects $thread (array of message rows) and $self ('user' or 'admin').
 */
?>
<div id="chatMessages" class="chat-box" data-self="<?= e($self) ?>" data-poll-url="<?= e(url('api/messages-poll.php')) ?>"<?= isset($threadUserId) ? ' data-thread-user="' . (int) $threadUserId . '"' : '' ?>>
    <?php if (!$thread): ?>
        <p class="text-muted text-center my-auto js-chat-empty">No messages yet.</p>
    <?php endif; ?>
    <?php foreach ($thread as $m): ?>
        <div class="chat-msg <?= $m['sender_type'] === $self ? 'me' : 'them' ?>" data-id="<?= (int) $m['id'] ?>">
            <?php if ($m['message'] !== ''): ?><div><?= nl2br(e($m['message'])) ?></div><?php endif; ?>
            <?php if (!empty($m['file_path'])): ?>
                <div><a href="<?= e(url('download.php?id=' . (int) $m['id'])) ?>" class="chat-file"><i class="fas fa-paperclip"></i> <?= e($m['file_name'] ?: 'Attachment') ?></a></div>
            <?php endif; ?>
            <div class="chat-time"><?= e(date('M j, H:i', strtotime($m['created_at']))) ?></div>
        </div>
    <?php endforeach; ?>
</div>
