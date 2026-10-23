<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Justificativas de ocorrências do ponto (falta, atraso, saída antecipada, registro
 * esquecido): o servidor justifica com motivo e anexo, a chefia abona ou recusa.
 * O RH também abona direto. Nada é apagado: o servidor retira o pedido em análise.
 *
 * Também traz as respostas da SELC ao escopo da frequência: tolerância de 15 minutos
 * e dispensa do ponto pelo cargo (secretário, adjunto e coordenadores).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('justificativas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos')->restrictOnDelete();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->date('inicio');
            $table->date('fim');
            $table->string('tipo', 30); // atestado, declaracao, servico_externo, esquecimento, outro
            $table->string('ocorrencia', 255)->nullable(); // o que o espelho apontava no envio
            $table->string('horarios', 60)->nullable(); // horário informado em esquecimento de registro
            $table->text('motivo');
            $table->string('situacao', 20); // pendente, abonada, recusada ou retirada
            $table->string('arquivo_caminho')->nullable();
            $table->string('arquivo_nome')->nullable();
            $table->string('arquivo_mime', 60)->nullable();
            $table->unsignedInteger('arquivo_tamanho')->nullable();
            $table->string('arquivo_hash', 64)->nullable();
            $table->foreignId('enviado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('enviado_pelo_servidor')->default(true);
            $table->foreignId('analisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('analisado_em')->nullable();
            $table->string('motivo_decisao', 255)->nullable();
            $table->timestamps();

            $table->index(['servidor_id', 'inicio']);
            $table->index(['orgao_id', 'situacao']);
        });

        Schema::table('regras_frequencia', function (Blueprint $table) {
            $table->json('cargos_dispensados')->nullable()->after('redes_permitidas');
            $table->unsignedSmallInteger('tolerancia_minutos')->default(15)->change();
        });

        // A tolerância de 10 minutos era provisória; a SELC informou 15.
        DB::table('regras_frequencia')->where('tolerancia_minutos', 10)->update(['tolerancia_minutos' => 15]);
    }

    public function down(): void
    {
        Schema::dropIfExists('justificativas');
        Schema::table('regras_frequencia', function (Blueprint $table) {
            $table->dropColumn('cargos_dispensados');
            $table->unsignedSmallInteger('tolerancia_minutos')->default(10)->change();
        });
    }
};
