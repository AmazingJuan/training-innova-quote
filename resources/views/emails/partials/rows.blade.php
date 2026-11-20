{{-- A block of the request as the summary on the site shows it: the question in grey, the answer in ink. --}}
@php($rows = array_filter($rows, fn ($value) => $value !== null && $value !== ''))
@if (count($rows) > 0)
<h2 style="margin:0; padding:0 0 10px 0; border-bottom:2px solid #bfdcec; font-family:Montserrat, 'Segoe UI', Arial, sans-serif; font-size:17px; font-weight:700; line-height:1.3; color:#102a3f;">{{ $title }}</h2>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 32px 0;">
  @foreach ($rows as $label => $value)
  <tr class="stack">
    <td class="row-label" width="44%" valign="top" style="padding:12px 16px 12px 0; border-bottom:1px solid #d5e2eb; font-size:15px; line-height:1.45; color:#4a5a67;">{{ $label }}</td>
    <td valign="top" style="padding:12px 0; border-bottom:1px solid #d5e2eb; font-size:16px; font-weight:600; line-height:1.45; color:#102a3f; word-break:break-word;">{!! nl2br(e($value)) !!}</td>
  </tr>
  @endforeach
</table>
@endif
