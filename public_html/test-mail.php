<?php
require_once __DIR__ . '/../app/services/MailService.php';

$mailer = new MailService();
$sent = $mailer
    ->to('your-test@email.com', 'Test User')
    ->subject('PHPMailer Test')
    ->body('<h1>It works!</h1><p>If you see this, PHPMailer is configured correctly.</p>')
    ->send();

echo $sent ? 'Sent!' : 'Failed — check error_log.';