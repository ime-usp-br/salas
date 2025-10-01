# *Endpoints* e *Responses* da API

## Índice

- [🔐 Autenticação](#-autenticação)
- [📋 Endpoints Públicos](#-endpoints-públicos-sem-autenticação)
- [📋 Endpoints Protegidos](#-endpoints-protegidos-com-autenticação)
  - [Consulta Otimizada de Reservas](#consulta-otimizada-de-reservas)
  - [Gestão de Reservas](#gestão-de-reservas)
  - [Status de Reservas](#status-de-reservas)
  - [👥 Gestão de Usuários](#-gestão-de-usuários)
  - [🔑 Gestão de Permissões Usuário-Categoria](#-gestão-de-permissões-usuário-categoria)
- [🔄 Reservas Recorrentes](#-reservas-recorrentes-ac6)
- [🔍 Códigos de Erro Detalhados](#-códigos-de-erro-detalhados)

---

## 🔐 Autenticação

A API utiliza **Laravel Sanctum** para autenticação baseada em tokens. Para acessar endpoints protegidos, você deve incluir o token de acesso no cabeçalho `Authorization`.

### Obtendo um Token de API

**POST** `/api/v1/auth/token`

Cria um token de API usando email e senha do usuário.

**Parâmetros:**
- `email` (obrigatório): Email do usuário
- `password` (obrigatório): Senha do usuário  
- `token_name` (opcional): Nome personalizado para o token (padrão: "API Token")

**Exemplo de Request:**
```json
POST /api/v1/auth/token
Content-Type: application/json

{
    "email": "usuario@usp.br",
    "password": "senha123",
    "token_name": "Token de Integração"
}
```

**Exemplo de Response (201 Created):**
```json
{
    "message": "Token criado com sucesso",
    "data": {
        "token": "1|A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6Q7R8S9T0",
        "token_name": "Token de Integração",
        "user": {
            "id": 123,
            "name": "João da Silva",
            "email": "usuario@usp.br"
        }
    }
}
```

**Errors:**
- `422`: Credenciais inválidas ou campos obrigatórios ausentes
- `429`: Rate limiting (máximo 5 tentativas por minuto)

### Usando o Token de API

Include o token no cabeçalho `Authorization` com o prefixo `Bearer`:

```http
GET /api/v1/auth/user
Authorization: Bearer 1|A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6Q7R8S9T0
```

### Endpoints de Autenticação

**GET** `/api/v1/auth/user` *(protegido)*
Retorna informações do usuário autenticado.

**Exemplo de Response:**
```json
{
    "data": {
        "id": 123,
        "name": "João da Silva",
        "email": "usuario@usp.br",
        "roles": ["admin"],
        "permissions": ["gerenciar_reservas", "aprovar_reservas"]
    }
}
```

**GET** `/api/v1/auth/tokens` *(protegido)*
Lista todos os tokens do usuário autenticado.

**Exemplo de Response:**
```json
{
    "data": [
        {
            "id": 1,
            "name": "Token de Integração",
            "last_used_at": "25/08/2024 14:30",
            "created_at": "20/08/2024 09:15"
        },
        {
            "id": 2,
            "name": "API Token",
            "last_used_at": null,
            "created_at": "22/08/2024 16:45"
        }
    ]
}
```

**DELETE** `/api/v1/auth/tokens/{id}` *(protegido)*
Revoga um token específico.

**DELETE** `/api/v1/auth/tokens` *(protegido)*
Revoga todos os tokens do usuário.

---

## 📋 Endpoints Públicos (sem autenticação)

### Salas

- `/api/v1/salas`: retorna todas as salas cadastradas no sistema com suas informações.

Exemplo de _response_:
```json
{
    "data": [{
        "id": 1,
        "nome": "Sala 01",
        "id_categoria": 1,
        "categoria": "Prédio Principal",
        "capacidade": 40,
        "recursos": []
    },
    // ...
    , {
        "id": 16,
        "nome": "Auditório",
        "id_categoria": 4,
        "categoria": "Bloco C",
        "capacidade": 100,
        "recursos": ["Projetor", "Lousa Interativa"]
    }]
}
```

---

- `/api/v1/salas/{sala_id}`: retorna as informações de uma sala.

Exemplo de *request*:
`https://salas.usp/api/v1/salas/16`

Exemplo de _response_:
```json
{
    "data": {
        "id": 16,
        "nome": "Auditório",
        "id_categoria": 4,
        "categoria": "Bloco C",
        "capacidade": 100,
        "recursos": ["Projetor", "Lousa Interativa"]
    }
}
```

---

- `/api/v1/categorias`: retorna todas as categorias cadastradas no sistema.

Exemplo de _response_:
```json
{
    "data": [{
        "id": 1,
        "nome": "Prédio Principal"
    }, {
        "id": 2,
        "nome": "Bloco A"
    }, {
        "id": 3,
        "nome": "Bloco B"
    }, {
        "id": 4,
        "nome": "Bloco C"
    }]
}
```

---

- `/api/v1/categorias/{categoria_id}`: retorna as informações de uma categoria.

Exemplo de *request*:
`https://salas.usp/api/v1/categorias/1`

Exemplo de _response_:
```json
{
    "data": {
        "id": 1,
        "nome": "Prédio Principal",
        "vinculo_cadastrado": "Pessoas da unidade",
        "setores_cadastrados": ["Assistência Técnica Administrativa", "Serviços Gerais"],
        "salas": [{
            "id": 1,
            "nome": "Sala 01",
            "id_categoria": 1,
            "categoria": "Prédio Principal",
            "capacidade": 40,
            "recursos": []
        }, {
            "id": 2,
            "nome": "Sala 02",
            "id_categoria": 1,
            "categoria": "Prédio Principal",
            "capacidade": 40,
            "recursos": []
        }, {
            "id": 3,
            "nome": "Sala 03",
            "id_categoria": 1,
            "categoria": "Prédio Principal",
            "capacidade": 40,
            "recursos": ["Lousa Interativa", "Projetor"]
        }]
    }
}
```

---

### Finalidades

- `/api/v1/finalidades`: retorna todas as finalidades cadastradas no sistema com suas cores.

Exemplo de _response_:
```json
{
    "data": [{
        "id": 1,
        "legenda": "Graduação",
        "cor": "#FFFCB7"
    }, {
        "id": 2,
        "legenda": "Pós-Graduação",
        "cor": "#C7F3BA"
    }, {
        "id": 3,
        "legenda": "Especialização",
        "cor": "#BAE3F3"
    }, {
        "id": 4,
        "legenda": "Extensão",
        "cor": "#EFDFCF"
    }, {
        "id": 5,
        "legenda": "Defesa",
        "cor": "#EEC9F1"
    }, {
        "id": 6,
        "legenda": "Qualificação",
        "cor": "#CCCC99"
    }, {
        "id": 7,
        "legenda": "Reunião",
        "cor": "#F78B83"
    }, {
        "id": 8,
        "legenda": "Evento",
        "cor": "#FBCCAC"
    }]
}
```

---

- `/api/v1/reservas`: retorna todas as reservas do dia corrente.

Exemplo de _response_:
```json
{
    "data": [{
        "id": 128,
        "nome": "Recepção da Pós-Graduação",
        "sala": "Sala 01",
        "sala_id": 1,
        "data": "15/08/2024",
        "horario_inicio": "9:00",
        "horario_fim": "12:00",
        "finalidade": "Pós-Graduação",
        "descricao": "Recepção dos novos alunos da pós-graduação.",
        "cadastrada_por": "João da Silva",
        "responsaveis": ["Silvério Rocha", "Armando de Souza"]
    }, {
        "id": 134,
        "nome": "Aula Magna",
        "sala": "Auditório",
        "sala_id": 16,
        "data": "15/08/2024",
        "horario_inicio": "15:00",
        "horario_fim": "16:40",
        "finalidade": "Graduação",
        "descricao": "Aula magna aberta para todos os ingressantes",
        "cadastrada_por": "Fulano de Andrade",
        "responsaveis": ["Fulano de Andrade"]
    }, {
        "id": 151,
        "nome": "Recepção da Graduação",
        "sala": "Sala 01",
        "sala_id": 1,
        "data": "15/08/2024",
        "horario_inicio": "19:20",
        "horario_fim": "21:00",
        "finalidade": "Graduação",
        "descricao": "Recepção dos novos alunos do período noturno da graduação.",
        "cadastrada_por": "Ciclano de Moura",
        "responsaveis": ["Ciclano de Moura"]
    }]
}
```

Para este *endpoint* três parâmetros estão disponíveis para filtrar as reservas, sendo estes: finalidade, sala e data.

Estes parâmetros podem ser passados e combinados via método GET, seguem alguns exemplos:

- `/api/v1/reservas?finalidade={finalidade_id}`: retorna todas as reservas do dia corrente com uma determinada finalidade.

Exemplo de *request*:
`https://salas.usp/api/v1/reservas?finalidade=1`

Exemplo de _response_:
```json
{
    "data": [{
        "id": 134,
        "nome": "Aula Magna",
        "sala": "Auditório",
        "sala_id": 16,
        "data": "15/08/2024",
        "horario_inicio": "15:00",
        "horario_fim": "16:40",
        "finalidade": "Graduação",
        "descricao": "Aula magna aberta para todos os ingressantes",
        "cadastrada_por": "Fulano de Andrade",
        "responsaveis": ["Fulano de Andrade"]
    }, {
        "id": 151,
        "nome": "Recepção da Graduação",
        "sala": "Sala 01",
        "sala_id": 1,
        "data": "15/08/2024",
        "horario_inicio": "19:20",
        "horario_fim": "21:00",
        "finalidade": "Graduação",
        "descricao": "Recepção dos novos alunos do período noturno da graduação.",
        "cadastrada_por": "Ciclano de Moura",
        "responsaveis": ["Ciclano de Moura"]
    }]
}
```

- `/api/v1/reservas?sala={sala_id}`: retorna todas as reservas do dia corrente em uma determinada sala.

Exemplo de *request*:
`https://salas.usp/api/v1/reservas?sala=1`

Exemplo de _response_:
```json
{
    "data": [{
        "id": 128,
        "nome": "Recepção da Pós-Graduação",
        "sala": "Sala 01",
        "sala_id": 1,
        "data": "15/08/2024",
        "horario_inicio": "9:00",
        "horario_fim": "12:00",
        "finalidade": "Pós-Graduação",
        "descricao": "Recepção dos novos alunos da pós-graduação.",
        "cadastrada_por": "João da Silva",
        "responsaveis": ["Silvério Rocha", "Armando de Souza"]
    }, {
        "id": 151,
        "nome": "Recepção da Graduação",
        "sala": "Sala 01",
        "sala_id": 1,
        "data": "15/08/2024",
        "horario_inicio": "19:20",
        "horario_fim": "21:00",
        "finalidade": "Graduação",
        "descricao": "Recepção dos novos alunos do período noturno da graduação.",
        "cadastrada_por": "Ciclano de Moura",
        "responsaveis": ["Ciclano de Moura"]
    }]
}
```

- `/api/v1/reservas?data={Y-m-d}`: retorna todas as reservas da data passada.

Exemplo de *request*:
`https://salas.usp/api/v1/reservas?data=2024-08-12`


Exemplo de _response_:
```json
{
    "data": [{
        "id": 114,
        "nome": "Reunião da Comissão de Recepção",
        "sala": "Sala 01",
        "sala_id": 1,
        "data": "12/08/2024",
        "horario_inicio": "15:00",
        "horario_fim": "16:30",
        "finalidade": "Graduação",
        "descricao": "Última reunião da comissão de recepção.",
        "cadastrada_por": "Ciclano de Moura",
        "responsaveis": ["Ciclano de Moura"]
    }, {
        "id": 97,
        "nome": "Simpósio de Inverno",
        "sala": "Auditório",
        "sala_id": 16,
        "data": "12/08/2024",
        "horario_inicio": "19:00",
        "horario_fim": "17:00",
        "finalidade": "Evento",
        "descricao": "Último dia do Simpósio de Inverno.",
        "cadastrada_por": "Fulano de Andrade",
        "responsaveis": ["Fulano de Andrade"]
    }]
}
```

- `/api/v1/reservas?data={Y-m-d}&finalidade={finalidade_id}`: retorna todas as reservas da data passada com a finalidade em questão.

Exemplo de *request*:
`https://salas.usp/api/v1/reservas?data=2024-08-12&finalidade=1`


Exemplo de _response_:
```json
{
    "data": [{
        "id": 114,
        "nome": "Reunião da Comissão de Recepção",
        "sala": "Sala 01",
        "sala_id": 1,
        "data": "12/08/2024",
        "horario_inicio": "15:00",
        "horario_fim": "16:30",
        "finalidade": "Graduação",
        "descricao": "Última reunião da comissão de recepção.",
        "cadastrada_por": "Ciclano de Moura",
        "responsaveis": ["Ciclano de Moura"]
    }]
}
```

---

## 🔍 **Códigos de Erro Detalhados**

### Códigos de Status HTTP

| Código | Significado | Quando Ocorre | Solução |
|--------|-------------|---------------|---------|
| **200** | OK | Operação bem-sucedida | - |
| **201** | Created | Recurso criado com sucesso | - |
| **401** | Unauthorized | Token inválido/ausente | Renovar token de autenticação |
| **403** | Forbidden | Sem permissão para a operação | Verificar permissões do usuário |
| **404** | Not Found | Recurso não encontrado | Confirmar ID do recurso |
| **422** | Validation Error | Dados de entrada inválidos | Corrigir dados conforme `errors` |
| **429** | Rate Limit | Muitas requisições | Aguardar tempo especificado em `Retry-After` |
| **500** | Server Error | Erro interno do servidor | Tentar novamente ou contatar suporte |

### Estrutura Padrão de Erro

Todos os erros seguem a estrutura abaixo:

```json
{
    "error": "Tipo do erro",
    "message": "Mensagem em português para o usuário",
    "details": {
        "type": "categoria_especifica_do_erro",
        "code": "codigo_identificador",
        "additional_info": "informações extras quando aplicável"
    }
}
```

### Erros Específicos da API de Reservas

#### **Erros de Validação (422)**

**Campos Obrigatórios:**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "nome": ["O campo nome é obrigatório."],
        "data": ["O campo data é obrigatório."],
        "sala_id": ["O campo sala id é obrigatório."]
    }
}
```

**Data Inválida:**
```json
{
    "error": "Validation failed",
    "message": "Formato de data inválido. Use Y-m-d.",
    "details": {
        "type": "invalid_date_format",
        "code": "validation_error"
    }
}
```

**Horário Inválido:**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "horario_fim": ["O horário de fim deve ser posterior ao horário de início."]
    }
}
```

#### **Erros de Autorização (403)**

**Acesso Negado:**
```json
{
    "error": "Forbidden",
    "message": "Você só pode cancelar suas próprias reservas.",
    "details": {
        "type": "insufficient_permissions",
        "code": "unauthorized_access",
        "user_id": 123,
        "reservation_owner": 456
    }
}
```

#### **Erros de Negócio (422)**

**Data Passada:**
```json
{
    "error": "Validation failed",
    "message": "Não é possível cancelar reservas de datas passadas.",
    "details": {
        "type": "business_rule_violation",
        "code": "past_date_restriction"
    }
}
```

**Conflito de Horários:**
```json
{
    "error": "Conflict",
    "message": "Conflito detectado com a reserva 'Reunião Diretoria' (14:00 às 16:00).",
    "details": {
        "type": "time_conflict",
        "code": "schedule_conflict",
        "conflicting_reservation": {
            "id": 789,
            "nome": "Reunião Diretoria",
            "horario_inicio": "14:00",
            "horario_fim": "16:00"
        }
    }
}
```

#### **Erros de Sistema (500)**

**Erro de Banco de Dados:**
```json
{
    "error": "Database error",
    "message": "Erro na base de dados. Verifique os dados e tente novamente.",
    "details": {
        "type": "database_error",
        "code": "query_exception"
    }
}
```

**Erro de Restrição Foreign Key:**
```json
{
    "error": "Validation failed",
    "message": "Referência inválida detectada (sala ou finalidade inexistente).",
    "details": {
        "type": "constraint_violation",
        "code": "foreign_key_constraint"
    }
}
```

### Rate Limiting

A API implementa rate limiting com diferentes limites por tipo de endpoint:

| Endpoint Type | Limite | Período | Cabeçalho de Resposta |
|---------------|--------|---------|----------------------|
| **Public** | 60 requests | 1 minuto | `X-RateLimit-Limit: 60` |
| **Auth** | 5 requests | 1 minuto | `X-RateLimit-Limit: 5` |
| **API** | 100 requests | 1 minuto | `X-RateLimit-Limit: 100` |
| **Reservations** | 30 requests | 1 minuto | `X-RateLimit-Limit: 30` |
| **Admin** | 20 requests | 1 minuto | `X-RateLimit-Limit: 20` |

**Exemplo de Rate Limit Excedido (429):**
```json
{
    "message": "Too Many Attempts.",
    "retry_after": 57
}
```

**Headers de Resposta:**
```http
X-RateLimit-Limit: 30
X-RateLimit-Remaining: 0
Retry-After: 57
```

---

## 📋 Endpoints Protegidos (com autenticação)

### Consulta Otimizada de Reservas

**GET** `/api/v1/reservas/by-room-and-date` *(protegido)*

Retorna reservas aprovadas e pendentes para uma sala específica em uma data específica. Endpoint otimizado para verificação de conflitos durante importação em massa, resolvendo problemas de rate limiting da API pública.

**Parâmetros de Query:**
- `sala_id` (integer, obrigatório): ID da sala a ser consultada
- `data` (string, obrigatório): Data da consulta no formato Y-m-d (ex: 2024-09-20)

**Autorização:**
- Requer token de autenticação válido
- Aplica throttle do grupo `reservations` (limites mais altos para operações em massa)

**Filtros Aplicados:**
- Apenas reservas com status `aprovada` OU `pendente`
- Ordenadas por `horario_inicio`
- Retorna apenas campos essenciais para otimização

**Exemplo de Request:**
```http
GET /api/v1/reservas/by-room-and-date?sala_id=15&data=2024-09-20
Authorization: Bearer 1|A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6Q7R8S9T0
Accept: application/json
```

**Exemplo de Response (200 OK) - Com reservas encontradas:**
```json
{
    "data": [
        {
            "id": 101,
            "nome": "Reunião de Departamento",
            "horario_inicio": "15:00",
            "horario_fim": "17:00"
        },
        {
            "id": 102,
            "nome": "Aula de MAC0110",
            "horario_inicio": "10:00",
            "horario_fim": "12:00"
        }
    ]
}
```

**Exemplo de Response (200 OK) - Sem reservas na data:**
```json
{
    "data": []
}
```

**Formato de Horário:**
- Campos `horario_inicio` e `horario_fim` formatados como `G:i` (ex: '9:00', '14:30')
- Remove zeros desnecessários (9:00 ao invés de 09:00)

**Casos de Uso:**
- Verificação de conflitos em importação em massa de reservas
- Validação de disponibilidade antes de criar reservas
- Integração sistema-a-sistema com alto volume de consultas

**Errors:**
- `401`: Token de autenticação inválido ou expirado
- `422`: Parâmetros inválidos (sala_id ou data ausentes/incorretos)
- `429`: Rate limiting excedido (respeita limites do grupo `reservations`)
- `500`: Erro interno do servidor

**Rate Limiting:**
Este endpoint utiliza o throttle `reservations`, que oferece limites mais generosos para usuários com roles de sistema (ex: `bulk-importer`, `admin`) conforme configurado em `API_RATE_LIMIT_RESERVATIONS_BULK_ROLES`.

---

### Gestão de Reservas

**PATCH** `/api/v1/reservas/{id}/approve` *(protegido)*

Aprova uma reserva pendente. Apenas responsáveis pela sala ou administradores podem aprovar reservas.

**Parâmetros:**
- `{id}` (obrigatório): ID da reserva a ser aprovada

**Autorização:**
- Usuário deve ser responsável pela sala da reserva OU
- Usuário deve ter privilégios de administrador

**Exemplo de Request:**
```http
PATCH /api/v1/reservas/123/approve
Authorization: Bearer 1|A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6Q7R8S9T0
```

**Exemplo de Response (200 OK):**
```json
{
    "message": "Reserva aprovada com sucesso.",
    "data": {
        "id": 123,
        "nome": "Reunião da Diretoria",
        "status": "aprovada",
        "approved_by": "João Responsável",
        "approved_at": "26/08/2024 14:30:45"
    }
}
```

**Errors:**
- `401`: Token de autenticação inválido
- `403`: Usuário não é responsável pela sala
- `404`: Reserva não encontrada
- `422`: Reserva não está em status pendente

---

**PATCH** `/api/v1/reservas/{id}/reject` *(protegido)*

Rejeita uma reserva pendente. Apenas responsáveis pela sala ou administradores podem rejeitar reservas.

**Parâmetros:**
- `{id}` (obrigatório): ID da reserva a ser rejeitada

**Autorização:**
- Usuário deve ser responsável pela sala da reserva OU
- Usuário deve ter privilégios de administrador

**Exemplo de Request:**
```http
PATCH /api/v1/reservas/123/reject
Authorization: Bearer 1|A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6Q7R8S9T0
```

**Exemplo de Response (200 OK):**
```json
{
    "message": "Reserva rejeitada com sucesso.",
    "data": {
        "id": 123,
        "nome": "Reunião da Diretoria",
        "status": "rejeitada",
        "rejected_by": "João Responsável",
        "rejected_at": "26/08/2024 14:30:45"
    }
}
```

**Errors:**
- `401`: Token de autenticação inválido
- `403`: Usuário não é responsável pela sala
- `404`: Reserva não encontrada
- `422`: Reserva não está em status pendente

---

### Status de Reservas

As reservas no sistema podem ter os seguintes status:

- **`aprovada`**: Reserva confirmada e pode ser utilizada
- **`pendente`**: Reserva aguardando aprovação dos responsáveis da sala
- **`rejeitada`**: Reserva negada pelos responsáveis da sala

**Importante:** Apenas reservas com status `pendente` podem ser aprovadas ou rejeitadas através dos endpoints acima.

---

### 👥 Gestão de Usuários

#### Buscar Usuário por Número USP

**GET** `/api/v1/users?codpes={codpes}` *(protegido)*

Busca um usuário pelo número USP (codpes). Útil para verificar se um usuário já existe no sistema antes de criá-lo.

**Parâmetros de Query:**
- `codpes` (integer, obrigatório): Número USP do usuário

**Autorização:**
- Requer token de autenticação válido
- Aplica throttle do grupo `api` (100 req/min)

**Exemplo de Request:**
```http
GET /api/v1/users?codpes=2803642
Authorization: Bearer 1|A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6Q7R8S9T0
```

**Exemplo de Response (200 OK) - Usuário encontrado:**
```json
{
    "data": {
        "id": 123,
        "codpes": 2803642,
        "name": "João da Silva",
        "email": "joao.silva@usp.br",
        "categorias": [
            {
                "id": 1,
                "nome": "Padrão",
                "vinculo_cadastrado": "Pessoas da unidade",
                "setores_cadastrados": ["Assistência Técnica"],
                "salas": []
            }
        ],
        "created_at": "2025-10-01T10:30:00.000000Z",
        "updated_at": "2025-10-01T10:30:00.000000Z"
    }
}
```

**Exemplo de Response (404 Not Found) - Usuário não existe:**
```json
{
    "error": "Not Found",
    "message": "Usuário não encontrado.",
    "details": {
        "type": "resource_not_found",
        "code": "not_found"
    }
}
```

**Casos de Uso:**
1. **Migração de Dados**: Verificar se usuário já existe antes de importar
2. **Integração de Sistemas**: Buscar ID do usuário a partir do número USP
3. **Validação**: Confirmar existência de usuário antes de associar permissões

**Workflow Recomendado (Migração):**
```
1. GET /api/v1/users?codpes={codpes}
   - Se 200 OK → usuário existe, usar o ID retornado
   - Se 404 → usuário não existe, criar com POST /users
2. PUT /api/v1/users/{id}/categorias
   - Sincronizar categorias/permissões
```

**Errors:**
- `401`: Token de autenticação inválido
- `404`: Usuário não encontrado
- `422`: Parâmetro codpes ausente ou não numérico
- `500`: Erro interno do servidor

---

#### Criação de Usuários

**POST** `/api/v1/users` *(protegido)*

Cria um novo usuário no sistema. Útil para integração com sistemas externos e migração de dados.

**Parâmetros:**
- `codpes` (integer, obrigatório): Número USP do usuário
- `name` (string, obrigatório): Nome completo do usuário (máx. 255 caracteres)
- `email` (string, obrigatório): E-mail válido e único
- `password` (string, opcional): Senha do usuário (mín. 8 caracteres). Se omitida, uma senha aleatória será gerada.

**Autorização:**
- Requer token de autenticação válido
- Aplica throttle do grupo `api` (100 req/min)

**Exemplo de Request:**
```http
POST /api/v1/users
Authorization: Bearer 1|A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6Q7R8S9T0
Content-Type: application/json

{
    "codpes": 1234567,
    "name": "João da Silva",
    "email": "joao.silva@usp.br",
    "password": "senha_segura_123"
}
```

**Exemplo de Response (201 Created):**
```json
{
    "data": {
        "id": 123,
        "codpes": 1234567,
        "name": "João da Silva",
        "email": "joao.silva@usp.br",
        "categorias": [],
        "created_at": "2025-10-01T10:30:00.000000Z",
        "updated_at": "2025-10-01T10:30:00.000000Z"
    },
    "message": "Usuário criado com sucesso."
}
```

**Errors:**
- `401`: Token de autenticação inválido
- `422`: Validação falhou (codpes ou email duplicado, campos obrigatórios ausentes)
- `500`: Erro interno do servidor

---

#### Consulta de Usuário

**GET** `/api/v1/users/{user}` *(protegido)*

Retorna informações detalhadas de um usuário, incluindo suas categorias associadas.

**Parâmetros:**
- `{user}` (obrigatório): ID do usuário

**Autorização:**
- Requer token de autenticação válido

**Exemplo de Request:**
```http
GET /api/v1/users/123
Authorization: Bearer 1|TOKEN
```

**Exemplo de Response (200 OK):**
```json
{
    "data": {
        "id": 123,
        "codpes": 1234567,
        "name": "João da Silva",
        "email": "joao.silva@usp.br",
        "categorias": [
            {
                "id": 1,
                "nome": "Padrão",
                "vinculo_cadastrado": "Pessoas da unidade",
                "setores_cadastrados": ["Assistência Técnica"],
                "salas": []
            },
            {
                "id": 2,
                "nome": "Auditório",
                "vinculo_cadastrado": "Pessoas da USP",
                "setores_cadastrados": [],
                "salas": []
            }
        ],
        "created_at": "2025-10-01T10:30:00.000000Z",
        "updated_at": "2025-10-01T10:30:00.000000Z"
    }
}
```

**Errors:**
- `401`: Token de autenticação inválido
- `404`: Usuário não encontrado
- `500`: Erro interno do servidor

---

### 🔑 Gestão de Permissões Usuário-Categoria

#### Listar Categorias do Usuário

**GET** `/api/v1/users/{user}/categorias` *(protegido)*

Retorna todas as categorias associadas a um usuário específico.

**Parâmetros:**
- `{user}` (obrigatório): ID do usuário

**Autorização:**
- Requer token de autenticação válido

**Exemplo de Request:**
```http
GET /api/v1/users/123/categorias
Authorization: Bearer 1|TOKEN
```

**Exemplo de Response (200 OK):**
```json
{
    "data": {
        "data": [
            {
                "id": 1,
                "nome": "Padrão",
                "vinculo_cadastrado": "Pessoas da unidade",
                "setores_cadastrados": ["Assistência Técnica"],
                "salas": []
            },
            {
                "id": 2,
                "nome": "Auditório",
                "vinculo_cadastrado": "Pessoas da USP",
                "setores_cadastrados": [],
                "salas": []
            }
        ]
    }
}
```

**Errors:**
- `401`: Token de autenticação inválido
- `404`: Usuário não encontrado
- `500`: Erro interno do servidor

---

#### Associar Categoria ao Usuário

**POST** `/api/v1/users/{user}/categorias` *(protegido)*

Associa uma categoria a um usuário. Esta operação é **idempotente**: se a associação já existe, retorna status 200 ao invés de 201.

**Parâmetros:**
- `{user}` (obrigatório): ID do usuário
- `categoria_id` (integer, obrigatório): ID da categoria a ser associada

**Autorização:**
- Requer token de autenticação válido

**Exemplo de Request:**
```http
POST /api/v1/users/123/categorias
Authorization: Bearer 1|TOKEN
Content-Type: application/json

{
    "categoria_id": 1
}
```

**Exemplo de Response (201 Created) - Nova associação:**
```json
{
    "data": {
        "user_id": 123,
        "categoria_id": 1,
        "categoria_nome": "Padrão",
        "created_at": "2025-10-01T10:30:00.000000Z"
    },
    "message": "Categoria associada com sucesso."
}
```

**Exemplo de Response (200 OK) - Associação já existia (idempotência):**
```json
{
    "data": {
        "user_id": 123,
        "categoria_id": 1,
        "categoria_nome": "Padrão",
        "created_at": "2025-09-15T08:20:00.000000Z"
    },
    "message": "Categoria já estava associada ao usuário."
}
```

**Errors:**
- `401`: Token de autenticação inválido
- `404`: Usuário não encontrado
- `422`: Categoria não existe ou categoria_id ausente
- `500`: Erro interno do servidor

---

#### Remover Categoria do Usuário

**DELETE** `/api/v1/users/{user}/categorias/{categoria}` *(protegido)*

Remove a associação entre um usuário e uma categoria.

**Parâmetros:**
- `{user}` (obrigatório): ID do usuário
- `{categoria}` (obrigatório): ID da categoria

**Autorização:**
- Requer token de autenticação válido

**Exemplo de Request:**
```http
DELETE /api/v1/users/123/categorias/1
Authorization: Bearer 1|TOKEN
```

**Exemplo de Response (200 OK):**
```json
{
    "message": "Categoria removida com sucesso."
}
```

**Errors:**
- `401`: Token de autenticação inválido
- `404`: Usuário, categoria ou associação não encontrada
- `500`: Erro interno do servidor

---

#### Sincronizar Categorias do Usuário (Operação Bulk)

**PUT** `/api/v1/users/{user}/categorias` *(protegido)*

Sincroniza as categorias do usuário de forma atômica. Remove associações não listadas, adiciona novas e mantém existentes.

**Parâmetros:**
- `{user}` (obrigatório): ID do usuário
- `categoria_ids` (array, obrigatório): Array de IDs de categorias (mínimo 1 item)

**Autorização:**
- Requer token de autenticação válido

**Comportamento:**
- Remove todas as associações que **não** estão no array
- Adiciona novas associações para IDs que não existiam
- Mantém associações que já existem
- Operação é executada dentro de uma transação (tudo ou nada)

**Exemplo de Request:**
```http
PUT /api/v1/users/123/categorias
Authorization: Bearer 1|TOKEN
Content-Type: application/json

{
    "categoria_ids": [1, 3]
}
```

**Exemplo de Response (200 OK):**
```json
{
    "data": {
        "attached": [3],
        "detached": {
            "1": 2
        },
        "updated": []
    },
    "message": "Categorias sincronizadas com sucesso.",
    "meta": {
        "success": true
    }
}
```

**Descrição dos Campos de Response:**
- `attached`: Array de IDs de categorias que foram adicionadas
- `detached`: Object/Array com IDs de categorias que foram removidas
- `updated`: Array de IDs de categorias que foram atualizadas (geralmente vazio)

**Casos de Uso:**
1. **Migração de Permissões**: Sincronizar permissões de sistemas legados (ex: Urano → Salas)
2. **Gestão de Perfis**: Aplicar conjunto de permissões baseado em role (ADM, OPR, USR)
3. **Atualização em Massa**: Atualizar permissões de múltiplos usuários programaticamente

**Exemplo - Aplicar permissões de Administrador (todas as categorias):**
```json
{
    "categoria_ids": [1, 2, 3]
}
```

**Exemplo - Aplicar permissões de Usuário Padrão (apenas categoria básica):**
```json
{
    "categoria_ids": [3]
}
```

**Errors:**
- `401`: Token de autenticação inválido
- `404`: Usuário não encontrado
- `422`: categoria_ids ausente, vazio ou contém IDs inválidos
- `500`: Erro interno do servidor ou falha na transação

**Rate Limiting:**
Este endpoint utiliza o throttle `api`, oferecendo 100 requisições por minuto para operações autenticadas.

---

## 🔄 Reservas Recorrentes (AC6)

### Criação de Reservas Recorrentes

**POST** `/api/v1/reservas` *(protegido)*

Cria uma nova reserva ou série de reservas recorrentes no sistema.

#### 📋 **Parâmetros para Reservas Simples**

**Parâmetros Obrigatórios:**
- `nome` (string): Título da reserva
- `data` (string): Data inicial no formato Y-m-d (ex: 2024-09-15)
- `horario_inicio` (string): Horário de início no formato H:i (ex: 14:00)
- `horario_fim` (string): Horário de fim no formato H:i (ex: 16:00)
- `sala_id` (integer): ID da sala a ser reservada
- `finalidade_id` (integer): ID da finalidade da reserva
- `tipo_responsaveis` (string): Tipo de responsáveis (eu, unidade, externo)

**Parâmetros Opcionais:**
- `descricao` (string): Descrição adicional da reserva
- `responsaveis_unidade` (array): IDs dos responsáveis da unidade (obrigatório quando tipo_responsaveis = "unidade")
- `responsaveis_externo` (array): Nomes dos responsáveis externos (obrigatório quando tipo_responsaveis = "externo")

#### 🔄 **Parâmetros para Reservas Recorrentes**

**⚠️ Importante**: Para reservas recorrentes, os campos `horario_inicio` e `horario_fim` são **opcionais** e **serão ignorados** quando `day_times` for fornecido.

**Parâmetros de Recorrência Obrigatórios:**
- `repeat_days` (array): Dias da semana para repetição (0=domingo, 1=segunda, ..., 6=sábado)
- `repeat_until` (string): Data final da recorrência no formato Y-m-d (obrigatório com repeat_days)
- `day_times` (object): **Obrigatório** para reservas recorrentes - Define horários específicos para cada dia

#### ⏰ **Estrutura do Campo `day_times`**

O campo `day_times` deve ser um objeto JSON onde:
- **Chaves**: Números representando dias da semana (0=domingo, 1=segunda, ..., 6=sábado)
- **Valores**: Objetos contendo `start` (horário início) e `end` (horário fim) no formato H:i

**Exemplo de `day_times`:**
```json
{
    "1": { "start": "08:00", "end": "10:00" },  // Segunda-feira
    "3": { "start": "14:00", "end": "16:00" }   // Quarta-feira
}
```

**⚠️ Regras importantes:**
- Todos os dias especificados em `repeat_days` devem ter entrada correspondente em `day_times`
- Horários devem estar no formato H:i (ex: "08:00", "14:30")
- Horário de fim deve ser posterior ao horário de início

**Validações de Recorrência:**
- **Período Máximo**: 6 meses entre data inicial e repeat_until
- **Máximo de Instâncias**: Máximo 100 reservas na série recorrente
- **Dias Válidos**: repeat_days deve conter entre 1 e 7 dias da semana únicos
- **Data Final**: repeat_until deve ser igual ou posterior à data inicial

**Exemplo - Reserva Única:**
```json
POST /api/v1/reservas
Authorization: Bearer 1|TOKEN
Content-Type: application/json

{
    "nome": "Reunião de Planejamento",
    "descricao": "Reunião mensal da equipe",
    "data": "2024-09-15",
    "horario_inicio": "14:00",
    "horario_fim": "16:00",
    "sala_id": 1,
    "finalidade_id": 7,
    "tipo_responsaveis": "eu"
}
```

**Exemplo - Reserva Recorrente com Horários Distintos:**
```json
POST /api/v1/reservas
Authorization: Bearer 1|TOKEN
Content-Type: application/json

{
    "nome": "Curso de Extensão",
    "descricao": "Curso com horários diferentes por dia",
    "data": "2024-09-15",
    "sala_id": 1,
    "finalidade_id": 4,
    "tipo_responsaveis": "unidade",
    "responsaveis_unidade": [123456, 789012],
    "repeat_days": [1, 3],
    "repeat_until": "2024-12-15",
    "day_times": {
        "1": { "start": "08:00", "end": "10:00" },
        "3": { "start": "14:00", "end": "16:00" }
    }
}
```

**Exemplo - Reserva Recorrente com Horário Uniforme:**
```json
POST /api/v1/reservas
Authorization: Bearer 1|TOKEN
Content-Type: application/json

{
    "nome": "Grupo de Estudo",
    "descricao": "Encontro semanal com mesmo horário",
    "data": "2024-09-16",
    "sala_id": 1,
    "finalidade_id": 1,
    "tipo_responsaveis": "eu",
    "repeat_days": [2, 4],
    "repeat_until": "2024-11-27",
    "day_times": {
        "2": { "start": "09:00", "end": "11:00" },
        "4": { "start": "09:00", "end": "11:00" }
    }
}
```

**Response - Reserva Única (201 Created):**
```json
{
    "data": {
        "id": 156,
        "nome": "Reunião de Planejamento",
        "descricao": "Reunião mensal da equipe",
        "sala": {
            "id": 1,
            "nome": "Sala 01"
        },
        "finalidade": {
            "id": 7,
            "nome": "Reunião"
        },
        "data": "15/09/2024",
        "horario_inicio": "14:00",
        "horario_fim": "16:00",
        "status": "aprovada",
        "user_id": 123,
        "created_at": "2024-08-26 10:30:45",
        "recurrent": false,
        "instances_created": 1
    },
    "meta": {
        "total_reservations": 1,
        "recurring_series": false,
        "success": true
    }
}
```

**Response - Reserva Recorrente com day_times (201 Created):**
```json
{
    "data": {
        "id": 157,
        "nome": "Curso de Extensão",
        "descricao": "Curso com horários diferentes por dia",
        "sala": {
            "id": 1,
            "nome": "Sala 01"
        },
        "finalidade": {
            "id": 4,
            "nome": "Extensão"
        },
        "data": "15/09/2024",
        "horario_inicio": "08:00",
        "horario_fim": "10:00",
        "status": "aprovada",
        "user_id": 123,
        "created_at": "2024-08-26 10:30:45",
        "recurrent": true,
        "instances_created": 25,
        "parent_id": 157,
        "recurring_details": {
            "repeat_days": [1, 3],
            "repeat_until": "2024-12-15",
            "day_times_used": true,
            "day_times": {
                "1": { "start": "08:00", "end": "10:00" },
                "3": { "start": "14:00", "end": "16:00" }
            },
            "first_date": "15/09/2024",
            "last_date": "13/12/2024"
        }
    },
    "meta": {
        "total_reservations": 25,
        "recurring_series": true,
        "success": true,
        "date_range": {
            "from": "15/09/2024",
            "to": "13/12/2024"
        }
    }
}
```

### Exclusão de Reservas Recorrentes

**DELETE** `/api/v1/reservas/{id}?purge={bool}&purge_from_date={date}` *(protegido)*

Remove uma reserva ou série de reservas recorrentes do sistema.

**Parâmetros de Query:**
- `purge` (boolean, opcional): Se true, remove todas as reservas da série recorrente
- `purge_from_date` (string, opcional): Data a partir da qual aplicar o purge (formato Y-m-d)

**Autorização:**
- Usuário deve ser o criador da reserva OU
- Usuário deve ter privilégios de administrador

**Comportamentos:**
1. **Sem purge**: Remove apenas a reserva específica
2. **purge=true**: Remove toda a série de reservas recorrentes
3. **purge=true&purge_from_date=YYYY-MM-DD**: Remove reservas da série a partir da data especificada

**Exemplo - Exclusão de Instância Única:**
```http
DELETE /api/v1/reservas/157
Authorization: Bearer 1|TOKEN
```

**Exemplo - Exclusão de Série Completa:**
```http
DELETE /api/v1/reservas/157?purge=true
Authorization: Bearer 1|TOKEN
```

**Exemplo - Exclusão Parcial da Série:**
```http
DELETE /api/v1/reservas/157?purge=true&purge_from_date=2024-10-15
Authorization: Bearer 1|TOKEN
```

**Response - Exclusão Única (200 OK):**
```json
{
    "message": "Reserva(s) cancelada(s) com sucesso.",
    "data": {
        "deleted_count": 1,
        "deleted_reservas": [
            {
                "id": 157,
                "nome": "Reunião Semanal da Equipe",
                "data": "15/09/2024",
                "status": "aprovada"
            }
        ],
        "operation_type": "single_deletion"
    },
    "meta": {
        "purge_applied": false,
        "partial_purge": false,
        "success": true
    }
}
```

**Response - Exclusão de Série (200 OK):**
```json
{
    "message": "Reserva(s) cancelada(s) com sucesso.",
    "data": {
        "deleted_count": 25,
        "deleted_reservas": [
            {
                "id": 157,
                "nome": "Reunião Semanal da Equipe",
                "data": "15/09/2024",
                "status": "aprovada"
            },
            {
                "id": 158,
                "nome": "Reunião Semanal da Equipe", 
                "data": "17/09/2024",
                "status": "pendente"
            }
        ],
        "operation_type": "series_deletion"
    },
    "meta": {
        "purge_applied": true,
        "partial_purge": false,
        "success": true
    }
}
```

**Response - Exclusão Parcial (200 OK):**
```json
{
    "message": "Reserva(s) cancelada(s) com sucesso.",
    "data": {
        "deleted_count": 12,
        "deleted_reservas": [
            {
                "id": 170,
                "nome": "Reunião Semanal da Equipe",
                "data": "16/10/2024",
                "status": "pendente"
            }
        ],
        "operation_type": "series_deletion"
    },
    "meta": {
        "purge_applied": true,
        "partial_purge": true,
        "success": true,
        "purge_from_date": "2024-10-15"
    }
}
```

**Errors:**
- `401`: Token de autenticação inválido
- `403`: Usuário não pode deletar esta reserva
- `404`: Reserva não encontrada
- `422`: Data de início do purge inválida (quando usando purge_from_date)
- `422`: Não é possível cancelar reservas de datas passadas (usuários não-admin)

### Validações de Negócio para Recorrências

**Regras de Validação:**
1. **Período Máximo**: 6 meses entre data inicial e data final
2. **Limite de Instâncias**: Máximo 100 reservas por série recorrente
3. **Dias da Semana**: Entre 1 e 7 dias únicos (0=domingo, 6=sábado)
4. **Data Final**: repeat_until deve ser igual ou posterior à data inicial
5. **Conflitos**: Validação de conflitos para cada instância da série

**Exemplos de Erros de Validação:**

**Período Excessivo (422):**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "repeat_until": ["O período de recorrência não pode exceder 6 meses."]
    }
}
```

**Muitas Instâncias (422):**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "repeat_until": ["O padrão de recorrência resultaria em mais de 100 reservas. Reduza o período ou os dias da semana."]
    }
}
```

**Dias Inválidos (422):**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "repeat_days": ["Não é possível repetir em mais de 7 dias por semana."]
    }
}
```

**day_times Obrigatório para Recorrência (422):**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "day_times": ["O campo day_times é obrigatório para reservas recorrentes. Defina os horários de cada dia da semana."]
    }
}
```

**day_times com Horário Inválido (422):**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "day_times": ["Horário não definido para Segunda (dia 1) em day_times."]
    }
}
```

### Consulta de Reservas Individuais

**GET** `/api/v1/reservas/{id}` *(protegido)*

Retorna os detalhes de uma reserva específica. Para reservas criadas com `day_times`, os campos `horario_inicio` e `horario_fim` sempre refletem os horários específicos daquela instância individual.

**Exemplo de Request:**
```http
GET /api/v1/reservas/157
Authorization: Bearer 1|TOKEN
```

**Response (200 OK):**
```json
{
    "data": {
        "id": 157,
        "nome": "Curso de Extensão",
        "descricao": "Curso com horários diferentes por dia",
        "sala": {
            "id": 1,
            "nome": "Sala 01"
        },
        "finalidade": {
            "id": 4,
            "legenda": "Extensão"
        },
        "data": "2024-09-15",
        "horario_inicio": "08:00",
        "horario_fim": "10:00",
        "status": "aprovada",
        "tipo_responsaveis": "unidade",
        "responsaveis": [
            {
                "id": 1,
                "nome": "João da Silva",
                "codpes": 123456
            }
        ],
        "user": {
            "id": 123,
            "name": "Maria Santos",
            "email": "maria@usp.br"
        },
        "recorrente": {
            "is_recurrent": true,
            "parent_id": 157,
            "repeat_days": [1, 3],
            "repeat_until": "2024-12-15"
        },
        "timestamps": {
            "created_at": "2024-08-26T10:30:45.000000Z",
            "updated_at": "2024-08-26T10:30:45.000000Z"
        }
    },
    "meta": {
        "success": true
    }
}
```

**Errors:**
- `401`: Token de autenticação inválido
- `404`: Reserva não encontrada
- `500`: Erro interno do servidor

### 🔒 Política de Imutabilidade da API

**⚠️ IMPORTANTE**: A partir desta implementação, a API de reservas adota uma **política de imutabilidade**. Reservas **não podem ser editadas** via API.

#### Endpoints Desabilitados

**PUT** `/api/v1/reservas/{id}` - ❌ **DESABILITADO**
**PATCH** `/api/v1/reservas/{id}` - ❌ **DESABILITADO**

#### Fluxo de Trabalho para Alterações

Para alterar uma reserva existente, use o fluxo:

1. **DELETE** `/api/v1/reservas/{id}` - Excluir a reserva existente
2. **POST** `/api/v1/reservas` - Criar uma nova reserva com os dados corretos

#### Response para Tentativas de Edição

Tentativas de usar PUT ou PATCH retornarão erro `405 Method Not Allowed`:

```json
{
    "error": "Method Not Allowed",
    "message": "As reservas são imutáveis via API. Para alterar uma reserva, exclua-a e crie uma nova.",
    "details": {
        "type": "immutability_policy",
        "code": "update_not_allowed",
        "method": "PUT",
        "suggested_workflow": [
            "1. DELETE /api/v1/reservas/{id} - Excluir a reserva existente",
            "2. POST /api/v1/reservas - Criar uma nova reserva com os dados corretos"
        ],
        "documentation": "https://docs.example.com/api/reservas#immutability-policy"
    }
}
```

#### Justificativa da Política

- **Consistência de Dados**: Evita estados inconsistentes em séries recorrentes
- **Auditoria**: Mantém histórico completo de alterações
- **Simplicidade**: Reduz complexidade de validação e conflitos
- **Confiabilidade**: Elimina cenários de corrupção de dados

---
# Tratamento de Erros Padronizados e Rate Limiting - Implementação

## Resumo das Melhorias Implementadas

Esta implementação aprimora a API do sistema de salas com **tratamento de erros padronizado** e **rate limiting granular**, mantendo **100% de compatibilidade** com clientes existentes.

## 1. Sistema de Resposta Padronizada

### ApiResponseTrait (`app/Http/Traits/ApiResponseTrait.php`)

Trait que fornece métodos padronizados para respostas da API:

#### Métodos de Sucesso
- `successResponse()` - Respostas de sucesso genéricas
- `createdResponse()` - Para recursos criados (201)
- `updatedResponse()` - Para recursos atualizados

#### Métodos de Erro
- `errorResponse()` - Erro genérico padronizado
- `validationErrorResponse()` - Erros de validação (422)
- `authenticationErrorResponse()` - Não autenticado (401)
- `forbiddenErrorResponse()` - Não autorizado (403)
- `notFoundErrorResponse()` - Não encontrado (404)
- `rateLimitErrorResponse()` - Rate limit excedido (429)
- `databaseErrorResponse()` - Erros de banco (500)
- `internalServerErrorResponse()` - Erro interno (500)

### Formato de Resposta
```json
{
  "data": { ... },           // Para sucessos
  "message": "string",       // Mensagem opcional
  "meta": {                  // Metadados
    "success": true
  },
  "error": "string",         // Tipo de erro
  "details": {               // Detalhes do erro
    "type": "error_type",
    "code": "error_code"
  }
}
```

## 2. Rate Limiting Granular

### Configuração por Categoria

#### `auth` - Autenticação
- **20/min** geral por IP
- **5/min** por email/IP (ataques direcionados)
- **50/hora** por IP (ataques sustentados)

#### `api` - API Autenticada
- **Usuários autenticados**: 100/min, 2000/hora
- **Não autenticados**: 30/min, 500/hora

#### `public` - Endpoints Públicos
- **60/min** por IP
- **1000/hora** por IP

#### `reservations` - Reservas
- **Usuários regulares**: 30/min, 500/hora
- **System Integration/Bulk**: 60/min, 500/hora, 2000/dia
- **Públicos**: 20/min, 200/hora

#### `admin` - Administração
- **30/min** por usuário
- **300/hora** por usuário

#### `uploads` - Uploads
- **10/min** por usuário/IP
- **100/hora** por usuário/IP
- **500/dia** por usuário/IP

#### `bulk` - Operações em Lote
- **100/min** por usuário/IP
- **1000/hora** por usuário/IP
- **5000/dia** por usuário/IP

### Aplicação nas Rotas

```php
// Endpoints públicos
Route::middleware(['throttle:public'])->group(function() {
    // Rotas públicas
});

// Autenticação
Route::middleware(['throttle:auth'])->group(function() {
    Route::post('token', ...);
});

// API autenticada
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function() {
    // Rotas autenticadas
});

// Reservas
Route::middleware(['auth:sanctum', 'throttle:reservations'])->group(function() {
    // CRUD de reservas
});

// Admin
Route::middleware(['throttle:admin'])->group(function() {
    // Aprovação/rejeição
});
```

## 3. Tratamento Global de Exceções

### Handler Melhorado (`app/Exceptions/Handler.php`)

- **Detecção Automática**: Identifica requests de API via `expectsJson()` ou `is('api/*')`
- **Responses Padronizadas**: Todas as exceções retornam formato consistente
- **Logging Aprimorado**: Context detalhado para debugging
- **Compatibilidade**: Requests web continuam com comportamento original

### Exceções Tratadas

- `ValidationException` → Resposta de validação padronizada
- `AuthenticationException` → Erro de autenticação
- `AuthorizationException` → Erro de autorização
- `NotFoundHttpException` → Recurso não encontrado
- `ThrottleRequestsException` → Rate limit excedido
- `QueryException` → Erro de banco de dados
- `HttpException` → Erros HTTP genéricos
- Todas as demais → Erro interno do servidor

## 4. Middleware de Rate Limiting

### ApiRateLimitMiddleware (`app/Http/Middleware/ApiRateLimitMiddleware.php`)

Middleware customizado com:
- **Keys Inteligentes**: Baseadas em usuário, email ou IP conforme contexto
- **Limites Dinâmicos**: Diferentes por categoria de endpoint
- **Headers Informativos**: `X-RateLimit-Limit` e `X-RateLimit-Remaining`
- **Logging**: Registra tentativas de rate limit excedido
- **Respostas Padronizadas**: Usa ApiResponseTrait

## 5. Implementação Compatível

### Backward Compatibility Garantida

1. **Métodos Existentes**: Todos preservados e funcionais
2. **Estruturas de Response**: Clientes existentes continuam funcionando
3. **Rate Limiting Transparente**: Aplicado sem quebrar funcionalidade
4. **Error Handling**: Apenas aprimora, não substitui comportamento existente

### Exemplo de Migração Gradual

#### Antes (mantido funcionando):
```php
return response()->json([
    'error' => 'Validation failed',
    'message' => 'Dados inválidos'
], 422);
```

#### Depois (novo padrão recomendado):
```php
return $this->validationErrorResponse($errors, 'Dados inválidos');
```

## 6. Configuração e Uso

### Para Novos Controllers
```php
use App\Http\Traits\ApiResponseTrait;

class NovoController extends Controller 
{
    use ApiResponseTrait;
    
    public function store() 
    {
        // ... lógica
        return $this->createdResponse($data, 'Criado com sucesso');
    }
}
```

### Para Controllers Existentes
```php
// Adicionar gradualmente
use App\Http\Traits\ApiResponseTrait;

class ExistingController extends Controller 
{
    use ApiResponseTrait;
    
    // Métodos existentes continuam funcionando
    // Novos métodos podem usar o trait
}
```

## 7. Monitoramento e Logs

### Logs de Rate Limiting
- **Tentativas Bloqueadas**: Registradas com contexto completo
- **Informações**: IP, usuário, endpoint, user-agent
- **Alertas**: Para padrões de abuso detectados

### Logs de Erros da API
- **Context Rico**: Exceção, endpoint, usuário, IP
- **Debugging**: Facilita identificação de problemas
- **Segurança**: Não expõe informações sensíveis

## 8. Testes e Validação

### Verificações Realizadas
- ✅ Sintaxe PHP válida
- ✅ Rotas carregadas corretamente  
- ✅ Configuração aplicada
- ✅ Middleware registrado
- ✅ Compatibilidade mantida

### Próximos Passos Recomendados
1. **Testes Automatizados**: Implementar testes para rate limiting
2. **Monitoramento**: Configurar alertas para rate limiting
3. **Documentação**: Atualizar documentação da API
4. **Treinamento**: Capacitar equipe no novo padrão

## Conclusão

Esta implementação melhora significativamente a robustez e consistência da API, fornecendo:

- 🛡️ **Proteção Contra Abuso**: Rate limiting granular e inteligente
- 📊 **Consistência**: Respostas padronizadas em toda API  
- 🔍 **Observabilidade**: Logs estruturados e informativos
- 🔒 **Segurança**: Prevenção de ataques de força bruta
- ⚡ **Performance**: Otimização de recursos do servidor
- 🚀 **Escalabilidade**: Base sólida para crescimento futuro

**Impacto**: Alto valor com zero breaking changes, garantindo evolução segura da API.

### Dicas de Performance e Otimização

1. **Cache Local**: Cache listas de salas e finalidades no client
2. **Paginação Inteligente**: Use `per_page` apropriado (15-25 para mobile, 50+ para desktop)
3. **Filtros Eficientes**: Combine filtros para reduzir dados transferidos
4. **Batch Operations**: Agrupe múltiplas operações quando possível
5. **Connection Pooling**: Reutilize conexões HTTP/2
6. **Gzip Compression**: Ative compressão para responses grandes

### Troubleshooting Comum

| Problema | Causa Provável | Solução |
|----------|----------------|---------|
| Token sempre inválido | Clock skew entre client/server | Sincronizar horário do sistema |
| Rate limit constante | Muitas requisições paralelas | Implementar queue/throttling |
| Conflitos de reserva | Validação client-server dessincronia | Revalidar antes de submeter |
| Uploads lentos | Dados desnecessários no payload | Otimizar estrutura de dados |