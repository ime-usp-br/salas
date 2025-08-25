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