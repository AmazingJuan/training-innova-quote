@extends('emails.layouts.base')

@php
  $code = $order->getQuotationCode() ?: '#'.$order->getId();
  $receiveUrl = $order->getReceiveQuotationSignedUrl();
@endphp

@section('title', 'Su cotización está lista')

@section('preheader', 'La propuesta para su solicitud '.$code.' ya está disponible.')

@section('heading', 'Su cotización está lista')

@section('content')
  @include('emails.partials.greeting', ['name' => $order->client?->name])
  <p style="margin:0 0 24px 0;">Nos complace informarle que la propuesta correspondiente a su solicitud ya está disponible. Puede consultarla y descargarla desde el siguiente enlace.</p>

  @include('emails.partials.reference', ['code' => $code])

  @if ($receiveUrl)
    @include('emails.partials.button', ['url' => $receiveUrl, 'label' => 'Ver la cotización', 'margin' => '0 0 20px 0'])
    <p style="margin:0; font-size:14px; line-height:1.5; color:#4a5a67;">El enlace es personal y seguro. Si el botón no funciona, copie esta dirección en su navegador:<br>
      <a href="{{ $receiveUrl }}" style="color:#0072af; word-break:break-all;">{{ $receiveUrl }}</a>
    </p>
  @else
    <p style="margin:0; font-size:16px; color:#4a5a67;">El documento todavía no está disponible. Le avisaremos por este medio lo más pronto posible.</p>
  @endif

  <p style="margin:28px 0 24px 0;">Quedamos atentos a cualquier inquietud sobre la propuesta.</p>
  @include('emails.partials.signature')
@endsection

@push('below_content')
  @include('emails.partials.company-contact')
@endpush
