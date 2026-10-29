<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DevolveArquivosAoServidorWeb;
use App\Importacao\AnalisadorQuadroCargos;
use App\Importacao\AplicadorQuadroCargos;
use App\Models\Importacao;
use App\Models\Orgao;
use App\Models\User;
use App\Support\Auditoria;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Importa a planilha do quadro de cargos pela linha de comando, como a tela de
 * importação faria: análise, relatório de divergências (CPF sempre mascarado),
 * registro na trilha e, sem bloqueio, a gravação. Útil na primeira carga.
 */
#[Signature('nexrh:importar-cargos
    {arquivo : Caminho da planilha .xlsx}
    {--orgao=SELC : Sigla do órgão}
    {--usuario= : E-mail de quem importa (padrão: o primeiro administrador geral)}
    {--so-analisar : Só mostra o relatório, sem gravar}
    {--criar-fichas : Cria também a ficha (incompleta) de cada ocupante ainda sem cadastro}')]
#[Description('Importa a planilha de estrutura de cargos e vagas')]
class ImportarCargos extends Command
{
    use DevolveArquivosAoServidorWeb;

    public function handle(AnalisadorQuadroCargos $analisador, AplicadorQuadroCargos $aplicador): int
    {
        try {
            return $this->importar($analisador, $aplicador);
        } finally {
            $this->devolverArquivosAoServidorWeb();
        }
    }

    private function importar(AnalisadorQuadroCargos $analisador, AplicadorQuadroCargos $aplicador): int
    {
        $origem = (string) $this->argument('arquivo');
        if (! is_file($origem)) {
            $this->error("Arquivo não encontrado: {$origem}");

            return self::FAILURE;
        }
        $orgao = Orgao::where('sigla', mb_strtoupper($this->option('orgao')))->first();
        $user = $this->option('usuario')
            ? User::where('email', mb_strtolower($this->option('usuario')))->first()
            : User::role('administrador-geral')->orderBy('id')->first();
        if (! $orgao || ! $user) {
            $this->error(! $orgao ? 'Órgão não encontrado.' : 'Usuário não encontrado.');

            return self::FAILURE;
        }
        Auth::setUser($user);

        $caminho = "importacoes/{$orgao->id}/".Str::uuid().'.xlsx';
        Storage::disk('local')->put($caminho, file_get_contents($origem));
        $resultado = $analisador->analisar(Storage::disk('local')->path($caminho));
        $bloqueios = array_filter($resultado['divergencias'], fn ($d) => $d['nivel'] === 'bloqueia');

        $importacao = Importacao::create([
            'orgao_id' => $orgao->id,
            'user_id' => $user->id,
            'tipo' => 'cargos',
            'formato' => $resultado['formato'],
            'arquivo_nome' => mb_substr(basename($origem), 0, 255),
            'arquivo_caminho' => $caminho,
            'arquivo_hash' => hash_file('sha256', $origem),
            'situacao' => $bloqueios === [] ? 'analisada' : 'recusada',
            'resumo' => $resultado['resumo'],
            'divergencias' => $resultado['divergencias'],
            'dados' => $bloqueios === [] ? ['cargos' => $resultado['cargos']] : null,
        ]);
        Auditoria::registrar('importacao', $importacao, 'Planilha "'.$importacao->arquivo_nome.'" enviada para análise pela linha de comando ('.count($resultado['divergencias']).' divergência(s))');

        $c = $resultado['resumo']['calculado'];
        $this->table(['Cargos', 'Vagas', 'Remanejadas', 'Disponíveis', 'Ocupadas', 'Vagas livres'], [[$c['cargos'], $c['total'], $c['remanejadas'], $c['disponiveis'], $c['ocupadas'], $c['vagas']]]);
        foreach ($resultado['divergencias'] as $d) {
            $this->line(sprintf('[%s] %s%s', $d['nivel'], $d['cargo'] ? $d['cargo'].': ' : '', $d['mensagem']));
        }

        if ($bloqueios !== []) {
            $this->error('A planilha tem problemas que impedem a importação. Nada foi gravado.');

            return self::FAILURE;
        }
        if ($this->option('so-analisar')) {
            $this->info("Análise guardada (importação nº {$importacao->id}). Para gravar, confirme na tela Importar planilha ou rode sem --so-analisar.");

            return self::SUCCESS;
        }

        $r = $aplicador->aplicar($importacao, (bool) $this->option('criar-fichas'));
        $this->info(sprintf('Quadro importado: %d cargo(s) criado(s), %d atualizado(s), %d vaga(s).', $r['criados'], $r['atualizados'], $r['vagas']));
        if ($r['fichas']) {
            $this->info(sprintf('Servidores: %d ficha(s) criada(s) e %d ligada(s) à vaga. As fichas novas ficam com a pendência "Ficha incompleta" até o RH completar.', $r['fichas']['criadas'], $r['fichas']['vinculadas']));
            foreach ($r['fichas']['ignoradas'] as $motivo) {
                $this->line('[sem ficha] '.$motivo);
            }
        }

        return self::SUCCESS;
    }
}
