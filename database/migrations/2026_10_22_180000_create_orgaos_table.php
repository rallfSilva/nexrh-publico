<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orgaos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('sigla', 30)->unique();
            $table->string('cnpj', 18)->nullable()->unique();
            $table->string('esfera', 20)->default('estadual');
            $table->string('uf', 2)->default('RR');
            $table->string('municipio')->default('Boa Vista');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('orgao_id')->nullable()->after('id')->constrained('orgaos')->restrictOnDelete();
            $table->string('cpf', 14)->nullable()->unique()->after('email');
            $table->boolean('ativo')->default(true)->after('password');
            $table->timestamp('ultimo_acesso_em')->nullable()->after('ativo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orgao_id');
            $table->dropColumn(['cpf', 'ativo', 'ultimo_acesso_em']);
        });

        Schema::dropIfExists('orgaos');
    }
};
