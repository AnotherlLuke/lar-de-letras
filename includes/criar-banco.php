<?php
// Cria o banco com os dados padrão de banco/livraria.sql quando ele ainda não existe.
// Chamado por includes/conexao.php na primeira execução do site.
// Também funciona pelo terminal: php includes/criar-banco.php

/**
 * Cria o banco $banco e importa banco/livraria.sql nele.
 * Se o banco já existir, não faz nada. Se a importação falhar, apaga o banco
 * pela metade para que a próxima visita tente de novo.
 */
function criar_banco_padrao(string $servidor, string $usuario, string $senha, string $banco): void
{
    $servidor_pdo = new PDO("mysql:host=$servidor;charset=utf8mb4", $usuario, $senha, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Evita que duas visitas ao mesmo tempo importem os dados duas vezes.
    $servidor_pdo->query("SELECT GET_LOCK('larletras_criar_banco', 30)");
    try {
        $existe = $servidor_pdo->prepare("SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?");
        $existe->execute([$banco]);
        if ($existe->fetchColumn()) {
            return; // outra visita acabou de criar
        }

        $sql = file_get_contents(__DIR__ . '/../banco/livraria.sql');
        if ($sql === false) {
            throw new RuntimeException('Arquivo banco/livraria.sql não encontrado.');
        }

        // Tira os comentários de linha inteira e separa os comandos (cada um termina com ";" no fim da linha).
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $comandos = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql)));

        $nome = '`' . str_replace('`', '``', $banco) . '`';
        $servidor_pdo->exec("CREATE DATABASE $nome CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try {
            $servidor_pdo->exec("USE $nome");
            foreach ($comandos as $comando) {
                // O arquivo apaga e recria o banco `livraria`; aqui o banco já foi criado com o nome configurado.
                if (preg_match('/^(DROP DATABASE|CREATE DATABASE|USE)\b/i', $comando)) {
                    continue;
                }
                $servidor_pdo->exec($comando);
            }
        } catch (Throwable $erro) {
            $servidor_pdo->exec("DROP DATABASE IF EXISTS $nome");
            throw $erro;
        }
    } finally {
        $servidor_pdo->query("SELECT RELEASE_LOCK('larletras_criar_banco')");
    }
}

// Uso pelo terminal: php includes/criar-banco.php
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require __DIR__ . '/conexao.php'; // cria o banco se faltar
    echo "Banco \"$banco\" pronto.\n";
}
