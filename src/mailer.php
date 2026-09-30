<?php declare(strict_types=1);

// Load the Composer dependencies installed by the Dockerfile
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

function get_brevo_config()
{
    $config = Brevo\Client\Configuration::getDefaultConfiguration();
    return $config->setApiKey('api-key', env('BREVO_API_KEY'));
}

/** 
 * Create a scheduled marketing campaign
 */
function create_email_campaign(): void
{
    $apiInstance = new Brevo\Client\Api\EmailCampaignsApi(new GuzzleHttp\Client(), get_brevo_config());
    $campaign = new \Brevo\Client\Model\CreateEmailCampaign([
        'name' => 'Campaign sent via the API',
        'subject' => 'My subject',
        'sender' => ['name' => 'LazyLedger', 'email' => 'hello@lazyledger.test'],
        'type' => 'classic',
        'htmlContent' => 'Congratulations! You successfully sent this example campaign via the Brevo API.',
        'recipients' => ['listIds' => [2, 7]],
        'scheduledAt' => '2026-10-01T00:00:01+08:00' // Formatted as ISO8601
    ]);

    try {
        $apiInstance->createEmailCampaign($campaign);
    } catch (Exception $e) {
        error_log('Brevo Campaign Error: ' . $e->getMessage());
    }
}

/** 
 * Send a single transactional email
 */
function send_transactional_email(string $toEmail, string $toName, string $subject, string $htmlContent): void
{
    $apiInstance = new Brevo\Client\Api\TransactionalEmailsApi(new GuzzleHttp\Client(), get_brevo_config());
    $email = new \Brevo\Client\Model\SendSmtpEmail([
        'sender' => ['name' => 'LazyLedger Support', 'email' => 'support@lazyledger.test'],
        'to' => [['email' => $toEmail, 'name' => $toName]],
        'subject' => $subject,
        'htmlContent' => $htmlContent
    ]);

    try {
        $apiInstance->sendTransacEmail($email);
    } catch (Exception $e) {
        error_log('Brevo Transactional Error: ' . $e->getMessage());
    }
}