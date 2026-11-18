<?php
require_once __DIR__ . '/includes/funcoes.php';
exigir_admin();

const CAPA_TAMANHO_MAXIMO = 2 * 1024 * 1024; // 2 MB
const CAPA_TIPOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

$generos = buscar_generos($conexao);
$ids_generos = array_map(fn($g) => (int) $g['id'], $generos);

$acao_get = $_GET['acao'] ?? '';
$modo     = in_array($acao_get, ['novo', 'editar', 'generos', 'banner'], true) ? $acao_get : 'lista';
$erros    = [];
$livro_editado = null;

$dados = [
    'titulo' => '', 'autor' => '', 'genero_id' => '', 'novo_genero' => '', 'ano' => '', 'paginas' => '',
    'idioma' => 'Português', 'descricao' => '',
    'vende_fisico' => true, 'preco_fisico' => '', 'desconto_fisico' => '0', 'estoque' => '1',
    'vende_digital' => false, 'preco_digital' => '', 'desconto_digital' => '0',
    'lancamento' => false, 'mais_vendido' => false, 'banner' => false,
];

function buscar_livro_admin(PDO $conexao, int $id): ?array
{
    $consulta = $conexao->prepare("SELECT * FROM livros WHERE id = :id");
    $consulta->execute(['id' => $id]);
    return $consulta->fetch() ?: null;
}

/** Valor do banco para o formulário: 49.9 -> "49,90" */
function preco_campo($valor): string
{
    return $valor === null ? '' : number_format((float) $valor, 2, ',', '');
}

/** Apaga a capa só se ela foi enviada pelo admin (as do catálogo inicial ficam). */
function apagar_capa_enviada(?string $imagem): void
{
    if ($imagem && str_starts_with($imagem, 'enviadas/')) {
        $arquivo = __DIR__ . '/imagens/' . $imagem;
        if (is_file($arquivo)) {
            unlink($arquivo);
        }
    }
}

/** Texto sem acentos, minúsculo e com hífens: "Ficção científica!" -> "ficcao-cientifica" */
function slug(string $texto): string
{
    $texto = strtr($texto, [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','ë'=>'e',
        'í'=>'i','î'=>'i','ì'=>'i','ï'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ò'=>'o','ö'=>'o',
        'ú'=>'u','û'=>'u','ù'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
        'Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e','Í'=>'i','Ó'=>'o','Ô'=>'o',
        'Õ'=>'o','Ú'=>'u','Ç'=>'c','Ñ'=>'n',
    ]);
    $texto = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $texto));
    return substr(trim($texto, '-'), 0, 60);
}

/** Nome de arquivo seguro para a capa: "O Cortiço!" -> "o-cortico" */
function nome_arquivo(string $texto): string
{
    return slug($texto) ?: 'livro';
}

/* ---------- Gêneros ---------- */

// Ids que já existem na página inicial; um gênero não pode usar o mesmo endereço.
const SLUGS_RESERVADOS = ['catalogo', 'conteudo', 'campo-busca', 'lancamentos', 'vendidos'];

/** Mensagem de erro do nome do gênero, ou null se estiver certo. */
function erro_nome_genero(string $nome): ?string
{
    if ($nome === '') {
        return 'Informe o nome do gênero.';
    }
    if (!preg_match('/^.{1,60}$/u', $nome)) {
        return 'O nome do gênero pode ter até 60 letras.';
    }
    return null;
}

/** Id do gênero com esse nome (sem diferença de maiúsculas e acentos), ou null. */
function genero_com_nome(PDO $conexao, string $nome): ?int
{
    $consulta = $conexao->prepare("SELECT id FROM generos WHERE nome = :nome");
    $consulta->execute(['nome' => $nome]);
    $id = $consulta->fetchColumn();
    return $id === false ? null : (int) $id;
}

/** Cria o gênero no fim da estante e devolve o id. */
function criar_genero(PDO $conexao, string $nome): int
{
    $base = slug($nome) ?: 'genero';
    $slug = $base;
    $existe = $conexao->prepare("SELECT COUNT(*) FROM generos WHERE slug = :slug");
    for ($n = 2; ; $n++) {
        $existe->execute(['slug' => $slug]);
        if (!in_array($slug, SLUGS_RESERVADOS, true) && !$existe->fetchColumn()) {
            break;
        }
        $slug = substr($base, 0, 55) . '-' . $n;
    }

    $ordem = (int) $conexao->query("SELECT COALESCE(MAX(ordem), 0) FROM generos")->fetchColumn() + 1;
    $conexao->prepare("INSERT INTO generos (nome, slug, ordem) VALUES (:nome, :slug, :ordem)")
            ->execute(['nome' => $nome, 'slug' => $slug, 'ordem' => min($ordem, 255)]);
    return (int) $conexao->lastInsertId();
}

/** Move o gênero uma posição para cima (-1) ou para baixo (+1) na estante. */
function mover_genero(PDO $conexao, int $id, int $passo): void
{
    $ids = $conexao->query("SELECT id FROM generos ORDER BY ordem, id")->fetchAll(PDO::FETCH_COLUMN);
    $ids = array_map('intval', $ids);
    $atual = array_search($id, $ids, true);
    $destino = $atual === false ? false : $atual + $passo;
    if ($destino === false || !isset($ids[$destino])) {
        return;
    }
    [$ids[$atual], $ids[$destino]] = [$ids[$destino], $ids[$atual]];

    // renumera a estante inteira (1, 2, 3...) para a ordem nunca ficar repetida
    $salvar = $conexao->prepare("UPDATE generos SET ordem = :ordem WHERE id = :id");
    foreach ($ids as $posicao => $genero_id) {
        $salvar->execute(['ordem' => min($posicao + 1, 255), 'id' => $genero_id]);
    }
}

