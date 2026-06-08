<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$stylePaths = [
    'public/assets/styles/tokens.css',
    'public/assets/styles/base.css',
    'public/assets/styles/components.css',
    'public/assets/styles/pages.css',
];
$errors = [];

foreach ($stylePaths as $path) {
    $contents = file_get_contents($root . '/' . $path);
    if ($contents === false) {
        $errors[] = "Missing stylesheet: {$path}";
        continue;
    }

    if (!css_braces_are_balanced($contents)) {
        $errors[] = "Unbalanced CSS blocks: {$path}";
    }

    if ($path !== $stylePaths[0] && preg_match('/#[0-9a-f]{3,8}\b|rgba?\(/i', $contents)) {
        $errors[] = "Raw colour value outside tokens.css: {$path}";
    }

    if ($path !== $stylePaths[0] && preg_match_all('/box-shadow:\s*([^;]+);/i', $contents, $shadowMatches)) {
        foreach ($shadowMatches[1] as $shadow) {
            $shadow = trim($shadow);
            if ($shadow !== 'none' && !str_starts_with($shadow, 'var(') && !str_starts_with($shadow, 'inset ')) {
                $errors[] = "Raw shadow value outside tokens.css: {$path}";
                break;
            }
        }
    }
}

$layout = file_get_contents($root . '/app/views/layout.php') ?: '';
$lastPosition = -1;
foreach ($stylePaths as $path) {
    $publicPath = '/' . preg_replace('#^public/#', '', $path);
    $position = strpos($layout, $publicPath);
    if ($position === false) {
        $errors[] = "Stylesheet is not loaded by layout.php: {$publicPath}";
        continue;
    }
    if ($position < $lastPosition) {
        $errors[] = "Stylesheets are loaded out of order in layout.php";
        break;
    }
    $lastPosition = $position;
}

if (is_file($root . '/public/assets/styles.css')) {
    $errors[] = 'Obsolete combined stylesheet still exists: public/assets/styles.css';
}

$selectors = [];
foreach (array_slice($stylePaths, 1) as $path) {
    $contents = file_get_contents($root . '/' . $path) ?: '';
    foreach (top_level_selectors($contents) as $selector) {
        $selectors[$selector][] = $path;
    }
}
foreach ($selectors as $selector => $paths) {
    if (count($paths) > 1) {
        $errors[] = "Duplicate non-responsive selector: {$selector}";
    }
}

$inlineStyles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app/views'));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $relativePath = substr($file->getPathname(), strlen($root) + 1);
    $contents = file_get_contents($file->getPathname()) ?: '';
    if (str_contains($contents, 'style=') && $relativePath !== 'app/views/partials/scores.php') {
        $inlineStyles[] = $relativePath;
    }
}
foreach ($inlineStyles as $path) {
    $errors[] = "Unexpected inline style: {$path}";
}

if ($errors) {
    fwrite(STDERR, "CSS audit failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "CSS audit passed.\n";

function css_braces_are_balanced(string $css): bool
{
    $depth = 0;
    $length = strlen($css);
    for ($i = 0; $i < $length; $i++) {
        if ($css[$i] === '{') {
            $depth++;
        } elseif ($css[$i] === '}') {
            $depth--;
            if ($depth < 0) {
                return false;
            }
        }
    }
    return $depth === 0;
}

/** @return list<string> */
function top_level_selectors(string $css): array
{
    $css = preg_replace('#/\*.*?\*/#s', '', $css) ?: '';
    $selectors = [];
    $depth = 0;
    $start = 0;
    $length = strlen($css);

    for ($i = 0; $i < $length; $i++) {
        if ($css[$i] === '{') {
            $header = trim(preg_replace('/\s+/', ' ', substr($css, $start, $i - $start)) ?: '');
            if ($depth === 0 && $header !== '' && !str_starts_with($header, '@')) {
                $selectors[] = $header;
            }
            $depth++;
            $start = $i + 1;
        } elseif ($css[$i] === '}') {
            $depth--;
            $start = $i + 1;
        }
    }

    return $selectors;
}
