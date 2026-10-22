<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos')->restrictOnDelete();
            $table->string('denominacao');
            $table->string('simbolo', 30);
            $table->string('natureza', 30)->default('comissao');
            $table->decimal('remuneracao', 12, 2)->nullable();
            $table->unsignedSmallInteger('quantidade_vagas');
            $table->string('base_legal')->nullable();
            $table->text('observacao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['orgao_id', 'denominacao', 'simbolo']);
        });

        Schema::create('vagas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos')->restrictOnDelete();
            $table->foreignId('cargo_id')->constrained('cargos')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->string('base_legal')->nullable();
            $table->string('situacao', 20)->default('vaga')->index();
            $table->string('orgao_remanejamento')->nullable();
            $table->string('ocupante_nome')->nullable();
            $table->text('ocupante_cpf')->nullable();
            $table->string('ato_numero', 60)->nullable();
            $table->date('ato_data')->nullable();
            $table->date('efeito_data')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->unique(['cargo_id', 'numero']);
        });

        Schema::create('importacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 30);
            $table->string('formato', 30)->nullable();
            $table->string('arquivo_nome');
            $table->string('arquivo_caminho');
            $table->string('arquivo_hash', 64);
            $table->string('situacao', 20)->default('analisada')->index();
            $table->jsonb('resumo')->nullable();
            $table->jsonb('divergencias')->nullable();
            $table->longText('dados')->nullable();
            $table->timestamp('aplicada_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacoes');
        Schema::dropIfExists('vagas');
        Schema::dropIfExists('cargos');
    }
};
