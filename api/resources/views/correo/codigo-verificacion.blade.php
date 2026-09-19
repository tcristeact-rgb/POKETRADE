<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('correo.verificacion.asunto', ['codigo' => $codigo]) }}</title>
</head>
<body style="margin:0;padding:32px 16px;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:12px;">
        <tr>
            <td style="padding:32px;">
                <p style="margin:0 0 8px;font-size:20px;font-weight:bold;">PokeTrade</p>
                <p style="margin:0 0 24px;font-size:16px;line-height:1.5;">{{ __('correo.verificacion.intro') }}</p>
                <p style="margin:0 0 24px;font-size:36px;font-weight:bold;letter-spacing:0.25em;text-align:center;">{{ substr($codigo, 0, 3) }} {{ substr($codigo, 3) }}</p>
                <p style="margin:0 0 8px;font-size:14px;line-height:1.5;color:#404040;">{{ __('correo.verificacion.caduca', ['minutos' => $minutos]) }}</p>
                <p style="margin:0;font-size:14px;line-height:1.5;color:#6e6e6e;">{{ __('correo.verificacion.ignorar') }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
