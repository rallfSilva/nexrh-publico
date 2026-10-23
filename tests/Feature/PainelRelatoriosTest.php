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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Painel por perfil e relatórios: cada um vê só o que pode, o CPF sai mascarado
 * nas exportações e cada exportação fica na trilha de auditoria.
 */
class PainelRelatoriosTest extends TestCase
{
    use RefreshDatabase;

    private Orgao $orgao;

    private User $rh;

    private User $chefia;

    private Servidor $ana;

    private Servidor $bruno;

    private Servidor $carla;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-22 10:00:00');
        $this->semearPerfis();
        $this->orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        $this->rh = $this->usuario('rh', $this->orgao);
        $this->chefia = $this->usuario('chefia', $this->orgao);
        $dti = Unidade::factory()->for($this->orgao)->create(['sigla' => 'DTI', 'chefe_user_id' => $this->chefia->id, 'minimo_presentes_ferias' => 1]);
        $dac = Unidade::factory()->for($this->orgao)->create(['sigla' => 'DAC', 'minimo_presentes_ferias' => 1]);

        $this->ana = Servidor::factory()->for($this->orgao)->create(['nome' => 'Ana Fictícia Teste', 'cpf' => '52998224725', 'unidade_id' => $dti->id, 'efeitos_a_partir_de' => '2024-01-02']);
        $this->bruno = Servidor::factory()->for($this->orgao)->create(['nome' => 'Bruno Fictício Teste', 'unidade_id' => $dti->id, 'efeitos_a_partir_de' => '2024-01-02']);
        $this->carla = Servidor::factory()->for($this->orgao)->create(['nome' => 'Carla Fictícia Teste', 'unidade_id' => $dac->id, 'efeitos_a_partir_de' => '2024-01-02']);

        $periodo = PeriodoAquisitivo::create(['orgao_id' => $this->orgao->id, 'servidor_id' => $this->ana->id, 'inicio' => '2025-01-02', 'fim' => '2026-01-01', 'dias_direito' => 30]);
        Ferias::create(['orgao_id' => $this->orgao->id, 'servidor_id' => $this->ana->id, 'periodo_aquisitivo_id' => $periodo->id, 'etapa' => 1, 'inicio' => '2026-10-19', 'fim' => '2026-11-07', 'dias' => 20, 'situacao' => 'aprovada']);
        Afastamento::create(['orgao_id' => $this->orgao->id, 'servidor_id' => $this->carla->id, 'tipo' => 'Licença para tratamento de saúde', 'inicio' => '2026-10-20', 'fim' => '2026-10-24']);

