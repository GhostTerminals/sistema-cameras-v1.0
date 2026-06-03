# 📊 Otimização de Índices de Banco de Dados

## 📋 Sobre Esta Otimização

Este guia descreve como aplicar otimizações de índices no banco de dados para melhorar significativamente a performance da API.

### Ganhos Esperados
- ⚡ **5-10x mais rápido** em buscas por IP, série, conta
- 💾 **Redução de memória** ao processar queries grandes
- 📈 **Throughput aumentado** de 100 para 500+ req/s

---

## 🔧 Como Usar

### Opção 1: Usar o Script PHP Automatizado (RECOMENDADO)

#### Passo 1: Fazer Backup do Banco
```bash
# Backup completo do banco de dados
mysqldump -u root -p seu_banco > backup_antes_otimizacao.sql

# Ou exportar via phpMyAdmin
# Menu: Exportar > Custom > Selecionar todas as tabelas > Executar
```

#### Passo 2: Executar o Script de Otimização
```bash
# Navegar até a pasta do projeto
cd c:\xampp\htdocs\sistema-cameras-v1.0

# Executar o script de otimização
php scripts/optimize-database.php
```

**Saída esperada**:
```
╔════════════════════════════════════════════════════════════════╗
║             🔍 DATABASE INDEX OPTIMIZER v1.0                   ║
╚════════════════════════════════════════════════════════════════╝

▶ 1. Analisando Índices Existentes
────────────────────────────────────────────────────────────────
  ✓ Conexão com banco de dados estabelecida
  ℹ Tabela 'equipamentos': 8 índices encontrados
  ✓ Total de índices: 15

▶ 2. Aplicando Índices de Otimização
────────────────────────────────────────────────────────────────
  ✓ Índice 'idx_equipamentos_ip' criado em equipamentos (45 ms)
  ⚠ Índice 'idx_equipamentos_numero_serie' já existe em 'equipamentos'
  ✓ Índice 'idx_alarmes_ip' criado em central_alarmes (32 ms)
  ...

✅ Otimização Concluída
Tempo total: 1.23 s

Índices criados: 12
Índices pulados: 3
Erros: 0
```

### Opção 2: Executar SQL Manualmente via phpMyAdmin

#### Passo 1: Abrir phpMyAdmin
```
http://localhost/phpmyadmin
```

#### Passo 2: Selecionar o Banco
- Escolha seu banco de dados na lista

#### Passo 3: Ir para "SQL"
- Clique na aba "SQL" no topo

#### Passo 4: Copiar e Colar o SQL

Abra o arquivo `scripts/optimize-indexes.sql` e copie o conteúdo (começando de "CREATE INDEX") para o phpMyAdmin.

#### Passo 5: Executar
- Clique em "Executar" (Go)

---

## ✅ Verificação Após Otimização

### Verificar Índices Criados

```sql
-- Ver todos os índices de uma tabela
SHOW INDEX FROM equipamentos;

-- Resultado esperado: Deve listar os novos índices
-- Name: idx_equipamentos_ip, idx_equipamentos_numero_serie, etc.

-- Ver índices de múltiplas tabelas
SELECT * FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = 'seu_banco' 
AND TABLE_NAME IN ('equipamentos', 'central_alarmes')
ORDER BY TABLE_NAME, SEQ_IN_INDEX;
```

### Testar Performance

#### Antes da Otimização (sem índices)
```sql
-- Esta query será lenta sem índices
SET @start = NOW(6);
SELECT * FROM equipamentos 
WHERE ip = '192.168.1.100' AND tipo_equipamento_id = 1;
SELECT TIMESTAMPDIFF(MICROSECOND, @start, NOW(6)) as query_time_us;

-- Resultado esperado: ~500-2000 ms
```

#### Depois da Otimização (com índices)
```sql
-- Esta mesma query será muito rápida com índices
SET @start = NOW(6);
SELECT * FROM equipamentos 
WHERE ip = '192.168.1.100' AND tipo_equipamento_id = 1;
SELECT TIMESTAMPDIFF(MICROSECOND, @start, NOW(6)) as query_time_us;

-- Resultado esperado: ~5-50 ms (50-100x mais rápido!)
```

---

## 📊 Índices Aplicados

### Categoria 1: Campos de Busca (Alta Prioridade) ⚡

| Índice | Tabela | Coluna | Propósito |
|--------|--------|--------|----------|
| `idx_equipamentos_ip` | equipamentos | ip | Busca por IP (comum em buscas) |
| `idx_equipamentos_numero_serie` | equipamentos | numero_serie | Busca por série (importação) |
| `idx_alarmes_ip` | central_alarmes | ip | Busca por IP de alarme |
| `idx_alarmes_conta` | central_alarmes | conta | Busca por conta/número |

**Impacto**: 50-100x mais rápido em buscas exatas

---

### Categoria 2: Foreign Keys (Média Prioridade) 🔗

