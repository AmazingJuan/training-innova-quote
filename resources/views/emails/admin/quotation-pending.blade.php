@extends('emails.layouts.base')

@php
  $contact = $quotationOrder->client;
  $code = $quotationOrder->getQuotationCode() ?: '#'.$quotationOrder->getId();
@endphp

@section('title', 'Nueva solicitud de cotización')

@section('preheader', ($contact?->company ?: $contact?->name ?: 'Un cliente').' solicitó una cotización de '.($quotationOrder->business_unit ?? 'un servicio').'.')

@section('heading', 'Nueva solicitud de cotización')

@section('lede')
  {{ $contact?->company ?: $contact?->name ?: 'Un cliente' }} envió una solicitud desde la plataforma.
@endsection

@section('footer_note', 'Notificación interna de la plataforma de cotización.')

@section('content')
  @include('emails.partials.reference', ['code' => $code])

  @include('emails.partials.rows', ['title' => 'Organización', 'rows' => [
    'Razón social' => $contact?->company,
    'NIT' => $contact?->nit,
    'Actividad económica' => $contact?->economic_activity,
    'Dirección' => $contact?->address,
    'Unidad de negocio' => $quotationOrder->business_unit,
  ]])

  @include('emails.partials.rows', ['title' => 'Persona de contacto', 'rows' => [
    'Nombre' => $contact?->name,
    'Cargo' => $contact?->role,
    'Correo electrónico' => $contact?->email,
    'Teléfono' => $contact?->phone,
  ]])

  @include('emails.partials.rows', ['title' => 'Plazo de la oferta', 'rows' => [
    'Fecha estimada de entrega de la oferta comercial' => $offerDate,
  ]])

  @if (!empty($attachedFiles))
    @include('emails.partials.rows', ['title' => 'Documentos adjuntos a este correo', 'rows' => [
      'Archivos' => implode(', ', $attachedFiles),
    ]])
  @endif

  @include('emails.partials.button', ['url' => route('dashboard'), 'label' => 'Abrir el panel', 'margin' => '0'])
@endsection
