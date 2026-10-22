<?php

namespace Tests\Feature;

use App\Models\Orgao;
use Database\Seeders\PerfisSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelosImportacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_todos_os_modelos_existem_e_podem_ser_baixados(): void
    {
        $this->seed(PerfisSeeder::class);
        $user = $this->usuario('rh', Orgao::factory()->create());

        foreach (array_keys(config('nexrh.modelos_importacao')) as $arquivo) {
            $this->assertFileExists(resource_path('modelos-importacao/'.$arquivo));
            $this->actingAs($user)->get('/modelos-importacao/'.$arquivo)->assertOk()->assertDownload($arquivo);
        }

        $this->assertDatabaseHas('registros_auditoria', ['evento' => 'download', 'user_id' => $user->id]);
    }

    public function test_nao_baixa_arquivo_fora_da_lista(): void
    {
        $this->seed(PerfisSeeder::class);
        $user = $this->usuario('rh', Orgao::factory()->create());

        $this->actingAs($user)->get('/modelos-importacao/qualquer.xlsx')->assertNotFound();
        $this->actingAs($user)->get('/modelos-importacao/..%2F..%2F.env')->assertNotFound();
    }
}
