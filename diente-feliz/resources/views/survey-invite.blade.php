@extends('layout')
@section('title','Invitar a responder encuesta')
@section('content')
<h1>{{ $survey->name }}</h1>
<p>Genera un enlace personal para que el paciente responda. Vence en 14 días y admite una sola respuesta. No muestra su identidad ni su ficha clínica.</p>
@if(session('survey_link'))<div class="notice"><label>Enlace personal<input readonly value="{{ session('survey_link') }}"></label><p>Para responder desde otro dispositivo, el servidor debe ser accesible desde ese dispositivo. Un enlace localhost solo funciona en este equipo.</p></div>@endif
<form class="card record-form" method="post" action="/surveys/{{ $survey->id }}/invite">@csrf
<label>Paciente<select name="patient_id" required><option value="">Seleccionar…</option>@foreach($patients as $patient)<option value="{{ $patient->id }}" @selected(old('patient_id')==$patient->id)>{{ $patient->name }}</option>@endforeach</select></label>
<label>Sucursal<select name="branch_id" required><option value="">Seleccionar…</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id')==$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
<label>Entrega<select name="channel" required><option value="link">Copiar enlace para compartir</option><option value="email" @selected(old('channel')==='email')>Agregar invitación a la cola de correos</option></select></label>
<p class="full">Requiere autorización de comunicaciones. El envío por correo depende del transporte y del procesador de mensajes configurados; generar la invitación no confirma su entrega.</p>
<div class="form-actions full"><a href="/modules/surveys/{{ $survey->id }}">Volver</a><button>Generar invitación</button></div>
</form>
@endsection
