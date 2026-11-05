<?php

declare(strict_types=1);

require_once __DIR__ . '/UserInputException.php';

use Postal\Client;
use Postal\Send\Message;

final class Mailer
{
    public static function send(string $to, string $subject, string $html, string $text, ?string $replyTo = null): void
    {
        $postalApiUrl = rtrim((string) ($_ENV['POSTAL_API_URL'] ?? ''), '/');
        $postalApiKey = (string) ($_ENV['POSTAL_API_KEY'] ?? '');
        if ($postalApiUrl === '' || $postalApiKey === '') {
            throw new UserInputException('Configuration email non disponible.');
        }

        try {
            $client = new Client($postalApiUrl, $postalApiKey);
            $mail = new Message();
            $mail->to($to);
            $mail->from('24h des Quais de Bordeaux <contact@24hdesquais.fr>');
            if ($replyTo !== null && $replyTo !== '') {
                $mail->replyTo($replyTo);
            }
            $mail->subject($subject);
            $mail->htmlBody($html);
            $mail->plainBody($text);
            $client->send->message($mail);
        } catch (UserInputException $e) {
            throw $e;
        } catch (Throwable $e) {
            error_log('Erreur envoi email: ' . $e->getMessage());
            throw new UserInputException('Erreur lors de l\'envoi du message.');
        }
    }

    public static function sendLoginCode(string $to, string $code): void
    {
        $safe = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $html = '<p>Voici ton code pour les 24h des Quais&nbsp;:</p>'
            . '<p style="font-size:28px;font-weight:bold;letter-spacing:4px;">' . $safe . '</p>'
            . '<p>Il est valable 10 minutes.</p>';
        $text = "Voici ton code pour les 24h des Quais : {$code}\n\nIl est valable 10 minutes.";

        self::send($to, 'Ton code — 24h des Quais', $html, $text);
    }
}
