# Lar de Letras — livraria digital

Protótipo do site da livraria Lar de Letras (projeto de extensão em programação, UNINOVE).
Feito em PHP, HTML5, CSS e um pouco de JavaScript. Roda no XAMPP, no Windows ou no Linux.

## Páginas

| Arquivo | O que faz |
|---|---|
| `index.php` | Página inicial: vitrine, lançamentos, mais vendidos e uma seção por gênero. A estante à esquerda leva até cada gênero. Também mostra os resultados da busca. |
| `livro.php?id=N` | Página do livro: escolha entre livro físico e e-book, quantidade e "Adicionar ao carrinho". |
| `carrinho.php` | Carrinho (guardado na sessão): alterar quantidade, remover e finalizar. Para finalizar é preciso entrar na conta. |
| `login.php` | Entrar ou criar conta. A senha é guardada com `password_hash`. |
| `sair.php` | Sai da conta. |
| `admin.php` | Área do administrador: lista, adiciona, edita e remove livros (preço, desconto, estoque, formato físico e/ou e-book, capa). |

## Administrador

O banco já vem com uma conta de administrador:

- **Usuário:** `admin`
- **Senha:** `123mudar`

Entre pela página de login, digitando `admin` no campo "E-mail ou usuário". Você cai direto em **Gerenciar livros**.
Um livro pode ser vendido só físico, só como e-book ou nos dois formatos. Quem tem e-book ganha a etiqueta "digital" na loja.
Ao lado de cada preço há um campo **Desconto (%)**, de 0 a 100, para promoções. O formulário mostra uma prévia do preço final,
e a loja exibe o preço antigo riscado, o novo e um selo (ex.: −20%). O preço original continua salvo: volte o desconto para 0% para encerrar a promoção.

O **banner principal** (carrossel da página inicial) mostra os livros marcados com "No banner principal" no formulário do admin.
Cada slide explica por que o livro está ali (lançamento, promoção, mais vendido), e as bolinhas embaixo mostram quantos slides existem.

## Capas

O padrão da loja é **900 × 1200 px (proporção 3:4)**. Capas em outra proporção são exibidas em 3:4 com um pequeno corte
nas bordas, então vale salvar as novas já nesse tamanho.
As capas enviadas ficam em `imagens/enviadas/`, e essa pasta precisa existir e aceitar gravação. No Windows/XAMPP ela já funciona.

## Como rodar no XAMPP (Windows)

1. Copie a pasta `LarLetras` para `C:\xampp\htdocs\`.
2. Abra o **XAMPP Control Panel** e clique em **Start** no **Apache** e no **MySQL**.
3. Acesse <http://localhost/phpmyadmin>, abra a aba **Importar**, escolha o arquivo
   `LarLetras\banco\livraria.sql` e clique em **Importar**.
   Isso apaga e recria o banco `livraria` com 7 gêneros e 23 livros.
4. Acesse <http://localhost/LarLetras/>.

## Como rodar no Linux

- **Com XAMPP (LAMPP):** copie a pasta para `/opt/lampp/htdocs/`, inicie com `sudo /opt/lampp/lampp start`
  e siga os passos 3 e 4 acima.
- **Sem XAMPP:** com `php` e MariaDB instalados, rode `mysql -u root < banco/livraria.sql`,
  depois `php -S localhost:8000` dentro da pasta e acesse <http://localhost:8000>.

## Configuração

Os dados de acesso ao banco ficam em `includes/conexao.php`: usuário `root`, sem senha, que é o padrão do XAMPP.
Se o seu MySQL tiver senha, altere `$senha` nesse arquivo.

## Estrutura

```
LarLetras/
  index.php, livro.php, carrinho.php, login.php, sair.php
  includes/   conexao, funções, cabeçalho, rodapé e o card do livro
  css/        estilo.css
  js/         script.js (opcional: destaque da estante e quantidade automática)
  imagens/    capas (nomes em minúsculas, sem acento e sem espaço)
  banco/      livraria.sql
```

## Regras para manter o projeto funcionando no Windows e no Linux

- Nomes de arquivos e imagens em **minúsculas, sem acentos e sem espaços** (`minha-capa.jpg`).
  O Linux diferencia `Capa.jpg` de `capa.jpg`; o Windows não.
- Salve os arquivos `.php` em **UTF-8 sem BOM**. O BOM causa o erro "headers already sent".
- Use `__DIR__` nos `require` (`require __DIR__ . '/includes/funcoes.php';`).

## Para adicionar um livro

Use a área do administrador (`admin.php`). Pelo phpMyAdmin também funciona:
`preco_fisico` vazio (NULL) significa que o livro não é vendido como físico, `preco_digital` vazio que não tem e-book,
e `estoque = 0` deixa o livro físico esgotado.
