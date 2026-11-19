<?php
require_once __DIR__ . '/includes/funcoes.php';

$generos = buscar_generos($conexao);
$id      = (int) ($_GET['id'] ?? 0);

$consulta = $conexao->prepare(SQL_LIVROS . " WHERE l.id = :id");
$consulta->execute(['id' => $id]);
$livro = $consulta->fetch();

if ($livro) {
    $no_carrinho = isset(carrinho()[(int) $livro['id']]);

    // Outros livros do mesmo gênero
    $consulta = $conexao->prepare(SQL_LIVROS . " WHERE l.genero_id = :genero AND l.id <> :id ORDER BY l.mais_vendido DESC, l.titulo LIMIT 4");
    $consulta->execute(['genero' => $livro['genero_id'], 'id' => $livro['id']]);
    $parecidos = $consulta->fetchAll();

    $titulo       = $livro['titulo'];
    $genero_ativo = $livro['genero_slug'];
} else {
    http_response_code(404);
    $titulo = 'Livro não encontrado';
}

$estante = true;
require __DIR__ . '/includes/cabecalho.php';
?>

<?php if (!$livro): ?>

    <section class="secao">
        <h1 class="secao-titulo">Livro não encontrado</h1>
        <p class="vazio">Esse endereço não corresponde a nenhum livro do catálogo. <a href="index.php">Voltar ao catálogo</a></p>
    </section>

<?php else: ?>

    <article class="ficha">
        <div class="ficha-capa">
            <?= capa($livro, 'Capa de ' . $livro['titulo']) ?>
        </div>

        <div class="ficha-info">
            <a class="ficha-genero" href="index.php#<?= e($livro['genero_slug']) ?>" style="<?= cores_genero($livro) ?>">
                <?= e($livro['genero_nome']) ?>
            </a>
            <h1 class="ficha-titulo"><?= e($livro['titulo']) ?></h1>
            <?php if (eh_admin()): ?>
                <a class="ficha-editar" href="admin.php?acao=editar&amp;id=<?= (int) $livro['id'] ?>">Editar este livro</a>
            <?php endif; ?>
            <p class="ficha-autor"><?= e($livro['autor']) ?></p>

            <?php if (!empty($livro['descricao'])): ?>
                <p class="ficha-descricao"><?= e($livro['descricao']) ?></p>
            <?php endif; ?>

            <dl class="ficha-detalhes">
                <div><dt>Idioma</dt><dd><?= e($livro['idioma']) ?></dd></div>
                <?php if ($livro['ano']): ?>
                    <div><dt>Publicado em</dt><dd><?= (int) $livro['ano'] ?></dd></div>
                <?php endif; ?>
                <?php if ($livro['paginas']): ?>
                    <div><dt>Páginas</dt><dd><?= (int) $livro['paginas'] ?></dd></div>
                <?php endif; ?>
            </dl>

            <form class="compra" action="carrinho.php" method="post">
                <input type="hidden" name="acao" value="adicionar">
                <input type="hidden" name="id" value="<?= (int) $livro['id'] ?>">

                <p class="compra-preco"><?= preco_html($livro['preco'], $livro['desconto']) ?></p>
                <p class="compra-nota">Ebook enviado para o seu email após o pagamento.</p>

                <?php if ($no_carrinho): ?>
                    <a class="botao" href="carrinho.php">Já está no carrinho: ver carrinho</a>
                <?php else: ?>
                    <button type="submit" class="botao">Adicionar ao carrinho</button>
                <?php endif; ?>
            </form>
        </div>
    </article>

    <?php if ($parecidos): ?>
        <section class="secao" aria-labelledby="titulo-parecidos">
            <h2 id="titulo-parecidos" class="secao-titulo">Você também pode gostar de…</h2>
            <div class="prateleira">
                <?php foreach ($parecidos as $livro) { require __DIR__ . '/includes/card-livro.php'; } ?>
            </div>
        </section>
    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/rodape.php'; ?>
