# Análise Técnica - V1 e Camada Game V2

## Fonte

Levantamento feito via terminal no servidor em `/home/elab`, analisando principalmente:

- `/home/elab/app.elab.social`
- `/home/elab/public_html/core`
- `/home/elab/schema-elab-roraima.sql`
- `/home/elab/colunas-elab.txt`
- backups específicos de `game_*`
- consulta direta ao banco via `db()` para `SHOW CREATE TABLE`

Este documento evita registrar credenciais, tokens, secrets ou dados pessoais reais.

---

## 1. Arquitetura geral encontrada

A V1 não é apenas a pasta do app. Ela depende fortemente de um core compartilhado.

```text
/home/elab/app.elab.social
/home/elab/public_html/core
```

### `/home/elab/app.elab.social`

Camada de produto/telas/endpoints locais.

Módulos observados:

- `api`
- `dashboard`
- `lideranca`
- `comunidade`
- `pessoas`
- `inicial`
- `publico`
- `endpoint`
- `invite`
- `perfil`
- `engajamento`
- `rede`
- `game`

### `/home/elab/public_html/core`

Camada de plataforma compartilhada.

Módulos observados:

- `data`
- `sessao`
- `tenant`
- `invite`
- `gamificacao`
- `missao`
- `games`
- `push`
- `meta`
- `metaverso`
- `social`
- `chatpro`
- `oratrix`
- `oratrixchat`
- `salvy`
- `google`
- `tse`
- `pam`

---

## 2. Stack observada

No ambiente da V1:

```text
PHP 8.2.31
Composer 2.9.7
Node v20.20.2
npm 10.8.2
```

`composer.json` observado:

```json
{
  "name": "teresappcom/public_html",
  "require": {
    "phpmailer/phpmailer": "^7.0",
    "guzzlehttp/guzzle": "^7.10",
    "minishlink/web-push": "^9.0"
  }
}
```

Não foi observado framework full-stack como Laravel ou Symfony no primeiro inventário.

---

## 3. Entidade central confirmada

A tabela mais referenciada no app e no core é:

```text
pessoas
```

Isso confirma a conclusão visual dos prints: Pessoa é a entidade central do produto.

### Campos relevantes de `pessoas`

```text
id
nome
nome_busca
apelido
chamar_por
sexo
nome_mae
data_nascimento
telefone
telefone_confirmado
email
pin
pin_tentativas
pin_bloqueado_em
token
token_expira_em
instagram
instagram_user
instagram_username
instagram_confirmado
instagram_followers
facebook
facebook_user_id
facebook_username
facebook_confirmado
latitude
longitude
cidade_votacao
estado_votacao
ponto_referencia
local_trabalho
transporte
convite_hash
convite_hash_criado_em
convite_hash_rotacionado_em
convite_liberado_em
pode_convidar
quarentena_convite
risco_convite
status
status_validacao
perfil
vinculo
biometria
online_status
online_desde
ultimo_ping
ultimo_movimento
ultima_velocidade
whatsapp_aceite
whatsapp_grupo_link
whatsapp_grupo_oficial
criado_em
atualizado_em
criado_por
```

### Endereço separado

Tabela:

```text
pessoas_enderecos
```

Campos:

```text
pessoa_id
endereco
numero
complemento
bairro
cidade
estado
cep
referencia
latitude
longitude
tipo
```

---

## 4. Rede e convites

Tabelas encontradas:

```text
rede_indicacoes
convites
convites_aprovacoes
convites_compartilhamentos
convites_links_cliques
convites_links_publicos
```

### `rede_indicacoes`

Campos:

```text
id
indicador_id
indicado_id
nivel
origem
criado_em
```

### Convites possuem controle de segurança

Campos observados em convites/aprovações:

```text
convidador_id
convidado_id
codigo_convite_publico
slug_curto
url_curta
token
token_hash_usado
status
score_risco
flags_risco
captcha_ok
bloqueado_automacao
ip_cadastro
user_agent
expira_em
aprovado_em
recusado_em
motivo_recusa
motivo_risco
```

Conclusão: o fluxo de convite tem mecanismos de rastreio, risco e aprovação.

---

## 5. Demandas

Demandas existem na V1 e devem ficar no Admin na V2, conforme decisão de produto informada.

Tabelas encontradas:

