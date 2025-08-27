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