<?php

declare(strict_types=1);

const ITEM_IMAGE_MAX_BYTES = 20_000_000;
const ITEM_IMAGE_MAX_PIXELS = 40_000_000;
const ITEM_IMAGE_DETAIL_SIZE = 1200;
const ITEM_IMAGE_THUMBNAIL_SIZE = 240;

function item_image_private_dir(int $itemId): string
{
    return config()['base_path'] . '/storage/item-images/' . $itemId;
}

function item_image_public_dir(int $itemId): string
{
    return config()['base_path'] . '/public/uploads/item-images/' . $itemId;
}

function item_image_original_path(int $itemId): string
{
    return item_image_private_dir($itemId) . '/original';
}

function item_image_variant_path(int $itemId, string $variant): string
{
    if (!in_array($variant, ['detail', 'thumbnail'], true)) {
        throw new InvalidArgumentException('Unknown item image variant.');
    }

    return item_image_public_dir($itemId) . '/' . $variant . '.webp';
}

function item_image_url(int $itemId, string $variant): string
{
    return is_file(item_image_variant_path($itemId, $variant))
        ? '/uploads/item-images/' . $itemId . '/' . $variant . '.webp'
        : '';
}

function item_image_files_complete(int $itemId): bool
{
    return is_file(item_image_original_path($itemId))
        && is_file(item_image_variant_path($itemId, 'detail'))
        && is_file(item_image_variant_path($itemId, 'thumbnail'));
}

function remove_item_images(int $itemId): void
{
    foreach ([item_image_original_path($itemId), item_image_variant_path($itemId, 'detail'), item_image_variant_path($itemId, 'thumbnail')] as $path) {
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Unable to remove item image file.');
        }
    }

    foreach ([item_image_private_dir($itemId), item_image_public_dir($itemId)] as $dir) {
        if (is_dir($dir)) {
            @rmdir($dir);
        }
    }
}

function schedule_item_image_processing(int $itemId, string $sourceUrl, bool $sourceChanged = false): void
{
    if (db()->inTransaction()) {
        $queue = item_image_processing_queue();
        $queue[$itemId] = [
            'url' => $sourceUrl,
            'changed' => $sourceChanged || (bool) ($queue[$itemId]['changed'] ?? false),
        ];
        item_image_processing_queue($queue);
        return;
    }

    sync_item_image($itemId, $sourceUrl, $sourceChanged);
}

function sync_item_image(int $itemId, string $sourceUrl, bool $sourceChanged): void
{
    try {
        if ($sourceChanged || $sourceUrl === '') {
            remove_item_images($itemId);
        }
        if ($sourceUrl === '' || (!$sourceChanged && item_image_files_complete($itemId))) {
            return;
        }
        process_item_image($itemId, $sourceUrl);
    } catch (Throwable $e) {
        error_log("Item image {$itemId}: " . $e->getMessage());
    }
}

function flush_item_image_processing_queue(): array
{
    $results = [];
    $queue = item_image_processing_queue();
    item_image_processing_queue([]);
    foreach ($queue as $itemId => $job) {
        try {
            sync_item_image((int) $itemId, (string) $job['url'], (bool) $job['changed']);
            $results[$itemId] = null;
        } catch (Throwable $e) {
            $results[$itemId] = $e->getMessage();
            error_log("Item image {$itemId}: " . $e->getMessage());
        }
    }

    return $results;
}

function item_image_processing_queue(?array $replace = null): array
{
    static $queue = [];
    if ($replace !== null) {
        $queue = $replace;
    }
    return $queue;
}

function process_item_image(int $itemId, string $sourceUrl, bool $force = false): void
{
    if (!$force && item_image_files_complete($itemId)) {
        return;
    }
    if (!function_exists('imagewebp')) {
        throw new RuntimeException('PHP GD with WebP support is required.');
    }

    $downloadPath = download_item_image($sourceUrl);
    try {
        $bytes = file_get_contents($downloadPath);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Downloaded image is empty.');
        }
        $info = @getimagesizefromstring($bytes);
        if (!is_array($info) || empty($info[0]) || empty($info[1])) {
            throw new RuntimeException('Downloaded file is not a supported image.');
        }
        if ((int) $info[0] * (int) $info[1] > ITEM_IMAGE_MAX_PIXELS) {
            throw new RuntimeException('Downloaded image dimensions are too large.');
        }
        $source = @imagecreatefromstring($bytes);
        if (!$source instanceof GdImage) {
            throw new RuntimeException('Downloaded image could not be decoded.');
        }

        ensure_item_image_dir(item_image_private_dir($itemId));
        ensure_item_image_dir(item_image_public_dir($itemId));
        $detailTemp = temporary_path(item_image_public_dir($itemId), 'detail');
        $thumbnailTemp = temporary_path(item_image_public_dir($itemId), 'thumbnail');
        try {
            write_item_image_variant($source, $detailTemp, ITEM_IMAGE_DETAIL_SIZE, 88);
            write_item_image_variant($source, $thumbnailTemp, ITEM_IMAGE_THUMBNAIL_SIZE, 82);
            atomic_replace($downloadPath, item_image_original_path($itemId));
            atomic_replace($detailTemp, item_image_variant_path($itemId, 'detail'));
            atomic_replace($thumbnailTemp, item_image_variant_path($itemId, 'thumbnail'));
        } finally {
            unset($source);
            @unlink($detailTemp);
            @unlink($thumbnailTemp);
        }
    } finally {
        @unlink($downloadPath);
    }
}

