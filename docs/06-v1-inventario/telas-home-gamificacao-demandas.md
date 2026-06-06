# Inventário V1 - Home, Gamificação, Rede e Demandas

## Fonte

Análise visual de prints enviados durante o levantamento da V1.

Importante: este documento registra apenas elementos observados visualmente. Regras de negócio, APIs e banco ainda precisam de confirmação.

---

## Tela 05 - Home principal / Dashboard do usuário

### Observado

- Saudação: "Boa tarde".
- Nome do usuário: "João Felipe".
- Pontuação total exibida: "10.404 Pontos".
- Posição no ranking geral: "Sua posição no Ranking Geral é 4".
- Imagem de Teresa Surita em destaque no topo.
- Card "Seu impacto hoje" com mensagem motivacional.
- Card promocional de missão liberada:
  - "Resgate seus Pontos AGORA!"
  - Botão "DETONAR MISSÃO".
- Card "MISSÃO CRÍTICA!" com conteúdo de post para resgate.
- Botão principal: "RESGATAR ESSE POST".
- Informação de tempo restante.
- Pontuação da missão: "+25 Pontos".

### Interpretação

A home da V1 é fortemente baseada em gamificação, missões, ranking e ações de engajamento em redes sociais.

### Pontos de atenção

- Confirmar regra de pontuação.
- Confirmar o que significa "resgatar post".
- Confirmar se a missão exige validação automática ou manual.
- Confirmar se há integração real com redes sociais para validação de engajamento.

---

## Tela 06 - Convites e rede do usuário

### Observado

Card com saudação: "Ei, João Felipe!".

Métricas exibidas:

- "98 Na sua rede".
- "0 Pendentes".
- "0 Pontos semana".
- Barra de progresso: "0 de 10".
- Texto: "Cada convite pode gerar até +160 Pontos".
- Botões:
  - "Convidar".
  - "Ver Rede".

### Interpretação

Existe um módulo de rede/indicação, provavelmente com convites e acompanhamento de pessoas vinculadas ao usuário.

### Pontos de atenção

- Confirmar se "rede" representa convidados diretos ou toda a árvore de indicação.
- Confirmar como convite vira ponto.
- Confirmar quais estados existem para convite: pendente, aceito, expirado etc.
- Confirmar se liderança/usuário comum possuem limites diferentes.

---

## Tela 07 - Compartilhamento de link

### Observado

- Card "Compartilhar seu link".
- Link exibido: `https://app.elab.social/i/106607`.
- Botão "Copiar".
- Botões de compartilhamento:
  - "Enviar no Instagram".
  - "Postar no Facebook".
  - "WhatsApp Status".
  - "Mais opções".

### Interpretação

Cada usuário possui um link único de convite/indicação.

### Pontos de atenção

- O domínio observado é `app.elab.social`, indicando possível plataforma base ELAB SOCIAL.
- Confirmar se o ID do convite é numérico e público.
- Avaliar risco de enumeração caso IDs sejam sequenciais.
- Confirmar se o link possui token seguro ou apenas identificador simples.

---

## Tela 08 - Ranking semanal

### Observado

Card "Melhores da Semana".

Abas:

- Engajamento.
- Cadastros.
- Comentários.

Itens exibidos:

- Teresa Surita: 12.782.
- Sandra Noronha: 120.
- Rita: 90.
- Você: 0.

Botão: "Ver ranking completo".

### Interpretação

Há ranking por diferentes dimensões de participação.

### Pontos de atenção

- Confirmar período do ranking.
- Confirmar se Teresa Surita participa do ranking como usuária ou item fixo de referência.
- Confirmar se ranking é público para todos os usuários.
- Confirmar se há prevenção contra manipulação de pontuação.

---

## Tela 09 - Blocos expansíveis da home

### Observado

Blocos principais:

- Pessoas.
- Redes Sociais.
- Demandas.

Cada bloco possui botão "CLIQUE PARA ABRIR" e ícone de expandir/recolher.

### Interpretação

A home concentra dashboards e atalhos operacionais em formato de acordeão.

---

## Tela 10 - Bloco Pessoas expandido

### Observado

Indicadores exibidos:

