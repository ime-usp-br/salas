# *Endpoints* e *Responses* da API

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

- `/api/v1/finalidades`: retorna todas as finalidades cadastradas no sistema.

Exemplo de _response_:
```json
{
    "data": [{
        "id": 1,
        "legenda": "Graduação"
    }, {
        "id": 2,
        "legenda": "Pós-Graduação"
    }, {
        "id": 3,
        "legenda": "Especialização"
    }, {
        "id": 4,
        "legenda": "Extensão"
    }, {
        "id": 5,
        "legenda": "Defesa"
    }, {
        "id": 6,
        "legenda": "Qualificação"
    }, {
        "id": 7,
        "legenda": "Reunião"
    }, {
        "id": 8,
        "legenda": "Evento"
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

## 🔄 Reservas Recorrentes (AC6)

### Criação de Reservas Recorrentes

**POST** `/api/v1/reservas` *(protegido)*

Cria uma nova reserva ou série de reservas recorrentes no sistema.

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

**Parâmetros de Recorrência:**
- `repeat_days` (array): Dias da semana para repetição (0=domingo, 1=segunda, ..., 6=sábado)
- `repeat_until` (string): Data final da recorrência no formato Y-m-d (obrigatório com repeat_days)

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

**Exemplo - Reserva Recorrente:**
```json
POST /api/v1/reservas
Authorization: Bearer 1|TOKEN
Content-Type: application/json

{
    "nome": "Reunião Semanal da Equipe",
    "descricao": "Reunião recorrente às segundas e quartas",
    "data": "2024-09-15",
    "horario_inicio": "14:00",
    "horario_fim": "16:00",
    "sala_id": 1,
    "finalidade_id": 7,
    "tipo_responsaveis": "unidade",
    "responsaveis_unidade": [123456, 789012],
    "repeat_days": [1, 3],
    "repeat_until": "2024-12-15"
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

**Response - Reserva Recorrente (201 Created):**
```json
{
    "data": {
        "id": 157,
        "nome": "Reunião Semanal da Equipe",
        "descricao": "Reunião recorrente às segundas e quartas",
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
        "recurrent": true,
        "instances_created": 25,
        "parent_id": 157,
        "recurring_details": {
            "repeat_days": [1, 3],
            "repeat_until": "2024-12-15",
            "first_date": "15/09/2024",
            "last_date": "15/12/2024"
        }
    },
    "meta": {
        "total_reservations": 25,
        "recurring_series": true,
        "success": true,
        "date_range": {
            "from": "15/09/2024",
            "to": "15/12/2024"
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

---

## 💡 **Casos de Uso Práticos e Melhores Práticas**

### Caso 1: Sistema de Gestão de Eventos
**Cenário**: Uma universidade precisa gerenciar reservas de salas para eventos acadêmicos.

```javascript
// Fluxo completo de reserva de evento
async function reservarEvento() {
    // 1. Obter token de autenticação
    const tokenResponse = await fetch('/api/v1/auth/token', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            email: 'organizador@universidade.edu.br',
            password: 'senha_segura',
            token_name: 'Sistema Eventos'
        })
    });
    
    const { token } = (await tokenResponse.json()).data;
    const headers = {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    };

    // 2. Listar salas disponíveis
    const salasResponse = await fetch('/api/v1/salas', { headers });
    const salas = (await salasResponse.json()).data;
    
    // 3. Verificar disponibilidade da sala desejada
    const salaId = 16; // Auditório
    const dataEvento = '2024-09-15';
    
    const disponibilidadeResponse = await fetch(
        `/api/v1/salas/${salaId}/availability?data=${dataEvento}`, 
        { headers }
    );
    
    // 4. Criar reserva recorrente para série de palestras
    const reservaResponse = await fetch('/api/v1/reservas', {
        method: 'POST',
        headers,
        body: JSON.stringify({
            nome: 'Semana de Ciência e Tecnologia',
            descricao: 'Palestras diárias durante a semana acadêmica',
            data: dataEvento,
            horario_inicio: '19:00',
            horario_fim: '21:00',
            sala_id: salaId,
            finalidade_id: 8, // Evento
            tipo_responsaveis: 'unidade',
            responsaveis_unidade: [123456, 789012],
            repeat_days: [1, 2, 3, 4, 5], // Segunda a sexta
            repeat_until: '2024-09-19'
        })
    });
    
    const reservaData = await reservaResponse.json();
    
    if (reservaResponse.ok) {
        console.log(`✅ Série criada! ${reservaData.data.instances_created} reservas`);
        return reservaData.data.parent_id;
    } else {
        console.error('❌ Erro na reserva:', reservaData);
        throw new Error(reservaData.message);
    }
}
```

### Caso 2: App Mobile para Reservas Rápidas
**Cenário**: Aplicativo móvel que permite reservas rápidas com validações inteligentes.

```swift
// Swift iOS - Reserva com tratamento de erros
class ReservaService {
    private let baseURL = "https://salas.usp.br/api/v1"
    private var authToken: String?
    
