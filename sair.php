<?php
require_once __DIR__ . '/includes/funcoes.php';

// Sai da conta, mas mantém o carrinho na sessão.
unset($_SESSION['usuario']);
session_regenerate_id(true);
avisar('Você saiu da sua conta.');
redirecionar('index.php');
