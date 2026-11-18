<?php
// Funções usadas por todas as páginas. Inclua este arquivo antes de qualquer HTML.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/conexao.php';

const FRETE_FIXO = 12.90;

/** Escapa texto para exibir no HTML com segurança. */
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** Formata um valor como preço em reais: R$ 1.234,90 */
function preco($valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

/** Preço depois do desconto (0 a 100%). */
function preco_final($preco, $desconto): float
{
    $desconto = max(0, min(100, (int) $desconto));
    return round((float) $preco * (100 - $desconto) / 100, 2);
}

/**
 * Preço para exibir na loja. Com desconto mostra o preço antigo riscado,
 * o novo e um selo com a porcentagem.
 */
function preco_html($preco, $desconto): string
{
    $desconto = (int) $desconto;
    if ($desconto <= 0) {
        return preco($preco);
    }
    return '<span class="so-leitor">De </span><s class="preco-antigo">' . preco($preco) . '</s> '
         . '<span class="so-leitor">por </span><span class="preco-promo">' . preco(preco_final($preco, $desconto)) . '</span> '
         . '<span class="selo-desconto">−' . $desconto . '%</span>';
}

function redirecionar(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Usuário logado (['id', 'nome', 'email']) ou null. */
function usuario_logado(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function eh_admin(): bool
{
    return (usuario_logado()['tipo'] ?? '') === 'admin';
}

/** Barra quem não é administrador. Use no topo das páginas de admin. */
function exigir_admin(): void
{
    if (eh_admin()) {
        return;
    }
    if (!usuario_logado()) {
        $_SESSION['voltar'] = 'admin.php';
        avisar('Entre com a conta de administrador para gerenciar os livros.');
        redirecionar('login.php');
    }
    avisar('Só administradores podem gerenciar os livros.', 'erro');
    redirecionar('index.php');
}

/** Token contra envio de formulário por outros sites (CSRF). */
function token(): string
{
    if (empty($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['token'];
}

function token_valido(): bool
{
    return hash_equals(token(), (string) ($_POST['token'] ?? ''));
}

/** Converte "49,90", "1.234,90" ou "R$ 49.90" em número. Retorna null se vazio ou inválido. */
function ler_preco(?string $texto): ?float
{
    $texto = trim(str_replace(['R$', ' '], '', (string) $texto));
    if ($texto === '') {
        return null;
    }
    if (str_contains($texto, ',')) {
        $texto = str_replace(['.', ','], ['', '.'], $texto);
    }
    return is_numeric($texto) ? round((float) $texto, 2) : null;
}

/** Capa do livro: a imagem enviada ou, se não houver, uma capa desenhada na cor do gênero. */
function capa(array $livro, string $alt = ''): string
{
    if (!empty($livro['imagem'])) {
        return '<img src="imagens/' . e($livro['imagem']) . '" alt="' . e($alt) . '" loading="lazy">';
    }
    $estilo = isset($livro['genero_cor']) ? ' style="' . cores_genero($livro) . '"' : '';
    $rotulo = $alt !== '' ? ' role="img" aria-label="' . e($alt) . '"' : ' aria-hidden="true"';
    return '<span class="capa-desenhada"' . $estilo . $rotulo . '>'
         . '<span class="capa-desenhada-titulo">' . e($livro['titulo']) . '</span>'
         . '<span class="capa-desenhada-autor">' . e($livro['autor']) . '</span>'
         . '</span>';
}

/** Guarda uma mensagem para mostrar na próxima página. $tipo: 'ok' ou 'erro'. */
function avisar(string $mensagem, string $tipo = 'ok'): void
{
    $_SESSION['aviso'] = ['mensagem' => $mensagem, 'tipo' => $tipo];
}

/** Retorna e apaga a mensagem guardada. */
function pegar_aviso(): ?array
{
    $aviso = $_SESSION['aviso'] ?? null;
    unset($_SESSION['aviso']);
    return $aviso;
}

/** Só aceita endereços internos simples, para o "voltar" do login não levar a outro site. */
function endereco_seguro(?string $url): string
{
    if ($url !== null && preg_match('/^[a-z]+\.php(\?id=\d+)?$/', $url)) {
        return $url;
    }
    return 'index.php';
}

function buscar_generos(PDO $conexao): array
{
    $sql = "SELECT g.*, COUNT(l.id) AS total
              FROM generos g
              LEFT JOIN livros l ON l.genero_id = g.id
             GROUP BY g.id
             ORDER BY g.ordem";
    return $conexao->query($sql)->fetchAll();
}

/** Estilo inline com as cores do gênero, usado pelas lombadas e marcadores. */
function cores_genero(array $item): string
{
    $cor   = $item['genero_cor'] ?? $item['cor'];
    $texto = $item['genero_cor_texto'] ?? $item['cor_texto'];
    return '--cor:' . e($cor) . ';--cor-texto:' . e($texto) . ';';
}

/** Colunas padrão para listar livros junto com o gênero. */
const SQL_LIVROS = "SELECT l.*, g.nome AS genero_nome, g.slug AS genero_slug,
                           g.cor AS genero_cor, g.cor_texto AS genero_cor_texto
                      FROM livros l
                      JOIN generos g ON g.id = l.genero_id";

/* ---------- Carrinho (fica na sessão) ----------
   $_SESSION['carrinho'] = ['12-fisico' => 2, '4-digital' => 1, ...] */

function carrinho(): array
{
    return $_SESSION['carrinho'] ?? [];
}

function carrinho_quantidade(): int
{
    return array_sum(carrinho());
}

function chave_carrinho(int $id, string $formato): string
{
    return $id . '-' . $formato;
}
