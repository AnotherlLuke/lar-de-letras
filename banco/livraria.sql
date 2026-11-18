-- Lar de Letras — banco de dados do protótipo
-- Importe este arquivo no phpMyAdmin (aba "Importar") ou pelo terminal:
--   mysql -u root < banco/livraria.sql
-- ATENÇÃO: apaga e recria o banco `livraria` inteiro.

DROP DATABASE IF EXISTS livraria;
CREATE DATABASE livraria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE livraria;

CREATE TABLE generos (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(60)  NOT NULL,
    slug       VARCHAR(60)  NOT NULL UNIQUE,  -- usado no endereço: index.php#ficcao-cientifica
    cor        CHAR(7)      NOT NULL DEFAULT '#6A3DB8',  -- banner, capas desenhadas e marcadores
    cor_texto  CHAR(7)      NOT NULL DEFAULT '#FFFFFF',
    ordem      TINYINT UNSIGNED NOT NULL DEFAULT 0  -- posição na estante
) ENGINE=InnoDB;

CREATE TABLE livros (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo         VARCHAR(160) NOT NULL,
    autor          VARCHAR(160) NOT NULL,
    descricao      TEXT         NULL,
    genero_id      INT UNSIGNED NOT NULL,
    idioma         VARCHAR(30)  NOT NULL DEFAULT 'Português',
    ano            SMALLINT     NULL,
    paginas        SMALLINT UNSIGNED NULL,
    imagem         VARCHAR(160) NULL,          -- NULL = sem capa (o site desenha uma)
    preco_fisico   DECIMAL(8,2) NULL,          -- NULL = não vende livro físico
    estoque        INT UNSIGNED NOT NULL DEFAULT 0,
    preco_digital  DECIMAL(8,2) NULL,          -- NULL = não tem versão digital
    desconto_fisico  TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- % de desconto (promoção), 0 a 100
    desconto_digital TINYINT UNSIGNED NOT NULL DEFAULT 0,
    lancamento     TINYINT(1)   NOT NULL DEFAULT 0,
    mais_vendido   TINYINT(1)   NOT NULL DEFAULT 0,
    banner         TINYINT(1)   NOT NULL DEFAULT 0,   -- aparece no carrossel da página inicial
    banner_ordem     TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- posição no carrossel
    banner_cor       CHAR(7)      NULL,     -- fundo do slide; NULL = cor do gênero
    banner_chamada   VARCHAR(120) NULL,     -- frase acima do título; NULL = texto automático
    banner_descricao TEXT         NULL,     -- texto do slide; NULL = descrição do livro
    CONSTRAINT chk_preco_fisico  CHECK (preco_fisico IS NULL OR preco_fisico > 0),
    CONSTRAINT chk_preco_digital CHECK (preco_digital IS NULL OR preco_digital > 0),
    CONSTRAINT chk_algum_formato CHECK (preco_fisico IS NOT NULL OR preco_digital IS NOT NULL),
    CONSTRAINT chk_descontos CHECK (desconto_fisico <= 100 AND desconto_digital <= 100),
    CONSTRAINT fk_livro_genero FOREIGN KEY (genero_id) REFERENCES generos (id)
) ENGINE=InnoDB;

