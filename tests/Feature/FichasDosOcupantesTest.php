<?php

namespace Tests\Feature;

use App\Importacao\FichasDosOcupantes;
use App\Models\Cargo;
use App\Models\Importacao;
use App\Models\Orgao;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\Vaga;
use App\Support\Cpf;
use Database\Factories\ServidorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Fichas criadas a partir dos ocupantes do quadro de cargos. */
class FichasDosOcupantesTest extends TestCase
{
    use RefreshDatabase;

    private const PLANILHA = __DIR__.'/../fixtures/estrutura-cargos-ficticia.xlsx';

    protected function setUp(): void
    {
        parent::setUp();
        $this->semearPerfis();
        Storage::fake('local');
    }

    public function test_cria_ficha_incompleta_para_cada_ocupante_com_cpf_valido(): void
    {
        $orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        $cargo = Cargo::factory()->for($orgao)->create();
        $cpf = ServidorFactory::cpf();
        $vaga = $this->vaga($cargo, 1, 'Pessoa Fictícia Um', $cpf, '100-P');
        $this->vaga($cargo, 2, 'Pessoa Fictícia Um', $cpf);
        $this->vaga($cargo, 3, 'Pessoa Fictícia Dois', '111.111.111-11');
        $this->vaga($cargo, 4, null, null, null, 'vaga');

        $r = app(FichasDosOcupantes::class)->criar($orgao->id);

        $this->assertSame(1, $r['criadas']);
        $this->assertCount(2, $r['ignoradas']);
        $s = Servidor::withoutGlobalScopes()->sole();
        $this->assertSame($vaga->id, $s->vaga_id);
        $this->assertSame($cpf, $s->cpf);
        $this->assertSame('100-P', $s->decreto_nomeacao);
        $this->assertFalse($s->fichaCompleta());
        $this->assertContains('Ficha incompleta', array_column($s->pendencias(), 'titulo'));

        // Rodar de novo não duplica.
        $this->assertSame(0, app(FichasDosOcupantes::class)->criar($orgao->id)['criadas']);
        $this->assertSame(1, Servidor::withoutGlobalScopes()->count());
    }

    public function test_quem_ja_tem_ficha_nao_muda_e_ganha_a_vaga_se_estiver_sem(): void
    {
        $orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        $cargo = Cargo::factory()->for($orgao)->create();
        $existente = Servidor::factory()->for($orgao)->create(['nome' => 'Nome Da Ficha', 'vaga_id' => null, 'unidade_id' => Unidade::factory()->for($orgao)]);
        $vaga = $this->vaga($cargo, 1, 'Nome Da Planilha', $existente->cpf);

        $r = app(FichasDosOcupantes::class)->criar($orgao->id);

        $this->assertSame(['criadas' => 0, 'vinculadas' => 1], array_slice($r, 0, 2));
        $existente->refresh();
        $this->assertSame('Nome Da Ficha', $existente->nome);
        $this->assertSame($vaga->id, $existente->vaga_id);
        $this->assertTrue($existente->fichaCompleta());
    }

    public function test_linha_de_comando_cria_as_fichas_com_a_opcao(): void
    {
        $orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        $this->usuario('administrador-geral', null);

        $this->artisan('nexrh:importar-cargos', ['arquivo' => self::PLANILHA, '--criar-fichas' => true])
            ->expectsOutputToContain('ficha(s) criada(s)')
            ->assertSuccessful();

        $ocupadas = Vaga::withoutGlobalScopes()->where('orgao_id', $orgao->id)->where('situacao', 'ocupada')->get();
        $validos = $ocupadas->map(fn ($v) => Cpf::somenteDigitos($v->ocupante_cpf))->filter(fn ($c) => Cpf::valido($c))->unique();
        $this->assertGreaterThan(0, $validos->count());
        $this->assertSame($validos->count(), Servidor::withoutGlobalScopes()->where('orgao_id', $orgao->id)->count());
        $this->assertSame(0, Servidor::withoutGlobalScopes()->whereNull('vaga_id')->count());
    }

    public function test_sem_a_opcao_so_grava_o_quadro(): void
    {
        Orgao::factory()->create(['sigla' => 'SELC']);
        $this->usuario('administrador-geral', null);

        $this->artisan('nexrh:importar-cargos', ['arquivo' => self::PLANILHA])->assertSuccessful();

        $this->assertSame(0, Servidor::withoutGlobalScopes()->count());
    }

    public function test_tela_cria_as_fichas_quando_marcado(): void
    {
        $orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        $rh = $this->usuario('rh', $orgao);

        $this->actingAs($rh)->post('/importacoes', ['tipo' => 'cargos', 'arquivo' => new UploadedFile(self::PLANILHA, 'estrutura.xlsx', null, null, true)])->assertRedirect();
        $importacao = Importacao::sole();
        $this->actingAs($rh)->get('/importacoes/'.$importacao->id)->assertOk()->assertSee('Criar também a ficha de cada ocupante');

        $this->actingAs($rh)->post('/importacoes/'.$importacao->id.'/aplicar', ['criar_fichas' => '1'])
            ->assertRedirect(route('servidores.index'))
            ->assertSessionHas('sucesso', fn ($m) => str_contains($m, 'ficha(s) criada(s)'));
        $this->assertGreaterThan(0, Servidor::withoutGlobalScopes()->count());
        $this->actingAs($rh)->get('/servidores')->assertOk();
        $this->actingAs($rh)->get('/servidores/'.Servidor::withoutGlobalScopes()->first()->id)->assertOk()->assertSee('Ficha incompleta');
    }

    public function test_falha_ao_gravar_o_arquivo_mostra_mensagem_e_nao_erro_500(): void
    {
        $orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        $rh = $this->usuario('rh', $orgao);
        $arquivo = new class(self::PLANILHA, 'estrutura.xlsx', null, null, true) extends UploadedFile
        {
            public function storeAs($path, $name = null, $options = []): string|false
            {
                return false;
            }
        };

        $this->actingAs($rh)->from('/importacoes/nova')->post('/importacoes', ['tipo' => 'cargos', 'arquivo' => $arquivo])
            ->assertRedirect('/importacoes/nova')
            ->assertSessionHasErrors(['arquivo' => 'O sistema não conseguiu guardar o arquivo no servidor. Peça à TI para conferir as permissões da pasta storage (Guia da TI, Problemas comuns).']);
        $this->assertSame(0, Importacao::count());
    }

    private function vaga(Cargo $cargo, int $numero, ?string $nome, ?string $cpf, ?string $ato = null, string $situacao = 'ocupada'): Vaga
    {
        return Vaga::withoutGlobalScopes()->create([
            'orgao_id' => $cargo->orgao_id, 'cargo_id' => $cargo->id, 'numero' => $numero, 'situacao' => $situacao,
            'ocupante_nome' => $nome, 'ocupante_cpf' => $cpf, 'ato_numero' => $ato,
        ]);
    }
}
