<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('title','Inicio') · Diente Feliz</title><link rel="stylesheet" href="/clinic.css"><script src="/clinic.js" defer></script></head>
<body><a class="skip" href="#content">Saltar al contenido</a><aside class="sidebar"><a class="brand" href="/"><span class="brand-icon">♧</span><span>Diente Feliz<small>GESTIÓN CLÍNICA</small></span></a><div class="workspace"><span class="online"></span> Entorno local <span class="demo">DEMO</span></div><nav aria-label="Principal"><a href="/" class="{{ request()->is('/') ? 'selected' : '' }}">◫ &nbsp; Inicio</a>
@php($group='')
@foreach(\App\Services\Modules::all() as $key=>$item)
@can($item['permission'].'.view')
@if($group!==$item['group'])<small class="nav-label">{{ $item['group'] }}</small>@php($group=$item['group'])@endif
<a href="/modules/{{ $key }}" class="{{ request()->is('modules/'.$key.'*') ? 'selected' : '' }}">{{ $item['title'] }}</a>
@endcan
@endforeach
@can('users.view')<a href="/users">Usuarios y accesos</a>@endcan
@can('roles.view')<a href="/roles">Roles y permisos</a>@endcan
@can('reports.view')<a href="/reports">Reportes</a>@endcan
@can('audit.view')<a href="/audit">Auditoría</a>@endcan
<a href="/help">? &nbsp; Ayuda y tutorial</a></nav><div class="sidebar-footer">Datos de demostración<br><small>La atención comienza con una sonrisa.</small></div></aside>
<div class="shell"><header class="topbar"><button class="menu-toggle secondary" type="button" aria-label="Abrir menú" aria-expanded="false">☰</button><span>Clínica Dental <strong>Diente Feliz</strong></span><div class="user-menu"><a href="/account"><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span> {{ auth()->user()->name }}</a><form method="post" action="/logout">@csrf<button class="text-button">Salir</button></form></div></header><main id="content">
@if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
@if(session('warning'))<div class="alert notice" role="status">{{ session('warning') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert"><strong>No se pudo guardar.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main><footer class="page-footer">Diente Feliz · Sistema de Gestión Clínica Dental <span>Uso académico · Datos ficticios</span></footer></div></body></html>
