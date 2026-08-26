-- ---------------------------------------------------------------------------
-- SlideRemote — Estrutura do banco de dados
--
-- Como importar pelo cPanel:
--   1. Crie o banco e o usuário em "MySQL Databases" e associe o usuário
--      ao banco com todos os privilégios.
--   2. Abra o phpMyAdmin, selecione o banco criado no menu lateral.
--   3. Vá na aba "Importar", escolha este arquivo (schema.sql) e clique
--      em "Executar".
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS sessoes (
    id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,

    -- Código de pareamento mostrado na lousa e digitado no celular.
    -- É único apenas entre as sessões ATIVAS (controlado pela aplicação),
    -- por isso não há índice UNIQUE aqui.
    codigo         CHAR(4)           NOT NULL,

    -- ID do arquivo no Google Drive (NULL quando a origem for upload de PDF).
    file_id        VARCHAR(128)      DEFAULT NULL,

    -- De onde veio a apresentação.
    origem         ENUM('drive', 'upload') NOT NULL DEFAULT 'drive',

    -- Estado da apresentação.
    total_slides   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    slide_atual    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    blackout       TINYINT(1)        NOT NULL DEFAULT 0,

    -- Trava de toque da lousa: quando ligada, toques e teclas na própria
    -- lousa não trocam de slide (só o celular comanda).
    lousa_travada  TINYINT(1)        NOT NULL DEFAULT 0,

    -- Última vez que o celular consultou/comandou esta sessão.
    -- Alimenta o indicador "celular pareado" na lousa.
    controle_visto_em DATETIME       DEFAULT NULL,

    -- Controle de ciclo de vida.
    criada_em      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizada_em  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,
    ativa          TINYINT(1)        NOT NULL DEFAULT 1,

    PRIMARY KEY (id),

    -- Busca principal: localizar a sessão ativa a partir do código.
    KEY idx_codigo_ativa (codigo, ativa),

    -- Limpeza de sessões antigas (feita na criação de novas sessões).
    KEY idx_ativa_atualizada (ativa, atualizada_em)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
