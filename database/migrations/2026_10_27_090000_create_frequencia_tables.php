<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frequência: regras de cada órgão, jornada de cada servidor (com vigência),
 * feriados e pontos facultativos, e os registros de entrada e saída.
 *
 * Registros de ponto não são apagados: o RH anula com motivo, e o registro
 * anulado continua no espelho, riscado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regras_frequencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->unique()->constrained('orgaos')->restrictOnDelete();
            $table->json('turnos_6h');
            $table->json('turnos_8h');
            $table->unsignedSmallInteger('tolerancia_minutos')->default(10);
            $table->boolean('exigir_rede')->default(true);
            $table->string('redes_permitidas', 500)->nullable();
            $table->string('fundamento', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('jornadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos')->restrictOnDelete();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->string('modalidade', 20); // presencial, teletrabalho ou dispensado
            $table->json('turnos')->nullable();
            $table->json('dias_semana');
            $table->date('inicio');
            $table->date('fim')->nullable();
            $table->string('observacao', 255)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['servidor_id', 'inicio']);
        });

        Schema::create('feriados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos')->restrictOnDelete();
            $table->date('data');
            $table->string('nome', 120);
            $table->string('tipo', 20); // feriado ou ponto_facultativo
            $table->timestamps();

            $table->unique(['orgao_id', 'data']);
        });

        Schema::create('registros_ponto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos')->restrictOnDelete();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->timestamp('momento');
            $table->string('origem', 20); // sistema, relogio ou rh
            $table->string('ip', 45)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo', 255)->nullable(); // inclusão pelo RH
            $table->unsignedBigInteger('nsr')->nullable(); // número sequencial do relógio de ponto
            $table->foreignId('importacao_id')->nullable()->constrained('importacoes')->nullOnDelete();
            $table->timestamp('anulado_em')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacao', 255)->nullable();
            $table->timestamps();

            $table->unique(['servidor_id', 'momento']);
            $table->index(['orgao_id', 'momento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_ponto');
        Schema::dropIfExists('feriados');
        Schema::dropIfExists('jornadas');
        Schema::dropIfExists('regras_frequencia');
    }
};
