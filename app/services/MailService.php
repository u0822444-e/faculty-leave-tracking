<?php
require_once __DIR__ . '/../../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../libs/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../../libs/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    private PHPMailer $mail;
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/mail.php';
        $this->mail = new PHPMailer(true);
        $this->setup();
    }

    private function setup(): void
    {
        try {
            $this->mail->isSMTP();
            $this->mail->Host       = $this->config['host'];
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = $this->config['username'];
            $this->mail->Password   = $this->config['password'];
            $this->mail->SMTPSecure = $this->config['encryption'];
            $this->mail->Port       = $this->config['port'];
            $this->mail->CharSet    = 'UTF-8';

            $this->mail->setFrom(
                $this->config['from_email'],
                $this->config['from_name']
            );
        } catch (Exception $e) {
            error_log('Mail setup failed: ' . $e->getMessage());
        }
    }

    public function to(string $email, string $name = ''): self
    {
        $this->mail->addAddress($email, $name);
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->mail->Subject = $subject;
        return $this;
    }

    public function body(string $html): self
    {
        $this->mail->isHTML(true);
        $this->mail->Body    = $html;
        $this->mail->AltBody = strip_tags($html);
        return $this;
    }

    public function send(): bool
    {
        try {
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mail send failed: ' . $this->mail->ErrorInfo);
            return false;
        }
    }
}