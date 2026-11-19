<?php
require_once __DIR__ . '/includes/funcoes.php';

$usuario = usuario_logado();
if (!$usuario) {
    $_SESSION['voltar'] = 'pedidos.php';
    avisar('Entre na sua conta para ver seus pedidos.');
    redirecionar('login.php');
}
if (eh_admin()) {
    redirecionar('admin.php?acao=pedidos'); // o admin vê os pedidos de todos os clientes
}

/* Cancelar: só o dono do pedido, e só enquanto o pagamento ainda está sendo confirmado. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'cancelar') {
    $pedido_id = (int) ($_POST['id'] ?? 0);
    if (!token_valido()) {
        avisar('O formulário expirou. Tente de novo.', 'erro');
    } else {
        $cancelar = $conexao->prepare(
            "UPDATE pedidos SET status = 'cancelado'
              WHERE id = :id AND usuario_id = :usuario AND status = 'confirmando'"
        );
        $cancelar->execute(['id' => $pedido_id, 'usuario' => $usuario['id']]);
        if ($cancelar->rowCount() === 1) {
            avisar('Pedido nº ' . $pedido_id . ' cancelado. Nada foi cobrado.');
        } else {
            avisar('Só dá para cancelar pedidos que ainda estão em “Confirmando pagamento”.', 'erro');
        }
    }
    redirecionar('pedidos.php');
}

$pedidos = buscar_pedidos($conexao, (int) $usuario['id']);

/** Frase que explica a situação do pedido para o cliente. */
function explicar_status(array $pedido): string
{
    return match ($pedido['status']) {
        'enviado'   => 'Os ebooks foram enviados para ' . $pedido['email'] . '.',
        'cancelado' => 'Este pedido foi cancelado e nada foi cobrado.',
        default     => 'Estamos confirmando o pagamento. Assim que for aprovado, os ebooks são enviados para ' . $pedido['email'] . '.',
    };
}

$titulo  = 'Meus pedidos';
$estante = false; // página de conta: sem a estante de gêneros, o pedido usa a largura toda
require __DIR__ . '/includes/cabecalho.php';
?>

<section class="secao">
    <h1 class="secao-titulo">Meus pedidos</h1>

    <?php if (!$pedidos): ?>
        <p class="vazio">Você ainda não fez nenhum pedido. <a href="index.php">Explorar o catálogo</a></p>
    <?php else: ?>
        <p class="secao-info"><?= count($pedidos) ?> <?= count($pedidos) === 1 ? 'pedido' : 'pedidos' ?></p>

        <ol class="pedidos">
            <?php foreach ($pedidos as $pedido): ?>
                <?php $capa = $pedido['itens'][0] ?? null; $outros = count($pedido['itens']) - 1; ?>
                <li class="pedido pedido-<?= e($pedido['status']) ?>">
                    <div class="pedido-capa">
                        <?php if ($capa): ?>
                            <?= capa($capa) ?>
                        <?php endif; ?>
                        <?php if ($outros > 0): ?>
                            <span class="pedido-mais" aria-label="e mais <?= $outros ?> <?= $outros === 1 ? 'ebook' : 'ebooks' ?>">+<?= $outros ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="pedido-topo">
                        <div>
                            <h2 class="pedido-numero">Pedido nº <?= (int) $pedido['id'] ?></h2>
                            <p class="pedido-data"><?= e(data_pedido($pedido['criado_em'])) ?></p>
                        </div>
                        <?= status_html($pedido['status']) ?>
                    </div>

                    <p class="pedido-explicacao"><?= e(explicar_status($pedido)) ?></p>

                    <div class="pedido-rodape">
                        <ul class="pedido-itens">
                            <?php foreach ($pedido['itens'] as $item): ?>
                                <li>
                                    <span>
                                        <?php if ($item['livro_id']): ?>
                                            <a href="livro.php?id=<?= (int) $item['livro_id'] ?>"><?= e($item['titulo']) ?></a>
                                        <?php else: ?>
                                            <span class="pedido-titulo"><?= e($item['titulo']) ?></span>
                                        <?php endif; ?>
                                        <span class="pedido-autor"><?= e($item['autor']) ?></span>
                                    </span>
                                    <?php if (count($pedido['itens']) > 1): ?>
                                        <span class="pedido-preco"><?= preco($item['preco']) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <dl class="pedido-resumo">
                            <div><dt>Pagamento</dt><dd><?= e(FORMAS_PAGAMENTO[$pedido['pagamento']] ?? $pedido['pagamento']) ?></dd></div>
                            <div><dt>Total</dt><dd><?= preco($pedido['total']) ?></dd></div>
                        </dl>

                        <?php if ($pedido['status'] === 'confirmando'): ?>
                            <form class="pedido-cancelar" action="pedidos.php" method="post"
                                  data-confirmar="Cancelar o pedido nº <?= (int) $pedido['id'] ?>? Isso não pode ser desfeito.">
                                <input type="hidden" name="token" value="<?= e(token()) ?>">
                                <input type="hidden" name="acao" value="cancelar">
                                <input type="hidden" name="id" value="<?= (int) $pedido['id'] ?>">
                                <button type="submit" class="botao botao-cancelar">Cancelar pedido</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/rodape.php'; ?>
