# 📺 TV Corporativa - Sistema Profissional

Sistema completo para gerenciamento de conteúdo em TVs corporativas, desenvolvido em PHP com interface moderna e funcionalidades avançadas.

## ✨ Funcionalidades Principais

### 🎛️ **Painel Administrativo**

-  Dashboard moderno com estatísticas em tempo real
-  Upload com drag & drop e preview instantâneo
-  Upload de conteúdo para a barra lateral (banners/imagens)
-  Controle remoto das TVs (atualização instantânea)
-  Sistema completo de logs e auditoria
-  Gerenciamento de canais e playlists
-  Cadastro e pré-visualização de feeds RSS
-  Gerenciamento de usuários e permissões

### 📺 **TV Display**

-  Reprodução automática de imagens e vídeos
-  Atualização em tempo real sem interromper conteúdo
-  Overlay com informações (logo, data/hora)
-  Controles via teclado (setas, espaço, fullscreen)
-  Transições suaves entre conteúdos
-  Exibição de canais específicos selecionados
-  Barra lateral dinâmica com RSS e anúncios

### 🔒 **Segurança**

-  Proteção contra SQL Injection
-  Tokens CSRF em formulários
-  Sistema de sessões seguro
-  Validação de arquivos no cliente e servidor
-  Logs detalhados de todas as ações

## 🚀 Instalação

### Pré-requisitos

-  PHP 7.4 ou superior
-  MySQL 5.7 ou superior
-  Servidor web (Apache/Nginx)
-  Extensões PHP: mysqli, fileinfo, gd

### Passos da Instalação

1. **Clone/Baixe o projeto**

```bash
git clone [seu-repositorio]
cd tv-corporativa
```

2. **Configure o banco de dados**

-  Edite `includes/db.php` com suas credenciais
-  Execute o script `install.sql` no seu MySQL

3. **Configure permissões**

```bash
chmod 755 uploads/
chmod 755 temp/
```

4. **Acesse o sistema**

-  Admin: `http://seudominio.com/admin/`
-  TV: `http://seudominio.com/tv/`

5. **(Opcional) Configure o cron para atualizar o RSS**

```bash
* * * * * php /caminho/para/cron/update_rss.php >/dev/null 2>&1
```

### Login Padrão

-  **Usuário:** admin
-  **Senha:** admin123

> ⚠️ **Importante:** Altere a senha padrão após o primeiro login!

## 📁 Estrutura do Projeto

```
tv-corporativa/
├── admin/                    # Painel administrativo
│   ├── dashboard.php         # Dashboard principal
│   ├── upload.php            # Upload de arquivos
│   ├── rss.php               # Configuração de feeds RSS
│   ├── test_rss.php          # Teste de RSS
│   ├── test_rss_manual.php   # Teste manual de RSS
│   ├── index.php             # Login
│   └── logout.php            # Logout
├── tv/                       # Interface da TV
│   ├── index.php             # Player da TV
│   ├── selecionar_canal.php  # Seleção de canais
│   ├── get_contents.php      # API de conteúdos
│   └── get_rss.php           # API de RSS
├── includes/                 # Arquivos do sistema
│   ├── db.php                # Conexão com banco
│   ├── functions.php         # Funções auxiliares
│   ├── rss_functions.php     # Utilidades de RSS
│   ├── sidebar_content.php   # Conteúdo da barra lateral
│   └── install_tables.php    # Instalação de tabelas
├── cron/                     # Rotinas agendadas
│   └── update_rss.php        # Atualização automática do RSS
├── assets/                   # Recursos estáticos
│   ├── css/                  # Folhas de estilo
│   └── js/                   # Scripts JavaScript
├── uploads/                  # Arquivos de mídia
├── temp/                     # Arquivos temporários
└── install.sql               # Script de instalação
```

## 💡 Como Usar

### Fazendo Upload de Conteúdo

1. Acesse o painel admin
2. Vá em "Upload" no menu lateral
3. Arraste arquivos para a área de upload ou clique para selecionar
4. Para imagens: defina o tempo de exibição
5. Clique em "Enviar Arquivo"