- Total de cadastros: 2.692.
- Últimas 24h: 0.
- Últimos 7 dias: 0.
- Convites enviados: 340.
- Ritmo da base: 0,9/dia.
- Engajamento de base: 15.706.
- Média diária: 524/dia.
- Ativação: "Alta participação".
- Oportunidades de relacionamento:
  - Hoje: 7.
  - No mês de Junho: 208.

Botões:

- Contatos importantes.
- Aniversários do dia.
- Ver base completa.

### Interpretação

O módulo Pessoas mistura indicadores de crescimento da base, relacionamento, engajamento e oportunidades operacionais.

### Pontos de atenção

- Confirmar se "oportunidades de relacionamento" são aniversários, leads, contatos sem interação ou outro critério.
- Confirmar fonte dos indicadores.
- Confirmar se dados são gerais da campanha ou específicos do usuário logado.

---

## Tela 11 - Bloco Redes Sociais expandido

### Observado

Indicadores de Instagram e Facebook, com período "Últimos 30 dias".

Métricas exibidas:

- Alcance total: 87.358.680.
- Engajamento total: 1.988.786.
- Visualizações: 34.270.320.
- Taxa de engajamento: 2,28%.
- Interações: 1.988.786.
- Curtidas: 1.077.457.
- Comentários: 300.623.
- Compartilhamentos: 588.283.

Engajamento do aplicativo:

- Convites ativos: 3.004.
- Interações Teresa Surita: 3.325.
- Taxa de engajamento: 2,17%.
- Usuários ativos: 59.

Botões:

- Analisar Concorrentes.
- Ver Relatório Completo.

### Interpretação

Há intenção de transformar o app em painel de mobilização digital e análise de redes sociais.

### Pontos de atenção

- Confirmar origem dos dados sociais.
- Confirmar se há integração oficial com Meta/Instagram/Facebook.
- Confirmar periodicidade de atualização.
- Confirmar se as métricas exibidas são reais, demonstrativas ou importadas.

---

## Tela 12 - Bloco Demandas expandido

### Observado

Atalhos:

- Demandas Registradas.
- Registrar Nova Demanda.
- Minha Equipe.
- Lista de Cadastrados.
- Desempenho da Equipe.

### Interpretação

O app possui também módulo operacional de demandas, indo além de rede social e gamificação.

---

## Tela 13 - Lista pronta para contato

### Observado

- Título: "Lista pronta para Contato".
- Botão "Voltar".
- Indicadores:
  - 1 Importantes.
  - 0 Contactados.
- Data exibida: 06/06/2026.
- Card de pessoa:
  - Nome: Hernandez.
  - Tag: Importante.
  - WhatsApp: exibido em formato com DDD.
  - Botão: "Falar no WhatsApp".
- Navegação inferior:
  - Suporte.
  - Ranking.
  - Início.
  - Perfil.
  - Sair.

### Interpretação

Existe uma fila ou lista operacional de contatos importantes para ação direta via WhatsApp.

### Pontos de atenção

- Confirmar se contato é marcado automaticamente como contactado após abrir WhatsApp.
- Confirmar se há registro de histórico da abordagem.
- Confirmar se número completo deve ser visível para todos os perfis.

---

## Tela 14 - Todas as demandas

### Observado

- Título: "Todas as demandas".
- Indicador "Nova" no topo.
- Resumo:
  - Total: 63.
  - Pendentes: 24.
  - Resolvidas: 39.
- Campo de busca por telefone, nome ou bairro.
- Filtros:
  - Pendentes.
  - Todas.
  - Resolvidas.
  - Urgentes.
- Lista de demandas em cards.
- Cada demanda apresenta:
  - Nome da pessoa.
  - Telefone.
  - Bairro/cidade ou referência territorial.
  - Status "Aberta".
  - Texto da demanda.
  - Data/hora de registro.
  - Botões: Abrir, WhatsApp, Resolver, Transferir, Marcar para visita.
- Paginação inferior.

### Interpretação

O módulo de demandas possui status, busca, filtros, ações operacionais e possível distribuição/transferência.

### Pontos de atenção

