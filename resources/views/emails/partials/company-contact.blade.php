{{-- "Llame o escriba", as in the site's footer. Pushed below the content as its own band. --}}
@if(!empty($companyContactEmail) || !empty($companyContactPhone) || !empty($companyContactAddress) || !empty($companyWebsiteUrl))
<tr>
  <td class="px" style="padding:24px 40px; background:#eef4f8; border-top:1px solid #d5e2eb; font-size:15px; line-height:1.6; color:#4a5a67;">
    <p style="margin:0 0 6px 0; font-family:Montserrat, 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:700; color:#0072af;">¿Tiene preguntas? Llame o escriba</p>
    @if(!empty($companyContactPhone))
    <p style="margin:0;"><a href="tel:{{ preg_replace('/\s+/', '', $companyContactPhone) }}" style="color:#102a3f; font-weight:600; text-decoration:none;">{{ $companyContactPhone }}</a></p>
    @endif
    @if(!empty($companyContactEmail))
    <p style="margin:0;"><a href="mailto:{{ $companyContactEmail }}" style="color:#102a3f; font-weight:600; text-decoration:none;">{{ $companyContactEmail }}</a></p>
    @endif
    @if(!empty($companyContactAddress))
    <p style="margin:6px 0 0 0;">{{ $companyContactAddress }}</p>
    @endif
    @if(!empty($companyWebsiteUrl))
    <p style="margin:6px 0 0 0;"><a href="{{ $companyWebsiteUrl }}" style="color:#0072af; font-weight:600;">{{ $companyWebsiteLabel ?: $companyWebsiteUrl }}</a></p>
    @endif
  </td>
</tr>
@endif