function download_item_image(string $url): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL is required to download item images.');
    }

    for ($redirects = 0; $redirects <= 3; $redirects++) {
        $target = validate_public_image_url($url);
        $resolveIp = str_contains($target['ip'], ':') ? '[' . $target['ip'] . ']' : $target['ip'];
        $temp = tempnam(sys_get_temp_dir(), 'gqs-image-');
        if ($temp === false) {
            throw new RuntimeException('Unable to create image download file.');
        }
        $handle = fopen($temp, 'wb');
        if ($handle === false) {
            @unlink($temp);
            throw new RuntimeException('Unable to open image download file.');
        }

        $headers = [];
        $bytes = 0;
        $curl = curl_init($url);
        $options = [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'GetQualityStuff/1.0 (+https://getqualitystuff.com)',
            CURLOPT_HTTPHEADER => ['Accept: image/*'],
            CURLOPT_RESOLVE => [$target['host'] . ':' . $target['port'] . ':' . $resolveIp],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$headers): int {
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($handle, &$bytes): int {
                $bytes += strlen($chunk);
                if ($bytes > ITEM_IMAGE_MAX_BYTES) {
                    return 0;
                }
                return fwrite($handle, $chunk);
            },
        ];
        $caInfo = item_image_ca_info();
        if ($caInfo !== '') {
            $options[CURLOPT_CAINFO] = $caInfo;
        }
        curl_setopt_array($curl, $options);
        $ok = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        unset($curl);
        fclose($handle);

        if ($status >= 300 && $status < 400 && isset($headers['location'])) {
            @unlink($temp);
            $url = absolutize_url($headers['location'], $url);
            continue;
        }
        if ($ok === false || $status < 200 || $status >= 300) {
            @unlink($temp);
            throw new RuntimeException($bytes > ITEM_IMAGE_MAX_BYTES ? 'Remote image exceeds the size limit.' : 'Remote image download failed: ' . ($error ?: "HTTP {$status}"));
        }

        return $temp;
    }

    throw new RuntimeException('Remote image redirected too many times.');
}

function item_image_ca_info(): string
{
    $locations = function_exists('openssl_get_cert_locations') ? openssl_get_cert_locations() : [];
    $candidates = array_merge(
        ['/etc/ssl/cert.pem', '/opt/homebrew/etc/openssl@3/cert.pem', '/usr/local/etc/openssl@3/cert.pem'],
        array_filter([$locations['default_cert_file'] ?? null, $locations['ini_cafile'] ?? null])
    );
    foreach (array_unique($candidates) as $path) {
        if (is_string($path) && is_readable($path)) {
            return $path;
        }
    }
    return '';
}

function validate_public_image_url(string $url): array
{
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host'])) {
        throw new InvalidArgumentException('Item image URL must use HTTP or HTTPS.');
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
        throw new InvalidArgumentException('Item image URL cannot contain credentials.');
    }

    $host = trim((string) $parts['host'], '[]');
    $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
    if ($ips === []) {
        throw new RuntimeException('Item image host could not be resolved.');
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new InvalidArgumentException('Item image host must resolve to a public address.');
        }
    }

    return [
        'host' => $host,
        'port' => (int) ($parts['port'] ?? (strtolower((string) $parts['scheme']) === 'https' ? 443 : 80)),
        'ip' => $ips[0],
    ];
}

function write_item_image_variant(GdImage $source, string $path, int $maxSize, int $quality): void
{
    $width = imagesx($source);
    $height = imagesy($source);
    $scale = min(1, $maxSize / max($width, $height));
    $targetWidth = max(1, (int) round($width * $scale));
    $targetHeight = max(1, (int) round($height * $scale));
    $target = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$target instanceof GdImage) {
        throw new RuntimeException('Unable to allocate resized image.');
    }

    imagealphablending($target, false);
    imagesavealpha($target, true);
    $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
    imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
    if (!imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height) || !imagewebp($target, $path, $quality)) {
        unset($target);
        throw new RuntimeException('Unable to write resized item image.');
    }
    unset($target);
}

function ensure_item_image_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create item image directory.');
    }
}

function temporary_path(string $dir, string $prefix): string
{
    $path = tempnam($dir, $prefix . '-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary image file.');
    }
    return $path;
}

function atomic_replace(string $source, string $destination): void
{
    if (!rename($source, $destination)) {
        throw new RuntimeException('Unable to install item image file.');
    }
    @chmod($destination, 0664);
}
