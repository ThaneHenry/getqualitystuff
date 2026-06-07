<?php
/** @var string $feedbackType */
/** @var string|null $feedbackEntityType */
/** @var int|null $feedbackEntityId */
?>
<details class="feedback-form">
    <summary><?= icon_markup($feedbackType === 'suggest_brand' ? 'suggest' : 'report') ?> <?= $feedbackType === 'suggest_brand' ? 'Suggest a brand' : 'Report outdated information' ?></summary>
    <form action="/feedback" method="post" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="type" value="<?= e($feedbackType) ?>">
        <input type="hidden" name="entity_type" value="<?= e($feedbackEntityType ?? '') ?>">
        <input type="hidden" name="entity_id" value="<?= e((string) ($feedbackEntityId ?? '')) ?>">
        <input type="hidden" name="redirect" value="<?= e(current_request_path()) ?>">
        <label>Email, optional <input type="email" name="contact_email" autocomplete="email"></label>
        <label>Message <textarea name="message" rows="4" required></textarea></label>
        <button type="submit">Send for review</button>
    </form>
</details>
