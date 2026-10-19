<?php

require_once __DIR__ . '/Env.php';

class EmailTemplates
{
    public static function welcome(
        string $fullName,
        string $username,
        string $tempPassword
    ): string {
        $appUrl = rtrim(Env::get('APP_URL', 'http://localhost'), '/');

        return "
        <div style=\"font-family: 'Inter', Arial, sans-serif; max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #E0E0E0; border-radius: 12px; overflow: hidden;\">
            <div style=\"background: linear-gradient(to bottom, #0F5E3D, #0a4a2f); padding: 32px 24px; text-align: center; color: white;\">
                <h1 style=\"margin: 0; font-size: 20px; font-weight: 700; letter-spacing: 0.5px;\">
                    Dr. Aurelio Mendoza Memorial Colleges
                </h1>
                <p style=\"margin: 8px 0 0; font-size: 13px; opacity: 0.8;\">
                    Faculty Leave & Payroll System
                </p>
            </div>

            <div style=\"padding: 32px 24px; color: #2C3E50;\">
                <h2 style=\"margin: 0 0 8px; font-size: 18px;\">Welcome, {$fullName}!</h2>
                <p style=\"font-size: 14px; line-height: 1.6; color: #2C3E50;\">
                    Your account has been created. Here are your login credentials:
                </p>

                <div style=\"background: #F1FDF6; border: 1px solid #E0E0E0; border-radius: 8px; padding: 16px; margin: 20px 0;\">
                    <p style=\"margin: 0 0 8px; font-size: 13px;\">
                        <strong>Username:</strong><br>
                        <span style=\"font-family: monospace; font-size: 14px; color: #0F5E3D;\">{$username}</span>
                    </p>
                    <p style=\"margin: 0; font-size: 13px;\">
                        <strong>Temporary Password:</strong><br>
                        <span style=\"font-family: monospace; font-size: 14px; color: #0F5E3D;\">{$tempPassword}</span>
                    </p>
                </div>

                <p style=\"font-size: 13px; color: #2C3E50; opacity: 0.7;\">
                    For security reasons, please change your password after your first login.
                </p>

                <a href=\"{$appUrl}/login\" style=\"display: inline-block; margin-top: 20px; background: #0F5E3D; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px;\">
                    Sign In
                </a>
            </div>

            <div style=\"background: #f9fafb; padding: 16px 24px; text-align: center; font-size: 11px; color: #2C3E50; opacity: 0.5; border-top: 1px solid #E0E0E0;\">
                © " . date('Y') . " Dr. Aurelio Mendoza Memorial Colleges. All rights reserved.
            </div>
        </div>
        ";
    }

    public static function passwordReset(string $name, string $resetLink): string
    {
        return "
    <div style=\"font-family: 'Inter', Arial, sans-serif; max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #E0E0E0; border-radius: 12px; overflow: hidden;\">
        <div style=\"background: linear-gradient(to bottom, #0F5E3D, #0a4a2f); padding: 32px 24px; text-align: center; color: white;\">
            <h1 style=\"margin: 0; font-size: 20px; font-weight: 700; letter-spacing: 0.5px;\">
                Dr. Aurelio Mendoza Memorial Colleges
            </h1>
            <p style=\"margin: 8px 0 0; font-size: 13px; opacity: 0.8;\">
                Faculty Leave & Payroll System
            </p>
        </div>

        <div style=\"padding: 32px 24px; color: #2C3E50;\">
            <h2 style=\"margin: 0 0 8px; font-size: 18px;\">Password Reset Request</h2>
            <p style=\"font-size: 14px; line-height: 1.6; color: #2C3E50;\">
                Hi {$name}, we received a request to reset your password. Click the button below to choose a new one.
            </p>

            <div style=\"text-align: center; margin: 28px 0;\">
                <a href=\"{$resetLink}\" style=\"display: inline-block; background: #0F5E3D; color: white; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px;\">
                    Reset Password
                </a>
            </div>

            <div style=\"background: #F1FDF6; border: 1px solid #E0E0E0; border-radius: 8px; padding: 14px; margin: 20px 0;\">
                <p style=\"margin: 0; font-size: 12px; color: #2C3E50; opacity: 0.8;\">
                    <strong>This link expires in 30 minutes.</strong><br>
                    If you did not request a password reset, you can safely ignore this email.
                </p>
            </div>

            <p style=\"font-size: 12px; color: #2C3E50; opacity: 0.6; word-break: break-all;\">
                Or copy this link:<br>
                <span style=\"font-family: monospace; font-size: 11px;\">{$resetLink}</span>
            </p>
        </div>

        <div style=\"background: #f9fafb; padding: 16px 24px; text-align: center; font-size: 11px; color: #2C3E50; opacity: 0.5; border-top: 1px solid #E0E0E0;\">
            © " . date('Y') . " Dr. Aurelio Mendoza Memorial Colleges. All rights reserved.
        </div>
    </div>
    ";
    }

