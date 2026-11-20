<?php
/*
 * Cabeçalho comum. Variáveis que a página pode definir antes do include:
 *   $titulo        título da aba (string)
 *   $estante       mostrar a estante de gêneros à esquerda (bool)
 *   $generos       lista vinda de buscar_generos() (obrigatória se $estante)
 *   $genero_ativo  slug do gênero destacado na estante (string|null)
 *   $link_estante  prefixo dos links da estante: '#' na home, 'index.php#' nas outras
 *   $busca         texto atual da busca (string)
 */
$titulo       = isset($titulo) ? $titulo . ' | Lar de Letras' : 'Lar de Letras';
$estante      = $estante ?? false;
$genero_ativo = $genero_ativo ?? null;
$link_estante = $link_estante ?? 'index.php#';
$busca        = $busca ?? '';
$usuario      = usuario_logado();
$no_carrinho  = carrinho_quantidade();
$aviso        = pegar_aviso();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?></title>
    <link rel="icon" href="imagens/icons/icone.svg?v=2" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Literata:opsz,wght@7..72,400;7..72,600;7..72,700&display=swap">
    <link rel="stylesheet" href="css/estilo.css?v=24">
    <script src="js/script.js?v=11" defer></script>
</head>
<body>
<a class="pular" href="#conteudo">Pular para o conteúdo</a>

<header class="topo">
    <div class="topo-interno">
        <a class="marca" href="index.php">
            <!-- logotipo: casa com um livro aberto dentro, um "L" em cada página -->
            <svg class="marca-logo" viewBox="0 0 64 56" aria-hidden="true">
                <g fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 18.5V9h6v5.3"/>
                    <path d="M5 24.7 32 6l27 18.7"/>
                    <path d="M10 21.2V47h18q4 3 8 0h18V21.2"/>
                    <path d="M32 27.5q-8-4.5-18-1.5v18.5q10-3 18 1.5q8-4.5 18-1.5V26q-10-3-18 1.5V46"/>
                </g>
                <g fill="currentColor" font-family="Literata, Georgia, serif" font-weight="600" font-size="13" text-anchor="middle">
                    <text x="23.5" y="41">L</text>
                    <text x="40.5" y="41">L</text>
                </g>
            </svg>
            <span class="marca-nome">Lar de Letras</span>
        </a>

        <form class="busca" action="index.php" method="get" role="search">
            <label for="campo-busca" class="so-leitor">Buscar livros</label>
            <input type="search" id="campo-busca" name="busca"
                   placeholder="Buscar por título ou autor" value="<?= e($busca) ?>">
            <button type="submit" aria-label="Buscar">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M15.5 15.5 21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
        </form>

        <nav class="conta" aria-label="Sua conta">
            <?php if ($usuario): ?>
                <span class="conta-nome">Olá, <?= e(strtok($usuario['nome'], ' ')) ?></span>
                <?php if (eh_admin()): ?>
                    <a href="admin.php">Gerenciar livros</a>
                <?php else: ?>
                    <a href="pedidos.php">Meus pedidos</a>
                <?php endif; ?>
                <a href="sair.php">Sair</a>
            <?php else: ?>
                <a href="login.php">Entrar</a>
            <?php endif; ?>

            <a class="sacola" href="carrinho.php"
               aria-label="Carrinho, <?= $no_carrinho ?> <?= $no_carrinho === 1 ? 'item' : 'itens' ?>">
                <svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path d="M5 8h14l-1 13H6L5 8Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 10V6a3 3 0 0 1 6 0v4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                <?php if ($no_carrinho > 0): ?>
                    <span class="sacola-total"><?= $no_carrinho ?></span>
                <?php endif; ?>
            </a>
        </nav>
    </div>
</header>

<?php if ($aviso): ?>
    <div class="aviso aviso-<?= e($aviso['tipo']) ?>" role="status">
        <p><?= e($aviso['mensagem']) ?></p>
    </div>
<?php endif; ?>

<div class="pagina<?= $estante ? ' com-estante' : '' ?>">
    <?php if ($estante): ?>
        <nav class="estante" aria-label="Gêneros">
            <h2 class="estante-titulo">Gêneros</h2>
            <ol class="pilha">
                <?php foreach ($generos as $genero): ?>
                    <?php if ((int) $genero['total'] === 0) continue; // gênero ainda sem livros não aparece na loja ?>
                    <li>
                        <a class="lombada<?= $genero['slug'] === $genero_ativo ? ' ativa' : '' ?>"
                           href="<?= e($link_estante . $genero['slug']) ?>"
                           data-genero="<?= e($genero['slug']) ?>">
                            <span class="lombada-nome"><?= e($genero['nome']) ?></span>
                            <span class="lombada-total" aria-label="<?= (int) $genero['total'] ?> livros"><?= (int) $genero['total'] ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
            <div class="tabua" aria-hidden="true"></div>
        </nav>
    <?php endif; ?>

    <main id="conteudo" class="conteudo">
