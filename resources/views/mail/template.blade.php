{{--
    De vaste mailopbouw (canvas "Alle mails"): donkere kop met afzenderregel,
    één boodschap, bouwstenen, één hoofdknop met pijl, vaste voet. Tabellen en
    inline stijlen, zodat Gmail, Outlook en Apple Mail hetzelfde tonen.
--}}
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $subject }}</title>
<style>
    body { margin: 0; padding: 0; background: #f3f4f8; }
    @media (max-width: 480px) { .wrap { width: 100% !important; } .pad { padding-left: 20px !important; padding-right: 20px !important; } }
</style>
</head>
<body style="margin:0;padding:0;background:#f3f4f8;font-family:Manrope,'Segoe UI',Helvetica,Arial,sans-serif;color:#0c1326">
<span style="display:none;max-height:0;overflow:hidden;color:#f3f4f8">{{ $preheader }}</span>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f8"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" class="wrap" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid rgba(14,21,38,.09)">
    <tr><td class="pad" style="background:#0c1326;padding:22px 32px">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
            <td style="color:#ffffff;font-size:20px;font-weight:800;letter-spacing:-.01em">Aucvy</td>
            <td align="right" style="color:#aab6cc;font-size:12px;line-height:1.4">{!! $sender === 'ivy' ? 'Bericht van Ivy,<br>je AI-assistent' : 'Aucvy, je partner in<br>zakelijke veilingen' !!}</td>
        </tr></table>
    </td></tr>
    <tr><td class="pad" style="padding:28px 32px 8px 32px">
        @foreach ($blocks as $block)
            @switch($block['type'])
                @case('heading')
                    <h1 style="margin:0 0 10px;font-size:24px;line-height:1.25;font-weight:800;color:#0c1326">{{ $block['text'] }}</h1>
                    @break
                @case('text')
                    <p style="margin:0 0 16px;font-size:15px;line-height:1.55;color:#2b3350">{{ $block['text'] }}</p>
                    @break
                @case('status')
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px"><tr><td style="background:#eef4ff;border-radius:12px;padding:14px 16px">
                        <p style="margin:0;font-size:14px;font-weight:800;color:#1e56e0">{{ $block['title'] }}</p>
                        <p style="margin:4px 0 0;font-size:14px;line-height:1.5;color:#2b3350">{{ $block['text'] }}</p>
                    </td></tr></table>
                    @break
                @case('ivy_tip')
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px"><tr>
                        <td width="44" valign="top" style="padding-right:12px"><div style="width:40px;height:40px;border-radius:50%;background:#2f6bff;color:#fff;font-weight:800;font-size:15px;line-height:40px;text-align:center">Ivy</div></td>
                        <td style="background:#f7f8fb;border-radius:12px;padding:12px 14px">
                            <p style="margin:0;font-size:13px;font-weight:800;color:#0c1326">{{ $block['title'] }}</p>
                            <p style="margin:4px 0 0;font-size:14px;line-height:1.5;color:#2b3350">{{ $block['text'] }}</p>
                        </td>
                    </tr></table>
                    @break
                @case('button')
                    @if (! empty($block['secondary']))
                        <p style="margin:0 0 14px"><a href="{{ $block['url'] }}" style="display:inline-block;padding:11px 18px;border-radius:12px;border:1px solid rgba(14,21,38,.18);color:#0c1326;font-size:14px;font-weight:700;text-decoration:none">{{ $block['label'] }}</a></p>
                    @else
                        <p style="margin:6px 0 18px"><a href="{{ $block['url'] }}" style="display:inline-block;padding:13px 22px;border-radius:12px;background:#2f6bff;color:#ffffff;font-size:15px;font-weight:800;text-decoration:none">{{ $block['label'] }} &rarr;</a></p>
                    @endif
                    @break
                @case('amount')
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px"><tr><td style="border:1px solid rgba(14,21,38,.09);border-radius:12px;padding:14px 16px">
                        <p style="margin:0;font-size:12px;font-weight:700;color:#6b7390;text-transform:uppercase;letter-spacing:.04em">{{ $block['label'] }}</p>
                        <p style="margin:2px 0 0;font-size:24px;font-weight:800;color:#0c1326">{{ $block['value'] }}</p>
                        @if (! empty($block['note']))<p style="margin:2px 0 0;font-size:13px;color:#6b7390">{{ $block['note'] }}</p>@endif
                    </td></tr></table>
                    @break
                @case('lines')
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;border:1px solid rgba(14,21,38,.09);border-radius:12px">
                        @foreach ($block['items'] as $item)
                            <tr><td style="padding:9px 14px;font-size:14px;color:#2b3350;border-bottom:1px solid rgba(14,21,38,.06)">{{ $item['label'] }}</td><td align="right" style="padding:9px 14px;font-size:14px;font-weight:700;color:#0c1326;border-bottom:1px solid rgba(14,21,38,.06)">{{ $item['value'] }}</td></tr>
                        @endforeach
                    </table>
                    @break
                @case('steps')
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px">
                        @foreach ($block['items'] as $step)
                            <tr><td width="28" valign="top" style="padding:4px 0"><span style="display:inline-block;width:22px;height:22px;border-radius:50%;background:#eef4ff;color:#1e56e0;font-size:12px;font-weight:800;line-height:22px;text-align:center">{{ $loop->iteration }}</span></td><td style="padding:4px 0;font-size:14px;line-height:1.5;color:#2b3350">{{ $step }}</td></tr>
                        @endforeach
                    </table>
                    @break
                @case('code')
                    <p style="margin:0 0 16px;padding:16px;border-radius:12px;background:#f7f8fb;text-align:center;font-size:28px;letter-spacing:.12em;font-weight:800;color:#0c1326">{{ $block['value'] }}</p>
                    @break
                @case('lots')
                    @foreach ($block['lots'] as $lot)
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 12px;border:1px solid rgba(14,21,38,.09);border-radius:12px"><tr>
                            <td width="88" valign="top" style="padding:10px">@if ($lot['image'])<img src="{{ $lot['image'] }}" width="78" height="60" alt="" style="display:block;width:78px;height:60px;object-fit:cover;border-radius:8px">@else<div style="width:78px;height:60px;border-radius:8px;background:#e3e7f0"></div>@endif</td>
                            <td valign="top" style="padding:10px 12px 10px 0">
                                <a href="{{ $lot['url'] }}" style="font-size:14px;font-weight:800;color:#0c1326;text-decoration:none">{{ $lot['title'] }}</a>
                                <p style="margin:2px 0 0;font-size:12px;color:#6b7390">{{ $lot['meta'] }}@if ($lot['ends']) · sluit {{ $lot['ends'] }}@endif</p>
                                <p style="margin:4px 0 0;font-size:14px;color:#0c1326"><strong>{{ $lot['price'] }}</strong> <span style="color:#6b7390">{{ $lot['price_label'] }}</span></p>
                            </td>
                        </tr></table>
                    @endforeach
                    @break
                @case('image')
                    <p style="margin:0 0 16px"><img src="{{ $block['url'] }}" alt="{{ $block['alt'] ?? '' }}" width="536" style="display:block;width:100%;max-width:536px;height:auto;border-radius:12px"></p>
                    @break
                @case('divider')
                    <hr style="border:0;border-top:1px solid rgba(14,21,38,.09);margin:8px 0 18px">
                    @break
                @case('footnote')
                    <p style="margin:0 0 16px;font-size:13px;line-height:1.5;color:#6b7390">{{ $block['text'] }}</p>
                    @break
            @endswitch
        @endforeach
    </td></tr>
    <tr><td class="pad" style="padding:18px 32px 26px;border-top:1px solid rgba(14,21,38,.09);font-size:12px;line-height:1.6;color:#6b7390">
        <strong style="color:#0c1326">Aucvy</strong><br>
        <a href="{{ route('legal.terms') }}" style="color:#6b7390">Voorwaarden</a> · <a href="{{ route('legal.privacy') }}" style="color:#6b7390">Privacy</a> · <a href="{{ route('contact') }}" style="color:#6b7390">Contact</a>@if ($unsubscribeUrl) · <a href="{{ $unsubscribeUrl }}" style="color:#6b7390">Afmelden</a>@endif<br>
        &copy; {{ now()->year }} Aucvy B.V. Het AI-commerceplatform voor zakelijke goederen.
    </td></tr>
</table>
</td></tr></table>
</body>
</html>
