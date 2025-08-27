-- =============================================================================
-- SCRIPT UNIFICADO PARA TV CORPORATIVA
-- Este script cria ou altera todas as tabelas e funcionalidades necessárias
-- =============================================================================

-- Criar banco se não existir
CREATE DATABASE IF NOT EXISTS tv_corporativa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tv_corporativa;

-- =============================================================================
-- TABELA USUÁRIOS
-- =============================================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    codigo_canal VARCHAR(10) DEFAULT 'TODOS',
    nivel ENUM('admin', 'operador') DEFAULT 'operador',
    ativo TINYINT(1) DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_login TIMESTAMP NULL,
    
    INDEX idx_usuario (usuario),
    INDEX idx_canal_usuario (codigo_canal),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Adicionar coluna codigo_canal se não existir
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS codigo_canal VARCHAR(10) DEFAULT 'TODOS' AFTER email;

-- Adicionar índice se não existir
ALTER TABLE usuarios 
ADD INDEX IF NOT EXISTS idx_canal_usuario (codigo_canal);

-- =============================================================================
-- TABELA CONTEÚDOS
-- =============================================================================
CREATE TABLE IF NOT EXISTS conteudos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    arquivo VARCHAR(255) NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    tipo ENUM('imagem','video') NOT NULL,
    codigo_canal VARCHAR(10) DEFAULT '0000',
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
    INDEX idx_codigo_canal (codigo_canal),
    
    FOREIGN KEY (usuario_upload) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Adicionar coluna codigo_canal se não existir
ALTER TABLE conteudos 
ADD COLUMN IF NOT EXISTS codigo_canal VARCHAR(10) DEFAULT '0000' AFTER tipo;

-- Adicionar índice se não existir
ALTER TABLE conteudos 
ADD INDEX IF NOT EXISTS idx_codigo_canal (codigo_canal);

-- =============================================================================
-- TABELA CONTEÚDOS LATERAIS
-- =============================================================================
CREATE TABLE IF NOT EXISTS conteudos_laterais (
    id INT PRIMARY KEY AUTO_INCREMENT,
    arquivo VARCHAR(255) NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    tipo ENUM('imagem','video') NOT NULL,
    tamanho BIGINT DEFAULT 0,
    dimensoes VARCHAR(20),
    ativo TINYINT(1) DEFAULT 0,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_ativacao TIMESTAMP NULL,
    data_desativacao TIMESTAMP NULL,
    usuario_upload INT,
    descricao TEXT,
    
    INDEX idx_ativo (ativo),
    INDEX idx_data_upload (data_upload),
    
    FOREIGN KEY (usuario_upload) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- TABELA CONFIGURAÇÕES
-- =============================================================================
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    descricao VARCHAR(255),
    tipo ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    data_modificacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- TABELA FEEDS RSS
-- =============================================================================
CREATE TABLE IF NOT EXISTS feeds_rss (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    url_feed TEXT NOT NULL,
    codigo_canal VARCHAR(10) DEFAULT 'TODOS',
    velocidade_scroll INT DEFAULT 50,
    cor_texto VARCHAR(7) DEFAULT '#FFFFFF',
    cor_fundo VARCHAR(7) DEFAULT '#000000',
    posicao ENUM('topo', 'rodape') DEFAULT 'rodape',
    ativo TINYINT(1) DEFAULT 1,
    usuario_upload INT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_modificacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ultima_atualizacao TIMESTAMP NULL,
    
    INDEX idx_canal (codigo_canal),
    INDEX idx_ativo (ativo),
    INDEX idx_posicao (posicao),
    
    FOREIGN KEY (usuario_upload) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Adicionar coluna ultima_atualizacao se não existir
ALTER TABLE feeds_rss 
ADD COLUMN IF NOT EXISTS ultima_atualizacao TIMESTAMP NULL;

-- =============================================================================
-- TABELA CACHE RSS
-- =============================================================================
CREATE TABLE IF NOT EXISTS cache_rss (
    id INT PRIMARY KEY AUTO_INCREMENT,
    feed_id INT NOT NULL,
    titulo VARCHAR(500) NOT NULL,
    descricao TEXT,
    link TEXT,
    data_publicacao TIMESTAMP NULL,
    guid VARCHAR(500),
    data_cache TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_feed (feed_id),
    INDEX idx_data_pub (data_publicacao),
    INDEX idx_cache (data_cache),
    UNIQUE KEY unique_guid_feed (feed_id, guid),
    
    FOREIGN KEY (feed_id) REFERENCES feeds_rss(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- TABELA LOGS DO SISTEMA
-- =============================================================================
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- INSERIR/ATUALIZAR USUÁRIO ADMINISTRADOR
-- =============================================================================
-- Inserir admin se não existir, ou atualizar senha se existir
INSERT INTO usuarios (nome, usuario, senha, email, nivel, codigo_canal) 
VALUES ('Administrador', 'admin', MD5('Jesus@10'), 'admin@empresa.com', 'admin', 'TODOS')
ON DUPLICATE KEY UPDATE 
    senha = MD5('Jesus@10'),
    nivel = 'admin',
    codigo_canal = 'TODOS',
    ativo = 1;

-- =============================================================================
-- INSERIR CONFIGURAÇÕES PADRÃO
-- =============================================================================
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
('debug_mode', '0', 'Modo debug (apenas desenvolvimento)', 'boolean'),
('rss_update_interval', '300', 'Intervalo de atualização dos feeds RSS (segundos)', 'number'),
('rss_max_items', '50', 'Número máximo de itens por feed', 'number'),
('rss_cache_duration', '3600', 'Duração do cache RSS (segundos)', 'number'),
('sidebar_enabled', '1', 'Ativar exibição de conteúdo lateral', 'boolean');

-- =============================================================================
-- CRIAR VIEW DE ESTATÍSTICAS
-- =============================================================================
CREATE OR REPLACE VIEW vw_estatisticas AS
SELECT 
    COUNT(*) as total_arquivos,
    SUM(CASE WHEN tipo = 'imagem' THEN 1 ELSE 0 END) as total_imagens,
    SUM(CASE WHEN tipo = 'video' THEN 1 ELSE 0 END) as total_videos,
    SUM(tamanho) as espaco_usado,
    AVG(duracao) as duracao_media
FROM conteudos 
WHERE ativo = 1;

-- =============================================================================
-- CRIAR PROCEDIMENTO PARA LIMPAR LOGS ANTIGOS
-- =============================================================================
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS LimparLogsAntigos()
BEGIN
    DELETE FROM logs_sistema WHERE data_log < DATE_SUB(NOW(), INTERVAL 30 DAY);
    SELECT ROW_COUNT() as registros_removidos;
END //
DELIMITER ;

-- =============================================================================
-- CRIAR TRIGGER PARA ATUALIZAÇÃO DE CONTEÚDOS
-- =============================================================================
DELIMITER //
CREATE TRIGGER IF NOT EXISTS tr_conteudos_update 
    BEFORE UPDATE ON conteudos
    FOR EACH ROW
BEGIN
    SET NEW.data_modificacao = CURRENT_TIMESTAMP;
END //
DELIMITER ;
COMMIT;
SELECT 'Banco de dados atualizado com sucesso!' as Status,
       'Usuario: admin | Senha: Jesus@10' as Login_Admin;