# 📖 API Reference - Sistema de Câmeras v2

## Índice Rápido

### Câmeras
- [GET /cameras](#get-cameras) - Listar câmeras
- [POST /cameras](#post-cameras) - Criar câmera
- [GET /cameras/{id}](#get-camerasid) - Detalhe da câmera
- [PUT /cameras/{id}](#put-camerasid) - Atualizar câmera
- [DELETE /cameras/{id}](#delete-camerasid) - Deletar câmera

### Alarmes
- [GET /alarmes](#get-alarmes) - Listar alarmes
- [POST /alarmes](#post-alarmes) - Criar alarme
- [GET /alarmes/{id}](#get-alarmesid) - Detalhe do alarme
- [PUT /alarmes/{id}](#put-alarmesid) - Atualizar alarme
- [DELETE /alarmes/{id}](#delete-alarmesid) - Deletar alarme

### Dados
- [GET /locais](#get-locais) - Listar locais
- [GET /modelos_cameras](#get-modelos_cameras) - Listar modelos de câmeras
- [GET /modelos_alarmes](#get-modelos_alarmes) - Listar modelos de alarmes
- [GET /dashboard](#get-dashboard) - Dashboard com estatísticas

### Relatórios e Uploads
- [GET /relatorios_cameras](#get-relatorios_cameras) - Relatório de câmeras
- [POST /upload_anexo](#post-upload_anexo) - Upload de arquivo

### Sistema
- [GET /health](#get-health) - Health check (admin only)

---

## Detalhamento dos Endpoints

### GET /cameras

**Listar todas as câmeras com paginação e filtros**

```bash
curl http://localhost/api/v2/cameras?page=1&per_page=50&busca=IP_ADDRESS
```

#### Parâmetros Query

| Param | Tipo | Padrão | Descrição |
|-------|------|--------|-----------|
| `page` | int | 1 | Número da página |
| `per_page` | int | 50 | Itens por página (máx 200) |
| `busca` | string | - | Busca por IP, série, nome local |
| `status` | string | - | Filtro por status |
| `modelo_id` | int | - | Filtro por ID do modelo |
| `local_id` | int | - | Filtro por ID do local |

#### Respostas

**200 OK - Sucesso**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "nome": "Camera Entrada",
      "ip": "192.168.1.100",
      "numero_serie": "CAM123456",
      "local_id": 1,
      "modelo_id": 5,
      "status": "ativa",
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "page": 1,
  "per_page": 50,
  "total": 250
}
```

**401 Unauthorized**
```json
{
  "status": "error",
  "code": "UNAUTHORIZED",
  "message": "Autenticação necessária"
}
```

---

### POST /cameras

**Criar uma nova câmera**

```bash
curl -X POST http://localhost/api/v2/cameras \
  -H "Content-Type: application/json" \
  -d '{
    "ip": "192.168.1.100",
    "numero_serie": "CAM123456",
    "local_id": 1,
    "modelo_id": 5,
    "patrimonio": "PAT-2024-001",
    "data_instalacao": "2024-01-15"
  }'
```

#### Request Body

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `ip` | string | ✓ | Endereço IP (formato válido) |
| `numero_serie` | string | ✓ | Número de série |
| `local_id` | int | ✓ | ID do local |
| `modelo_id` | int | ✓ | ID do modelo |
| `patrimonio` | string | - | Número de patrimônio |
| `data_instalacao` | string | - | Data (YYYY-MM-DD) |
| `observacao` | string | - | Observações |
| `coordenadas` | string | - | Lat,Long (ex: -23.5,-46.6) |

#### Respostas

**201 Created - Sucesso**
```json
{
  "status": "created",
  "message": "Câmera cadastrada com sucesso",
  "camera": {
    "id": 256,
    "ip": "192.168.1.100",
    "numero_serie": "CAM123456",
    "local_id": 1,
    "modelo_id": 5
  }
}
```

**422 Validation Error**
```json
{
  "status": "error",
  "code": "VALIDATION_ERROR",
  "errors": {
    "ip": ["IP inválido"],
    "numero_serie": ["Campo obrigatório"]
  }
}
```

---

### GET /cameras/{id}

**Obter detalhes de uma câmera específica**

```bash
curl http://localhost/api/v2/cameras/123
```

#### Parâmetros Path

| Param | Tipo | Descrição |
|-------|------|-----------|
| `id` | int | ID da câmera |

#### Respostas

**200 OK**
```json
{
  "status": "success",
  "data": {
    "id": 123,
    "nome": "Camera Entrada",
    "ip": "192.168.1.100",
    "numero_serie": "CAM123456",
    "local_id": 1,
    "modelo_id": 5,
    "modelo_nome": "Intelbras IC7",
    "marca_nome": "Intelbras",
    "status": "ativa",
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-20T14:45:00Z"
  }
}
```

**404 Not Found**
```json
{
  "status": "error",
  "code": "NOT_FOUND",
  "message": "Câmera não encontrada"
}
```

---

### PUT /cameras/{id}

**Atualizar uma câmera**

```bash
curl -X PUT http://localhost/api/v2/cameras/123 \
  -H "Content-Type: application/json" \
  -d '{
    "ip": "192.168.1.101",
    "observacao": "Câmera relocada"
  }'
```

#### Request Body
(Mesmos campos de POST, todos opcionais)

#### Respostas

**200 OK - Sucesso**
```json
{
  "status": "success",
  "message": "Câmera atualizada com sucesso",
  "camera": { ... }
}
```

---

### DELETE /cameras/{id}

**Deletar uma câmera (soft delete)**

```bash
curl -X DELETE http://localhost/api/v2/cameras/123
```

#### Respostas

**200 OK - Sucesso**
```json
{
  "status": "success",
  "message": "Câmera deletada com sucesso"
}
```

---

### GET /alarmes

**Listar todos os alarmes**

```bash
curl http://localhost/api/v2/alarmes?page=1&per_page=20&busca=CONTA
```

#### Parâmetros

| Param | Tipo | Padrão | Descrição |
|-------|------|--------|-----------|
| `page` | int | 1 | Número da página |
| `per_page` | int | 20 | Itens por página (máx 100) |
| `busca` | string | - | Busca por conta, local, IP |

#### Respostas

**200 OK**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "conta": "ALM-001",
      "local": "Entrada Principal",
      "endereco": "Rua A, 100",
      "status": "ativa",
      "modelo_id": 1,
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "page": 1,
  "per_page": 20,
  "total": 150
}
```

---

### POST /alarmes

**Criar um novo alarme**

```bash
curl -X POST http://localhost/api/v2/alarmes \
  -H "Content-Type: application/json" \
  -d '{
    "conta": "ALM-002",
    "local": "Entrada Principal",
    "endereco": "Rua A, 100",
    "modelo_id": 1,
    "observacao": "Alarme novo"
  }'
```

#### Respostas

**201 Created**
```json
{
  "status": "created",
  "message": "Alarme cadastrado com sucesso",
  "alarm": { ... }
}
```

---

### GET /locais

**Listar todos os locais**

```bash
curl http://localhost/api/v2/locais
```

#### Respostas

**200 OK**
```json
{
  "status": "success",
  "locais": [
    {
      "id": 1,
      "nome": "Entrada Principal",
      "cidade": "São Paulo",
      "uf": "SP"
    }
  ]
}
```

---

### GET /modelos_cameras

**Listar modelos de câmeras disponíveis**

```bash
curl http://localhost/api/v2/modelos_cameras
```

#### Respostas

**200 OK**
```json
{
  "status": "success",
  "modelos": [
    {
      "id": 1,
      "nome": "Intelbras IC7",
      "marca": "Intelbras",
      "resolucao": "1080p"
    }
  ]
}
```

---

### GET /modelos_alarmes

**Listar modelos de alarmes disponíveis**

```bash
curl http://localhost/api/v2/modelos_alarmes
```

---

### GET /dashboard

**Obter dados do dashboard (estatísticas)**

```bash
curl http://localhost/api/v2/dashboard
```

#### Respostas

**200 OK**
```json
{
  "status": "success",
  "stats": {
    "total": 250,
    "ativas": 240,
    "manutencao": 7,
    "desativadas": 3,
    "uptime": 95,
    "alarmes_total": 150,
    "alarmes_manutencao": 145,
    "alarmes_atrasadas": 5
  },
  "tipos": [
    {
      "tipo": "Bullet",
      "quantidade": 150
    }
  ]
}
```

---

### GET /relatorios_cameras

**Gerar relatório de câmeras com filtros avançados**

```bash
curl 'http://localhost/api/v2/relatorios_cameras?data_inicial=01/01/2024&data_final=31/12/2024&status=1&page=1'
```

#### Parâmetros

| Param | Tipo | Descrição |
|-------|------|-----------|
| `page_num` | int | Número da página |
| `per_page` | int | Itens por página (máx 200) |
| `data_inicial` | string | Data inicial (dd/mm/yyyy) |
| `data_final` | string | Data final (dd/mm/yyyy) |
| `status` | string | ID do status |
| `local` | int | ID do local |
| `regiao` | int | ID da região |
| `pesquisa` | string | Busca por IP |

#### Respostas

**200 OK**
```json
{
  "status": "success",
  "data": [
    {
      "id": 123,
      "nome_local": "Entrada",
      "ip": "192.168.1.100",
      "numero_serie": "CAM123456",
      "modelo_nome": "Intelbras IC7",
      "data_instalacao": "2024-01-15",
      "status_nome": "Ativa"
    }
  ],
  "page": 1,
  "per_page": 50,
  "total": 250
}
```

---

### POST /upload_anexo

**Fazer upload de arquivo (foto, documento)**

```bash
curl -X POST http://localhost/api/v2/upload_anexo \
  -F "file=@foto.jpg" \
  -F "equipamento_id=123" \
  -F "tipo=foto" \
  -F "descricao=Foto frontal"
```

#### Request Body (multipart/form-data)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `file` | file | ✓ | Arquivo a fazer upload (máx 10MB) |
| `equipamento_id` | int | - | ID da câmera |
| `alarme_id` | int | - | ID do alarme |
| `manutencao_camera_id` | int | - | ID da manutenção câmera |
| `manutencao_alarme_id` | int | - | ID da manutenção alarme |
| `tipo` | string | - | foto, documento, anexo |
| `descricao` | string | - | Descrição do arquivo |

#### Tipos MIME Aceitos

**Imagens**
- image/jpeg (.jpg, .jpeg)
- image/png (.png)
- image/gif (.gif)
- image/webp (.webp)
- image/bmp (.bmp)

**Documentos**
- application/pdf (.pdf)
- application/msword (.doc)
- application/vnd.openxmlformats-officedocument.wordprocessingml.document (.docx)
- application/vnd.ms-excel (.xls)
- application/vnd.openxmlformats-officedocument.spreadsheetml.sheet (.xlsx)

#### Respostas

**201 Created - Sucesso**
```json
{
  "status": "created",
  "message": "Arquivo enviado com sucesso",
  "anexo": {
    "id": 456,
    "nome_original": "foto.jpg",
    "nome_arquivo": "abc123def456.jpg",
    "url": "https://cameras.example.com/uploads/equipamentos/abc123def456.jpg",
    "tamanho": 125000,
    "mime_type": "image/jpeg",
    "tipo": "foto",
    "descricao": "Foto frontal"
  }
}
```

**422 Validation Error**
```json
{
  "status": "error",
  "code": "VALIDATION_ERROR",
  "message": "Nenhum ID fornecido ou tipo de arquivo inválido"
}
```

---

### GET /health

**Health check (requer acesso admin)**

```bash
curl http://localhost/api/v2/health
```

#### Respostas

**200 OK - Sistema OK**
```json
{
  "status": "ok",
  "environment": "production",
  "checks": {
    "db_connection": true,
    "table_auditoria_eventos": true,
    "table_login_attempts": true,
    "table_user_sessions": true,
    "https": true
  }
}
```

**200 OK - Sistema Degradado**
```json
{
  "status": "degraded",
  "environment": "production",
  "checks": {
    "db_connection": true,
    "table_auditoria_eventos": false,
    "table_login_attempts": true,
    "table_user_sessions": true,
    "https": true
  }
}
```

---

## Códigos de Erro Comuns

| Código | HTTP | Significado |
|--------|------|-------------|
| `UNAUTHORIZED` | 401 | Autenticação necessária |
| `FORBIDDEN` | 403 | Acesso negado |
| `NOT_FOUND` | 404 | Recurso não encontrado |
| `BAD_REQUEST` | 400 | Requisição inválida |
| `VALIDATION_ERROR` | 422 | Erro de validação |
| `TOO_MANY_REQUESTS` | 429 | Rate limit excedido |
| `INTERNAL_ERROR` | 500 | Erro interno do servidor |

---

## Autenticação

### Fazer Login (Fora da API)

A autenticação é baseada em sessões PHP. Faça login na interface web primeiro, então use a API com o cookie de sessão.

```bash
# Obter cookie
curl -c cookies.txt -X POST http://localhost/login \
  -d "email=user@example.com&password=senha"

# Usar em requisições posteriores
curl -b cookies.txt http://localhost/api/v2/cameras
```

---

## Rate Limiting

**Limites por sessão:**
- 100 requisições / 15 minutos

**Limite por IP:**
- 1000 requisições / 1 hora

Quando excedido, você receberá:
```json
{
  "status": "error",
  "code": "TOO_MANY_REQUESTS",
  "message": "Rate limit excedido",
  "retry_after": 60
}
```

HTTP Status: **429 Too Many Requests**

---

## Boas Práticas

✅ **Faça**
- Use paginação para listas grandes
- Valide dados antes de enviar
- Implemente retry com backoff exponencial
- Cache dados quando possível
- Use HTTPS em produção

❌ **Não Faça**
- Não consulte a API em loops apertados
- Não exponha tokens em URLs
- Não ignore rate limiting
- Não armazene senhas
- Não faça requests síncronos bloqueantes

---

## Exemplos em Diferentes Linguagens

### JavaScript/Fetch

```javascript
// Listar câmeras
const response = await fetch('http://localhost/api/v2/cameras?page=1', {
  method: 'GET',
  credentials: 'include',
  headers: {
    'Content-Type': 'application/json'
  }
});

const data = await response.json();
console.log(data);
```

### Python

```python
import requests

response = requests.get(
    'http://localhost/api/v2/cameras',
    params={'page': 1, 'per_page': 50},
    cookies=session_cookies
)

data = response.json()
print(data)
```

### cURL

```bash
curl -H "Content-Type: application/json" \
  http://localhost/api/v2/cameras?page=1&per_page=50
```

---

## Suporte

Para dúvidas ou problemas:
- 📧 Email: api-support@example.com
- 🐛 Issues: issues@example.com
- 📚 Docs: https://cameras.example.com/docs

---

**Última atualização**: Junho 2024  
**Versão da API**: 2.0.0  
**Versão do OpenAPI**: 3.0.0
