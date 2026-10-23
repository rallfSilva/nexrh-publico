@php
    $user = auth()->user();
    $nav = fn (string $padrao) => request()->routeIs($padrao) ? 'on' : '';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? 'NexRH Público' }} · NexRH Público</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a href="#conteudo" class="sr-only focus:not-sr-only">Pular para o conteúdo</a>
<div class="app">
    <aside class="side" aria-label="Menu principal">
        <a href="{{ route('painel') }}" class="brand">
            <span class="logo" aria-hidden="true">N</span>
            <span><b>NexRH Público</b><small>Gestão de pessoas</small></span>
        </a>
        <nav class="nav">
            <span class="cap">Visão geral</span>
            <a href="{{ route('painel') }}" class="{{ $nav('painel') }}"><x-icone nome="painel" />Painel</a>
            @if ($user->servidor()->exists() || $user->hasRole('servidor'))
                <a href="{{ route('minha-ficha') }}" class="{{ $nav('minha-ficha*') }}"><x-icone nome="pasta" />Minha ficha</a>
            @endif

            <span class="cap">Estrutura</span>
            @can('orgaos.gerenciar')
                <a href="{{ route('orgaos.index') }}" class="{{ $nav('orgaos.*') }}"><x-icone nome="predio" />Órgãos</a>
            @endcan
            @can('unidades.ver')
                <a href="{{ route('unidades.index') }}" class="{{ $nav('unidades.*') }}"><x-icone nome="organograma" />Unidades e organograma</a>
            @endcan
            @can('cargos.ver')
                <a href="{{ route('cargos.index') }}" class="{{ $nav('cargos.*') || $nav('vagas.*') || $nav('importacoes.*') ? 'on' : '' }}"><x-icone nome="cargos" />Cargos e vagas</a>
            @endcan
            @can('listar-servidores')
                <a href="{{ route('servidores.index') }}" class="{{ $nav('servidores.*') || $nav('declaracoes.*') || $nav('dependentes.*') ? 'on' : '' }}"><x-icone nome="servidor" />Servidores</a>
            @endcan
            @can('gerenciar-dossie')
                @php($aValidar = \App\Models\Documento::where('situacao', 'pendente')->count())
                <a href="{{ route('documentos.pendentes') }}" class="{{ $nav('documentos.*') }}"><x-icone nome="pasta" />Documentos a validar
                    @if ($aValidar)<em class="cnt" aria-label="{{ $aValidar }} aguardando">{{ $aValidar }}</em>@endif
                </a>
            @endcan
            @can('ver-escala')
                @php($feriasAAprovar = $user->can('aprovar-ferias') ? \App\Models\Ferias::aAprovarPor($user)->count() : 0)
                <a href="{{ route('ferias.escala') }}" class="{{ $nav('ferias.*') || $nav('afastamentos.*') ? 'on' : '' }}"><x-icone nome="ferias" />Férias
                    @if ($feriasAAprovar)<em class="cnt" aria-label="{{ $feriasAAprovar }} a aprovar">{{ $feriasAAprovar }}</em>@endif
                </a>
            @endcan

            <span class="cap">Administração</span>
            @can('usuarios.ver')
                <a href="{{ route('usuarios.index') }}" class="{{ $nav('usuarios.*') }}"><x-icone nome="usuarios" />Usuários e perfis</a>
            @endcan
            @can('auditoria.ver')
                <a href="{{ route('auditoria.index') }}" class="{{ $nav('auditoria.*') }}"><x-icone nome="auditoria" />Trilha de auditoria</a>
            @endcan
            <a href="{{ route('modelos.index') }}" class="{{ $nav('modelos.*') }}"><x-icone nome="planilha" />Modelos de planilha</a>
            <a href="{{ route('seguranca') }}" class="{{ $nav('seguranca') }}"><x-icone nome="escudo" />Minha segurança</a>
        </nav>
        <div class="foot">
            Fase 1 · Entrega 5<br>
            Férias, afastamentos e escala do setor.
        </div>
    </aside>

    <div class="main">
        <header class="top">
            <div class="org">
                <span class="chip c-navy"><x-icone nome="predio" /></span>
                <div>
                    <b>{{ $user->orgao?->sigla ?? 'Todos os órgãos' }}</b><br>
                    <span>{{ $user->orgao?->nome ?? 'Administração geral do sistema' }}</span>
                </div>
            </div>
            <div class="me">
                <div class="who">
                    <b>{{ $user->name }}</b>
                    <span>{{ \App\Models\User::PERFIS[$user->perfilPrincipal()] ?? 'Sem perfil' }}</span>
                </div>
                <span class="avatar" aria-hidden="true">{{ $user->iniciais() }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="icobtn" title="Sair do sistema" aria-label="Sair do sistema"><x-icone nome="sair" /></button>
                </form>
            </div>
        </header>

        <main id="conteudo" class="content">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
