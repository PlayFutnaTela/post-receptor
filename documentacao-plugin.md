# Documentação do Plugin Post Receptor

## Visão Geral

O **Post Receptor** é um plugin para WordPress desenvolvido por Alexandre Chaves, projetado para atuar como um receptor de posts enviados via REST API por um plugin emissor. Ele permite a criação e atualização automática de posts no WordPress, incluindo tradução de conteúdo, gerenciamento de mídias, categorias, tags, autores e integração com plugins como Yoast SEO e Elementor.

O plugin é ideal para cenários de replicação de conteúdo entre sites WordPress, especialmente em contextos multilíngues, onde o conteúdo precisa ser traduzido automaticamente.

## Funcionalidades Principais

### 1. Recebimento de Posts via REST API
- **Endpoint Principal**: `/wp-json/post-receptor/v1/receive`
- Recebe dados JSON contendo informações completas do post (título, conteúdo, excerpt, categorias, tags, mídias, etc.)
- Autenticação via token Bearer no header `Authorization`
- Suporte a criação e atualização de posts existentes

### 2. Tradução Automática de Conteúdo
- Integração com OpenAI GPT-4o-mini para traduções
- Tradução contextual (título, corpo, excerpt, categorias, tags, metadados de mídia)
- Suporte a múltiplos idiomas (pt_BR, pt_PT, en_US, en_GB, es_ES, fr_FR, de_DE)
- Preservação de HTML no conteúdo durante a tradução
- System prompt configurável para orientar o estilo de tradução

### 3. Gerenciamento de Mídias
- Download e configuração de imagem destacada
- Tradução de metadados de mídia (alt, title, caption, description)
- Suporte a anexos múltiplos
- Verificação de duplicatas para evitar downloads desnecessários

### 4. Gerenciamento de Taxonomias
- Criação automática de categorias e tags traduzidas
- Geração de slugs únicos e limpos
- Verificação de existência antes de criar termos duplicados

### 5. Gerenciamento de Autores
- Busca de autores existentes por login
- Criação automática de novos usuários com dados fornecidos
- Fallback para usuário admin ou atual se necessário

### 6. Integração com Plugins
- **Yoast SEO**: Configuração de meta description e focus keyword traduzidos
- **Elementor**: Importação de dados do construtor de páginas (sem tradução)

### 7. Operações Adicionais
- **Atualização de Status**: Endpoint `/wp-json/post-receptor/v1/update-status` para alterar status do post
- **Exclusão de Posts**: Endpoint `/wp-json/post-receptor/v1/delete` para remoção permanente
- **Verificação de Token**: Endpoint `/wp-json/post-receptor/v1/check-token` para validar autenticação

## Estrutura do Plugin

### Arquivos Principais
- `post-receptor.php`: Arquivo principal do plugin, responsável por inicialização e carregamento de dependências
- `includes/class-post-receptor.php`: Classe principal que orquestra o processamento dos posts
- `includes/rest-endpoints.php`: Definição dos endpoints REST API
- `includes/admin-settings.php`: Página de configurações gerais no admin
- `includes/admin-api-settings.php`: Configurações específicas da API OpenAI

### Classes Auxiliares
- `Post_Utils`: Utilitários gerais (busca de posts existentes, geração de slugs)
- `Post_Translation`: Gerenciamento de traduções via OpenAI
- `Post_Media`: Processamento de mídias e imagens destacadas
- `Post_Terms`: Gerenciamento de categorias e tags
- `Post_Author`: Gerenciamento de autores

## Fluxo de Funcionamento

### 1. Recebimento de Dados
1. O plugin emissor envia uma requisição POST para `/wp-json/post-receptor/v1/receive`
2. Os dados incluem: ID do post emissor, título, conteúdo, excerpt, idioma de origem, categorias, tags, mídias, dados Yoast, dados Elementor, etc.
3. Autenticação é verificada via token Bearer

