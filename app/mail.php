<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function send_app_mail(string $to, string $subject, string $body): bool
{
    $config = config();
    $from = $config['mail_from'];

    if ($config['mail_transport'] === 'mail') {
        return mail($to, $subject, $body, [
            'From' => $from,
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    $line = implode("\n", [
        '--- ' . date(DATE_ATOM) . ' ---',
        'To: ' . $to,
        'From: ' . $from,
        'Subject: ' . $subject,
        '',
        $body,
        '',
    ]);

    return file_put_contents(config()['base_path'] . '/storage/mail.log', $line, FILE_APPEND | LOCK_EX) !== false;
}

function absolute_url(string $path): string
{
    return config()['app_url'] . '/' . ltrim($path, '/');
}
