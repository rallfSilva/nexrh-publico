<?php

namespace Tests\Feature;

use App\Importacao\AnalisadorQuadroCargos;
use App\Importacao\AplicadorQuadroCargos;
use App\Models\Declaracao;
use App\Models\Importacao;
use App\Models\Orgao;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Critério de aceite: o exemplo fictício do modelo 03 entra inteiro, com as linhas
 * erradas de fora, cada servidor na vaga do quadro e sem duplicar ao reimportar.
 */
class ImportacaoServidoresTest extends TestCase
{
    use RefreshDatabase;

    private const CARGOS = __DIR__.'/../fixtures/estrutura-cargos-ficticia.xlsx';

    private const SERVIDORES = __DIR__.'/../fixtures/servidores-ficticios.xlsx';

    private Orgao $orgao;

    private User $rh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->semearPerfis();
        Storage::fake('local');
        $this->orgao = Orgao::factory()->create(['sigla' => 'SELC']);

        foreach (['GAB', 'ASJUR', 'COAM', 'DTI', 'DRH', 'DAC'] as $sigla) {
            Unidade::factory()->for($this->orgao)->create(['sigla' => $sigla]);
        }

        $this->rh = $this->usuario('rh', $this->orgao);
        $this->actingAs($this->rh);
        $quadro = (new AnalisadorQuadroCargos)->analisar(self::CARGOS);
        $importacao = Importacao::create([
            'orgao_id' => $this->orgao->id, 'user_id' => $this->rh->id, 'tipo' => 'cargos', 'formato' => $quadro['formato'],
            'arquivo_nome' => 'cargos.xlsx', 'arquivo_caminho' => 'x', 'arquivo_hash' => 'x', 'situacao' => 'analisada',
            'resumo' => $quadro['resumo'], 'divergencias' => $quadro['divergencias'], 'dados' => ['cargos' => $quadro['cargos']],
        ]);
        app(AplicadorQuadroCargos::class)->aplicar($importacao);
    }

    private function enviar(?User $quem = null): Importacao
    {
        $this->actingAs($quem ?? $this->rh)
            ->post('/importacoes', ['tipo' => 'servidores', 'arquivo' => new UploadedFile(self::SERVIDORES, 'servidores.xlsx', null, null, true)])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return Importacao::latest('id')->firstOrFail();
    }

    public function test_analise_separa_as_linhas_com_erro(): void
    {
        $importacao = $this->enviar();

        $this->assertSame('servidores', $importacao->tipo);
        $this->assertSame('analisada', $importacao->situacao);
        $this->assertSame(94, $importacao->resumo['calculado']['validas']);
        $this->assertSame(5, $importacao->resumo['calculado']['com_erro']);
        $this->assertCount(5, $importacao->divergenciasDoNivel('erro'));
        $this->assertSame(0, Servidor::count(), 'nada é gravado antes de confirmar');

        $this->get("/importacoes/{$importacao->id}")->assertOk()->assertSee('Prévia: 94 servidores');
    }

    public function test_aplicar_grava_servidores_vagas_e_declaracoes(): void
    {
        $importacao = $this->enviar();
        $this->post("/importacoes/{$importacao->id}/aplicar")->assertRedirect('/servidores');

        $this->assertSame(94, Servidor::count());
        $this->assertSame(267, Declaracao::count());
        $this->assertNull($importacao->refresh()->dados);

        $ana = Servidor::where('nome', 'Ana Beatriz Martins')->firstOrFail();
        $this->assertSame('Agente de Contratação', $ana->vaga->cargo->denominacao);
        $this->assertSame('ocupada', $ana->vaga->situacao);
        $this->assertSame($ana->cpf, $ana->vaga->ocupante_cpf);

        $vagas = Servidor::whereNotNull('vaga_id')->pluck('vaga_id');
        $this->assertSame($vagas->count(), $vagas->unique()->count(), 'dois servidores na mesma vaga');
    }

    public function test_reimportar_atualiza_sem_duplicar(): void
    {
        $this->post('/importacoes/'.$this->enviar()->id.'/aplicar');
        $vagas = Servidor::orderBy('id')->pluck('vaga_id', 'id');

        $segunda = $this->enviar();
        $this->assertSame(0, $segunda->resumo['calculado']['novos']);
        $this->assertSame(94, $segunda->resumo['calculado']['atualizados']);
        $this->post("/importacoes/{$segunda->id}/aplicar");

        $this->assertSame(94, Servidor::count());
        $this->assertSame(267, Declaracao::count());
        $this->assertEquals($vagas, Servidor::orderBy('id')->pluck('vaga_id', 'id'));
    }

    public function test_relatorio_nunca_mostra_cpf_completo(): void
    {
        $importacao = $this->enviar();
        $html = $this->get("/importacoes/{$importacao->id}")->getContent();
        $mensagens = collect($importacao->divergencias)->pluck('mensagem')->implode(' ');

        foreach ($importacao->dados['servidores'] as $linha) {
            $cpf = $linha['dados']['cpf'];
            $this->assertStringNotContainsString($cpf, $html);
            $this->assertStringNotContainsString($cpf, $mensagens);
        }
    }

    public function test_quem_nao_gerencia_servidores_nao_importa(): void
    {
        $auditor = $this->usuario('auditor', $this->orgao);

        $this->actingAs($auditor)->get('/importacoes/nova?tipo=servidores')->assertForbidden();
        $this->actingAs($auditor)
            ->post('/importacoes', ['tipo' => 'servidores', 'arquivo' => new UploadedFile(self::SERVIDORES, 'servidores.xlsx', null, null, true)])
            ->assertForbidden();

        $importacao = $this->enviar();
        $this->actingAs($auditor)->get("/importacoes/{$importacao->id}")->assertForbidden();
    }
}