### 2. Processamento de Tradução
1. Verifica se o idioma de origem difere do idioma de destino configurado
2. Para cada campo traduzível, chama a API OpenAI com prompt contextual
3. Preserva estrutura HTML no conteúdo
4. Aplica system prompt configurado para manter consistência de estilo

### 3. Criação/Atualização do Post
1. Busca post existente pelo meta `emissor_post_id`
2. Gera slug único a partir do título traduzido
3. Processa autor (busca ou cria novo usuário)
4. Processa categorias e tags (traduz, verifica existência, cria se necessário)
5. Processa mídias (baixa imagem destacada, traduz metadados)
6. Cria ou atualiza o post com todos os dados
7. Define metadados Yoast e Elementor

### 4. Retorno da Resposta
- Retorna sucesso com ID do post criado/atualizado
- Em caso de erro, retorna mensagem de erro apropriada

## Configurações Administrativas

### Página Principal (Post Receptor)
- **URL do Emissor**: URL do site que enviará os posts
- **Idioma de Destino**: Idioma para o qual o conteúdo será traduzido
- **System Prompt**: Texto para orientar o GPT nas traduções
- **Token de Autenticação**: Geração de token seguro para autenticação API

### Página API GPT
- **OpenAI API Key**: Chave da API OpenAI para traduções
- **System Prompt**: Mesmo campo da página principal (duplicado por compatibilidade)
- **Revogação de Chave**: Opção para remover a chave armazenada

## Formato dos Dados Esperados

### Estrutura JSON de Entrada
```json
{
  "ID": 123,
  "title": "Título do Post",
  "content": "<p>Conteúdo HTML</p>",
  "excerpt": "Resumo do post",
  "origin_language": "pt_BR",
  "categories": [
    {"name": "Categoria 1"},
    {"name": "Categoria 2"}
  ],
  "tags": [
    {"name": "Tag 1"},
    {"name": "Tag 2"}
  ],
  "author": "login_do_autor",
  "author_data": {
    "user_login": "login_do_autor",
    "user_email": "email@exemplo.com",
    "first_name": "Nome",
    "last_name": "Sobrenome",
    "display_name": "Nome Completo"
  },
  "media": {
    "featured_image": {
      "url": "https://exemplo.com/imagem.jpg",
      "alt": "Texto alternativo",
      "title": "Título da imagem",
      "caption": "Legenda",
      "description": "Descrição"
    },
    "attachments": [...]
  },
  "yoast_metadesc": "Meta description para SEO",
  "focus_keyword": "Palavra-chave principal",
  "elementor": {...},
  "status": "publish"
}
```

## Considerações Técnicas

### Segurança
- Autenticação obrigatória via token Bearer
- Sanitização de dados de entrada
- Verificação de permissões de usuário admin
- Proteção contra acesso direto aos arquivos

### Performance
- Tentativas múltiplas (até 3) para chamadas à API OpenAI
- Timeout de 30 segundos para requisições externas
- Verificação de duplicatas para evitar downloads desnecessários
- Logging extensivo para debug

### Limitações
- Suporte limitado a idiomas (7 idiomas pré-definidos)
- Dependência de API externa (OpenAI) para traduções
- Não traduz dados do Elementor (preserva como está)
- Requer configuração manual de tokens e chaves API

### Logs e Debug
- Logs extensivos no error_log do WordPress
- Informações detalhadas sobre cada etapa do processamento
- Facilita identificação de problemas em produção

## Casos de Uso

1. **Replicação de Conteúdo**: Sincronização automática entre sites WordPress
2. **Tradução Automática**: Publicação multilíngue com traduções em tempo real
3. **Integração com Sistemas Externos**: Recebimento de conteúdo de CMS externos
4. **Workflow de Publicação**: Automação de processos editoriais

## Manutenção e Suporte

O plugin inclui logging detalhado para facilitar a manutenção e debug. Em caso de problemas, verificar:
1. Logs do WordPress (wp-content/debug.log)
2. Configurações de API e tokens
3. Conectividade com API OpenAI
4. Permissões de usuário no WordPress

Para suporte adicional, consultar a documentação ou o desenvolvedor Alexandre Chaves.

