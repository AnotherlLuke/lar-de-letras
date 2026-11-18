<?php
require_once __DIR__ . '/includes/funcoes.php';

if (usuario_logado()) {
    redirecionar('index.php');
}

$erros  = ['entrar' => null, 'cadastrar' => null];
$campos = ['email_entrar' => '', 'nome' => '', 'email_cadastrar' => ''];

/** Guarda o usuário na sessão e volta para onde ele estava (carrinho, por exemplo). */
function entrar_como(array $usuario): void
{
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'    => (int) $usuario['id'],
        'nome'  => $usuario['nome'],
        'email' => $usuario['email'],
        'tipo'  => $usuario['tipo'] ?? 'cliente',
    ];
    $padrao  = $_SESSION['usuario']['tipo'] === 'admin' ? 'admin.php' : 'index.php';
    $destino = endereco_seguro($_SESSION['voltar'] ?? $padrao);
    unset($_SESSION['voltar']);
    avisar('Olá, ' . strtok($usuario['nome'], ' ') . '. Você entrou na sua conta.');
    redirecionar($destino);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'entrar') {
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $campos['email_entrar'] = $email;

        $consulta = $conexao->prepare("SELECT * FROM usuarios WHERE email = :email");
        $consulta->execute(['email' => $email]);
        $usuario = $consulta->fetch();

        if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
            entrar_como($usuario);
        }
        $erros['entrar'] = $usuario
            ? 'Senha incorreta. Confira e tente de novo.'
            : 'Não encontramos uma conta com esse e-mail ou usuário. Crie uma ao lado.';
    }

    if ($acao === 'cadastrar') {
        $nome  = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $campos['nome'] = $nome;
        $campos['email_cadastrar'] = $email;

        if ($nome === '') {
            $erros['cadastrar'] = 'Informe seu nome.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros['cadastrar'] = 'Informe um e-mail válido, como nome@exemplo.com.';
        } elseif (strlen($senha) < 6) {
            $erros['cadastrar'] = 'A senha precisa ter pelo menos 6 caracteres.';
        } else {
            $consulta = $conexao->prepare("SELECT id FROM usuarios WHERE email = :email");
            $consulta->execute(['email' => $email]);

            if ($consulta->fetch()) {
                $erros['cadastrar'] = 'Este e-mail já tem conta. Entre com sua senha ao lado.';
                $campos['email_entrar'] = $email;
            } else {
                $consulta = $conexao->prepare(
                    "INSERT INTO usuarios (nome, email, senha_hash) VALUES (:nome, :email, :senha)"
                );
                $consulta->execute([
                    'nome'  => $nome,
                    'email' => $email,
                    'senha' => password_hash($senha, PASSWORD_DEFAULT),
                ]);
                entrar_como(['id' => $conexao->lastInsertId(), 'nome' => $nome, 'email' => $email]);
            }
        }
    }
}

$titulo = 'Entrar';
require __DIR__ . '/includes/cabecalho.php';
?>

<section class="acesso">
    <h1 class="acesso-titulo">Entre ou crie sua conta</h1>

    <div class="acesso-formularios">
        <form class="acesso-form" action="login.php" method="post" novalidate>
            <h2>Já sou cliente</h2>
            <input type="hidden" name="acao" value="entrar">

            <?php if ($erros['entrar']): ?>
                <p class="erro-form" role="alert"><?= e($erros['entrar']) ?></p>
            <?php endif; ?>

            <label for="entrar-email">E-mail ou usuário</label>
            <input type="text" id="entrar-email" name="email" autocomplete="username" required
                   value="<?= e($campos['email_entrar']) ?>" placeholder="nome@exemplo.com">

            <label for="entrar-senha">Senha</label>
            <input type="password" id="entrar-senha" name="senha" autocomplete="current-password" required>

            <button type="submit" class="botao botao-largo">Entrar</button>
        </form>

        <form class="acesso-form" action="login.php" method="post" novalidate>
            <h2>Quero criar uma conta</h2>
            <input type="hidden" name="acao" value="cadastrar">

            <?php if ($erros['cadastrar']): ?>
                <p class="erro-form" role="alert"><?= e($erros['cadastrar']) ?></p>
            <?php endif; ?>

            <label for="cadastro-nome">Nome</label>
            <input type="text" id="cadastro-nome" name="nome" autocomplete="name" required
                   value="<?= e($campos['nome']) ?>">

            <label for="cadastro-email">E-mail</label>
            <input type="email" id="cadastro-email" name="email" autocomplete="email" required
                   value="<?= e($campos['email_cadastrar']) ?>" placeholder="nome@exemplo.com">

            <label for="cadastro-senha">Senha</label>
            <input type="password" id="cadastro-senha" name="senha" autocomplete="new-password"
                   minlength="6" required aria-describedby="dica-senha">
            <p class="dica" id="dica-senha">Pelo menos 6 caracteres.</p>

            <button type="submit" class="botao botao-largo botao-contorno">Criar conta</button>
        </form>
    </div>
</section>

<?php require __DIR__ . '/includes/rodape.php'; ?>
