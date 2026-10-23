<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Férias: regras de cada órgão, períodos aquisitivos, etapas de férias
 * (cada linha é uma etapa) e licenças e afastamentos, que também tiram a
 * pessoa do setor.
 *
 * Nada é apagado: férias são canceladas, recusadas ou interrompidas, e
 * afastamentos são cancelados, sempre com motivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regras_ferias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->unique()->constrained('orgaos')->restrictOnDelete();
            $table->unsignedSmallInteger('dias_por_periodo')->default(30);
            $table->unsignedSmallInteger('max_etapas')->default(3);
            $table->unsignedSmallInteger('min_dias_etapa')->default(10);
            $table->unsignedSmallInteger('max_periodos_acumulados')->default(2);
            $table->unsignedSmallInteger('antecedencia_dias')->default(30);
            $table->string('fundamento', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('periodos_aquisitivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos')->restrictOnDelete();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->date('inicio');
            $table->date('fim');
            $table->unsignedSmallInteger('dias_direito');
            $table->timestamps();

            $table->unique(['servidor_id', 'inicio']);
        });

        Schema::create('ferias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos')->restrictOnDelete();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->foreignId('periodo_aquisitivo_id')->constrained('periodos_aquisitivos')->restrictOnDelete();
            $table->unsignedSmallInteger('etapa');
            $table->date('inicio');
            $table->date('fim');
            $table->unsignedSmallInteger('dias');
            $table->string('situacao', 20)->index();
            $table->string('processo_sei', 40)->nullable();
            $table->string('observacao', 255)->nullable();

            $table->boolean('solicitada_pelo_servidor')->default(false);
            $table->foreignId('solicitado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('analisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('analisado_em')->nullable();
            $table->string('motivo', 255)->nullable(); // recusa, cancelamento ou interrupção

            // Interrupção: fim e dias passam a ser os gozados; o previsto fica guardado.
            $table->date('fim_previsto')->nullable();
            $table->date('interrompida_em')->nullable();
            $table->timestamps();

            $table->index(['servidor_id', 'inicio']);
            $table->index(['orgao_id', 'inicio', 'fim']);
        });

        Schema::create('afastamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos')->restrictOnDelete();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->string('tipo', 80);
            $table->date('inicio');
            $table->date('fim');
            $table->string('documento', 120)->nullable();
            $table->string('processo_sei', 40)->nullable();
            $table->string('observacao', 255)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelado_em')->nullable();
            $table->string('motivo_cancelamento', 255)->nullable();
            $table->timestamps();

            $table->index(['servidor_id', 'inicio']);
            $table->index(['orgao_id', 'inicio', 'fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afastamentos');
        Schema::dropIfExists('ferias');
        Schema::dropIfExists('periodos_aquisitivos');
        Schema::dropIfExists('regras_ferias');
    }
};
