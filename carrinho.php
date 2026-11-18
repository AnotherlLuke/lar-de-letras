<?php
require_once __DIR__ . '/includes/funcoes.php';

/** Busca um livro pelo id (ou null). */
function buscar_livro(PDO $conexao, int $id): ?array
{
    $consulta = $conexao->prepare("SELECT * FROM livros WHERE id = :id");
    $consulta->execute(['id' => $id]);
    return $consulta->fetch() ?: null;
}

/** Lê o carrinho da sessão e calcula itens, subtotal, frete e total. */
function montar_carrinho(PDO $conexao): array
{
    $itens      = [];
    $subtotal   = 0.0;
    $tem_fisico = false;
    $carrinho   = carrinho();

    if ($carrinho) {
        $ids = array_values(array_unique(array_map(fn($chave) => (int) $chave, array_keys($carrinho))));
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $consulta = $conexao->prepare("SELECT * FROM livros WHERE id IN ($marcadores)");
        $consulta->execute($ids);
        $livros = [];
        foreach ($consulta->fetchAll() as $linha) {
            $livros[(int) $linha['id']] = $linha;
        }

        foreach ($carrinho as $chave => $quantidade) {
            [$id, $formato] = explode('-', $chave);
            $livro = $livros[(int) $id] ?? null;
            if (!$livro) {
                unset($_SESSION['carrinho'][$chave]); // livro saiu do catálogo
                continue;
            }
            $digital    = $formato === 'digital';
            $unitario   = $digital
                ? preco_final($livro['preco_digital'], $livro['desconto_digital'])
                : preco_final($livro['preco_fisico'], $livro['desconto_fisico']);
            $desconto   = (int) ($digital ? $livro['desconto_digital'] : $livro['desconto_fisico']);
            $item_total = $unitario * $quantidade;
            $subtotal  += $item_total;
            $tem_fisico = $tem_fisico || !$digital;

            $itens[] = compact('chave', 'livro', 'digital', 'quantidade', 'unitario', 'item_total', 'desconto');
        }
    }

    $frete = $tem_fisico ? FRETE_FIXO : 0.0;
    return [$itens, $subtotal, $tem_fisico, $frete, $subtotal + $frete];
}