    /**
     * Password-changed confirmation.
     *
     * NEVER contains the password itself — only the fact that it was changed
     * and (for admin changes) who did it and how to set a new one.
     *
     * @param string      $name       Recipient display name
     * @param string      $username   Recipient username
     * @param string      $when       Human-readable timestamp
     * @param string|null $changedBy  Admin username if changed by admin; null = self-service
     */
    public static function passwordChanged(
        string $name,
        string $username,
        string $when,
        ?string $changedBy = null
    ): string {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeUser = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safeWhen = htmlspecialchars($when, ENT_QUOTES, 'UTF-8');
        $safeBy   = $changedBy !== null ? htmlspecialchars($changedBy, ENT_QUOTES, 'UTF-8') : null;

        $appUrl = rtrim(Env::get('APP_URL', 'http://localhost'), '/');
        $forgotUrl = $appUrl . '/forgot-password';
        $safeForgotUrl = htmlspecialchars($forgotUrl, ENT_QUOTES, 'UTF-8');

        $isAdminChange = $safeBy !== null;

        if ($isAdminChange) {
            $contextLine = "The password for your account <strong>@{$safeUser}</strong> was reset by administrator <strong>{$safeBy}</strong> on <strong>{$safeWhen}</strong>.";

            $nextSteps = <<<HTML
                            <p style="margin:0 0 12px;color:#2C3E50;font-size:14px;line-height:1.6;">
                                <strong>What happens next:</strong>
                            </p>
                            <ol style="margin:0 0 16px 20px;padding:0;color:#2C3E50;font-size:14px;line-height:1.8;">
                                <li>Your old password no longer works.</li>
                                <li>Go to the <a href="{$safeForgotUrl}" style="color:#0F5E3D;font-weight:600;">DAMMC login page → Forgot Password</a>.</li>
                                <li>Enter your email address to receive a one-time reset link.</li>
                                <li>Click the link and choose a new password.</li>
                            </ol>
                            <p style="margin:0 0 16px;color:#2C3E50;font-size:13px;line-height:1.6;opacity:0.8;">
                                For security reasons, we never send passwords by email. If you need assistance logging in, contact the admin office.
                            </p>
            HTML;

            $warning = "If you did not request this password reset, please contact HR or the system administrator immediately.";
        } else {
            $contextLine = "This is a confirmation that the password for your account <strong>@{$safeUser}</strong> was successfully changed on <strong>{$safeWhen}</strong>.";

            $nextSteps = <<<HTML
                            <p style="margin:0 0 16px;color:#2C3E50;font-size:14px;line-height:1.6;">
                                You can now sign in with your new password.
                            </p>
            HTML;

            $warning = "If you did not make this change, reset your password immediately and contact HR or the system administrator.";
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Changed</title>
</head>
<body style="margin:0;padding:0;background:#F1FDF6;font-family:Inter,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.05);overflow:hidden;">
                    <tr>
                        <td style="background:#0F5E3D;padding:24px 28px;">
                            <h1 style="margin:0;color:#ffffff;font-size:18px;font-weight:700;">DAMMC</h1>
                            <p style="margin:4px 0 0;color:rgba(255,255,255,0.8);font-size:12px;">Faculty Leave & Payroll System</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px;">
                            <h2 style="margin:0 0 16px;color:#2C3E50;font-size:16px;">Hi {$safeName},</h2>
                            <p style="margin:0 0 16px;color:#2C3E50;font-size:14px;line-height:1.6;">
                                {$contextLine}
                            </p>
                            {$nextSteps}
                            <div style="margin:24px 0;padding:14px 16px;background:#FEF3C7;border-left:4px solid #F59E0B;border-radius:6px;">
                                <p style="margin:0;color:#92400E;font-size:13px;line-height:1.5;">
                                    <strong>Note:</strong> {$warning}
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px;background:#F8F9FA;border-top:1px solid #E0E0E0;">
                            <p style="margin:0;color:#94A3B8;font-size:11px;line-height:1.5;">
                                This is an automated message. Please do not reply.
                                Sent to you because the password for your DAMMC account was recently changed.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}