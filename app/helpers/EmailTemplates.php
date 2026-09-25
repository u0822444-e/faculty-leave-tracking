<?php

class EmailTemplates
{
    public static function welcome(
        string $fullName,
        string $username,
        string $tempPassword
    ): string {
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

                <a href=\"http://localhost:8002/login\" style=\"display: inline-block; margin-top: 20px; background: #0F5E3D; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px;\">
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
}

