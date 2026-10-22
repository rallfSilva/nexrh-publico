<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? 'Entrar' }} · NexRH Público</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="auth">
    <section class="auth-hero">
        <div class="brand">
            <span class="logo" aria-hidden="true">N</span>
            <span><b>NexRH Público</b><small>Gestão de pessoas para o setor público</small></span>
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
            {{ $slot }}
        </div>
    </section>
</div>
</body>
</html>