/* ---------- Ações (sempre via POST, depois redireciona) ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $_SESSION['carrinho'] = carrinho();

    if ($acao === 'adicionar') {
        $livro   = buscar_livro($conexao, (int) ($_POST['id'] ?? 0));
        $formato = ($_POST['formato'] ?? '') === 'digital' ? 'digital' : 'fisico';

        if (!$livro) {
            avisar('Esse livro não existe mais no catálogo.', 'erro');
            redirecionar('index.php');
        }

        $chave  = chave_carrinho((int) $livro['id'], $formato);
        $voltar = 'livro.php?id=' . (int) $livro['id'];

        if ($formato === 'digital') {
            if ($livro['preco_digital'] === null) {
                avisar('“' . $livro['titulo'] . '” não tem versão digital.', 'erro');
                redirecionar($voltar);
            }
            if (isset($_SESSION['carrinho'][$chave])) {
                avisar('O e-book de “' . $livro['titulo'] . '” já está no seu carrinho.');
            } else {
                $_SESSION['carrinho'][$chave] = 1;
                avisar('E-book de “' . $livro['titulo'] . '” adicionado ao carrinho.');
            }
        } else {
            $estoque = (int) $livro['estoque'];
            if ($livro['preco_fisico'] === null) {
                avisar('“' . $livro['titulo'] . '” é vendido só como e-book.', 'erro');
                redirecionar($voltar);
            }
            if ($estoque === 0) {
                avisar('O livro físico de “' . $livro['titulo'] . '” está esgotado.', 'erro');
                redirecionar($voltar);
            }
            $atual   = $_SESSION['carrinho'][$chave] ?? 0;
            $pedida  = max(1, (int) ($_POST['quantidade'] ?? 1));
            $nova    = min($atual + $pedida, $estoque);
            $_SESSION['carrinho'][$chave] = $nova;

            if ($atual + $pedida > $estoque) {
                avisar('Temos só ' . $estoque . ' exemplares de “' . $livro['titulo'] . '”. Seu carrinho ficou com ' . $nova . '.', 'erro');
            } else {
                avisar('“' . $livro['titulo'] . '” adicionado ao carrinho.');
            }
        }
        redirecionar('carrinho.php');
    }

    if ($acao === 'atualizar') {
        $chave = $_POST['chave'] ?? '';
        if (preg_match('/^(\d+)-fisico$/', $chave, $partes) && isset($_SESSION['carrinho'][$chave])) {
            $livro      = buscar_livro($conexao, (int) $partes[1]);
            $quantidade = (int) ($_POST['quantidade'] ?? 1);

            if (!$livro || $quantidade <= 0) {
                unset($_SESSION['carrinho'][$chave]);
                avisar('Item removido do carrinho.');
            } elseif ($quantidade > (int) $livro['estoque']) {
                $_SESSION['carrinho'][$chave] = (int) $livro['estoque'];
                avisar('Temos só ' . (int) $livro['estoque'] . ' exemplares de “' . $livro['titulo'] . '”.', 'erro');
            } else {
                $_SESSION['carrinho'][$chave] = $quantidade;
                avisar('Quantidade atualizada.');
            }
        }
        redirecionar('carrinho.php');
    }

    if ($acao === 'remover') {
        unset($_SESSION['carrinho'][$_POST['chave'] ?? '']);
        avisar('Item removido do carrinho.');
        redirecionar('carrinho.php');
    }

    if ($acao === 'finalizar') {
        if (!$_SESSION['carrinho']) {
            redirecionar('carrinho.php');
        }
        $usuario = usuario_logado();
        if (!$usuario) {
            $_SESSION['voltar'] = 'carrinho.php';
            avisar('Entre na sua conta para finalizar a compra. Seus livros continuam no carrinho.');
            redirecionar('login.php');
        }
        // Protótipo: não grava pedido nem cobra nada, só confirma e esvazia o carrinho.
        $total = montar_carrinho($conexao)[4];
        $_SESSION['pedido_recebido'] = [
            'nome'  => strtok($usuario['nome'], ' '),
            'total' => $total,
        ];
        unset($_SESSION['carrinho']);
        redirecionar('carrinho.php');
    }

    redirecionar('carrinho.php');
}

[$itens, $subtotal, $tem_fisico, $frete, $total] = montar_carrinho($conexao);

$pedido = $_SESSION['pedido_recebido'] ?? null;
unset($_SESSION['pedido_recebido']);

$titulo  = 'Carrinho';
$generos = buscar_generos($conexao);
$estante = true;
require __DIR__ . '/includes/cabecalho.php';
?>

<section class="secao carrinho">
    <h1 class="secao-titulo">Seu carrinho</h1>

    <?php if ($pedido): ?>

        <div class="confirmacao">
            <h2>Pedido recebido, <?= e($pedido['nome']) ?>.</h2>
            <p>Total de <?= preco($pedido['total']) ?>. Como este site é um protótipo, nenhum pagamento foi cobrado e nada será enviado.</p>
            <a class="botao" href="index.php">Voltar ao catálogo</a>
        </div>

    <?php elseif (!$itens): ?>

        <p class="vazio">Seu carrinho está vazio. <a href="index.php">Explorar o catálogo</a></p>

    <?php else: ?>

        <div class="carrinho-grade">
            <ul class="itens">
                <?php foreach ($itens as $item): $livro = $item['livro']; ?>
                    <li class="item">
                        <a class="item-capa" href="livro.php?id=<?= (int) $livro['id'] ?>" tabindex="-1" aria-hidden="true">
                            <?= capa($livro) ?>
                        </a>

                        <div class="item-info">
                            <h2 class="item-titulo"><a href="livro.php?id=<?= (int) $livro['id'] ?>"><?= e($livro['titulo']) ?></a></h2>
                            <p class="item-autor"><?= e($livro['autor']) ?></p>
                            <p class="item-formato<?= $item['digital'] ? ' digital' : '' ?>">
                                <?= $item['digital'] ? 'E-book, sem frete' : 'Livro físico' ?>
                                <?php if ($item['desconto'] > 0): ?>
                                    <span class="selo-desconto"><?= $item['desconto'] ?>% de desconto</span>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="item-quantidade">
                            <?php if ($item['digital']): ?>
                                <span class="item-unico">1 cópia</span>
                            <?php else: ?>
                                <form action="carrinho.php" method="post" class="form-quantidade">
                                    <input type="hidden" name="acao" value="atualizar">
                                    <input type="hidden" name="chave" value="<?= e($item['chave']) ?>">
                                    <label for="qtd-<?= e($item['chave']) ?>">Quantidade</label>
                                    <input type="number" id="qtd-<?= e($item['chave']) ?>" name="quantidade"
                                           value="<?= (int) $item['quantidade'] ?>" min="0" max="<?= (int) $livro['estoque'] ?>">
                                    <button type="submit" class="botao-texto atualizar">Atualizar</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <p class="item-preco">
                            <?= preco($item['item_total']) ?>
                            <?php if ($item['quantidade'] > 1): ?>
                                <span><?= preco($item['unitario']) ?> cada</span>
                            <?php endif; ?>
                        </p>

                        <form action="carrinho.php" method="post" class="item-remover">
                            <input type="hidden" name="acao" value="remover">
                            <input type="hidden" name="chave" value="<?= e($item['chave']) ?>">
                            <button type="submit" class="botao-texto">Remover</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>

            <aside class="resumo" aria-labelledby="titulo-resumo">
                <h2 id="titulo-resumo">Resumo</h2>
                <dl>
                    <div><dt>Subtotal</dt><dd><?= preco($subtotal) ?></dd></div>
                    <div><dt>Frete</dt><dd><?= $tem_fisico ? preco($frete) : 'Sem frete' ?></dd></div>
                    <div class="resumo-total"><dt>Total</dt><dd><?= preco($total) ?></dd></div>
                </dl>
                <?php if ($tem_fisico): ?>
                    <p class="resumo-nota">Frete fixo para todo o Brasil. E-books não pagam frete.</p>
                <?php endif; ?>
                <form action="carrinho.php" method="post">
                    <input type="hidden" name="acao" value="finalizar">
                    <button type="submit" class="botao botao-largo">Finalizar compra</button>
                </form>
                <?php if (!usuario_logado()): ?>
                    <p class="resumo-nota">Você vai entrar na sua conta antes de finalizar.</p>
                <?php endif; ?>
            </aside>
        </div>

    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/rodape.php'; ?>
