<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>@yield('title', 'Training Corporation')</title>
{{-- The site's typefaces; clients that ignore web fonts fall back to Segoe UI / Arial. --}}
<link href="https://fonts.bunny.net/css?family=montserrat:700,800|source-sans-3:400,600,700&display=swap" rel="stylesheet">
<style type="text/css">
  body { margin:0; padding:0; -webkit-text-size-adjust:100%; }
  a { color:#0072af; }
  .btn:hover { background:#005a8c !important; }
  @media only screen and (max-width: 620px) {
    .px { padding-left: 24px !important; padding-right: 24px !important; }
    .heading { font-size: 26px !important; }
    .stack, .stack td { display: block !important; width: 100% !important; }
    .stack td.row-label { border-bottom: 0 !important; padding: 12px 0 2px 0 !important; }
  }
</style>
</head>
@php
  $display = "Montserrat, 'Segoe UI', Arial, sans-serif";
  $text = "'Source Sans 3', 'Source Sans Pro', 'Segoe UI', Arial, sans-serif";
@endphp
<body style="margin:0; padding:0; background:#eef4f8; font-family:{{ $text }}; color:#102a3f;">
  @hasSection('preheader')
  <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">@yield('preheader')</div>
  @endif
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef4f8;">
    <tr>
      <td align="center" style="padding:32px 12px;">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background:#ffffff; border:1px solid #d5e2eb; border-radius:6px; overflow:hidden;">
          {{-- Header as on the site: the logo on white over a hairline. --}}
          <tr>
            <td class="px" style="padding:28px 40px 24px 40px; border-bottom:1px solid #d5e2eb;">
              <a href="{{ config('app.url') }}" target="_blank" rel="noopener noreferrer" style="text-decoration:none;">
                {{-- Embedded in the message (when it is sent), so it shows even where remote images are blocked. --}}
                <img src="{{ isset($message) ? $message->embed(\App\Utils\Brand::logoPath()) : asset(\App\Utils\Brand::LOGO) }}" alt="Training Corporation" width="112" style="display:block; width:112px; height:auto; border:0;" />
              </a>
            </td>
          </tr>
          <tr>
            <td class="px" style="padding:36px 40px 8px 40px;">
              <h1 class="heading" style="margin:0; font-family:{{ $display }}; font-size:30px; font-weight:800; line-height:1.15; letter-spacing:-0.5px; color:#0072af;">@yield('heading')</h1>
              @hasSection('lede')
              <p style="margin:14px 0 0 0; font-size:18px; line-height:1.5; color:#4a5a67;">@yield('lede')</p>
              @endif
            </td>
          </tr>
          <tr>
            <td class="px" style="padding:24px 40px 40px 40px; font-size:16px; line-height:1.55; color:#102a3f;">
              @yield('content')
            </td>
          </tr>
          @stack('below_content')
          {{-- Footer in the site's navy. --}}
          <tr>
            <td class="px" bgcolor="#0b2c47" style="background:#0b2c47; padding:28px 40px; font-size:14px; line-height:1.5; color:#a9c6dc;">
              <p style="margin:0 0 4px 0; font-family:{{ $display }}; font-size:15px; font-weight:700; color:#ffffff;">Training Corporation</p>
              <p style="margin:0;">Asesoría, diseño de eventos de formación y evaluación organizacional.</p>
              <p style="margin:12px 0 0 0; font-size:13px; color:#93abc0;">@yield('footer_note', 'Este correo se generó automáticamente; no es necesario responderlo.')</p>
              <p style="margin:4px 0 0 0; font-size:13px; color:#93abc0;">© {{ date('Y') }} Training Corporation S.A.S.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
