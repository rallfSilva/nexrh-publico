<?php

namespace Database\Seeders;

use App\Importacao\AnalisadorDependentes;
use App\Importacao\AnalisadorQuadroCargos;
use App\Importacao\AnalisadorServidores;
use App\Importacao\AplicadorDependentes;
use App\Importacao\AplicadorQuadroCargos;
use App\Importacao\AplicadorServidores;
use App\Models\Cargo;
use App\Models\Dependente;
use App\Models\Documento;
use App\Models\Importacao;
use App\Models\Orgao;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\User;
use App\Servidores\ArquivoDossie;
use App\Support\Cpf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;
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

        $gab = $this->unidade($selc, 'GAB', 'Gabinete do Secretário', 'gabinete', null, null, 1);
        $this->unidade($selc, 'ASJUR', 'Assessoria Jurídica', 'assessoria', $gab, null, 1);
        $this->unidade($selc, 'COAM', 'Coordenadoria de Administração', 'coordenadoria', $gab, $criados['chefia.coam@nexrh.local'], 2);
        $this->unidade($selc, 'DTI', 'Departamento de Tecnologia da Informação', 'departamento', $gab, $criados['chefia.dti@nexrh.local'], 2);
        $this->unidade($selc, 'DRH', 'Departamento de Recursos Humanos', 'departamento', $gab, null, 2);
        $this->unidade($selc, 'DAC', 'Departamento de Acompanhamento de Contratos', 'departamento', $gab, null, 2);

        $this->quadroDeCargos($selc);

        // A servidora de demonstração tem acesso ao sistema: o CPF (fictício) liga o usuário à ficha.
        $criados['servidor.selc@nexrh.local']->update(['cpf' => '52998224725']);
        $this->servidores($selc);
        $this->dependentes($selc);
        $this->dossie($selc, $criados['rh.selc@nexrh.local']);

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
        return Unidade::withoutGlobalScopes()->firstOrCreate(
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
    }
}
