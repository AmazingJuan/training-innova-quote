@extends('emails.layouts.base')

@php
  $contact = $quotationOrder->client;
  $code = $quotationOrder->getQuotationCode() ?: '#'.$quotationOrder->id;
@endphp

@section('title', 'Recibimos su solicitud de cotización')

@section('preheader', 'Su solicitud '.$code.' quedó registrada. Le enviaremos la propuesta a este correo.')

@section('heading', 'Recibimos su solicitud')

@section('content')
  @include('emails.partials.greeting', ['name' => $contact?->name])
  <p style="margin:0 0 24px 0;">Agradecemos su interés en los servicios de Training Corporation. Hemos recibido su solicitud de cotización y nuestro equipo ya cuenta con la información necesaria para preparar su propuesta.</p>

  @include('emails.partials.reference', ['code' => $code])

  @include('emails.partials.notice', ['text' => 'Le enviaremos la propuesta a este correo lo más pronto posible.', 'detail' => 'Incluirá un enlace seguro para consultarla y descargarla.'])

  @include('emails.partials.rows', ['title' => 'Solicitud', 'rows' => [
    'Fecha' => $quotationOrder->created_at?->format('d/m/Y, H:i'),
    'Unidad de negocio' => $quotationOrder->business_unit,
    'Línea de gestión' => $quotationOrder->gestion_line,
    'Servicios' => collect($quotationOrder->services ?? [])->map(fn ($s) => is_array($s) ? ($s['name'] ?? json_encode($s)) : $s)->implode("\n"),
  ]])

  @if ($contact)
    @include('emails.partials.rows', ['title' => 'Datos de contacto', 'rows' => [
      'Nombre' => $contact->name,
      'Empresa' => $contact->company,
      'Cargo' => $contact->role,
      'Correo electrónico' => $contact->email,
      'Teléfono' => $contact->phone,
    ]])
  @endif

  @if (!empty($quotationOrder->answers))
    @include('emails.partials.rows', ['title' => 'Detalles del servicio', 'rows' => collect($quotationOrder->answers)->map(fn ($v) => is_array($v) ? implode(', ', $v) : (string) $v)->all()])
  @endif

  <p style="margin:0 0 24px 0; font-size:15px; color:#4a5a67;">Si algún dato no es correcto, le agradecemos comunicarse con nosotros indicando el número de su solicitud.</p>

  @include('emails.partials.signature')
@endsection

@push('below_content')
  @include('emails.partials.company-contact')
@endpush
