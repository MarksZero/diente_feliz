<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Encuesta · Diente Feliz</title><link rel="stylesheet" href="/clinic.css"></head><body><main>
<section class="card"><h1>Encuesta de atención</h1>
@if(isset($questions))
<p>Tu opinión nos ayuda a revisar la atención. Evalúa cada pregunta del 1 (muy mala) al 5 (muy buena). No incluyas información clínica ni datos personales.</p>
@if($errors->any())<div class="alert error" role="alert">Revisa las respuestas: todas las preguntas requieren una valoración del 1 al 5.</div>@endif
<form method="post" action="/survey/{{ $token }}">@csrf
@foreach($questions as $index=>$question)<label>{{ $question }}<select name="ratings[{{ $index }}]" required><option value="">Seleccionar…</option>@foreach([1=>'Muy mala',2=>'Mala',3=>'Regular',4=>'Buena',5=>'Muy buena'] as $value=>$label)<option value="{{ $value }}" @selected((string)old('ratings.'.$index)===(string)$value)>{{ $value }} · {{ $label }}</option>@endforeach</select></label>@endforeach
<label>Comentario opcional<textarea name="comment" maxlength="2000" rows="4">{{ old('comment') }}</textarea></label><button>Enviar respuestas</button></form>
@else<p>Tu respuesta fue recibida. Puedes cerrar esta página.</p>@endif
</section></main></body></html>
