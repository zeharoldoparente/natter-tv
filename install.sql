CREATE DATABASE IF NOT EXISTS tv_corporativa CHARACTER SET utf8 COLLATE utf8_general_ci;
USE tv_corporativa;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    nivel ENUM('admin', 'operador') DEFAULT 'operador',
    ativo TINYINT(1) DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_login TIMESTAMP NULL,
    
    INDEX idx_usuario (usuario),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS conteudos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    arquivo VARCHAR(255) NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    tipo ENUM('imagem','video') NOT NULL,
    duracao INT DEFAULT 5,
    tamanho BIGINT DEFAULT 0,
    dimensoes VARCHAR(20),
    ativo TINYINT(1) DEFAULT 1,
    ordem_exibicao INT DEFAULT 0,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_modificacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    usuario_upload INT,
    
    INDEX idx_tipo (tipo),
    INDEX idx_ativo (ativo),
    INDEX idx_ordem (ordem_exibicao),
    INDEX idx_data (data_upload),
    
    FOREIGN KEY (usuario_upload) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS configuracoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    descricao VARCHAR(255),
    tipo ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    data_modificacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS logs_sistema (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT,
    acao VARCHAR(100) NOT NULL,
    detalhes TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    data_log TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_usuario (usuario_id),
    INDEX idx_acao (acao),
    INDEX idx_data (data_log),
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO usuarios (nome, usuario, senha, email, nivel) 
VALUES ('Administrador', 'admin', MD5('admin123'), 'admin@empresa.com', 'admin');

INSERT IGNORE INTO configuracoes (chave, valor, descricao, tipo) VALUES
('empresa_nome', 'TV Corporativa', 'Nome da empresa exibido na TV', 'string'),
('tv_update_interval', '30', 'Intervalo de verificação de atualizações (segundos)', 'number'),
('show_clock', '1', 'Mostrar relógio na TV', 'boolean'),
('show_date', '1', 'Mostrar data na TV', 'boolean'),
('default_image_duration', '5', 'Duração padrão para imagens (segundos)', 'number'),
('max_file_size', '52428800', 'Tamanho máximo de arquivo (bytes)', 'number'),
('allowed_extensions', 'jpg,jpeg,png,gif,mp4,avi,mov,wmv', 'Extensões de arquivo permitidas', 'string'),
('tv_background_color', '#000000', 'Cor de fundo da TV', 'string'),
('auto_fullscreen', '1', 'Ativar fullscreen automático na TV', 'boolean'),
('debug_mode', '0', 'Modo debug (apenas desenvolvimento)', 'boolean');


CREATE OR REPLACE VIEW vw_estatisticas AS
SELECT 
    COUNT(*) as total_arquivos,
    SUM(CASE WHEN tipo = 'imagem' THEN 1 ELSE 0 END) as total_imagens,
    SUM(CASE WHEN tipo = 'video' THEN 1 ELSE 0 END) as total_videos,
    SUM(tamanho) as espaco_usado,
    AVG(duracao) as duracao_media
FROM conteudos 
WHERE ativo = 1;

DELIMITER //
CREATE PROCEDURE IF NOT EXISTS LimparLogsAntigos()
BEGIN
    DELETE FROM logs_sistema WHERE data_log < DATE_SUB(NOW(), INTERVAL 30 DAY);
    SELECT ROW_COUNT() as registros_removidos;
END //
DELIMITER ;

DELIMITER //
CREATE TRIGGER IF NOT EXISTS tr_conteudos_update 
    BEFORE UPDATE ON conteudos
    FOR EACH ROW
BEGIN
    SET NEW.data_modificacao = CURRENT_TIMESTAMP;
END //
DELIMITER ;

ALTER TABLE conteudos ADD COLUMN codigo_canal VARCHAR(10) DEFAULT '0000' AFTER tipo;

ALTER TABLE conteudos ADD INDEX idx_codigo_canal (codigo_canal);

COMMIT;

SELECT 'Banco de dados criado com sucesso!' as Status,
       'Usuario: admin | Senha: admin123' as Login_Padrao;