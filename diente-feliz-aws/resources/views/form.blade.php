@extends('layout')
@section('title',$definition['title'])
@section('content')
<a class="back" href="/modules/{{ $module }}">← {{ $definition['title'] }}</a><div class="page-heading"><div><h1>{{ $record?'Editar registro':'Nuevo registro' }}</h1><p class="muted">{{ $definition['title'] }} · Los cambios quedan registrados en auditoría.</p></div></div>
<form class="card record-form" method="post" action="/modules/{{ $module }}{{ $record?'/'.$record->id:'' }}" enctype="multipart/form-data">@csrf @if($record)@method('PUT')@endif
@if(in_array($module,['payments','refunds']))<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key',(string)\Illuminate\Support\Str::uuid()) }}"><div class="notice full">Los montos se registran en pesos chilenos enteros. Esta operación será permanente; las correcciones financieras se realizan con una nota de devolución.</div>@endif
@if($module==='consents')<div class="notice full">Selecciona una plantilla y revisa el texto antes de emitirlo. El nombre del firmante deja constancia de consentimiento presencial; no constituye una firma electrónica avanzada.</div>@endif
@if($module==='odontogram')<p class="notice full">Selecciona la sucursal y confirma la pieza, superficie y estado. Lingual incluye palatina; oclusal incluye incisal. Un registro completo sustituye las superficies anteriores en el dibujo; el historial se conserva. Para corregir un hallazgo, agrega un nuevo registro.</p>@endif
@foreach($definition['fields'] as $field=>$spec)
@if($module==='appointments' && $field==='ends_at')
<label>Duración <span class="required">*</span><select name="duration" required>@foreach([30,45,60] as $minutes)<option value="{{ $minutes }}" @selected((int)old('duration',$record ? \Carbon\Carbon::parse($record->starts_at)->diffInMinutes(\Carbon\Carbon::parse($record->ends_at)) : 30)===$minutes)>{{ $minutes }} minutos</option>@endforeach</select><small>El término se calcula automáticamente y se verifica la disponibilidad.</small>@error('duration')<small class="field-error">{{ $message }}</small>@enderror</label>
@continue
@endif
@continue($module==='budgets' && $field==='name')
@php($default = $module==='surveys' && $field==='questions' ? \App\Services\Modules::surveyQuestions() : ($field==='active' ? 1 : null))
@php($value=old($field,$record?->$field ?? request($field,$default)))
@if($spec[1]==='datetime-local' && $value)@php($value=\Carbon\Carbon::parse($value)->format('Y-m-d\TH:i'))@endif
<label class="{{ $spec[1]==='textarea'?'full':'' }}">{{ $spec[0] }} @if(str_contains($spec[2],'required'))<span class="required">*</span>@endif
@if($spec[1]==='textarea')<textarea name="{{ $field }}" rows="5" @required(str_contains($spec[2],'required'))>{{ $value }}</textarea>
@elseif($spec[1]==='select')<select name="{{ $field }}" @required(str_contains($spec[2],'required'))><option value="">Seleccionar…</option>@foreach($options[$field] as $key=>$label)<option value="{{ $key }}" @selected((string)$value===(string)$key)>{{ $label }}</option>@endforeach</select>
@else<input type="{{ $spec[1] }}" name="{{ $field }}" @if($spec[1]!=='file') value="{{ $value }}" @endif @if($spec[1]==='file') accept=".pdf,.png,.jpg,.jpeg" @endif @required(str_contains($spec[2],'required'))>
@endif
@if($module==='patients' && $field==='identifier')<small>Código único de la ficha, por ejemplo PAC-0001. No es un RUT. En esta versión académica usa datos ficticios.</small>@endif
@if($module==='patients' && $field==='active')<small>Sí permite nuevas operaciones. Al elegir No, se conserva la ficha y su historial, pero se impiden nuevas operaciones.</small>@endif
@if($module==='recalls' && $field==='due_on')<small>Opcional: programa cuándo volver a contactar. Sin fecha queda pendiente de programar; no representa un contacto realizado.</small>@endif
@if($module==='surveys' && $field==='questions')<small>Una pregunta por línea, hasta 20. Cada pregunta se responde del 1 al 5; las invitaciones conservan las preguntas vigentes al generarlas.</small>@endif
@error($field)<small class="field-error">{{ $message }}</small>@enderror</label>
@endforeach
@if($module==='patients')<fieldset class="full"><legend>Sucursales autorizadas</legend>@foreach($branches as $id=>$name)<label class="check"><input type="checkbox" name="branches[]" value="{{ $id }}" @checked(in_array($id,old('branches',$record?\DB::table('branch_patient')->where('patient_id',$record->id)->pluck('branch_id')->all():[])))>{{ $name }}</label>@endforeach<small>Las asociaciones existentes se conservan para proteger el historial.</small></fieldset>@endif
@if($module==='consents')<details class="full"><summary>Consultar plantillas disponibles</summary>@foreach(\App\Services\Clinic::query('templates')->where('kind','Consentimiento')->where('active',true)->get() as $template)<h3>{{ $template->name }} · versión {{ $template->version }}</h3><p class="prewrap">{{ $template->body }}</p><button type="button" class="secondary copy-template" data-template="{{ $template->body }}">Usar este texto</button>@endforeach</details>@endif
<div class="form-actions full"><a href="/modules/{{ $module }}">Cancelar</a><button>Guardar registro</button></div></form>
@endsection
