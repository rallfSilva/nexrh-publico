<?php

return [
    // Administradores (geral e do órgão) só usam o sistema depois de ativar a autenticação em dois fatores.
    'exigir_2fa' => (bool) env('NEXRH_EXIGIR_2FA', true),

    // Modelos de planilha oferecidos para download (pasta resources/modelos-importacao).
    'modelos_importacao' => [
        '01_modelo_unidades.xlsx' => ['Unidades e organograma', 'Setores do órgão, unidade superior, chefe e mínimo de pessoas no setor durante as férias.'],
        '02_modelo_cargos_e_vagas.xlsx' => ['Cargos e vagas', 'Quadro de cargos comissionados e efetivos, símbolos, quantitativo legal e remanejamentos.'],
        '03_modelo_servidores.xlsx' => ['Servidores', 'Todos os campos da ficha cadastral do servidor, mais acumulação de cargos e bens.'],
        '04_modelo_dependentes.xlsx' => ['Dependentes', 'Dependentes com parentesco e finalidade (IR, previdência, salário-família).'],
        '05_modelo_ferias.xlsx' => ['Férias', 'Períodos aquisitivos e programação de férias já marcadas.'],
        '06_modelo_afastamentos.xlsx' => ['Afastamentos', 'Licenças e afastamentos com tipo, período e fundamento.'],
        'exemplo_estrutura_cargos_ficticia.xlsx' => ['Exemplo: estrutura de cargos (fictícia)', 'Cópia da planilha de estrutura de cargos no formato usado pela SELC, com nomes e CPFs inventados. Serve para testar a importação.'],
    ],
];
