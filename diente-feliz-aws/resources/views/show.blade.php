@extends('layout')
@section('title',$definition['title'])
@section('content')
<a class="back" href="/modules/{{ $module }}">← {{ $definition['title'] }}</a><div class="page-heading"><div><span class="eyebrow">{{ $definition['title'] }} / #{{ $record->id }}</span><h1>{{ $record->name ?? 'Detalle del registro' }}</h1><p class="muted">Actualizado {{ $record->updated_at?->format('d/m/Y H:i') }}</p></div><div class="actions">@can('update',$record)<a class="button" href="/modules/{{ $module }}/{{ $record->id }}/edit">Editar</a>@endcan @if($module==='documents')<a class="button" href="/documents/{{ $record->id }}/download">Descargar archivo</a>@endif<button type="button" class="secondary print-button">Imprimir / PDF</button></div></div>
@if($module==='surveys')@can('communication.write')<p><a class="button" href="/surveys/{{ $record->id }}/invite">Invitar a responder</a></p>@endcan
@endif
@if($module==='patients')<div class="tabs"><a class="{{ request('view')!=='advanced'?'active':'' }}" href="?view=simple">Ficha simple</a><a class="{{ request('view')==='advanced'?'active':'' }}" href="?view=advanced">Ficha avanzada</a></div>@endif
<section class="card detail-grid">@foreach($definition['fields'] as $field=>$spec)@if(!in_array($field,['file','accepted']))<div class="{{ $spec[1]==='textarea'?'full':'' }}"><small>{{ $spec[0] }}</small><p class="prewrap">{{ $options[$field][$record->$field] ?? $record->$field ?? 'Sin información' }}</p></div>@endif @endforeach
@if($module==='budgets')<div><small>Descuento de convenio</small><p>{{ $record->discount_percent }} %</p></div><div><small>Total</small><p>$ {{ number_format($record->total,0,',','.') }}</p></div><div><small>Saldo pendiente</small><p>$ {{ number_format(\App\Services\Clinic::balance($record),0,',','.') }}</p></div>@can('finance.write')<a class="button" href="/modules/payments/create?patient_id={{ $record->patient_id }}&branch_id={{ $record->branch_id }}&budget_id={{ $record->id }}">Registrar abono</a>@endcan @endif
@if($module==='payments')@can('refunds.write')<a class="button" href="/modules/refunds/create?patient_id={{ $record->patient_id }}&branch_id={{ $record->branch_id }}&payment_id={{ $record->id }}">Emitir devolución</a>@endcan @endif
@if($module==='consents')<div class="full"><small>Constancia de integridad · {{ $record->signed_at }}</small><p class="hash">{{ $record->document_hash }}</p></div>@endif
@if($module==='messages')<div><small>Estado de envío</small><p>{{ $record->status }}</p></div>@endif
@if($module==='responses')<div class="full"><small>Preguntas al momento de responder</small><p class="prewrap">{{ $record->question_snapshot }}</p></div>@endif
</section>
@if($module==='patients')
@can('clinical.view')@include('odontogram')@endcan
@foreach($sections as $section=>$items)
@if(request('view')==='advanced' || in_array($section,['appointments','clinical','budgets','recalls']))
<section class="card patient-section"><div class="card-heading"><div><h2>{{ \App\Services\Modules::get($section)['title'] }}</h2><p class="muted">{{ request('view')==='advanced'?'Historial reciente (hasta 100 registros)':'Últimos registros' }}</p></div><div class="actions"><a href="/modules/{{ $section }}?patient_id={{ $record->id }}">Ver todos</a>@can(\App\Services\Modules::get($section)['permission'].'.write')<a class="button secondary" href="/modules/{{ $section }}/create?patient_id={{ $record->id }}">+ Agregar</a>@endcan</div></div>
@forelse(request('view')==='advanced'?$items:$items->take(3) as $item)<article class="timeline-item"><span class="timeline-date">{{ $item->created_at->format('d/m/Y') }}</span><div><strong>{{ $item->name ?? $item->diagnosis ?? $item->reason ?? $item->subject ?? ('Registro #'.$item->id) }}</strong>@if($item->treatment)<p class="prewrap">{{ $item->treatment }}</p>@endif @if($item->amount)<p>$ {{ number_format($item->amount,0,',','.') }}</p>@endif @if($section==='budgets')<p>Saldo: $ {{ number_format(\App\Services\Clinic::balance($item),0,',','.') }} · {{ $item->status }}</p>@endif @if($item->body)<p>{{ \Illuminate\Support\Str::limit($item->body,200) }}</p>@endif</div><a href="/modules/{{ $section }}/{{ $item->id }}">Abrir →</a></article>@empty<p class="empty small">Todavía no hay registros.</p>@endforelse</section>
@endif @endforeach @endif
@endsection