```text
demandas
demandas_eventos
demandas_responsaveis
demandas_respostas
demandas_midias
demandas_visitas
demandas_draft
controle_sequencial_demandas
```

Campos relevantes de `demandas`:

```text
id
protocolo
sequencial_global
titulo
descricao
categoria
prioridade
status
sla_status
prazo_limite
pessoa_id
demandante_id
responsavel_id
transferido_para_id
criado_por
autor_acao_id
importante_visitar
resolucao
resolucao_comentario
resolvida_em
resolvido_em
origem
origem_social
social_event_id
social_network
social_username
social_post_url
social_comment_id
social_comment_text
instagram_username
instagram_post_url
instagram_comment_id
instagram_comentario
instagram_comentado_em
criado_em
atualizado_em
```

Conclusão: demanda é operacional, tem histórico, responsável, transferência, visitas, mídias e possível origem social.

---

## 6. Gamificação legado V1

O schema base contém a camada antiga:

```text
gamificacao_estado_usuario
gamificacao_eventos_usuario
gamificacao_indicacoes_eventos
gamificacao_metas_indicacao
gamificacao_metas_indicacao_usuario
gamificacao_pontos_temporada
gamificacao_prestigio_historico
gamificacao_temporada_usuario
gamificacao_temporadas
gamificacao_xp
```

Campos relevantes de `gamificacao_xp`:

```text
id
pessoa_id
tipo_evento
referencia_id
descricao
xp
criado_em
```

Campos relevantes de `gamificacao_estado_usuario`:

```text
id
pessoa_id
chave
valor
atualizado_em
```

---

## 7. Missões legado

Tabelas encontradas:

```text
missao_catalogo_crm
missao_compartilhamento_acessos
missao_compartilhamentos
missao_dispatch_execucoes
missao_estado_usuario
missao_historico_usuario
missao_regras_planejador
```

Campos relevantes de `missao_estado_usuario`:

```text
id
pessoa_id
missao_codigo
missao_tipo
modo_execucao
network
objetivo
status
validacao_tipo
post_id
referencia_id
referencia_tipo
payload_json
origem
origem_regra
prioridade
disponivel_em
expira_em
concluida_em
evento_conclusao_id
evento_conclusao_tipo
substituida_por_estado_id
criada_em
atualizada_em
```

---

## 8. Camada Game V2 confirmada

A V2 possui uma nova camada de gamificação baseada em tabelas `game_*` e serviços centrais em:

```text
/home/elab/public_html/core/games
```

Classes confirmadas:

```text
GameEstadoService
GameMoedasService
GameEventosService
GameConteudoService
GameMissaoMensalService
```

Script de migração observado:

```text
public_html/core/games/scripts/migrar-pontos-legado.php
```

---

## 9. Regra central da Game V2

O método central é:

```php
GameMoedasService::registrarMovimento(
    int $tenantClienteId,
    int $pessoaId,
    string $eventoCodigo,
    string $origemTipo,
    ?string $origemId = null,
    ?string $network = null,
    ?string $postId = null,
    ?array $metadata = null
): array
```

Fluxo interno observado:

```text
eventoCodigo
  -> game_acoes
  -> game_moedas_ledger
  -> cálculo de XP
  -> game_xp_ledger
  -> game_niveis
  -> game_usuario_estado
  -> game_eventos
```

Regra real de XP:

```text
XP total = intdiv(moedas_total_ganhas, 100)
```

Ou seja:

```text
100 moedas ganhas = 1 XP
```

Isso explica visualmente valores como:

```text
14.899 moedas -> 148 XP
```

---

## 10. Fonte de verdade recomendada para V2

### Legado

```text
pessoas.pontos
```

Deve ser tratado como legado/compatibilidade.

### Atual V2

```text
game_usuario_estado.moedas_saldo
game_usuario_estado.moedas_total_ganhas
game_usuario_estado.xp_total
game_usuario_estado.nivel_atual
```

### Histórico auditável

```text
game_moedas_ledger
game_xp_ledger
game_eventos
```

---

## 11. Tabelas Game V2 confirmadas diretamente no banco

### `game_acoes`

Catálogo de ações gamificadas.

Campos:

```text
id
codigo
nome
descricao
moedas
xp_equivalente
tipo
network
limite_diario
ativo
criado_em
atualizado_em
```

