# Layout V2 - Gamificação, Comunidade, Ranking e Missões

## Fonte

Análise visual dos primeiros prints do layout da V2.

Importante: este documento registra apenas o que foi observado nas imagens. Regras de negócio, APIs, permissões e validações ainda precisam ser confirmadas.

---

## Direção geral da V2

A V2 apresenta uma mudança forte de linguagem visual em relação à V1.

### Observado

- Estética mais lúdica, com avatares, medalhas, conquistas, moedas, XP e níveis.
- Navegação inferior fixa com abas:
  - Inicial.
  - Missão.
  - Ranking.
  - Comunidade.
  - Perfil.
  - Admin.
- Uso recorrente de cards arredondados, sombras suaves, gradientes e elementos de jogo.
- O usuário aparece como personagem/avatar, não apenas como cadastro.
- A pontuação é separada em pelo menos dois conceitos:
  - Moedas.
  - XP.

### Interpretação

A V2 parece reposicionar o aplicativo como uma plataforma de mobilização gamificada, com experiência mais próxima de jogo/comunidade do que de CRM operacional.

---

## Tela V2-01 - Perfil gamificado

### Observado

Topo com:

- Tag: "MEU PERFIL".
- Nome: João Felipe.
- Avatar ilustrado.
- Botões superiores de atalho:
  - Início.
  - Configurações.
- Card de nível exibindo: nível 4.
- Identificação: `@joaofelipeat · MUDANDO O MUNDO DESDE 2026`.

Cards de resumo:

- Categoria atual: Mobilização.
- Time: 98.
- Seguidores: 0.

Card de progresso:

- "Nível: Mobilização".
- "Faltam só 2 XP para você subir.".
- Barra de XP de 148 até 150 XP.

Botão principal:

- "CONVIDAR AMIGO".
- Texto auxiliar: "Convide pelo WhatsApp, ganhe moedas & fortaleça seu time.".

Visão geral:

- Dias de ofensiva: 0.
- Nível atual: Mobilização.
- Moedas: 14.899.
- XP total: 148.

Missão semanal:

- Objetivo: convidar 5 pessoas.
- Progresso: 0/5.
- Marcadores numerados de 1 a 5.
- Indicadores:
  - Convites enviados: 0.
  - Amigos para aprovar: 0.
  - Total de ativados: 98.

Seções:

- Níveis.
- Medalhas.
- Conquistas.

### Interpretação

A tela de perfil da V2 transforma o usuário em jogador/personagem. A noção de rede da V1 permanece, mas ganha linguagem de time, XP, moedas, níveis, medalhas e conquistas.

### Pontos de atenção

- Confirmar diferença funcional entre moedas e XP.
- Confirmar se "time" equivale à rede/subárvore da V1.
- Confirmar se "seguidores" é uma nova entidade ou apenas métrica futura.
- Confirmar regra para dias de ofensiva.
- Confirmar como uma conquista é desbloqueada.

---

## Tela V2-02 - Comunidade / Feed de novidades

### Observado

Seção "Geral" com descrição:

- "A comunidade elab.social não dorme! Confira os últimos destaques.".

Itens de feed com:

- Avatar por inicial ou ícone.
- Nome da pessoa.
- Tempo relativo, por exemplo "Hoje cedo" ou "1 dia".
- Texto de atividade.
- Ilustração contextual, como coração, robô ou presente.
- Botões de ação:
  - "Dar um parabéns".
  - "Responder agora".
  - "Celebrar".
- Contador de curtidas ou reações.
- Alguns eventos exibem recompensas, como:
  - +50 moedas.
  - +1 XP.

Navegação/abas internas:

- Amigos.
- Geral.
- Novidades.

Rodapé:

- Estado de carregamento: "Carregando novidades...".

### Interpretação

A V2 introduz um feed social/comunitário, com eventos de atividade, celebrações, respostas e recompensas.

### Pontos de atenção

- Confirmar se o feed é público para toda comunidade ou filtrado pela rede do usuário.
- Confirmar quais eventos entram no feed.
- Confirmar se as interações do feed geram XP/moedas.
- Confirmar moderação, privacidade e possibilidade de ocultar eventos.
- Confirmar se comentários são internos ao app ou direcionam para redes sociais externas.

---

## Tela V2-03 - Ranking da comunidade

### Observado

Título:

- "Ranking da Comunidade".
- Subtítulo: "Ranking da semana · moedas, XP e níveis em jogo.".

Filtros de período:

- Semana.
- Mês.
- Geral.

Pódio visual com Top 3:

- 1º João F.
  - 14.899 moedas.
  - 148 XP.
  - Nível 4.
- 2º Carina L.
  - 13.840 moedas.
  - 138 XP.
  - Nível 4.
