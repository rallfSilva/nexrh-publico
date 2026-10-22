<?php

namespace Tests\Feature;

use App\Importacao\AnalisadorQuadroCargos;
use App\Models\Cargo;
use App\Models\Importacao;
use App\Models\Orgao;
use App\Models\RegistroAuditoria;
use App\Models\Vaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * Critério de aceite: a planilha de junho/2026 (aqui, a cópia com nomes e CPFs fictícios)
 * mostra 168 cargos e aponta a divergência do Assistente Executivo.
 */
class ImportacaoCargosTest extends TestCase
{
    use RefreshDatabase;

    private const PLANILHA = __DIR__.'/../fixtures/estrutura-cargos-ficticia.xlsx';

    private Orgao $orgao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->semearPerfis();
        Storage::fake('local');
        $this->orgao = Orgao::factory()->create(['sigla' => 'SELC']);
    }

    private function enviar(string $caminho = self::PLANILHA, string $nome = 'junho-2026.xlsx'): Importacao
    {
        $rh = $this->usuario('rh', $this->orgao);
        $this->actingAs($rh)
            ->post('/importacoes', ['arquivo' => new UploadedFile($caminho, $nome, null, null, true)])
            ->assertRedirect();

        return Importacao::latest('id')->firstOrFail();
    }

    public function test_analise_conta_168_cargos_e_os_totais_reais(): void
    {
        $r = (new AnalisadorQuadroCargos)->analisar(self::PLANILHA);

        $this->assertSame('estrutura_selc', $r['formato']);
        $this->assertSame(21, $r['resumo']['calculado']['cargos']);
        $this->assertSame(168, $r['resumo']['calculado']['total']);
        $this->assertSame(49, $r['resumo']['calculado']['remanejadas']);
        $this->assertSame(119, $r['resumo']['calculado']['disponiveis']);
        $this->assertSame(95, $r['resumo']['calculado']['ocupadas']);
        $this->assertSame(24, $r['resumo']['calculado']['vagas']);
    }

    public function test_aponta_a_divergencia_do_assistente_executivo(): void
    {
        $r = (new AnalisadorQuadroCargos)->analisar(self::PLANILHA);
        $mensagens = collect($r['divergencias'])->where('cargo', 'Assistente Executivo')->pluck('mensagem')->implode(' | ');

        $this->assertStringContainsString('O cabeçalho informa 17 ocupadas, mas a lista tem 15', $mensagens);
        $this->assertStringContainsString('10 remanejadas + 17 ocupadas + 3 livres = 30, mas o cargo tem 28 vagas', $mensagens);
    }

    public function test_aponta_que_o_resumo_da_planilha_nao_bate(): void
    {
        $r = (new AnalisadorQuadroCargos)->analisar(self::PLANILHA);
        $mensagens = collect($r['divergencias'])->pluck('mensagem')->implode(' | ');

        $this->assertSame(['total' => 168, 'remanejadas' => 49, 'disponiveis' => 119, 'ocupadas' => 97, 'vagas' => 23], $r['resumo']['declarado']);
        $this->assertStringContainsString('"Cargos ocupados" informa 97, mas a contagem das vagas dá 95', $mensagens);
        $this->assertStringContainsString('"Cargos vagos" informa 23, mas a contagem das vagas dá 24', $mensagens);
    }

    public function test_divergencias_nunca_mostram_cpf_completo(): void
    {
        $r = (new AnalisadorQuadroCargos)->analisar(self::PLANILHA);

        foreach ($r['divergencias'] as $d) {
            $this->assertDoesNotMatchRegularExpression('/\d{3}\.\d{3}\.\d{3}/', $d['mensagem']);
        }
    }

    public function test_envio_mostra_previa_sem_alterar_o_quadro(): void
    {
        $importacao = $this->enviar();

        $this->assertSame('analisada', $importacao->situacao);
        $this->assertSame(0, Cargo::withoutGlobalScopes()->count());

        $this->get("/importacoes/{$importacao->id}")
            ->assertOk()
            ->assertSee('Relatório de divergências')
            ->assertSee('Confirmar importação')
            ->assertSee('Assistente Executivo')
            ->assertSee('Diferença de 2');
    }

    public function test_confirmar_grava_cargos_vagas_e_um_registro_de_auditoria(): void
    {
        $importacao = $this->enviar();
        $antes = RegistroAuditoria::withoutGlobalScopes()->count();

        $this->post("/importacoes/{$importacao->id}/aplicar")->assertRedirect();

        $this->assertSame(21, Cargo::count());
        $this->assertSame(168, Vaga::count());
        $this->assertSame(168, (int) Cargo::sum('quantidade_vagas'));
        $this->assertSame(95, Vaga::where('situacao', 'ocupada')->count());
        $this->assertSame(24, Vaga::where('situacao', 'vaga')->count());

        $assistente = Cargo::where('denominacao', 'Assistente Executivo')->firstOrFail();
        $this->assertSame('CNEI-I', $assistente->simbolo);
        $this->assertSame(15, $assistente->vagas()->where('situacao', 'ocupada')->count());

        $importacao->refresh();
        $this->assertSame('aplicada', $importacao->situacao);
        $this->assertNull($importacao->dados, 'Os dados pessoais não devem ficar duplicados na importação.');

        // Um registro para a aplicação, em vez de um por vaga.
        $this->assertSame($antes + 1, RegistroAuditoria::withoutGlobalScopes()->count());
    }

    public function test_cpf_do_ocupante_fica_criptografado_no_banco(): void
    {
        $this->post('/importacoes/'.$this->enviar()->id.'/aplicar');

        $vaga = Vaga::where('situacao', 'ocupada')->whereNotNull('ocupante_cpf')->firstOrFail();
        $bruto = \DB::table('vagas')->where('id', $vaga->id)->value('ocupante_cpf');

        $this->assertMatchesRegularExpression('/^\d{11}$/', $vaga->ocupante_cpf);
        $this->assertStringNotContainsString($vaga->ocupante_cpf, $bruto);
    }

    public function test_reimportar_atualiza_sem_duplicar(): void
    {
        $this->post('/importacoes/'.$this->enviar()->id.'/aplicar');
        $this->post('/importacoes/'.$this->enviar()->id.'/aplicar');

        $this->assertSame(21, Cargo::count());
        $this->assertSame(168, Vaga::count());
    }

    public function test_cancelar_nao_altera_nada_e_apaga_o_arquivo(): void
    {
        $importacao = $this->enviar();
        Storage::disk('local')->assertExists($importacao->arquivo_caminho);

        $this->post("/importacoes/{$importacao->id}/cancelar")->assertRedirect('/importacoes');

        $this->assertSame('cancelada', $importacao->fresh()->situacao);
        Storage::disk('local')->assertMissing($importacao->arquivo_caminho);
        $this->assertSame(0, Cargo::count());
        $this->post("/importacoes/{$importacao->id}/aplicar")->assertSessionHas('erro');
    }

    public function test_arquivo_que_nao_e_planilha_e_recusado(): void
    {
        $this->actingAs($this->usuario('rh', $this->orgao))
            ->post('/importacoes', ['arquivo' => UploadedFile::fake()->create('virus.exe', 10)])
            ->assertSessionHasErrors('arquivo');
    }

    public function test_planilha_de_formato_desconhecido_e_recusada(): void
    {
        $caminho = sys_get_temp_dir().'/qualquer.xlsx';
        $w = new Writer;
        $w->openToFile($caminho);
        $w->addRow(Row::fromValues(['nome', 'idade']));
        $w->close();

        $importacao = $this->enviar($caminho, 'qualquer.xlsx');

        $this->assertSame('recusada', $importacao->situacao);
        $this->assertFalse($importacao->podeAplicar());
    }

    public function test_modelo_padrao_do_nexrh_tambem_e_aceito(): void
    {
        $caminho = sys_get_temp_dir().'/modelo.xlsx';
        $w = new Writer;
        $w->openToFile($caminho);
        $cargos = $w->getCurrentSheet();
        $cargos->setName('Cargos');
        $w->addRow(Row::fromValues(['denominacao *', 'simbolo *', 'natureza *', 'remuneracao *', 'quantidade_vagas *', 'base_legal *', 'observacao']));
        $w->addRow(Row::fromValues(['descrição']));
        $w->addRow(Row::fromValues(['Assessor Especial', 'cnes-iv', 'Cargo em comissão', '4472,58', 5, 'Lei nº 0.000', '']));
        $w->addNewSheetAndMakeItCurrent()->setName('Remanejamentos');
        $w->addRow(Row::fromValues(['denominacao *', 'simbolo *', 'quantidade *', 'sentido *', 'orgao *', 'decreto *', 'data_decreto *']));
        $w->addRow(Row::fromValues(['descrição']));
        $w->addRow(Row::fromValues(['Assessor Especial', 'CNES-IV', 2, 'Saída (SELC para outro órgão)', 'Órgão Exemplo', 'Decreto nº 1-E', '15/03/2024']));
        $w->close();

        $importacao = $this->enviar($caminho, 'modelo.xlsx');
        $this->assertSame('modelo_nexrh', $importacao->formato);
        $this->post("/importacoes/{$importacao->id}/aplicar");

        $cargo = Cargo::firstOrFail();
        $this->assertSame('CNES-IV', $cargo->simbolo);
        $this->assertSame('4472.58', $cargo->remuneracao);
        $this->assertSame(['remanejada' => 2, 'vaga' => 3], $cargo->vagas->countBy('situacao')->sortKeys()->all());
        $this->assertSame('Órgão Exemplo', $cargo->vagas->firstWhere('situacao', 'remanejada')->orgao_remanejamento);
    }

    public function test_importacao_de_outro_orgao_responde_404(): void
    {
        $importacao = $this->enviar();
        $outro = $this->usuario('rh', Orgao::factory()->create());

        $this->actingAs($outro)->get("/importacoes/{$importacao->id}")->assertNotFound();
        $this->actingAs($outro)->post("/importacoes/{$importacao->id}/aplicar")->assertNotFound();
    }
}
