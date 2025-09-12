# Guia de Deploy - Sistema Salas

Este documento descreve o processo para realizar um deploy inicial da aplicação "Salas" em um servidor de produção ou homologação utilizando a infraestrutura Docker definida neste repositório.

## Visão Geral da Arquitetura

A aplicação é implantada como um conjunto de containers orquestrados pelo Docker Compose. A arquitetura consiste em:
-   **app:** Um container com Nginx e PHP-FPM para servir a aplicação Laravel. A imagem é construída a partir do `Dockerfile` na raiz do projeto.
-   **mysql:** Um container para o banco de dados MySQL.
-   **redis:** Um container para cache e sessões (opcional).
-   **mailpit:** Um container para captura de e-mails em ambientes de não produção.

A configuração é dividida em dois arquivos principais:
-   **`docker-compose.yml`**: Define a arquitetura base dos serviços, otimizada para **desenvolvimento local com Laravel Sail**.
-   **`docker-compose.prod.yml`**: Um arquivo de *override* que adapta a arquitetura para **produção/homologação**, utilizando a imagem customizada do `Dockerfile`.

## Pré-requisitos do Servidor

1.  Acesso SSH com um usuário que pertença ao grupo `docker`.
2.  Git, Docker e Docker Compose instalados.
3.  O servidor deve permitir o encaminhamento de pacotes (`net.ipv4.ip_forward = 1`).
4.  As portas que serão expostas (definidas no `.env`) devem estar liberadas no firewall do host.

---

## Passo a Passo do Deploy Inicial

### 1. Preparação do Ambiente

Clone o repositório no servidor e navegue para o diretório do projeto.

```bash
cd /caminho/para/projetos
git clone https://github.com/ime-usp-br/salas.git
cd salas
```

### 2. Configuração do Ambiente

Crie o arquivo de configuração de ambiente (`.env`) a partir do exemplo. **Este arquivo não deve ser versionado.**

```bash
cp .env.example .env
nano .env
```

Ajuste as seguintes variáveis no arquivo `.env` para corresponder ao seu ambiente de destino:

-   **Configurações da Aplicação:**
    -   `APP_ENV`: Mude para `production` ou `staging`.
    -   `APP_DEBUG`: **Obrigatório** ser `false`.
    -   `APP_URL`: A URL pública da aplicação (ex: `http://salas.ime.usp.br`).
    -   `APP_KEY`: Deixe em branco. Será gerado posteriormente.

-   **Portas Externas:**
    -   `APP_PORT`: Porta pública para a aplicação (ex: `8016`).
    -   `FORWARD_DB_PORT`: Porta pública para o MySQL (ex: `3309`).
    -   `FORWARD_REDIS_PORT`, `FORWARD_MAILPIT_PORT`, etc.

-   **Banco de Dados:**
    -   `DB_DATABASE`, `DB_USERNAME`: Nomes para o banco e usuário de produção.
    -   `DB_PASSWORD`: **Defina uma senha forte e segura.**

-   **Credenciais de Serviços:**
    -   Preencha as variáveis `SENHAUNICA_*` e `REPLICADO_*` com as credenciais corretas para o ambiente.

-   **Cache e Sessão:**
    -   `CACHE_DRIVER` e `SESSION_DRIVER` devem corresponder aos serviços que você está usando (ex: `redis`, `file`).

### 3. Build e Inicialização dos Serviços

Com o `.env` configurado, use o `docker-compose.prod.yml` para construir e iniciar a stack de produção.

1.  **Crie os diretórios para volumes persistentes:**
    ```bash
    mkdir -p ./docker/mysql ./docker/redis
    ```

2.  **Construa a imagem e inicie os containers:**
    Este comando usa o `docker-compose.prod.yml` para sobrescrever a configuração de desenvolvimento, construindo a imagem a partir do nosso `Dockerfile` customizado.
    ```bash
    sudo docker compose -f docker-compose.prod.yml up -d --build
    ```

3.  **Verifique o Status:**
    Aguarde cerca de um minuto para a inicialização do banco de dados e verifique se todos os serviços estão rodando e saudáveis.
    ```bash
    sudo docker compose -f docker-compose.prod.yml ps
    ```
    Todos os containers devem ter o status `Up` e o `mysql` deve estar `(healthy)`.

### 4. Configuração Final da Aplicação

Execute os seguintes comandos `artisan` para finalizar a configuração da aplicação dentro do container.

1.  **Popular o Banco de Dados:**
    Este comando executa as migrações do banco de dados e os *seeders* para popular com dados iniciais.
    ```bash
    sudo docker compose -f docker-compose.prod.yml exec app php artisan migrate:fresh --seed --force
    ```

2.  **Criar um Usuário Administrador (Opcional):**
    Se necessário, crie um usuário administrador local para gerenciamento da API ou acesso inicial.
    ```bash
    sudo docker compose -f docker-compose.prod.yml exec app php artisan make:admin-local-user
    ```

### 5. Verificação

Sua aplicação agora está no ar e pronta para uso.

-   **URL da Aplicação:** Acesse a `APP_URL` que você configurou no `.env`.
-   **Logs da Aplicação:** Para monitorar os logs em tempo real, use:
    ```bash
    sudo docker compose -f docker-compose.prod.yml logs -f app
    ```

---

## Manutenção e Atualizações

### Atualizando a Aplicação

Para aplicar atualizações do repositório Git:

1.  **Puxe as últimas alterações:**
    ```bash
    git pull
    ```
2.  **Reconstrua a imagem e reinicie os containers:**
    ```bash
    sudo docker compose -f docker-compose.prod.yml up -d --build
    ```
3.  **Execute as migrações (se houver):**
    ```bash
    sudo docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
    ```
4.  **Limpe e recrie os caches:**
    ```bash
    sudo docker compose -f docker-compose.prod.yml exec app php artisan optimize:clear
    sudo docker compose -f docker-compose.prod.yml exec app php artisan config:cache
    sudo docker compose -f docker-compose.prod.yml exec app php artisan route:cache
    sudo docker compose -f docker-compose.prod.yml exec app php artisan view:cache
    ```

### Comandos Úteis

-   **Parar todos os serviços:**
    `sudo docker compose -f docker-compose.prod.yml down`
-   **Executar um comando Artisan:**
    `sudo docker compose -f docker-compose.prod.yml exec app php artisan <comando>`
-   **Acessar o shell do container da aplicação:**
    `sudo docker compose -f docker-compose.prod.yml exec app /bin/sh`
