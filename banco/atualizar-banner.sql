-- Atualiza um banco `livraria` já importado SEM apagar livros nem usuários.
-- Necessário para o admin editar o banner (Gerenciar livros → Banner).
-- phpMyAdmin: selecione o banco `livraria`, aba "Importar", escolha este arquivo.
-- Terminal: mysql -u root livraria < banco/atualizar-banner.sql

ALTER TABLE livros
    ADD COLUMN banner_ordem     TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER banner,
    ADD COLUMN banner_cor       CHAR(7)      NULL AFTER banner_ordem,
    ADD COLUMN banner_chamada   VARCHAR(120) NULL AFTER banner_cor,
    ADD COLUMN banner_descricao TEXT         NULL AFTER banner_chamada;
