<?php
// Conexão com o MySQL/MariaDB do XAMPP (usuário root, sem senha).
// Se o seu XAMPP tiver senha no root, altere $senha abaixo.

$servidor = "localhost";
$usuario  = "root";
$senha    = "";
$banco    = "livraria";

// Opcional: crie includes/conexao.local.php (ignorado pelo git) para usar
// outro usuário/senha só na sua máquina, sem alterar este arquivo.
if (is_file(__DIR__ . '/conexao.local.php')) {
    require __DIR__ . '/conexao.local.php';
}

$conectar = fn() => new PDO(
    "mysql:host=$servidor;dbname=$banco;charset=utf8mb4",
    $usuario,
    $senha,
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

try {
    try {
        $conexao = $conectar();
    } catch (PDOException $erro) {
        // 1049 = banco não existe: na primeira execução, cria com os dados de banco/livraria.sql.
        if ((int) $erro->getCode() !== 1049) {
            throw $erro;
        }
        require_once __DIR__ . '/criar-banco.php';
        criar_banco_padrao($servidor, $usuario, $senha, $banco);
        $conexao = $conectar();
    }
} catch (Throwable $erro) {
    error_log($erro->getMessage());
    http_response_code(500);
    die('Não foi possível conectar ao banco "' . htmlspecialchars($banco) . '". Confira se o MySQL está ligado no painel do XAMPP '
      . 'e se o usuário configurado em includes/conexao.php tem permissão para criar o banco.');
}
