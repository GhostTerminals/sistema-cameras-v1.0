-- Adiciona regiao_id e horario_funcionamento a tabela locais
ALTER TABLE locais
    ADD COLUMN regiao_id INT NULL AFTER secretaria_id,
    ADD COLUMN horario_funcionamento VARCHAR(255) NULL AFTER regiao_id,
    ADD INDEX idx_locais_regiao (regiao_id),
    ADD CONSTRAINT fk_locais_regiao FOREIGN KEY (regiao_id) REFERENCES regioes(id) ON DELETE SET NULL;
