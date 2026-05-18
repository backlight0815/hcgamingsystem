<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Phase Passed Notification</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8f9fa; padding: 20px;">
    <table width="100%" cellspacing="0" cellpadding="0" 
           style="max-width: 650px; margin: auto; background: #ffffff; border-radius: 8px; 
                  overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
        <tr>
            <td style="background: #198754; color: #ffffff; padding: 15px 20px; font-size: 20px; font-weight: bold;">
                🎉 Phase {{ $passdetails['phase'] ?? '1' }} Passed
            </td>
        </tr>
        <tr>
            <td style="padding: 25px; color: #333; line-height: 1.6;">
                <p>Hi {{ $passdetails['user_name'] ?? 'Trader' }},</p>

                <h2 style="color: #198754; margin-top: 0;">Congratulations!! 🎊</h2>

                <p>You have successfully completed <strong>Phase {{ $passdetails['phase'] ?? '1' }}</strong> of your evaluation.</p>

                <p>Your <strong>Phase {{ $passdetails['next_phase'] ?? '2' }}</strong> trading account will appear in your dashboard, and you will also be notified by email when it’s ready.</p>

                <h3 style="margin-top: 25px;">📌 Things to Remember:</h3>
                <ul style="padding-left: 20px;">
                    <li>Your evaluation phase begins after your first trade.</li>
                    <li>Always follow the trading rules, otherwise you may be reset to the beginning.</li>
                    <li>Keep notes of your rules and monitor your performance.</li>
                </ul>

                <p style="margin-top: 20px;">
                    ⚠️ <strong>Note:</strong> Your next account will be created automatically. 
                    If you do not receive the credentials within 24 hours, please contact support.
                </p>

                <p style="margin-top: 20px;">
                    <a href="{{ $passdetails['url'] ?? url('/dashboard') }}" 
                       style="background: #198754; color: #fff; text-decoration: none; 
                              padding: 12px 24px; border-radius: 6px; font-size: 16px; 
                              display: inline-block;">
                        Go to Dashboard
                    </a>
                </p>

                <p style="margin-top: 30px; font-size: 12px; color: #6c757d;">
                    If you need help, contact us at 
                    <a href="mailto:support@yourfirm.com" style="color:#0d6efd;">support@yourfirm.com</a>.
                </p>

                <p style="margin-top: 20px;">Kind regards,<br><strong>Your Prop Firm Team</strong></p>
            </td>
        </tr>
    </table>
</body>
</html>
