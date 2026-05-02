<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $siteName ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f5f5f5;font-family:Arial,sans-serif;color:#222;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f5f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff;border-radius:8px;padding:24px;max-width:600px;">
                    <tr>
                        <td>
                            @foreach (($blocks ?? []) as $block)
                                @php
                                    $type = $block['type'] ?? null;
                                    $data = $block['data'] ?? [];
                                @endphp
                                @if ($type === 'heading')
                                    <h2 style="margin:0 0 16px 0;font-size:22px;line-height:1.3;color:#111;">
                                        {{ $data['content'] ?? '' }}
                                    </h2>
                                @elseif ($type === 'paragraph' || $type === 'text')
                                    <div style="margin:0 0 16px 0;font-size:15px;line-height:1.55;color:#222;">
                                        {!! $data['content'] ?? '' !!}
                                    </div>
                                @elseif ($type === 'button')
                                    @php
                                        $label = $data['label'] ?? 'Bekijk';
                                        $url = $data['url'] ?? '#';
                                    @endphp
                                    <p style="margin:0 0 16px 0;">
                                        <a href="{{ $url }}" style="display:inline-block;padding:12px 20px;background-color:#111;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">
                                            {{ $label }}
                                        </a>
                                    </p>
                                @elseif ($type === 'image')
                                    @if (! empty($data['url']))
                                        <p style="margin:0 0 16px 0;">
                                            <img src="{{ $data['url'] }}" alt="{{ $data['alt'] ?? '' }}" style="max-width:100%;height:auto;border-radius:6px;" />
                                        </p>
                                    @endif
                                @elseif ($type === 'divider')
                                    <hr style="border:none;border-top:1px solid #e5e5e5;margin:16px 0;" />
                                @endif
                            @endforeach

                            <p style="margin:24px 0 0 0;font-size:12px;color:#888;line-height:1.5;">
                                {{ $siteName ?? config('app.name') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