/** Lê um desconto de 0 a 100. Vazio vale 0. Retorna false se inválido. */
function ler_desconto(string $texto)
{
    $texto = trim(str_replace('%', '', $texto));
    if ($texto === '') {
        return 0;
    }
    return filter_var($texto, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
}

/* ---------- Banner ---------- */

/** Próxima posição livre no fim do carrossel. */
function proxima_ordem_banner(PDO $conexao): int
{
    $maior = (int) $conexao->query("SELECT COALESCE(MAX(banner_ordem), 0) FROM livros WHERE banner = 1")->fetchColumn();
    return min($maior + 1, 255);
}

/** Livros do banner, na ordem do carrossel. */
function slides_banner(PDO $conexao): array
{
    return ordenar_banner($conexao->query(SQL_LIVROS . " WHERE l.banner = 1")->fetchAll());
}

/** Move o slide uma posição para a esquerda (-1) ou para a direita (+1) no carrossel. */
function mover_slide(PDO $conexao, int $id, int $passo): void
{
    $ids = array_map(fn($l) => (int) $l['id'], slides_banner($conexao));
    $atual = array_search($id, $ids, true);
    $destino = $atual === false ? false : $atual + $passo;
    if ($destino === false || !isset($ids[$destino])) {
        return;
    }
    [$ids[$atual], $ids[$destino]] = [$ids[$destino], $ids[$atual]];

    $salvar = $conexao->prepare("UPDATE livros SET banner_ordem = :ordem WHERE id = :id");
    foreach ($ids as $posicao => $livro_id) {
        $salvar->execute(['ordem' => min($posicao + 1, 255), 'id' => $livro_id]);
    }
}

/* ---------- Abrir um livro para edição ---------- */

if ($modo === 'editar') {
    $livro_editado = buscar_livro_admin($conexao, (int) ($_GET['id'] ?? 0));
    if (!$livro_editado) {
        avisar('Esse livro não existe mais no catálogo.', 'erro');
        redirecionar('admin.php');
    }
    $dados = [
        'titulo'           => $livro_editado['titulo'],
        'autor'            => $livro_editado['autor'],
        'genero_id'        => (string) $livro_editado['genero_id'],
        'ano'              => (string) ($livro_editado['ano'] ?? ''),
        'paginas'          => (string) ($livro_editado['paginas'] ?? ''),
        'idioma'           => $livro_editado['idioma'],
        'descricao'        => (string) ($livro_editado['descricao'] ?? ''),
        'vende_fisico'     => $livro_editado['preco_fisico'] !== null,
        'preco_fisico'     => preco_campo($livro_editado['preco_fisico']),
        'desconto_fisico'  => (string) $livro_editado['desconto_fisico'],
        'estoque'          => (string) $livro_editado['estoque'],
        'vende_digital'    => $livro_editado['preco_digital'] !== null,
        'preco_digital'    => preco_campo($livro_editado['preco_digital']),
        'desconto_digital' => (string) $livro_editado['desconto_digital'],
        'lancamento'       => (bool) $livro_editado['lancamento'],
        'mais_vendido'     => (bool) $livro_editado['mais_vendido'],
        'banner'           => (bool) $livro_editado['banner'],
    ];
}

/* ---------- Ações ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!token_valido()) {
        avisar('O formulário expirou. Tente de novo.', 'erro');
        redirecionar('admin.php');
    }

    $acao = $_POST['acao'] ?? '';

    /* Banner: escolher os livros, a cor, a chamada, a descrição e a ordem dos slides */
    if (str_starts_with($acao, 'banner_')) {
        $consulta = $conexao->prepare(SQL_LIVROS . " WHERE l.id = :id");
        $consulta->execute(['id' => (int) ($_POST['id'] ?? 0)]);
        $slide = $consulta->fetch();

        if (!$slide) {
            avisar('Escolha um livro do catálogo.', 'erro');
        } elseif ($acao === 'banner_adicionar') {
            if (!$slide['banner']) {
                $conexao->prepare("UPDATE livros SET banner = 1, banner_ordem = :ordem WHERE id = :id")
                        ->execute(['ordem' => proxima_ordem_banner($conexao), 'id' => $slide['id']]);
            }
            avisar('“' . $slide['titulo'] . '” entrou no fim do banner.');
        } elseif ($acao === 'banner_salvar') {
            $cor      = strtoupper(trim((string) ($_POST['cor'] ?? '')));
            $chamada  = trim((string) ($_POST['chamada'] ?? ''));
            $descricao = trim((string) ($_POST['descricao'] ?? ''));

            if (!preg_match('/^#[0-9A-F]{6}$/', $cor)) {
                avisar('Escolha a cor do fundo do slide.', 'erro');
            } elseif (!preg_match('/^.{0,120}$/u', $chamada)) {
                avisar('A chamada pode ter até 120 letras.', 'erro');
            } else {
                // a cor do gênero não precisa ser guardada: assim o slide acompanha o gênero
                $usar_genero = isset($_POST['usar_cor_genero']) || $cor === strtoupper($slide['genero_cor']);
                $conexao->prepare("UPDATE livros SET banner_cor = :cor, banner_chamada = :chamada, banner_descricao = :descricao
                                    WHERE id = :id")
                        ->execute([
                            'cor'       => $usar_genero ? null : $cor,
                            'chamada'   => $chamada !== '' ? $chamada : null,
                            'descricao' => $descricao !== '' ? $descricao : null,
                            'id'        => $slide['id'],
                        ]);
                avisar('Slide de “' . $slide['titulo'] . '” salvo.');
            }
        } elseif ($acao === 'banner_esquerda' || $acao === 'banner_direita') {
            mover_slide($conexao, (int) $slide['id'], $acao === 'banner_esquerda' ? -1 : 1);
        } elseif ($acao === 'banner_remover') {
            $conexao->prepare("UPDATE livros SET banner = 0 WHERE id = :id")->execute(['id' => $slide['id']]);
            avisar('“' . $slide['titulo'] . '” saiu do banner. O livro continua no catálogo.');
        }
        redirecionar('admin.php?acao=banner');
    }

    /* Gêneros: adicionar, renomear, mudar de lugar na estante e remover */
    if (str_starts_with($acao, 'genero_')) {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $genero = null;
        foreach ($generos as $g) {
            if ((int) $g['id'] === (int) ($_POST['id'] ?? 0)) {
                $genero = $g;
            }
        }

        if ($acao === 'genero_adicionar') {
            $erro = erro_nome_genero($nome);
            if (!$erro && genero_com_nome($conexao, $nome)) {
                $erro = 'Já existe um gênero chamado “' . $nome . '”.';
            }
            if ($erro) {
                avisar($erro, 'erro');
            } else {
                criar_genero($conexao, $nome);
                avisar('O gênero “' . $nome . '” foi adicionado ao fim da estante.');
            }
        } elseif (!$genero) {
            avisar('Esse gênero não existe mais.', 'erro');
        } elseif ($acao === 'genero_renomear') {
            $erro = erro_nome_genero($nome);
            $outro = $erro ? null : genero_com_nome($conexao, $nome);
            if (!$erro && $outro && $outro !== (int) $genero['id']) {
                $erro = 'Já existe um gênero chamado “' . $nome . '”.';
            }
            if ($erro) {
                avisar($erro, 'erro');
            } else {
                $conexao->prepare("UPDATE generos SET nome = :nome WHERE id = :id")
                        ->execute(['nome' => $nome, 'id' => $genero['id']]);
                avisar('“' . $genero['nome'] . '” agora se chama “' . $nome . '”.');
            }
        } elseif ($acao === 'genero_subir' || $acao === 'genero_descer') {
            mover_genero($conexao, (int) $genero['id'], $acao === 'genero_subir' ? -1 : 1);
        } elseif ($acao === 'genero_remover') {
            if ((int) $genero['total'] > 0) {
                avisar('“' . $genero['nome'] . '” ainda tem livros. Mude o gênero deles ou remova os livros antes.', 'erro');
            } else {
                $conexao->prepare("DELETE FROM generos WHERE id = :id")->execute(['id' => $genero['id']]);
                avisar('O gênero “' . $genero['nome'] . '” foi removido.');
            }
        }
        redirecionar('admin.php?acao=generos');
    }

    if ($acao === 'remover') {
        $livro = buscar_livro_admin($conexao, (int) ($_POST['id'] ?? 0));
        if ($livro) {
            $conexao->prepare("DELETE FROM livros WHERE id = :id")->execute(['id' => $livro['id']]);
            apagar_capa_enviada($livro['imagem']);
            avisar('“' . $livro['titulo'] . '” foi removido do catálogo.');
        }
        redirecionar('admin.php');
    }

    if ($acao === 'adicionar' || $acao === 'salvar') {
        if ($acao === 'salvar' && !$livro_editado) {
            redirecionar('admin.php');
        }

        foreach (['titulo', 'autor', 'genero_id', 'novo_genero', 'ano', 'paginas', 'idioma', 'descricao',
                  'preco_fisico', 'desconto_fisico', 'estoque', 'preco_digital', 'desconto_digital'] as $campo) {
            $dados[$campo] = trim((string) ($_POST[$campo] ?? ''));
        }
        foreach (['vende_fisico', 'vende_digital', 'lancamento', 'mais_vendido', 'banner'] as $campo) {
            $dados[$campo] = isset($_POST[$campo]);
        }

        if ($dados['titulo'] === '')  $erros['titulo'] = 'Informe o título.';
        if ($dados['autor'] === '')   $erros['autor'] = 'Informe o autor.';
        if ($dados['genero_id'] === 'novo') {
            $erro_genero = erro_nome_genero($dados['novo_genero']);
            if ($erro_genero) $erros['novo_genero'] = $erro_genero;
        } elseif (!in_array((int) $dados['genero_id'], $ids_generos, true)) {
            $erros['genero_id'] = 'Escolha um gênero.';
        }

        $ano = $dados['ano'] === '' ? null : filter_var($dados['ano'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => (int) date('Y') + 1]]);
        if ($ano === false) $erros['ano'] = 'O ano precisa ser um número, como 1899.';

        $paginas = $dados['paginas'] === '' ? null : filter_var($dados['paginas'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65000]]);
        if ($paginas === false) $erros['paginas'] = 'O número de páginas precisa ser maior que zero.';

        $preco_fisico = null;
        $desconto_fisico = 0;
        $estoque = 0;
        if ($dados['vende_fisico']) {
            $preco_fisico = ler_preco($dados['preco_fisico']);
            if ($preco_fisico === null || $preco_fisico <= 0) $erros['preco_fisico'] = 'Informe o preço do livro físico, como 49,90.';
            $desconto_fisico = ler_desconto($dados['desconto_fisico']);
            if ($desconto_fisico === false) $erros['desconto_fisico'] = 'O desconto do livro físico precisa ser de 0 a 100%.';
            $estoque = filter_var($dados['estoque'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($estoque === false) $erros['estoque'] = 'O estoque precisa ser 0 ou mais.';
        }

        $preco_digital = null;
        $desconto_digital = 0;
        if ($dados['vende_digital']) {
            $preco_digital = ler_preco($dados['preco_digital']);
            if ($preco_digital === null || $preco_digital <= 0) $erros['preco_digital'] = 'Informe o preço do e-book, como 29,90.';
            $desconto_digital = ler_desconto($dados['desconto_digital']);
            if ($desconto_digital === false) $erros['desconto_digital'] = 'O desconto do e-book precisa ser de 0 a 100%.';
        }

        if (!$dados['vende_fisico'] && !$dados['vende_digital']) {
            $erros['formatos'] = 'Marque pelo menos um formato: livro físico ou e-book.';
        }

        // Capa (opcional; na edição, só troca se um arquivo novo for enviado)
        $capa = $_FILES['capa'] ?? null;
        $capa_tipo = null;
        if ($capa && $capa['error'] !== UPLOAD_ERR_NO_FILE) {
            if (in_array($capa['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $capa['size'] > CAPA_TAMANHO_MAXIMO) {
                $erros['capa'] = 'A capa precisa ter no máximo 2 MB.';
            } elseif ($capa['error'] !== UPLOAD_ERR_OK) {
                $erros['capa'] = 'Não foi possível receber a capa. Envie o arquivo de novo.';
            } else {
                $info = @getimagesize($capa['tmp_name']);
                $capa_tipo = $info ? (CAPA_TIPOS[$info['mime']] ?? null) : null;
                if (!$capa_tipo) $erros['capa'] = 'A capa precisa ser uma imagem JPG, PNG ou WebP.';
            }
        }

        $imagem = $livro_editado['imagem'] ?? null;
        $remover_capa = $acao === 'salvar' && isset($_POST['remover_capa']) && !$capa_tipo;

        if (!$erros && $capa_tipo) {
            $nova = 'enviadas/' . nome_arquivo($dados['titulo']) . '-' . bin2hex(random_bytes(3)) . '.' . $capa_tipo;
            if (!is_dir(__DIR__ . '/imagens/enviadas')) {
                mkdir(__DIR__ . '/imagens/enviadas', 0775, true);
            }
            if (move_uploaded_file($capa['tmp_name'], __DIR__ . '/imagens/' . $nova)) {
                apagar_capa_enviada($imagem); // troca a capa antiga
                $imagem = $nova;
            } else {
                $erros['capa'] = 'Não foi possível salvar a capa na pasta imagens/enviadas.';
            }
        }

        if (!$erros) {
            if ($remover_capa) {
                apagar_capa_enviada($imagem);
                $imagem = null;
            }

            // "Novo gênero": cria agora (ou usa o que já tem esse nome) e vincula o livro a ele
            if ($dados['genero_id'] === 'novo') {
                $dados['genero_id'] = (string) (genero_com_nome($conexao, $dados['novo_genero'])
                                      ?? criar_genero($conexao, $dados['novo_genero']));
            }

            // livro que acabou de entrar no banner vai para o fim do carrossel
            $banner_ordem = (int) ($livro_editado['banner_ordem'] ?? 0);
            if ($dados['banner'] && empty($livro_editado['banner'])) {
                $banner_ordem = proxima_ordem_banner($conexao);
            }

            $valores = [
                'titulo'           => $dados['titulo'],
                'autor'            => $dados['autor'],
                'descricao'        => $dados['descricao'] !== '' ? $dados['descricao'] : null,
                'genero_id'        => (int) $dados['genero_id'],
                'idioma'           => $dados['idioma'] !== '' ? $dados['idioma'] : 'Português',
                'ano'              => $ano,
                'paginas'          => $paginas,
                'imagem'           => $imagem,
                'preco_fisico'     => $preco_fisico,
                'desconto_fisico'  => (int) $desconto_fisico,
                'estoque'          => (int) $estoque,
                'preco_digital'    => $preco_digital,
                'desconto_digital' => (int) $desconto_digital,
                'lancamento'       => (int) $dados['lancamento'],
                'mais_vendido'     => (int) $dados['mais_vendido'],
                'banner'           => (int) $dados['banner'],
                'banner_ordem'     => $banner_ordem,
            ];

            if ($acao === 'salvar') {
                $valores['id'] = (int) $livro_editado['id'];
                $conexao->prepare(
                    "UPDATE livros SET titulo = :titulo, autor = :autor, descricao = :descricao, genero_id = :genero_id,
                            idioma = :idioma, ano = :ano, paginas = :paginas, imagem = :imagem,
                            preco_fisico = :preco_fisico, desconto_fisico = :desconto_fisico, estoque = :estoque,
                            preco_digital = :preco_digital, desconto_digital = :desconto_digital,
                            lancamento = :lancamento, mais_vendido = :mais_vendido, banner = :banner,
                            banner_ordem = :banner_ordem
                      WHERE id = :id"
                )->execute($valores);
                avisar('Alterações em “' . $dados['titulo'] . '” salvas.');
            } else {
                $conexao->prepare(
                    "INSERT INTO livros (titulo, autor, descricao, genero_id, idioma, ano, paginas, imagem,
                                         preco_fisico, desconto_fisico, estoque, preco_digital, desconto_digital,
                                         lancamento, mais_vendido, banner, banner_ordem)
                     VALUES (:titulo, :autor, :descricao, :genero_id, :idioma, :ano, :paginas, :imagem,
                             :preco_fisico, :desconto_fisico, :estoque, :preco_digital, :desconto_digital,
                             :lancamento, :mais_vendido, :banner, :banner_ordem)"
                )->execute($valores);
                avisar('“' . $dados['titulo'] . '” foi adicionado ao catálogo.');
            }
            redirecionar('admin.php');
        }
    }
}

/* ---------- Dados da lista ---------- */

if ($modo === 'lista') {
    $livros = $conexao->query(SQL_LIVROS . " ORDER BY g.ordem, l.titulo")->fetchAll();
}
if ($modo === 'banner') {
    $slides = slides_banner($conexao);
    $fora_do_banner = $conexao->query("SELECT id, titulo, autor FROM livros WHERE banner = 0 ORDER BY titulo")->fetchAll();
}

/** Mensagem de erro de um campo (ou nada). */
function erro_campo(array $erros, string $campo): string
{
    return isset($erros[$campo])
        ? '<p class="erro-campo" id="erro-' . $campo . '">' . e($erros[$campo]) . '</p>'
        : '';
}

function invalido(array $erros, string $campo): string
{
    return isset($erros[$campo]) ? ' aria-invalid="true" aria-describedby="erro-' . $campo . '"' : '';
}

/** Texto da prévia de preço (o JavaScript atualiza enquanto o admin digita). */
function previa_preco(string $preco_texto, string $desconto_texto): string
{
    $preco = ler_preco($preco_texto);
    if ($preco === null || $preco <= 0) {
        return 'Preço na loja: informe o preço';
    }
    $desconto = ler_desconto($desconto_texto);
    if ($desconto === false) {
        return 'Preço na loja: o desconto vai de 0 a 100%';
    }
    if ($desconto === 0) {
        return 'Preço na loja: <strong>' . preco($preco) . '</strong>';
    }
    return 'Preço na loja: <s>' . preco($preco) . '</s> <strong>' . preco(preco_final($preco, $desconto)) . '</strong>';
}

$editando = $modo === 'editar';
$titulo = match ($modo) {
    'editar'  => 'Editar livro',
    'novo'    => 'Adicionar livro',
    'generos' => 'Gêneros',
    'banner'  => 'Banner principal',
    default   => 'Gerenciar livros',
};
require __DIR__ . '/includes/cabecalho.php';
?>

<?php if ($modo === 'lista'): ?>

    <section class="admin">
        <div class="admin-topo">
            <div>
                <h1 class="secao-titulo">Gerenciar livros</h1>
                <p class="secao-info"><?= count($livros) ?> livros no catálogo</p>
            </div>
            <div class="admin-topo-botoes">
                <a class="botao botao-contorno" href="admin.php?acao=banner">Banner</a>
                <a class="botao botao-contorno" href="admin.php?acao=generos">Gêneros</a>
                <a class="botao" href="admin.php?acao=novo">
                    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                    Novo livro
                </a>
            </div>
        </div>

        <?php if (!$livros): ?>
            <p class="vazio">O catálogo está vazio. <a href="admin.php?acao=novo">Adicione o primeiro livro</a></p>
        <?php else: ?>
            <div class="tabela-rolagem">
                <table class="tabela-admin">
                    <thead>
                        <tr>
                            <th scope="col">Livro</th>
                            <th scope="col">Gênero</th>
                            <th scope="col">Livro físico</th>
                            <th scope="col">E-book</th>
                            <th scope="col"><span class="so-leitor">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($livros as $livro): ?>
                            <tr>
                                <td>
                                    <div class="admin-livro">
                                        <span class="admin-capa"><?= capa($livro) ?></span>
                                        <span>
                                            <a href="livro.php?id=<?= (int) $livro['id'] ?>"><?= e($livro['titulo']) ?></a>
                                            <span class="admin-autor"><?= e($livro['autor']) ?></span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="admin-genero" style="<?= cores_genero($livro) ?>"><?= e($livro['genero_nome']) ?></span>
                                </td>
                                <td>
                                    <?php if ($livro['preco_fisico'] === null): ?>
                                        <span class="admin-nao">Não vende</span>
                                    <?php else: ?>
                                        <?= preco_html($livro['preco_fisico'], $livro['desconto_fisico']) ?>
                                        <span class="admin-estoque<?= (int) $livro['estoque'] === 0 ? ' zerado' : '' ?>">
                                            <?= (int) $livro['estoque'] === 0 ? 'Esgotado' : (int) $livro['estoque'] . ' em estoque' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($livro['preco_digital'] === null): ?>
                                        <span class="admin-nao">Não vende</span>
                                    <?php else: ?>
                                        <span class="admin-digital"><?= preco_html($livro['preco_digital'], $livro['desconto_digital']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="admin-acoes">
                                    <a class="botao-texto" href="admin.php?acao=editar&amp;id=<?= (int) $livro['id'] ?>">Editar</a>
                                    <form action="admin.php" method="post"
                                          data-confirmar="Remover “<?= e($livro['titulo']) ?>” do catálogo? Isso não pode ser desfeito.">
                                        <input type="hidden" name="token" value="<?= e(token()) ?>">
                                        <input type="hidden" name="acao" value="remover">
                                        <input type="hidden" name="id" value="<?= (int) $livro['id'] ?>">
                                        <button type="submit" class="botao-texto botao-perigo">Remover</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($modo === 'generos'): $ultimo = count($generos) - 1; ?>

    <section class="admin admin-novo">
        <a class="admin-voltar" href="admin.php">Voltar para a lista de livros</a>
        <h1 class="secao-titulo">Gêneros</h1>
        <p class="secao-info">A ordem desta lista é a ordem da estante na loja.</p>

        <form class="form-livro" action="admin.php?acao=generos" method="post">
            <input type="hidden" name="token" value="<?= e(token()) ?>">
            <input type="hidden" name="acao" value="genero_adicionar">
            <div class="campo campo-largo">
                <label for="nome-novo">Novo gênero</label>
                <div class="campo-linha">
                    <input type="text" id="nome-novo" name="nome" maxlength="60" placeholder="Ex.: Poesia" required>
                    <button type="submit" class="botao">Adicionar gênero</button>
                </div>
                <p class="dica">Entra no fim da estante. Depois é só escolher esse gênero ao cadastrar o livro.</p>
            </div>
        </form>

        <div class="tabela-rolagem">
            <table class="tabela-admin tabela-generos">
                <thead>
                    <tr>
                        <th scope="col">Gênero</th>
                        <th scope="col">Livros</th>
                        <th scope="col">Posição</th>
                        <th scope="col"><span class="so-leitor">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($generos as $i => $genero): $gid = (int) $genero['id']; ?>
                        <tr>
                            <td>
                                <span class="admin-genero" style="<?= cores_genero($genero) ?>">
                                    <label class="so-leitor" for="nome-<?= $gid ?>">Nome do gênero</label>
                                    <input type="text" id="nome-<?= $gid ?>" name="nome" form="renomear-<?= $gid ?>"
                                           maxlength="60" required value="<?= e($genero['nome']) ?>">
                                </span>
                            </td>
                            <td><?= (int) $genero['total'] ?></td>
                            <td class="genero-posicao">
                                <?php foreach (['genero_subir' => [$i === 0, 'Subir', 'M6 15l6-6 6 6'], 'genero_descer' => [$i === $ultimo, 'Descer', 'M6 9l6 6 6-6']] as $acao_ordem => [$bloqueado, $verbo, $seta]): ?>
                                    <form action="admin.php?acao=generos" method="post">
                                        <input type="hidden" name="token" value="<?= e(token()) ?>">
                                        <input type="hidden" name="acao" value="<?= $acao_ordem ?>">
                                        <input type="hidden" name="id" value="<?= $gid ?>">
                                        <button type="submit" class="botao-seta" aria-label="<?= $verbo ?> “<?= e($genero['nome']) ?>” na estante"<?= $bloqueado ? ' disabled' : '' ?>>
                                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="<?= $seta ?>" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </button>
                                    </form>
                                <?php endforeach; ?>
                            </td>
                            <td class="admin-acoes">
                                <form id="renomear-<?= $gid ?>" action="admin.php?acao=generos" method="post">
                                    <input type="hidden" name="token" value="<?= e(token()) ?>">
                                    <input type="hidden" name="acao" value="genero_renomear">
                                    <input type="hidden" name="id" value="<?= $gid ?>">
                                    <button type="submit" class="botao-texto">Salvar nome</button>
                                </form>
                                <?php if ((int) $genero['total'] === 0): ?>
                                    <form action="admin.php?acao=generos" method="post"
                                          data-confirmar="Remover o gênero “<?= e($genero['nome']) ?>”?">
                                        <input type="hidden" name="token" value="<?= e(token()) ?>">
                                        <input type="hidden" name="acao" value="genero_remover">
                                        <input type="hidden" name="id" value="<?= $gid ?>">
                                        <button type="submit" class="botao-texto botao-perigo">Remover</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="dica-generos">Só dá para remover um gênero sem livros. Para esvaziar um gênero, edite os livros dele e escolha outro.</p>
    </section>

<?php elseif ($modo === 'banner'): $ultimo = count($slides) - 1; ?>

    <section class="admin admin-novo">
        <a class="admin-voltar" href="admin.php">Voltar para a lista de livros</a>
        <h1 class="secao-titulo">Banner principal</h1>
        <p class="secao-info">O carrossel da página inicial mostra estes slides, nesta ordem. Campo de texto vazio usa o texto automático.</p>

        <form class="form-livro" action="admin.php?acao=banner" method="post">
            <input type="hidden" name="token" value="<?= e(token()) ?>">
            <input type="hidden" name="acao" value="banner_adicionar">
            <div class="campo campo-largo">
                <label for="banner-livro">Colocar um livro no banner</label>
                <div class="campo-linha">
                    <select id="banner-livro" name="id" required>
                        <option value="">Escolha um livro</option>
                        <?php foreach ($fora_do_banner as $opcao): ?>
                            <option value="<?= (int) $opcao['id'] ?>"><?= e($opcao['titulo']) ?> (<?= e($opcao['autor']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="botao">Adicionar ao banner</button>
                </div>
                <p class="dica">Entra no fim do carrossel, com a cor do gênero. Depois dá para trocar a cor e os textos.</p>
            </div>
        </form>

        <?php if (!$slides): ?>
            <p class="vazio">Nenhum livro no banner. Sem slides, o carrossel some da página inicial.</p>
        <?php endif; ?>

        <ol class="slides-admin">
            <?php foreach ($slides as $i => $slide): $sid = (int) $slide['id']; ?>
                <li class="slide-admin" style="<?= cores_banner($slide) ?>">
                    <div class="slide-admin-previa">
                        <span class="admin-capa"><?= capa($slide) ?></span>
                        <div class="slide-admin-texto">
                            <p class="slide-admin-chamada" data-previa-chamada="<?= e(motivo_destaque($slide)) ?>"><?= e(chamada_banner($slide)) ?></p>
                            <p class="slide-admin-titulo"><?= $i + 1 ?>. <?= e($slide['titulo']) ?></p>
                        </div>
                        <div class="genero-posicao">
                            <?php foreach (['banner_esquerda' => [$i === 0, 'Antes', 'M15 5l-7 7 7 7'], 'banner_direita' => [$i === $ultimo, 'Depois', 'M9 5l7 7-7 7']] as $acao_ordem => [$bloqueado, $verbo, $seta]): ?>
                                <form action="admin.php?acao=banner" method="post">
                                    <input type="hidden" name="token" value="<?= e(token()) ?>">
                                    <input type="hidden" name="acao" value="<?= $acao_ordem ?>">
                                    <input type="hidden" name="id" value="<?= $sid ?>">
                                    <button type="submit" class="botao-seta" aria-label="<?= $verbo ?>: mover “<?= e($slide['titulo']) ?>” no carrossel"<?= $bloqueado ? ' disabled' : '' ?>>
                                        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="<?= $seta ?>" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <form class="form-livro slide-admin-campos" action="admin.php?acao=banner" method="post">
                        <input type="hidden" name="token" value="<?= e(token()) ?>">
                        <input type="hidden" name="acao" value="banner_salvar">
                        <input type="hidden" name="id" value="<?= $sid ?>">

                        <div class="campo campo-curto">
                            <label for="cor-<?= $sid ?>">Cor do fundo</label>
                            <input type="color" id="cor-<?= $sid ?>" name="cor" data-previa-cor
                                   data-cor-genero="<?= e($slide['genero_cor']) ?>"
                                   value="<?= e($slide['banner_cor'] ?: $slide['genero_cor']) ?>">
                            <label class="marcar marcar-pequeno">
                                <input type="checkbox" name="usar_cor_genero" data-usar-cor-genero <?= $slide['banner_cor'] ? '' : 'checked' ?>>
                                Cor do gênero (<?= e($slide['genero_nome']) ?>)
                            </label>
                        </div>

                        <div class="campo campo-chamada">
                            <label for="chamada-<?= $sid ?>">Chamada</label>
                            <input type="text" id="chamada-<?= $sid ?>" name="chamada" maxlength="120" data-previa-texto
                                   placeholder="<?= e(motivo_destaque($slide)) ?>" value="<?= e($slide['banner_chamada'] ?? '') ?>">
                            <p class="dica">Frase pequena acima do título. Vazio: <?= e(motivo_destaque($slide)) ?>.</p>
                        </div>

                        <div class="campo campo-largo">
                            <label for="descricao-<?= $sid ?>">Descrição no banner</label>
                            <textarea id="descricao-<?= $sid ?>" name="descricao" rows="3"
                                      placeholder="<?= e($slide['descricao'] ?? '') ?>"><?= e($slide['banner_descricao'] ?? '') ?></textarea>
                            <p class="dica">Vazio: usa a descrição do livro, que aparece em cinza no campo.</p>
                        </div>

                        <div class="campo-largo form-botoes">
                            <button type="submit" class="botao">Salvar slide</button>
                            <a class="botao botao-contorno" href="admin.php?acao=editar&amp;id=<?= $sid ?>">Editar livro</a>
                            <button type="submit" class="botao-texto botao-perigo" form="remover-<?= $sid ?>">Tirar do banner</button>
                        </div>
                    </form>
                    <form id="remover-<?= $sid ?>" action="admin.php?acao=banner" method="post"
                          data-confirmar="Tirar “<?= e($slide['titulo']) ?>” do banner? O livro continua no catálogo.">
                        <input type="hidden" name="token" value="<?= e(token()) ?>">
                        <input type="hidden" name="acao" value="banner_remover">
                        <input type="hidden" name="id" value="<?= $sid ?>">
                    </form>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

<?php else: ?>

    <section class="admin admin-novo">
        <a class="admin-voltar" href="admin.php">Voltar para a lista de livros</a>
        <h1 class="secao-titulo"><?= $editando ? 'Editar livro' : 'Adicionar livro' ?></h1>
        <?php if ($editando): ?>
            <p class="secao-info">
                <?= e($livro_editado['titulo']) ?>.
                <a href="livro.php?id=<?= (int) $livro_editado['id'] ?>">Ver na loja</a>
            </p>
        <?php endif; ?>

        <?php if ($erros): ?>
            <div class="erro-form" role="alert">
                <p>Confira os campos marcados:</p>
                <ul>
                    <?php foreach ($erros as $mensagem): ?>
                        <li><?= e($mensagem) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="form-livro"
              action="<?= $editando ? 'admin.php?acao=editar&amp;id=' . (int) $livro_editado['id'] : 'admin.php?acao=novo' ?>"
              method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="token" value="<?= e(token()) ?>">
            <input type="hidden" name="acao" value="<?= $editando ? 'salvar' : 'adicionar' ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= CAPA_TAMANHO_MAXIMO ?>">

            <div class="campo campo-largo">
                <label for="titulo">Título <span class="obrigatorio">(obrigatório)</span></label>
                <input type="text" id="titulo" name="titulo" maxlength="160" value="<?= e($dados['titulo']) ?>"<?= invalido($erros, 'titulo') ?>>
                <?= erro_campo($erros, 'titulo') ?>
            </div>

            <div class="campo">
                <label for="autor">Autor <span class="obrigatorio">(obrigatório)</span></label>
                <input type="text" id="autor" name="autor" maxlength="160" value="<?= e($dados['autor']) ?>"<?= invalido($erros, 'autor') ?>>
                <?= erro_campo($erros, 'autor') ?>
            </div>

            <div class="campo campo-genero">
                <label for="genero_id">Gênero <span class="obrigatorio">(obrigatório)</span></label>
                <select id="genero_id" name="genero_id"<?= invalido($erros, 'genero_id') ?>>
                    <option value="">Escolha um gênero</option>
                    <?php foreach ($generos as $genero): ?>
                        <option value="<?= (int) $genero['id'] ?>" <?= (string) $genero['id'] === $dados['genero_id'] ? 'selected' : '' ?>>
                            <?= e($genero['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="novo" <?= $dados['genero_id'] === 'novo' ? 'selected' : '' ?>>+ Novo gênero…</option>
                </select>
                <?= erro_campo($erros, 'genero_id') ?>
                <div class="novo-genero">
                    <label for="novo_genero">Nome do novo gênero</label>
                    <input type="text" id="novo_genero" name="novo_genero" maxlength="60" placeholder="Ex.: Poesia"
                           value="<?= e($dados['novo_genero']) ?>"<?= invalido($erros, 'novo_genero') ?>>
                    <?= erro_campo($erros, 'novo_genero') ?>
                    <p class="dica">Ele é criado junto com o livro, no fim da estante.</p>
                </div>
            </div>

            <div class="campo campo-curto">
                <label for="ano">Ano</label>
                <input type="text" inputmode="numeric" id="ano" name="ano" maxlength="4" value="<?= e($dados['ano']) ?>"<?= invalido($erros, 'ano') ?>>
                <?= erro_campo($erros, 'ano') ?>
            </div>

            <div class="campo campo-curto">
                <label for="paginas">Páginas</label>
                <input type="text" inputmode="numeric" id="paginas" name="paginas" maxlength="5" value="<?= e($dados['paginas']) ?>"<?= invalido($erros, 'paginas') ?>>
                <?= erro_campo($erros, 'paginas') ?>
            </div>

            <div class="campo">
                <label for="idioma">Idioma</label>
                <input type="text" id="idioma" name="idioma" maxlength="30" value="<?= e($dados['idioma']) ?>">
            </div>

            <div class="campo campo-largo">
                <label for="descricao">Descrição</label>
                <textarea id="descricao" name="descricao" rows="4"><?= e($dados['descricao']) ?></textarea>
                <p class="dica">Aparece na página do livro. Uma ou duas frases sobre a história.</p>
            </div>

            <fieldset class="campo-largo formatos-admin"<?= isset($erros['formatos']) ? ' aria-describedby="erro-formatos"' : '' ?>>
                <legend>Formatos à venda</legend>
                <?= erro_campo($erros, 'formatos') ?>

                <div class="formato-admin">
                    <label class="marcar">
                        <input type="checkbox" name="vende_fisico" <?= $dados['vende_fisico'] ? 'checked' : '' ?>>
                        Livro físico
                    </label>
                    <div class="formato-campos" data-previa>
                        <div class="campo">
                            <label for="preco_fisico">Preço (R$)</label>
                            <input type="text" inputmode="decimal" id="preco_fisico" name="preco_fisico" placeholder="49,90"
                                   data-preco value="<?= e($dados['preco_fisico']) ?>"<?= invalido($erros, 'preco_fisico') ?>>
                            <?= erro_campo($erros, 'preco_fisico') ?>
                        </div>
                        <div class="campo">
                            <label for="desconto_fisico">Desconto (%)</label>
                            <input type="number" id="desconto_fisico" name="desconto_fisico" min="0" max="100" step="1"
                                   data-desconto value="<?= e($dados['desconto_fisico']) ?>"<?= invalido($erros, 'desconto_fisico') ?>>
                            <?= erro_campo($erros, 'desconto_fisico') ?>
                        </div>
                        <output class="previa-preco" for="preco_fisico desconto_fisico" aria-live="polite"><?= previa_preco($dados['preco_fisico'], $dados['desconto_fisico']) ?></output>
                        <div class="campo">
                            <label for="estoque">Estoque</label>
                            <input type="text" inputmode="numeric" id="estoque" name="estoque"
                                   value="<?= e($dados['estoque']) ?>"<?= invalido($erros, 'estoque') ?>>
                            <?= erro_campo($erros, 'estoque') ?>
                        </div>
                    </div>
                </div>

                <div class="formato-admin formato-admin-digital">
                    <label class="marcar">
                        <input type="checkbox" name="vende_digital" <?= $dados['vende_digital'] ? 'checked' : '' ?>>
                        E-book (digital)
                    </label>
                    <div class="formato-campos" data-previa>
                        <div class="campo">
                            <label for="preco_digital">Preço (R$)</label>
                            <input type="text" inputmode="decimal" id="preco_digital" name="preco_digital" placeholder="29,90"
                                   data-preco value="<?= e($dados['preco_digital']) ?>"<?= invalido($erros, 'preco_digital') ?>>
                            <?= erro_campo($erros, 'preco_digital') ?>
                        </div>
                        <div class="campo">
                            <label for="desconto_digital">Desconto (%)</label>
                            <input type="number" id="desconto_digital" name="desconto_digital" min="0" max="100" step="1"
                                   data-desconto value="<?= e($dados['desconto_digital']) ?>"<?= invalido($erros, 'desconto_digital') ?>>
                            <?= erro_campo($erros, 'desconto_digital') ?>
                        </div>
                        <output class="previa-preco" for="preco_digital desconto_digital" aria-live="polite"><?= previa_preco($dados['preco_digital'], $dados['desconto_digital']) ?></output>
                        <p class="dica">Ganha a etiqueta “digital” na loja e não paga frete.</p>
                    </div>
                </div>
                <p class="dica dica-formatos">Use o desconto para promoções: o preço original continua salvo e aparece riscado na loja. Volte para 0% para encerrar a promoção.</p>
            </fieldset>

            <fieldset class="campo-largo destaques-admin">
                <legend>Mostrar na página inicial</legend>
                <label class="marcar">
                    <input type="checkbox" name="lancamento" <?= $dados['lancamento'] ? 'checked' : '' ?>>
                    Em Lançamentos
                </label>
                <label class="marcar">
                    <input type="checkbox" name="mais_vendido" <?= $dados['mais_vendido'] ? 'checked' : '' ?>>
                    Em Mais vendidos
                </label>
                <label class="marcar">
                    <input type="checkbox" name="banner" <?= $dados['banner'] ? 'checked' : '' ?>>
                    No banner principal
                </label>
                <p class="dica">Cor, textos e ordem do slide: <a href="admin.php?acao=banner">Gerenciar livros → Banner</a>.</p>
            </fieldset>

            <div class="campo campo-largo">
                <label for="capa"><?= $editando ? 'Trocar a capa' : 'Capa do livro' ?></label>
                <?php if ($editando && $livro_editado['imagem']): ?>
                    <div class="capa-atual">
                        <img src="imagens/<?= e($livro_editado['imagem']) ?>" alt="Capa atual">
                        <label class="marcar">
                            <input type="checkbox" name="remover_capa">
                            Remover a capa atual
                        </label>
                    </div>
                <?php endif; ?>
                <input type="file" id="capa" name="capa" accept="image/jpeg,image/png,image/webp"<?= invalido($erros, 'capa') ?>>
                <?= erro_campo($erros, 'capa') ?>
                <p class="dica">JPG, PNG ou WebP de até 2 MB, no padrão da loja: 900 × 1200 px (3:4). Sem capa, a loja desenha uma na cor do gênero.<?= $erros && !isset($erros['capa']) ? ' Se você já tinha escolhido um arquivo, escolha de novo.' : '' ?></p>
            </div>

            <div class="campo-largo form-botoes">
                <button type="submit" class="botao"><?= $editando ? 'Salvar alterações' : 'Adicionar livro' ?></button>
                <a class="botao botao-contorno" href="admin.php">Cancelar</a>
            </div>
        </form>
    </section>

<?php endif; ?>

<?php require __DIR__ . '/includes/rodape.php'; ?>
