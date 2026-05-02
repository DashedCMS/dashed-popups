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
                                @elseif ($type === 'usp')
                                    @php
                                        $items = collect(explode("\n", (string) ($data['items'] ?? '')))
                                            ->map(fn ($l) => trim($l))
                                            ->filter()
                                            ->values();
                                    @endphp
                                    @if ($items->isNotEmpty())
                                        <ul style="margin:0 0 16px 0;padding:0;list-style:none;font-size:15px;line-height:1.55;color:#222;">
                                            @foreach ($items as $item)
                                                <li style="padding:4px 0 4px 22px;background:url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%23111%22><path d=%22M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z%22/></svg>') no-repeat 0 8px;background-size:14px 14px;">{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                @elseif ($type === 'discount')
                                    @php
                                        $resolvedCode = $data['code'] ?? '';
                                        if ($resolvedCode === '' && isset($popupDiscountCode)) {
                                            $resolvedCode = $popupDiscountCode;
                                        }
                                    @endphp
                                    @if ($resolvedCode !== '')
                                        <div style="margin:0 0 16px 0;padding:16px;background-color:#f5f5f5;border-radius:6px;text-align:center;">
                                            <div style="font-size:14px;color:#444;margin-bottom:8px;">{{ $data['label'] ?? 'Gebruik deze code voor extra korting:' }}</div>
                                            <div style="display:inline-block;padding:8px 14px;background-color:#fff;border:1px dashed #999;border-radius:4px;font-size:18px;letter-spacing:1px;font-weight:bold;color:#111;">{{ $resolvedCode }}</div>
                                        </div>
                                    @endif
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
