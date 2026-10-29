<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Declaracao;
use App\Models\Orgao;
use App\Models\RegistroAuditoria;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Vaga;
use App\Support\Cpf;
use Database\Factories\ServidorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServidoresTest extends TestCase
{
    use RefreshDatabase;

    private Orgao $orgao;

    private Unidade $dac;

    private Cargo $cargo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->semearPerfis();
        $this->orgao = Orgao::factory()->create(['sigla' => 'SELC', 'municipio' => 'Boa Vista', 'uf' => 'RR']);
        $this->dac = Unidade::factory()->for($this->orgao)->create(['sigla' => 'DAC']);
        $this->cargo = Cargo::factory()->for($this->orgao)->comVagas()->create(['denominacao' => 'Agente de Contratação', 'simbolo' => 'CNETS-I', 'quantidade_vagas' => 3]);
    }

    private function vaga(int $numero = 1): Vaga
    {
        return $this->cargo->vagas()->where('numero', $numero)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function formulario(array $extra = []): array
    {
        $dados = ServidorFactory::ficha(['vaga_id' => $this->vaga()->id, 'unidade_id' => $this->dac->id, ...$extra]);

        foreach ($dados as $campo => $valor) {
            if (is_bool($valor)) {
                $dados[$campo] = $valor ? '1' : '0';
            }
        }

        return $dados;
    }

    private function cadastrar(array $extra = [], ?User $quem = null): Servidor
    {
        $this->actingAs($quem ?? $this->usuario('rh', $this->orgao))
            ->post('/servidores', $this->formulario($extra))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return Servidor::latest('id')->firstOrFail();
    }

    public function test_cadastro_ocupa_a_vaga_e_protege_os_documentos(): void
    {
        $cpf = ServidorFactory::cpf();
        $servidor = $this->cadastrar(['nome' => 'Joana Fictícia Lima', 'cpf' => Cpf::formatar($cpf)]);

        $this->assertSame($cpf, $servidor->cpf);
        $this->assertSame(Cpf::hash($cpf), DB::table('servidores')->value('cpf_hash'));
        $this->assertStringNotContainsString($cpf, (string) DB::table('servidores')->value('cpf'));
        $this->assertStringNotContainsString('0000-0', (string) DB::table('servidores')->value('agencia'));

        $vaga = $this->vaga()->refresh();
        $this->assertSame('ocupada', $vaga->situacao);
        $this->assertSame('Joana Fictícia Lima', $vaga->ocupante_nome);
        $this->assertSame($cpf, $vaga->ocupante_cpf);
        $this->assertSame('1.388-P', $vaga->ato_numero);

        $this->assertSame(['nomeacao', 'ingresso'], $servidor->eventos()->pluck('tipo')->sort()->values()->reverse()->values()->all());

        $registro = RegistroAuditoria::where('evento', 'criado')->where('auditable_type', $servidor->getMorphClass())->firstOrFail();
        $this->assertSame('[protegido]', $registro->valores_novos['cpf']);
        $this->assertSame('[protegido]', $registro->valores_novos['raca_cor']);
        $this->assertArrayNotHasKey('cpf_hash', $registro->valores_novos);
        $this->assertStringNotContainsString($cpf, json_encode(RegistroAuditoria::all()->toArray()));
    }

    public function test_regras_da_ficha(): void
    {
        $rh = $this->usuario('rh', $this->orgao);
        $existente = $this->cadastrar([], $rh);

        $this->actingAs($rh)->post('/servidores', $this->formulario(['cpf' => '111.111.111-11', 'vaga_id' => $this->vaga(2)->id]))->assertSessionHasErrors('cpf');
        $this->actingAs($rh)->post('/servidores', $this->formulario(['cpf' => $existente->cpf, 'vaga_id' => $this->vaga(2)->id]))
            ->assertSessionHasErrors(['cpf' => 'Já existe um servidor com este CPF no órgão.']);
        $this->actingAs($rh)->post('/servidores', $this->formulario(['vaga_id' => $this->vaga(1)->id]))->assertSessionHasErrors('vaga_id');
        $this->actingAs($rh)->post('/servidores', $this->formulario(['nome' => '', 'vaga_id' => $this->vaga(2)->id]))->assertSessionHasErrors('nome');
        $this->actingAs($rh)->post('/servidores', $this->formulario(['vaga_id' => '']))->assertSessionHasErrors('vaga_id');
        // O que foi preenchido continua sendo conferido, mesmo salvando pela metade.
        $this->actingAs($rh)->post('/servidores', $this->formulario(['email' => 'sem-arroba', 'cep' => '', 'vaga_id' => $this->vaga(2)->id]))->assertSessionHasErrors('email');

        $this->assertSame(1, Servidor::count());
    }

    public function test_ficha_pode_ser_salva_pela_metade(): void
    {
        $rh = $this->usuario('rh', $this->orgao);
        $servidor = $this->cadastrar([], $rh);
        $this->assertTrue($servidor->fichaCompleta());

        // Troca a lotação e deixa endereço, banco e documentos em branco: salva assim mesmo.
        $dti = Unidade::factory()->for($this->orgao)->create(['sigla' => 'DTI']);
        $this->actingAs($rh)->put("/servidores/{$servidor->id}", $this->formulario([
            'cpf' => $servidor->cpf, 'nome' => $servidor->nome, 'unidade_id' => $dti->id,
            'cep' => '', 'logradouro' => '', 'agencia' => '', 'pis_pasep' => '', 'nacionalidade' => '', 'pcd' => '1', 'tipo_deficiencia' => '',
        ]))->assertSessionHasNoErrors()->assertRedirect("/servidores/{$servidor->id}")
            ->assertSessionHas('sucesso', fn ($m) => str_contains($m, 'incompleta') && str_contains($m, 'Endereço e banco (3)'));

        $servidor->refresh();
        $this->assertSame($dti->id, $servidor->unidade_id);
        $this->assertNull($servidor->cep);
        $this->assertSame('Brasileira', $servidor->nacionalidade);
        $this->assertFalse($servidor->fichaCompleta());
        $this->assertSame(['Dados pessoais' => 1, 'Documentos' => 1, 'Endereço e banco' => 3], $servidor->etapasIncompletas());
        $this->assertContains('Ficha incompleta', array_column($servidor->pendencias(), 'titulo'));

        // Depois o RH completa e a pendência some.
        $this->actingAs($rh)->put("/servidores/{$servidor->id}", $this->formulario(['cpf' => $servidor->cpf, 'nome' => $servidor->nome, 'unidade_id' => $dti->id]))
            ->assertSessionHasNoErrors()->assertSessionHas('sucesso', 'Cadastro atualizado.');
        $this->assertTrue($servidor->refresh()->fichaCompleta());
    }

    public function test_ficha_sem_vaga_salva_a_lotacao(): void
    {
        $rh = $this->usuario('rh', $this->orgao);
        $servidor = Servidor::factory()->for($this->orgao)->create(['vaga_id' => null, 'cep' => null]);

        $this->actingAs($rh)->get("/servidores/{$servidor->id}/editar")->assertOk();
        $this->actingAs($rh)->put("/servidores/{$servidor->id}", ['nome' => $servidor->nome, 'cpf' => $servidor->cpf, 'unidade_id' => $this->dac->id])
            ->assertSessionHasNoErrors();

        $servidor->refresh();
        $this->assertSame($this->dac->id, $servidor->unidade_id);
        $this->assertNull($servidor->vaga_id);
        $this->assertSame('1990-03-15', $servidor->data_nascimento->format('Y-m-d'));
    }

    public function test_troca_de_vaga_lotacao_e_desligamento(): void
    {
        $rh = $this->usuario('rh', $this->orgao);
        $servidor = $this->cadastrar([], $rh);
        $dti = Unidade::factory()->for($this->orgao)->create(['sigla' => 'DTI']);

        $this->actingAs($rh)->put("/servidores/{$servidor->id}", $this->formulario(['cpf' => $servidor->cpf, 'nome' => $servidor->nome, 'vaga_id' => $this->vaga(2)->id, 'unidade_id' => $dti->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame('vaga', $this->vaga(1)->situacao);
        $this->assertNull($this->vaga(1)->ocupante_cpf);
        $this->assertSame('ocupada', $this->vaga(2)->situacao);
        $this->assertTrue($servidor->eventos()->where('descricao', 'Lotação alterada de DAC para DTI')->exists());

        $this->actingAs($rh)->post("/servidores/{$servidor->id}/desligar", ['data_desligamento' => '2026-09-30', 'motivo_desligamento' => 'Exoneração a pedido', 'ato_desligamento' => '1.500-P'])
            ->assertSessionHasNoErrors();

        $servidor->refresh();
        $this->assertSame('desligado', $servidor->situacao);
        $this->assertNull($servidor->vaga_id);
        $this->assertSame('vaga', $this->vaga(2)->situacao);
        $this->assertSame([], $servidor->pendencias());

        // Readmissão: escolher de novo uma vaga.
        $this->actingAs($rh)->put("/servidores/{$servidor->id}", $this->formulario(['cpf' => $servidor->cpf, 'nome' => $servidor->nome, 'vaga_id' => $this->vaga(3)->id, 'decreto_nomeacao' => '1.600-P']))
            ->assertSessionHasNoErrors();
        $this->assertSame('ativo', $servidor->refresh()->situacao);
        $this->assertSame('ocupada', $this->vaga(3)->situacao);
    }

    public function test_vaga_de_servidor_cadastrado_so_muda_pela_ficha(): void
    {
        $rh = $this->usuario('rh', $this->orgao);
        $servidor = $this->cadastrar([], $rh);

        $this->actingAs($rh)->put('/vagas/'.$this->vaga()->id, ['situacao' => 'vaga', 'observacao' => 'Nota'])->assertRedirect();

        $this->assertSame('ocupada', $this->vaga()->situacao);
        $this->assertSame('Nota', $this->vaga()->observacao);
        $this->assertSame($servidor->nome, $this->vaga()->ocupante_nome);
    }

    public function test_quem_ve_o_que(): void
    {
        $servidor = $this->cadastrar();
        $outraUnidade = Unidade::factory()->for($this->orgao)->create(['sigla' => 'GAB']);
        $daOutraEquipe = Servidor::factory()->for($this->orgao)->create(['unidade_id' => $outraUnidade->id]);

        // Chefia vê só a própria equipe.
        $chefe = $this->usuario('chefia', $this->orgao);
        $this->dac->update(['chefe_user_id' => $chefe->id]);
        $this->actingAs($chefe)->get('/servidores')->assertOk()->assertSee($servidor->nome)->assertDontSee($daOutraEquipe->nome);
        $this->actingAs($chefe)->get("/servidores/{$daOutraEquipe->id}")->assertNotFound();
        $this->actingAs($chefe)->get("/servidores/{$servidor->id}")->assertOk()->assertSee($servidor->cpfMascarado())->assertDontSee($servidor->cpfFormatado())->assertDontSee($servidor->pis_pasep);
        $this->actingAs($chefe)->get("/servidores/{$servidor->id}/ficha.pdf")->assertForbidden();
        $this->actingAs($chefe)->get('/servidores/novo')->assertForbidden();

        // Auditor vê todos, mascarado; RH vê completo.
        $this->actingAs($this->usuario('auditor', $this->orgao))->get("/servidores/{$servidor->id}")->assertOk()->assertDontSee($servidor->cpfFormatado());
        $this->actingAs($this->usuario('rh', $this->orgao))->get("/servidores/{$servidor->id}")->assertOk()->assertSee($servidor->cpfFormatado());

        // Perfil servidor não lista ninguém; outro órgão não enxerga.
        $this->actingAs($this->usuario('servidor', $this->orgao))->get('/servidores')->assertForbidden();
        $this->actingAs($this->usuario('rh', Orgao::factory()->create()))->get("/servidores/{$servidor->id}")->assertNotFound();

        $this->assertTrue(RegistroAuditoria::withoutGlobalScopes()->where('evento', 'acesso')->where('auditable_id', $servidor->id)->exists());
    }

    public function test_ficha_em_pdf_e_minha_ficha(): void
    {
        $cpf = ServidorFactory::cpf();
        $usuario = $this->usuario('servidor', $this->orgao, ['cpf' => $cpf]);
        $servidor = $this->cadastrar(['cpf' => $cpf, 'nome' => 'Servidora Com Acesso']);

        $this->assertSame($usuario->id, $servidor->user_id);

        $resposta = $this->actingAs($this->usuario('rh', $this->orgao))->get("/servidores/{$servidor->id}/ficha.pdf");
        $resposta->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
        $this->assertTrue(RegistroAuditoria::where('evento', 'download')->where('auditable_id', $servidor->id)->exists());

        $this->actingAs($usuario)->get('/minha-ficha')->assertOk()->assertSee('Servidora Com Acesso')->assertSee(Cpf::formatar($cpf));
        $this->actingAs($usuario)->get('/minha-ficha/ficha.pdf')->assertOk();
        $this->actingAs($this->usuario('servidor', $this->orgao))->get('/minha-ficha')->assertOk()->assertSee('ainda não está ligado');
    }

    public function test_usuario_criado_depois_e_ligado_pelo_cpf(): void
    {
        $servidor = $this->cadastrar();
        $usuario = $this->usuario('servidor', $this->orgao, ['cpf' => $servidor->cpf]);

        $this->assertSame($usuario->id, $servidor->refresh()->user_id);
    }

    public function test_declaracoes_ficam_criptografadas_e_resolvem_pendencias(): void
    {
        $rh = $this->usuario('rh', $this->orgao);
        $servidor = $this->cadastrar([], $rh);
        $ano = now()->year;

        $this->assertCount(3, $servidor->pendencias());

        $this->actingAs($rh)->post("/servidores/{$servidor->id}/declaracoes/bens", [
            'data_declaracao' => now()->format('Y-m-d'), 'ano_referencia' => $ano, 'possui_bens' => '1',
            'bens' => [['titular' => 'servidor', 'discriminacao' => 'Veículo fictício', 'valor' => '45.000,00'], ['titular' => 'servidor', 'discriminacao' => '', 'valor' => '']],
        ])->assertSessionHasNoErrors();
        $this->actingAs($rh)->post("/servidores/{$servidor->id}/declaracoes/bens", ['data_declaracao' => now()->format('Y-m-d'), 'ano_referencia' => $ano, 'possui_bens' => '1', 'bens' => []])
            ->assertSessionHasErrors('bens');
        $this->actingAs($rh)->post("/servidores/{$servidor->id}/declaracoes/parentesco", [
            'data_declaracao' => now()->format('Y-m-d'), 'vinculo_funcional' => 'Ocupante de função de confiança ou cargo em comissão',
            'cargo_comissao' => 'Agente de Contratação', 'possui_parente' => '0', 'possui_conjuge' => '0',
        ])->assertSessionHasNoErrors();
        $this->actingAs($rh)->post("/servidores/{$servidor->id}/declaracoes/acumulacao", ['data_declaracao' => now()->format('Y-m-d'), 'acumula' => '1', 'aposentado' => '0'])
            ->assertSessionHasErrors(['cargo_acumulado', 'instituicao']);

        $bens = Declaracao::where('tipo', 'bens')->firstOrFail();
        $this->assertEquals(45000, $bens->conteudo['bens'][0]['valor']);
        $this->assertCount(1, $bens->conteudo['bens']);
        $this->assertStringNotContainsString('Veículo', (string) DB::table('declaracoes')->where('id', $bens->id)->value('conteudo'));
        $this->assertStringNotContainsString('Veículo', json_encode(RegistroAuditoria::all()->toArray()));

        $this->assertSame(['Declaração de acumulação pendente'], array_column($servidor->refresh()->pendencias(), 'titulo'));
    }

    public function test_busca_por_nome_sem_acento_e_por_cpf(): void
    {
        $servidor = $this->cadastrar(['nome' => 'João Fictício Araújo']);
        $rh = $this->usuario('rh', $this->orgao);

        $this->actingAs($rh)->get('/servidores?busca=joao')->assertSee('João Fictício Araújo');
        $this->actingAs($rh)->get('/servidores?busca='.Cpf::formatar($servidor->cpf))->assertSee('João Fictício Araújo');
        $this->actingAs($rh)->get('/servidores?busca=Inexistente')->assertDontSee('João Fictício Araújo')->assertSee('Nenhum servidor encontrado');
    }
}
