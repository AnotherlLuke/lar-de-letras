<?php
require_once __DIR__ . '/includes/funcoes.php';

/** Busca um livro pelo id (ou null). */
function buscar_livro(PDO $conexao, int $id): ?array
{
    $consulta = $conexao->prepare("SELECT * FROM livros WHERE id = :id");
    $consulta->execute(['id' => $id]);
    return $consulta->fetch() ?: null;
}

/** Lê o carrinho da sessão e calcula os itens e o total. */
function montar_carrinho(PDO $conexao): array
{
    $itens = [];
    $total = 0.0;
    $ids   = array_keys(carrinho());
    $_SESSION['carrinho'] = [];

    if ($ids) {
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $consulta = $conexao->prepare("SELECT * FROM livros WHERE id IN ($marcadores) ORDER BY titulo");
        $consulta->execute($ids);
        $livros = $consulta->fetchAll();

        // livro que saiu do catálogo sai também do carrinho
        $_SESSION['carrinho'] = array_fill_keys(array_map(fn($l) => (int) $l['id'], $livros), true);

        foreach ($livros as $livro) {
            $preco  = preco_final($livro['preco'], $livro['desconto']);
            $total += $preco;
            $itens[] = ['livro' => $livro, 'preco' => $preco, 'desconto' => (int) $livro['desconto']];
        }
    }

    return [$itens, $total];
}

