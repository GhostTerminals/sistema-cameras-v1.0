-- =====================================================
-- MIGRATION 001: FULLTEXT INDEXES + RATE LIMIT TABLE
-- =====================================================

-- Tabela para rate limiting via banco (substitui storage em arquivo)
CREATE TABLE IF NOT EXISTS rate_limits (
    `key` VARCHAR(64) NOT NULL PRIMARY KEY,
    `tokens` DECIMAL(10,4) NOT NULL DEFAULT 0,
    `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='Rate limiting via sliding window / token bucket';
-- Melhora performance de buscas LIKE '%...%' nas
-- tabelas mais consultadas pelo sistema.
-- Aplicar via: mysql -u root -p cftv_gml < 001_fulltext_indexes.sql
-- Rollback: DROP INDEX <nome> ON <tabela>;
-- =====================================================

-- Índices FULLTEXT para buscas nos locais
ALTER TABLE locais ADD FULLTEXT INDEX idx_locais_busca_fulltext (nome, logradouro, bairro);

-- Índices FULLTEXT para catálogo de modelos
ALTER TABLE catalogo_modelos ADD FULLTEXT INDEX idx_catalogo_modelos_busca_fulltext (nome);

-- Índices FULLTEXT para secretarias
ALTER TABLE secretarias ADD FULLTEXT INDEX idx_secretarias_busca_fulltext (nome);

-- Índices FULLTEXT para equipamentos (campos de texto livre)
ALTER TABLE equipamentos ADD FULLTEXT INDEX idx_equipamentos_busca_fulltext (patrimonio, numero_serie);

-- Índices FULLTEXT para usuários (busca administrativa)
ALTER TABLE usuarios ADD FULLTEXT INDEX idx_usuarios_busca_fulltext (nome, usuario);

-- =====================================================
-- NOTAS:
-- 1. FULLTEXT só funciona com engine InnoDB (padrão do projeto)
-- 2. Mínimo de 3 caracteres para tokens (configurável via
--    innodb_ft_min_token_size)
-- 3. IPs não são indexados com FULLTEXT pois os pontos
--    são separadores de tokens; usar o índice B-tree
--    existente idx_equip_ip e buscar com prefixo
-- 4. Para consultas FULLTEXT, usar MATCH(colunas) AGAINST
--    em vez de LIKE '%termo%'
-- =====================================================
