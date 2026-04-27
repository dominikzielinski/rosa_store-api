<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Nowe zgłoszenie — Rosa D'oro</title>
</head>
<body style="font-family: 'Helvetica Neue', Arial, sans-serif; color: #0F0F0F; background: #FAF8F2; margin: 0; padding: 24px;">
    <div style="max-width: 640px; margin: 0 auto; background: #FFFEF5; padding: 32px; border: 1px solid #E5E5E5;">
        <h1 style="font-family: 'Playfair Display', Georgia, serif; color: #6B1C2A; font-size: 24px; margin: 0 0 24px;">
            Nowe zgłoszenie {{ $submission->type->getData()['name'] }}
        </h1>

        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <tr>
                <td style="padding: 8px 0; color: #666; width: 160px;">Imię i nazwisko:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $submission->full_name }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #666;">E-mail:</td>
                <td style="padding: 8px 0;">
                    <a href="mailto:{{ $submission->email }}" style="color: #6B1C2A;">{{ $submission->email }}</a>
                </td>
            </tr>
            @if ($submission->phone)
                <tr>
                    <td style="padding: 8px 0; color: #666;">Telefon:</td>
                    <td style="padding: 8px 0;">
                        <a href="tel:{{ $submission->phone }}" style="color: #6B1C2A;">{{ $submission->phone }}</a>
                    </td>
                </tr>
            @endif
            @if ($submission->company)
                <tr>
                    <td style="padding: 8px 0; color: #666;">Firma:</td>
                    <td style="padding: 8px 0;">{{ $submission->company }}</td>
                </tr>
            @endif
            @if ($submission->nip)
                <tr>
                    <td style="padding: 8px 0; color: #666;">NIP:</td>
                    <td style="padding: 8px 0;">{{ $submission->nip }}</td>
                </tr>
            @endif
            @if ($submission->event_type)
                <tr>
                    <td style="padding: 8px 0; color: #666;">Typ eventu:</td>
                    <td style="padding: 8px 0;">{{ $submission->event_type }}</td>
                </tr>
            @endif
            @if ($submission->gift_count)
                <tr>
                    <td style="padding: 8px 0; color: #666;">Liczba prezentów:</td>
                    <td style="padding: 8px 0;">{{ $submission->gift_count }}</td>
                </tr>
            @endif
            @if ($submission->preferred_contact)
                <tr>
                    <td style="padding: 8px 0; color: #666;">Preferowany kontakt:</td>
                    <td style="padding: 8px 0;">{{ $submission->preferred_contact->getData()['name'] }}</td>
                </tr>
            @endif
        </table>

        <h2 style="font-family: 'Playfair Display', Georgia, serif; color: #6B1C2A; font-size: 18px; margin: 32px 0 12px;">
            Wiadomość
        </h2>
        <p style="white-space: pre-wrap; line-height: 1.6; margin: 0; padding: 16px; background: #FAF8F2; border-left: 3px solid #C9A959;">{{ $submission->message }}</p>

        <div style="margin-top: 32px; padding-top: 16px; border-top: 1px solid #E5E5E5; font-size: 12px; color: #999;">
            <p style="margin: 4px 0;">Zgoda marketingowa: {{ $submission->consent_marketing ? 'TAK' : 'nie' }}</p>
            <p style="margin: 4px 0;">Data: {{ $submission->created_at->format('Y-m-d H:i') }}</p>
            <p style="margin: 4px 0;">IP: {{ $submission->ip_address ?? '—' }}</p>
        </div>
    </div>
</body>
</html>
