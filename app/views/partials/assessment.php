<?php
/** @var array $entity */
/** @var array $sources */
$strengths = editorial_lines($entity['assessment_strengths'] ?? '');
$caveats = editorial_lines($entity['assessment_caveats'] ?? '');
?>
<section class="assessment-block">
    <div class="assessment-block__header">
        <div>
            <p class="eyebrow">Investigative assessment</p>
            <h2><?= e(assessment_status_label($entity['assessment_status'] ?? 'listed')) ?></h2>
        </div>
        <?php if (!empty($entity['reviewed_at'])): ?>
            <p class="assessment-date">Reviewed <?= e(date('j M Y', strtotime($entity['reviewed_at']))) ?></p>
        <?php endif; ?>
    </div>
    <p class="assessment-summary"><?= e($entity['assessment_summary'] ?: assessment_status_message($entity['assessment_status'] ?? 'listed')) ?></p>
    <?php if ($strengths || $caveats): ?>
        <div class="assessment-columns">
            <?php if ($strengths): ?>
                <div><h3>Reasons to consider</h3><ul><?php foreach ($strengths as $line): ?><li><?= e($line) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <?php if ($caveats): ?>
                <div><h3>Caveats</h3><ul><?php foreach ($caveats as $line): ?><li><?= e($line) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($sources): ?>
        <div class="assessment-sources">
            <h3>Sources</h3>
            <ul><?php foreach ($sources as $source): ?><li><a href="<?= e($source['url']) ?>" rel="noopener" target="_blank"><?= e($source['label'] ?: parse_url($source['url'], PHP_URL_HOST)) ?></a></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
</section>