    func criarReservaRapida(nome: String, salaId: Int, inicio: String, fim: String) async throws -> ReservaResponse {
        guard let token = authToken else {
            throw ReservaError.naoAutenticado
        }
        
        let url = URL(string: "\(baseURL)/reservas")!
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        
        let tomorrow = Calendar.current.date(byAdding: .day, value: 1, to: Date())!
        let formatter = DateFormatter()
        formatter.dateFormat = "yyyy-MM-dd"
        
        let reservaData = [
            "nome": nome,
            "data": formatter.string(from: tomorrow),
            "horario_inicio": inicio,
            "horario_fim": fim,
            "sala_id": salaId,
            "finalidade_id": 7, // Reunião
            "tipo_responsaveis": "eu"
        ]
        
        request.httpBody = try JSONSerialization.data(withJSONObject: reservaData)
        
        do {
            let (data, response) = try await URLSession.shared.data(for: request)
            
            if let httpResponse = response as? HTTPURLResponse {
                switch httpResponse.statusCode {
                case 201:
                    return try JSONDecoder().decode(ReservaResponse.self, from: data)
                case 401:
                    throw ReservaError.tokenExpirado
                case 403:
                    throw ReservaError.semPermissao
                case 422:
                    let validationError = try JSONDecoder().decode(ValidationErrorResponse.self, from: data)
                    throw ReservaError.dadosInvalidos(validationError.errors)
                case 429:
                    throw ReservaError.muitasRequisicoes
                default:
                    throw ReservaError.erroServidor(httpResponse.statusCode)
                }
            }
        } catch {
            throw ReservaError.erroRede(error.localizedDescription)
        }
        
        throw ReservaError.respostaInvalida
    }
}

enum ReservaError: Error {
    case naoAutenticado
    case tokenExpirado
    case semPermissao
    case dadosInvalidos([String: [String]])
    case muitasRequisicoes
    case erroServidor(Int)
    case erroRede(String)
    case respostaInvalida
}
```

### Caso 3: Dashboard de Administração
**Cenário**: Painel administrativo para gerenciar aprovações e relatórios.

```python
# Python - Dashboard admin com relatórios
import requests
from datetime import datetime, timedelta
import pandas as pd

