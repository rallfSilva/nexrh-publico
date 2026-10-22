<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Orgao;
use App\Models\RegistroAuditoria;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\User;
use App\Servidores\ArquivoDossie;
use Database\Factories\ServidorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DossieTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n% documento ficticio de teste\n%%EOF\n";

    private Orgao $orgao;

    private Servidor $servidor;

    private User $rh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->semearPerfis();
        Storage::fake('local');
        $this->orgao = Orgao::factory()->create(['sigla' => 'SELC']);
        $this->servidor = Servidor::factory()->for($this->orgao)->create(['nome' => 'Joana Fictícia Lima']);
        $this->rh = $this->usuario('rh', $this->orgao);
    }

    private function pdf(string $nome = 'rg.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nome, self::PDF);
    }

    private function incluir(array $extra = [], ?User $quem = null): Documento
    {
        $this->actingAs($quem ?? $this->rh)
            ->post("/servidores/{$this->servidor->id}/documentos", ['tipo' => 'rg', 'arquivo' => $this->pdf(), ...$extra])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return Documento::latest('id')->firstOrFail();
    }

    /** Servidor com acesso ao sistema, ligado pelo CPF. */
    private function servidorComAcesso(): User
    {
        $cpf = ServidorFactory::cpf();
        $user = $this->usuario('servidor', $this->orgao, ['cpf' => $cpf]);
        $this->servidor->update(['cpf' => $cpf, 'user_id' => $user->id]);

        return $user;
    }

    public function test_rh_inclui_documento_validado_e_criptografado(): void
    {
        $doc = $this->incluir(['data_documento' => '2026-06-20']);

        $this->assertSame('identificacao', $doc->categoria);
        $this->assertSame('validado', $doc->situacao);
        $this->assertSame($this->rh->id, $doc->analisado_por);
        $this->assertSame('rg.pdf', $doc->arquivo_nome);
        $this->assertSame(hash('sha256', self::PDF), $doc->arquivo_hash);

        $noDisco = Storage::disk('local')->get($doc->arquivo_caminho);
        $this->assertStringNotContainsString('%PDF', $noDisco, 'o arquivo deve ficar cifrado no disco');

        $resposta = $this->get("/servidores/{$this->servidor->id}/documentos/{$doc->id}/arquivo?baixar=1")->assertOk();
        $this->assertSame(self::PDF, $resposta->getContent());
        $this->assertSame('application/pdf', $resposta->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment;', $resposta->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $resposta->headers->get('X-Content-Type-Options'));

        $this->get("/servidores/{$this->servidor->id}/documentos/{$doc->id}/arquivo")->assertOk()->assertHeader('Content-Disposition', 'inline; filename="rg.pdf"; filename*=UTF-8\'\'rg.pdf');

        $eventos = RegistroAuditoria::withoutGlobalScopes()->where('auditable_type', $doc->getMorphClass())->where('auditable_id', $doc->id)->pluck('evento')->all();
        $this->assertEqualsCanonicalizing(['criado', 'download', 'acesso'], $eventos);
    }

    public function test_arquivo_disfarcado_ou_grande_demais_e_recusado(): void
    {
        $this->actingAs($this->rh);
        $url = "/servidores/{$this->servidor->id}/documentos";

        $html = UploadedFile::fake()->createWithContent('rg.pdf', '<html><script>alert(1)</script></html>');
        $this->post($url, ['tipo' => 'rg', 'arquivo' => $html])->assertSessionHasErrors(['arquivo' => 'Envie PDF, JPG ou PNG.']);

        $grande = UploadedFile::fake()->create('rg.pdf', Documento::TAMANHO_MAXIMO_KB + 1, 'application/pdf');
        $this->post($url, ['tipo' => 'rg', 'arquivo' => $grande])->assertSessionHasErrors('arquivo');

        $this->post($url, ['tipo' => 'certificado_curso', 'arquivo' => $this->pdf()])->assertSessionHasErrors('descricao');
        $this->post($url, ['tipo' => 'nao_existe', 'arquivo' => $this->pdf()])->assertSessionHasErrors('tipo');
        $this->post($url, ['tipo' => 'cnh', 'data_documento' => '2026-01-10', 'validade' => '2025-01-10', 'arquivo' => $this->pdf()])->assertSessionHasErrors('validade');

        $this->assertSame(0, Documento::count());
    }

    public function test_nome_do_arquivo_e_limpo(): void
    {
        $doc = $this->incluir(['arquivo' => UploadedFile::fake()->createWithContent('../../etc/"evil"<x>.php.pdf', self::PDF)]);

        $this->assertDoesNotMatchRegularExpression('#[/\\\\"<>]#', $doc->arquivo_nome);
        $this->assertStringEndsWith('.pdf', $doc->arquivo_nome);
    }

    public function test_servidor_envia_e_rh_valida_ou_recusa(): void
    {
        $user = $this->servidorComAcesso();

        $this->actingAs($user)->post('/minha-ficha/documentos', ['tipo' => 'comprovante_residencia', 'arquivo' => $this->pdf('conta.pdf')])
            ->assertRedirect('/minha-ficha?aba=dossie');
        $enviado = Documento::latest('id')->firstOrFail();
        $this->assertSame('pendente', $enviado->situacao);
        $this->assertTrue($enviado->enviado_pelo_servidor);

        // Atos funcionais são do órgão: o servidor não inclui.
        $this->post('/minha-ficha/documentos', ['tipo' => 'decreto_nomeacao', 'arquivo' => $this->pdf()])->assertSessionHasErrors('tipo');

        $this->get('/minha-ficha?aba=dossie')->assertOk()->assertSee('Comprovante de residência')->assertSee('Aguardando validação')->assertDontSee('Validar</button>', false);
        $this->get("/minha-ficha/documentos/{$enviado->id}/arquivo")->assertOk();

        // Fila do RH
        $this->actingAs($this->rh)->get('/documentos/a-validar')->assertOk()->assertSee('Comprovante de residência')->assertSee('Joana Fictícia Lima');
        $this->post("/servidores/{$this->servidor->id}/documentos/{$enviado->id}/recusar", [])->assertSessionHasErrors('motivo');
        $this->post("/servidores/{$this->servidor->id}/documentos/{$enviado->id}/recusar", ['motivo' => 'Imagem ilegível.', 'voltar' => 'fila'])
            ->assertRedirect('/documentos/a-validar');
        $this->assertSame('recusado', $enviado->refresh()->situacao);
        $this->post("/servidores/{$this->servidor->id}/documentos/{$enviado->id}/validar")->assertStatus(409);

        $this->actingAs($user)->get('/minha-ficha')->assertSee('Documento recusado: Comprovante de residência')->assertSee('Imagem ilegível.');

        $this->post('/minha-ficha/documentos', ['tipo' => 'comprovante_residencia', 'arquivo' => $this->pdf('conta2.pdf')]);
        $novo = Documento::latest('id')->firstOrFail();
        $this->actingAs($this->rh)->post("/servidores/{$this->servidor->id}/documentos/{$novo->id}/validar")->assertRedirect();
        $this->assertSame('validado', $novo->refresh()->situacao);
        $this->assertSame($this->rh->id, $novo->analisado_por);
        $this->get('/documentos/a-validar')->assertSee('Nada para validar.');
    }

    public function test_nada_e_apagado_so_arquivado_com_motivo(): void
    {
        $doc = $this->incluir();
        $this->actingAs($this->rh);
        $base = "/servidores/{$this->servidor->id}/documentos/{$doc->id}";

        $this->delete($base)->assertNotFound(); // não existe rota de exclusão
        $this->post("{$base}/arquivar", [])->assertSessionHasErrors('motivo');
        $this->post("{$base}/arquivar", ['motivo' => 'Substituído por versão nova.'])->assertRedirect();

        $this->assertSame('arquivado', $doc->refresh()->situacao);
        $this->assertSame('Substituído por versão nova.', $doc->motivo);
        $this->assertTrue(Storage::disk('local')->exists($doc->arquivo_caminho));
        $this->get("/servidores/{$this->servidor->id}?aba=dossie")->assertSee('Arquivados (1)')->assertSee('Substituído por versão nova.');
    }

    public function test_quem_nao_pode_ver_o_dossie(): void
    {
        $doc = $this->incluir();
        $arquivo = "/servidores/{$this->servidor->id}/documentos/{$doc->id}/arquivo";

        $auditor = $this->usuario('auditor', $this->orgao);
        $this->actingAs($auditor)->get($arquivo)->assertForbidden();
        $this->get("/servidores/{$this->servidor->id}")->assertOk()->assertDontSee('Carteira de identidade (RG)')->assertDontSee('data-aba="dossie"', false);
        $this->get('/documentos/a-validar')->assertForbidden();

        $unidade = Unidade::factory()->for($this->orgao)->create();
        $this->servidor->update(['unidade_id' => $unidade->id]);
        $chefe = $this->usuario('chefia', $this->orgao);
        $unidade->update(['chefe_user_id' => $chefe->id]);
        $this->actingAs($chefe)->get($arquivo)->assertForbidden();

        // RH de outro órgão: o servidor nem existe para ele.
        $this->actingAs($this->usuario('rh', Orgao::factory()->create()))->get($arquivo)->assertNotFound();

        // Outro servidor não abre documento que não é dele pela Minha ficha.
        $outro = Servidor::factory()->for($this->orgao)->create(['cpf' => $cpf = ServidorFactory::cpf()]);
        $this->actingAs($this->usuario('servidor', $this->orgao, ['cpf' => $cpf]));
        $this->assertSame($outro->id, auth()->user()->servidor?->id);
        $this->get("/minha-ficha/documentos/{$doc->id}/arquivo")->assertNotFound();
    }

    public function test_documento_de_outro_servidor_na_url_responde_404(): void
    {
        $doc = $this->incluir();
        $outro = Servidor::factory()->for($this->orgao)->create();

        $this->actingAs($this->rh)->get("/servidores/{$outro->id}/documentos/{$doc->id}/arquivo")->assertNotFound();
        $this->post("/servidores/{$outro->id}/documentos/{$doc->id}/arquivar", ['motivo' => 'x'])->assertNotFound();
    }

    public function test_arquivo_alterado_no_disco_nao_e_entregue(): void
    {
        $doc = $this->incluir();
        Storage::disk('local')->put($doc->arquivo_caminho, encrypt('outro conteúdo', false));

        $this->expectException(RuntimeException::class);
        app(ArquivoDossie::class)->conteudo($doc);
    }

    public function test_pendencias_da_posse_e_vencimento(): void
    {
        $this->actingAs($this->rh)->get("/servidores/{$this->servidor->id}")->assertSee('Dossiê da posse incompleto')->assertSee('10 de 10 documentos faltando', false);

        $this->incluir(['tipo' => 'cnh', 'data_documento' => '2020-01-10', 'validade' => '2025-01-10']);
        $this->get("/servidores/{$this->servidor->id}")->assertSee('Carteira de motorista (CNH) vencido')->assertSee('Vencido em 10/01/2025');
    }
}
