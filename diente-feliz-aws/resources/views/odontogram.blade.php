@php
    $current = \App\Services\Odontogram::current($teeth);
    $colors = \App\Services\Odontogram::COLORS;
    $canWrite = auth()->user()->permits('clinical.write');
    $base = '/modules/odontogram'.($canWrite ? '/create' : '').'?patient_id='.$record->id;
    $dentitions = [
        'Permanente' => [array_merge(range(18,11),range(21,28)),array_merge(range(48,41),range(31,38))],
        'Temporal' => [array_merge(range(55,51),range(61,65)),array_merge(range(85,81),range(71,75))],
    ];
@endphp
<section class="card dental-chart" id="odontograma" aria-labelledby="odontogram-title">
    <div class="card-heading">
        <div><h2 id="odontogram-title">Odontograma</h2><p class="muted">Nomenclatura FDI · Vista desde el profesional: derecha del paciente a la izquierda del dibujo.</p></div>
        <a href="/modules/odontogram?patient_id={{ $record->id }}">Historial completo</a>
    </div>
    <p>{{ $canWrite ? 'Selecciona una superficie para registrar un hallazgo, o el modelo del diente para registrar la pieza completa.' : 'Consulta el estado de cada superficie y su historial.' }} Las piezas sin registro no se consideran sanas.</p>
    <p class="muted">V: vestibular · L/P: lingual o palatina · M: mesial · D: distal · O/I: oclusal o incisal. Colores de uso interno; no sustituyen la evaluación clínica.</p>
    <div class="dental-legend">
        @foreach($colors as $condition=>$color)
            <span><svg width="16" height="16" aria-hidden="true"><rect width="16" height="16" fill="{{ $color }}" stroke="#64748b"/></svg> {{ $condition }}</span>
        @endforeach
    </div>
    @foreach($dentitions as $dentition=>$rows)
        <h3>{{ $dentition }}</h3>
        <div class="dental-scroll" tabindex="0" aria-label="Dentición {{ strtolower($dentition) }}; desplaza horizontalmente en pantallas pequeñas">
            @foreach($rows as $rowIndex=>$row)
                <p class="muted">Arcada {{ $rowIndex===0 ? 'superior' : 'inferior' }}</p>
                <div class="dental-row">
                    @foreach($row as $tooth)
                        @php
                            $findings = $current->where('tooth',$tooth);
                            $whole = $findings->firstWhere('surface','Completa');
                            $right = in_array(intdiv($tooth,10),[1,4,5,8]);
                            $top = $rowIndex===0 ? 'Vestibular' : 'Lingual';
                            $bottom = $rowIndex===0 ? 'Lingual' : 'Vestibular';
                            $left = $right ? 'Distal' : 'Mesial';
                            $rightSurface = $right ? 'Mesial' : 'Distal';
                            $surfaces = [
                                [$top,'0,0 60,0 42,18 18,18',30,11,$top==='Vestibular'?'V':'L/P'],
                                [$bottom,'0,60 18,42 42,42 60,60',30,55,$bottom==='Vestibular'?'V':'L/P'],
                                [$left,'0,0 18,18 18,42 0,60',8,33,substr($left,0,1)],
                                [$rightSurface,'60,0 60,60 42,42 42,18',52,33,substr($rightSurface,0,1)],
                                ['Oclusal','18,18 42,18 42,42 18,42',30,33,$tooth%10<=3?'I':'O'],
                            ];
                        @endphp
                        <div class="dental-piece">
                            @php
    $position=$tooth%10;
    $temporary=intdiv($tooth,10)>=5;
    $type=$position<=2?'Incisivo':($position===3?'Canino':(($temporary || $position>=6)?'Molar':'Premolar'));
    $path=match($type) {
        'Incisivo'=>'M18 10 Q30 6 42 10 L41 32 Q37 42 34 67 Q30 80 26 67 Q23 42 19 32 Z',
        'Canino'=>'M16 21 L30 6 L44 21 Q44 33 38 40 L33 72 Q30 82 27 72 L22 40 Q16 33 16 21 Z',
        'Premolar'=>'M13 16 Q19 5 30 14 Q41 5 47 16 L44 35 Q40 43 39 65 Q37 78 33 64 L30 48 L27 64 Q23 78 21 65 Q20 43 16 35 Z',
        default=>'M8 17 Q13 6 23 13 Q30 7 37 13 Q47 6 52 17 L49 36 Q46 45 46 67 Q42 80 37 63 L30 47 L23 63 Q18 80 14 67 Q14 45 11 36 Z',
    };
@endphp
<a class="dental-model" href="{{ $base }}&tooth={{ $tooth }}&surface=Completa" aria-label="Seleccionar {{ strtolower($type) }}, pieza {{ $tooth }}">
<svg viewBox="0 0 60 84" aria-hidden="true" class="tooth-model {{ $rowIndex===0?'lower-tooth':'' }}"><path d="{{ $path }}" fill="{{ $colors[$whole?->condition ?? 'Sin registro'] ?? '#f1f5f9' }}" stroke="#64748b" stroke-width="1.5"/><path d="M20 24 Q30 30 40 24 M30 28 L30 42" fill="none" stroke="#94a3b8"/></svg>
</a><small>{{ $type }}</small>
<a class="dental-number" href="{{ $base }}&tooth={{ $tooth }}&surface=Completa" aria-label="Pieza {{ $tooth }}, completa">{{ $tooth }}</a>
                            <svg viewBox="-2 -2 64 64" role="group" aria-label="Superficies de la pieza {{ $tooth }}">
                                @foreach($surfaces as [$surface,$points,$x,$y,$letter])
                                    @php
                                        $finding = $findings->firstWhere('surface',$surface) ?? $whole;
                                        $condition = $finding?->condition ?? 'Sin registro';
                                    @endphp
                                    <a href="{{ $base }}&tooth={{ $tooth }}&surface={{ urlencode($surface) }}" aria-label="Pieza {{ $tooth }}, {{ $surface }}, {{ $condition }}">
                                        <title>{{ $surface }}: {{ $condition }}</title>
                                        <polygon points="{{ $points }}" fill="{{ $colors[$condition] ?? $colors['Sin registro'] }}" stroke="#64748b"/>
                                        <text x="{{ $x }}" y="{{ $y }}" text-anchor="middle">{{ $letter }}</text>
                                    </a>
                                @endforeach
                            </svg>
                            <small>{{ $whole?->condition ?? ($findings->isEmpty()?'Sin registro':'Por superficie') }}</small>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endforeach
    <details class="dental-history"><summary>Hallazgos vigentes y observaciones ({{ $current->count() }})</summary>
        <p>Un registro de pieza completa sustituye los hallazgos anteriores de esa pieza en el dibujo. Un registro posterior por superficie actualiza esa superficie. El historial original se conserva. Se muestran únicamente las sucursales autorizadas.</p>
        @forelse($current as $finding)
            <p><a href="/modules/odontogram/{{ $finding->id }}">Pieza {{ $finding->tooth }} · {{ $finding->surface }} · {{ $finding->condition }}</a> · {{ $finding->created_at?->format('d/m/Y H:i') }} · Sucursal #{{ $finding->branch_id }}<br>{{ $finding->notes }}</p>
        @empty<p>No hay hallazgos registrados.</p>@endforelse
    </details>
</section>
