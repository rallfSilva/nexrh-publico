<?php

namespace Database\Seeders;

use App\Frequencia\Turnos;
use App\Importacao\AnalisadorAfastamentos;
use App\Importacao\AnalisadorDependentes;
use App\Importacao\AnalisadorFerias;
use App\Importacao\AnalisadorQuadroCargos;
use App\Importacao\AnalisadorServidores;
use App\Importacao\AplicadorAfastamentos;
use App\Importacao\AplicadorDependentes;
use App\Importacao\AplicadorFerias;
use App\Importacao\AplicadorQuadroCargos;
use App\Importacao\AplicadorServidores;
use App\Models\Afastamento;
use App\Models\Cargo;
use App\Models\Dependente;
use App\Models\Documento;
use App\Models\Feriado;
use App\Models\Ferias;
use App\Models\Importacao;
use App\Models\Jornada;
use App\Models\Orgao;
use App\Models\RegistroPonto;
use App\Models\RegraFrequencia;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\User;
use App\Servidores\ArquivoDossie;
use App\Support\Cpf;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dados de demonstração, todos fictícios. Nunca roda em produção.
 * Senha de todos os usuários de demonstração: NexRH@demo2026
 */
class DemoSeeder extends Seeder
{
    public const SENHA = 'NexRH@demo2026';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoSeeder ignorado em produção.');

