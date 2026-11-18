<?php
require_once __DIR__ . '/includes/funcoes.php';

$generos = buscar_generos($conexao);
$id      = (int) ($_GET['id'] ?? 0);

$consulta = $conexao->prepare(SQL_LIVROS . " WHERE l.id = :id");
$consulta->execute(['id' => $id]);
$livro = $consulta->fetch();

if ($livro) {
    $estoque     = (int) $livro['estoque'];
    $vende_fisico = $livro['preco_fisico'] !== null;
    $tem_fisico  = $vende_fisico && $estoque > 0;
    $tem_digital = $livro['preco_digital'] !== null;
    $formato_padrao = $tem_fisico ? 'fisico' : 'digital';

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

            <?php if (!$tem_fisico && !$tem_digital): ?>
                <p class="vazio">Este livro está esgotado no momento.</p>
            <?php else: ?>
                <form class="compra" action="carrinho.php" method="post">
                    <input type="hidden" name="acao" value="adicionar">
                    <input type="hidden" name="id" value="<?= (int) $livro['id'] ?>">

                    <fieldset class="formatos">
                        <legend>Escolha o formato</legend>

                        <?php if ($vende_fisico): ?>
                        <label class="formato<?= $tem_fisico ? '' : ' indisponivel' ?>">
                            <input type="radio" name="formato" value="fisico"
                                <?= $tem_fisico ? '' : 'disabled' ?>
                                <?= $formato_padrao === 'fisico' ? 'checked' : '' ?>>
                            <span class="formato-nome">Livro físico</span>
                            <span class="formato-preco"><?= preco_html($livro['preco_fisico'], $livro['desconto_fisico']) ?></span>
                            <span class="formato-nota">
                                <?= $tem_fisico
                                    ? ($estoque === 1 ? 'Último exemplar em estoque' : $estoque . ' em estoque')
                                    : 'Esgotado' ?>
                            </span>
                        </label>
                        <?php endif; ?>

                        <?php if ($tem_digital): ?>
                            <label class="formato formato-digital">
                                <input type="radio" name="formato" value="digital"
                                    <?= $formato_padrao === 'digital' ? 'checked' : '' ?>>
                                <span class="formato-nome">E-book</span>
                                <span class="formato-preco"><?= preco_html($livro['preco_digital'], $livro['desconto_digital']) ?></span>
                                <span class="formato-nota">Sem frete. Download liberado após o pagamento.</span>
                            </label>
                        <?php endif; ?>
                    </fieldset>

                    <?php if ($tem_fisico): ?>
                        <div class="quantidade" data-so-fisico>
                            <label for="quantidade">Quantidade</label>
                            <input type="number" id="quantidade" name="quantidade"
                                   value="1" min="1" max="<?= $estoque ?>">
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="botao">Adicionar ao carrinho</button>
                </form>
            <?php endif; ?>
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
