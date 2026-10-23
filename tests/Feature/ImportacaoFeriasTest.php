<?php

namespace Tests\Feature;

use App\Importacao\AnalisadorQuadroCargos;
use App\Importacao\AnalisadorServidores;
use App\Importacao\AplicadorQuadroCargos;
use App\Importacao\AplicadorServidores;
use App\Models\Afastamento;
use App\Models\Ferias;
use App\Models\Importacao;
use App\Models\Orgao;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\User;
use App\Support\Cpf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Os exemplos fictícios dos modelos 05 e 06 entram com as linhas erradas de fora:
 * afastamentos primeiro (as férias desviam deles), depois as férias, conferidas
 * contra o saldo, as outras etapas e o mínimo de presentes de cada setor.
 * Datas fixas: o exemplo foi montado para 22/10/2026.
 */
class ImportacaoFeriasTest extends TestCase
{
    use RefreshDatabase;

    private const CARGOS = __DIR__.'/../fixtures/estrutura-cargos-ficticia.xlsx';

    private const SERVIDORES = __DIR__.'/../fixtures/servidores-ficticios.xlsx';

    private const FERIAS = __DIR__.'/../fixtures/ferias-ficticias.xlsx';

    private const AFASTAMENTOS = __DIR__.'/../fixtures/afastamentos-ficticios.xlsx';

    /** Mínimos de presentes do exemplo, os mesmos da demonstração. */
    private const MINIMOS = ['GAB' => 7, 'ASJUR' => 6, 'COAM' => 16, 'DTI' => 11, 'DRH' => 5, 'DAC' => 22];

    private Orgao $orgao;

    private User $rh;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-22 10:00:00');
        $this->semearPerfis();
        Storage::fake('local');
        $this->orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        foreach (self::MINIMOS as $sigla => $minimo) {
            Unidade::factory()->for($this->orgao)->create(['sigla' => $sigla, 'minimo_presentes_ferias' => $minimo]);
        }
        $this->rh = $this->usuario('rh', $this->orgao);
        $this->actingAs($this->rh);

        $this->aplicar('cargos', (new AnalisadorQuadroCargos)->analisar(self::CARGOS), AplicadorQuadroCargos::class);
        $this->aplicar('servidores', (new AnalisadorServidores)->analisar(self::SERVIDORES, $this->orgao->id), AplicadorServidores::class);
    }

    private function aplicar(string $tipo, array $r, string $aplicador): void
    {
        app($aplicador)->aplicar(Importacao::create([
            'orgao_id' => $this->orgao->id, 'user_id' => $this->rh->id, 'tipo' => $tipo, 'formato' => $r['formato'],
            'arquivo_nome' => "{$tipo}.xlsx", 'arquivo_caminho' => 'x', 'arquivo_hash' => 'x', 'situacao' => 'analisada',
            'resumo' => $r['resumo'], 'divergencias' => $r['divergencias'], 'dados' => [$tipo => $r[$tipo]],
        ]));
    }

    private function enviar(string $tipo, ?User $quem = null): Importacao
    {
        $arquivo = $tipo === 'ferias' ? self::FERIAS : self::AFASTAMENTOS;
        $this->actingAs($quem ?? $this->rh)
            ->post('/importacoes', ['tipo' => $tipo, 'arquivo' => new UploadedFile($arquivo, "{$tipo}.xlsx", null, null, true)])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return Importacao::latest('id')->firstOrFail();
    }

    private function importarAfastamentos(): void
    {
        $this->post('/importacoes/'.$this->enviar('afastamentos')->id.'/aplicar')->assertRedirect('/ferias');
    }

    public function test_afastamentos_separam_as_tres_linhas_erradas(): void
    {
        $importacao = $this->enviar('afastamentos');

        $this->assertSame('modelo_afastamentos', $importacao->formato);
        $this->assertSame(14, $importacao->resumo['calculado']['validas']);
        $this->assertSame(3, $importacao->resumo['calculado']['com_erro']);
        $this->assertSame(0, Afastamento::count());

        $this->post("/importacoes/{$importacao->id}/aplicar")->assertRedirect('/ferias');
        $this->assertSame(14, Afastamento::count());
    }

    public function test_ferias_separam_as_cinco_linhas_erradas(): void
    {
        $this->importarAfastamentos();
        $importacao = $this->enviar('ferias');

        $this->assertSame('modelo_ferias', $importacao->formato);
        $this->assertSame(5, $importacao->resumo['calculado']['com_erro']);
        $this->assertSame(397, $importacao->resumo['calculado']['validas']);

        $erros = collect($importacao->divergenciasDoNivel('erro'))->pluck('mensagem')->implode(' | ');
        $this->assertStringContainsString('nenhum servidor do órgão tem o CPF', $erros);
        $this->assertStringContainsString('o setor DTI ficaria com 10 pessoas em 18/01/2027 (mínimo: 11)', $erros);
        $this->assertStringContainsString('coincide com férias de', $erros);
        $this->assertStringContainsString('passa do saldo do período', $erros);
        $this->assertStringContainsString('não confere com os do servidor', $erros);

        $avisos = collect($importacao->divergenciasDoNivel('aviso'))->pluck('mensagem')->implode(' | ');
        $this->assertStringContainsString('Os períodos aquisitivos passam a contar da data da planilha', $avisos);

        $this->get("/importacoes/{$importacao->id}")->assertOk()->assertSee('Prévia');
    }

    public function test_aplicar_grava_periodos_e_reimportar_nao_duplica(): void
    {
        $this->importarAfastamentos();
        $this->post('/importacoes/'.$this->enviar('ferias')->id.'/aplicar')->assertRedirect('/ferias');

        $this->assertSame(397, Ferias::count());
        $ana = Servidor::where('cpf_hash', Cpf::hash('52998224725'))->firstOrFail();
        $this->assertSame('2022-03-14', $ana->periodosAquisitivos()->first()->inicio->format('Y-m-d'));
        $this->assertTrue(Ferias::where('situacao', 'interrompida')->exists());
        $this->assertTrue(Ferias::where('situacao', 'cancelada')->exists());
        $this->assertSame(3, Ferias::where('situacao', 'programada')->count());

        // Ninguém do setor abaixo do mínimo em dia nenhum dali para frente.
        $dti = Unidade::where('sigla', 'DTI')->firstOrFail();
        $this->get('/ferias?unidade='.$dti->id.'&mes=2027-01')->assertOk()->assertDontSee('class="baixo', false);

        $segunda = $this->enviar('ferias');
        $this->assertSame(0, $segunda->resumo['calculado']['novos']);
        $this->post("/importacoes/{$segunda->id}/aplicar");
        $this->assertSame(397, Ferias::count());
    }

    public function test_relatorio_nunca_mostra_cpf_completo(): void
    {
        $this->importarAfastamentos();
        $importacao = $this->enviar('ferias');
        $html = $this->get("/importacoes/{$importacao->id}")->getContent();
        $mensagens = collect($importacao->divergencias)->pluck('mensagem')->implode(' ');

        $this->assertDoesNotMatchRegularExpression('/\d{3}\.\d{3}\.\d{3}-\d{2}/', $html.$mensagens);
        $this->assertStringNotContainsString('52998224725', $html);
    }

    public function test_so_quem_gerencia_ferias_importa(): void
    {
        $this->actingAs($this->usuario('auditor', $this->orgao))->get('/importacoes/nova?tipo=ferias')->assertForbidden();
        $this->actingAs($this->usuario('chefia', $this->orgao))->get('/importacoes/nova?tipo=afastamentos')->assertForbidden();
    }
}
