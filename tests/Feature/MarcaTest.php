<?php

namespace Tests\Feature;

use App\Models\Orgao;
use App\Support\Marca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarcaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->semearPerfis();
    }

    public function test_arquivos_da_logo_existem(): void
    {
        foreach (['favicon.ico', 'favicon.svg', 'apple-touch-icon.png', Marca::SIMBOLO, Marca::SIMBOLO_PNG, 'img/marca/logo-horizontal.svg', 'img/marca/simbolo-contorno.svg'] as $arquivo) {
            $this->assertFileExists(public_path($arquivo));
            $this->assertGreaterThan(0, filesize(public_path($arquivo)), $arquivo);
        }
    }

    public function test_login_mostra_a_logo_e_o_favicon(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('img/marca/simbolo.svg')
            ->assertSee('favicon.svg')
            ->assertSee('apple-touch-icon.png');
    }

    public function test_telas_internas_tem_a_logo_e_o_rodape_de_impressao(): void
    {
        $user = $this->usuario('rh', Orgao::factory()->create(), ['name' => "Joana D'Ávila Teste"]);

        $html = $this->actingAs($user)->get('/painel')->assertOk()
            ->assertSee('img/marca/simbolo.svg')
            ->assertSee('@bottom-left', false)
            ->getContent();

        // Nome com apóstrofo vira escape CSS, sem quebrar a string nem o HTML.
        $this->assertStringContainsString('Joana D\\27 Ávila Teste', $html);
    }

    public function test_texto_css_escapa_o_que_pode_fechar_a_string_ou_o_estilo(): void
    {
        $this->assertSame('"a\\22  b\\3c /style\\3e "', Marca::textoCss('a" b</style>'));
        $this->assertSame('"NexRH Público · 30/10/2026"', Marca::textoCss('NexRH Público · 30/10/2026'));
    }

    public function test_simbolo_para_pdf_e_png_embutido(): void
    {
        $this->assertStringStartsWith('data:image/png;base64,iVBORw0KGgo', Marca::simboloPng());
        $this->assertStringStartsWith('data:image/svg+xml;base64,', Marca::simboloSvg(12));
        $this->assertStringContainsString('width="12" height="12"', base64_decode(substr(Marca::simboloSvg(12), 26)));
    }
}