Tipos:

```text
acao
bonus
penalidade
coletivo
ranking
inatividade
```

---

### `game_niveis`

Catálogo de níveis.

Campos:

```text
id
nivel
nome
xp_minimo
moedas_equivalentes
descricao
icone_url
ativo
criado_em
atualizado_em
```

---

### `game_usuario_estado`

Resumo atual do usuário/jogador.

Campos:

```text
id
tenant_cliente_id
pessoa_id
moedas_saldo
moedas_total_ganhas
moedas_total_perdidas
xp_total
nivel_atual
ofensiva_dias
ofensiva_comentarios_dias
ultimo_acesso_app
ultimo_evento_em
status
criado_em
atualizado_em
```

Status:

```text
ativo
inativo
zerado
```

Chave única:

```text
tenant_cliente_id + pessoa_id
```

---

### `game_moedas_ledger`

Extrato de moedas.

Campos:

```text
id
tenant_cliente_id
pessoa_id
tipo_movimento
quantidade
evento_codigo
origem_tipo
origem_id
network
post_id
descricao
metadata_json
criado_em
```

Tipos de movimento:

```text
credito
debito
zeragem
```

---

### `game_xp_ledger`

Extrato de XP.

Campos:

```text
id
tenant_cliente_id
pessoa_id
xp
moedas_base
evento_codigo
origem_tipo
origem_id
descricao
metadata_json
criado_em
```

---

### `game_eventos`

Eventos exibíveis na interface.

Campos:

```text
id
tenant_cliente_id
pessoa_id
tipo
titulo
mensagem
moedas
xp
nivel
medalha_id
conquista_id
lottie_url
som_url
exibido
criado_em
exibido_em
```

Tipos:

```text
moedas
xp
nivel_up
medalha
conquista
ranking
streak
penalidade
coletivo
```

---

### `game_medalhas`

Catálogo de medalhas.

Campos:

```text
id
conquista_id
codigo
nome
descricao
raridade
ordem
icone_url
lottie_url
som_url
cor_principal
cor_secundaria
ativo
criado_em
atualizado_em
```

Raridades:

```text
comum
rara
epica
lendaria
especial
```

---

### `game_conquistas`

Catálogo de conquistas.

Campos:

```text
id
codigo
nome
descricao
icone_url
categoria
criterio_tipo
criterio_valor
network
ativo
criado_em
atualizado_em
```

---

### `game_usuario_medalhas`

Medalhas desbloqueadas por usuário.

Campos:

```text
id
tenant_cliente_id
pessoa_id
medalha_id
conquista_id
origem_tipo
origem_id
desbloqueada_em
visualizada
```

Chave única:

```text
tenant_cliente_id + pessoa_id + medalha_id
```

---

### `game_usuario_conquistas`

Consulta direta indicou que esta tabela não existe atualmente no banco, embora o código possua fallback/verificação para ela.

Status:

```text
não encontrada no banco atual
```

---

### `game_conteudos`

Catálogo de conteúdos gamificados.

Campos:

```text
id
tenant_cliente_id
tipo
titulo
descricao
youtube_video_id
url_original
embed_url
thumbnail_url
duracao_segundos
moedas_assistir
moedas_compartilhar
percentual_minimo_recompensa
segundos_minimos_recompensa
status
criado_por
criado_em
atualizado_em
```

Tipos:

```text
youtube
video_proprio
instagram
facebook
```

Status:

```text
rascunho
ativo
inativo
arquivado
```

---

### `game_conteudo_progresso`

Progresso por pessoa em conteúdo gamificado.

Campos:

```text
id
tenant_cliente_id
pessoa_id
conteudo_id
percentual_maximo
segundos_assistidos
duracao_segundos
completo
moedas_pagas
game_moedas_ledger_id
primeiro_play_em
completo_em
ultimo_evento_em
criado_em
atualizado_em
```

---

### `game_missoes_mensais`

Catálogo de missões mensais.

Campos:

```text
id
tenant_cliente_id
titulo
subtitulo
descricao
mes_referencia
data_inicio
data_fim
meta_impacto_individual
meta_impacto_coletiva
recompensa_moedas
recompensa_xp
cor_principal
cor_secundaria
icone_url
lottie_url
som_url
status
criado_em
atualizado_em
```