- Confirmar ciclo de vida da demanda.
- Confirmar responsáveis/equipe.
- Confirmar se transferência muda responsável ou setor.
- Confirmar se "Marcar para visita" gera agenda/tarefa.
- Confirmar se WhatsApp registra interação.

---

## Tela 15 - Registrar nova demanda

### Observado

- Botão "Voltar".
- Título: "Registrar Nova Demanda".
- Campo obrigatório: "Telefone *".
- Botão: "Verificar".

### Interpretação

O cadastro de nova demanda começa pela verificação do telefone, provavelmente para localizar ou criar a pessoa antes de registrar a demanda.

### Pontos de atenção

- Confirmar se telefone é equivalente a WhatsApp.
- Confirmar fluxo quando pessoa já existe.
- Confirmar fluxo quando pessoa não existe.
- Confirmar se demanda sempre precisa estar vinculada a uma pessoa.

---

## Tela 16 - Cadastros diretos / Base de pessoas

### Observado

- Título: "Cadastros Diretos".
- Botão "Meus Indicados".
- Campo de busca por nome, apelido ou telefone.
- Lista de pessoas em cards.
- Cada card contém:
  - Nome.
  - Nome completo.
  - Telefone.
  - Bairro.
  - Pontos.
  - Indicados.
  - Data de cadastro.
  - Último acesso.
- Botões:
  - WhatsApp.
  - Mapa.
  - Ver Indicados.

### Interpretação

Há uma base de pessoas com vínculo por indicação e ações rápidas de contato, localização e navegação na rede.

### Pontos de atenção

- Confirmar se "Cadastros Diretos" são pessoas cadastradas diretamente pelo usuário logado.
- Confirmar diferença entre "Cadastros Diretos" e "Meus Indicados".
- Confirmar se bairro e cidade são obrigatórios.
- Confirmar permissões de visualização da base.

---

## Navegação inferior observada

- Suporte.
- Ranking.
- Início.
- Perfil.
- Sair.

### Interpretação

A V1 possui navegação mobile fixa por abas, com a home como centro da experiência.

---

## Entidades aparentes identificadas

- Pessoa.
- Usuário.
- Convite.
- Rede/Indicação.
- Pontuação.
- Ranking.
- Missão.
- Post/Missão social.
- Demanda.
- Equipe.
- Contato importante.
- Métrica de redes sociais.

---

## Hipótese de domínio atual

Pessoa é a entidade central. Ao redor dela existem rede de indicação, pontuação, missões de engajamento, demandas, contatos e métricas operacionais.

Fluxo geral observado:

1. Pessoa acessa o app.
2. Recebe missões e pontuações.
3. Compartilha link e convida novos usuários.
4. Acompanha ranking.
5. Realiza ações de contato/rede.
6. Registra ou acompanha demandas.
7. A equipe monitora base, redes e demandas.

---

## Dúvidas abertas

1. O usuário comum vê todos esses blocos ou isso depende de perfil?
2. Quem pode acessar Pessoas, Redes Sociais e Demandas?
3. A pontuação é individual, por equipe ou geral?
4. Missões são criadas manualmente por admin?
5. Existe validação automática de missão em redes sociais?
6. O app integra com WhatsApp apenas por deep link ou também por API?
7. Demandas possuem responsáveis e prazos?
8. Existe histórico de interações por pessoa?
9. O mapa usa endereço, bairro ou coordenadas?
10. Dados sociais vêm de API oficial ou lançamento manual?

---

## Recomendações iniciais para V2

1. Separar claramente experiência do cidadão, liderança e equipe interna.
2. Definir modelo formal de Pessoa, Convite, Rede, Pontuação, Missão e Demanda.
3. Criar trilha de auditoria para ações sensíveis: login, convite, pontuação, demanda, transferência e resolução.
4. Rever exposição de telefones e dados pessoais por perfil.
5. Trocar identificadores públicos sequenciais por tokens opacos quando houver links de convite.
6. Criar documentação de scoring antes de implementar gamificação.
7. Validar juridicamente o uso de dados pessoais, localização, telefone e métricas de engajamento.
8. Definir se o app será plataforma de mobilização, CRM político, rede social ou combinação modular desses produtos.
