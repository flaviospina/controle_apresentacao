-- ---------------------------------------------------------------------------
-- SlideRemote — Atualização v2 (trava da lousa + caneta laser)
--
-- Use este arquivo APENAS se você já tinha o SlideRemote instalado antes
-- desta versão. Instalações novas devem importar somente o schema.sql.
--
-- Como aplicar pelo cPanel:
--   1. Abra o phpMyAdmin e selecione o banco do SlideRemote.
--   2. Aba "Importar" → escolha este arquivo → "Executar".
--      (ou cole o comando abaixo na aba "SQL")
-- ---------------------------------------------------------------------------

ALTER TABLE sessoes
    ADD COLUMN lousa_travada TINYINT(1) NOT NULL DEFAULT 0 AFTER blackout;

-- A caneta laser não usa o banco de dados (a posição passa por um arquivo
-- temporário na pasta cache/), então este ALTER é a única mudança.
