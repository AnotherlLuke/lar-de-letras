<?php
require_once __DIR__ . '/includes/funcoes.php';
exigir_admin();

const CAPA_TAMANHO_MAXIMO = 2 * 1024 * 1024; // 2 MB
const CAPA_TIPOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

$generos = buscar_generos($conexao);
$ids_generos = array_map(fn($g) => (int) $g['id'], $generos);

$acao_get = $_GET['acao'] ?? '';
$modo     = in_array($acao_get, ['novo', 'editar'], true) ? $acao_get : 'lista';
$erros    = [];
$livro_editado = null;

$dados = [
    'titulo' => '', 'autor' => '', 'genero_id' => '', 'ano' => '', 'paginas' => '',
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

/** Transforma o título em um nome de arquivo seguro: "O Cortiço!" -> "o-cortico" */
function nome_arquivo(string $texto): string
{
    $texto = strtr($texto, [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','ë'=>'e',
        'í'=>'i','î'=>'i','ì'=>'i','ï'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ò'=>'o','ö'=>'o',
        'ú'=>'u','û'=>'u','ù'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
        'Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e','Í'=>'i','Ó'=>'o','Ô'=>'o',
        'Õ'=>'o','Ú'=>'u','Ç'=>'c','Ñ'=>'n',
    ]);
    $texto = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $texto));
    return substr(trim($texto, '-'), 0, 60) ?: 'livro';
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

        foreach (['titulo', 'autor', 'genero_id', 'ano', 'paginas', 'idioma', 'descricao',
                  'preco_fisico', 'desconto_fisico', 'estoque', 'preco_digital', 'desconto_digital'] as $campo) {
            $dados[$campo] = trim((string) ($_POST[$campo] ?? ''));
        }
        foreach (['vende_fisico', 'vende_digital', 'lancamento', 'mais_vendido', 'banner'] as $campo) {
            $dados[$campo] = isset($_POST[$campo]);
        }

        if ($dados['titulo'] === '')  $erros['titulo'] = 'Informe o título.';
        if ($dados['autor'] === '')   $erros['autor'] = 'Informe o autor.';
        if (!in_array((int) $dados['genero_id'], $ids_generos, true)) $erros['genero_id'] = 'Escolha um gênero.';

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
            ];

            if ($acao === 'salvar') {
                $valores['id'] = (int) $livro_editado['id'];
                $conexao->prepare(
                    "UPDATE livros SET titulo = :titulo, autor = :autor, descricao = :descricao, genero_id = :genero_id,
                            idioma = :idioma, ano = :ano, paginas = :paginas, imagem = :imagem,
                            preco_fisico = :preco_fisico, desconto_fisico = :desconto_fisico, estoque = :estoque,
                            preco_digital = :preco_digital, desconto_digital = :desconto_digital,
                            lancamento = :lancamento, mais_vendido = :mais_vendido, banner = :banner
                      WHERE id = :id"
                )->execute($valores);
                avisar('Alterações em “' . $dados['titulo'] . '” salvas.');
            } else {
                $conexao->prepare(
                    "INSERT INTO livros (titulo, autor, descricao, genero_id, idioma, ano, paginas, imagem,
                                         preco_fisico, desconto_fisico, estoque, preco_digital, desconto_digital,
                                         lancamento, mais_vendido, banner)
                     VALUES (:titulo, :autor, :descricao, :genero_id, :idioma, :ano, :paginas, :imagem,
                             :preco_fisico, :desconto_fisico, :estoque, :preco_digital, :desconto_digital,
                             :lancamento, :mais_vendido, :banner)"
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
$titulo = $editando ? 'Editar livro' : ($modo === 'novo' ? 'Adicionar livro' : 'Gerenciar livros');
require __DIR__ . '/includes/cabecalho.php';
?>

<?php if ($modo === 'lista'): ?>

    <section class="admin">
        <div class="admin-topo">
            <div>
                <h1 class="secao-titulo">Gerenciar livros</h1>
                <p class="secao-info"><?= count($livros) ?> livros no catálogo</p>
            </div>
            <a class="botao" href="admin.php?acao=novo">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                Novo livro
            </a>
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

            <div class="campo">
                <label for="genero_id">Gênero <span class="obrigatorio">(obrigatório)</span></label>
                <select id="genero_id" name="genero_id"<?= invalido($erros, 'genero_id') ?>>
                    <option value="">Escolha um gênero</option>
                    <?php foreach ($generos as $genero): ?>
                        <option value="<?= (int) $genero['id'] ?>" <?= (string) $genero['id'] === $dados['genero_id'] ? 'selected' : '' ?>>
                            <?= e($genero['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= erro_campo($erros, 'genero_id') ?>
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
