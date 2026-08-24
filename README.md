# SlideRemote

Controle uma apresentação exibida na **lousa digital** usando o **celular**
como controle remoto — pela rede, sem nenhum hardware apontador.

Na lousa, abra `apresentar.php`, informe o link do Google Slides/Drive
(ou envie um PDF) e conecte o celular pelo **QR Code** ou pelo **código de
4 dígitos**. Do celular você avança e retrocede slides, ativa a tela preta,
pula direto para um slide pela grade de miniaturas e acompanha o contador
e o cronômetro.

- **Stack**: PHP 8.2+ e MySQL, HTML/CSS/JavaScript puro (sem Node, sem build).
- **Bibliotecas**: PDF.js e QRCode.js, carregadas por CDN.
- **Hospedagem-alvo**: HostGator compartilhado (ou qualquer cPanel), sem terminal.

## Estrutura de arquivos

```
slideremote/
├── apresentar.php     ← tela da lousa digital
├── controle.php       ← tela do celular (controle remoto)
├── proxy_pdf.php      ← baixa a apresentação do Google e serve o PDF
├── config.php         ← credenciais do banco (ÚNICO arquivo a editar)
├── schema.sql         ← estrutura do banco (importar no phpMyAdmin)
├── .htaccess          ← proteções básicas
├── api/
│   ├── sessao.php     ← cria a sessão e devolve o código
│   ├── estado.php     ← estado atual (consultado a cada 500 ms)
│   └── comando.php    ← próximo/anterior/ir para/tela preta/encerrar
├── assets/
│   ├── estilo.css
│   └── app.js
└── cache/             ← PDFs baixados/enviados (bloqueada ao público)
```

## Instalação pelo cPanel (HostGator)

### 1. Criar o banco de dados

1. No cPanel, abra **MySQL® Databases** (Bancos de Dados MySQL).
2. Em *Create New Database*, crie um banco — por exemplo `slideremote`.
   O nome final ficará com o prefixo da sua conta: `minhaconta_slideremote`.
3. Em *MySQL Users → Add New User*, crie um usuário (ex.: `slideremote`)
   com uma senha forte. Anote a senha.
4. Em *Add User To Database*, associe o usuário ao banco e marque
   **ALL PRIVILEGES** (todos os privilégios) → *Make Changes*.

### 2. Importar o `schema.sql`

1. No cPanel, abra o **phpMyAdmin**.
2. No menu da esquerda, clique no banco criado (`minhaconta_slideremote`).
3. Aba **Importar** → *Escolher arquivo* → selecione o `schema.sql` →
   botão **Executar**.
4. Deve aparecer a tabela `sessoes` no banco. Pronto.

### 3. Editar o `config.php`

Abra o `config.php` (pode ser no Bloco de Notas, antes de enviar) e preencha
as quatro linhas do topo com os dados do passo 1:

```php
define('BD_SERVIDOR', 'localhost');                    // no HostGator é localhost mesmo
define('BD_NOME',     'minhaconta_slideremote');
define('BD_USUARIO',  'minhaconta_slideremote');
define('BD_SENHA',    'a-senha-que-voce-criou');
```

### 4. Enviar os arquivos

1. No cPanel, abra o **Gerenciador de Arquivos** (File Manager) e entre em
   `public_html` (ou na pasta do subdomínio que preferir).
2. Crie uma pasta `slideremote` e entre nela.
3. Use **Upload** para enviar os arquivos. Dica: compacte tudo em um `.zip`
   no computador, envie o zip e use **Extract** no Gerenciador — mantém a
   estrutura de pastas (`api/`, `assets/`, `cache/`).
4. Confirme que a pasta `cache/` existe e tem permissão `755`
   (botão direito → *Change Permissions*). No HostGator o PHP roda com o
   seu próprio usuário, então `755` já permite a escrita.

### 5. Conferir a versão do PHP e a extensão cURL

1. No cPanel, abra **Select PHP Version** (Selecionar versão do PHP).
2. Escolha **PHP 8.2** (ou mais novo).
3. Na lista de extensões, confirme que **curl**, **pdo_mysql** e
   **fileinfo** estão marcadas (normalmente já vêm ativas).

### 6. Testar

Acesse `https://seudominio.com.br/slideremote/apresentar.php`. Se aparecer a
tela "SlideRemote — Controle a apresentação da lousa pelo seu celular", a
instalação está de pé.

## Como compartilhar a apresentação no Google Drive

Para a lousa conseguir baixar a apresentação, ela precisa estar visível para
"qualquer pessoa com o link":

1. Abra a apresentação no **Google Slides** (ou o PDF no **Drive**).
2. Clique em **Compartilhar** (botão azul, canto superior direito).
3. Em **Acesso geral**, troque de "Restrito" para
   **"Qualquer pessoa com o link"** — papel **Leitor** já basta.
