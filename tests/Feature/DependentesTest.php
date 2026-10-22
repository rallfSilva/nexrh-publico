<?php

namespace Tests\Feature;

use App\Models\Dependente;
use App\Models\Orgao;
use App\Models\RegistroAuditoria;
use App\Models\Servidor;
use App\Models\User;
use App\Support\Cpf;
use Database\Factories\ServidorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DependentesTest extends TestCase
{
    use RefreshDatabase;

    private Orgao $orgao;

    private Servidor $servidor;

    private User $rh;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-22 10:00:00');
        $this->semearPerfis();
        $this->orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        $this->servidor = Servidor::factory()->for($this->orgao)->create(['nome' => 'Joana Fictícia Lima']);
        $this->rh = $this->usuario('rh', $this->orgao);
    }

    /** @return array<string, mixed> */
    private function formulario(array $extra = []): array
    {
        return [
            'nome' => 'Pedro Fictício Lima',
            'cpf' => Cpf::formatar(ServidorFactory::cpf()),
            'data_nascimento' => '2018-03-10',
            'sexo' => 'M',
            'parentesco' => '03',
            'declarar_ir' => '1',
            'declarar_previdencia' => '1',
            'salario_familia' => '1',
            'pcd' => '0',
            ...$extra,
        ];
    }

    private function cadastrar(array $extra = []): Dependente
    {
        $this->actingAs($this->rh)
            ->post("/servidores/{$this->servidor->id}/dependentes", $this->formulario($extra))
            ->assertSessionHasNoErrors()
            ->assertRedirect("/servidores/{$this->servidor->id}?aba=dependentes");

        return Dependente::latest('id')->firstOrFail();
    }

    public function test_cadastro_protege_o_cpf_e_audita_sem_o_valor(): void
    {
        $dep = $this->cadastrar(['cpf' => Cpf::formatar($cpf = ServidorFactory::cpf())]);

        $this->assertSame($cpf, $dep->cpf);
        $this->assertSame('03', $dep->parentesco);
        $this->assertFalse($dep->universitario);
        $this->assertSame(99, $dep->tipo_deficiencia);
        $this->assertStringNotContainsString($cpf, (string) DB::table('dependentes')->value('cpf'));
        $this->assertSame(['Imposto de renda', 'Previdência', 'Salário-família'], $dep->finalidades());

        $auditoria = RegistroAuditoria::withoutGlobalScopes()->where('auditable_type', $dep->getMorphClass())->firstOrFail();
        $this->assertSame('[protegido]', $auditoria->valores_novos['cpf']);
        $this->assertArrayNotHasKey('cpf_hash', $auditoria->valores_novos);

        $this->get("/servidores/{$this->servidor->id}?aba=dependentes")->assertSee('Pedro Fictício Lima')->assertSee(Cpf::formatar($cpf));
    }

    public function test_universitario_sai_do_codigo_de_parentesco(): void
    {
        $this->assertTrue($this->cadastrar(['parentesco' => '04', 'data_nascimento' => '2005-01-01', 'salario_familia' => '0'])->universitario);
        $this->assertSame('04', $this->cadastrar(['parentesco' => '03', 'universitario' => '1', 'data_nascimento' => '2006-01-01', 'salario_familia' => '0'])->parentesco);
    }

    public function test_regras(): void
    {
        $this->actingAs($this->rh);
        $url = "/servidores/{$this->servidor->id}/dependentes";

        $this->post($url, $this->formulario(['cpf' => '123.456.789-00']))->assertSessionHasErrors(['cpf' => 'CPF inválido. Confira os números.']);
        $this->post($url, $this->formulario(['cpf' => $this->servidor->cpf]))->assertSessionHasErrors(['cpf' => 'Este é o CPF do próprio servidor.']);
        $this->post($url, $this->formulario(['data_nascimento' => now()->addDay()->format('Y-m-d')]))->assertSessionHasErrors('data_nascimento');
        $this->post($url, $this->formulario(['pcd' => '1', 'tipo_deficiencia' => '99']))->assertSessionHasErrors(['tipo_deficiencia' => 'Escolha o tipo de deficiência.']);
        $this->post($url, $this->formulario(['parentesco' => '05']))->assertSessionHasErrors('parentesco');

        $repetido = $this->cadastrar();
        $this->post($url, $this->formulario(['cpf' => $repetido->cpf]))->assertSessionHasErrors(['cpf' => 'Este dependente já está cadastrado para o servidor.']);

        // Um cônjuge ativo por vez. Na tela, salário-família para cônjuge vira "não" (a pergunta some).
        $conjuge = $this->cadastrar(['nome' => 'Rui Fictício', 'parentesco' => '01', 'data_nascimento' => '1980-01-01', 'sexo' => 'M']);
        $this->assertFalse($conjuge->salario_familia);
        $this->post($url, $this->formulario(['parentesco' => '02', 'data_nascimento' => '1982-01-01']))
            ->assertSessionHasErrors(['parentesco' => 'Rui Fictício já está cadastrado(a) como cônjuge ou companheiro(a). Encerre aquele cadastro antes.']);

        $this->assertSame(2, Dependente::count());
    }

    public function test_limites_de_idade_viram_pendencia(): void
    {
        $this->cadastrar(['nome' => 'Filho Com 22', 'data_nascimento' => '2004-05-01', 'salario_familia' => '0', 'declarar_previdencia' => '0']);
        $this->cadastrar(['nome' => 'Universitário Com 22', 'parentesco' => '04', 'data_nascimento' => '2004-05-01', 'salario_familia' => '0', 'declarar_previdencia' => '0']);
        $this->cadastrar(['nome' => 'Filho Com 15', 'data_nascimento' => '2011-01-01']);
        $this->cadastrar(['nome' => 'Filho PcD Com 30', 'data_nascimento' => '1996-01-01', 'pcd' => '1', 'tipo_deficiencia' => '3', 'salario_familia' => '0']);

        $alertas = Dependente::all()->mapWithKeys(fn ($d) => [$d->nome => $d->alertas()]);
        $this->assertSame(['Tem 22 anos: passou do limite de 21 anos para o imposto de renda (24 se for universitário).'], $alertas['Filho Com 22']);
        $this->assertSame([], $alertas['Universitário Com 22']);
        $this->assertSame(['Tem 15 anos: o salário-família vai só até os 14 anos.'], $alertas['Filho Com 15']);
        $this->assertSame([], $alertas['Filho PcD Com 30']);

        $this->get("/servidores/{$this->servidor->id}")->assertSee('Revisar dependente Filho Com 22')->assertSee('Revisar dependente Filho Com 15');
    }

    public function test_encerrar_e_reativar(): void
    {
        $dep = $this->cadastrar();
        $base = "/servidores/{$this->servidor->id}/dependentes/{$dep->id}";

        $this->post("{$base}/encerrar", ['encerrado_em' => '2026-10-01'])->assertSessionHasErrors('motivo_encerramento');
        $this->post("{$base}/encerrar", ['encerrado_em' => '2017-01-01', 'motivo_encerramento' => 'Outro'])->assertSessionHasErrors('encerrado_em');
        $this->post("{$base}/encerrar", ['encerrado_em' => '2026-10-01', 'motivo_encerramento' => 'Fim da guarda, tutela ou curatela'])->assertRedirect();
        $this->assertFalse($dep->refresh()->ativo());
        $this->get("/servidores/{$this->servidor->id}?aba=dependentes")->assertSee('Não são mais dependentes (1)')->assertSee('Fim da guarda, tutela ou curatela');

        // CPF de encerrado não pode ser cadastrado de novo: reativa.
        $this->post("/servidores/{$this->servidor->id}/dependentes", $this->formulario(['cpf' => $dep->cpf]))
            ->assertSessionHasErrors(['cpf' => 'Este dependente já foi cadastrado e está encerrado. Abra o cadastro dele e reative.']);

        $this->post("{$base}/reativar")->assertRedirect();
        $this->assertTrue($dep->refresh()->ativo());
        $this->assertNull($dep->motivo_encerramento);
        $this->delete($base)->assertStatus(405);
    }

    public function test_reativar_conjuge_com_outro_ativo_e_recusado(): void
    {
        $antigo = $this->cadastrar(['nome' => 'Primeiro Cônjuge', 'parentesco' => '01', 'data_nascimento' => '1980-01-01']);
        $this->post("/servidores/{$this->servidor->id}/dependentes/{$antigo->id}/encerrar", ['encerrado_em' => '2025-01-01', 'motivo_encerramento' => 'Separação ou divórcio']);
        $this->cadastrar(['nome' => 'Segundo Cônjuge', 'parentesco' => '02', 'data_nascimento' => '1981-01-01']);

        $this->post("/servidores/{$this->servidor->id}/dependentes/{$antigo->id}/reativar")->assertSessionHas('erro');
        $this->assertFalse($antigo->refresh()->ativo());
    }

    public function test_quem_ve_e_quem_altera(): void
    {
        $dep = $this->cadastrar();
        $this->flushSession(); // tira o aviso de sucesso do cadastro, que traz o nome

        $auditor = $this->usuario('auditor', $this->orgao);
        $this->actingAs($auditor)->get("/servidores/{$this->servidor->id}")->assertOk()->assertDontSee('Pedro Fictício Lima');
        $this->get("/servidores/{$this->servidor->id}/dependentes/novo")->assertForbidden();
        $this->get("/servidores/{$this->servidor->id}/dependentes/{$dep->id}/editar")->assertForbidden();

        // O próprio servidor vê os dependentes dele, sem botões de alteração.
        $user = $this->usuario('servidor', $this->orgao, ['cpf' => $this->servidor->cpf]);
        $this->servidor->update(['user_id' => $user->id]);
        $this->actingAs($user)->get('/minha-ficha?aba=dependentes')->assertOk()->assertSee('Pedro Fictício Lima')->assertDontSee('Novo dependente');
        $this->post("/servidores/{$this->servidor->id}/dependentes", $this->formulario())->assertNotFound(); // pelas rotas do RH a ficha não existe para ele; só em Minha ficha

        // Dependente de outro servidor na URL: 404.
        $outro = Servidor::factory()->for($this->orgao)->create();
        $this->actingAs($this->rh)->get("/servidores/{$outro->id}/dependentes/{$dep->id}/editar")->assertNotFound();
    }

    public function test_editar_e_ficha_em_pdf(): void
    {
        $dep = $this->cadastrar();

        $this->put("/servidores/{$this->servidor->id}/dependentes/{$dep->id}", $this->formulario(['cpf' => $dep->cpf, 'nome' => 'Pedro Fictício Lima Filho']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Pedro Fictício Lima Filho', $dep->refresh()->nome);

        $this->get("/servidores/{$this->servidor->id}/ficha.pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
