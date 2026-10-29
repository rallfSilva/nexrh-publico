<?php

namespace App\Console\Commands;

use App\Models\Orgao;
use App\Models\User;
use App\Support\Auditoria;
use Database\Seeders\PerfisSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tira a demonstração para começar com os dados reais: esvazia todas as tabelas
 * (servidores, cargos, unidades, férias, ponto e usuários fictícios) e os arquivos
 * enviados, e deixa só os perfis, o órgão e um administrador geral com senha nova.
 * A trilha de auditoria fica: o banco não deixa apagá-la, e a numeração continua, para
 * nenhum registro novo se confundir com um antigo. Não tem volta: faça a cópia antes.
 */
#[Signature('nexrh:limpar-demonstracao
    {--confirmar= : Escreva APAGAR para confirmar}
    {--orgao-sigla=SELC : Sigla do órgão}
    {--orgao-nome=Secretaria de Estado de Licitação e Contratação : Nome do órgão}
    {--email=admin@nexrh.local : E-mail (login) do administrador geral}
    {--nome=Administrador do Sistema : Nome do administrador geral}
    {--senha-em= : Arquivo onde gravar a senha gerada (se omitido, ela é mostrada na tela)}')]
#[Description('Apaga todos os dados de demonstração e deixa o sistema pronto para os dados reais')]
class LimparDemonstracao extends Command
{
    public function handle(): int
    {
        if ($this->option('confirmar') !== 'APAGAR') {
            $this->error('Isto apaga TODOS os dados do sistema. Faça a cópia de segurança e rode de novo com --confirmar=APAGAR.');

            return self::FAILURE;
        }
        if (filter_var($this->option('email'), FILTER_VALIDATE_EMAIL) === false) {
            $this->error('E-mail do administrador inválido.');

            return self::FAILURE;
        }

        $this->info('Apagando o banco de dados...');
        $tabelas = collect(Schema::getTables(schema: DB::connection()->getConfig('search_path') ?: 'public'))
            // A trilha de auditoria não se apaga (o banco recusa): a da demonstração fica como histórico.
            ->pluck('name')->reject(fn ($t) => in_array($t, ['migrations', 'registros_auditoria'], true))
            ->map(fn ($t) => '"'.str_replace('"', '', $t).'"');
        DB::statement('TRUNCATE TABLE '.$tabelas->implode(', ').' CASCADE');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('db:seed', ['--class' => PerfisSeeder::class, '--force' => true]);

        $this->info('Apagando os arquivos enviados (dossiê, anexos e planilhas)...');
        $disco = Storage::disk('local');
        foreach ($disco->directories() as $pasta) {
            $disco->deleteDirectory($pasta);
        }
        foreach ($disco->files() as $arquivo) {
            if (! str_starts_with(basename($arquivo), '.')) {
                $disco->delete($arquivo);
            }
        }

        $orgao = Orgao::create([
            'sigla' => mb_strtoupper($this->option('orgao-sigla')),
            'nome' => $this->option('orgao-nome'),
            'esfera' => 'estadual',
            'uf' => 'RR',
            'municipio' => 'Boa Vista',
        ]);

        // Senha forte gerada aqui: nada da demonstração (como a senha conhecida) sobra.
        $senha = Str::password(16, symbols: false).'a1A';
        $admin = User::create([
            'name' => $this->option('nome'),
            'email' => mb_strtolower($this->option('email')),
            'password' => $senha,
            'orgao_id' => null,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('administrador-geral');
        Auditoria::registrar('alterado', $orgao, 'Dados de demonstração removidos pela linha de comando; sistema pronto para os dados reais');

        $this->info("Pronto. Órgão {$orgao->sigla} criado, sem servidores, cargos nem unidades.");
        if ($arquivo = $this->option('senha-em')) {
            file_put_contents($arquivo, "NexRH Público: administrador geral\nLogin: {$admin->email}\nSenha: {$senha}\n\nNo primeiro acesso o sistema pede a verificação em duas etapas. Apague este arquivo depois de guardar a senha.\n");
            $this->line("Login: {$admin->email}. A senha foi gravada em {$arquivo}.");
        } else {
            $this->line("Login: {$admin->email}");
            $this->line("Senha: {$senha}");
        }
        $this->line('No primeiro acesso o sistema pede a verificação em duas etapas. Deixe NEXRH_DEMO=false no .env para a demonstração não voltar.');

        return self::SUCCESS;
    }
}
