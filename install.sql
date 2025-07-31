CREATE DATABASE IF NOT EXISTS tv_corporativa;
USE tv_corporativa;

CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100),
    usuario VARCHAR(50) UNIQUE,
    senha VARCHAR(255)
);

INSERT INTO usuarios (nome, usuario, senha) 
VALUES ('Administrador', 'admin', MD5('1234'));

CREATE TABLE conteudos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    arquivo VARCHAR(255),
    tipo ENUM('imagem','video'),
    duracao INT DEFAULT 5,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
