<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ficha pode nascer incompleta: a planilha do quadro de cargos traz só nome, CPF,
 * vaga e ato de nomeação. O resto da Ficha SEGAD fica como pendência até o RH
 * preencher; a tela de edição continua exigindo tudo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->char('sexo', 1)->nullable()->change();
            $table->unsignedSmallInteger('estado_civil')->nullable()->change();
            $table->unsignedSmallInteger('raca_cor')->nullable()->change();
            $table->string('naturalidade', 80)->nullable()->change();
            $table->date('data_nascimento')->nullable()->change();
            $table->string('nome_mae', 160)->nullable()->change();
            $table->unsignedSmallInteger('grau_instrucao')->nullable()->change();
            $table->string('cep', 9)->nullable()->change();
            $table->string('logradouro', 160)->nullable()->change();
            $table->string('numero', 20)->nullable()->change();
            $table->string('bairro', 80)->nullable()->change();
            $table->string('municipio', 80)->nullable()->change();
            $table->char('uf', 2)->nullable()->change();
            $table->string('celular', 20)->nullable()->change();
            $table->string('email', 160)->nullable()->change();
            $table->string('banco_codigo', 5)->nullable()->change();
            $table->text('agencia')->nullable()->change();
            $table->text('conta_corrente')->nullable()->change();
        });
    }

    /** Só volta se nenhuma ficha estiver incompleta. */
    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->char('sexo', 1)->nullable(false)->change();
            $table->unsignedSmallInteger('estado_civil')->nullable(false)->change();
            $table->unsignedSmallInteger('raca_cor')->nullable(false)->change();
            $table->string('naturalidade', 80)->nullable(false)->change();
            $table->date('data_nascimento')->nullable(false)->change();
            $table->string('nome_mae', 160)->nullable(false)->change();
            $table->unsignedSmallInteger('grau_instrucao')->nullable(false)->change();
            $table->string('cep', 9)->nullable(false)->change();
            $table->string('logradouro', 160)->nullable(false)->change();
            $table->string('numero', 20)->nullable(false)->change();
            $table->string('bairro', 80)->nullable(false)->change();
            $table->string('municipio', 80)->nullable(false)->change();
            $table->char('uf', 2)->nullable(false)->change();
            $table->string('celular', 20)->nullable(false)->change();
            $table->string('email', 160)->nullable(false)->change();
            $table->string('banco_codigo', 5)->nullable(false)->change();
            $table->text('agencia')->nullable(false)->change();
            $table->text('conta_corrente')->nullable(false)->change();
        });
    }
};
