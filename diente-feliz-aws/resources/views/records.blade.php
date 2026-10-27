@extends('layout')
@section('title',$definition['title'])
@section('content')
<div class="page-heading"><div><span class="eyebrow">{{ $definition['group'] }}</span><h1>{{ $definition['title'] }}</h1><p class="muted">Consulta y gestiona la información de tus sucursales.</p></div>@if(empty($definition['readonly']))@can($definition['permission'].'.write')<a class="button" href="/modules/{{ $module }}/create">+ Nuevo registro</a>@endcan
@endif</div>
<section class="card"><form class="filters" method="get">
@if(isset($definition['fields']['name']))<label>Nombre<input name="q" value="{{ request('q') }}" maxlength="100"></label>@endif
@if(isset($definition['fields']['address']))<label>Dirección<input name="address" value="{{ request('address') }}" maxlength="250"></label>@endif
@foreach(['branch_id'=>'Sucursal','patient_id'=>'Paciente','professional_id'=>'Profesional','room_id'=>'Box','status'=>'Estado','active'=>'Habilitado'] as $field=>$label)
@if(isset($options[$field]))<label>{{ $label }}<select name="{{ $field }}"><option value="">Todos</option>@foreach($options[$field] as $value=>$text)<option value="{{ $value }}" @selected((string)request($field)===(string)$value)>{{ $text }}</option>@endforeach</select></label>@endif
@endforeach
@if(\App\Services\Modules::dateFilter($module))
<label>{{ $module==='recalls'?'Contacto previsto desde':(in_array($module,['appointments','availability'])?'Inicio desde':'Registro desde') }}<input type="date" name="from" value="{{ request('from') }}"></label><label>Hasta<input type="date" name="to" value="{{ request('to') }}"></label>
@endif
<button>Filtrar</button><a href="/modules/{{ $module }}">Limpiar</a></form>
@if($module==='responses')<p class="notice">Las respuestas se reciben desde enlaces personales generados en Encuestas. No se cargan manualmente desde administración.</p>@endif
<form method="get" action="/modules/{{ $module }}/export"><div class="list-toolbar"><span>{{ $records->total() }} registros</span>@can('exports.view')<div class="actions"><select name="format" aria-label="Formato de descarga"><option value="csv">CSV / Excel</option><option value="html">Documento imprimible / PDF</option>@if($module==='documents')<option value="zip">Archivos ZIP</option>@endif</select>@foreach(request()->only(['q','address','active','branch_id','patient_id','professional_id','room_id','status','from','to','tooth','surface']) as $k=>$v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach<button class="secondary">Descargar (máx. 100)</button></div>@endcan</div>
<div class="table-wrap"><table><thead><tr>@can('exports.view')<th><input type="checkbox" class="select-all" aria-label="Seleccionar todos"></th>@endcan<th>Registro</th>@foreach(array_slice($definition['fields'],0,4,true) as $field=>$spec)<th>{{ $spec[0] }}</th>@endforeach<th></th></tr></thead><tbody>@forelse($records as $row)<tr>@can('exports.view')<td><input type="checkbox" name="ids[]" value="{{ $row->id }}" aria-label="Seleccionar registro {{ $row->id }}"></td>@endcan<td><span class="record-id">#{{ str_pad($row->id,4,'0',STR_PAD_LEFT) }}</span></td>@foreach(array_slice($definition['fields'],0,4,true) as $field=>$spec)<td>{{ \Illuminate\Support\Str::limit((string)($options[$field][$row->$field] ?? $row->$field),80) }}</td>@endforeach<td><a href="/modules/{{ $module }}/{{ $row->id }}">Abrir →</a></td></tr>@empty<tr><td colspan="8" class="empty">No hay registros para estos filtros.</td></tr>@endforelse</tbody></table></div></form>{{ $records->links() }}</section>
@endsection
