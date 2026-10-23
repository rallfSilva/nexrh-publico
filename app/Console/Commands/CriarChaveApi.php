<?php

namespace App\Console\Commands;

use App\Models\ChaveApi;
use App\Models\Orgao;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nexrh:chave-api
    {orgao : Sigla do órgão}
    {nome : Para qual sistema é a chave}
    {--escopos=unidades : Escopos separados por vírgula (unidades, cargos, servidores, ferias)}
    {--expira= : Vale até (AAAA-MM-DD); sem data, vale até ser revogada}')]
#[Description('Cria uma chave de acesso à API de consulta pela linha de comando (a última linha é a chave)')]
class CriarChaveApi extends Command
{
    public function handle(): int
    {
        $orgao = Orgao::where('sigla', $this->argument('orgao'))->first();
        $escopos = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('escopos')))));
        $invalidos = array_diff($escopos, array_keys(ChaveApi::ESCOPOS));

        if ($orgao === null || $escopos === [] || $invalidos !== []) {
            $this->error($orgao === null ? 'Órgão não encontrado.' : 'Escopos inválidos: '.implode(', ', $invalidos ?: ['nenhum']).'. Use: '.implode(', ', array_keys(ChaveApi::ESCOPOS)).'.');

            return self::FAILURE;
        }

        $gerada = ChaveApi::gerar();
        $chave = ChaveApi::create([
            'orgao_id' => $orgao->id,
            'nome' => $this->argument('nome'),
            'prefixo' => $gerada['prefixo'],
            'hash' => $gerada['hash'],
            'escopos' => $escopos,
            'expira_em' => $this->option('expira'),
        ]);

        $this->info('Chave criada para '.$orgao->sigla.' ('.implode(', ', $escopos).'). Ela não aparece de novo:');
        $this->line($gerada['texto']);

        return self::SUCCESS;
    }
}