class AdminDashboard:
    def __init__(self, token):
        self.base_url = "https://salas.usp.br/api/v1"
        self.headers = {
            "Authorization": f"Bearer {token}",
            "Content-Type": "application/json"
        }
    
    def get_pending_approvals(self):
        """Busca reservas pendentes de aprovação"""
        try:
            # Como não temos endpoint específico, simulamos busca por status
            response = requests.get(
                f"{self.base_url}/reservas/my",
                headers=self.headers,
                params={"status": "pendente", "per_page": 50}
            )
            
            if response.status_code == 200:
                return response.json()["data"]
            elif response.status_code == 429:
                print("⚠️ Rate limit atingido. Aguarde...")
                return []
            else:
                print(f"❌ Erro ao buscar aprovações: {response.status_code}")
                return []
                
        except requests.RequestException as e:
            print(f"❌ Erro de rede: {e}")
            return []
    
    def approve_reservation(self, reserva_id):
        """Aprova uma reserva específica"""
        try:
            response = requests.patch(
                f"{self.base_url}/reservas/{reserva_id}/approve",
                headers=self.headers
            )
            
            if response.status_code == 200:
                data = response.json()
                print(f"✅ Reserva {reserva_id} aprovada por {data['data']['approved_by']}")
                return True
            elif response.status_code == 403:
                print(f"❌ Sem permissão para aprovar reserva {reserva_id}")
                return False
            elif response.status_code == 422:
                error_data = response.json()
                print(f"❌ Não foi possível aprovar: {error_data['message']}")
                return False
            else:
                print(f"❌ Erro inesperado: {response.status_code}")
                return False
                
        except requests.RequestException as e:
            print(f"❌ Erro de rede: {e}")
            return False
    
    def generate_usage_report(self, days=30):
        """Gera relatório de uso das salas"""
        end_date = datetime.now().date()
        start_date = end_date - timedelta(days=days)
        
        # Coleta dados dia por dia para contornar limitações da API
        all_reservations = []
        current_date = start_date
        
        while current_date <= end_date:
            try:
                response = requests.get(
                    f"{self.base_url}/reservas",
                    params={
                        "data": current_date.strftime("%Y-%m-%d"),
                        "per_page": 50
                    }
                )
                
                if response.status_code == 200:
                    day_reservations = response.json()["data"]
                    all_reservations.extend(day_reservations)
                elif response.status_code == 429:
                    print(f"⚠️ Rate limit - pulando {current_date}")
                
                current_date += timedelta(days=1)
                
            except requests.RequestException as e:
                print(f"❌ Erro coletando dados de {current_date}: {e}")
                current_date += timedelta(days=1)
                continue
        
        # Análise com pandas
        df = pd.DataFrame(all_reservations)
        
        if not df.empty:
            # Relatório por sala
            usage_by_room = df['sala'].value_counts()
            
            # Relatório por finalidade
            usage_by_purpose = df['finalidade'].value_counts()
            
            # Horários mais utilizados
            df['hour'] = pd.to_datetime(df['horario_inicio']).dt.hour
            usage_by_hour = df['hour'].value_counts().sort_index()
            
            print(f"\n📊 Relatório de Uso - Últimos {days} dias")
            print(f"Total de reservas: {len(df)}")
            print(f"\n🏛️ Top 5 salas mais utilizadas:")
            print(usage_by_room.head())
            print(f"\n🎯 Finalidades mais comuns:")
            print(usage_by_purpose.head())
            print(f"\n⏰ Horários de pico:")
            peak_hours = usage_by_hour.nlargest(3)
            for hour, count in peak_hours.items():
                print(f"  {hour:02d}:00 - {count} reservas")
        
        return df

# Uso do dashboard
if __name__ == "__main__":
    # Assumindo que temos token de admin
    admin = AdminDashboard("admin_token_here")
    
    # Processar aprovações pendentes
    pending = admin.get_pending_approvals()
    for reserva in pending:
        print(f"📋 Reserva pendente: {reserva['nome']} - Sala {reserva['sala']}")
        # admin.approve_reservation(reserva['id'])  # Descomente para aprovar
    
    # Gerar relatório mensal
    report_df = admin.generate_usage_report(30)
```

### Melhores Práticas de Implementação

#### 1. **Gerenciamento de Token**
```javascript
class TokenManager {
    constructor() {
        this.token = localStorage.getItem('api_token');
        this.tokenExpiry = localStorage.getItem('token_expiry');
    }
    