4. Clique em **Copiar link** e depois em **Concluído**.
5. É esse link que você cola na tela da lousa.

> **Importante**: o link de *publicar na web* (Arquivo → Compartilhar →
> Publicar na web, que contém `/d/e/` no endereço) **não funciona** para
> exportar o PDF. Use sempre o botão **Compartilhar → Copiar link**.

## Como usar no dia a dia

1. **Na lousa**: abra `apresentar.php`, cole o link e toque em **Apresentar**.
2. Aguarde a barra de progresso preparar todos os slides.
3. **No celular**: aponte a câmera para o QR Code (abre `controle.php` já
   conectado) ou acesse `controle.php` e digite o código de 4 dígitos.
4. Toque na tela da lousa uma vez para entrar em **tela cheia**.
5. Controle pelo celular: **Próximo / Anterior**, **Tela preta**,
   **Ir para slide** (grade), contador e cronômetro (toque no cronômetro
   para zerar). O celular vibra a cada comando e a tela não apaga
   (em aparelhos com suporte a Wake Lock).
6. Ao terminar, **Encerrar** no celular fecha a sessão nas duas telas.

Se a conexão do celular cair, a lousa **mantém o slide atual** — nada volta
ao início. As setas do teclado da lousa (◀ ▶) seguem funcionando como
reserva, além de **B** (tela preta) e **C** (mostrar o código de novo).

## Teste antes do dia da apresentação

Faça este ensaio **na rede da escola**, alguns dias antes:

- [ ] Abra `apresentar.php` na própria lousa digital e cole o link real da
      sua apresentação. Ela baixou e mostrou o primeiro slide?
- [ ] Conecte o seu celular pelo QR Code **na rede Wi-Fi da escola** (e
      também pelo 4G, como reserva). O indicador no canto da lousa ficou
      verde?
- [ ] Avance e volte alguns slides. A troca acontece em menos de 1 segundo?
- [ ] Teste a **tela preta** e a grade **Ir para slide**.
- [ ] Deixe o celular bloqueado por 1 minuto, desbloqueie e confira se o
      controle continua respondendo.
- [ ] **Plano B**: baixe a apresentação em PDF (Arquivo → Fazer download →
      PDF no Google Slides), guarde no celular/pendrive e teste o botão
      **Enviar um arquivo PDF** — para o caso de o Drive estar bloqueado na
      rede no dia.
- [ ] Se algo falhar, veja a seção abaixo.

## Problemas comuns

| Mensagem / sintoma | O que fazer |
|---|---|
| "A apresentação não está compartilhada publicamente" | Refaça o passo a passo de compartilhamento acima ("Qualquer pessoa com o link"). Ou use o plano B (enviar PDF). |
| "Este é um link de publicação na web (contém /d/e/)" | Você copiou o link de *Publicar na web*. Use **Compartilhar → Copiar link**. |
| "Não foi possível reconhecer este link" | Cole o link completo do Slides/Drive, ou apenas o ID do arquivo. |
| "Tempo esgotado ao baixar a apresentação" | Rede lenta ou arquivo grande. Tente de novo; persiste? Use o plano B (PDF). |
| "Não foi possível conectar ao banco de dados" | Revise as 4 linhas do `config.php` (nome do banco e do usuário levam o prefixo da conta). |
| "A extensão cURL do PHP não está habilitada" | cPanel → *Select PHP Version* → marque **curl**. |
| "Não foi possível gravar na pasta cache/" | Confira se a pasta `cache/` foi enviada e está com permissão 755. |
| Código de 4 dígitos não aceito no celular | O código muda a cada sessão — use o que está na lousa **agora**. Sessões paradas há mais de 6 h expiram. |
| Celular vibrou mas o slide não trocou | Veja o pontinho no cabeçalho do celular: vermelho = sem internet no celular. A lousa mantém o slide atual até a conexão voltar. |
| A tela do celular apaga durante a aula | Aparelhos sem Wake Lock: aumente o tempo de bloqueio de tela nas configurações do Android/iOS. |

## Notas de segurança

- Toda consulta ao MySQL usa **prepared statements**; toda saída passa por
  escape; o `FILE_ID` é validado por regex antes de qualquer uso e nunca é
  usado bruto em caminhos de arquivo.
- A pasta `cache/` e o `schema.sql` são bloqueados por `.htaccess`.
- Os PDFs ficam em cache no servidor (renovado a cada 72 h; uploads por
  24 h) e as sessões expiram sozinhas após 6 h de inatividade — a limpeza
  acontece no próprio código, sem cron.
- Não há login: quem tiver o código de 4 dígitos da sessão **ativa** pode
  controlar a apresentação. O código é sorteado a cada sessão e morre com ela.
