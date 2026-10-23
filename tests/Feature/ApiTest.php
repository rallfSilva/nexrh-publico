<?php

namespace Tests\Feature;

use App\Models\Afastamento;
use App\Models\ChaveApi;
use App\Models\Orgao;
use App\Models\RegistroAuditoria;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * API de consulta: só com chave válida, só o escopo liberado, só o órgão da chave
 * e nunca dados pessoais.
 */
class ApiTest extends TestCase
{
    use RefreshDatabase;

    private Orgao $orgao;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-22 10:00:00');
        $this->semearPerfis();
        $this->orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        $this->admin = $this->usuario('administrador-orgao', $this->orgao);
        $dti = Unidade::factory()->for($this->orgao)->create(['sigla' => 'DTI']);
        Servidor::factory()->for($this->orgao)->create(['nome' => 'Ana Fictícia Api', 'cpf' => '52998224725', 'unidade_id' => $dti->id, 'agencia' => '1234', 'conta_corrente' => '99999-9']);

        $outro = Orgao::factory()->create(['sigla' => 'OUTRO']);
        Servidor::factory()->for($outro)->create(['nome' => 'Pessoa de Outro Órgão']);
    }

    /** Cria a chave pela tela, como o administrador faria, e devolve o texto mostrado uma vez. */
    private function criarChave(array $escopos, ?string $expira = null): string
    {
        $this->actingAs($this->admin)
            ->post('/chaves-api', ['nome' => 'Sistema de ponto', 'escopos' => $escopos, 'expira_em' => $expira])
            ->assertRedirect('/chaves-api')
            ->assertSessionHas('chave_criada');
        $texto = session('chave_criada')['texto'];
        auth()->forgetGuards();

        return $texto;
    }

    private function api(string $chave, string $url)
    {
        return $this->withHeader('Authorization', 'Bearer '.$chave)->getJson('/api/v1'.$url);
    }

    public function test_tela_mostra_a_chave_uma_vez_e_guarda_so_o_hash(): void
    {
        $texto = $this->criarChave(['servidores']);

        $this->assertStringStartsWith('nxr_', $texto);
        $chave = ChaveApi::firstOrFail();
        $this->assertSame(hash('sha256', $texto), $chave->hash);
        $this->assertSame(['servidores'], $chave->escopos);

        // Nem a tabela nem a trilha de auditoria guardam a chave.
        $this->assertStringNotContainsString($texto, json_encode(ChaveApi::all()->makeVisible('hash')->toArray()).json_encode(RegistroAuditoria::all()->toArray()));
        $this->assertStringNotContainsString(substr($texto, 4), RegistroAuditoria::pluck('valores_novos')->toJson());

        // Aparece na tela seguinte à criação e some depois.
        $this->actingAs($this->admin)->get('/chaves-api')->assertOk()->assertSee($texto);
        $this->get('/chaves-api')->assertOk()->assertSee('Sistema de ponto')->assertDontSee($texto);
    }

    public function test_sem_chave_ou_com_chave_invalida_responde_401(): void
    {
        $this->getJson('/api/v1/unidades')->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Bearer');
        $this->api('nxr_inventada', '/unidades')->assertUnauthorized();
        $this->api('qualquer-coisa', '/unidades')->assertUnauthorized();
    }

    public function test_escopo_nao_liberado_responde_403(): void
    {
        $chave = $this->criarChave(['unidades']);

        $this->api($chave, '/unidades')->assertOk()->assertJsonPath('dados.0.sigla', 'DTI');
        $this->api($chave, '/servidores')->assertForbidden();
        $this->api($chave, '/ferias')->assertForbidden();
        $this->api($chave, '')->assertOk()->assertJsonPath('orgao.sigla', 'SELC')->assertJsonMissingPath('consultas.servidores');
    }

    public function test_servidores_sem_dados_pessoais_e_so_do_orgao_da_chave(): void
    {
        $chave = $this->criarChave(['servidores']);

        $resposta = $this->api($chave, '/servidores')->assertOk()
            ->assertJsonCount(1, 'dados')
            ->assertJsonPath('dados.0.nome', 'Ana Fictícia Api')
            ->assertJsonPath('dados.0.cpf_mascarado', '***.982.247-**')
            ->assertJsonPath('paginacao.total', 1);
        $json = $resposta->getContent();
        foreach (['52998224725', '529.982.247-25', '99999-9', 'Mãe Fictícia', 'Pessoa de Outro Órgão'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $json);
        }

        auth()->forgetGuards();
        $outro = Servidor::where('nome', 'Pessoa de Outro Órgão')->value('id');
        $this->assertNotNull($outro);
        $this->api($chave, '/servidores/'.$outro)->assertNotFound();
        $this->api($chave, '/servidores?por_pagina=500')->assertUnprocessable();
    }

    public function test_chave_revogada_ou_expirada_deixa_de_funcionar(): void
    {
        $chave = $this->criarChave(['unidades'], '2026-12-31');
        $this->api($chave, '/unidades')->assertOk();
        $this->assertSame(1, ChaveApi::first()->usos);

        Carbon::setTestNow('2027-01-01 08:00:00');
        $this->api($chave, '/unidades')->assertUnauthorized();

        Carbon::setTestNow('2026-10-22 10:00:00');
        $registro = ChaveApi::firstOrFail();
        $this->actingAs($this->admin)->post("/chaves-api/{$registro->id}/revogar")->assertRedirect();
        auth()->forgetGuards();
        $this->api($chave, '/unidades')->assertUnauthorized();
        $this->assertNotNull($registro->fresh()->revogada_em);
    }

    public function test_so_a_administracao_do_orgao_gerencia_chaves(): void
    {
        $this->actingAs($this->usuario('rh', $this->orgao))->get('/chaves-api')->assertForbidden();

        $chave = $this->criarChave(['unidades']);
        $id = ChaveApi::firstOrFail()->id;
        $outroAdmin = $this->usuario('administrador-orgao', Orgao::where('sigla', 'OUTRO')->first());
        $this->actingAs($outroAdmin)->post("/chaves-api/{$id}/revogar")->assertNotFound();
        auth()->forgetGuards();
        $this->assertNull(ChaveApi::findOrFail($id)->revogada_em);
    }

    public function test_ferias_e_ausencias_sem_o_tipo_da_licenca(): void
    {
        $chave = $this->criarChave(['ferias']);
        $ana = Servidor::where('nome', 'Ana Fictícia Api')->firstOrFail();
        Afastamento::create(['orgao_id' => $this->orgao->id, 'servidor_id' => $ana->id, 'tipo' => 'Licença à gestante', 'inicio' => '2026-10-01', 'fim' => '2027-03-29']);

        $json = $this->api($chave, '/ausencias?de=2026-10-22&ate=2026-10-22')->assertOk()
            ->assertJsonPath('dados.0.motivo', 'licenca_ou_afastamento')
            ->getContent();
        $this->assertStringNotContainsString('gestante', $json);

        $this->api($chave, '/ferias?de=2026-01-01&ate=2027-12-31')->assertStatus(422);
    }
}
