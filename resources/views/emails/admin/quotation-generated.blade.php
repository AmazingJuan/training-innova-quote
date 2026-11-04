@extends('emails.layouts.base')

@php
  $contact = $quotationOrder->client;
  $code = $quotationOrder->getQuotationCode() ?: '#'.$quotationOrder->getId();
@endphp

@section('title', 'Cotización enviada al cliente')

@section('heading', 'Cotización enviada al cliente')

@section('lede')
  Se cargó el documento y {{ $contact?->name ?: 'el cliente' }} recibió el enlace por correo.
@endsection

@section('footer_note', 'Notificación interna de la plataforma de cotización.')

@section('content')
  @include('emails.partials.reference', ['code' => $code])

  @include('emails.partials.rows', ['title' => 'Cliente', 'rows' => [
    'Nombre' => $contact?->name,
    'Empresa' => $contact?->company,
    'Correo electrónico' => $contact?->email,
  ]])

  @if ($quotationOrder->getQuotationUrl())
    @include('emails.partials.button', ['url' => $quotationOrder->getQuotationUrl(), 'label' => 'Abrir el documento', 'margin' => '0'])
  @endif
@endsection
