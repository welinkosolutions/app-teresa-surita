# Inventário V1 - Telas de Onboarding e Acesso

## Fonte

Análise visual de 4 prints enviados no início do levantamento da V1.

Importante: este documento registra apenas o que foi observado nas imagens. Regras de negócio e integrações ainda precisam ser confirmadas.

---

## Tela 01 - Chamada inicial / Splash promocional

### Observado

- Fundo em gradiente verde/azul.
- Card superior com ícone de sino e texto:
  - "Teresa Surita escreveu:"
  - "Oii! Acabei de deixar uma novidade para você. Acesse o app e descubra!!"
- Imagem central de Teresa Surita em formato visual de postagem/rede social.
- Elementos flutuantes de reação, como curtida e coração.
- Botão principal: "CLIQUE PARA ACESSAR".
- Assinatura visual "Teresa Surita".
- Rodapé: "Feito com amor pela Keepdo Brasil".

### Interpretação

Esta tela parece funcionar como landing/splash de entrada, com foco emocional e convite para acessar o aplicativo.

### Pontos de atenção

- Confirmar se é tela nativa do app, webview ou página de campanha.
- Confirmar se o botão leva para login, cadastro, convite ou home.
- Avaliar acessibilidade do contraste, especialmente em textos sobre gradiente.

---

## Tela 02 - Login / Acesso seguro

### Observado

- Título superior: "Acesso seguro", com ícone de cadeado.
- Imagem de Teresa Surita em destaque.
- Card branco com formulário.
- Campos:
  - WhatsApp.
  - Senha.
- Campo de senha possui ícone para visualizar/ocultar senha.
- Botão principal: "Entrar".
- Links secundários:
  - "Cadastre-se".
  - "Esqueci minha Senha".
- Barra inferior com ação: "VOLTAR".

### Interpretação

A autenticação da V1 parece baseada em WhatsApp + senha.

### Pontos de atenção

- Confirmar formato aceito para WhatsApp: com DDD, máscara, país, apenas números etc.
- Confirmar política de senha.
- Confirmar se há validação por OTP, SMS, WhatsApp ou apenas senha local.
- Confirmar tratamento para usuários já convidados versus cadastro aberto.
- Confirmar se o login usa API própria, serviço terceiro ou backend ELAB/Keepdo.

---

## Tela 03 - Recuperação de acesso

### Observado

- Título superior: "Recuperar acesso", com ícone de envelope.
- Card com título: "Esqueceu a senha?".
- Texto orientativo:
  - "Digite seu WhatsApp para verificar seu e-mail e solicitar uma nova senha de acesso ao aplicativo."
- Campo solicitado:
  - "Use o WhatsApp do Cadastro".
  - Placeholder: "Digite aqui o número com DDD".
- Botão principal: "Continuar".
- Barra inferior com ação: "VOLTAR".

### Interpretação

A recuperação de senha usa o WhatsApp como chave inicial e aparentemente valida ou consulta o e-mail cadastrado para redefinição.

### Pontos de atenção

- Confirmar se o usuário recebe link por e-mail, código por WhatsApp ou senha temporária.
- Confirmar se o e-mail é exibido parcialmente ou apenas usado internamente.
- Confirmar requisitos de segurança para evitar enumeração de usuários.
- Confirmar limitação de tentativas e logs de recuperação.

---

## Tela 04 - Convite especial

### Observado

- Card central em fundo claro.
- Título: "CONVITE ESPECIAL".
- Nome em destaque: "João".
- Texto:
  - "Está convidando você para participar da rede social da Teresa Surita."
- Caixa de confirmação:
  - "Seu convite foi CONFIRMADO".
  - "O cadastro levará menos de 1 minuto, clique no botão para começar."
- Botão principal vermelho: "INICIAR AGORA".
- Rodapé: "ELAB SOCIAL · Mobilização Inteligente".

### Interpretação

Esta tela indica existência de fluxo de cadastro por convite, possivelmente com identificação de quem convidou o novo usuário.

### Pontos de atenção

- O texto parece incompleto: antes de "Está convidando você" deveria existir o nome de quem convidou.
- Confirmar se "João" é o convidado ou o usuário que está convidando.
- Confirmar se o convite possui token, validade e uso único.
- Confirmar se o convite já pré-valida acesso ao cadastro.
- Confirmar relação com a marca ELAB SOCIAL.

---

## Componentes visuais recorrentes

- Gradiente verde/azul.
- Cards brancos com bordas arredondadas.
- Botões grandes, centralizados e com texto em caixa alta ou negrito.
- Linguagem simples e direta.
- Navegação inferior com botão "VOLTAR" em telas de acesso.

---

## Fluxos identificados até agora

### Fluxo A - Entrada comum

1. Usuário acessa tela de chamada inicial.
2. Clica em "CLIQUE PARA ACESSAR".
3. Vai para tela de login ou cadastro.

### Fluxo B - Login

1. Usuário informa WhatsApp.
2. Usuário informa senha.
3. Clica em "Entrar".
4. Sistema autentica e libera acesso.

### Fluxo C - Recuperação de senha

1. Usuário clica em "Esqueci minha Senha".
2. Informa WhatsApp com DDD.
3. Clica em "Continuar".
4. Sistema verifica cadastro e inicia recuperação.

### Fluxo D - Cadastro por convite

1. Usuário abre link de convite.
2. Sistema exibe convite especial.
3. Usuário clica em "INICIAR AGORA".
4. Sistema inicia cadastro.

---

## Dúvidas abertas

1. O aplicativo tem cadastro público ou apenas por convite?
2. WhatsApp é o identificador principal da pessoa?
3. Existe e-mail obrigatório no cadastro?
4. Qual backend atende essa V1?
5. O app é nativo, PWA, webview ou híbrido?
6. O fluxo de convite pertence ao app Teresa Surita ou a uma base ELAB SOCIAL reaproveitada?
7. Existe separação entre cidadão comum, liderança e equipe interna?
8. Quais eventos são auditados no login, recuperação e convite?

---

## Recomendações iniciais para V2

1. Definir formalmente a jornada de acesso: convite, cadastro aberto, login e recuperação.
2. Padronizar textos e capitalização: por exemplo, "Esqueci minha senha" em vez de alternar maiúsculas.
3. Garantir validação, máscara e normalização do WhatsApp.
4. Implementar proteção contra enumeração de usuários na recuperação de senha.
5. Registrar eventos de segurança: tentativa de login, falha, recuperação, convite aceito.
6. Documentar marca, cores, tipografia e componentes visuais em um guia de design.