    async ensureValidToken(credentials) {
        if (this.isTokenExpired()) {
            await this.refreshToken(credentials);
        }
        return this.token;
    }
    
    isTokenExpired() {
        if (!this.token || !this.tokenExpiry) return true;
        return Date.now() > parseInt(this.tokenExpiry);
    }
    
    async refreshToken({ email, password }) {
        const response = await fetch('/api/v1/auth/token', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password, token_name: 'App Client' })
        });
        
        if (response.ok) {
            const data = await response.json();
            this.token = data.data.token;
            // Assumir expiração de 24h se não informado
            this.tokenExpiry = Date.now() + (24 * 60 * 60 * 1000);
            
            localStorage.setItem('api_token', this.token);
            localStorage.setItem('token_expiry', this.tokenExpiry.toString());
        } else {
            throw new Error('Falha na renovação do token');
        }
    }
}
```

#### 2. **Retry Logic com Backoff Exponencial**
```javascript
class APIClient {
    constructor(tokenManager) {
        this.tokenManager = tokenManager;
        this.maxRetries = 3;
    }
    
    async request(endpoint, options = {}, retryCount = 0) {
        const token = await this.tokenManager.ensureValidToken();
        
        const response = await fetch(`/api/v1${endpoint}`, {
            ...options,
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
                ...options.headers
            }
        });
        
        // Rate limiting - retry com backoff
        if (response.status === 429 && retryCount < this.maxRetries) {
            const retryAfter = response.headers.get('Retry-After') || 1;
            const delay = Math.min(1000 * Math.pow(2, retryCount), parseInt(retryAfter) * 1000);
            
            console.log(`Rate limited. Retrying after ${delay}ms...`);
            await this.sleep(delay);
            return this.request(endpoint, options, retryCount + 1);
        }
        
        // Token expirado - renovar e tentar novamente
        if (response.status === 401 && retryCount < this.maxRetries) {
            this.tokenManager.clearToken();
            return this.request(endpoint, options, retryCount + 1);
        }
        
        return response;
    }
    
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}
```

#### 3. **Validação Client-Side**
```javascript
class ReservationValidator {
    static validateReservation(data) {
        const errors = {};
        
        // Validação de nome
        if (!data.nome || data.nome.trim().length < 3) {
            errors.nome = ['Nome deve ter pelo menos 3 caracteres'];
        }
        
        // Validação de data
        const reservationDate = new Date(data.data);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (reservationDate < today) {
            errors.data = ['Data deve ser hoje ou no futuro'];
        }
        
        // Validação de horários
        const [startHour, startMin] = data.horario_inicio.split(':').map(Number);
        const [endHour, endMin] = data.horario_fim.split(':').map(Number);
        
        const startMinutes = startHour * 60 + startMin;
        const endMinutes = endHour * 60 + endMin;
        
        if (endMinutes <= startMinutes) {
            errors.horario_fim = ['Horário de fim deve ser após o início'];
        }
        
        // Validação de duração mínima (30 minutos)
        if (endMinutes - startMinutes < 30) {
            errors.horario_fim = ['Reserva deve ter duração mínima de 30 minutos'];
        }
        
        return Object.keys(errors).length > 0 ? errors : null;
    }
    
    static validateRecurringReservation(data) {
        const errors = this.validateReservation(data) || {};
        
        if (data.repeat_days && data.repeat_days.length === 0) {
            errors.repeat_days = ['Selecione pelo menos um dia da semana'];
        }
        
        if (data.repeat_until) {
            const endDate = new Date(data.repeat_until);
            const startDate = new Date(data.data);
            const maxDate = new Date(startDate);
            maxDate.setMonth(maxDate.getMonth() + 6); // 6 meses máximo
            
            if (endDate > maxDate) {
                errors.repeat_until = ['Período máximo de recorrência é 6 meses'];
            }
        }
        
        return Object.keys(errors).length > 0 ? errors : null;
    }
}
```

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