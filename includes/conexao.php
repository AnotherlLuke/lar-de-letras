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

try {
    $conexao = new PDO(
        "mysql:host=$servidor;dbname=$banco;charset=utf8mb4",
        $usuario,
        $senha,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $erro) {
    error_log($erro->getMessage());
    http_response_code(500);
    die('Não foi possível conectar ao banco "livraria". Confira se o MySQL está ligado no painel do XAMPP '
      . 'e se o arquivo banco/livraria.sql já foi importado no phpMyAdmin.');
}