CREATE TABLE usuarios (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(100) NOT NULL,
    email       VARCHAR(160) NOT NULL UNIQUE,
    senha_hash  VARCHAR(255) NOT NULL,
    tipo        ENUM('cliente', 'admin') NOT NULL DEFAULT 'cliente',
    criado_em   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Administrador da loja: usuário "admin", senha "123mudar" (troque depois do primeiro acesso).
-- O admin entra pelo login com "admin" no lugar do e-mail.
INSERT INTO usuarios (nome, email, senha_hash, tipo) VALUES
('Administrador', 'admin', '$2y$12$fP/c1DJlhg6kUQ7OjBivtOehCRER/iZCjnUid5b7c0sweCMdONLnm', 'admin');

INSERT INTO generos (id, nome, slug, cor, cor_texto, ordem) VALUES
(1, 'Romance',                 'romance',                 '#C2456A', '#FFFFFF', 1),
(2, 'Ficção científica',       'ficcao-cientifica',       '#2369B0', '#FFFFFF', 2),
(3, 'Fantasia',                'fantasia',                '#2F7D50', '#FFFFFF', 3),
(4, 'Terror',                  'terror',                  '#353840', '#FFFFFF', 4),
(5, 'Aventura',                'aventura',                '#E88928', '#1D2238', 5),
(6, 'Tecnologia',              'tecnologia',              '#0E7C86', '#FFFFFF', 6),
(7, 'Desenvolvimento pessoal', 'desenvolvimento-pessoal', '#E3B23C', '#1D2238', 7);

INSERT INTO livros
(titulo, autor, descricao, genero_id, idioma, ano, paginas, imagem, preco_fisico, estoque, preco_digital, lancamento, mais_vendido) VALUES
-- Romance
('Dom Casmurro', 'Machado de Assis',
 'Bentinho, já velho, revisita a juventude ao lado de Capitu e tenta provar a si mesmo uma traição que talvez nunca tenha acontecido.',
 1, 'Português', 1899, 256, 'dom-casmurro.jpg', 34.90, 18, 9.90, 0, 1),
('Memórias Póstumas de Brás Cubas', 'Machado de Assis',
 'Um defunto autor narra a própria vida com ironia, sem pressa e sem perdoar ninguém, muito menos a si mesmo.',
 1, 'Português', 1881, 288, 'memorias-postumas.jpg', 36.90, 12, 9.90, 0, 0),
('O Morro dos Ventos Uivantes', 'Emily Brontë',
 'Nos pântanos de Yorkshire, o amor entre Catherine e Heathcliff atravessa gerações e se transforma em vingança.',
 1, 'Português', 1847, 416, 'morro-dos-ventos.jpg', 49.90, 7, 24.90, 0, 0),

-- Ficção científica
('Neuromancer', 'William Gibson',
 'Case, um hacker em decadência, ganha uma última chance de voltar à matrix, junto com um trabalho que pode ser grande demais para ele.',
 2, 'Português', 1984, 320, 'neuromancer.jpg', 54.90, 0, 32.90, 0, 0),
('A Guerra dos Mundos', 'H. G. Wells',
 'Cilindros caem perto de Londres e, de dentro deles, sai uma invasão que a humanidade não tem como deter.',
 2, 'Português', 1898, 224, 'guerra-dos-mundos.jpg', 39.90, 10, NULL, 0, 0),
('A Máquina do Tempo', 'H. G. Wells',
 'Um inventor viaja ao ano 802.701 e encontra a humanidade dividida entre os elóis da superfície e os morlocks do subsolo.',
 2, 'Português', 1895, 144, 'maquina-do-tempo.jpg', 29.90, 15, NULL, 0, 0),
('Viagem ao Centro da Terra', 'Júlio Verne',
 'O professor Lidenbrock decifra um manuscrito antigo e desce pela cratera de um vulcão islandês rumo às profundezas do planeta.',
 2, 'Português', 1864, 272, 'viagem-centro-terra.jpg', 37.90, 9, 12.90, 0, 0),

-- Fantasia
('O Senhor dos Anéis', 'J. R. R. Tolkien',
 'Frodo herda um anel que não pode ser usado nem guardado, e parte com oito companheiros para destruí-lo na Montanha da Perdição.',
 3, 'Português', 1954, 1216, 'senhor-dos-aneis.jpg', 149.90, 6, NULL, 0, 1),
('As Crônicas de Nárnia', 'C. S. Lewis',
 'Sete histórias em um volume: atravessando um guarda-roupa, crianças chegam a um reino de animais falantes protegido pelo leão Aslan.',
 3, 'Português', 1956, 752, 'narnia.jpg', 89.90, 11, 44.90, 0, 0),
('O Cavaleiro dos Sete Reinos', 'George R. R. Martin',
 'Cem anos antes de A Guerra dos Tronos, o cavaleiro andante Dunk e seu escudeiro Egg cruzam Westeros atrás de torneios e de honra.',
 3, 'Português', 2015, 336, 'cavaleiro-dos-sete-reinos.jpg', 69.90, 8, 34.90, 1, 0),
('Tress, a Garota do Mar Esmeralda', 'Brandon Sanderson',
 'Tress vive numa ilha cercada por um mar de esporos verdes. Quando o rapaz que ela ama é levado, ela embarca sozinha para buscá-lo.',
 3, 'Português', 2023, 464, 'tress.jpg', 79.90, 14, 39.90, 1, 0),
('Warbreaker', 'Brandon Sanderson',
 'Duas princesas, um deus que não fala e uma magia alimentada pelas cores e pelo fôlego das pessoas.',
 3, 'Português', 2009, 656, 'warbreaker.jpg', 89.90, 5, 42.90, 1, 0),

-- Terror
('O Iluminado', 'Stephen King',
 'Jack Torrance aceita cuidar de um hotel isolado durante o inverno. Danny, seu filho, percebe antes de todos que o hotel tem vontade própria.',
 4, 'Português', 1977, 520, 'o-iluminado.jpg', 69.90, 9, 34.90, 0, 1),
('O Chamado de Cthulhu', 'H. P. Lovecraft',
 'Contos em que estudiosos descobrem, pouco a pouco, que algo muito antigo dorme no fundo do oceano.',
 4, 'Português', 1928, 192, 'cthulhu.jpg', 44.90, 13, 19.90, 0, 0),
('Frankenstein', 'Mary Shelley',
 'Victor Frankenstein dá vida a uma criatura e foge dela. Sozinha, a criatura aprende a ler, a pensar e a odiar.',
 4, 'Português', 1818, 280, 'frankenstein.jpg', 39.90, 4, 14.90, 0, 0),
('O Menino que Desenhava Monstros', 'Keith Donohue',
 'Jack Peter não sai de casa desde que quase se afogou. Agora, os monstros que ele desenha começam a aparecer do lado de fora.',
 4, 'Português', 2014, 304, 'o-menino-que-desenhava-monstros.jpg', 59.90, 6, 29.90, 1, 0),

-- Aventura
('Robinson Crusoé', 'Daniel Defoe',
 'Único sobrevivente de um naufrágio, Robinson passa 28 anos numa ilha deserta construindo, sozinho, uma vida inteira.',
 5, 'Português', 1719, 336, 'robinson-crusoe.jpg', 34.90, 10, NULL, 0, 0),
('A Ilha do Tesouro', 'Robert Louis Stevenson',
 'Jim Hawkins encontra um mapa do tesouro e embarca com uma tripulação em que não dá para confiar, principalmente no cozinheiro Long John Silver.',
 5, 'Português', 1883, 240, 'ilha-do-tesouro.jpg', 32.90, 16, 9.90, 0, 0),
('As Aventuras de Tom Sawyer', 'Mark Twain',
 'Às margens do Mississippi, Tom foge da escola, assiste ao próprio velório e testemunha um crime que não pode contar a ninguém.',
 5, 'Português', 1876, 256, 'tom-sawyer.jpg', 34.90, 12, 9.90, 0, 0),

-- Tecnologia
('Clean Code', 'Robert C. Martin',
 'Como escrever código que outras pessoas conseguem ler e mudar: nomes, funções, testes e refatoração, com exemplos em Java.',
 6, 'Inglês', 2008, 464, 'clean-code.jpg', 189.90, 5, 89.90, 0, 1),

-- Desenvolvimento pessoal
('Como Fazer Amigos e Influenciar Pessoas na Era Digital', 'Dale Carnegie & Associates',
 'Os princípios clássicos de Carnegie aplicados a e-mails, redes sociais e conversas que hoje acontecem pela tela.',
 7, 'Português', 2011, 304, 'como-fazer-amigos.jpg', 49.90, 10, 24.90, 1, 0),
('Como Falar em Público e Encantar as Pessoas', 'Dale Carnegie',
 'Exercícios e conselhos para perder o medo do palco e montar uma fala que prenda a atenção do começo ao fim.',
 7, 'Português', NULL, 224, 'como-falar-em-publico.jpg', 44.90, 8, 22.90, 0, 0),
('O Corpo Fala', 'Pierre Weil e Roland Tompakow',
 'Um guia ilustrado da comunicação não verbal: o que gestos, postura e expressões dizem antes de qualquer palavra.',
 7, 'Português', NULL, 288, 'o-corpo-fala.jpg', 42.90, 20, 21.90, 0, 1);

-- Promoções de exemplo
UPDATE livros SET desconto_fisico = 20 WHERE titulo = 'O Iluminado';
UPDATE livros SET desconto_digital = 30 WHERE titulo = 'Neuromancer';

-- Livros no banner principal (carrossel da página inicial)
UPDATE livros SET banner = 1 WHERE titulo IN
('Tress, a Garota do Mar Esmeralda', 'O Iluminado', 'O Cavaleiro dos Sete Reinos', 'Neuromancer', 'Dom Casmurro');
UPDATE livros SET banner_cor = '#B92222' WHERE titulo = 'O Cavaleiro dos Sete Reinos';
