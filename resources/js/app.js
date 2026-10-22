// Comportamentos pequenos das telas. Tudo funciona sem JavaScript; aqui só fica mais confortável.
document.documentElement.classList.add('js');

const $$ = (seletor, raiz = document) => Array.from(raiz.querySelectorAll(seletor));

/* Campos que só aparecem em certos casos: data-mostrar-se="campo:valor1,valor2" (ou "campo:!valor"). */
function valorDoCampo(form, nome) {
    const marcado = form.querySelector(`[name="${nome}"]:checked`);
    if (marcado) return marcado.value;
    const campo = form.querySelector(`[name="${nome}"]:not([type=radio]):not([type=checkbox])`);
    return campo ? campo.value : '';
}

function atualizarCondicionais(form) {
    $$('[data-mostrar-se]', form).forEach((el) => {
        const [nome, lista] = el.dataset.mostrarSe.split(':');
        const negar = lista.startsWith('!');
        const valores = (negar ? lista.slice(1) : lista).split(',');
        const atual = valorDoCampo(form, nome).trim();
        const casa = valores.includes(atual);
        el.hidden = negar ? (atual === '' || casa) : !casa;
    });
}

$$('form').forEach((form) => {
    if (!form.querySelector('[data-mostrar-se]')) return;
    atualizarCondicionais(form);
    form.addEventListener('change', () => atualizarCondicionais(form));
    form.addEventListener('input', () => atualizarCondicionais(form));
});

/* Máscaras simples de digitação. */
const mascaras = {
    cpf: (d) => d.slice(0, 11).replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2'),
    cep: (d) => d.slice(0, 8).replace(/(\d{5})(\d)/, '$1-$2'),
    celular: (d) => d.slice(0, 11).replace(/^(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d{1,4})$/, '$1-$2'),
};
$$('[data-mascara]').forEach((input) => {
    const aplicar = mascaras[input.dataset.mascara];
    input.addEventListener('input', () => {
        const digitos = input.value.replace(/\D/g, '');
        input.value = digitos === '' ? '' : aplicar(digitos);
    });
});

/* Nome do banco a partir do código. */
$$('[data-bancos]').forEach((codigo) => {
    const bancos = JSON.parse(codigo.dataset.bancos);
    const nome = codigo.form.querySelector('[name="banco_nome"]');
    codigo.addEventListener('input', () => {
        const encontrado = bancos[codigo.value.trim().padStart(3, '0')];
        if (encontrado && nome) nome.value = encontrado;
    });
});

/* Cadastro em etapas. */
$$('form[data-etapas]').forEach((form) => {
    const etapas = $$('.etapa', form);
    const botoes = $$('[data-ir-etapa]', form);
    const barra = form.querySelector('.prog span');
    let atual = 0;

    // A etapa pode estar escondida; só não contam os campos condicionais escondidos.
    const camposVisiveis = (etapa) => $$('input, select, textarea', etapa)
        .filter((c) => !c.closest('[data-mostrar-se][hidden]'));

    const valida = (etapa) => camposVisiveis(etapa).every((c) => c.checkValidity());

    function mostrar(indice, foco = true) {
        atual = Math.max(0, Math.min(indice, etapas.length - 1));
        etapas.forEach((e, i) => { e.hidden = i !== atual; });
        botoes.forEach((b, i) => {
            if (i === atual) b.setAttribute('aria-current', 'step'); else b.removeAttribute('aria-current');
        });
        if (barra) barra.style.width = `${Math.round(((atual + 1) / etapas.length) * 100)}%`;
        form.querySelector('[data-anterior]').hidden = atual === 0;
        form.querySelector('[data-proxima]').hidden = atual === etapas.length - 1;
        if (foco) etapas[atual].querySelector('h2')?.focus();
    }

    function conferir(indice) {
        const etapa = etapas[indice];
        const ok = valida(etapa);
        botoes[indice].classList.toggle('com-erro', !ok);
        botoes[indice].classList.toggle('feita', ok);
        return ok;
    }

    function relatarErro(indice) {
        mostrar(indice, false);
        camposVisiveis(etapas[indice]).find((c) => !c.checkValidity())?.reportValidity();
    }

    botoes.forEach((b, i) => b.addEventListener('click', () => { conferir(atual); mostrar(i); }));
    form.querySelector('[data-proxima]').addEventListener('click', () => {
        if (!conferir(atual)) { relatarErro(atual); return; }
        mostrar(atual + 1);
    });
    form.querySelector('[data-anterior]').addEventListener('click', () => mostrar(atual - 1));

    form.addEventListener('submit', (evento) => {
        const invalida = etapas.findIndex((_, i) => !conferir(i));
        if (invalida !== -1) {
            evento.preventDefault();
            relatarErro(invalida);
        }
    });

    // Volta do servidor com erros: abre a primeira etapa que tem erro.
    const comErro = etapas.findIndex((e) => e.querySelector('.erro'));
    etapas.forEach((e, i) => { if (e.querySelector('.erro')) botoes[i].classList.add('com-erro'); });
    mostrar(comErro === -1 ? 0 : comErro, false);
});

/* Abas da ficha: ?aba=declaracoes abre direto na aba. */
$$('[data-abas]').forEach((grupo) => {
    const abas = $$('[data-aba]', grupo);
    const paineis = abas.map((a) => document.getElementById(a.dataset.aba));

    function abrir(id, atualizarEndereco = true) {
        abas.forEach((a, i) => {
            const sel = a.dataset.aba === id;
            a.setAttribute('aria-selected', sel ? 'true' : 'false');
            a.tabIndex = sel ? 0 : -1;
            paineis[i].hidden = !sel;
        });
        if (atualizarEndereco) {
            const url = new URL(window.location);
            url.searchParams.set('aba', id);
            history.replaceState(null, '', url);
        }
    }

    abas.forEach((a) => a.addEventListener('click', (e) => { e.preventDefault(); abrir(a.dataset.aba); }));
    grupo.addEventListener('keydown', (e) => {
        if (!['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
        const i = abas.findIndex((a) => a.getAttribute('aria-selected') === 'true');
        const prox = abas[(i + (e.key === 'ArrowRight' ? 1 : abas.length - 1)) % abas.length];
        abrir(prox.dataset.aba);
        prox.focus();
    });

    const pedida = new URL(window.location).searchParams.get('aba');
    abrir(abas.some((a) => a.dataset.aba === pedida) ? pedida : abas[0].dataset.aba, false);
});

/* Listas que crescem (parentes, bens): botão copia o <template>. */
$$('[data-adicionar-linha]').forEach((botao) => {
    const modelo = document.getElementById(botao.dataset.adicionarLinha);
    const destino = document.getElementById(botao.dataset.destino);
    botao.addEventListener('click', () => {
        const indice = destino.children.length;
        destino.insertAdjacentHTML('beforeend', modelo.innerHTML.replaceAll('__i__', indice));
        destino.lastElementChild.querySelector('input, select')?.focus();
    });
});
document.addEventListener('click', (e) => {
    const remover = e.target.closest('[data-remover-linha]');
    if (remover) remover.closest('.linha-lista').remove();
});

/* Diálogos de confirmação (ex.: desligamento). */
$$('[data-abrir-dialogo]').forEach((botao) => {
    botao.addEventListener('click', () => document.getElementById(botao.dataset.abrirDialogo).showModal());
});
$$('[data-fechar-dialogo]').forEach((botao) => {
    botao.addEventListener('click', () => botao.closest('dialog').close());
});
