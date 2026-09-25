<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

final class EmailService
{
    private const BREVO_API_URL = 'https://api.brevo.com/v3/smtp/email';

    public function sendPasswordReset(string $toEmail, string $toName, string $resetLink): bool
    {
        $apiKey = kickoff_env('BREVO_API_KEY', '');
        $senderEmail = kickoff_env('BREVO_SENDER_EMAIL', 'noreply@kickoff.example.com');
        $senderName = kickoff_env('BREVO_SENDER_NAME', SITE_NAME);

        if ($apiKey === '') {
            Logger::warning('EmailService', 'BREVO_API_KEY is not configured. Email not sent.', ['to' => $toEmail]);
            // In dev environment, just pretend it sent so testing can proceed without API key
            if (APP_ENV !== 'production') {
                Logger::info('EmailService', 'DEV MODE: Simulated password reset email', ['link' => $resetLink]);
                return true;
            }
            return false;
        }

        $htmlContent = "
        <div style=\"font-family: sans-serif; max-width: 600px; margin: 0 auto;\">
            <h2>" . htmlspecialchars(SITE_NAME) . " Password Reset</h2>
            <p>Hello " . htmlspecialchars($toName) . ",</p>
            <p>We received a request to reset your password. Click the secure link below to choose a new password.</p>
            <p><a href=\"" . htmlspecialchars($resetLink) . "\" style=\"display: inline-block; padding: 10px 20px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 4px;\">Reset Password</a></p>
            <p>This link will expire soon. If you did not request this reset, you can safely ignore this email.</p>
            <p>Best,<br>The " . htmlspecialchars(SITE_NAME) . " Team</p>
        </div>";

        $payload = [
            'sender' => ['name' => $senderName, 'email' => $senderEmail],
            'to' => [['email' => $toEmail, 'name' => $toName]],
            'subject' => SITE_NAME . ' Password Reset',
            'htmlContent' => $htmlContent
        ];

        return $this->sendBrevoRequest($payload, $apiKey);
    }

    private function sendBrevoRequest(array $payload, string $apiKey, int $maxRetries = 2): bool
    {
        $ch = curl_init(self::BREVO_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            $attempt++;
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($response === false) {
                Logger::error('EmailService', 'Curl error sending Brevo email', ['error' => curl_error($ch), 'attempt' => $attempt]);
                sleep((int) pow(2, $attempt)); // Exponential backoff
                continue;
            }
            
            if ($httpCode >= 200 && $httpCode < 300) {
                Logger::info('EmailService', 'Email sent successfully via Brevo', ['response' => $response]);
                curl_close($ch);
                return true;
            }
            
            Logger::error('EmailService', 'Brevo API error', ['http_code' => $httpCode, 'response' => $response, 'attempt' => $attempt]);
            
            // Permanent errors (4xx) shouldn't be retried
            if ($httpCode >= 400 && $httpCode < 500 && $httpCode !== 429) {
                curl_close($ch);
                return false;
            }
            
            sleep((int) pow(2, $attempt));
        }
        
        curl_close($ch);
        return false;
    }
}
