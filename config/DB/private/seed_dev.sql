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
('Administrador Sistema','admin','$2y$10$VIFP3Z9OtR.TDWXWXaEViuuIwBjtH3BpUeK23519vDVaRpvBpc6li',1,1),
('Supervisor Sistema','supervisor','$2y$10$VIFP3Z9OtR.TDWXWXaEViuuIwBjtH3BpUeK23519vDVaRpvBpc6li',2,1),
('User Sistema','user','$2y$10$VIFP3Z9OtR.TDWXWXaEViuuIwBjtH3BpUeK23519vDVaRpvBpc6li',3,1);

-- Senha temporais em ambiente de desenvolvimento, devem ser alteradas no primeiro acesso
