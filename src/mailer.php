<?php declare(strict_types=1);

// Load the Composer dependencies installed by the Dockerfile
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use Brevo\Brevo;
use Brevo\TransactionalEmails\Requests\SendTransacEmailRequest;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestSender;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem;
use Brevo\Exceptions\BrevoApiException;

/** 
 * Send a single transactional email
 */
function send_transactional_email(string $toEmail, string $toName, string $subject, string $htmlContent): void
{
    // The V5 SDK initializes directly with the API key
    $client = new Brevo(env('BREVO_API_KEY'));

    $request = new SendTransacEmailRequest([
        'subject' => $subject,
        'htmlContent' => $htmlContent,
        'sender' => new SendTransacEmailRequestSender([
            'name' => 'LazyLedger Support',
            
            // IMPORTANT: Change this back to your verified Brevo email!
            'email' => 'deguzzzy0827@gmail.com' 
        ]),
        'to' => [
            new SendTransacEmailRequestToItem([
                'email' => $toEmail,
                'name' => $toName
            ])
        ]
    ]);

    try {
        $client->transactionalEmails->sendTransacEmail($request);
    } catch (BrevoApiException $e) {
        // V5 provides a getBody() method to see exactly why Brevo rejected it
        error_log('Brevo API Error: ' . $e->getMessage() . ' - Details: ' . $e->getBody());
    } catch (Exception $e) {
        error_log('General Mailer Error: ' . $e->getMessage());
    }
}