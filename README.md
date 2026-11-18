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
| `admin.php` | Área do administrador: lista, adiciona, edita e remove livros (preço, desconto, estoque, formato físico e/ou e-book, capa). Em **Gêneros** (`admin.php?acao=generos`) cria, renomeia, reordena e remove gêneros. Em **Banner** (`admin.php?acao=banner`) edita o carrossel da página inicial. |

## Administrador

O banco já vem com uma conta de administrador:

- **Usuário:** `admin`
- **Senha:** `123mudar`

Entre pela página de login, digitando `admin` no campo "E-mail ou usuário". Você cai direto em **Gerenciar livros**.
Um livro pode ser vendido só físico, só como e-book ou nos dois formatos. Quem tem e-book ganha a etiqueta "digital" na loja.
Ao lado de cada preço há um campo **Desconto (%)**, de 0 a 100, para promoções. O formulário mostra uma prévia do preço final,
e a loja exibe o preço antigo riscado, o novo e um selo (ex.: −20%). O preço original continua salvo: volte o desconto para 0% para encerrar a promoção.

Em **Gerenciar livros → Gêneros** o admin cria gêneros novos, renomeia, muda a ordem da estante (setas) e remove gêneros sem livros.
Também dá para criar um gênero na hora de cadastrar o livro: no campo Gênero, escolha **+ Novo gênero…** e digite o nome.
Na estante, todas as lombadas têm a mesma cor e o mesmo tamanho. Gênero novo usa o roxo da marca no banner e nas capas desenhadas.
Gênero sem livros não aparece na estante da loja.

O **banner principal** (carrossel da página inicial) é editado em **Gerenciar livros → Banner**: o admin escolhe quais livros
aparecem, a ordem dos slides (setas), a cor de fundo de cada slide, a chamada (frase acima do título) e a descrição.
Chamada ou descrição vazia usa o texto automático: a chamada explica por que o livro está ali (lançamento, promoção, mais vendido)
e a descrição é a do livro. Sem cor escolhida, o slide usa a cor do gênero. A cor do texto (branco ou escuro) é ajustada sozinha.
O "No banner principal" do formulário do livro continua funcionando e coloca o livro no fim do carrossel.

## Capas

O padrão da loja é **900 × 1200 px (proporção 3:4)**. Capas em outra proporção são exibidas em 3:4 com um pequeno corte
nas bordas, então vale salvar as novas já nesse tamanho.
As capas enviadas ficam em `imagens/enviadas/`, e essa pasta precisa existir e aceitar gravação. No Windows/XAMPP ela já funciona.

## Como rodar no XAMPP (Windows)

1. Copie a pasta `LarLetras` para `C:\xampp\htdocs\`.
2. Abra o **XAMPP Control Panel** e clique em **Start** no **Apache** e no **MySQL**.
3. Acesse <http://localhost/LarLetras/>.
   Na primeira visita, se o banco `livraria` não existir, o site cria sozinho com os dados de
   `banco/livraria.sql` (7 gêneros, 23 livros e o usuário admin). Não precisa importar nada.
   Para voltar aos dados padrão, apague o banco `livraria` no phpMyAdmin e recarregue o site
   (ou importe `banco/livraria.sql` na aba **Importar**, que apaga e recria o banco).
   Já tinha importado uma versão antiga? Importe também `banco/atualizar-generos.sql` (para criar gêneros)
   e `banco/atualizar-banner.sql` (para editar o banner). Eles não apagam livros nem usuários.

## Como rodar no Linux

- **Com XAMPP (LAMPP):** copie a pasta para `/opt/lampp/htdocs/`, inicie com `sudo /opt/lampp/lampp start`
  e siga o passo 3 acima.
- **Sem XAMPP:** com `php` e MariaDB instalados, rode `php -S localhost:8000` dentro da pasta e acesse
  <http://localhost:8000>. O banco é criado na primeira visita (ou antes, com `php includes/criar-banco.php`).

## Configuração

Os dados de acesso ao banco ficam em `includes/conexao.php`: usuário `root`, sem senha, que é o padrão do XAMPP.
Se o seu MySQL tiver senha, altere `$senha` nesse arquivo, ou crie `includes/conexao.local.php`
(fica fora do git, pelo `.gitignore`) para trocar usuário e senha só na sua máquina.
O usuário precisa ter permissão para criar o banco na primeira execução.

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
