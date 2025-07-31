# 📺 TV Corporativa - Sistema Profissional

Sistema completo para gerenciamento de conteúdo em TVs corporativas, desenvolvido em PHP com interface moderna e funcionalidades avançadas.

## ✨ Funcionalidades Principais

### 🎛️ **Painel Administrativo**

-  Dashboard moderno com estatísticas em tempo real
-  Upload com drag & drop e preview instantâneo
-  Controle remoto das TVs (atualização instantânea)
-  Sistema completo de logs e auditoria
-  Gerenciamento de usuários e permissões

### 📺 **TV Display**

-  Reprodução automática de imagens e vídeos
-  Atualização em tempo real sem interromper conteúdo
-  Overlay com informações (logo, data/hora)
-  Controles via teclado (setas, espaço, fullscreen)
-  Transições suaves entre conteúdos

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

### Login Padrão

-  **Usuário:** admin
-  **Senha:** admin123

> ⚠️ **Importante:** Altere a senha padrão após o primeiro login!

## 📁 Estrutura do Projeto

```
tv-corporativa/
├── admin/                  # Painel administrativo
│   ├── dashboard.php      # Dashboard principal
│   ├── upload.php         # Upload de arquivos
│   ├── index.php          # Login
│   └── logout.php         # Logout
├── tv/                     # Interface da TV
│   ├── index.php          # Player da TV
│   └── get_contents.php   # API de conteúdos
├── includes/               # Arquivos do sistema
│   ├── db.php             # Conexão com banco
│   ├── functions.php      # Funções auxiliares
│   └── install_tables.php # Instalação de tabelas
├── assets/                 # Recursos estáticos
│   ├── css/               # Folhas de estilo
│   └── js/                # Scripts JavaScript
├── uploads/                # Arquivos de mídia
├── temp/                  # Arquivos temporários
└── install.sql           # Script de instalação
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

### Visualizando a TV

1. Acesse `http://seudominio.com/tv/`
2. A página entrará em fullscreen automaticamente
3. Use as teclas:
   -  **Setas:** Navegar entre conteúdos
   -  **Espaço:** Próximo conteúdo
   -  **F:** Toggle fullscreen
   -  **R:** Recarregar

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
