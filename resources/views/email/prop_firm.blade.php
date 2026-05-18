<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $details['subject'] ?? 'Prop Firm Notification' }}</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8f9fa; padding: 20px;">
<table width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
    <tr>
        <td style="background: #0d6efd; color: #ffffff; padding: 15px 20px; font-size: 18px; font-weight: bold;">
            📢 Prop Firm Notification
        </td>
    </tr>

    <tr>
        <td style="padding: 20px; color: #333;">
            <p>Hi {{ $details['user_name'] ?? 'Trader' }},</p>

            {{-- 🚫 FAIL --}}
            @if(isset($details['status']) && $details['status'] === 'FAIL')
                <p style="font-size: 16px; color: #dc3545; font-weight: bold;">⚠️ Trading Rule Breach Detected</p>
                <p>We regret to inform you that account number <strong>{{ $details['account_number'] ?? 'N/A' }}</strong> has breached one or more trading rules.</p>

                @if(!empty($details['breach_rule']))
                    <p><strong>Rule:</strong> {{ $details['breach_rule'] }}</p>
                @endif
                @if(!empty($details['violated_at']))
                    <p><strong>Violated at:</strong> {{ $details['violated_at'] }}</p>
                @endif
                @if(isset($details['max_allowed_risk']) || isset($details['max_open_risk']))
                    <p><strong>Max Open Risk Allowed:</strong> {{ $details['max_allowed_risk'] ?? 'N/A' }}</p>
                    <p><strong>Max Open Risk Recorded:</strong> {{ $details['max_open_risk'] ?? 'N/A' }}</p>
                @endif

            {{-- ✅ PASS --}}
            @elseif(isset($details['status']) && $details['status'] === 'PASS')
                <p style="font-size: 16px; color: #198754; font-weight: bold;">✅ Congratulations! Evaluation Passed</p>
                <p>Your account number <strong>{{ $details['account_number'] ?? 'N/A' }}</strong> has successfully completed <strong>Phase {{ $details['phase'] ?? '1' }}</strong>.</p>

                @if(!empty($details['next_phase']))
                    <p>You are now eligible to proceed to <strong>Phase {{ $details['next_phase'] }}</strong>. The new account will appear on your dashboard shortly.</p>
                @endif

                <div style="margin: 16px 0; padding: 12px 14px; background: #f1f8ff; border: 1px solid #d0e7ff; border-radius: 6px;">
                    <p style="margin: 0 0 8px 0;"><strong>Things To Remember:</strong></p>
                    <ul style="margin: 0 0 0 18px;">
                        <li>Your next phase begins after executing your first trade.</li>
                        <li>Always follow the trading rules; breaking them may reset your stage.</li>
                        <li>Keep a note of all rules and monitor your performance.</li>
                    </ul>
                    <p style="margin: 12px 0 0 0;"><strong>Important:</strong> If the new account credentials do not arrive within 24 hours, please contact support.</p>
                </div>

            {{-- 💰 FUNDED --}}
            @elseif(isset($details['status']) && $details['status'] === 'FUNDED')
                <p style="font-size: 16px; color: #0d6efd; font-weight: bold;">🎉 Congratulations! You Are Now a Funded Trader</p>
                <p>Great job, {{ $details['user_name'] ?? 'Trader' }}! Your account <strong>{{ $details['account_number'] ?? 'N/A' }}</strong> has successfully passed all evaluation phases and is now a <strong>Funded Trading Account</strong>.</p>

                <div style="margin: 16px 0; padding: 12px 14px; background: #e9fce9; border: 1px solid #b2e0b2; border-radius: 6px;">
                    <p style="margin: 0 0 8px 0;"><strong>Next Steps:</strong></p>
                    <ul style="margin: 0 0 0 18px;">
                        <li>Your funded account credentials will be delivered to your email/dashboard.</li>
                        <li>Follow the funded account rules to continue trading safely.</li>
                        <li>Payouts will be available as per the profit-sharing policy.</li>
                    </ul>
                </div>

                <p style="margin-top: 15px;">Welcome to the elite group of <strong>Funded Traders</strong>! 🚀</p>

            {{-- Default Fallback --}}
            @else
                <p>Your account update is available below:</p>
            @endif

            <p style="margin-top: 20px;">
                <a href="{{ $details['url'] ?? '#' }}" style="background: #0d6efd; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 5px;">Go to Dashboard</a>
            </p>

            <p style="margin-top: 20px; color: #6c757d;">
                If you require assistance, our support team is available at <a href="mailto:hcgaming@gmail.com">hcgaming@gmail.com</a>.
            </p>

            <p style="margin-top: 30px; font-size: 12px; color: #6c757d;">
                This is an automated notification. Please do not reply to this email.
            </p>
        </td>
    </tr>
</table>
</body>
</html>
