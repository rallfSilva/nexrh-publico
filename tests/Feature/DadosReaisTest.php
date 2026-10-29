<?php

namespace Tests\Feature;

use App\Importacao\AnalisadorQuadroCargos;
use App\Importacao\LeitorPlanilha;
use App\Models\Cargo;
use App\Models\Importacao;
use App\Models\Orgao;
use App\Models\RegistroAuditoria;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/** Passagem da demonstração para os dados reais: limpar tudo e importar o quadro de cargos. */
class DadosReaisTest extends TestCase
{
    use RefreshDatabase;

    private const PLANILHA = __DIR__.'/../fixtures/estrutura-cargos-ficticia.xlsx';

    protected function setUp(): void
    {
        parent::setUp();
        $this->semearPerfis();
        Storage::fake('local');
    }

    public function test_limpar_pede_confirmacao(): void
    {
        $orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        Unidade::factory()->for($orgao)->create();

        $this->artisan('nexrh:limpar-demonstracao')->assertFailed();
        $this->assertSame(1, Unidade::withoutGlobalScopes()->count());
    }

    public function test_limpar_apaga_tudo_e_deixa_so_o_orgao_e_um_administrador_novo(): void
    {
        $orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        $unidade = Unidade::factory()->for($orgao)->create();
        Servidor::factory()->for($orgao)->create(['nome' => 'Pessoa Fictícia', 'unidade_id' => $unidade->id]);
        $this->usuario('rh', $orgao, ['email' => 'rh.selc@nexrh.local']);
        Storage::disk('local')->put('dossie/1/teste.bin', 'conteúdo fictício');
        $arquivo = tempnam(sys_get_temp_dir(), 'senha');

        $this->artisan('nexrh:limpar-demonstracao', ['--confirmar' => 'APAGAR', '--senha-em' => $arquivo])->assertSuccessful();

        $this->assertSame(0, Servidor::withoutGlobalScopes()->count());
        $this->assertSame(0, Unidade::withoutGlobalScopes()->count());
        $this->assertTrue(RegistroAuditoria::where('descricao', 'like', 'Dados de demonstração removidos%')->exists());
        $this->assertSame(['SELC'], Orgao::pluck('sigla')->all());
        $this->assertSame([], Storage::disk('local')->allFiles());

        $admin = User::sole();
        $this->assertSame('admin@nexrh.local', $admin->email);
        $this->assertTrue($admin->hasRole('administrador-geral'));
        $this->assertFalse(Hash::check('NexRH@demo2026', $admin->password));
        preg_match('/Senha: (\S+)/', file_get_contents($arquivo), $m);
        $this->assertTrue(Hash::check($m[1], $admin->password));
        unlink($arquivo);
    }

    public function test_importar_cargos_pela_linha_de_comando(): void
    {
        $orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        $this->usuario('administrador-geral', null);

        $this->artisan('nexrh:importar-cargos', ['arquivo' => self::PLANILHA, '--so-analisar' => true])->assertSuccessful();
        $this->assertSame(0, Cargo::withoutGlobalScopes()->count());
        $this->assertSame('analisada', Importacao::sole()->situacao);

        $this->artisan('nexrh:importar-cargos', ['arquivo' => self::PLANILHA])
            ->expectsOutputToContain('Quadro importado')
            ->doesntExpectOutputToContain('39053344705')
            ->assertSuccessful();
        $esperado = app(AnalisadorQuadroCargos::class)->analisar(self::PLANILHA)['resumo']['calculado']['cargos'];
        $this->assertSame($esperado, Cargo::withoutGlobalScopes()->where('orgao_id', $orgao->id)->count());
        $this->assertSame('aplicada', Importacao::latest('id')->first()->situacao);
    }

    public function test_planilha_com_uma_aba_por_mes_usa_a_mais_recente(): void
    {
        $linhas = (new LeitorPlanilha)->ler(self::PLANILHA);
        $aba = reset($linhas);
        $caminho = sys_get_temp_dir().'/estrutura-meses.xlsx';
        $w = new Writer;
        $w->openToFile($caminho);
        $w->getCurrentSheet()->setName('JANEIRO-2026');
        foreach (array_slice($aba, 0, 6) as $l) {
            $w->addRow(Row::fromValues(array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d/m/Y') : $v, $l)));
        }
        $w->addNewSheetAndMakeItCurrent()->setName('OUT-2026');
        foreach ($aba as $l) {
            $w->addRow(Row::fromValues(array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d/m/Y') : $v, $l)));
        }
        $w->close();

        $original = app(AnalisadorQuadroCargos::class)->analisar(self::PLANILHA);
        $r = app(AnalisadorQuadroCargos::class)->analisar($caminho);

        $this->assertSame($original['resumo']['calculado'], $r['resumo']['calculado']);
        $this->assertStringContainsString('Foi usada a última, "OUT-2026"', collect($r['divergencias'])->firstWhere('nivel', 'aviso')['mensagem']);
        unlink($caminho);
    }
}
