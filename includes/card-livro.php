<?php
/*
 * Um livro "em pé" na prateleira. Espera a variável $livro
 * (linha de livros com as colunas de SQL_LIVROS).
 */
$link_livro = 'livro.php?id=' . (int) $livro['id'];
?>
<article class="livro">
    <a class="livro-capa" href="<?= $link_livro ?>" tabindex="-1" aria-hidden="true">
        <span class="capa">
            <?= capa($livro) ?>
        </span>
    </a>
    <h3 class="livro-titulo"><a href="<?= $link_livro ?>"><?= e($livro['titulo']) ?></a></h3>
    <p class="livro-autor"><?= e($livro['autor']) ?></p>
    <p class="livro-preco"><?= preco_html($livro['preco'], $livro['desconto']) ?></p>
</article>
