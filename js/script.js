// Lar de Letras: comportamentos opcionais. O site funciona sem JavaScript.
document.documentElement.classList.add('js');

document.addEventListener('DOMContentLoaded', () => {
    destacarGeneroVisivel();
    alternarQuantidade();
    atualizarQuantidadeAoMudar();
    confirmarAntesDeEnviar();
    previaDoPrecoComDesconto();
    carrosselDoBanner();
    previaDoSlide();
});

// Admin → Banner: a faixa colorida do slide mostra a cor e a chamada enquanto o admin edita.
function previaDoSlide() {
    document.querySelectorAll('.slide-admin').forEach((slide) => {
        const cor = slide.querySelector('[data-previa-cor]');
        const usarGenero = slide.querySelector('[data-usar-cor-genero]');
        const chamada = slide.querySelector('[data-previa-texto]');
        const previaChamada = slide.querySelector('[data-previa-chamada]');

        const pintar = () => {
            slide.style.setProperty('--cor', cor.value);
            slide.style.setProperty('--cor-texto', corDoTexto(cor.value));
        };

        cor.addEventListener('input', () => {
            usarGenero.checked = cor.value.toUpperCase() === cor.dataset.corGenero.toUpperCase();
            pintar();
        });
        usarGenero.addEventListener('change', () => {
            if (usarGenero.checked) cor.value = cor.dataset.corGenero.toLowerCase();
            pintar();
        });
        chamada.addEventListener('input', () => {
            previaChamada.textContent = chamada.value.trim() || previaChamada.dataset.previaChamada;
        });
    });
}

// Mesma regra do PHP (cor_texto_para): branco ou escuro, o que tiver mais contraste.
function corDoTexto(hex) {
    const luz = [1, 3, 5].reduce((soma, inicio, i) => {
        const c = parseInt(hex.slice(inicio, inicio + 2), 16) / 255;
        const linear = c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
        return soma + [0.2126, 0.7152, 0.0722][i] * linear;
    }, 0);
    return 1.05 / (luz + 0.05) >= (luz + 0.05) / 0.0662 ? '#FFFFFF' : '#1D2238';
}

// Página inicial: banner com flechas e bolinhas. Sem JavaScript, os slides rolam para o lado.
function carrosselDoBanner() {
    const carrossel = document.querySelector('[data-carrossel]');
    if (!carrossel) return;

    const trilho = carrossel.querySelector('.vitrine-trilho');
    const slides = carrossel.querySelectorAll('.vitrine-slide');
    const pontos = carrossel.querySelectorAll('.vitrine-pontos button');
    const anterior = carrossel.querySelector('.vitrine-anterior');
    const proxima = carrossel.querySelector('.vitrine-proxima');
    if (slides.length < 2) return;

    const semAnimacao = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let atual = 0;

    const ir = (indice) => {
        atual = (indice + slides.length) % slides.length; // dá a volta nas pontas
        trilho.scrollTo({ left: atual * trilho.clientWidth, behavior: semAnimacao ? 'auto' : 'smooth' });
        marcar();
    };

    const marcar = () => {
        pontos.forEach((ponto, i) => {
            if (i === atual) ponto.setAttribute('aria-current', 'true');
            else ponto.removeAttribute('aria-current');
        });
        slides.forEach((slide, i) => {
            slide.toggleAttribute('inert', i !== atual); // só o slide visível recebe foco
        });
    };

    anterior.addEventListener('click', () => ir(atual - 1));
    proxima.addEventListener('click', () => ir(atual + 1));
    pontos.forEach((ponto, i) => ponto.addEventListener('click', () => ir(i)));

    carrossel.addEventListener('keydown', (evento) => {
        if (evento.key === 'ArrowLeft') { ir(atual - 1); evento.preventDefault(); }
        if (evento.key === 'ArrowRight') { ir(atual + 1); evento.preventDefault(); }
    });

    // Quando a pessoa arrasta com o dedo, atualiza as bolinhas.
    let espera;
    trilho.addEventListener('scroll', () => {
        clearTimeout(espera);
        espera = setTimeout(() => {
            const indice = Math.round(trilho.scrollLeft / trilho.clientWidth);
            if (indice !== atual) { atual = indice; marcar(); }
        }, 80);
    }, { passive: true });

    window.addEventListener('resize', () => {
        trilho.scrollTo({ left: atual * trilho.clientWidth });
    });

    marcar();
}

