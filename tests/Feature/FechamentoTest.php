<?php

namespace Tests\Feature;

use App\Frequencia\Fechamento;
use App\Models\FechamentoFrequencia;
use App\Models\Feriado;
use App\Models\Importacao;
use App\Models\Justificativa;
use App\Models\Orgao;
use App\Models\RegistroAuditoria;
use App\Models\RegistroPonto;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\User;
use App\Relatorios\BoletimDeFrequencia;
use Database\Factories\ServidorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FechamentoTest extends TestCase
{
    use RefreshDatabase;

    private Orgao $orgao;

    private Unidade $dti;

    private Unidade $dad;

    private User $rh;

    private User $chefe;

    private User $chefeDad;

    private Servidor $joana;

    private User $joanaUser;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 10:00:00'); // quarta-feira; outubro já terminou
        $this->semearPerfis();
        $this->orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        $this->chefe = $this->usuario('chefia', $this->orgao);
        $this->chefeDad = $this->usuario('chefia', $this->orgao);
        $this->dti = Unidade::factory()->for($this->orgao)->create(['sigla' => 'DTI', 'chefe_user_id' => $this->chefe->id]);
        $this->dad = Unidade::factory()->for($this->orgao)->create(['sigla' => 'DAD', 'chefe_user_id' => $this->chefeDad->id]);
        $this->rh = $this->usuario('rh', $this->orgao);

        $this->joanaUser = $this->usuario('servidor', $this->orgao, ['cpf' => $cpf = ServidorFactory::cpf()]);
        $this->joana = Servidor::factory()->for($this->orgao)->create([
            'nome' => 'Joana Fictícia Lima', 'cpf' => $cpf, 'user_id' => $this->joanaUser->id,
            'unidade_id' => $this->dti->id, 'efeitos_a_partir_de' => '2024-03-01',
        ]);
        Servidor::factory()->for($this->orgao)->create(['nome' => 'Davi Fictício Souza', 'unidade_id' => $this->dad->id, 'efeitos_a_partir_de' => '2024-03-01']);

        foreach (['2026-10-19 07:30', '2026-10-19 13:31', '2026-10-21 07:29', '2026-10-21 13:32'] as $m) {
            RegistroPonto::create(['orgao_id' => $this->orgao->id, 'servidor_id' => $this->joana->id, 'momento' => $m, 'origem' => 'sistema']);
        }
    }

    public function test_prazo_e_o_quinto_dia_util_do_mes_seguinte(): void
    {
        $outubro = Carbon::parse('2026-10-01');
        $this->assertSame('2026-11-06', Fechamento::prazo($this->orgao->id, $outubro)->format('Y-m-d'));

        // Finados numa segunda: o prazo anda um dia útil. Ponto facultativo não conta como feriado.
        Feriado::create(['orgao_id' => $this->orgao->id, 'data' => '2026-11-02', 'nome' => 'Finados', 'tipo' => 'nacional']);
        Feriado::create(['orgao_id' => $this->orgao->id, 'data' => '2026-11-03', 'nome' => 'Facultativo fictício', 'tipo' => 'ponto_facultativo']);
        $this->assertSame('2026-11-09', Fechamento::prazo($this->orgao->id, $outubro)->format('Y-m-d'));
    }

    public function test_chefia_confere_so_o_proprio_setor_e_sem_justificativa_em_analise(): void
    {
        $this->actingAs($this->joanaUser)->post('/meu-ponto/justificativas', ['inicio' => '2026-10-20', 'tipo' => 'esquecimento', 'motivo' => 'Esqueci (teste).'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->chefe)->get('/frequencia/fechamento')->assertOk()
            ->assertSee('Outubro de 2026')->assertSee('DTI')->assertDontSee('DAD ·')->assertSee('1 em análise');

        $this->post("/frequencia/fechamento/{$this->dti->id}/conferir", ['mes' => '2026-10'])
            ->assertSessionHasErrors('fechamento');
        $this->post("/frequencia/fechamento/{$this->dad->id}/conferir", ['mes' => '2026-10'])->assertForbidden();
        $this->post("/frequencia/fechamento/{$this->dti->id}/fechar", ['mes' => '2026-10'])->assertForbidden();

        $j = Justificativa::firstOrFail();
        $this->post("/frequencia/justificativas/{$j->id}/abonar");

        $this->post("/frequencia/fechamento/{$this->dti->id}/conferir", ['mes' => '2026-10', 'observacao_chefia' => 'Tudo certo (teste).'])
            ->assertRedirect('/frequencia/fechamento?mes=2026-10');
        $f = FechamentoFrequencia::firstOrFail();
        $this->assertSame('conferido', $f->situacao());
        $this->assertSame($this->chefe->id, $f->conferido_por);

        // O mês atual ainda não fecha: o pedido vira o mês anterior.
        $this->post("/frequencia/fechamento/{$this->dti->id}/conferir", ['mes' => '2026-11'])->assertRedirect('/frequencia/fechamento?mes=2026-10');
        $this->assertSame(1, FechamentoFrequencia::count());
    }

    public function test_rh_fecha_e_o_mes_nao_muda_mais(): void
    {
        $this->actingAs($this->rh)->post("/frequencia/fechamento/{$this->dti->id}/fechar", ['mes' => '2026-10'])
            ->assertRedirect('/frequencia/fechamento?mes=2026-10');
        $f = FechamentoFrequencia::firstOrFail();
        $this->assertTrue($f->fechado());
        $this->assertCount(1, $f->boletim);
        $this->assertSame('Joana Fictícia Lima', $f->boletim[0]['servidor']);
        $this->assertSame(2, $f->boletim[0]['com_registro']);
        $this->assertSame(20, $f->boletim[0]['faltas']); // 22 dias úteis em outubro, 2 com registro

        // Marcação, anulação, jornada e abono pelo RH: recusados no mês fechado.
        $this->post("/servidores/{$this->joana->id}/frequencia/registros", ['data' => '2026-10-20', 'hora' => '07:30', 'motivo' => 'Teste.'])
            ->assertSessionHasErrors(['data' => 'O mês 10/2026 já foi fechado pelo RH. Para mudar, o RH precisa reabrir o mês do setor.']);
        $marca = RegistroPonto::firstOrFail();
        $this->post("/servidores/{$this->joana->id}/frequencia/registros/{$marca->id}/anular", ['motivo_anulacao' => 'Teste.'])->assertSessionHasErrors('motivo_anulacao');
        $this->assertTrue($marca->fresh()->valido());
        $this->post("/servidores/{$this->joana->id}/frequencia/jornada", ['modalidade' => 'presencial', 'turnos' => '08:00-14:00', 'dias_semana' => [1, 2, 3, 4, 5], 'inicio' => '2026-10-15'])
            ->assertSessionHasErrors('inicio');
        $this->post("/servidores/{$this->joana->id}/frequencia/abonos", ['inicio' => '2026-10-20', 'tipo' => 'outro', 'motivo' => 'Teste.'])->assertSessionHasErrors('inicio');
        $this->assertSame(0, Justificativa::count());

        // O espelho avisa e não oferece correção.
        $this->get("/servidores/{$this->joana->id}/frequencia?mes=2026-10")->assertOk()->assertSee('Mês fechado')->assertDontSee('clique para anular');

        // O servidor não justifica mais outubro, e as ocorrências de outubro saem de Meu ponto.
        $this->actingAs($this->joanaUser)->post('/meu-ponto/justificativas', ['inicio' => '2026-10-20', 'tipo' => 'esquecimento', 'motivo' => 'Teste.'])
            ->assertSessionHasErrors('inicio');
        $this->get('/meu-ponto?mes=2026-10')->assertOk()->assertSee('Mês fechado')->assertDontSee('/meu-ponto/justificar?data=2026-10-20', false);

        // Outro setor continua aberto.
        $this->assertFalse(Fechamento::estaFechado(Servidor::where('unidade_id', $this->dad->id)->first(), Carbon::parse('2026-10-20')));
        $this->assertTrue(RegistroAuditoria::where('auditable_type', FechamentoFrequencia::class)->exists());
    }

    public function test_rh_reabre_com_motivo_e_fecha_de_novo(): void
    {
        $this->actingAs($this->rh)->post("/frequencia/fechamento/{$this->dti->id}/fechar", ['mes' => '2026-10']);
        $this->post("/frequencia/fechamento/{$this->dti->id}/reabrir", ['mes' => '2026-10'])->assertSessionHasErrors('motivo_reabertura');
        $this->post("/frequencia/fechamento/{$this->dti->id}/reabrir", ['mes' => '2026-10', 'motivo_reabertura' => 'Atestado entregue com atraso (teste).'])
            ->assertRedirect('/frequencia/fechamento?mes=2026-10');

        $f = FechamentoFrequencia::firstOrFail();
        $this->assertFalse($f->fechado());
        $this->assertNull($f->boletim);
        $this->assertSame($this->rh->id, $f->reaberto_por);

        $this->post("/servidores/{$this->joana->id}/frequencia/abonos", ['inicio' => '2026-10-20', 'tipo' => 'atestado', 'motivo' => 'Atestado (teste).'])->assertSessionHasNoErrors();
        $this->post("/frequencia/fechamento/{$this->dti->id}/fechar", ['mes' => '2026-10']);
        $f->refresh();
        $this->assertTrue($f->fechado());
        $this->assertSame(1, $f->boletim[0]['abonados']);
        $this->assertSame(19, $f->boletim[0]['faltas']);

        $this->actingAs($this->chefe)->post("/frequencia/fechamento/{$this->dti->id}/reabrir", ['mes' => '2026-10', 'motivo_reabertura' => 'x'])->assertForbidden();
    }

    public function test_fechar_os_conferidos_de_uma_vez(): void
    {
        $this->actingAs($this->chefe)->post("/frequencia/fechamento/{$this->dti->id}/conferir", ['mes' => '2026-10']);
        $this->actingAs($this->rh)->get('/frequencia/fechamento?mes=2026-10')->assertOk()->assertSee('Fechar os 1 conferidos');

        $this->post('/frequencia/fechamento/conferidos', ['mes' => '2026-10'])->assertSessionHas('sucesso', '1 setor fechado: DTI.');
        $this->assertSame(1, FechamentoFrequencia::whereNotNull('fechado_em')->count());
    }

    public function test_boletim_usa_o_guardado_no_fechamento(): void
    {
        $this->actingAs($this->rh)->get('/relatorios/frequencia?mes=2026-10')->assertOk()
            ->assertSee('Boletim de frequência')->assertSee('Joana Fictícia Lima')->assertSee('Davi Fictício Souza')->assertSee('Aberto');

        $this->post("/frequencia/fechamento/{$this->dti->id}/fechar", ['mes' => '2026-10']);
        // Uma marcação que entrasse por fora não muda o boletim guardado.
        RegistroPonto::create(['orgao_id' => $this->orgao->id, 'servidor_id' => $this->joana->id, 'momento' => '2026-10-22 07:30', 'origem' => 'sistema']);

        $linhas = app(BoletimDeFrequencia::class)->linhas($this->rh, ['mes' => '2026-10', 'unidade' => (string) $this->dti->id]);
        $this->assertSame(20, $linhas[0]['faltas']);
        $this->assertStringStartsWith('Fechado em 04/11/2026', $linhas[0]['situacao']);

        $this->get('/relatorios/frequencia/exportar.pdf?mes=2026-10')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/relatorios/frequencia/exportar.xlsx?mes=2026-10')->assertOk();

        // A chefia vê só o próprio setor.
        $this->actingAs($this->chefeDad)->get('/relatorios/frequencia?mes=2026-10')->assertOk()->assertSee('Davi Fictício Souza')->assertDontSee('Joana Fictícia Lima');
        $this->actingAs($this->joanaUser)->get('/relatorios/frequencia')->assertForbidden();
    }

    public function test_relogio_nao_importa_marcacao_de_mes_fechado(): void
    {
        $this->actingAs($this->rh)->post("/frequencia/fechamento/{$this->dti->id}/fechar", ['mes' => '2026-10']);
        $cpf = str_pad(preg_replace('/\D/', '', $this->joana->cpf), 12, '0', STR_PAD_LEFT);
        $arquivo = tempnam(sys_get_temp_dir(), 'afd');
        file_put_contents($arquivo, implode("\r\n", [
            '000000000111122233300018100000000000000ORGAO FICTICIO',
            '0000000013'.'2026-10-20T07:30:00-0400'.$cpf.'AB12',
            '0000000023'.'2026-11-03T07:30:00-0400'.$cpf.'AB12',
        ]));

        $this->post('/importacoes', ['tipo' => 'ponto', 'arquivo' => new UploadedFile($arquivo, 'afd.txt', 'text/plain', null, true)])->assertSessionHasNoErrors();
        $importacao = Importacao::latest('id')->firstOrFail();
        $this->assertSame(1, $importacao->resumo['calculado']['validas']);
        $this->assertSame(1, $importacao->resumo['calculado']['com_erro']);
        $this->get("/importacoes/{$importacao->id}")->assertSee('de mês já fechado pelo RH (10/2026)');

        $this->post("/importacoes/{$importacao->id}/aplicar");
        $this->assertFalse(RegistroPonto::where('momento', '2026-10-20 07:30:00')->exists());
        $this->assertTrue(RegistroPonto::where('momento', '2026-11-03 07:30:00')->exists());
    }

    public function test_api_entrega_so_os_meses_fechados(): void
    {
        $this->actingAs($this->rh)->post("/frequencia/fechamento/{$this->dti->id}/fechar", ['mes' => '2026-10']);
        $admin = $this->usuario('administrador-orgao', $this->orgao);
        $this->actingAs($admin)->post('/chaves-api', ['nome' => 'Folha (teste)', 'escopos' => ['frequencia']])->assertSessionHas('chave_criada');
        $texto = session('chave_criada')['texto'];
        auth()->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$texto)->getJson('/api/v1/frequencia')->assertStatus(422);
        $r = $this->withHeader('Authorization', 'Bearer '.$texto)->getJson('/api/v1/frequencia?mes=2026-10')->assertOk();
        $r->assertJsonPath('setores_abertos', ['DAD'])
            ->assertJsonPath('dados.0.unidade.sigla', 'DTI')
            ->assertJsonPath('dados.0.servidores.0.servidor.nome', 'Joana Fictícia Lima')
            ->assertJsonPath('dados.0.servidores.0.faltas', 20);
        $this->assertStringNotContainsString('motivo', $r->getContent());
        $this->withHeader('Authorization', 'Bearer '.$texto)->getJson('/api/v1/servidores')->assertForbidden();
    }
}
