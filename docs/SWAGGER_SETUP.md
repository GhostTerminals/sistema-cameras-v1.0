# 📚 Guia Swagger UI - Sistema de Câmeras API

## Introdução

Este guia explica como acessar e usar a interface interativa do Swagger UI para testar a API do Sistema de Câmeras.

## O Que é Swagger UI?

**Swagger UI** é uma interface interativa que permite:
- 📖 Visualizar toda documentação da API em um único lugar
- 🧪 Testar endpoints diretamente no navegador
- 📝 Ver exemplos de requisições e respostas
- 🔍 Explorar schemas e validações
- 🔐 Autenticar-se automaticamente

## Acessando o Swagger

### Opção 1: Online (Recomendado para Produção)

Acesse via URL direta:
```
https://cameras.example.com/api/swagger
```

### Opção 2: Localmente (Desenvolvimento)

```
http://localhost/api/swagger
```

## Autenticação

### Passo 1: Fazer Login

Antes de testar qualquer endpoint, você precisa autenticar:

1. Acesse a página de login
2. Faça login com suas credenciais
3. O cookie de sessão (PHPSESSID) será automaticamente enviado nos requests

### Passo 2: Verificar Autenticação

Na interface Swagger:
1. Localize o ícone de "🔒 Authorize" (cadeado)
2. Clique para abrir o diálogo de autenticação
3. O cookie da sessão deve aparecer como autenticado

## Testando Endpoints

### Exemplo 1: Listar Câmeras

1. **Abra a seção "Câmeras"**
   - Procure por `GET /cameras`

2. **Clique no endpoint**
   - Uma seção se expandirá

3. **Configure os parâmetros** (opcional)
   - `page`: 1 (padrão)
   - `per_page`: 50 (padrão)
   - `busca`: deixe vazio para listar todas

4. **Clique em "Try it out"**
   - Os campos ficarão editáveis

5. **Clique em "Execute"**
   - O request será enviado
   - Você verá:
     - **Request URL**: URL chamada
     - **Request headers**: Headers enviados
     - **Response headers**: Headers recebidos
     - **Response body**: Dados retornados

### Exemplo 2: Criar Câmera

1. **Abra** `POST /cameras`

2. **Clique em "Try it out"**

3. **Preencha o body JSON**:
   ```json
   {
     "ip": "192.168.1.100",
     "numero_serie": "CAM123456",
     "local_id": 1,
     "modelo_id": 5,
     "patrimonio": "PAT-2024-001",
     "data_instalacao": "2024-01-15",
     "observacao": "Câmera frontal"
   }
   ```

4. **Clique em "Execute"**

5. **Verifique a resposta**:
   - Status 201 = Criado com sucesso
   - A resposta conterá o ID da câmera criada

### Exemplo 3: Buscar Câmera Específica

1. **Abra** `GET /cameras/{id}`

2. **Preencha o parâmetro**:
   - `id`: 123 (ID da câmera)

3. **Execute**

## Entendendo as Respostas

### Resposta de Sucesso (200/201)

```json
{
  "status": "success",
  "data": {
    "id": 123,
    "ip": "192.168.1.100",
    "numero_serie": "CAM123456",
    "local_id": 1,
    "modelo_id": 5
  }
}
```

### Resposta de Erro de Validação (422)

```json
{
  "status": "error",
  "code": "VALIDATION_ERROR",
  "errors": {
    "ip": ["O campo ip é obrigatório"],
    "numero_serie": ["O número de série é inválido"]
  }
}
```

### Resposta Não Autorizado (401)

```json
{
  "status": "error",
  "code": "UNAUTHORIZED",
  "message": "Autenticação necessária"
}
```

### Resposta Não Encontrado (404)

```json
{
  "status": "error",
  "code": "NOT_FOUND",
  "message": "Recurso não encontrado"
}
```

## Testando com Paginação

Muitos endpoints retornam dados paginados:

```json
{
  "status": "success",
  "data": [...],
  "page": 1,
  "per_page": 50,
  "total": 250
}
```

### Para navegar:
- **Página 1**: page=1, per_page=50
- **Página 2**: page=2, per_page=50
- **Próxima página**: page=(current_page + 1)

## Filtros e Buscas

### Câmeras - Busca e Filtros

```
GET /cameras?busca=IP_ADDRESS&status=ativa&modelo_id=5&page=1&per_page=50
```

- `busca`: Busca por IP, série, nome local
- `status`: Filtro por status do equipamento
- `modelo_id`: Filtro por modelo de câmera

### Alarmes - Busca

```
GET /alarmes?busca=CONTA&page=1&per_page=20
```

- `busca`: Busca por conta, local, IP, endereço

### Relatórios - Filtros Avançados

