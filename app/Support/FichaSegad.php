<?php

namespace App\Support;

/**
 * Listas e códigos da Ficha de Cadastro enviada à SEGAD/RR, iguais aos da
 * planilha modelo 03 (aba "Listas"). As telas, a importação e o PDF usam
 * as mesmas listas, para o código gravado ser sempre o da ficha.
 */
final class FichaSegad
{
    public const SEXOS = ['F' => 'Feminino', 'M' => 'Masculino'];

    public const ESTADOS_CIVIS = [
        1 => 'Casado(a)',
        2 => 'Solteiro(a)',
        3 => 'Separado(a)',
        4 => 'Divorciado(a)',
        5 => 'Viúvo(a)',
    ];

    public const RACAS = [
        1 => 'Branco',
        2 => 'Preto',
        3 => 'Pardo',
        4 => 'Amarelo',
        5 => 'Indígena',
        6 => 'Não informado',
    ];

    public const DEFICIENCIAS = [
        1 => 'Física',
        2 => 'Visual',
        3 => 'Auditiva',
        4 => 'Mental',
        5 => 'Intelectual',
        99 => 'Não deficiente',
    ];

    /** Códigos de parentesco do dependente (tabela do eSocial usada na ficha), com dois dígitos. */
    public const PARENTESCOS = [
        '01' => 'Cônjuge',
        '02' => 'Companheiro(a) com filho, união há mais de 5 anos ou declaração de união estável',
        '03' => 'Filho(a) ou enteado(a)',
        '04' => 'Filho(a) ou enteado(a) universitário(a) ou em escola técnica de 2º grau',
        '06' => 'Irmão(ã), neto(a) ou bisneto(a) sob guarda judicial',
        '07' => 'Irmão(ã), neto(a) ou bisneto(a) sob guarda judicial, universitário(a) ou em escola técnica',
        '09' => 'Pais, avós e bisavós',
        '10' => 'Menor pobre sob guarda judicial',
        '11' => 'Pessoa absolutamente incapaz sob tutela ou curatela',
        '12' => 'Ex-cônjuge',
        '99' => 'Agregado/Outros',
    ];

    public const TIPOS_SANGUINEOS = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];

    public const CATEGORIAS_RESERVA = [
        1 => '1ª Categoria',
        2 => '2ª Categoria',
        3 => '3ª Categoria',
    ];

    public const ESCOLARIDADES = [
        1 => 'Analfabeto',
        2 => 'Até o 5º ano incompleto do fundamental',
        3 => '5º ano completo do fundamental',
        4 => 'Do 6º ao 9º ano incompleto do fundamental',
        5 => 'Fundamental completo',
        6 => 'Médio incompleto',
        7 => 'Médio completo',
        8 => 'Superior incompleto',
        9 => 'Superior completo',
        10 => 'Pós-graduação completa',
        11 => 'Mestrado completo',
        12 => 'Doutorado completo',
    ];

    public const CATEGORIAS_CNH = ['A', 'B', 'AB', 'C', 'D', 'E', 'AC', 'AD', 'AE'];

    public const CARGAS_HORARIAS = ['6h diárias / 30h semanais', '8h diárias / 40h semanais'];

    public const VINCULOS = [
        'Efetivo ocupante de função de confiança ou cargo em comissão',
        'Ocupante de função de confiança ou cargo em comissão',
        'Requisitado ou cedido, ocupante de cargo em comissão',
    ];

    public const TITULARES_BEM = ['servidor' => 'Servidor(a)', 'conjuge' => 'Cônjuge ou companheiro(a)'];

    /** Bancos mais comuns na folha, para preencher o nome a partir do código. */
    public const BANCOS = [
        '001' => 'Banco do Brasil',
        '033' => 'Santander',
        '041' => 'Banrisul',
        '070' => 'BRB',
        '077' => 'Banco Inter',
        '104' => 'Caixa Econômica Federal',
        '237' => 'Bradesco',
        '260' => 'Nu Pagamentos',
        '336' => 'C6 Bank',
        '341' => 'Itaú Unibanco',
        '748' => 'Sicredi',
        '756' => 'Sicoob',
    ];

    public const UFS = [
        'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA',
        'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
    ];

    /** "2 - Solteiro(a)" ou "Solteiro(a)" vira 2, conforme a lista. */
    public static function codigo(array $lista, mixed $valor): int|string|null
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        if (preg_match('/^(\d+)\s*(-|$)/', $valor, $m)) {
            // "03" é chave texto no PHP; "3", "10" e "99" viram chaves inteiras.
            foreach ([(int) $m[1], str_pad($m[1], 2, '0', STR_PAD_LEFT)] as $chave) {
                if (array_key_exists($chave, $lista)) {
                    return $chave;
                }
            }
        }

        $chave = mb_strtolower($valor);
        foreach ($lista as $codigo => $rotulo) {
            if (mb_strtolower((string) $rotulo) === $chave || mb_strtolower((string) $codigo) === $chave) {
                return $codigo;
            }
        }

        return null;
    }

    /** Rótulo com o código, como sai na ficha: "2 - Solteiro(a)". */
    public static function comCodigo(array $lista, int|string|null $codigo): ?string
    {
        if ($codigo === null || ! array_key_exists($codigo, $lista)) {
            return null;
        }

        return is_int($codigo) ? $codigo.' - '.$lista[$codigo] : $lista[$codigo];
    }
}
