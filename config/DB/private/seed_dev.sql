-- =====================================================
-- SEED PARA AMBIENTE DE DESENVOLVIMENTO APENAS
-- =====================================================
-- ATENCAO: Nao utilizar em producao!
-- Os hashes abaixo sao bcrypt de senhas conhecidas e publicas.
-- Em producao, crie os usuarios manualmente ou via script
-- com senhas fortes e aleatorias.
-- =====================================================


DROP USER IF EXISTS 'gmluser'@'localhost';
CREATE USER 'gmluser'@'localhost' IDENTIFIED BY 'ykB]Af4|2kHPU#;1g+?J3z)P]=p|AX=R';
GRANT ALL PRIVILEGES ON cftv_gml.* TO 'gmluser'@'localhost';
FLUSH PRIVILEGES;

INSERT INTO usuarios (nome, usuario, senha, nivel_acesso_id, senha_temporaria) VALUES
('Administrador Sistema','admin','$2y$10$WLFdgPpyVeQglDo2wggnDeFBb5EfoZ8UOyBziquqvXCUlM3VqNbzC',1,1),
('User Sistema','user','$2y$10$NmG.JfubmdUpWGlLpksWfuzc913CobhE6xDKaEB6ABk26GOehWLwm',3,1),
('Supervisor Sistema','supervisor','$2y$10$wSoUtQf9losEWf8llaZddu1hPY3lc0Qzbzqw4K6Y2MPeWg7L9IhAe',2,1);

