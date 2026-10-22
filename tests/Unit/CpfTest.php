<?php

namespace Tests\Unit;

use App\Importacao\Texto;
use App\Support\Cpf;
use PHPUnit\Framework\TestCase;

class CpfTest extends TestCase
{
    public function test_valida_digitos(): void
    {
        $this->assertTrue(Cpf::valido('529.982.247-25'));
        $this->assertFalse(Cpf::valido('529.982.247-26'));
        $this->assertFalse(Cpf::valido('111.111.111-11'));
        $this->assertFalse(Cpf::valido('123'));
    }

    public function test_titulo_em_portugues(): void
    {
        $this->assertSame('Secretário de Estado de Licitação e Contratação', Texto::titulo('SECRETÁRIO DE ESTADO DE LICITAÇÃO E CONTRATAÇÃO'));
        $this->assertSame('Consultor Técnico I', Texto::titulo('CONSULTOR TÉCNICO I'));
    }

    public function test_numeros_e_datas(): void
    {
        $this->assertSame(4472.58, Texto::decimal('4.472,58'));
        $this->assertSame(9571.34, Texto::decimal(9571.34));
        $this->assertSame('2026-06-25', Texto::data('25/06/2026'));
        $this->assertNull(Texto::data('−'));
        $this->assertNull(Texto::valor('—'));
    }
}
