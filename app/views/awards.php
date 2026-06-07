<?php /** @var array $awards */ ?>
<section class="page-header awards-header">
    <div>
        <p class="eyebrow">GQS awards</p>
        <h1>Recognition for specific strengths.</h1>
        <p class="page-intro">Get Quality Stuff awards highlight a clear, investigated reason to consider a brand. They are based on available evidence, not hands-on product testing.</p>
    </div>
</section>

<section class="awards-note">
    <h2>Focused, evidence-led, and reviewable</h2>
    <p>An award is not a blanket endorsement. It recognises one particular strength where we find credible supporting evidence. Awards may be revised or removed when practices change or information becomes outdated.</p>
</section>

<section class="awards-grid" aria-label="Get Quality Stuff awards">
    <?php foreach ($awards as $award): ?>
        <article class="award-card" id="<?= e($award['slug']) ?>">
            <img src="/assets/awards/placeholder.svg" alt="">
            <div>
                <p class="eyebrow">Get Quality Stuff award</p>
                <h2><?= e($award['name']) ?></h2>
                <p><?= e($award['description']) ?></p>
                <p class="award-card__criteria"><strong>What it recognises:</strong> <?= e($award['criteria']) ?></p>
                <div class="award-card__downloads">
                    <a class="button button--quiet" href="/assets/awards/placeholder.svg" download="<?= e($award['slug']) ?>.svg">Download SVG</a>
                    <a class="button button--quiet" href="/assets/awards/placeholder.png" download="<?= e($award['slug']) ?>.png">Download PNG</a>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</section>
