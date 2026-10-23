<?php

return [
    // Administradores (geral e do órgão) só usam o sistema depois de ativar a autenticação em dois fatores.
    'exigir_2fa' => (bool) env('NEXRH_EXIGIR_2FA', true),

    // Cidade que aparece antes da data nas declarações da ficha em PDF.
    'local_assinatura' => env('NEXRH_LOCAL_ASSINATURA', 'Boa Vista/RR'),

    // Modelos de planilha oferecidos para download (pasta resources/modelos-importacao).
    'modelos_importacao' => [
        '01_modelo_unidades.xlsx' => ['Unidades e organograma', 'Setores do órgão, unidade superior, chefe e mínimo de pessoas no setor durante as férias.'],
        '02_modelo_cargos_e_vagas.xlsx' => ['Cargos e vagas', 'Quadro de cargos comissionados e efetivos, símbolos, quantitativo legal e remanejamentos.'],
        '03_modelo_servidores.xlsx' => ['Servidores', 'Todos os campos da ficha cadastral do servidor, mais acumulação de cargos e bens.'],
        '04_modelo_dependentes.xlsx' => ['Dependentes', 'Dependentes com parentesco e finalidade (IR, previdência, salário-família).'],
        '05_modelo_ferias.xlsx' => ['Férias', 'Histórico e programação de férias por período aquisitivo, com etapa, dias e situação. Identifica o servidor pelo CPF.'],
        '06_modelo_afastamentos.xlsx' => ['Afastamentos', 'Licenças e afastamentos com tipo, período e documento. Importe antes das férias.'],
        'exemplo_ponto_ficticio.txt' => ['Exemplo: relógio de ponto (fictício)', 'Arquivo AFD (Portaria 671/2021) com marcações inventadas de agosto de 2026 de dez servidores do exemplo 03, com um CPF de fora do órgão e uma linha ilegível de propósito.'],
        'exemplo_afastamentos_ficticio.xlsx' => ['Exemplo: afastamentos (fictício)', 'Planilha modelo 06 preenchida com licenças inventadas dos servidores do exemplo 03, com três linhas erradas de propósito.'],
        'exemplo_ferias_ficticio.xlsx' => ['Exemplo: férias (fictício)', 'Planilha modelo 05 preenchida com férias inventadas dos servidores do exemplo 03, com cinco linhas erradas de propósito.'],
        'exemplo_dependentes_ficticio.xlsx' => ['Exemplo: dependentes (fictício)', 'Planilha modelo 04 preenchida com dependentes inventados dos servidores do exemplo 03, para testar a importação.'],
        'exemplo_servidores_ficticio.xlsx' => ['Exemplo: servidores (fictício)', 'Planilha modelo 03 preenchida com servidores inventados, para testar a importação do cadastro.'],
        'exemplo_estrutura_cargos_ficticia.xlsx' => ['Exemplo: estrutura de cargos (fictícia)', 'Cópia da planilha de estrutura de cargos no formato usado pela SELC, com nomes e CPFs inventados. Serve para testar a importação.'],
    ],
];
