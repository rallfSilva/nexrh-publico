<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? 'Entrar' }} · NexRH Público</title>
    <x-icones-do-site />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="auth">
    <section class="auth-hero">
        <img src="{{ asset('img/marca/simbolo-contorno.svg') }}" alt="" class="auth-marca-dagua" aria-hidden="true">
        <div class="brand">
            <x-logo :tamanho="48" subtitulo="Gestão de pessoas para o setor público" />
        </div>
        <div>
            <h1>Pessoas, cargos e férias do órgão em um só lugar.</h1>
            <p>Chega de planilhas soltas e pastas perdidas. Cada informação com dono, histórico e acesso controlado.</p>
            <div class="auth-pontos">
                <div><span><x-icone nome="cadeado" /></span>Acesso por perfil, com verificação em duas etapas</div>
                <div><span><x-icone nome="auditoria" /></span>Trilha de auditoria de tudo o que é feito</div>
                <div><span><x-icone nome="ferias" /></span>Férias planejadas sem conflito entre colegas</div>
            </div>
        </div>
        <small style="color:#A9D2EE">Dados protegidos conforme a LGPD (Lei nº 13.709/2018).</small>
    </section>
    <section class="auth-form">
        <div class="auth-box">
            <x-logo :tamanho="44" class="logo-claro auth-logo-form" subtitulo="Gestão de pessoas para o setor público" />
            {{ $slot }}
        </div>
    </section>
</div>
</body>
</html>
