<fieldset class="score-fields">
    <legend>Scores</legend>
    <?php foreach ($scores as $score): ?>
        <label>
            <?= e($score['name']) ?>
            <input type="number" min="0" max="5" step="0.1" name="scores[<?= e($score['slug']) ?>]" value="<?= e($score['score'] === null ? '' : (string) $score['score']) ?>">
        </label>
    <?php endforeach; ?>
</fieldset>
