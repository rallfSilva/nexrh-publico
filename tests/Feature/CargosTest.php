<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Orgao;
use App\Models\RegistroAuditoria;
use App\Models\Vaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CargosTest extends TestCase
{
    use RefreshDatabase;

    private Orgao $orgao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->semearPerfis();
        $this->orgao = Orgao::factory()->create();
    }

    public function test_cadastro_de_cargo_cria_as_vagas(): void
    {
        $this->actingAs($this->usuario('rh', $this->orgao))
            ->post('/cargos', ['denominacao' => 'Assessor Especial', 'simbolo' => 'cnes-iv', 'natureza' => 'comissao', 'remuneracao' => '4.472,58', 'quantidade_vagas' => 4, 'ativo' => '1'])
            ->assertRedirect();

        $cargo = Cargo::firstOrFail();
        $this->assertSame('CNES-IV', $cargo->simbolo);
        $this->assertSame('4472.58', $cargo->remuneracao);
        $this->assertSame([1, 2, 3, 4], $cargo->vagas->pluck('numero')->all());
        $this->assertSame($this->orgao->id, $cargo->orgao_id);
    }

    public function test_aumentar_e_reduzir_vagas(): void
    {
        $rh = $this->usuario('rh', $this->orgao);
        $cargo = Cargo::factory()->for($this->orgao)->comVagas()->create(['quantidade_vagas' => 3]);
        $dados = fn (int $n) => ['denominacao' => $cargo->denominacao, 'simbolo' => $cargo->simbolo, 'natureza' => 'comissao', 'quantidade_vagas' => $n, 'ativo' => '1'];

        $this->actingAs($rh)->put("/cargos/{$cargo->id}", $dados(5))->assertSessionHasNoErrors()->assertRedirect("/cargos/{$cargo->id}");
        $this->assertSame(5, $cargo->vagas()->count());

        $cargo->vagas()->where('numero', 5)->update(['situacao' => 'ocupada', 'ocupante_nome' => 'Pessoa Fictícia']);
        $this->actingAs($rh)->put("/cargos/{$cargo->id}", $dados(3))->assertSessionHasErrors('quantidade_vagas');
        $this->assertSame(5, $cargo->vagas()->count());
    }

    public function test_editar_vaga_valida_cpf_e_limpa_ocupante_ao_liberar(): void
    {
        $rh = $this->usuario('rh', $this->orgao);
        $cargo = Cargo::factory()->for($this->orgao)->comVagas()->create();
        $vaga = $cargo->vagas()->first();

        $this->actingAs($rh)->put("/vagas/{$vaga->id}", ['situacao' => 'ocupada', 'ocupante_nome' => 'Pessoa Fictícia', 'ocupante_cpf' => '111.111.111-11'])
            ->assertSessionHasErrors('ocupante_cpf');

        $this->actingAs($rh)->put("/vagas/{$vaga->id}", ['situacao' => 'ocupada', 'ocupante_nome' => 'Pessoa Fictícia', 'ocupante_cpf' => '529.982.247-25', 'ato_numero' => '1-P'])
            ->assertSessionHasNoErrors();
        $this->assertSame('52998224725', $vaga->fresh()->ocupante_cpf);

        $this->actingAs($rh)->put("/vagas/{$vaga->id}", ['situacao' => 'vaga']);
        $this->assertNull($vaga->fresh()->ocupante_nome);
        $this->assertNull($vaga->fresh()->ocupante_cpf);
    }

    public function test_cpf_aparece_mascarado_e_fora_da_auditoria(): void
    {
        $rh = $this->usuario('rh', $this->orgao);
        $vaga = Cargo::factory()->for($this->orgao)->comVagas()->create()->vagas()->first();

        $this->actingAs($rh)->put("/vagas/{$vaga->id}", ['situacao' => 'ocupada', 'ocupante_nome' => 'Pessoa Fictícia', 'ocupante_cpf' => '52998224725']);

        $this->actingAs($rh)->get("/cargos/{$vaga->cargo_id}")->assertSee('***.982.247-**')->assertDontSee('529.982.247-25')->assertDontSee('52998224725');
        $this->assertStringNotContainsString('52998224725', RegistroAuditoria::withoutGlobalScopes()->get()->toJson());
    }

    public function test_quadro_mostra_totais(): void
    {
        $cargo = Cargo::factory()->for($this->orgao)->comVagas()->create(['quantidade_vagas' => 4, 'denominacao' => 'Assessor Fictício']);
        $cargo->vagas()->where('numero', 1)->update(['situacao' => 'ocupada']);
        $cargo->vagas()->where('numero', 4)->update(['situacao' => 'remanejada', 'orgao_remanejamento' => 'Outro']);

        $this->assertSame(['total' => 4, 'remanejadas' => 1, 'disponiveis' => 3, 'ocupadas' => 1, 'vagas' => 2], $cargo->fresh()->totais());
        $this->actingAs($this->usuario('auditor', $this->orgao))->get('/cargos')->assertOk()->assertSee('Assessor Fictício');
    }

    public function test_permissoes_e_isolamento(): void
    {
        $alheio = Cargo::factory()->comVagas()->create();

        $this->actingAs($this->usuario('servidor', $this->orgao))->get('/cargos')->assertForbidden();
        $this->actingAs($this->usuario('chefia', $this->orgao))->get('/cargos')->assertForbidden();
        $this->actingAs($this->usuario('auditor', $this->orgao))->get('/cargos/create')->assertForbidden();

        $rh = $this->usuario('rh', $this->orgao);
        $this->actingAs($rh)->get("/cargos/{$alheio->id}")->assertNotFound();
        $this->actingAs($rh)->get('/vagas/'.Vaga::withoutGlobalScopes()->where('cargo_id', $alheio->id)->value('id').'/edit')->assertNotFound();
    }
}
