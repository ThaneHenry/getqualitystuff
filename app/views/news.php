<?php
/** @var array $articles */
?>
<div class="page-layout">
<section class="page-header">
    <div>
        <p class="eyebrow">News</p>
        <h1>Latest from Get Quality Stuff</h1>
    </div>
</section>

<section class="news-list">
    <?php foreach ($articles as $article): ?>
        <article class="news-card news-card--full">
            <div>
                <time datetime="<?= e($article['published_at']) ?>"><?= e(date('j M Y', strtotime($article['published_at']))) ?></time>
                <h2><?= e($article['title']) ?></h2>
                <p><?= e($article['body'] ?: $article['excerpt']) ?></p>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$articles): ?>
        <article class="empty-state">
            <h2>No news yet</h2>
            <p>Updates will appear here soon.</p>
        </article>
    <?php endif; ?>
</section>
</div>