## Fluxo de Trabalho do Plugin

O fluxo de trabalho do plugin Post Receptor é projetado para automatizar completamente o processo de recebimento, tradução e publicação de posts entre sites WordPress. Abaixo, detalhamos passo a passo como o plugin opera em um cenário típico:

### 1. Configuração Inicial
- **No Site Receptor**: Instalar e ativar o plugin Post Receptor
- Configurar a URL do emissor, idioma de destino e system prompt
- Gerar token de autenticação e configurar chave OpenAI API
- **No Site Emissor**: Configurar o token gerado no receptor

### 2. Publicação no Emissor
- Autor cria ou edita um post no site emissor
- Plugin emissor coleta todos os dados: título, conteúdo, metadados, mídias, etc.
- Emissor envia requisição POST para o endpoint `/wp-json/post-receptor/v1/receive` do receptor

### 3. Autenticação e Validação
- Receptor verifica o token Bearer no header Authorization
- Se inválido, retorna erro 403 Forbidden
- Se válido, processa os dados JSON recebidos

### 4. Análise de Idioma e Tradução
- Compara `origin_language` com `target_language` configurado
- Se iguais: pula tradução, usa conteúdo original
- Se diferentes: inicia processo de tradução via OpenAI GPT-4o-mini
  - Monta prompt contextual baseado no tipo de conteúdo (título, corpo, etc.)
  - Inclui system prompt configurado para manter estilo consistente
  - Faz até 3 tentativas em caso de falha na API
  - Preserva tags HTML no conteúdo durante tradução

### 5. Processamento de Autores
- Busca usuário existente pelo `author_login`
- Se não encontrado e `author_data` fornecido: cria novo usuário com role 'author'
- Se falhar: usa usuário admin ou atual como fallback

### 6. Processamento de Taxonomias
- **Categorias**: Para cada categoria recebida
  - Traduz nome se necessário
  - Gera slug limpo e único
  - Verifica se termo já existe
  - Cria nova categoria se não existir
- **Tags**: Processo similar ao de categorias, mas para taxonomia 'post_tag'

### 7. Processamento de Mídias
- **Imagem Destacada**:
  - Baixa imagem da URL fornecida
  - Cria attachment no WordPress
  - Traduz metadados (alt, title, caption, description)
  - Define como thumbnail do post
  - Armazena URL original para evitar re-downloads
- **Anexos**: Processo similar para outros arquivos anexados

### 8. Criação/Atualização do Post
- Verifica se post já existe via meta `emissor_post_id`
- Prepara array com todos os dados:
  - Título, conteúdo, excerpt traduzidos
  - Slug gerado
  - Autor ID
  - Status fornecido
- Se post existe: atualiza com `wp_update_post()`
- Se não existe: cria novo com `wp_insert_post()`
- Adiciona meta `emissor_post_id` para rastreamento

### 9. Configuração de Metadados
- Associa categorias e tags ao post
- Define metadados Yoast SEO (meta description, focus keyword traduzidos)
- Importa dados Elementor (sem tradução)
- Armazena dados de mídia processados

### 10. Resposta e Logging
- Retorna JSON com sucesso e ID do post
- Registra logs detalhados em `wp-content/debug.log` para cada etapa
- Em caso de erro: retorna código apropriado (400, 403, 500) com mensagem

### 11. Operações Adicionais
- **Atualização de Status**: Endpoint separado para mudar status do post (draft, publish, etc.)
- **Exclusão**: Remove post e imagem destacada permanentemente
- **Verificação de Token**: Endpoint para testar conectividade

### Cenário de Erro e Recuperação
- Se tradução falhar: usa conteúdo original
- Se mídia não baixar: continua sem imagem
- Se autor não criar: usa fallback
- Se post não salvar: retorna erro com detalhes
- Tentativas múltiplas para APIs externas com timeout de 30s

Este fluxo garante que o conteúdo seja replicado de forma confiável, traduzido automaticamente e integrado completamente no WordPress receptor, mantendo todas as funcionalidades e metadados originais.