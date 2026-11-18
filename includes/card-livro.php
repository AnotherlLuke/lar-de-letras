<?php
/*
 * Um livro "em pé" na prateleira. Espera a variável $livro
 * (linha de livros com as colunas de SQL_LIVROS).
 */
$link_livro = 'livro.php?id=' . (int) $livro['id'];
$tem_digital = $livro['preco_digital'] !== null;
?>
<article class="livro">
    <a class="livro-capa" href="<?= $link_livro ?>" tabindex="-1" aria-hidden="true">
        <span class="capa">
            <?= capa($livro) ?>
            <?php if ($tem_digital): ?>
                <span class="tag-digital">digital</span>
            <?php endif; ?>
        </span>
    </a>
    <h3 class="livro-titulo"><a href="<?= $link_livro ?>"><?= e($livro['titulo']) ?></a></h3>
    <p class="livro-autor"><?= e($livro['autor']) ?></p>
    <?php if ($livro['preco_fisico'] !== null): ?>
        <p class="livro-preco">
            <?php if ((int) $livro['estoque'] > 0): ?>
                <?= preco_html($livro['preco_fisico'], $livro['desconto_fisico']) ?>
            <?php else: ?>
                <span class="esgotado">Físico esgotado</span>
            <?php endif; ?>
        </p>
    <?php endif; ?>
    <?php if ($tem_digital): ?>
        <p class="livro-ebook">E-book <?= preco_html($livro['preco_digital'], $livro['desconto_digital']) ?></p>
    <?php endif; ?>
</article>