**Formatos Suportados:**

-  **Imagens:** JPG, PNG, GIF
-  **Vídeos:** MP4, AVI, MOV, WMV
-  **Tamanho máximo:** 50MB por arquivo

### Controlando a TV

1. No dashboard, use o botão "Atualizar TV Agora"
2. A TV verifica atualizações automaticamente a cada 30 segundos
3. Novos conteúdos aparecem automaticamente sem interromper a reprodução

### Gerenciando Feeds RSS e Barra Lateral

1. Acesse `http://seudominio.com/admin/rss.php`
2. Informe a URL do feed e salve
3. Agende o cron `cron/update_rss.php` para atualizar as notícias
4. O feed aparecerá automaticamente na barra lateral da TV

### Selecionando Canais

1. Na TV, acesse `http://seudominio.com/tv/selecionar_canal.php`
2. Escolha o canal desejado para iniciar a reprodução

### Visualizando a TV

1. Acesse `http://seudominio.com/tv/`
2. A página entrará em fullscreen automaticamente
3. Use as teclas:
   -  **Seta Direita** ou **Espaço:** Próximo conteúdo
   -  **Seta Esquerda:** Conteúdo anterior
   -  **F:** Alternar fullscreen
   -  **R:** Recarregar a página
   -  **C:** Selecionar outro canal
   -  **U:** Atualizar RSS manualmente

## ⚙️ Configurações Avançadas

### Personalização da TV

Edite as configurações no banco de dados (tabela `configuracoes`):

```sql
-- Alterar intervalo de verificação (segundos)
UPDATE configuracoes SET valor = '60' WHERE chave = 'tv_update_interval';

-- Alterar nome da empresa
UPDATE configuracoes SET valor = 'Minha Empresa' WHERE chave = 'empresa_nome';

-- Desabilitar relógio na TV
UPDATE configuracoes SET valor = '0' WHERE chave = 'show_clock';
```

### Backup dos Dados

```sql
-- Backup apenas das configurações e conteúdos
mysqldump -u usuario -p tv_corporativa conteudos configuracoes > backup.sql
```

### Limpeza de Logs

O sistema mantém logs por 30 dias. Para limpeza manual:

```sql
CALL LimparLogsAntigos();
```

## 🔧 Manutenção

### Verificação de Integridade

Periodicamente, verifique se os arquivos físicos coincidem com o banco:

```php
// Execute este script para identificar arquivos órfãos
include 'includes/db.php';

$arquivos_banco = [];
$result = $conn->query("SELECT arquivo FROM conteudos WHERE ativo = 1");
while($row = $result->fetch_assoc()) {
    $arquivos_banco[] = $row['arquivo'];
}

$arquivos_pasta = scandir('uploads/');
$orfaos = array_diff($arquivos_pasta, $arquivos_banco);

echo "Arquivos órfãos encontrados: " . count($orfaos);
```

### Monitoramento de Espaço

```sql
-- Ver espaço utilizado
SELECT
    COUNT(*) as total_arquivos,
    ROUND(SUM(tamanho)/1024/1024, 2) as espaco_mb
FROM conteudos WHERE ativo = 1;
```

## 🚨 Troubleshooting

### Problemas Comuns

**TV não atualiza automaticamente:**

-  Verifique se a pasta `temp/` tem permissão de escrita
-  Confirme que o JavaScript está habilitado
-  Verifique o console do navegador para erros

**Upload falha:**

-  Verifique permissões da pasta `uploads/`
-  Confirme limites de upload no PHP (`upload_max_filesize`, `post_max_size`)
-  Verifique espaço em disco

**Erro de conexão com banco:**

-  Confirme credenciais em `includes/db.php`
-  Verifique se o MySQL está rodando
-  Confirme que o banco `tv_corporativa` existe

### Logs do Sistema

Os logs ficam na tabela `logs_sistema` e incluem:

-  Login/logout de usuários
-  Upload/exclusão de arquivos
-  Atualizações da TV
-  Erros do sistema

---

**Desenvolvido com ❤️ para modernizar sua comunicação!**

Dúvidas ou suestões entre em contato.
