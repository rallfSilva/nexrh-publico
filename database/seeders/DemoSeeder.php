<?php

namespace Database\Seeders;

use App\Models\Orgao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Seeder;

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

        $outroGab = $this->unidade($outro, 'GAB', 'Gabinete', 'gabinete', null, null, 1);
        $this->unidade($outro, 'DAF', 'Diretoria Administrativa e Financeira', 'diretoria', $outroGab, null, 2);
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
