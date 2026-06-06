# Inventário V1 - Ranking, Perfil e Liderança

## Fonte

Análise visual de prints enviados durante o levantamento da V1.

Importante: este documento registra apenas o que foi observado nas imagens. Regras de negócio, integrações e permissões ainda precisam ser confirmadas.

---

## Tela 17 - Ranking vitalício

### Observado

- Título: "Ranking Vitalício".
- Botão "Voltar".
- Card "Sua posição" exibindo:
  - Nome: João Felipe.
  - Posição geral: 4.
  - Total: 10.404 pontos.
  - Indicadores complementares:
    - Engajamento.
    - Comentários.
    - Cadastros.
    - Comunidade.
- Blocos de ranking por categoria:
  - Top 3 geral em pontos.
  - Top 3 geral em engajamento.
  - Top 3 geral em comentários.
  - Top 3 geral em cadastros.
- Lista geral da comunidade com posição, nome, pontos e métricas internas.
- Paginação inferior.

### Interpretação

A V1 possui ranking acumulado, chamado de vitalício, e rankings segmentados por tipo de contribuição. Isso reforça a lógica de gamificação contínua, não apenas semanal.

### Pontos de atenção

- Confirmar se o ranking vitalício soma toda a vida do usuário ou período de campanha.
- Confirmar se pontos podem expirar.
- Confirmar critérios de desempate.
- Confirmar se todos os usuários visualizam a lista completa ou apenas parte dela.
- Confirmar se os nomes e métricas são públicos para todos os usuários.

---

## Tela 18 - Perfil do usuário

### Observado

- Foto de perfil circular.
- Texto: "Toque na foto para alterar".
- Nome em destaque: João Felipe.
- Indicação: "Indicado por: Natalia Munis".
- Escolha de exibição:
  - Nome.
  - Apelido.
- Dados pessoais:
  - Nome da mãe.
  - Nascimento.
- Contatos:
  - Telefone.
  - E-mail.
- Redes sociais:
  - Instagram.
  - Facebook.
- Endereço:
  - Rua.
  - Bairro/cidade/UF.
  - CEP.
- Botões:
  - Editar dados.
  - Editar endereço.
  - Solicitar troca de líder.
  - Voltar.

### Interpretação

O perfil concentra dados pessoais, contato, redes sociais, endereço e vínculo hierárquico por indicação/liderança.

### Pontos de atenção

- Nome da mãe e nascimento são dados sensíveis e precisam de finalidade clara.
- Confirmar se telefone é editável pelo usuário ou se funciona como identificador principal.
- Confirmar se alteração de foto substitui imagem anterior ou mantém histórico.
- Confirmar se a exibição por nome/apelido afeta ranking, rede, comentários ou apenas interface.

---

## Tela 19 - Editar perfil

### Observado

- Título: "Editar perfil".
- Subtítulo: "Atualize seus dados pessoais".
- Botão "Voltar".
- Formulário com seções:
  - Dados pessoais.
  - Contatos.
  - Redes sociais.
- Campos:
  - Nome completo.
  - Apelido.
  - Nome da mãe.
  - Data de nascimento.
  - Telefone.
  - E-mail.
  - Instagram.
  - Facebook.
- Botões:
  - Salvar alterações.
  - Cancelar.

### Interpretação

A V1 permite que o próprio usuário edite dados cadastrais centrais e redes sociais.

### Pontos de atenção

- Confirmar quais campos são obrigatórios.
- Confirmar se mudança de telefone exige revalidação.
- Confirmar se alteração de dados pessoais gera auditoria.
- Confirmar se existem validações de formato para Instagram/Facebook.
- Confirmar se a edição pode impactar ranking, convite e vínculo de rede.

---

## Tela 20 - Alterar líder direto

### Observado

- Título: "Alterar líder direto".
- Texto explicativo:
  - "A mudança move você e toda a sua rede abaixo, preservando sua subárvore e reconectando tudo ao novo líder."
- Botões superiores:
  - Voltar ao perfil.
  - Início.
- Situação atual:
  - João Felipe: seu perfil.
  - Natália: líder direto atual.
  - 98 pessoas abaixo de você.
- Regras de segurança exibidas:
  - Usuário não pode escolher a si mesmo.
  - Usuário não pode escolher alguém que esteja abaixo da sua própria rede.
  - Quando a troca acontece, toda a estrutura desce junto com o usuário.
- Busca de novo líder:
  - Campo por nome, apelido ou ID.
  - Botão "Buscar".
- Candidatos encontrados:
  - Estado inicial: "Faça uma busca para exibir possíveis líderes."

### Interpretação

A V1 modela a rede de indicação como árvore hierárquica com subárvore. A troca de líder é uma operação estrutural que move o nó do usuário e todos seus descendentes para outro líder.

### Pontos de atenção

- Esta é uma operação crítica e deve ser auditada.
- Precisa impedir ciclo na árvore de liderança.
- Precisa validar permissões: usuário pode solicitar, mas talvez equipe/admin aprove.
- Confirmar se a troca é automática ou depende de aprovação.
- Confirmar impacto em pontuação, ranking, cadastros diretos e comissões de pontos.
- Confirmar se a relação é de convite, liderança ou ambas.

---

## Entidades reforçadas nesta leva

- Pessoa.
- Perfil.
- Líder.
- Rede.
- Subárvore de indicação.
- Ranking vitalício.
- Pontuação por categoria.
- Dados pessoais.
- Redes sociais do usuário.
- Endereço.
- Solicitação de troca de líder.

---

## Modelo conceitual observado

```text
Pessoa
  ├─ Perfil
  ├─ Dados pessoais
  ├─ Contatos
  ├─ Endereço
  ├─ Redes sociais
  ├─ Pontuação
  ├─ Ranking
  └─ Líder direto
        └─ Rede / Subárvore
```

A relação de liderança parece ser uma árvore:

```text
Líder A
  └─ Pessoa B
       ├─ Pessoa C
       ├─ Pessoa D
       └─ Pessoa E
```

Quando Pessoa B troca de líder, toda a subárvore abaixo dela se move junto.

---

## Riscos técnicos identificados

1. Operações de troca de líder podem gerar ciclos se não houver validação.
2. Recalcular métricas de rede pode ser caro se a árvore for grande.
3. Exibir ranking completo pode expor dados pessoais ou estratégicos.
4. Permitir edição de telefone pode quebrar autenticação ou histórico.
5. Dados como nome da mãe e data de nascimento exigem justificativa e proteção.
6. Se IDs de usuários forem públicos, há risco de enumeração.

---

## Recomendações para V2

1. Modelar a rede como árvore com validação formal de ancestralidade.
2. Separar relação de "quem convidou" da relação de "líder direto", caso ambas tenham significados diferentes.
3. Criar tabela/evento de histórico de troca de líder.
4. Exigir aprovação administrativa para mudança de líder quando houver subárvore relevante.
5. Definir política de privacidade para dados pessoais e exibição no ranking.
6. Criar cálculo de ranking auditável e rastreável por eventos de pontuação.
7. Tratar telefone como identificador controlado, com fluxo de revalidação para alteração.
