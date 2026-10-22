<?php

namespace Tests\Feature;

use App\Importacao\AnalisadorQuadroCargos;
use App\Importacao\AnalisadorServidores;
use App\Importacao\AplicadorQuadroCargos;
use App\Importacao\AplicadorServidores;
use App\Models\Dependente;
use App\Models\Importacao;
use App\Models\Orgao;
use App\Models\Unidade;
use App\Models\User;
use App\Support\Cpf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * O exemplo fictício do modelo 04 entra com as linhas erradas de fora, os limites de
 * idade como aviso e sem duplicar ao reimportar. Datas fixas: as idades do exemplo
 * foram calculadas para 22/10/2026.
 */
class ImportacaoDependentesTest extends TestCase
{
    use RefreshDatabase;

    private const CARGOS = __DIR__.'/../fixtures/estrutura-cargos-ficticia.xlsx';

    private const SERVIDORES = __DIR__.'/../fixtures/servidores-ficticios.xlsx';

    private const DEPENDENTES = __DIR__.'/../fixtures/dependentes-ficticios.xlsx';

    private Orgao $orgao;

    private User $rh;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-22 10:00:00');
        $this->semearPerfis();
        Storage::fake('local');
        $this->orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        foreach (['GAB', 'ASJUR', 'COAM', 'DTI', 'DRH', 'DAC'] as $sigla) {
            Unidade::factory()->for($this->orgao)->create(['sigla' => $sigla]);
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

    private function enviar(?User $quem = null): Importacao
    {
        $this->actingAs($quem ?? $this->rh)
            ->post('/importacoes', ['tipo' => 'dependentes', 'arquivo' => new UploadedFile(self::DEPENDENTES, 'dependentes.xlsx', null, null, true)])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return Importacao::latest('id')->firstOrFail();
    }

    public function test_analise_separa_erros_e_avisos(): void
    {
        $importacao = $this->enviar();

        $this->assertSame('modelo_dependentes', $importacao->formato);
        $this->assertSame(67, $importacao->resumo['calculado']['validas']);
        $this->assertSame(5, $importacao->resumo['calculado']['com_erro']);
        $this->assertSame(0, Dependente::count());

        $erros = collect($importacao->divergenciasDoNivel('erro'))->pluck('mensagem')->implode(' | ');
        $this->assertStringContainsString('nenhum servidor do órgão tem o CPF', $erros);
        $this->assertStringContainsString('CPF inválido', $erros);
        $this->assertStringContainsString('já tem cônjuge ou companheiro(a) na linha', $erros);
        $this->assertStringContainsString('"data_nascimento"', $erros);
        $this->assertStringContainsString('O salário-família é só para filho', $erros);

        $avisos = collect($importacao->divergenciasDoNivel('aviso'))->pluck('mensagem')->implode(' | ');
        $this->assertStringContainsString('Tem 23 anos: passou do limite de 21 anos para o imposto de renda', $avisos);
        $this->assertStringContainsString('Tem 16 anos: o salário-família vai só até os 14 anos', $avisos);

        $this->get("/importacoes/{$importacao->id}")->assertOk()->assertSee('Prévia: 67 dependentes');
    }

    public function test_aplicar_e_reimportar_sem_duplicar(): void
    {
        $this->post('/importacoes/'.$this->enviar()->id.'/aplicar')->assertRedirect('/servidores');

        $this->assertSame(67, Dependente::count());
        $ana = Dependente::whereHas('servidor', fn ($q) => $q->where('cpf_hash', Cpf::hash('52998224725')))->orderBy('nome')->get();
        $this->assertSame(['Clara Martins Rocha', 'Pedro Martins Rocha'], $ana->pluck('nome')->all());
        $this->assertTrue($ana[0]->universitario);
        $this->assertSame(['Imposto de renda', 'Previdência', 'Salário-família'], $ana[1]->finalidades());

        $segunda = $this->enviar();
        $this->assertSame(0, $segunda->resumo['calculado']['novos']);
        $this->assertSame(67, $segunda->resumo['calculado']['atualizados']);
        $this->post("/importacoes/{$segunda->id}/aplicar");
        $this->assertSame(67, Dependente::count());
    }

    public function test_relatorio_nunca_mostra_cpf_completo(): void
    {
        $importacao = $this->enviar();
        $html = $this->get("/importacoes/{$importacao->id}")->getContent();
        $mensagens = collect($importacao->divergencias)->pluck('mensagem')->implode(' ');

        $this->assertDoesNotMatchRegularExpression('/\d{3}\.\d{3}\.\d{3}-\d{2}/', $html.$mensagens);
        foreach ($importacao->dados['dependentes'] as $linha) {
            $this->assertStringNotContainsString($linha['dados']['cpf'], $html);
        }
    }

    public function test_so_quem_gerencia_o_dossie_importa_dependentes(): void
    {
        $auditor = $this->usuario('auditor', $this->orgao);
        $this->actingAs($auditor)->get('/importacoes/nova?tipo=dependentes')->assertForbidden();

        // Quem só gerencia cargos não vê a importação de dependentes.
        $importacao = $this->enviar();
        $cargos = User::factory()->create(['orgao_id' => $this->orgao->id]);
        $cargos->givePermissionTo('cargos.gerenciar');
        $this->actingAs($cargos)->get("/importacoes/{$importacao->id}")->assertNotFound();
        $this->post('/importacoes', ['tipo' => 'dependentes', 'arquivo' => new UploadedFile(self::DEPENDENTES, 'dependentes.xlsx', null, null, true)])
            ->assertSessionHasErrors('tipo');
    }
}
