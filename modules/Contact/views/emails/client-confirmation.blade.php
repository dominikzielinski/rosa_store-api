<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Dziękujemy za wiadomość — Rosa D'oro</title>
</head>
<body style="font-family: 'Helvetica Neue', Arial, sans-serif; color: #0F0F0F; background: #FAF8F2; margin: 0; padding: 24px;">
    <div style="max-width: 640px; margin: 0 auto; background: #FFFEF5; padding: 40px 32px; border: 1px solid #E5E5E5;">
        <div style="text-align: center; margin-bottom: 32px;">
            <p style="font-family: 'Playfair Display', Georgia, serif; font-size: 20px; letter-spacing: 4px; color: #6B1C2A; margin: 0;">
                ROSA D'ORO
            </p>
        </div>

        <h1 style="font-family: 'Playfair Display', Georgia, serif; color: #6B1C2A; font-size: 28px; margin: 0 0 24px; text-align: center; font-weight: 400;">
            Dziękujemy, <span style="font-style: italic; color: #C9A959;">{{ $submission->full_name }}</span>
        </h1>

        <p style="font-size: 15px; line-height: 1.8; color: #333; margin: 0 0 16px;">
            Otrzymaliśmy Twoje zgłoszenie i&nbsp;bardzo dziękujemy za&nbsp;zainteresowanie Rosa&nbsp;D'oro. Skontaktujemy się z&nbsp;Tobą najszybciej jak to możliwe.
        </p>

        @if ($submission->type->value === 'b2b')
            <p style="font-size: 15px; line-height: 1.8; color: #333; margin: 0 0 16px;">
                W&nbsp;przypadku zapytań biznesowych odpowiadamy zazwyczaj w&nbsp;ciągu 24&nbsp;godzin roboczych.
            </p>
        @else
            <p style="font-size: 15px; line-height: 1.8; color: #333; margin: 0 0 16px;">
                Nasz zespół przejrzy Twoją wiadomość i&nbsp;odpisze najszybciej jak to możliwe.
            </p>
        @endif

        <div style="margin: 32px 0; padding: 20px; background: #FAF8F2; border-left: 3px solid #C9A959;">
            <p style="margin: 0 0 8px; font-size: 12px; letter-spacing: 2px; text-transform: uppercase; color: #999;">
                Twoja wiadomość
            </p>
            <p style="white-space: pre-wrap; line-height: 1.6; margin: 0; color: #333; font-size: 14px;">{{ $submission->message }}</p>
        </div>

        <p style="font-size: 14px; line-height: 1.7; color: #666; margin: 24px 0 0;">
            Jeśli nie wysyłałeś/aś tej wiadomości, po&nbsp;prostu zignoruj tego maila.
        </p>

        <div style="margin-top: 40px; padding-top: 24px; border-top: 1px solid #E5E5E5; text-align: center;">
            <p style="font-size: 12px; color: #999; margin: 4px 0;">
                Rosa D'oro&nbsp;·&nbsp;Personalizowane prezenty premium
            </p>
            <p style="font-size: 12px; color: #999; margin: 4px 0;">
                <a href="mailto:kontakt@rosadoro.pl" style="color: #6B1C2A; text-decoration: none;">kontakt@rosadoro.pl</a>
            </p>
        </div>
    </div>
</body>
</html>