/* ---------- Ações (sempre via POST, depois redireciona) ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $_SESSION['carrinho'] = carrinho();

    if ($acao === 'adicionar') {
        $livro = buscar_livro($conexao, (int) ($_POST['id'] ?? 0));
        if (!$livro) {
            avisar('Esse livro não existe mais no catálogo.', 'erro');
            redirecionar('index.php');
        }

        $id = (int) $livro['id'];
        if (isset($_SESSION['carrinho'][$id])) {
            avisar('“' . $livro['titulo'] . '” já está no seu carrinho.');
        } else {
            $_SESSION['carrinho'][$id] = true;
            avisar('“' . $livro['titulo'] . '” adicionado ao carrinho.');
        }
        redirecionar('carrinho.php');
    }

    if ($acao === 'remover') {
        unset($_SESSION['carrinho'][(int) ($_POST['id'] ?? 0)]);
        avisar('Item removido do carrinho.');
        redirecionar('carrinho.php');
    }

    if ($acao === 'finalizar') {
        if (!$_SESSION['carrinho']) {
            redirecionar('carrinho.php');
        }
        $pagamento = $_POST['pagamento'] ?? '';
        if (!isset(FORMAS_PAGAMENTO[$pagamento])) {
            avisar('Escolha a forma de pagamento: Pix ou cartão de crédito.', 'erro');
            redirecionar('carrinho.php');
        }
        $_SESSION['pagamento'] = $pagamento; // continua marcada se precisar entrar na conta antes

        $usuario = usuario_logado();
        if (!$usuario) {
            $_SESSION['voltar'] = 'carrinho.php';
            avisar('Entre na sua conta para finalizar a compra. Seus livros continuam no carrinho.');
            redirecionar('login.php');
        }
        // A conta do administrador gerencia a loja, não compra (e não tem Meus pedidos).
        if (eh_admin()) {
            avisar('A conta de administrador não faz compras. Entre com uma conta de cliente para finalizar.', 'erro');
            redirecionar('carrinho.php');
        }

        // Protótipo: grava o pedido, mas não cobra nada e não envia email de verdade.
        [$itens, $total] = montar_carrinho($conexao);
        if (!$itens) {
            redirecionar('carrinho.php');
        }

        $conexao->beginTransaction();
        try {
            $conexao->prepare(
                "INSERT INTO pedidos (usuario_id, email, pagamento, total) VALUES (:usuario, :email, :pagamento, :total)"
            )->execute([
                'usuario'   => $usuario['id'],
                'email'     => $usuario['email'],
                'pagamento' => $pagamento,
                'total'     => $total,
            ]);
            $pedido_id = (int) $conexao->lastInsertId();

            $gravar_item = $conexao->prepare(
                "INSERT INTO pedido_itens (pedido_id, livro_id, titulo, autor, preco)
                 VALUES (:pedido, :livro, :titulo, :autor, :preco)"
            );
            foreach ($itens as $item) {
                $gravar_item->execute([
                    'pedido' => $pedido_id,
                    'livro'  => $item['livro']['id'],
                    'titulo' => $item['livro']['titulo'],
                    'autor'  => $item['livro']['autor'],
                    'preco'  => $item['preco'],
                ]);
            }
            $conexao->commit();
        } catch (PDOException $erro) {
            $conexao->rollBack();
            error_log($erro->getMessage());
            avisar('Não foi possível finalizar a compra. Tente de novo.', 'erro');
            redirecionar('carrinho.php');
        }

        $_SESSION['pedido_recebido'] = [
            'numero' => $pedido_id,
            'nome'   => strtok($usuario['nome'], ' '),
            'email'  => $usuario['email'],
            'total'  => $total,
            'titulos' => array_map(fn($item) => $item['livro']['titulo'], $itens),
            'pagamento' => FORMAS_PAGAMENTO[$pagamento],
        ];
        unset($_SESSION['pagamento']);
        unset($_SESSION['carrinho']);
        redirecionar('carrinho.php');
    }

    redirecionar('carrinho.php');
}

[$itens, $total] = montar_carrinho($conexao);

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
            <h2>Pedido Realizado!</h2>
            <p class="confirmacao-destaque">Seus Ebooks serão enviados no seu email.</p>
            <dl class="confirmacao-detalhes">
                <div><dt>Pedido</dt><dd>nº <?= (int) $pedido['numero'] ?> · <?= status_html('confirmando') ?></dd></div>
                <div><dt>Email</dt><dd><?= e($pedido['email']) ?></dd></div>
                <div><dt><?= count($pedido['titulos']) === 1 ? 'Ebook' : 'Ebooks' ?></dt><dd><?= e(implode(', ', $pedido['titulos'])) ?></dd></div>
                <div><dt>Pagamento</dt><dd><?= e($pedido['pagamento']) ?></dd></div>
                <div><dt>Total</dt><dd><?= preco($pedido['total']) ?></dd></div>
            </dl>
            <p class="confirmacao-nota">Como este site é um protótipo, nenhum pagamento foi cobrado e nenhum email foi enviado de verdade.</p>
            <div class="confirmacao-botoes">
                <a class="botao" href="pedidos.php">Ver meus pedidos</a>
                <a class="botao botao-contorno" href="index.php">Voltar ao catálogo</a>
            </div>
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
                            <?php if ($item['desconto'] > 0): ?>
                                <p class="item-promo"><span class="selo-desconto"><?= $item['desconto'] ?>% de desconto</span></p>
                            <?php endif; ?>
                        </div>

                        <p class="item-preco"><?= preco($item['preco']) ?></p>

                        <form action="carrinho.php" method="post" class="item-remover">
                            <input type="hidden" name="acao" value="remover">
                            <input type="hidden" name="id" value="<?= (int) $livro['id'] ?>">
                            <button type="submit" class="botao-texto">Remover</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>

            <aside class="resumo" aria-labelledby="titulo-resumo">
                <h2 id="titulo-resumo">Resumo</h2>
                <dl>
                    <div><dt><?= count($itens) ?> <?= count($itens) === 1 ? 'ebook' : 'ebooks' ?></dt><dd><?= preco($total) ?></dd></div>
                    <div class="resumo-total"><dt>Total</dt><dd><?= preco($total) ?></dd></div>
                </dl>
                <p class="resumo-nota">
                    <?php if (usuario_logado() && !eh_admin()): ?>
                        Os ebooks são enviados para <strong><?= e(usuario_logado()['email']) ?></strong> assim que o pagamento é confirmado.
                    <?php else: ?>
                        Os ebooks são enviados para o email da sua conta assim que o pagamento é confirmado.
                    <?php endif; ?>
                </p>
                <form action="carrinho.php" method="post">
                    <input type="hidden" name="acao" value="finalizar">
                    <fieldset class="pagamento">
                        <legend>Forma de pagamento</legend>
                        <?php foreach (FORMAS_PAGAMENTO as $valor => $nome): ?>
                            <label class="pagamento-opcao">
                                <input type="radio" name="pagamento" value="<?= e($valor) ?>" required
                                    <?= ($_SESSION['pagamento'] ?? '') === $valor ? 'checked' : '' ?>>
                                <?= e($nome) ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
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
