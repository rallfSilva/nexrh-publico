<?php

namespace Tests\Feature;

use App\Frequencia\Espelho;
use App\Frequencia\Turnos;
use App\Models\Afastamento;
use App\Models\Feriado;
use App\Models\Importacao;
use App\Models\Jornada;
use App\Models\Orgao;
use App\Models\RegistroAuditoria;
use App\Models\RegistroPonto;
use App\Models\RegraFrequencia;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\User;
use Database\Factories\ServidorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FrequenciaTest extends TestCase
{
    use RefreshDatabase;

    private Orgao $orgao;

    private Unidade $dti;

    private User $rh;

    private Servidor $joana;

    private User $joanaUser;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-22 10:00:00'); // quinta-feira
        $this->semearPerfis();
        $this->orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        $this->dti = Unidade::factory()->for($this->orgao)->create(['sigla' => 'DTI']);
        $this->rh = $this->usuario('rh', $this->orgao);

        $this->joanaUser = $this->usuario('servidor', $this->orgao, ['cpf' => $cpf = ServidorFactory::cpf()]);
        $this->joana = Servidor::factory()->for($this->orgao)->create([
            'nome' => 'Joana Fictícia Lima', 'cpf' => $cpf, 'user_id' => $this->joanaUser->id,
            'unidade_id' => $this->dti->id, 'efeitos_a_partir_de' => '2024-03-01', 'pis_pasep' => '120.00000.01-1',
        ]);
    }

    private function marcar(Servidor $s, string ...$momentos): void
    {
        foreach ($momentos as $m) {
            RegistroPonto::create(['orgao_id' => $s->orgao_id, 'servidor_id' => $s->id, 'momento' => $m, 'origem' => 'sistema']);
        }
    }

    /** @return array<string, array<string, mixed>> dias do espelho por data */
    private function espelho(Servidor $s, string $mes = '2026-10'): array
    {
        $calculo = (new Espelho)->carregar(collect([$s]), Carbon::parse($mes.'-01'))->calcular($s->fresh(), $this->rh);

        return collect($calculo['dias'])->keyBy(fn ($d) => $d['data']->format('Y-m-d'))->all() + ['totais' => $calculo['totais']];
    }

    public function test_turnos_sao_lidos_do_texto(): void
    {
        $this->assertSame([['07:30', '13:30']], Turnos::ler('07:30-13:30'));
        $this->assertSame([['08:00', '12:00'], ['14:00', '18:00']], Turnos::ler('8h00 às 12:00 e 14:00-18:00'));
        $this->assertNull(Turnos::ler('13:30-07:30'));
        $this->assertNull(Turnos::ler('08:00-12:00, 11:00-15:00'));
        $this->assertSame('6h30', Turnos::horas(390));
        $this->assertSame('-0h45', Turnos::horas(-45));
    }

    public function test_servidor_registra_entrada_e_saida(): void
    {
        $this->actingAs($this->joanaUser)->get('/meu-ponto')->assertOk()
            ->assertSee('Registrar entrada')->assertSee('07:30 às 13:30, de segunda a sexta');

        $this->post('/meu-ponto')->assertRedirect('/meu-ponto')->assertSessionHas('sucesso', 'Ponto registrado às 10:00 de 22/10/2026.');
        $r = RegistroPonto::firstOrFail();
        $this->assertSame('sistema', $r->origem);
        $this->assertSame($this->joanaUser->id, $r->registrado_por);
        $this->assertSame('127.0.0.1', $r->ip);

        // Clique duplo: menos de 2 minutos depois, não registra.
        Carbon::setTestNow('2026-10-22 10:01:30');
        $this->post('/meu-ponto')->assertSessionHasErrors('ponto');
        $this->assertSame(1, RegistroPonto::count());

        Carbon::setTestNow('2026-10-22 13:31:00');
        $this->get('/meu-ponto')->assertSee('Registrar saída');
        $this->post('/meu-ponto')->assertSessionHasNoErrors();
        $this->assertSame(['10:00', '13:31'], RegistroPonto::orderBy('momento')->get()->map(fn ($r) => $r->momento->format('H:i'))->all());
    }

    public function test_ponto_so_vale_da_rede_do_orgao_e_nao_para_teletrabalho(): void
    {
        $fora = ['REMOTE_ADDR' => '200.10.20.30'];
        $this->actingAs($this->joanaUser)->withServerVariables($fora)->get('/meu-ponto')
            ->assertSee('só pode ser registrado de dentro da rede do órgão')->assertDontSee('Registrar entrada');
        $this->withServerVariables($fora)->post('/meu-ponto')->assertSessionHasErrors('ponto');
        $this->assertSame(0, RegistroPonto::count());

        // Rede incluída nas regras: passa a valer.
        RegraFrequencia::create([...RegraFrequencia::PADRAO, 'orgao_id' => $this->orgao->id, 'redes_permitidas' => '200.10.20.0/24']);
        $this->withServerVariables($fora)->post('/meu-ponto')->assertSessionHasNoErrors();
        $this->assertSame(1, RegistroPonto::count());

        Jornada::create(['orgao_id' => $this->orgao->id, 'servidor_id' => $this->joana->id, 'modalidade' => 'teletrabalho', 'dias_semana' => [1, 2, 3, 4, 5], 'inicio' => '2026-10-01']);
        Carbon::setTestNow('2026-10-22 13:00:00');
        $this->withServerVariables($fora)->post('/meu-ponto')->assertSessionHasErrors(['ponto' => 'Sua jornada é em teletrabalho: não há registro de ponto.']);
    }

    public function test_quem_nao_tem_ficha_ve_aviso(): void
    {
        $this->actingAs($this->usuario('servidor', $this->orgao))->get('/meu-ponto')->assertOk()->assertDontSee('Registrar entrada');
        $this->post('/meu-ponto')->assertNotFound();
    }

    public function test_espelho_mostra_situacao_de_cada_dia(): void
    {
        Feriado::create(['orgao_id' => $this->orgao->id, 'data' => '2026-10-12', 'nome' => 'Nossa Senhora Aparecida', 'tipo' => 'feriado']);
        Afastamento::create(['orgao_id' => $this->orgao->id, 'servidor_id' => $this->joana->id, 'tipo' => 'Licença para tratamento de saúde', 'inicio' => '2026-10-19', 'fim' => '2026-10-20']);
        $this->marcar($this->joana, '2026-10-01 07:28', '2026-10-01 13:35'); // normal
        $this->marcar($this->joana, '2026-10-02 08:10', '2026-10-02 13:30'); // atraso de 40 min
        $this->marcar($this->joana, '2026-10-06 07:30');                      // falta a saída
        $this->marcar($this->joana, '2026-10-07 07:35', '2026-10-07 13:31', '2026-10-07 13:40'); // ímpar
        $this->marcar($this->joana, '2026-10-22 07:29');                      // hoje

        $d = $this->espelho($this->joana);

        $this->assertSame('ok', $d['2026-10-01']['situacao']);
        $this->assertSame(367, $d['2026-10-01']['trabalhado']);
        $this->assertSame('atencao', $d['2026-10-02']['situacao']);
        $this->assertSame(['Entrada 40 min depois do horário', 'Faltaram 0h40 para a jornada'], $d['2026-10-02']['alertas']);
        $this->assertSame('folga', $d['2026-10-03']['situacao']);
        $this->assertSame('falta', $d['2026-10-05']['situacao']);
        $this->assertSame('incompleto', $d['2026-10-06']['situacao']);
        $this->assertSame('incompleto', $d['2026-10-07']['situacao']);
        $this->assertSame('feriado', $d['2026-10-12']['situacao']);
        $this->assertSame('Nossa Senhora Aparecida', $d['2026-10-12']['titulo']);
        $this->assertSame('afastamento', $d['2026-10-19']['situacao']);
        $this->assertSame('hoje', $d['2026-10-22']['situacao']);
        $this->assertSame('futuro', $d['2026-10-23']['situacao']);

        // 15 dias úteis até ontem, menos o feriado e os 2 da licença; 2 com registro (1 a verificar).
        $t = $d['totais'];
        $this->assertSame(12, $t['dias_trabalho']);
        $this->assertSame(2, $t['trabalhados']);
        $this->assertSame(1, $t['atencao']);
        $this->assertSame(2, $t['incompletos']);
        $this->assertSame(8, $t['faltas']);
        $this->assertSame(2, $t['ausencias']);
    }

    public function test_carga_de_8h_usa_o_horario_com_almoco(): void
    {
        $this->joana->update(['carga_horaria' => '8h diárias / 40h semanais']);
        $this->marcar($this->joana, '2026-10-01 08:00', '2026-10-01 12:00', '2026-10-01 14:00', '2026-10-01 18:00');

        $d = $this->espelho($this->joana);
        $this->assertSame('ok', $d['2026-10-01']['situacao']);
        $this->assertSame(480, $d['2026-10-01']['esperado']);
        $this->assertSame('08:00 às 12:00 e 14:00 às 18:00, de segunda a sexta (8h00 por dia)', $d['2026-10-01']['jornada']->resumo());
    }

    public function test_chefia_ve_so_a_equipe_e_servidor_nao_ve_a_frequencia(): void
    {
        $outra = Unidade::factory()->for($this->orgao)->create(['sigla' => 'COAM']);
        $caio = Servidor::factory()->for($this->orgao)->create(['nome' => 'Caio Fictício Reis', 'unidade_id' => $outra->id]);
        $chefe = $this->usuario('chefia', $this->orgao);
        $this->dti->update(['chefe_user_id' => $chefe->id]);

        $this->actingAs($chefe)->get('/frequencia')->assertOk()->assertSee('Joana Fictícia Lima')->assertDontSee('Caio Fictício Reis');
        $this->get('/frequencia?unidade='.$outra->id)->assertDontSee('Caio Fictício Reis');
        $this->get("/servidores/{$this->joana->id}/frequencia")->assertOk()->assertDontSee('Incluir marcação');
        $this->get("/servidores/{$caio->id}/frequencia")->assertNotFound();
        $this->post("/servidores/{$this->joana->id}/frequencia/registros", ['data' => '2026-10-21', 'hora' => '07:30', 'motivo' => 'x'])->assertForbidden();

        $this->actingAs($this->joanaUser)->get('/frequencia')->assertForbidden();
        $this->get("/servidores/{$this->joana->id}/frequencia")->assertNotFound();

        $this->actingAs($this->usuario('auditor', $this->orgao))->get('/frequencia?unidade='.$outra->id)->assertOk()->assertSee('Caio Fictício Reis');
        $this->get('/frequencia/regras')->assertForbidden();
    }

    public function test_rh_inclui_e_anula_marcacao_com_motivo(): void
    {
        $url = "/servidores/{$this->joana->id}/frequencia";
        $this->actingAs($this->rh)->post("$url/registros", ['data' => '2026-10-21', 'hora' => '13:30', 'motivo' => ''])->assertSessionHasErrors('motivo');
        $this->post("$url/registros", ['data' => '2026-10-23', 'hora' => '07:30', 'motivo' => 'Teste'])->assertSessionHasErrors(['data' => 'Não dá para incluir marcação no futuro.']);

        $this->post("$url/registros", ['data' => '2026-10-21', 'hora' => '13:30', 'motivo' => 'Esqueceu de registrar a saída'])
            ->assertRedirect("$url?mes=2026-10")->assertSessionHasNoErrors();
        $r = RegistroPonto::firstOrFail();
        $this->assertSame('rh', $r->origem);
        $this->assertSame($this->rh->id, $r->registrado_por);
        $this->post("$url/registros", ['data' => '2026-10-21', 'hora' => '13:30', 'motivo' => 'De novo'])->assertSessionHasErrors(['hora' => 'Já existe uma marcação nesse horário.']);

        $this->post("$url/registros/{$r->id}/anular", ['motivo_anulacao' => ''])->assertSessionHasErrors('motivo_anulacao');
        $this->post("$url/registros/{$r->id}/anular", ['motivo_anulacao' => 'Incluída no dia errado'])->assertSessionHasNoErrors();
        $r->refresh();
        $this->assertFalse($r->valido());
        $this->assertSame('Incluída no dia errado', $r->motivo_anulacao);
        $this->assertSame(1, RegistroPonto::count()); // não apaga
        $this->post("$url/registros/{$r->id}/anular", ['motivo_anulacao' => 'De novo'])->assertStatus(409);

        $eventos = RegistroAuditoria::withoutGlobalScopes()->where('auditable_type', $r->getMorphClass())->where('auditable_id', $r->id)->pluck('evento')->all();
        $this->assertSame(['criado', 'alterado'], $eventos);

        // Marcação de outro servidor pela URL deste: não encontra.
        $outro = Servidor::factory()->for($this->orgao)->create(['unidade_id' => $this->dti->id]);
        $this->post("/servidores/{$outro->id}/frequencia/registros/{$r->id}/anular", ['motivo_anulacao' => 'x'])->assertNotFound();

        $this->get("$url?mes=2026-10")->assertOk()->assertSee('Incluída no dia errado');
    }

    public function test_nova_jornada_encerra_a_anterior(): void
    {
        $url = "/servidores/{$this->joana->id}/frequencia/jornada";
        $this->actingAs($this->rh)->post($url, ['modalidade' => 'presencial', 'turnos' => '7h às 9h depois', 'dias_semana' => [1], 'inicio' => '2026-10-01'])
            ->assertSessionHasErrors('turnos');

        $this->post($url, ['modalidade' => 'presencial', 'turnos' => '08:00-12:00, 14:00-18:00', 'dias_semana' => [1, 2, 3, 4, 5], 'inicio' => '2026-10-01'])->assertSessionHasNoErrors();
        $this->post($url, ['modalidade' => 'teletrabalho', 'dias_semana' => [1, 3, 5], 'inicio' => '2026-09-01'])->assertSessionHasErrors('inicio');
        $this->post($url, ['modalidade' => 'teletrabalho', 'dias_semana' => [5, 1, 3], 'inicio' => '2026-10-15', 'observacao' => 'Portaria fictícia 1/2026'])->assertSessionHasNoErrors();

        [$nova, $antiga] = $this->joana->jornadas()->get()->all();
        $this->assertSame('2026-10-14', $antiga->fim->format('Y-m-d'));
        $this->assertNull($nova->fim);
        $this->assertSame([1, 3, 5], $nova->dias_semana);
        $this->assertNull($nova->turnos);

        $d = $this->espelho($this->joana);
        $this->assertSame('falta', $d['2026-10-14']['situacao']); // ainda presencial
        $this->assertSame('teletrabalho', $d['2026-10-16']['situacao']);
        $this->assertSame('folga', $d['2026-10-20']['situacao']); // terça fora dos dias
    }

    public function test_regras_e_feriados(): void
    {
        $this->actingAs($this->rh)->get('/frequencia/regras')->assertOk()->assertSee('07:30-13:30')->assertSee('127.0.0.1');

        $base = ['turnos_6h' => '07:00-13:00', 'turnos_8h' => '08:00-12:00, 14:00-18:00', 'tolerancia_minutos' => 15, 'dia_fechamento' => 15, 'exigir_rede' => '1', 'redes_permitidas' => '10.20.0.0/16'];
        $this->put('/frequencia/regras', [...$base, 'turnos_6h' => '07:00-12:00'])->assertSessionHasErrors('turnos_6h');
        $this->put('/frequencia/regras', [...$base, 'redes_permitidas' => '10.20.0.0/99'])->assertSessionHasErrors('redes_permitidas');
        $this->put('/frequencia/regras', $base)->assertSessionHasNoErrors();

        $regra = RegraFrequencia::doOrgao($this->orgao->id);
        $this->assertSame([['07:00', '13:00']], $regra->turnos_6h);
        $this->assertTrue($regra->redePermite('10.20.5.9'));
        $this->assertFalse($regra->redePermite('127.0.0.1'));

        $this->post('/frequencia/feriados/sugeridos', ['ano' => 2026])->assertSessionHasNoErrors();
        $datas = Feriado::where('orgao_id', $this->orgao->id)->get()->keyBy(fn ($f) => $f->data->format('m-d'));
        $this->assertSame('Paixão de Cristo', $datas['04-03']->nome); // Páscoa em 05/04/2026
        $this->assertSame('ponto_facultativo', $datas['02-16']->tipo); // Carnaval
        $this->assertSame('Dia de Roraima', $datas['10-05']->nome);
        $this->assertSame('ponto_facultativo', $datas['10-28']->tipo);
        $n = $datas->count();
        $this->post('/frequencia/feriados/sugeridos', ['ano' => 2026])->assertSessionHas('sucesso', 'O calendário de 2026 já tinha todos os feriados sugeridos.');
        $this->assertSame($n, Feriado::count());

        $this->post('/frequencia/feriados', ['data' => '2026-10-05', 'nome' => 'Repetido', 'tipo' => 'feriado'])->assertSessionHasErrors('data');
        $this->post('/frequencia/feriados', ['data' => '2026-12-24', 'nome' => 'Véspera de Natal', 'tipo' => 'ponto_facultativo'])->assertSessionHasNoErrors();
        $vespera = Feriado::whereDate('data', '2026-12-24')->firstOrFail();
        $this->delete("/frequencia/feriados/{$vespera->id}")->assertSessionHasNoErrors();
        $this->assertNull(Feriado::find($vespera->id));
    }

    public function test_pascoa_e_feriados_moveis(): void
    {
        $this->assertSame('2027-03-28', Feriado::pascoa(2027)->format('Y-m-d'));
        $this->assertSame('2025-04-20', Feriado::pascoa(2025)->format('Y-m-d'));
        $this->assertArrayHasKey('2027-05-27', Feriado::sugeridos(2027)); // Corpus Christi
    }

    public function test_importa_arquivo_afd_do_relogio(): void
    {
        $caio = Servidor::factory()->for($this->orgao)->create(['nome' => 'Caio Fictício Reis', 'unidade_id' => $this->dti->id, 'pis_pasep' => '120.00000.02-0']);
        $this->marcar($this->joana, '2026-10-01 07:28'); // já está no sistema
        $cpf = preg_replace('/\D/', '', $this->joana->cpf);

        $linhas = [
            '000000000111122233300018100000000000000ORGAO FICTICIO',
            '0000000013'.'2026-10-01T07:28:00-0400'.str_pad($cpf, 12, '0', STR_PAD_LEFT).'AB12',
            '0000000023'.'2026-10-01T13:32:00-0400'.str_pad($cpf, 12, '0', STR_PAD_LEFT).'AB12',
            '0000000033'.'2026-10-01T13:32:00-0400'.str_pad($cpf, 12, '0', STR_PAD_LEFT).'AB12', // repetida no arquivo
            '0000000043'.'2026-10-02T07:30:00-0400'.'039053344705'.'AB12',                       // CPF de ninguém
            '0000000063'.'021020260731'.'012000000020',                                           // 1.510 com PIS do Caio
            '0000000073'.'99-99-9999T99:99:00-0400'.str_pad($cpf, 12, '0', STR_PAD_LEFT).'AB12', // ilegível
        ];
        $arquivo = tempnam(sys_get_temp_dir(), 'afd');
        file_put_contents($arquivo, implode("\r\n", $linhas));

        $this->actingAs($this->rh)->post('/importacoes', ['tipo' => 'ponto', 'arquivo' => new UploadedFile($arquivo, 'afd.txt', 'text/plain', null, true)])
            ->assertSessionHasNoErrors();
        $importacao = Importacao::latest('id')->firstOrFail();
        $this->assertSame('afd_671', $importacao->formato);
        $resumo = $importacao->resumo['calculado'];
        $this->assertSame(6, $resumo['linhas']);
        $this->assertSame(2, $resumo['validas']); // 13:32 da Joana e 07:31 do Caio
        $this->assertSame(1, $resumo['ja_registradas']);
        $this->assertSame(2, $resumo['com_erro']); // CPF de ninguém e linha ilegível

        $this->get("/importacoes/{$importacao->id}")->assertOk()->assertSee('***.533.447-**')->assertSee('Caio Fictício Reis')->assertDontSee('39053344705');

        $this->post("/importacoes/{$importacao->id}/aplicar")->assertRedirect('/frequencia');
        $this->assertSame(3, RegistroPonto::count());
        $caioReg = RegistroPonto::where('servidor_id', $caio->id)->firstOrFail();
        $this->assertSame('relogio', $caioReg->origem);
        $this->assertSame('2026-10-02 07:31', $caioReg->momento->format('Y-m-d H:i'));
        $this->assertSame(6, $caioReg->nsr);
        $this->assertSame($importacao->id, $caioReg->importacao_id);

        // Reenviar o mesmo arquivo não duplica.
        $this->post('/importacoes', ['tipo' => 'ponto', 'arquivo' => new UploadedFile($arquivo, 'afd.txt', 'text/plain', null, true)]);
        $this->assertSame(0, Importacao::latest('id')->first()->resumo['calculado']['validas']);
    }

    public function test_arquivo_sem_marcacoes_e_bloqueado_e_chefia_nao_importa(): void
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'afd');
        file_put_contents($arquivo, "isto não é um AFD\n");
        $this->actingAs($this->rh)->post('/importacoes', ['tipo' => 'ponto', 'arquivo' => new UploadedFile($arquivo, 'x.txt', 'text/plain', null, true)]);
        $importacao = Importacao::latest('id')->firstOrFail();
        $this->post("/importacoes/{$importacao->id}/aplicar")->assertSessionHas('erro');
        $this->assertSame(0, RegistroPonto::count());

        $this->actingAs($this->usuario('chefia', $this->orgao))->get('/importacoes/nova?tipo=ponto')->assertForbidden();
    }

    public function test_exemplo_ficticio_e_valido_e_nao_traz_cpf_real(): void
    {
        $conteudo = file_get_contents(resource_path('modelos-importacao/exemplo_ponto_ficticio.txt'));
        $this->assertStringContainsString('FICTICI', $conteudo);
        $this->actingAs($this->rh)->get('/modelos-importacao/exemplo_ponto_ficticio.txt')->assertOk();
    }
}
