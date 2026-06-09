<?php
$this->section('content');
?>
<div class="page-layout">
<section class="page-header about-header">
    <div>
        <p class="eyebrow">About / Brand</p>
        <h1>Brand guide</h1>
        <p class="page-intro">A practical overview of the visual identity used across Get Quality Stuff.</p>
    </div>
</section>

<section class="brand-guide-section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Logo</p>
            <h2>The Get Quality Stuff mark</h2>
        </div>
    </div>
    <div class="logo-showcase">
        <div>
            <img src="/assets/get-logo.png" alt="Get Quality Stuff logo">
        </div>
    </div>
    <p class="brand-guide-note">Give the mark room to breathe and keep it on clear, uncluttered backgrounds.</p>
</section>

<section class="brand-guide-section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Colour</p>
            <h2>Brand palette</h2>
        </div>
    </div>
    <div class="colour-grid">
        <article class="colour-swatch colour-swatch--accent">
            <span>Accent pink</span>
            <code>#ff385c</code>
        </article>
        <article class="colour-swatch colour-swatch--accent-dark">
            <span>Deep magenta</span>
            <code>#d70466</code>
        </article>
        <article class="colour-swatch colour-swatch--leaf">
            <span>Leaf green</span>
            <code>#26735b</code>
        </article>
        <article class="colour-swatch colour-swatch--ink">
            <span>Ink</span>
            <code>#222222</code>
        </article>
        <article class="colour-swatch colour-swatch--soft">
            <span>Soft grey</span>
            <code>#f7f7f7</code>
        </article>
        <article class="colour-swatch colour-swatch--paper">
            <span>Paper</span>
            <code>#ffffff</code>
        </article>
    </div>
</section>

<section class="brand-guide-section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Interface</p>
            <h2>Controls and interaction states</h2>
        </div>
    </div>
    <p class="brand-guide-note">Fields replace their existing border when active. Buttons and links keep a clear external keyboard-focus outline.</p>
    <div class="brand-guide-control-grid">
        <label>Default input <input type="text" value="Useful and direct"></label>
        <label>Focused input <input class="brand-guide-focus-demo" type="text" value="Green replaces the border"></label>
        <label>Error input <input type="text" value="Needs attention" aria-invalid="true"></label>
        <label>Dropdown <select><option>Recommended</option></select></label>
        <label>Textarea <textarea rows="3">Clear supporting detail.</textarea></label>
        <label>Disabled input <input type="text" value="Unavailable" disabled></label>
    </div>
    <div class="brand-guide-actions">
        <button type="button">Primary action</button>
        <button class="button--quiet" type="button">Quiet action</button>
        <button type="button" disabled>Disabled action</button>
        <a class="primary-link" href="#ui-statuses">Linked action</a>
    </div>
</section>

<section class="brand-guide-section" id="ui-statuses">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Interface</p>
            <h2>Cards, panels, and statuses</h2>
        </div>
    </div>
    <div class="brand-guide-example-grid">
        <article class="about-card">
            <h3>Simple card</h3>
            <p>Use cards to group a compact, self-contained piece of information.</p>
        </article>
        <article class="assessment-block">
            <h3>Supporting panel</h3>
            <p class="assessment-summary">Use tinted panels sparingly for evidence, guidance, or a meaningful status.</p>
        </article>
    </div>
    <div class="brand-guide-status-grid">
        <span class="type-tag type-tag--brand">Brand</span>
        <span class="type-tag type-tag--store">Store</span>
        <span class="type-tag type-tag--item">Item</span>
        <span class="assessment-status">Listed</span>
        <span class="assessment-status assessment-status--needs_update">Needs update</span>
    </div>
</section>

<section class="brand-guide-section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Interface</p>
            <h2>Spacing scale</h2>
        </div>
    </div>
    <p class="brand-guide-note">Use the shared spacing scale before introducing a new gap or padding value.</p>
    <div class="brand-guide-spacing-grid" aria-label="Spacing scale">
        <?php foreach ([1 => 4, 2 => 8, 3 => 12, 4 => 16, 5 => 24, 6 => 32, 7 => 48, 8 => 64] as $step => $pixels): ?>
            <div class="brand-guide-spacing-sample brand-guide-spacing-sample--<?= e((string) $step) ?>">
                <span aria-hidden="true"></span>
                <code><?= e((string) $pixels) ?>px</code>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="brand-guide-section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Typography</p>
            <h2>Type styles</h2>
        </div>
    </div>
    <div class="type-grid">
        <article class="type-sample type-sample--display">
            <p class="eyebrow">Display / Unbounded</p>
            <p class="type-sample__large">Quality is worth finding.</p>
            <p>Used in bold weights for major headings and expressive moments.</p>
        </article>
        <article class="type-sample">
            <p class="eyebrow">Body / Inter and system sans-serif</p>
            <p class="type-sample__body">Clear, approachable text keeps useful information easy to scan and understand.</p>
            <p>Used for navigation, descriptions, labels, and interface controls.</p>
        </article>
    </div>
</section>

<section class="brand-guide-section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Principles</p>
            <h2>How the brand should feel</h2>
        </div>
    </div>
    <div class="about-grid">
        <article class="about-card">
            <h3>Useful first</h3>
            <p>Design should make it easier to discover, compare, and remember quality choices.</p>
        </article>
        <article class="about-card">
            <h3>Confident, not loud</h3>
            <p>Use bold display type and colour with restraint, leaving generous space around content.</p>
        </article>
        <article class="about-card">
            <h3>Warm and direct</h3>
            <p>Write plainly, avoid inflated claims, and help people make their own informed decisions.</p>
        </article>
    </div>
</section>
</div>
