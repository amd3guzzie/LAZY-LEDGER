<?php declare(strict_types=1);

// Load the Composer dependencies installed by the Dockerfile
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/http.php';

use Brevo\Brevo;
use Brevo\TransactionalEmails\Requests\SendTransacEmailRequest;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestSender;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem;
use Brevo\Exceptions\BrevoApiException;

/**
 * Send a single transactional email. Never throws: a failed email must not fail the request
 * (the user row, ticket reply, etc. has already been saved by the time we get here).
 */
function send_transactional_email(string $toEmail, string $toName, string $subject, string $htmlContent): void
{
    $apiKey = env('BREVO_API_KEY');
    if ($apiKey === null) {
        error_log("Mailer: BREVO_API_KEY is not set; skipped \"$subject\" to $toEmail");
        return;
    }

    try {
        // The V5 SDK initializes directly with the API key
        $client = new Brevo($apiKey);

        $request = new SendTransacEmailRequest([
            'subject' => $subject,
            'htmlContent' => $htmlContent,
            'sender' => new SendTransacEmailRequestSender([
                'name' => env('MAIL_FROM_NAME', 'LazyLedger Support'),
                // Must be a sender verified in Brevo.
                'email' => env('MAIL_FROM_EMAIL', 'deguzzzy0827@gmail.com'),
            ]),
            'to' => [
                new SendTransacEmailRequestToItem([
                    'email' => $toEmail,
                    'name' => $toName
                ])
            ]
        ]);

        $client->transactionalEmails->sendTransacEmail($request);
    } catch (BrevoApiException $e) {
        // V5 provides a getBody() method to see exactly why Brevo rejected it
        error_log('Brevo API Error: ' . $e->getMessage() . ' - Details: ' . $e->getBody());
    } catch (Throwable $e) {
        error_log('General Mailer Error: ' . $e->getMessage());
    }
}

/**
 * Public base URL for links in emails, from APP_URL (or Railway's RAILWAY_PUBLIC_DOMAIN).
 * Deliberately not taken from the request's Host header, which a client can forge.
 */
function app_url(): ?string
{
    $url = env('APP_URL');
    if ($url === null && env('RAILWAY_PUBLIC_DOMAIN') !== null) {
        $url = 'https://' . env('RAILWAY_PUBLIC_DOMAIN');
    }
    return $url === null ? null : rtrim($url, '/');
}

/**
 * Wrap email content in the LazyLedger layout. $bodyHtml must already be escaped.
 * The button is only shown when a public URL is configured.
 */
function email_layout(string $heading, string $bodyHtml, ?string $buttonText = null, string $path = '/'): string
{
    $button = '';
    $base = app_url();
    if ($buttonText !== null && $base !== null) {
        $button = '<p style="margin:24px 0 8px"><a href="' . e($base . $path) . '" style="background:#c14f27;color:#ffffff;'
            . 'padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block">'
            . e($buttonText) . '</a></p>';
    }
    return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#3d3d3d">'
        . '<h2 style="color:#c14f27;margin-bottom:4px">LAZY LEDGER</h2>'
        . '<h1 style="font-size:22px;margin-top:8px">' . e($heading) . '</h1>'
        . $bodyHtml . $button
        . '<p style="font-size:12px;color:#888;margin-top:32px">You received this email because you have a LazyLedger account. '
        . 'This is an automated message; to reply, open a support ticket from your dashboard.</p></div>';
}
