<?php

namespace Tests\Feature;

use App\Models\Afastamento;
use App\Models\Ferias;
use App\Models\Orgao;
use App\Models\PeriodoAquisitivo;
use App\Models\RegistroAuditoria;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\User;
use Database\Factories\ServidorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FeriasTest extends TestCase
{
    use RefreshDatabase;

    private Orgao $orgao;

    private Unidade $dti;

    private User $rh;

    /** @var list<Servidor> três pessoas no DTI, mínimo de 2 presentes */
    private array $equipe;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-22 10:00:00'); // quinta-feira
        $this->semearPerfis();
        $this->orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        $this->dti = Unidade::factory()->for($this->orgao)->create(['sigla' => 'DTI', 'minimo_presentes_ferias' => 2]);
        $this->rh = $this->usuario('rh', $this->orgao);

        foreach (['Joana Fictícia Lima', 'Caio Fictício Reis', 'Lia Fictícia Prado'] as $nome) {
            $this->equipe[] = Servidor::factory()->for($this->orgao)->create(['nome' => $nome, 'unidade_id' => $this->dti->id, 'efeitos_a_partir_de' => '2024-03-01']);
        }
    }

    /** Abre a ficha (gera os períodos) e devolve o período que começa na data. */
    private function periodo(Servidor $s, string $inicio): PeriodoAquisitivo
    {
        $this->actingAs($this->rh)->get("/servidores/{$s->id}?aba=ferias")->assertOk();

        return PeriodoAquisitivo::where('servidor_id', $s->id)->whereDate('inicio', $inicio)->firstOrFail();
    }

    private function marcar(Servidor $s, PeriodoAquisitivo $p, string $inicio, int $dias): TestResponse
    {
        return $this->actingAs($this->rh)->post("/servidores/{$s->id}/ferias", ['periodo_aquisitivo_id' => $p->id, 'inicio' => $inicio, 'dias' => $dias]);
    }

    public function test_periodos_sao_contados_a_partir_do_ingresso(): void
    {
        $this->periodo($this->equipe[0], '2024-03-01');

        $periodos = PeriodoAquisitivo::where('servidor_id', $this->equipe[0]->id)->orderBy('inicio')->get();
        $this->assertSame(['2024/2025', '2025/2026', '2026/2027'], $periodos->map->rotulo()->all());
        $this->assertSame('2025-02-28', $periodos[0]->fim->format('Y-m-d'));
        $this->assertSame(30, $periodos[0]->dias_direito);

        // O primeiro período exige 12 meses de exercício; os seguintes, não.
        $this->assertSame('2025-03-01', $periodos[0]->liberadoEm()->format('Y-m-d'));
        $this->assertSame('2026-03-01', $periodos[2]->liberadoEm()->format('Y-m-d'));

        // Abrir de novo não duplica.
        $this->periodo($this->equipe[0], '2024-03-01');
        $this->assertSame(3, PeriodoAquisitivo::where('servidor_id', $this->equipe[0]->id)->count());

        // Dois períodos vencidos com saldo: pendência de férias acumuladas.
        $this->get("/servidores/{$this->equipe[0]->id}")->assertSee('Férias acumuladas: 2 períodos com saldo');
    }

    public function test_rh_marca_ferias_aprovadas_e_o_saldo_diminui(): void
    {
        $s = $this->equipe[0];
        $p = $this->periodo($s, '2024-03-01');

        $this->marcar($s, $p, '2026-11-09', 20)->assertSessionHasNoErrors()->assertRedirect("/servidores/{$s->id}?aba=ferias");

        $f = Ferias::firstOrFail();
        $this->assertSame('aprovada', $f->situacao);
        $this->assertSame('2026-11-28', $f->fim->format('Y-m-d'));
        $this->assertSame($this->rh->id, $f->analisado_por);
        $this->assertSame(10, $p->fresh()->saldo());
        $this->assertTrue(RegistroAuditoria::withoutGlobalScopes()->where('auditable_type', $f->getMorphClass())->where('evento', 'criado')->exists());

        $this->get("/servidores/{$s->id}?aba=ferias")->assertSee('09/11/2026 a 28/11/2026')->assertSee('Saldo de 10 dias');
    }

    public function test_regras_de_saldo_e_de_etapas(): void
    {
        $s = $this->equipe[0];
        $p = $this->periodo($s, '2024-03-01');

        $this->marcar($s, $p, '2026-11-09', 31)->assertSessionHasErrors(['dias' => 'O saldo do período 2024/2025 é de 30 dias.']);
        $this->marcar($s, $p, '2026-11-09', 8)->assertSessionHasErrors(['dias' => 'Cada etapa precisa ter pelo menos 10 dias (ou todo o saldo que resta, 30).']);
        $this->marcar($s, $p, '2026-11-09', 25)->assertSessionHasErrors('dias'); // sobrariam 5

        $this->marcar($s, $p, '2026-11-09', 10)->assertSessionHasNoErrors();
        $this->marcar($s, $p, '2026-12-07', 10)->assertSessionHasNoErrors();
        // Terceira e última etapa: precisa ser o saldo todo (10).
        $this->marcar($s, $p, '2027-01-11', 10)->assertSessionHasNoErrors();
        $this->marcar($s, $p, '2027-03-01', 10)->assertSessionHasErrors(['periodo_aquisitivo_id' => 'O período 2024/2025 não tem mais saldo.']);

        // Primeiro período de quem entrou há pouco: só depois de 12 meses.
        $novo = Servidor::factory()->for($this->orgao)->create(['unidade_id' => $this->dti->id, 'efeitos_a_partir_de' => '2026-07-01']);
        $pn = $this->periodo($novo, '2026-07-01');
        $this->marcar($novo, $pn, '2026-12-01', 30)->assertSessionHasErrors(['inicio' => 'As férias do período 2026/2027 podem começar a partir de 01/07/2027, quando se completam os 12 meses de exercício.']);
    }

    public function test_bloqueio_pelo_minimo_de_presentes_do_setor(): void
    {
        [$joana, $caio, $lia] = $this->equipe;

        $this->marcar($joana, $this->periodo($joana, '2024-03-01'), '2027-01-04', 30)->assertSessionHasNoErrors();

        // Com Joana fora, Caio sair deixaria só Lia: abaixo do mínimo de 2.
        $this->marcar($caio, $this->periodo($caio, '2024-03-01'), '2027-01-25', 20)
            ->assertSessionHasErrors(['inicio' => 'O setor DTI ficaria com 1 pessoa em 25/01/2027 (mínimo: 2) e em mais 8 dia(s) do período. Fora nesse dia: Joana Fictícia Lima.']);

        // Logo depois que ela volta, pode.
        $this->marcar($caio, $this->periodo($caio, '2024-03-01'), '2027-02-08', 20)->assertSessionHasNoErrors();

        // Afastamento também tira a pessoa do setor.
        Afastamento::create(['orgao_id' => $this->orgao->id, 'servidor_id' => $caio->id, 'tipo' => 'Licença para tratamento de saúde', 'inicio' => '2026-11-02', 'fim' => '2026-11-20']);
        $this->marcar($lia, $this->periodo($lia, '2024-03-01'), '2026-11-16', 30)->assertSessionHasErrors('inicio');

        $this->assertSame(2, Ferias::count());
    }

    public function test_nao_coincide_com_as_proprias_ferias_nem_com_afastamento(): void
    {
        $s = $this->equipe[0];
        $this->marcar($s, $this->periodo($s, '2024-03-01'), '2026-11-09', 30)->assertSessionHasNoErrors();
        $this->marcar($s, $this->periodo($s, '2025-03-01'), '2026-12-01', 30)
            ->assertSessionHasErrors(['inicio' => 'Já há férias marcadas de 09/11/2026 a 08/12/2026 que coincidem com estas datas.']);

        // Afastamento em cima de férias marcadas: o RH cancela ou interrompe antes.
        $this->post("/servidores/{$s->id}/afastamentos", ['tipo' => 'Licença gala (casamento)', 'inicio' => '2026-11-20', 'fim' => '2026-11-27'])
            ->assertSessionHasErrors(['inicio' => 'Há férias marcadas de 09/11/2026 a 08/12/2026. Cancele ou remarque as férias antes de registrar o afastamento.']);

        $this->post("/servidores/{$s->id}/afastamentos", ['tipo' => 'Licença gala (casamento)', 'inicio' => '2027-02-01', 'fim' => '2027-02-08', 'documento' => 'Certidão'])->assertSessionHasNoErrors();
        $this->marcar($s, $this->periodo($s, '2025-03-01'), '2027-01-25', 30)->assertSessionHasErrors(['inicio' => 'Há licença ou afastamento registrado de 01/02/2027 a 08/02/2027, que coincide com estas datas.']);
    }

    public function test_servidor_pede_e_a_chefia_aprova(): void
    {
        [$joana] = $this->equipe;
        $servidorUser = $this->usuario('servidor', $this->orgao, ['cpf' => $cpf = ServidorFactory::cpf()]);
        $joana->update(['cpf' => $cpf, 'user_id' => $servidorUser->id]);
        $chefe = $this->usuario('chefia', $this->orgao);
        $this->dti->update(['chefe_user_id' => $chefe->id]);

        $this->actingAs($servidorUser)->get('/minha-ficha?aba=ferias')->assertOk()->assertSee('Pedir férias');
        $this->get('/minha-ficha/ferias/pedir')->assertOk()->assertSee('2024/2025')->assertSee('Datas livres no setor DTI');
        $p = PeriodoAquisitivo::where('servidor_id', $joana->id)->orderBy('inicio')->firstOrFail();

        $this->post('/minha-ficha/ferias', ['periodo_aquisitivo_id' => $p->id, 'inicio' => '2026-11-02', 'dias' => 30])
            ->assertSessionHasErrors(['inicio' => 'Peça com pelo menos 30 dias de antecedência: a partir de 21/11/2026. Para datas mais próximas, fale com o RH.']);
        $this->post('/minha-ficha/ferias', ['periodo_aquisitivo_id' => $p->id, 'inicio' => '2027-01-04', 'dias' => 30])->assertRedirect('/minha-ficha?aba=ferias');

        $pedido = Ferias::firstOrFail();
        $this->assertSame('programada', $pedido->situacao);
        $this->assertTrue($pedido->solicitada_pelo_servidor);
        $this->get('/minha-ficha?aba=ferias')->assertSee('Aguardando aprovação')->assertSee('Retirar pedido');

        // O servidor não aprova o próprio pedido: pelas rotas do RH a ficha nem existe para ele.
        $this->post("/servidores/{$joana->id}/ferias/{$pedido->id}/aprovar")->assertNotFound();

        $this->actingAs($chefe)->get('/ferias/a-aprovar')->assertOk()->assertSee('Joana Fictícia Lima')->assertSee('04/01/2027 a 02/02/2027');
        $this->post("/servidores/{$joana->id}/ferias/{$pedido->id}/aprovar", ['voltar' => 'fila'])->assertRedirect('/ferias/a-aprovar');
        $this->assertSame('aprovada', $pedido->refresh()->situacao);
        $this->assertSame($chefe->id, $pedido->analisado_por);
        $this->post("/servidores/{$joana->id}/ferias/{$pedido->id}/aprovar")->assertStatus(409);
    }

    public function test_recusa_pede_motivo_e_o_saldo_volta(): void
    {
        [$joana] = $this->equipe;
        $p = $this->periodo($joana, '2024-03-01');
        $pedido = Ferias::create(['orgao_id' => $this->orgao->id, 'servidor_id' => $joana->id, 'periodo_aquisitivo_id' => $p->id, 'etapa' => 1, 'inicio' => '2027-01-04', 'fim' => '2027-02-02', 'dias' => 30, 'situacao' => 'programada', 'solicitada_pelo_servidor' => true]);
        $this->assertSame(0, $p->fresh()->saldo());

        $this->post("/servidores/{$joana->id}/ferias/{$pedido->id}/recusar", [])->assertSessionHasErrors('motivo');
        $this->post("/servidores/{$joana->id}/ferias/{$pedido->id}/recusar", ['motivo' => 'Entrega do sistema em janeiro.'])->assertRedirect();

        $this->assertSame('recusada', $pedido->refresh()->situacao);
        $this->assertSame(30, $p->fresh()->saldo());
        $this->get("/servidores/{$joana->id}?aba=ferias")->assertSee('Recusada')->assertSee('Entrega do sistema em janeiro.');
    }

    public function test_cancelar_e_interromper(): void
    {
        [$joana, $caio] = $this->equipe;
        $p = $this->periodo($joana, '2024-03-01');
        $emGozo = Ferias::create(['orgao_id' => $this->orgao->id, 'servidor_id' => $joana->id, 'periodo_aquisitivo_id' => $p->id, 'etapa' => 1, 'inicio' => '2026-10-15', 'fim' => '2026-11-03', 'dias' => 20, 'situacao' => 'aprovada']);
        $this->assertSame('em_gozo', $emGozo->situacaoAtual());

        $base = "/servidores/{$joana->id}/ferias/{$emGozo->id}";
        $this->post("{$base}/cancelar", ['motivo' => 'x'])->assertStatus(409); // já começou: só interrompe
        $this->post("{$base}/interromper", ['interrompida_em' => '2026-10-15', 'motivo_interrupcao' => 'Calamidade pública'])->assertSessionHasErrors('interrompida_em');
        $this->post("{$base}/interromper", ['interrompida_em' => '2026-10-22', 'motivo_interrupcao' => 'Necessidade do serviço declarada pela autoridade máxima do órgão'])->assertRedirect();

        $emGozo->refresh();
        $this->assertSame('interrompida', $emGozo->situacao);
        $this->assertSame(7, $emGozo->dias);
        $this->assertSame('2026-10-21', $emGozo->fim->format('Y-m-d'));
        $this->assertSame('2026-11-03', $emGozo->fim_previsto->format('Y-m-d'));
        $this->assertSame(23, $p->fresh()->saldo());

        $pc = $this->periodo($caio, '2024-03-01');
        $this->marcar($caio, $pc, '2027-03-01', 30);
        $futura = Ferias::where('servidor_id', $caio->id)->firstOrFail();
        $this->post("/servidores/{$caio->id}/ferias/{$futura->id}/cancelar", [])->assertSessionHasErrors('motivo');
        $this->post("/servidores/{$caio->id}/ferias/{$futura->id}/cancelar", ['motivo' => 'Pedido do servidor.'])->assertRedirect();
        $this->assertSame('cancelada', $futura->refresh()->situacao);
        $this->assertSame(30, $pc->fresh()->saldo());

        $this->delete("/servidores/{$caio->id}/ferias/{$futura->id}")->assertNotFound(); // não existe rota de exclusão
    }

    public function test_sugestoes_respeitam_o_setor_e_comecam_na_segunda(): void
    {
        [$joana, $caio] = $this->equipe;
        $this->marcar($joana, $this->periodo($joana, '2024-03-01'), '2026-10-26', 30)->assertSessionHasNoErrors();
        $p = $this->periodo($caio, '2024-03-01');

        $lista = $this->getJson("/servidores/{$caio->id}/ferias/sugestoes?periodo={$p->id}&dias=15")->assertOk()->json();

        $this->assertCount(3, $lista);
        foreach ($lista as $s) {
            $inicio = Carbon::parse($s['inicio']);
            $this->assertTrue($inicio->isMonday());
            $this->assertTrue($inicio->gt('2026-11-24'), 'Joana fica fora até 24/11: o setor não comporta Caio antes');
        }
        $this->assertSame('2026-11-30', $lista[0]['inicio']);
        $this->getJson("/servidores/{$caio->id}/ferias/sugestoes?periodo={$p->id}&dias=31")->assertOk()->assertExactJson([]);
    }

    public function test_quem_ve_e_quem_altera(): void
    {
        [$joana] = $this->equipe;
        $p = $this->periodo($joana, '2024-03-01');
        $this->marcar($joana, $p, '2026-11-09', 30);
        Afastamento::create(['orgao_id' => $this->orgao->id, 'servidor_id' => $joana->id, 'tipo' => 'Licença para tratamento de saúde', 'inicio' => '2027-02-01', 'fim' => '2027-02-05']);
        $f = Ferias::firstOrFail();

        $auditor = $this->usuario('auditor', $this->orgao);
        $this->actingAs($auditor)->get('/ferias?unidade='.$this->dti->id)->assertOk()->assertSee('Joana Fictícia Lima');
        $this->get("/servidores/{$joana->id}?aba=ferias")->assertOk()->assertSee('Licença ou afastamento')->assertDontSee('Licença para tratamento de saúde')->assertDontSee('Marcar férias');
        $this->get("/servidores/{$joana->id}/ferias/marcar")->assertForbidden();
        $this->post("/servidores/{$joana->id}/ferias/{$f->id}/cancelar", ['motivo' => 'x'])->assertForbidden();
        $this->get('/ferias/a-aprovar')->assertForbidden();

        // Chefia de outra unidade: não vê o DTI na escala e o servidor nem existe para ela.
        $outra = Unidade::factory()->for($this->orgao)->create(['sigla' => 'COAM']);
        $chefe = $this->usuario('chefia', $this->orgao);
        $outra->update(['chefe_user_id' => $chefe->id]);
        $this->actingAs($chefe)->get('/ferias')->assertOk()->assertSee('COAM')->assertDontSee('Joana Fictícia Lima');
        $this->post("/servidores/{$joana->id}/ferias/{$f->id}/aprovar")->assertNotFound();

        // Férias de outro servidor na URL: 404.
        $caio = $this->equipe[1];
        $this->actingAs($this->rh)->post("/servidores/{$caio->id}/ferias/{$f->id}/cancelar", ['motivo' => 'x'])->assertNotFound();

        // Outro órgão: não vê.
        $this->actingAs($this->usuario('rh', Orgao::factory()->create()))->get("/servidores/{$joana->id}/ferias/marcar")->assertNotFound();
    }

    public function test_escala_do_mes_e_do_ano(): void
    {
        [$joana] = $this->equipe;
        $this->marcar($joana, $this->periodo($joana, '2024-03-01'), '2026-11-09', 30);

        $this->get('/ferias?unidade='.$this->dti->id.'&mes=2026-11')->assertOk()
            ->assertSee('DTI · Novembro de 2026')->assertSee('mínimo de 2 presentes')
            ->assertSee('Férias · 09/11/2026 a 08/12/2026');
        $this->get('/ferias?unidade='.$this->dti->id.'&visao=ano&ano=2026')->assertOk()->assertSee('22 dia(s) de férias', false);
    }

    public function test_regras_do_orgao_sao_editaveis_e_valem_nos_pedidos(): void
    {
        $this->actingAs($this->rh)->get('/ferias/regras')->assertOk()->assertSee('Regras provisórias');
        $this->put('/ferias/regras', ['dias_por_periodo' => 30, 'max_etapas' => 2, 'min_dias_etapa' => 15, 'max_periodos_acumulados' => 2, 'antecedencia_dias' => 45, 'fundamento' => 'Norma fictícia de teste'])
            ->assertSessionHasNoErrors()->assertRedirect('/ferias/regras');

        $s = $this->equipe[0];
        $p = $this->periodo($s, '2024-03-01');
        $this->marcar($s, $p, '2026-11-09', 10)->assertSessionHasErrors(['dias' => 'Cada etapa precisa ter pelo menos 15 dias (ou todo o saldo que resta, 30).']);
        $this->marcar($s, $p, '2026-11-09', 15)->assertSessionHasNoErrors();
    }
}