Status:

```text
rascunho
ativo
encerrado
cancelado
```

---

### `game_missao_acoes`

Ações dentro da missão mensal.

Campos:

```text
id
tenant_cliente_id
missao_mensal_id
codigo
titulo
descricao
impacto
limite_diario
tipo_validacao
network
ordem
status
criado_em
atualizado_em
```

Tipos de validação:

```text
manual
link
evento
convite
social_event
sistema
```

---

### `game_missao_especiais`

Catálogo de missões especiais.

Campos:

```text
id
tenant_cliente_id
missao_mensal_id
codigo
titulo
descricao
tipo_condicao
acao_codigo_alvo
quantidade_alvo
bonus_impacto
recorrencia
desbloqueio_tipo
ordem
status
criado_em
atualizado_em
```

Tipos de condição:

```text
checklist_basico
acao_quantidade
```

Desbloqueio:

```text
sempre
apos_checklist_basico
```

---

### `game_missao_especial_usuario`

Controle de missão especial por usuário.

Campos:

```text
id
tenant_cliente_id
pessoa_id
missao_mensal_id
missao_especial_id
periodo_referencia
progresso_atual
progresso_meta
status
bonus_impacto
bonus_pago
impacto_ledger_id
liberada_em
concluida_em
resgatada_em
criado_em
atualizado_em
```

Status:

```text
bloqueada
liberada
concluida
resgatada
```

---

### `game_impacto_ledger`

Ledger de impacto de missão.

Campos:

```text
id
tenant_cliente_id
pessoa_id
missao_mensal_id
acao_id
evento_codigo
quantidade_impacto
origem_tipo
origem_id
network
post_id
metadata_json
convertido_em_moedas
game_moedas_ledger_id
criado_em
```

---

### `game_missao_usuario_progresso`

Resumo do progresso do usuário na missão mensal.

Campos:

```text
id
tenant_cliente_id
pessoa_id
missao_mensal_id
impactos_total
moedas_convertidas
xp_gerado
acoes_concluidas
meta_individual_batida
concluida_em
criado_em
atualizado_em
```

---

## 12. Views Game V2 confirmadas

### `vw_game_usuario_estado_resumo`

Resumo direto de `game_usuario_estado`.

### `vw_game_medalhas_catalogo`

Catálogo de medalhas com dados de conquista associada.

### `vw_game_usuario_medalhas_resumo`

Medalhas desbloqueadas por usuário com dados de medalha e conquista.

### `vw_game_conquistas_catalogo`

Catálogo ativo de conquistas.

### `vw_comunidade_feed_unificado`

Feed unificado da comunidade combinando:

```text
vw_comunidade_feed_game
vw_comunidade_feed_metaverso
vw_comunidade_feed_social_events
```

Campos relevantes do feed:

```text
tenant_cliente_id
origem_id
origem_tipo
pessoa_id
tipo
grupo
titulo
texto
link_url
cta_label
recompensa_moedas
recompensa_xp
lottie_url
som_url
animacao_tipo
publicado_em
nome
apelido
chamar_por
perfil
network
external_post_id
```

---

## 13. Decisões recomendadas para V2

1. `GameMoedasService::registrarMovimento()` deve ser a única porta para alterar moedas, XP, nível e eventos de game.
2. `pessoas.pontos` deve ser tratado como legado e não como fonte principal.
3. A UI V2 deve ler estado de `game_usuario_estado` ou `vw_game_usuario_estado_resumo`.
4. O ranking V2 deve usar `game_usuario_estado`, preferencialmente ordenando por moeda, XP ou critério formal a definir.
5. Demandas devem ficar apenas no Admin da V2.
6. `game_usuario_conquistas` não existe no banco atual; antes de usar, decidir entre criar tabela ou continuar usando medalhas/conquistas via `game_usuario_medalhas`.
7. A existência de views com `DEFINER=root@localhost` deve ser revisada antes de migração/produção, para evitar problemas de permissão em outro ambiente.
8. Tabelas `game_*` devem ser exportadas para um schema versionado oficial do projeto.
9. Criar documentação de glossário: moedas, XP, nível, impacto, medalha, conquista, evento e missão.
10. Não misturar `gamificacao_*` com `game_*` em novas implementações sem camada clara de compatibilidade.
