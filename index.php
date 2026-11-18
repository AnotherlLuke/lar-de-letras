<?php
require_once __DIR__ . '/includes/funcoes.php';

$generos = buscar_generos($conexao);
$busca   = trim($_GET['busca'] ?? '');

if ($busca !== '') {
    // Página de resultados da busca
    $consulta = $conexao->prepare(SQL_LIVROS . " WHERE l.titulo LIKE :titulo OR l.autor LIKE :autor ORDER BY l.titulo");
    $termo = '%' . $busca . '%';
    $consulta->execute(['titulo' => $termo, 'autor' => $termo]);
    $resultados = $consulta->fetchAll();
} else {
    $livros = $conexao->query(SQL_LIVROS . " ORDER BY l.titulo")->fetchAll();

    $lancamentos = array_values(array_filter($livros, fn($l) => $l['lancamento']));
    usort($lancamentos, fn($a, $b) => (int) $b['ano'] <=> (int) $a['ano']);

    // Banner principal: livros escolhidos pelo admin, na ordem definida em Gerenciar livros → Banner
    $destaques = ordenar_banner(array_filter($livros, fn($l) => $l['banner']));

    $mais_vendidos = array_filter($livros, fn($l) => $l['mais_vendido']);

    // Agrupa por gênero, na ordem da estante
    $por_genero = [];
    foreach ($livros as $livro) {
        $por_genero[$livro['genero_slug']][] = $livro;
    }
}

$titulo       = $busca !== '' ? 'Busca: ' . $busca : null;
$estante      = true;
$link_estante = $busca !== '' ? 'index.php#' : '#';
require __DIR__ . '/includes/cabecalho.php';
?>

<?php if ($busca !== ''): ?>

    <section class="secao">
        <h1 class="secao-titulo">Resultados para “<?= e($busca) ?>”</h1>
        <?php if ($resultados): ?>
            <p class="secao-info"><?= count($resultados) ?> <?= count($resultados) === 1 ? 'livro encontrado' : 'livros encontrados' ?></p>
            <div class="prateleira">
                <?php foreach ($resultados as $livro) { require __DIR__ . '/includes/card-livro.php'; } ?>
            </div>
        <?php else: ?>
            <p class="vazio">Nenhum livro com esse título ou autor. Confira a grafia ou <a href="index.php">veja o catálogo completo</a>.</p>
        <?php endif; ?>
    </section>

<?php else: ?>

    <h1 class="so-leitor">Lar de Letras, livraria digital</h1>

    <?php if ($destaques): $total_destaques = count($destaques); ?>
        <section class="vitrine" aria-roledescription="carrossel" aria-label="Destaques" data-carrossel>
            <div class="vitrine-trilho">
                <?php foreach ($destaques as $i => $destaque): $link = 'livro.php?id=' . (int) $destaque['id']; ?>
                    <article class="vitrine-slide" style="<?= cores_banner($destaque) ?>"
                             role="group" aria-roledescription="slide" aria-label="<?= $i + 1 ?> de <?= $total_destaques ?>">
                        <a class="vitrine-capa" href="<?= $link ?>" tabindex="-1" aria-hidden="true">
                            <?= capa($destaque) ?>
                        </a>
                        <div class="vitrine-texto">
                            <p class="vitrine-contexto"><?= e(chamada_banner($destaque)) ?></p>
                            <h2 class="vitrine-titulo"><?= e($destaque['titulo']) ?></h2>
                            <p class="vitrine-autor"><?= e($destaque['autor']) ?></p>
                            <?php if (descricao_banner($destaque) !== ''): ?>
                                <p class="vitrine-descricao"><?= e(descricao_banner($destaque)) ?></p>
                            <?php endif; ?>
                            <div class="vitrine-acao">
                                <a class="botao botao-claro" href="<?= $link ?>">Ver livro</a>
                                <p class="vitrine-preco">
                                    <?php if ($destaque['preco_fisico'] !== null): ?>
                                        <?= preco_html($destaque['preco_fisico'], $destaque['desconto_fisico']) ?>
                                    <?php else: ?>
                                        E-book <?= preco_html($destaque['preco_digital'], $destaque['desconto_digital']) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($total_destaques > 1): ?>
                <button type="button" class="vitrine-seta vitrine-anterior" aria-label="Destaque anterior">
                    <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <button type="button" class="vitrine-seta vitrine-proxima" aria-label="Próximo destaque">
                    <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <div class="vitrine-pontos">
                    <?php for ($i = 0; $i < $total_destaques; $i++): ?>
                        <button type="button" aria-label="Mostrar destaque <?= $i + 1 ?> de <?= $total_destaques ?>"
                                <?= $i === 0 ? 'aria-current="true"' : '' ?>></button>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($lancamentos): ?>
        <section class="secao" aria-labelledby="titulo-lancamentos">
            <h2 id="titulo-lancamentos" class="secao-titulo">Lançamentos</h2>
            <div class="prateleira">
                <?php foreach ($lancamentos as $livro) { require __DIR__ . '/includes/card-livro.php'; } ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($mais_vendidos): ?>
        <section class="secao" aria-labelledby="titulo-vendidos">
            <h2 id="titulo-vendidos" class="secao-titulo">Mais vendidos</h2>
            <div class="prateleira">
                <?php foreach ($mais_vendidos as $livro) { require __DIR__ . '/includes/card-livro.php'; } ?>
            </div>
        </section>
    <?php endif; ?>

    <div id="catalogo">
        <?php foreach ($generos as $genero): ?>
            <?php if (empty($por_genero[$genero['slug']])) continue; ?>
            <section class="secao secao-genero" id="<?= e($genero['slug']) ?>"
                     data-genero="<?= e($genero['slug']) ?>" style="<?= cores_genero($genero) ?>"
                     aria-labelledby="titulo-<?= e($genero['slug']) ?>">
                <h2 id="titulo-<?= e($genero['slug']) ?>" class="secao-titulo">
                    <?= e($genero['nome']) ?>
                </h2>
                <p class="secao-info"><?= (int) $genero['total'] ?> <?= (int) $genero['total'] === 1 ? 'livro' : 'livros' ?></p>
                <div class="prateleira">
                    <?php foreach ($por_genero[$genero['slug']] as $livro) { require __DIR__ . '/includes/card-livro.php'; } ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/rodape.php'; ?>