- 3º Del.
  - 13.270 moedas.
  - 132 XP.
  - Nível 4.

Mensagem motivacional:

- "Você está no topo. Continue defendendo sua posição!".

Lista abaixo do pódio:

- Posição.
- Avatar.
- Nome.
- Nível.
- Categoria.
- Moedas.
- XP.

Card fixo inferior do usuário:

- Posição atual.
- Nome.
- Moedas.
- Nível.
- XP.

### Interpretação

A V2 mantém ranking, mas altera o foco de "pontos" da V1 para combinação de moedas, XP e nível. O ranking vira parte central da experiência de jogo.

### Pontos de atenção

- Confirmar se ranking por engajamento, cadastros e comentários da V1 foi substituído ou apenas redesenhado.
- Confirmar se moedas e XP contam juntos ou separadamente.
- Confirmar critérios para Top 3 e desempate.
- Confirmar se ranking expõe nomes reais, apelidos ou nomes abreviados.

---

## Tela V2-04 - Inicial / Missão ativa

### Observado

Topo laranja com:

- Avatar.
- Nome abreviado: "João ...".
- Card de nível: nível 4.
- Progresso de XP:
  - Faltam 96 XP para chegar ao próximo nível.
  - 10.404 XP até 10.500 XP.

Card de missão:

- Título: "Apoiar no Instagram".
- Descrição: "Comente no Instagram e ganhe +25 pontos".
- Área visual da missão com ícone de raio.
- Badge: "Ganhe +25 pontos".
- Bloco "POST DA MISSÃO".
- Mensagem: "A próxima missão aparece aqui assim que estiver disponível.".

### Interpretação

A tela inicial da V2 parece ser orientada à missão ativa do usuário. A missão pode depender de uma ação externa no Instagram.

### Pontos de atenção

- Há inconsistência visual/conceitual: a tela fala em +25 pontos, enquanto outras telas usam moedas e XP.
- Confirmar se "pontos" é termo legado, sinônimo de moedas ou entidade separada.
- Confirmar como o app valida comentário no Instagram.
- Confirmar se a missão tem prazo, status, tentativa e comprovação.
- Confirmar se existe fila de missões ou apenas uma missão ativa por vez.

---

## Diferenças observadas entre V1 e V2

### V1

- Linguagem mais operacional.
- Foco em pontos, ranking, convites, demandas, pessoas e painéis.
- Home carregada com muitos módulos.
- Visual institucional com foto real de Teresa.

### V2

- Linguagem gamificada.
- Foco em avatar, níveis, XP, moedas, medalhas, conquistas e comunidade.
- Navegação mais clara por abas.
- Visual mais lúdico e orientado a jornada.
- O painel administrativo ainda aparece na barra inferior, mas não foi detalhado nesses prints.

---

## Entidades aparentes na V2

- Pessoa.
- Perfil gamificado.
- Avatar.
- Time.
- Seguidor.
- Nível.
- XP.
- Moeda.
- Missão.
- Missão semanal.
- Convite.
- Amigo para aprovação.
- Medalha.
- Conquista.
- Feed da comunidade.
- Evento de comunidade.
- Reação/curtida.
- Ranking.

---

## Hipótese de domínio V2

```text
Pessoa
  ├─ Perfil gamificado
  ├─ Avatar
  ├─ Time/Rede
  ├─ Convites
  ├─ XP
  ├─ Moedas
  ├─ Nível
  ├─ Medalhas
  ├─ Conquistas
  ├─ Missões
  ├─ Eventos de comunidade
  └─ Ranking
```

---

## Dúvidas abertas

1. Moedas e XP são entidades diferentes?
2. O ranking ordena por moedas, XP ou score composto?
3. O campo "time" representa a subárvore de liderança da V1?
4. O módulo de demandas continua existindo na V2?
5. O perfil com dados pessoais da V1 foi removido ou movido para configurações?
6. O Admin da barra inferior abre quais funções?
7. Como validar missão de Instagram/Facebook/TikTok?
8. Feed de comunidade é automático ou manual?
9. Existe moderação de eventos, comentários e reações?
10. Quais dados do usuário ficam públicos no ranking e feed?

---

## Recomendações iniciais para a V2

1. Criar glossário formal para XP, moedas, pontos, nível, medalha e conquista.
2. Definir se "pontos" será removido para evitar confusão com moedas/XP.
3. Separar camadas de experiência:
   - Usuário/cidadão.
   - Liderança/mobilização.
   - Administração.
4. Manter a árvore de rede da V1, mas documentar como "time" na V2 caso esse seja o termo do produto.
5. Criar eventos auditáveis para tudo que gera XP, moeda, medalha ou ranking.
6. Definir política de privacidade para feed e ranking.
7. Não implementar validação de redes sociais por suposição; confirmar API, limitações e alternativa manual.
