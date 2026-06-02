<section class="score-board">
    <h2>Score breakdown</h2>
    <div class="score-list">
        <?php foreach ($scores as $score): ?>
            <div class="score-row">
                <span><?= e($score['name']) ?></span>
                <div class="meter" aria-hidden="true"><span style="width: <?= e((string) (((float) ($score['score'] ?? 0) / 5) * 100)) ?>%"></span></div>
                <strong><?= e($score['score'] === null ? '—' : number_format((float) $score['score'], 1)) ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
</section>