```
GET /relatorios_cameras?data_inicial=01/01/2024&data_final=31/12/2024&status=1&local=5
```

- `data_inicial`: Data de início (dd/mm/yyyy)
- `data_final`: Data de fim (dd/mm/yyyy)
- `status`: ID do status
- `local`: ID do local

## Upload de Arquivos

### Fazendo Upload via Swagger

1. **Abra** `POST /upload_anexo`

2. **Clique em "Try it out"**

3. **Preencha os campos**:
   - `file`: Clique para selecionar o arquivo
   - `equipamento_id`: ID da câmera (ex: 123)
   - `tipo`: foto, documento ou anexo
   - `descricao`: Descrição do arquivo

4. **Execute**

5. **Verifique a resposta**:
   ```json
   {
     "status": "created",
     "anexo": {
       "id": 456,
       "nome_original": "foto.jpg",
       "url": "https://cameras.example.com/uploads/equipamentos/abc123def456.jpg",
       "tamanho": 125000,
       "mime_type": "image/jpeg"
     }
   }
   ```

## Testando Health Check

1. **Abra** `GET /health`

2. **Execute** (requer acesso admin)

3. **Respostas possíveis**:
   - `status: ok` - Todos os checks passaram
   - `status: degraded` - Alguns checks falharam

## Dicas Úteis

### 1. Salvar Requests Favoritos
- Muitos clientes HTTP salvam requests
- Use Postman ou Insomnia para coleções

### 2. Usar Variáveis
- Defina variáveis para IDs frequentemente usados
- Ex: `CAMERA_ID`, `ALARM_ID`

### 3. Ver Headers
- Clique em "Response headers" para ver headers da resposta
- Útil para debugging de cache e rate limiting

### 4. Exportar Responses
- Copie o JSON da resposta
- Cole em ferramentas como JsonFormatter.org

### 5. Testar Erros Intencionalmente
- Tente IDs inválidos para ver respostas 404
- Tente dados incompletos para ver respostas 422

## Rate Limiting

A API tem rate limiting ativo:

- **Por sessão**: 100 requisições / 15 minutos
- **Por IP**: 1000 requisições / 1 hora

Quando atingir o limite, você receberá:

```json
{
  "status": "error",
  "code": "TOO_MANY_REQUESTS",
  "message": "Rate limit excedido",
  "retry_after": 60
}
```

**Resposta HTTP**: 429

## Estrutura de Resposta Padrão

### Sucesso (GET/PUT/DELETE)
```json
{
  "status": "success",
  "data": {...}
}
```

### Sucesso (POST com recurso criado)
```json
{
  "status": "created",
  "message": "Recurso criado com sucesso",
  "data": {...}
}
```

### Erro
```json
{
  "status": "error",
  "code": "ERROR_CODE",
  "message": "Mensagem de erro"
}
```

## Resolvendo Problemas Comuns

### Erro: "Autenticação necessária"
- ✅ Solução: Faça login primeiro
- ✅ Verifique se o cookie PHPSESSID está ativo

### Erro: "Campo obrigatório faltando"
- ✅ Verifique a documentação para campos necessários
- ✅ Preencha todos os campos marcados com *

### Erro: "Recurso não encontrado" (404)
- ✅ Verifique se o ID existe
- ✅ Tente listar e copiar um ID válido

### Erro: "Validação falhou" (422)
- ✅ Verifique os tipos de dados
- ✅ Veja a resposta para detalhes dos campos inválidos

### Erro: "Rate limit excedido" (429)
- ✅ Aguarde alguns minutos antes de fazer nova requisição
- ✅ Reduza a frequência de requisições

## Usando cURL (Alternativa)

Se preferir usar linha de comando:

### Autenticação
```bash
curl -c cookies.txt -X POST http://localhost/login \
  -d "email=user@example.com&password=senha"
```

### Listar Câmeras
```bash
curl -b cookies.txt http://localhost/api/v2/cameras?page=1&per_page=50
```

### Criar Câmera
```bash
curl -b cookies.txt -X POST http://localhost/api/v2/cameras \
  -H "Content-Type: application/json" \
  -d '{
    "ip": "192.168.1.100",
    "numero_serie": "CAM123456",
    "local_id": 1,
    "modelo_id": 5
  }'
```

### Upload de Arquivo
```bash
curl -b cookies.txt -F "file=@foto.jpg" \
  -F "equipamento_id=123" \
  http://localhost/api/v2/upload_anexo
```

## Suporte Adicional

- 📧 Email: api-support@example.com
- 🐛 Reporte bugs: issues@example.com
- 📚 Documentação técnica: /docs/API_REFERENCE.md

---

**Última atualização**: 2024
**Versão da API**: 2.0.0
