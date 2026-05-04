<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afgemeld - {{ $siteName }}</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family: Arial, sans-serif;">
    <div style="max-width:600px; margin:48px auto; background:#ffffff; border-radius:8px; padding:32px; text-align:center;">
        <h1 style="margin:0 0 16px 0; font-size:22px; color:#111827;">Je bent afgemeld</h1>
        <p style="margin:0 0 16px 0; color:#374151; line-height:1.55;">
            @if($alreadyCancelled)
                Je was al afgemeld voor de popup-opvolg-flow van <strong>{{ $siteName }}</strong>.
            @else
                Je ontvangt geen verdere e-mails meer voor de popup-opvolg-flow van <strong>{{ $siteName }}</strong>.
            @endif
        </p>
        <p style="margin:24px 0 0 0; font-size:13px; color:#9ca3af;">Je kunt dit venster sluiten.</p>
    </div>
</body>
</html>