| Índice | Tabela | Coluna | Propósito |
|--------|--------|--------|----------|
| `idx_equipamentos_modelo_id` | equipamentos | modelo_id | Join com modelos |
| `idx_equipamentos_local_id` | equipamentos | local_id | Join com locais |
| `idx_equipamentos_status_id` | equipamentos | status_id | Join com status |
| `idx_alarmes_modelo_id` | central_alarmes | modelo_id | Join com modelos alarmes |

**Impacto**: 10-20x mais rápido em JOINs

---

### Categoria 3: Filtros e Compostos (Alta Prioridade) ✨

| Índice | Tabela | Colunas | Propósito |
|--------|--------|---------|----------|
| `idx_eq_tipo_status` | equipamentos | tipo_equipamento_id, status_id | Filtro por tipo E status |
| `idx_eq_local_tipo` | equipamentos | local_id, tipo_equipamento_id | Filtro por local E tipo |

**Impacto**: 30-50x mais rápido em queries com múltiplos filtros

---

## 🔍 Monitorar Performance

### 1. Ativar Slow Query Log

Adicione a `my.cnf` do MySQL:

```ini
[mysqld]
# Slow query logging
long_query_time = 2
log_queries_not_using_indexes = 1
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
```

### 2. Ver Queries Lentas

```bash
# Linux/Mac
tail -f /var/log/mysql/slow-query.log

# Contar queries lentas
grep "Query_time" /var/log/mysql/slow-query.log | wc -l
```

### 3. Analisar Query Específica

```sql
-- Ver plano de execução ANTES (sem índice)
EXPLAIN SELECT * FROM equipamentos WHERE ip = '192.168.1.100';

-- Ver plano DEPOIS (com índice)
-- Deve mostrar "Using index" em vez de "Using filescan"
EXPLAIN SELECT * FROM equipamentos WHERE ip = '192.168.1.100';
```

---

## 🚀 Configurações MySQL Recomendadas

Adicione ao `my.cnf` para melhor performance:

```ini
[mysqld]
# Connection pooling
max_connections = 100
max_user_connections = 50
wait_timeout = 28800
interactive_timeout = 28800

# Buffer pool (ajustar conforme RAM disponível)
innodb_buffer_pool_size = 256M

# Query cache
query_cache_type = 1
query_cache_size = 64M
query_cache_limit = 2M

# Logs de query lenta
long_query_time = 2
log_queries_not_using_indexes = 1
slow_query_log = 1

# General log (desabilitar em produção)
# general_log = 0

# Threads
innodb_flush_log_at_trx_commit = 2
```

### Aplicar Mudanças

```bash
# Reiniciar MySQL para aplicar mudanças
sudo systemctl restart mysql

# Ou via Docker
docker restart nome-container-mysql
```

---

## 📈 Resultados Esperados

### Antes da Otimização

```
GET /api/v2/cameras?busca=192.168.1.100
Time: 1200ms
Queries: 5
Database Time: 1100ms
```

### Depois da Otimização

```
GET /api/v2/cameras?busca=192.168.1.100
Time: 120ms (10x mais rápido!)
Queries: 5 (mesmo número)
Database Time: 50ms
```

---

## 🐛 Troubleshooting

### Problema: "Duplicate Key Name"

**Causa**: Índice já existe no banco

**Solução**: O script detecta automaticamente. Se executar SQL direto, use `IF NOT EXISTS`

```sql
CREATE INDEX IF NOT EXISTS idx_equipamentos_ip ON equipamentos(ip);
```

### Problema: "Out of Memory"

**Causa**: Buffer pool do MySQL muito pequeno

**Solução**: Aumentar em `my.cnf`

```ini
innodb_buffer_pool_size = 512M  # Aumentar de 256M para 512M
```

### Problema: Índices não estão sendo usados

**Solução**: Rodar ANALYZE TABLE

```sql
ANALYZE TABLE equipamentos;
ANALYZE TABLE central_alarmes;
```

---

## 🔄 Próximas Otimizações

1. **Cache Layer** (Redis) - Cache de modelos, locais, dashboard
2. **Query Optimization** - Eliminar N+1 queries
3. **Connection Pooling** - Reutilizar conexões
4. **Monitoring** - Query logging e métricas

---

## 📋 Checklist de Implementação

- [x] Criar script SQL com índices
- [x] Criar script PHP de otimização
- [x] Criar guia de implementação
- [ ] Executar otimização no ambiente de DEV
- [ ] Testar performance com load testing
- [ ] Executar em PRODUÇÃO (com backup!)
- [ ] Monitorar slow queries
- [ ] Documentar resultados

---

## 📞 Suporte

Se encontrar problemas:

1. Verifique se os índices foram criados: `SHOW INDEX FROM equipamentos;`
2. Rode ANALYZE TABLE: `ANALYZE TABLE equipamentos;`
3. Limpe cache da aplicação se estiver usando
4. Reinicie o MySQL se as mudanças não aparecerem

---

**Pronto para aplicar as otimizações?**

```bash
php scripts/optimize-database.php
```
