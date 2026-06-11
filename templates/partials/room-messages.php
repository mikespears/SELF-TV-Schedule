<?php

declare(strict_types=1);

/** @var list<array{title: string, body: string}> $messages */
/** @var string $modifier */

if ($messages === []) {
    return;
}
?>
<div class="room-announcements room-announcements--<?= e($modifier) ?>">
    <?php foreach ($messages as $message): ?>
        <article class="room-message">
            <?php if ($message['title'] !== ''): ?>
                <h2 class="room-message__title"><?= e($message['title']) ?></h2>
            <?php endif; ?>
            <?php if ($message['body'] !== ''): ?>
                <p class="room-message__body"><?= e($message['body']) ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
