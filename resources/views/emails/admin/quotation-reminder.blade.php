@extends('emails.layouts.base')

@php
  $contact = $quotationOrder->client;
  $code = $quotationOrder->getQuotationCode() ?: '#'.$quotationOrder->getId();
  $who = $contact?->company ?: $contact?->name ?: 'un cliente';
  $days = number_format($businessDaysWaiting, 1, ',', '');
  $days = str_ends_with($days, ',0') ? substr($days, 0, -2) : $days;
@endphp

@section('title', $overdue ? 'Cotización vencida' : 'Cotización pendiente')

@section('preheader', 'La solicitud '.$code.' de '.$who.' sigue pendiente de cotización.')

@section('heading', $overdue ? 'Cotización vencida' : 'Cotización pendiente')

@section('lede')
  La solicitud de {{ $who }} lleva {{ $days }} días hábiles sin cotización.
@endsection

@section('footer_note', 'Recordatorio automático: se enviará cada 2,5 días hábiles hasta que la cotización se marque como lista.')

@section('content')
  @include('emails.partials.reference', ['code' => $code])

  @if ($overdue)
    @include('emails.partials.notice', [
      'text' => 'La fecha de entrega indicada por el cliente ya pasó.',
      'detail' => 'Fecha estimada de entrega: '.$offerDate.'.',
    ])
  @endif

  @include('emails.partials.rows', ['title' => 'Solicitud', 'rows' => [
    'Razón social' => $contact?->company,
    'Persona de contacto' => $contact?->name,
    'Correo electrónico' => $contact?->email,
    'Teléfono' => $contact?->phone,
    'Unidad de negocio' => $quotationOrder->business_unit,
    'Fecha de envío' => $quotationOrder->getCreatedAt()?->timezone(config('app.timezone'))->format('d/m/Y h:i a'),
    'Fecha estimada de entrega de la oferta comercial' => $offerDate,
    'Recordatorio' => 'N.º '.$number,
  ]])

  @include('emails.partials.button', ['url' => route('dashboard.quotation-orders.show', $quotationOrder), 'label' => 'Abrir la solicitud', 'margin' => '0'])
@endsection
