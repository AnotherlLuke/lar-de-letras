-- Atualiza um banco `livraria` já importado SEM apagar livros nem usuários.
-- Necessário para o admin conseguir criar gêneros novos (Gerenciar livros → Gêneros).
-- phpMyAdmin: selecione o banco `livraria`, aba "Importar", escolha este arquivo.
-- Terminal: mysql -u root livraria < banco/atualizar-generos.sql
--
-- Gênero novo ganha o roxo da marca no banner, nas capas desenhadas e nos marcadores.

ALTER TABLE generos
    MODIFY cor       CHAR(7) NOT NULL DEFAULT '#6A3DB8',
    MODIFY cor_texto CHAR(7) NOT NULL DEFAULT '#FFFFFF';
