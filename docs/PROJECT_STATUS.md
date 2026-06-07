# APP Teresa Surita - Status do Projeto

Atualizado em: 2026-06-07

## Repositório

- GitHub: `welinkosolutions/app-teresa-surita`
- Branch principal: `main`
- Projeto em produção/ambiente atual: `https://app.elab.social`

## Regra principal

Este projeto é separado dos demais projetos Welinko. Não misturar com CRM Romero Jucá, Welinko Core, Cloud da Welinko ou outros projetos.

## Estado geral

Estamos finalizando o aplicativo da Teresa Surita, com foco em:

- Home/Dashboard V3 gamificada.
- Comunidade com feed social e botões abrindo posts corretamente.
- Perfil e Ranking como referências visuais.
- Área Interna V2 para líderes e administradores.
- Footer V2 compartilhado entre as páginas principais.

## Arquivos principais em trabalho

- `dashboard/index.php`
- `comunidade/social.php`
- `comunidade/ranking.php`
- `pessoas/perfil.php`
- `interno/admin.php`
- `assets/footer/menu.php`
- `assets/css/footer-v2.css`
- APIs de feed em `api/feed/*.php`

## Área Interna V2

Criada a página:

```txt
/interno/admin.php
```

Diretriz:

- Não é dashboard.
- É painel operacional para líderes e administradores.
- Deve ser uma tela enxuta de trabalho.
- O botão Admin no footer leva para esta área.

Módulos/atalhos previstos ou implementados:

- Demandas Registradas.
- Registrar Nova Demanda.
- Minha Equipe.
- Lista de Cadastrados.
- Desempenho da Equipe.

O menu antigo custom da página interna foi substituído pelo footer compartilhado:

```php
/assets/footer/menu.php
/assets/css/footer-v2.css
```

## Redirecionamentos internos

As páginas internas de liderança/exclusivo foram ajustadas para voltar para:

```txt
/interno/admin.php
```

em vez de:

```txt
/dashboard/index.php
```

Áreas afetadas:

- `lideranca/*.php`
- `exclusivo/*.php`
- `interno/*.php`

A validação feita no terminal indicou que não havia mais referências a `/dashboard/index.php` nesses diretórios após o ajuste.

## Comunidade / Feed Social

Arquivo principal:

```txt
comunidade/social.php
```

Contexto:

- O feed V2 já está em uso com footer flutuante.
- Os cards foram compactados em estilo timeline.
- Os botões de ação estavam soltando confete e toast, mas alguns não abriam corretamente o post original.

Pontos técnicos analisados:

```js
postUrlForItem()
appDeepLinkForItem()
actionElement()
.js-feed-action
.js-open-social-post
```

Problema observado:

- Facebook abriu melhor após ajustes.
- Instagram abria o app/site, mas nem sempre abria o post correto.
- Para itens sem `link_url`, o correto é não exibir ação de abrir post no feed.

Diretriz definida:

```txt
Se tem link_url, botão abre o post.
Se não tem link_url, não aparece no feed.
```

Pendência técnica:

- Revisar `api/feed/todos.php`, `api/feed/amigos.php`, `api/feed/novos.php` e a view `vw_comunidade_feed_social_events` para garantir `link_url` real em Facebook e Instagram.
- Evitar deep link quebrado quando já houver permalink real.
- O botão com link deve abrir primeiro o `webUrl`/permalink real.

## Banco / Posts Instagram e Facebook

Tabelas analisadas:

- `metaverso_posts`
- `social_events`

Views analisadas:

- `vw_comunidade_feed_unificado`
- `vw_comunidade_feed_social_events`
- `vw_comunidade_feed_metaverso`

Conclusão atual:

- `metaverso_posts` tem permalinks válidos para posts recentes do Instagram até `2026-06-02`.
- Comentários recentes do Instagram só devem aparecer se conseguirem join com `metaverso_posts` e tiverem `permalink`.
- Facebook pode montar fallback de permalink pelo `object_id` no formato `pagina_post`.

Cron/metaverso:

- Havia crons de analytics pausadas com prefixo `PAUSADO_META_ORGANIZACAO`.
- Foi criado wrapper:

```txt
/home/elab/metaverso_analytics_instagram.sh
```

- Cron final configurada:

```cron
*/10 * * * * /usr/bin/flock -n /tmp/metaverso_analytics_instagram.lock /home/elab/metaverso_analytics_instagram.sh
```

Atenção: o crontab estava corrompendo o espaço entre `cron.php` e `--run`, por isso foi usado wrapper `.sh`.

## Home / Dashboard V3

Arquivo:

```txt
dashboard/index.php
```

Objetivo:

Transformar a Home antiga em uma Home V3 gamificada, visualmente mais próxima de:

- `pessoas/perfil.php`
- `comunidade/ranking.php`

Referências visuais aprovadas:

- Perfil: hero lúdico, cards arredondados, progressão, medalhas/conquistas.
- Ranking: topo dark premium, pódio, gamificação forte, profundidade visual.

### Decisões de UX

Remover da Home:

- `Seu impacto hoje` / card de narrativa de engajamento.
- `Melhores da Semana`.
- Cards `Pessoas`.
- Card `Redes Sociais`.
- Card de convite/rede do tipo `Ei, João Felipe`, com estatísticas de rede, pendentes e botão Ver Rede.

Manter/Redesenhar:

- Header com saudação, nome, pontos e posição no ranking.
- Hero visual.
- Missão do dia como card principal clicável inteiro.
- Compartilhar seu link.
- Footer V2.

### Estado atual da Home V3

Foi aplicado override CSS/JS no final de `dashboard/index.php` com marcador:

```css
/* HOME V2 SAFE OVERRIDE */
```

Esse bloco foi duplicado uma vez, depois limpo. Atualmente deve existir apenas uma ocorrência.

Validação já feita:

```bash
php -l dashboard/index.php
```

Resultado esperado/observado:

```txt
No syntax errors detected in dashboard/index.php
```

### Visual atual da Home

Depois do último ajuste, a Home ficou com:

- Header dark premium.
- Hero recortado/cinematográfico.
- Missão dark premium.
- Compartilhar link.
- Footer V2.

Problema atual pendente:

Ainda aparece o bloco antigo:

```txt
Redes Sociais
CLIQUE PARA ABRIR
```

na parte inferior da Home.

Próxima tarefa exata:

1. Localizar no `dashboard/index.php` o bloco PHP/HTML que gera `Redes Sociais`.
2. Remover definitivamente esse bloco do código fonte, não apenas esconder por CSS.
3. Validar com:

```bash
php -l dashboard/index.php
```

4. Testar no celular.

Possíveis termos para busca:

```bash
grep -n "Redes Sociais\|monitor-redes\|cardMonitorRedes\|Monitor" dashboard/index.php
```

## Segurança e disciplina

- Evitar patches agressivos por regex ampla em blocos PHP grandes.
- Sempre fazer backup antes de alterar:

```bash
cp -av arquivo.php arquivo.php.bak_nome_$(date +%Y%m%d_%H%M%S)
```

- Sempre validar PHP:

```bash
php -l arquivo.php
```

- Não misturar com outros projetos.
- Não commitar segredos, `.env`, logs, backups, uploads ou dumps SQL.

## Como retomar

No próximo chat, iniciar com:

```txt
Projeto APP Teresa Surita. Retomar Home V3. Precisamos localizar e remover definitivamente o card "Redes Sociais" que ainda aparece na dashboard/index.php. Já existe Área Interna V2 funcionando em /interno/admin.php e footer V2 compartilhado. Continuar a partir do PROJECT_STATUS.md.
```
