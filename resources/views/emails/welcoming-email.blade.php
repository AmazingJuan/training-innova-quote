@extends('emails.layouts.base')

@section('title', 'Nueva plataforma de cotización')

@section('preheader', 'Solicite cotizaciones de auditoría, consultoría y formación en línea.')

@section('heading', 'Cotice en línea con Training Corporation')

@section('content')
  @include('emails.partials.greeting', ['name' => $userName])
  <p style="margin:0 0 24px 0;">Nos complace informarle que ahora puede solicitar sus cotizaciones desde nuestra nueva plataforma: elija el servicio, diligencie el formulario de solicitud y reciba la propuesta en su correo.</p>

  @include('emails.partials.button', ['url' => route('quotation.select.business_unit'), 'label' => 'Solicitar una cotización', 'margin' => '0 0 32px 0'])

  @include('emails.partials.signature')
@endsection

@push('below_content')
  @include('emails.partials.company-contact')
@endpush
