# Lar de Letras — loja de ebooks

Protótipo do site da Lar de Letras, uma loja que vende só ebooks (Projeto de Extensão em Programação, UNINOVE).

Feito em PHP, HTML5, CSS e JavaScript.
O projeto foi feito para rodar no Xampp, funcionando tanto no Windows quanto Linux.


## Páginas

| Arquivo | O que faz |
|---|---|
| `index.php` | Página inicial: vitrine, lançamentos, mais vendidos e uma seção por gênero. A estante à esquerda leva até cada gênero. Também mostra os resultados da busca. |
| `livro.php?id=N` | Página do livro: detalhes, preço do ebook e "Adicionar ao carrinho". |
| `carrinho.php` | Carrinho (guardado na sessão): uma cópia de cada ebook, sem frete. Remover, escolher Pix ou cartão de crédito e finalizar. Para finalizar é preciso entrar na conta. |
| `pedidos.php` | Meus pedidos: os pedidos do cliente logado, com os ebooks, o total e a situação (Confirmando pagamento, Enviado ou Cancelado). Pedido em Confirmando pagamento pode ser cancelado pelo cliente. |
| `login.php` | Entrar ou criar conta. A senha é guardada com `password_hash`. |
| `sair.php` | Sai da conta. |
| `admin.php` | Área do administrador: lista, adiciona, edita e remove livros (preço, desconto, capa). Em **Gêneros** (`admin.php?acao=generos`) cria, renomeia, reordena e remove gêneros. Em **Banner** (`admin.php?acao=banner`) edita o carrossel da página inicial. Em **Pedidos** (`admin.php?acao=pedidos`) vê os pedidos de todos os clientes e muda a situação deles. |

## Administrador

O banco já vem com uma conta de administrador:

- **Email:** `lar.livros@admin.com`
- **Senha:** `123mudar`

Entre pela página de login com esse email e senha, como qualquer cliente. Você cai direto em **Gerenciar livros**.
Todo livro do catálogo é um ebook, com um único preço.
Ao lado do preço há um campo **Desconto (%)**, de 0 a 100, para promoções. O formulário mostra uma prévia do preço final,
e a loja exibe o preço antigo riscado, o novo e um selo (ex.: −20%). O preço original continua salvo: volte o desconto para 0% para encerrar a promoção.

Em **Gerenciar livros → Gêneros** o admin cria gêneros novos, renomeia, muda a ordem da estante (setas) e remove gêneros sem livros.
Também dá para criar um gênero na hora de cadastrar o livro: no campo Gênero, escolha **+ Novo gênero…** e digite o nome.
Na estante, todas as lombadas têm a mesma cor e o mesmo tamanho. Gênero novo usa o roxo da marca no banner.
Gênero sem livros não aparece na estante da loja.

O **banner principal** (carrossel da página inicial) é editado em **Gerenciar livros → Banner**: o admin escolhe quais livros
aparecem, a ordem dos slides (setas), a cor de fundo de cada slide, a chamada (frase acima do título) e a descrição.
Chamada ou descrição vazia usa o texto automático: a chamada explica por que o livro está ali (lançamento, promoção, mais vendido)
e a descrição é a do livro. Sem cor escolhida, o slide usa a cor do gênero. A cor do texto (branco ou escuro) é ajustada sozinha.
O "No banner principal" do formulário do livro continua funcionando e coloca o livro no fim do carrossel.

Ao finalizar a compra o pedido fica gravado com a situação **Confirmando pagamento**. Em **Gerenciar livros → Pedidos**
o admin muda para **Enviado** ou **Cancelado**, e o cliente acompanha em **Meus pedidos** (link no topo do site).
Enquanto o pedido está em **Confirmando pagamento**, o próprio cliente pode cancelá-lo em Meus pedidos.
A conta do admin gerencia a loja e não faz compras.

## Capas

O padrão da loja é **900 × 1200 px (proporção 3:4)**. Capas em outra proporção são exibidas em 3:4 com um pequeno corte
nas bordas, então vale salvar as novas já nesse tamanho.
Todas as capas (as do catálogo inicial e as enviadas pelo admin) ficam em `imagens/covers/`, e essa pasta precisa aceitar gravação. No Windows/XAMPP ela já funciona.
Livro sem capa (ou com uma imagem que não abre) aparece com `imagens/covers/no-cover.png`.

## Como rodar no XAMPP (Windows)

1. Copie a pasta `lar-de-letras` para `C:\xampp\htdocs\`.
2. Abra o **XAMPP Control Panel** e clique em **Start** no **Apache** e no **MySQL**.
3. Acesse <http://localhost/lar-de-letras/>.
   Na primeira visita, se o banco `livraria` não existir, o site cria sozinho com os dados de
   `banco/livraria.sql` (7 gêneros, 23 livros e o usuário admin). Não precisa importar nada. 
   Para voltar aos dados padrão, apague o banco `livraria` no MySQL ou phpMyAdmin e recarregue o site.
   (ou importe `banco/livraria.sql` na aba **Importar**, que apaga e recria o banco).

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
lar-de-letras/
  index.php, livro.php, carrinho.php, pedidos.php, login.php, sair.php
  includes/   conexao, funções, cabeçalho, rodapé e o card do livro
  css/        estilo.css
  js/         script.js (opcional: destaque da estante, carrossel e prévias do admin)
  imagens/    covers/ (capas, em minúsculas, sem acento e sem espaço) e icons/ (ícone do site)
  banco/      livraria.sql (estrutura e dados padrão do banco)
```

