<?php

namespace Tests\Feature;

use App\Models\Dependente;
use App\Models\Documento;
use App\Models\Servidor;
use App\Models\User;
use App\Models\Vaga;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PerfisSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstalacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_perfis_podem_ser_semeados_varias_vezes(): void
    {
        $this->seed(PerfisSeeder::class);
        $this->seed(PerfisSeeder::class);

        $this->assertSame(6, Role::count());
        $this->assertTrue(Role::findByName('auditor')->hasPermissionTo('auditoria.ver'));
        $this->assertFalse(Role::findByName('auditor')->hasPermissionTo('usuarios.gerenciar'));
    }

    public function test_comando_de_instalacao_cria_orgao_e_administrador_geral(): void
    {
        $this->artisan('nexrh:instalar', [
            '--orgao-nome' => 'Secretaria de Teste',
            '--orgao-sigla' => 'sete',
            '--nome' => 'Admin Teste',
            '--email' => 'Admin@Teste.gov.br',
            '--senha' => 'SenhaForte123',
        ])->assertSuccessful();

        $admin = User::where('email', 'admin@teste.gov.br')->firstOrFail();
        $this->assertNull($admin->orgao_id);
        $this->assertTrue($admin->ehAdministradorGeral());
        $this->assertDatabaseHas('orgaos', ['sigla' => 'SETE']);
    }

    public function test_comando_recusa_senha_fraca(): void
    {
        $this->artisan('nexrh:instalar', [
            '--orgao-nome' => 'X', '--orgao-sigla' => 'X', '--nome' => 'X', '--email' => 'x@x.gov.br', '--senha' => '123',
        ])->assertFailed();
    }

    public function test_dados_de_demonstracao_sao_ficticios_e_consistentes(): void
    {
        Storage::fake('local');
        $this->seed(PerfisSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(8, User::count());
        $this->assertSame(168, Vaga::withoutGlobalScopes()->count());
        $this->assertTrue(User::where('email', 'admin@nexrh.local')->first()->ehAdministradorGeral());
        // Só a servidora de demonstração tem CPF, e é o CPF de teste usado em exemplos (fictício).
        $this->assertSame(['52998224725'], User::whereNotNull('cpf')->pluck('cpf')->all());

        $this->assertSame(94, Servidor::withoutGlobalScopes()->count());
        $this->assertSame('Ana Beatriz Martins', User::where('email', 'servidor.selc@nexrh.local')->first()->servidor->nome);
        $this->assertSame(0, Servidor::withoutGlobalScopes()->where('email', 'not like', '%@example.com')->count());

        // Dependentes do exemplo do modelo 04 e um dossiê de demonstração com documentos em todas as situações.
        $this->assertSame(67, Dependente::withoutGlobalScopes()->count());
        $ana = User::where('email', 'servidor.selc@nexrh.local')->first()->servidor;
        $this->assertEqualsCanonicalizing(['validado', 'pendente', 'recusado'], Documento::withoutGlobalScopes()->where('servidor_id', $ana->id)->distinct()->pluck('situacao')->all());
    }
}