// Admin: mostra o preço final enquanto o admin digita o preço e o desconto.
function previaDoPrecoComDesconto() {
    const real = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    // Mesma regra do PHP (ler_preco): aceita "49,90", "1.234,90" e "49.90".
    const lerPreco = (texto) => {
        let t = texto.replace(/R\$|\s/g, '');
        if (t === '') return null;
        if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.');
        const n = Number(t);
        return Number.isFinite(n) ? Math.round(n * 100) / 100 : null;
    };

    document.querySelectorAll('[data-previa]').forEach((bloco) => {
        const campoPreco = bloco.querySelector('[data-preco]');
        const campoDesconto = bloco.querySelector('[data-desconto]');
        const saida = bloco.querySelector('.previa-preco');

        const atualizar = () => {
            const preco = lerPreco(campoPreco.value);
            const textoDesconto = campoDesconto.value.trim();
            const desconto = textoDesconto === '' ? 0 : Number(textoDesconto);

            if (preco === null || preco <= 0) {
                saida.textContent = 'Preço na loja: informe o preço';
            } else if (!Number.isInteger(desconto) || desconto < 0 || desconto > 100) {
                saida.textContent = 'Preço na loja: o desconto vai de 0 a 100%';
            } else if (desconto === 0) {
                saida.innerHTML = 'Preço na loja: <strong>' + real.format(preco) + '</strong>';
            } else {
                const final = Math.round(preco * (100 - desconto)) / 100;
                saida.innerHTML = 'Preço na loja: <s>' + real.format(preco) + '</s> <strong>' + real.format(final) + '</strong>';
            }
        };

        campoPreco.addEventListener('input', atualizar);
        campoDesconto.addEventListener('input', atualizar);
    });
}

// Admin: pede confirmação antes de remover um livro.
function confirmarAntesDeEnviar() {
    document.querySelectorAll('form[data-confirmar]').forEach((form) => {
        form.addEventListener('submit', (evento) => {
            if (!window.confirm(form.dataset.confirmar)) evento.preventDefault();
        });
    });
}

// Página inicial: puxa da estante a lombada do gênero que está na tela.
function destacarGeneroVisivel() {
    const secoes = document.querySelectorAll('section[data-genero]');
    const lombadas = document.querySelectorAll('.lombada[data-genero]');
    if (!secoes.length || !('IntersectionObserver' in window)) return;

    const marcar = (slug) => {
        lombadas.forEach((lombada) => {
            const ativa = lombada.dataset.genero === slug;
            lombada.classList.toggle('ativa', ativa);
            if (ativa) lombada.setAttribute('aria-current', 'location');
            else lombada.removeAttribute('aria-current');
        });
    };

    const observador = new IntersectionObserver((entradas) => {
        entradas.forEach((entrada) => {
            if (entrada.isIntersecting) marcar(entrada.target.dataset.genero);
        });
    }, { rootMargin: '-35% 0px -60% 0px' });

    secoes.forEach((secao) => observador.observe(secao));

    // Acima do catálogo nenhuma lombada fica destacada.
    const catalogo = document.getElementById('catalogo');
    window.addEventListener('scroll', () => {
        if (catalogo && catalogo.getBoundingClientRect().top > window.innerHeight * 0.4) marcar(null);
    }, { passive: true });
}

// Página do livro: a quantidade só faz sentido para o livro físico.
function alternarQuantidade() {
    const campo = document.querySelector('[data-so-fisico]');
    const opcoes = document.querySelectorAll('input[name="formato"]');
    if (!campo || !opcoes.length) return;

    const atualizar = () => {
        const escolhido = document.querySelector('input[name="formato"]:checked');
        campo.hidden = !escolhido || escolhido.value !== 'fisico';
    };
    opcoes.forEach((opcao) => opcao.addEventListener('change', atualizar));
    atualizar();
}

// Carrinho: envia a nova quantidade assim que o valor muda.
function atualizarQuantidadeAoMudar() {
    document.querySelectorAll('.form-quantidade input[type="number"]').forEach((campo) => {
        campo.addEventListener('change', () => campo.form.submit());
    });
}