            return;
        }

        $selc = Orgao::firstOrCreate(['sigla' => 'SELC'], [
            'nome' => 'Secretaria de Estado de Licitação e Contratação',
            'esfera' => 'estadual',
            'uf' => 'RR',
            'municipio' => 'Boa Vista',
        ]);

        $outro = Orgao::firstOrCreate(['sigla' => 'SEDEMO'], [
            'nome' => 'Secretaria de Demonstração (órgão fictício para testar o isolamento)',
            'esfera' => 'estadual',
            'uf' => 'RR',
            'municipio' => 'Boa Vista',
        ]);

        $usuarios = [
            ['admin@nexrh.local', 'Administrador do Sistema', null, 'administrador-geral'],
            ['admin.selc@nexrh.local', 'Helena Duarte Campos', $selc, 'administrador-orgao'],
            ['rh.selc@nexrh.local', 'Marcos Vinícius Teles', $selc, 'rh'],
            ['chefia.dti@nexrh.local', 'Patrícia Moura Lins', $selc, 'chefia'],
            ['chefia.coam@nexrh.local', 'Rodrigo Albuquerque Sá', $selc, 'chefia'],
            ['servidor.selc@nexrh.local', 'Ana Beatriz Martins', $selc, 'servidor'],
            ['auditor.selc@nexrh.local', 'Jorge Henrique Pacheco', $selc, 'auditor'],
            ['admin.sedemo@nexrh.local', 'Carla Fernandes Rocha', $outro, 'administrador-orgao'],
        ];

        $criados = [];

        foreach ($usuarios as [$email, $nome, $orgao, $perfil]) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $nome,
                'orgao_id' => $orgao?->id,
                'password' => self::SENHA,
                'email_verified_at' => now(),
            ]);
            $user->syncRoles([$perfil]);
            $criados[$email] = $user;
        }

        // O mínimo de presentes nas férias acompanha o tamanho de cada setor no exemplo de servidores.
        $gab = $this->unidade($selc, 'GAB', 'Gabinete do Secretário', 'gabinete', null, null, 7);
        $this->unidade($selc, 'ASJUR', 'Assessoria Jurídica', 'assessoria', $gab, null, 6);
        $this->unidade($selc, 'COAM', 'Coordenadoria de Administração', 'coordenadoria', $gab, $criados['chefia.coam@nexrh.local'], 16);
        $this->unidade($selc, 'DTI', 'Departamento de Tecnologia da Informação', 'departamento', $gab, $criados['chefia.dti@nexrh.local'], 11);
        $this->unidade($selc, 'DRH', 'Departamento de Recursos Humanos', 'departamento', $gab, null, 5);
        $this->unidade($selc, 'DAC', 'Departamento de Acompanhamento de Contratos', 'departamento', $gab, null, 22);

        $this->quadroDeCargos($selc);

        // A servidora de demonstração tem acesso ao sistema: o CPF (fictício) liga o usuário à ficha.
        $criados['servidor.selc@nexrh.local']->update(['cpf' => '52998224725']);
        $this->servidores($selc);
        $this->dependentes($selc);
        $this->dossie($selc, $criados['rh.selc@nexrh.local']);
        $this->afastamentosEFerias($selc, $criados['rh.selc@nexrh.local']);
        $this->frequencia($selc, $criados['rh.selc@nexrh.local']);

        $outroGab = $this->unidade($outro, 'GAB', 'Gabinete', 'gabinete', null, null, 1);
        $this->unidade($outro, 'DAF', 'Diretoria Administrativa e Financeira', 'diretoria', $outroGab, null, 2);
    }

    /** Importa a cópia fictícia da planilha de estrutura de cargos, como faria o RH. */
    private function quadroDeCargos(Orgao $orgao): void
    {
        if (Cargo::withoutGlobalScopes()->where('orgao_id', $orgao->id)->exists()) {
            return;
        }

        $origem = resource_path('modelos-importacao/exemplo_estrutura_cargos_ficticia.xlsx');
        $caminho = 'importacoes/'.$orgao->id.'/demonstracao.xlsx';
        Storage::disk('local')->put($caminho, file_get_contents($origem));

        $resultado = (new AnalisadorQuadroCargos)->analisar($origem);

        $importacao = Importacao::create([
            'orgao_id' => $orgao->id,
            'tipo' => 'cargos',
            'formato' => $resultado['formato'],
            'arquivo_nome' => 'estrutura-de-cargos-ficticia.xlsx',
            'arquivo_caminho' => $caminho,
            'arquivo_hash' => hash_file('sha256', $origem),
            'situacao' => 'analisada',
            'resumo' => $resultado['resumo'],
            'divergencias' => $resultado['divergencias'],
            'dados' => ['cargos' => $resultado['cargos']],
        ]);

        (new AplicadorQuadroCargos)->aplicar($importacao);
    }

    /** Importa a planilha modelo 03 com servidores fictícios, como faria o RH. */
    private function servidores(Orgao $orgao): void
    {
        if (Servidor::withoutGlobalScopes()->where('orgao_id', $orgao->id)->exists()) {
            return;
        }

        $origem = resource_path('modelos-importacao/exemplo_servidores_ficticio.xlsx');
        $caminho = 'importacoes/'.$orgao->id.'/demonstracao-servidores.xlsx';
        Storage::disk('local')->put($caminho, file_get_contents($origem));

        $resultado = (new AnalisadorServidores)->analisar($origem, $orgao->id);

        $importacao = Importacao::create([
            'orgao_id' => $orgao->id,
            'tipo' => 'servidores',
            'formato' => $resultado['formato'],
            'arquivo_nome' => 'servidores-ficticios.xlsx',
            'arquivo_caminho' => $caminho,
            'arquivo_hash' => hash_file('sha256', $origem),
            'situacao' => 'analisada',
            'resumo' => $resultado['resumo'],
            'divergencias' => $resultado['divergencias'],
            'dados' => ['servidores' => $resultado['servidores']],
        ]);

        app(AplicadorServidores::class)->aplicar($importacao);
    }

    /** Importa a planilha modelo 04 com dependentes fictícios dos servidores fictícios. */
    private function dependentes(Orgao $orgao): void
    {
        if (Dependente::withoutGlobalScopes()->where('orgao_id', $orgao->id)->exists()) {
            return;
        }

        $origem = resource_path('modelos-importacao/exemplo_dependentes_ficticio.xlsx');
        $caminho = 'importacoes/'.$orgao->id.'/demonstracao-dependentes.xlsx';
        Storage::disk('local')->put($caminho, file_get_contents($origem));

        $resultado = (new AnalisadorDependentes)->analisar($origem, $orgao->id);

        $importacao = Importacao::create([
            'orgao_id' => $orgao->id,
            'tipo' => 'dependentes',
            'formato' => $resultado['formato'],
            'arquivo_nome' => 'dependentes-ficticios.xlsx',
            'arquivo_caminho' => $caminho,
            'arquivo_hash' => hash_file('sha256', $origem),
            'situacao' => 'analisada',
            'resumo' => $resultado['resumo'],
            'divergencias' => $resultado['divergencias'],
            'dados' => ['dependentes' => $resultado['dependentes']],
        ]);

        (new AplicadorDependentes)->aplicar($importacao);
    }

    /** Importa os exemplos dos modelos 06 e 05: os afastamentos primeiro, porque as férias desviam deles. */
    private function afastamentosEFerias(Orgao $orgao, User $rh): void
    {
        $tipos = [
            'afastamentos' => [Afastamento::class, AnalisadorAfastamentos::class, AplicadorAfastamentos::class, 'exemplo_afastamentos_ficticio.xlsx'],
            'ferias' => [Ferias::class, AnalisadorFerias::class, AplicadorFerias::class, 'exemplo_ferias_ficticio.xlsx'],
        ];

        foreach ($tipos as $tipo => [$modelo, $analisador, $aplicador, $arquivo]) {
            if ($modelo::withoutGlobalScopes()->where('orgao_id', $orgao->id)->exists()) {
                continue;
            }

            $origem = resource_path('modelos-importacao/'.$arquivo);
            $caminho = 'importacoes/'.$orgao->id.'/demonstracao-'.$tipo.'.xlsx';
            Storage::disk('local')->put($caminho, file_get_contents($origem));

            $resultado = app($analisador)->analisar($origem, $orgao->id);

            $importacao = Importacao::create([
                'orgao_id' => $orgao->id,
                'user_id' => $rh->id,
                'tipo' => $tipo,
                'formato' => $resultado['formato'],
                'arquivo_nome' => str_replace('exemplo_', '', $arquivo),
                'arquivo_caminho' => $caminho,
                'arquivo_hash' => hash_file('sha256', $origem),
                'situacao' => 'analisada',
                'resumo' => $resultado['resumo'],
                'divergencias' => $resultado['divergencias'],
                'dados' => [$tipo => $resultado[$tipo]],
            ]);

            app($aplicador)->aplicar($importacao);
        }
    }

    /**
     * Frequência de demonstração: feriados, algumas jornadas diferentes (8h, teletrabalho,
     * dispensa) e as marcações do mês passado até hoje, com faltas, atrasos e registros
     * incompletos de propósito. Tudo gerado aqui, com sorteio fixo por servidor e dia.
     */
    private function frequencia(Orgao $orgao, User $rh): void
    {
        if (RegistroPonto::withoutGlobalScopes()->where('orgao_id', $orgao->id)->exists()) {
            return;
        }

        $inicio = today()->subMonthNoOverflow()->startOfMonth();
        $hoje = today();
        Feriado::semAuditoria(function () use ($inicio, $hoje, $orgao) {
            foreach (array_unique([$inicio->year, $hoje->year, $hoje->year + 1]) as $ano) {
                foreach (Feriado::sugeridos($ano) as $data => [$nome, $tipo]) {
                    Feriado::withoutGlobalScopes()->firstOrCreate(['orgao_id' => $orgao->id, 'data' => $data], ['nome' => $nome, 'tipo' => $tipo]);
                }
            }
        });
        $feriados = Feriado::withoutGlobalScopes()->where('orgao_id', $orgao->id)->pluck('data')->map->format('Y-m-d')->flip();
        $regra = RegraFrequencia::doOrgao($orgao->id);

        $servidores = Servidor::withoutGlobalScopes()->with('unidade')->where('orgao_id', $orgao->id)->where('situacao', 'ativo')->orderBy('id')->get();
        $porSigla = $servidores->groupBy(fn ($s) => $s->unidade?->sigla);
        $jornadas = [];
        $novaJornada = function (Servidor $s, string $modalidade, ?array $turnos, string $obs) use ($orgao, $rh, $inicio, &$jornadas) {
            $jornadas[$s->id] = Jornada::withoutGlobalScopes()->create([
                'orgao_id' => $orgao->id, 'servidor_id' => $s->id, 'modalidade' => $modalidade, 'turnos' => $turnos,
                'dias_semana' => [1, 2, 3, 4, 5], 'inicio' => $inicio->copy()->subYear(), 'observacao' => $obs, 'registrado_por' => $rh->id,
            ]);
        };
        Jornada::semAuditoria(function () use ($porSigla, $regra, $novaJornada) {
            foreach (($porSigla['COAM'] ?? collect())->take(4) as $s) {
                $novaJornada($s, 'presencial', $regra->turnos_8h, 'Jornada de 8h (demonstração)');
            }
            foreach (($porSigla['DTI'] ?? collect())->slice(-2) as $s) {
                $novaJornada($s, 'teletrabalho', null, 'Teletrabalho (demonstração)');
            }
            if ($s = ($porSigla['GAB'] ?? collect())->first()) {
                $novaJornada($s, 'dispensado', null, 'Dispensado do ponto (demonstração)');
            }
        });

        $ids = $servidores->pluck('id');
        $fora = [];
        foreach ([
            Ferias::withoutGlobalScopes()->whereIn('situacao', ['aprovada', 'interrompida'])->whereIn('servidor_id', $ids)->entre($inicio, $hoje)->get(),
            Afastamento::withoutGlobalScopes()->whereNull('cancelado_em')->whereIn('servidor_id', $ids)->entre($inicio, $hoje)->get(),
        ] as $lista) {
            foreach ($lista as $a) {
                foreach (CarbonPeriod::create($a->inicio->max($inicio), $a->fim->min($hoje)) as $d) {
                    $fora[$a->servidor_id][$d->format('Y-m-d')] = true;
                }
            }
        }

        $linhas = [];
        $marcar = function (Servidor $s, Carbon $dia, int $minutos, array $extra = []) use (&$linhas, $orgao) {
            $linhas[] = [
                'orgao_id' => $orgao->id, 'servidor_id' => $s->id, 'momento' => $dia->copy()->addMinutes($minutos)->format('Y-m-d H:i:00'),
                'origem' => 'sistema', 'ip' => '10.20.'.(($s->unidade_id ?? 0) % 8 + 1).'.'.($s->id % 200 + 20), 'registrado_por' => $s->user_id,
                'motivo' => null, 'anulado_em' => null, 'anulado_por' => null, 'motivo_anulacao' => null,
                'created_at' => now(), 'updated_at' => now(), ...$extra,
            ];
        };

        foreach ($servidores as $s) {
            $jornada = $jornadas[$s->id] ?? Jornada::padrao($s, $regra);
            if (! $jornada->registraPonto()) {
                continue;
            }
            $turnos = array_map(fn ($t) => [Turnos::emMinutos($t[0]), Turnos::emMinutos($t[1])], $jornada->turnos);
            $ingresso = $s->ingresso()?->copy()->startOfDay();

            foreach (CarbonPeriod::create($inicio, $hoje) as $dia) {
                $chave = $dia->format('Y-m-d');
                if (! $jornada->trabalhaEm($dia) || isset($feriados[$chave]) || isset($fora[$s->id][$chave]) || ($ingresso && $dia->lt($ingresso))) {
                    continue;
                }
                mt_srand(crc32($s->id.'|'.$chave));
                $sorte = mt_rand(0, 999);
                $entrada = $turnos[0][0] + ($sorte >= 40 && $sorte < 110 ? mt_rand(15, 50) : mt_rand(-12, 8));
                $saida = end($turnos)[1] + ($sorte >= 110 && $sorte < 150 ? -mt_rand(20, 45) : mt_rand(0, 14));

                if ($dia->isToday()) {
                    // Hoje: só quem já teria chegado, e ninguém saiu ainda.
                    if (now()->hour * 60 + now()->minute >= $entrada && $sorte >= 15) {
                        $marcar($s, $dia, $entrada);
                    }

                    continue;
                }
                if ($sorte < 15) {
                    continue; // sem registro
                }
                $marcar($s, $dia, $entrada);
                if (count($turnos) > 1) {
                    $marcar($s, $dia, $turnos[0][1] + mt_rand(0, 6));
                    $marcar($s, $dia, $turnos[1][0] + mt_rand(-6, 4));
                }
                if ($sorte >= 25 && $sorte < 40) {
                    continue; // esqueceu a saída: registro incompleto
                }
                $marcar($s, $dia, $saida);
            }
        }

        // Correções do RH: uma marcação esquecida incluída e uma duplicada anulada.
        $ana = $servidores->firstWhere('cpf_hash', Cpf::hash('52998224725'));
        $diaUtil = fn (int $n) => collect(CarbonPeriod::create($inicio, $hoje->copy()->subDay()))
            ->filter(fn ($d) => $d->isWeekday() && ! isset($feriados[$d->format('Y-m-d')]) && ! isset($fora[$ana?->id][$d->format('Y-m-d')]))->values()->get($n);
        if ($ana && ($dia = $diaUtil(3))) {
            $chave = $dia->format('Y-m-d');
            $linhas = array_values(array_filter($linhas, fn ($l) => ! ($l['servidor_id'] === $ana->id && str_starts_with($l['momento'], $chave) && $l['momento'] > $chave.' 12:00')));
            $marcar($ana, $dia, 13 * 60 + 32, ['origem' => 'rh', 'ip' => null, 'registrado_por' => $rh->id, 'motivo' => 'Esqueceu de registrar a saída; confirmado pela chefia (demonstração)']);
        }
        if ($ana && ($dia = $diaUtil(6))) {
            $primeira = collect($linhas)->first(fn ($l) => $l['servidor_id'] === $ana->id && str_starts_with($l['momento'], $dia->format('Y-m-d')));
            if ($primeira) {
                $marcar($ana, $dia, Turnos::emMinutos(substr($primeira['momento'], 11, 5)) + 1, [
                    'anulado_em' => now(), 'anulado_por' => $rh->id, 'motivo_anulacao' => 'Marcação em duplicidade (demonstração)',
                ]);
            }
        }

        foreach (array_chunk($linhas, 1000) as $lote) {
            RegistroPonto::insertOrIgnore($lote);
        }
    }

    /**
     * Dossiê de demonstração: a servidora Ana com documentos em todas as situações
     * (validados, aguardando validação e um recusado) e alguns servidores com parte
     * dos documentos da posse. Os arquivos são PDFs gerados aqui, com a marca FICTÍCIO.
     */
    private function dossie(Orgao $orgao, User $rh): void
    {
        if (Documento::withoutGlobalScopes()->where('orgao_id', $orgao->id)->exists()) {
            return;
        }

        $arquivos = app(ArquivoDossie::class);
        $ana = Servidor::withoutGlobalScopes()->where('orgao_id', $orgao->id)->where('cpf_hash', Cpf::hash('52998224725'))->firstOrFail();

        // Um PDF por tipo de documento (gerar um por servidor deixaria a instalação lenta).
        $pdfs = [];
        $incluir = function (Servidor $s, string $tipo, array $extra = []) use ($arquivos, $rh, &$pdfs) {
            $categoria = collect(Documento::TIPOS)->search(fn ($tipos) => array_key_exists($tipo, $tipos));
            $rotulo = $extra['descricao'] ?? Documento::TIPOS[$categoria][$tipo];
            $pdf = $pdfs[$rotulo] ??= Pdf::loadHTML('<div style="font-family:DejaVu Sans,sans-serif;padding:40px;color:#0F2A3A">'
                .'<p style="color:#9F1D1D;font-weight:bold;font-size:22px;border:3px solid #9F1D1D;padding:10px;text-align:center">DOCUMENTO FICTÍCIO · DEMONSTRAÇÃO</p>'
                .'<h1 style="color:#1E5470">'.e($rotulo).'</h1>'
                .'<p>Este arquivo foi gerado pelo NexRH Público para mostrar o dossiê digital. Não tem validade nem dados de pessoas reais.</p></div>')
                ->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output();
            $pelaServidora = ($extra['situacao'] ?? 'validado') !== 'validado' || ($extra['enviado_pelo_servidor'] ?? false);

            Documento::create([
                'orgao_id' => $s->orgao_id,
                'servidor_id' => $s->id,
                'categoria' => $categoria,
                'tipo' => $tipo,
                'situacao' => 'validado',
                ...$extra,
                ...$arquivos->guardarConteudo($s, $pdf, Str::slug($rotulo).'.pdf', 'application/pdf'),
                'enviado_por' => $pelaServidora ? $s->user_id : $rh->id,
                'enviado_pelo_servidor' => $pelaServidora,
                'analisado_por' => ($extra['situacao'] ?? 'validado') === 'pendente' ? null : $rh->id,
                'analisado_em' => ($extra['situacao'] ?? 'validado') === 'pendente' ? null : now(),
            ]);
        };

        Documento::semAuditoria(function () use ($incluir, $ana, $orgao) {
            foreach (['rg', 'cpf', 'certidao_civil', 'titulo_eleitor', 'decreto_nomeacao', 'termo_posse'] as $tipo) {
                $incluir($ana, $tipo, ['data_documento' => '2026-06-20']);
            }
            $incluir($ana, 'comprovante_residencia', ['data_documento' => '2026-08-10', 'enviado_pelo_servidor' => true]);
            $incluir($ana, 'diploma', ['descricao' => 'Bacharelado em Ciências Contábeis', 'data_documento' => '2005-12-15']);
            $incluir($ana, 'aso', ['situacao' => 'pendente', 'data_documento' => '2026-10-15', 'validade' => '2027-10-15']);
            $incluir($ana, 'certificado_curso', ['situacao' => 'pendente', 'descricao' => 'Curso Nova Lei de Licitações (40h)', 'data_documento' => '2026-09-30']);
            $incluir($ana, 'pis_pasep', ['situacao' => 'recusado', 'motivo' => 'Imagem ilegível. Envie de novo, com boa iluminação e sem cortes.']);
            $incluir($ana, 'cnh', ['data_documento' => '2021-03-02', 'validade' => '2026-03-02']);

            $outros = Servidor::withoutGlobalScopes()->where('orgao_id', $orgao->id)->whereKeyNot($ana->id)->orderBy('id')->limit(6)->get();
            foreach ($outros as $i => $s) {
                foreach (array_slice(array_keys(Documento::EXIGIDOS_POSSE), 0, 10 - $i) as $tipo) {
                    $incluir($s, $tipo, ['data_documento' => '2026-06-20']);
                }
            }
        });
    }

    private function unidade(Orgao $orgao, string $sigla, string $nome, string $tipo, ?Unidade $pai, ?User $chefe, int $minimo): Unidade
    {
        $unidade = Unidade::withoutGlobalScopes()->firstOrCreate(
            ['orgao_id' => $orgao->id, 'sigla' => $sigla],
            [
                'nome' => $nome,
                'tipo' => $tipo,
                'parent_id' => $pai?->id,
                'chefe_user_id' => $chefe?->id,
                'minimo_presentes_ferias' => $minimo,
                'email' => strtolower($sigla).'@'.strtolower($orgao->sigla).'.exemplo.gov.br',
            ],
        );

        // Instalações anteriores à Entrega 5 têm o mínimo antigo (1 ou 2): passa ao do exemplo, sem mexer no que o RH mudou.
        if ($unidade->minimo_presentes_ferias !== $minimo && in_array($unidade->minimo_presentes_ferias, [1, 2], true)) {
            $unidade->update(['minimo_presentes_ferias' => $minimo]);
        }

        return $unidade;
    }
}
