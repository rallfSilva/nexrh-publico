<?php

namespace Tests\Feature;

use App\Frequencia\Espelho;
use App\Models\Cargo;
use App\Models\Justificativa;
use App\Models\Orgao;
use App\Models\RegistroAuditoria;
use App\Models\RegistroPonto;
use App\Models\RegraFrequencia;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Vaga;
use Database\Factories\ServidorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class JustificativasTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n% declaracao ficticia de teste\n%%EOF\n";

    private Orgao $orgao;

    private Unidade $dti;

    private User $rh;

    private User $chefe;

    private Servidor $joana;

    private User $joanaUser;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-22 10:00:00'); // quinta-feira
        Storage::fake('local');
        $this->semearPerfis();
        $this->orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        $this->chefe = $this->usuario('chefia', $this->orgao);
        $this->dti = Unidade::factory()->for($this->orgao)->create(['sigla' => 'DTI', 'chefe_user_id' => $this->chefe->id]);
        $this->rh = $this->usuario('rh', $this->orgao);

        $this->joanaUser = $this->usuario('servidor', $this->orgao, ['cpf' => $cpf = ServidorFactory::cpf()]);
        $this->joana = Servidor::factory()->for($this->orgao)->create([
            'nome' => 'Joana Fictícia Lima', 'cpf' => $cpf, 'user_id' => $this->joanaUser->id,
            'unidade_id' => $this->dti->id, 'efeitos_a_partir_de' => '2024-03-01',
        ]);

        // 20/10 sem registro; 21/10 com atraso de 40 min; 19/10 normal.
        $this->marcar('2026-10-19 07:30', '2026-10-19 13:31', '2026-10-21 08:10', '2026-10-21 13:30');
    }

    private function marcar(string ...$momentos): void
    {
        foreach ($momentos as $m) {
            RegistroPonto::create(['orgao_id' => $this->orgao->id, 'servidor_id' => $this->joana->id, 'momento' => $m, 'origem' => 'sistema']);
        }
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('declaracao.pdf', self::PDF);
    }

    private function justificar(array $dados): TestResponse
    {
        return $this->actingAs($this->joanaUser)->post('/meu-ponto/justificativas', $dados + ['motivo' => 'Consulta médica (teste).']);
    }

    /** @return array<string, array<string, mixed>> */
    private function dias(): array
    {
        $calculo = (new Espelho)->carregar(collect([$this->joana->fresh()]), Carbon::parse('2026-10-01'))->calcular($this->joana->fresh(), $this->rh);

        return collect($calculo['dias'])->keyBy(fn ($d) => $d['data']->format('Y-m-d'))->all() + ['totais' => $calculo['totais']];
    }

    public function test_servidor_ve_as_ocorrencias_e_justifica_com_anexo(): void
    {
        $this->actingAs($this->joanaUser)->get('/meu-ponto')->assertOk()
            ->assertSee('Ocorrências para justificar')->assertSee('Ter, 20/10 · Sem registro', false)->assertSee('Entrada 40 min depois do horário');
        $this->get('/meu-ponto/justificar?data=2026-10-20')->assertOk()->assertSee('Terça-feira, 20/10/2026: Sem registro.');
        $this->get('/painel')->assertSee('ocorrências para justificar');

        $this->justificar(['tipo' => 'declaracao', 'inicio' => '2026-10-20', 'arquivo' => $this->pdf()])
            ->assertRedirect('/meu-ponto?mes=2026-10')->assertSessionHasNoErrors();

        $j = Justificativa::firstOrFail();
        $this->assertSame('pendente', $j->situacao);
        $this->assertSame('20/10: Sem registro', $j->ocorrencia);
        $this->assertTrue($j->enviado_pelo_servidor);
        $this->assertStringStartsWith("frequencia/{$this->orgao->id}/{$this->joana->id}/", $j->arquivo_caminho);
        $this->assertStringNotContainsString('%PDF', Storage::disk('local')->get($j->arquivo_caminho), 'o anexo deve ficar cifrado no disco');

        // Em análise: o dia continua sem registro, mas sai da lista de ocorrências.
        $d = $this->dias();
        $this->assertSame('falta', $d['2026-10-20']['situacao']);
        $this->assertFalse($d['2026-10-20']['justificavel']);
        $this->assertSame(1, $d['totais']['em_analise']);

        // O motivo pode ter dado de saúde: a trilha não guarda o texto.
        $auditoria = RegistroAuditoria::withoutGlobalScopes()->where('auditable_type', $j->getMorphClass())->firstOrFail();
        $this->assertSame('[protegido]', $auditoria->valores_novos['motivo']);
    }

    public function test_regras_do_envio(): void
    {
        $this->justificar(['tipo' => 'atestado', 'inicio' => '2026-10-20'])->assertSessionHasErrors(['arquivo' => 'Anexe o documento (PDF, JPG ou PNG).']);
        $this->justificar(['tipo' => 'outro', 'inicio' => '2026-10-20', 'arquivo' => UploadedFile::fake()->createWithContent('x.pdf', '<html></html>')])->assertSessionHasErrors(['arquivo' => 'Envie PDF, JPG ou PNG.']);
        $this->justificar(['tipo' => 'outro', 'inicio' => '2026-10-22'])->assertSessionHasErrors(['inicio' => 'Justifique a partir do dia seguinte, quando a ocorrência já aparece no espelho.']);
        $this->justificar(['tipo' => 'outro', 'inicio' => '2026-08-31'])->assertSessionHasErrors(['inicio' => 'O prazo para justificar vai até o mês anterior. Para dias mais antigos, fale com o RH.']);
        $this->justificar(['tipo' => 'outro', 'inicio' => '2026-10-19'])->assertSessionHasErrors(['inicio' => 'Nesse período não há falta, atraso nem registro incompleto para justificar.']);
        $this->justificar(['tipo' => 'outro', 'inicio' => '2026-10-20', 'fim' => '2026-10-21'])->assertSessionHasErrors(['fim' => 'Só o atestado médico vale para mais de um dia. Para outros motivos, envie uma justificativa por dia.']);
        $this->justificar(['tipo' => 'atestado', 'inicio' => '2026-10-01', 'fim' => '2026-10-21', 'arquivo' => $this->pdf()])->assertSessionHasErrors('fim');

        $this->justificar(['tipo' => 'esquecimento', 'inicio' => '2026-10-21', 'horarios' => 'entrada às 07:30'])->assertSessionHasNoErrors();
        $this->justificar(['tipo' => 'outro', 'inicio' => '2026-10-21'])->assertSessionHasErrors(['inicio' => 'Já há uma justificativa em análise para 21/10/2026.']);
        $this->assertSame(1, Justificativa::count());
    }

    public function test_atestado_de_varios_dias_abona_todos(): void
    {
        $this->justificar(['tipo' => 'atestado', 'inicio' => '2026-10-20', 'fim' => '2026-10-21', 'arquivo' => $this->pdf()])->assertSessionHasNoErrors();
        $j = Justificativa::firstOrFail();
        $this->assertSame('20/10: Sem registro; 21/10: Entrada 40 min depois do horário, Faltaram 0h40 para a jornada', $j->ocorrencia);

        $this->actingAs($this->chefe)->post("/frequencia/justificativas/{$j->id}/abonar")->assertSessionHasNoErrors();

        $d = $this->dias();
        $this->assertSame('abonado', $d['2026-10-20']['situacao']);
        $this->assertSame('abonado', $d['2026-10-21']['situacao']);
        $this->assertSame(360, $d['2026-10-20']['trabalhado']);
        $this->assertSame(360, $d['2026-10-21']['trabalhado']);
        $this->assertSame(2, $d['totais']['abonados']);
        $this->assertSame(0, $d['totais']['atencao']);
    }

    public function test_chefia_abona_ou_recusa_so_a_propria_equipe(): void
    {
        $this->justificar(['tipo' => 'declaracao', 'inicio' => '2026-10-20', 'arquivo' => $this->pdf()]);
        $this->justificar(['tipo' => 'outro', 'inicio' => '2026-10-21']);
        [$falta, $atraso] = Justificativa::orderBy('inicio')->get()->all();

        $this->actingAs($this->chefe)->get('/frequencia/justificativas')->assertOk()
            ->assertSee('Joana Fictícia Lima')->assertSee('20/10: Sem registro')->assertSee('declaracao.pdf');
        $this->get('/painel')->assertSee('Justificativas de ponto');

        // Anexo: a chefia vê, e cada acesso fica na trilha.
        $this->get("/justificativas/{$falta->id}/arquivo")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertTrue(RegistroAuditoria::withoutGlobalScopes()->where('evento', 'acesso')->where('auditable_id', $falta->id)->exists());

        $this->post("/frequencia/justificativas/{$atraso->id}/recusar", ['motivo_decisao' => ''])->assertSessionHasErrors('motivo_decisao');
        $this->post("/frequencia/justificativas/{$atraso->id}/recusar", ['motivo_decisao' => 'Atraso sem motivo aceito (teste).'])->assertSessionHasNoErrors();
        $this->post("/frequencia/justificativas/{$falta->id}/abonar")->assertSessionHasNoErrors();
        $this->post("/frequencia/justificativas/{$falta->id}/abonar")->assertStatus(409);

        $this->assertSame('recusada', $atraso->fresh()->situacao);
        $this->assertSame($this->chefe->id, $falta->fresh()->analisado_por);
        $d = $this->dias();
        $this->assertSame('abonado', $d['2026-10-20']['situacao']);
        $this->assertSame('atencao', $d['2026-10-21']['situacao']);
        $this->assertTrue($d['2026-10-21']['justificavel'], 'recusada, pode justificar de novo');

        $this->actingAs($this->joanaUser)->get('/meu-ponto')->assertSee('Atraso sem motivo aceito (teste).')->assertSee('justificativa recusada');
        $this->get('/frequencia/justificativas?aba=decididas')->assertForbidden();

        // Chefia de outra unidade não vê nem decide.
        $outroChefe = $this->usuario('chefia', $this->orgao);
        Unidade::factory()->for($this->orgao)->create(['sigla' => 'COAM', 'chefe_user_id' => $outroChefe->id]);
        $this->justificar(['tipo' => 'outro', 'inicio' => '2026-10-21']);
        $nova = Justificativa::latest('id')->first();
        $this->actingAs($outroChefe)->get('/frequencia/justificativas')->assertDontSee('Joana Fictícia Lima');
        $this->post("/frequencia/justificativas/{$nova->id}/abonar")->assertForbidden();
        $this->get("/justificativas/{$falta->id}/arquivo")->assertNotFound();

        // Auditor não abre anexo (pode ter dado de saúde).
        $this->actingAs($this->usuario('auditor', $this->orgao))->get("/justificativas/{$falta->id}/arquivo")->assertNotFound();
    }

    public function test_ninguem_decide_a_propria_justificativa(): void
    {
        $this->joana->update(['user_id' => $this->chefe->id]);
        $this->actingAs($this->chefe)->post('/meu-ponto/justificativas', ['tipo' => 'outro', 'inicio' => '2026-10-21', 'motivo' => 'Teste'])->assertSessionHasNoErrors();
        $j = Justificativa::firstOrFail();

        $this->get('/frequencia/justificativas')->assertDontSee('Joana Fictícia Lima');
        $this->post("/frequencia/justificativas/{$j->id}/abonar")->assertForbidden();
        $this->actingAs($this->rh)->post("/frequencia/justificativas/{$j->id}/abonar")->assertSessionHasNoErrors();
    }

    public function test_servidor_retira_a_justificativa_em_analise(): void
    {
        $this->justificar(['tipo' => 'outro', 'inicio' => '2026-10-21']);
        $j = Justificativa::firstOrFail();

        $outro = $this->usuario('servidor', $this->orgao);
        $this->actingAs($outro)->post("/meu-ponto/justificativas/{$j->id}/retirar")->assertNotFound();

        $this->actingAs($this->joanaUser)->post("/meu-ponto/justificativas/{$j->id}/retirar")->assertRedirect('/meu-ponto?mes=2026-10');
        $this->assertSame('retirada', $j->fresh()->situacao);
        $this->assertTrue($this->dias()['2026-10-21']['justificavel']);
        $this->post("/meu-ponto/justificativas/{$j->id}/retirar")->assertStatus(409);
    }

    public function test_rh_abona_direto_pelo_espelho(): void
    {
        $url = "/servidores/{$this->joana->id}/frequencia/abonos";
        $this->actingAs($this->rh)->post($url, ['tipo' => 'outro', 'inicio' => '2026-10-20', 'motivo' => 'Convocação para audiência (teste).'])
            ->assertRedirect("/servidores/{$this->joana->id}/frequencia?mes=2026-10")->assertSessionHasNoErrors();
        // Dia futuro: o RH pode abonar antes (convocação marcada).
        $this->post($url, ['tipo' => 'outro', 'inicio' => '2026-10-26', 'motivo' => 'Convocação marcada (teste).'])->assertSessionHasNoErrors();

        $j = Justificativa::orderBy('inicio')->first();
        $this->assertSame('abonada', $j->situacao);
        $this->assertFalse($j->enviado_pelo_servidor);
        $this->assertSame($this->rh->id, $j->analisado_por);
        $this->assertSame('abonado', $this->dias()['2026-10-20']['situacao']);

        $this->get("/servidores/{$this->joana->id}/frequencia?mes=2026-10")->assertOk()->assertSee('Abonar dias')->assertSee('Abonado');
        $this->actingAs($this->chefe)->post($url, ['tipo' => 'outro', 'inicio' => '2026-10-21', 'motivo' => 'x'])->assertForbidden();
    }

    public function test_cargos_dispensados_e_tolerancia_de_15_minutos(): void
    {
        $coord = Cargo::factory()->for($this->orgao)->create(['denominacao' => 'Coordenador']);
        $vaga = Vaga::create(['orgao_id' => $this->orgao->id, 'cargo_id' => $coord->id, 'numero' => 1, 'situacao' => 'ocupada']);
        $chefeDeSetor = Servidor::factory()->for($this->orgao)->create(['unidade_id' => $this->dti->id, 'vaga_id' => $vaga->id]);

        $espelho = (new Espelho)->carregar(collect([$chefeDeSetor]), Carbon::parse('2026-10-01'));
        $jornada = $espelho->jornadaEm($chefeDeSetor, today());
        $this->assertSame('dispensado', $jornada->modalidade);
        $this->assertSame('Dispensado pelo cargo: Coordenador.', $jornada->observacao);

        // Atraso de 12 minutos: dentro da tolerância.
        $this->marcar('2026-10-15 07:42', '2026-10-15 13:30');
        $this->assertSame('ok', $this->dias()['2026-10-15']['situacao']);

        // O RH tira o cargo da lista: passa a registrar ponto.
        $this->actingAs($this->rh)->get('/frequencia/regras')->assertSee('Cargos dispensados do ponto')->assertSee('Coordenador');
        $this->put('/frequencia/regras', [
            'turnos_6h' => '07:30-13:30', 'turnos_8h' => '08:00-12:00, 14:00-18:00', 'tolerancia_minutos' => 15, 'dia_fechamento' => 15,
            'exigir_rede' => '1', 'redes_permitidas' => '127.0.0.0/8',
        ])->assertSessionHasNoErrors();
        $this->assertSame([], RegraFrequencia::doOrgao($this->orgao->id)->cargos_dispensados);
        $espelho = (new Espelho)->carregar(collect([$chefeDeSetor->fresh()]), Carbon::parse('2026-10-01'));
        $this->assertSame('presencial', $espelho->jornadaEm($chefeDeSetor, today())->modalidade);

        $outro = Cargo::factory()->create();
        $this->put('/frequencia/regras', [
            'turnos_6h' => '07:30-13:30', 'turnos_8h' => '08:00-12:00, 14:00-18:00', 'tolerancia_minutos' => 15, 'dia_fechamento' => 15,
            'exigir_rede' => '1', 'redes_permitidas' => '127.0.0.0/8', 'cargos_dispensados' => [$outro->id],
        ])->assertSessionHasErrors('cargos_dispensados.0');
    }
}