        // Outro órgão, que nunca pode aparecer.
        $outro = Orgao::factory()->create(['sigla' => 'OUTRO']);
        Servidor::factory()->for($outro)->create(['nome' => 'Pessoa de Outro Órgão']);
    }

    public function test_painel_do_rh_mostra_os_numeros_do_orgao(): void
    {
        $this->actingAs($this->rh)->get('/painel')->assertOk()
            ->assertSee('Servidores ativos')
            ->assertSeeInOrder(['De férias hoje', '1', '1 pessoa de licença ou afastamento'])
            ->assertSee('Servidores por unidade')
            ->assertSee('Férias nos próximos 12 meses')
            ->assertSee('Ana Fictícia Teste')
            ->assertDontSee('Pessoa de Outro Órgão');
    }

    public function test_painel_da_chefia_mostra_so_a_equipe(): void
    {
        $resposta = $this->actingAs($this->chefia)->get('/painel')->assertOk()
            ->assertSee('Minha equipe')
            ->assertSee('Ana Fictícia Teste')
            ->assertDontSee('Carla Fictícia Teste')
            ->assertDontSee('Cargos vagos');

        $this->assertStringContainsString('2</span>', $resposta->getContent()); // 2 pessoas na equipe
    }

    public function test_painel_do_servidor_mostra_a_propria_situacao(): void
    {
        $user = $this->usuario('servidor', $this->orgao, ['cpf' => '52998224725']);
        $this->ana->update(['user_id' => $user->id]);

        $this->actingAs($user)->get('/painel')->assertOk()
            ->assertSee('Saldo de férias')
            ->assertSee('O que falta na sua ficha')
            ->assertDontSee('Servidores por unidade')
            ->assertDontSee('Bruno Fictício Teste');
    }

    public function test_cada_perfil_ve_so_os_relatorios_que_pode(): void
    {
        $this->actingAs($this->rh)->get('/relatorios')->assertOk()->assertSee('Dependentes')->assertSee('Quadro de cargos e vagas');

        $auditor = $this->usuario('auditor', $this->orgao);
        $this->actingAs($auditor)->get('/relatorios')->assertOk()->assertSee('Pendências dos servidores')->assertDontSee('Dependentes por finalidade');
        $this->get('/relatorios/dependentes')->assertNotFound();

        $this->actingAs($this->chefia)->get('/relatorios')->assertOk()->assertSee('Saldo de férias')->assertDontSee('Quadro de cargos');
        $this->get('/relatorios/cargos')->assertNotFound();

        $servidor = $this->usuario('servidor', $this->orgao);
        $this->actingAs($servidor)->get('/relatorios')->assertForbidden();
        $this->actingAs($this->rh)->get('/relatorios/nao-existe')->assertNotFound();
    }

    public function test_relatorio_de_servidores_mascara_o_cpf_e_respeita_a_equipe(): void
    {
        $this->actingAs($this->rh)->get('/relatorios/servidores')->assertOk()
            ->assertSee('***.982.247-**')
            ->assertDontSee('529.982.247-25')
            ->assertSee('Carla Fictícia Teste')
            ->assertDontSee('Pessoa de Outro Órgão');

        $this->actingAs($this->chefia)->get('/relatorios/servidores')->assertOk()
            ->assertSee('Ana Fictícia Teste')
            ->assertDontSee('Carla Fictícia Teste');

        // Filtro com valor que não está nas opções é ignorado.
        $this->actingAs($this->chefia)->get('/relatorios/servidores?situacao=qualquer')->assertOk()->assertSee('Ana Fictícia Teste');
    }

    public function test_exportar_excel_e_pdf_registra_na_auditoria(): void
    {
        $this->actingAs($this->rh);

        $excel = $this->get('/relatorios/servidores/exportar.xlsx')->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $conteudo = $this->textoDoExcel($excel->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('***.982.247-**', $conteudo);
        $this->assertStringNotContainsString('529.982.247-25', $conteudo);
        $this->assertStringNotContainsString('52998224725', $conteudo);

        $this->get('/relatorios/ferias/exportar.pdf?ano=2026')->assertOk()->assertHeader('content-type', 'application/pdf');

        $registros = RegistroAuditoria::where('evento', 'exportacao')->orderBy('id')->get();
        $this->assertCount(2, $registros);
        $this->assertSame('Relatório "Servidores" exportado em Excel (3 linhas)', $registros[0]->descricao);
        $this->assertSame(['ano' => '2026'], $registros[1]->valores_novos['filtros']);
    }

    public function test_afastamentos_so_mostram_o_tipo_para_quem_ve_dados_pessoais(): void
    {
        $this->actingAs($this->rh)->get('/relatorios/afastamentos?ano=2026')->assertOk()->assertSee('Licença para tratamento de saúde');

        $auditor = $this->usuario('auditor', $this->orgao);
        $this->actingAs($auditor)->get('/relatorios/afastamentos?ano=2026')->assertOk()
            ->assertSee('Licença ou afastamento')
            ->assertDontSee('tratamento de saúde');
    }

    public function test_saldo_de_ferias_gera_os_periodos_e_aponta_quem_acumulou(): void
    {
        $this->actingAs($this->rh)->get('/relatorios/saldo-ferias?mostrar=acumulados')->assertOk()
            ->assertSee('Bruno Fictício Teste')
            ->assertSee('Acumulou 2 períodos');
    }

    public function test_manual_abre_para_todos_os_perfis(): void
    {
        foreach (['rh', 'chefia', 'servidor', 'auditor', 'administrador-orgao'] as $perfil) {
            $this->actingAs($this->usuario($perfil, $this->orgao))->get('/ajuda')->assertOk()->assertSee('Manual do sistema')->assertSee('Para você');
        }
    }

    /** Texto das células de uma planilha .xlsx (strings em linha, como o OpenSpout grava). */
    private function textoDoExcel(string $caminho): string
    {
        $zip = new \ZipArchive;
        $zip->open($caminho);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        return html_entity_decode(strip_tags(str_replace('<', ' <', $xml)));
    }
}
