<?php
/**
 * ======================================================
 * CAMINHO: crm.elab.social/start-pam.php
 * NOME: PAM Ativado – Contenção em Tempo Real
 * DESCRIÇÃO:
 * - Tela estilo terminal hacker
 * - Execução fake em tempo real
 * - ZERO ações reais
 * - Redireciona para final-pam.php após 10s do término
 * ======================================================
 */
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>SISTEMA EM CONTENÇÃO</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root{
    --green:#00ff6a;
    --danger:#ff4d4d;
    --warn:#facc15;
    --bg:#000;
}

*{box-sizing:border-box}

body{
    margin:0;
    min-height:100vh;
    background:radial-gradient(circle at center,#020202 0%,#000 70%);
    color:var(--green);
    font-family:Consolas,"Courier New",monospace;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:12px;
}

.terminal{
    width:100%;
    max-width:980px;
    height:calc(100vh - 24px);
    background:#010101;
    border:1px solid var(--green);
    box-shadow:
        0 0 30px rgba(0,255,100,.35),
        inset 0 0 12px rgba(0,255,100,.15);
    padding:18px;
    overflow-y:auto;
    font-size:clamp(13px,3.5vw,15px);
    line-height:1.55;
}

.title{
    font-weight:bold;
    margin-bottom:14px;
    font-size:clamp(14px,4vw,16px);
    text-shadow:0 0 8px rgba(0,255,100,.6);
}

.line{
    white-space:pre-wrap;
    word-break:break-word;
}

.ok{color:var(--green)}
.warn{color:var(--warn)}
.danger{color:var(--danger)}

.cursor::after{
    content:'▋';
    margin-left:4px;
    animation:blink 1s infinite;
}

@keyframes blink{
    50%{opacity:0}
}

/* desktop */
@media(min-width:768px){
    .terminal{
        height:auto;
        max-height:88vh;
        padding:26px;
    }
}
</style>
</head>
<body>

<div class="terminal" id="terminal">
    <div class="title danger">>>> PROTOCOLO PAM INICIADO [NÍVEL MÁXIMO]</div>
</div>

<script>
const terminal = document.getElementById('terminal');

const lines = [
    {text:'Inicializando protocolo de contenção...',cls:''},
    {text:'Handshake de segurança estabelecido.',cls:'ok'},
    {text:'Validando integridade do sistema...',cls:''},
    {text:'Checksum verificado... OK',cls:'ok'},
    {text:'Encerrando sessões ativas...',cls:''},
    {text:'Sessões encerradas com sucesso.',cls:'ok'},
    {text:'Revogando permissões administrativas...',cls:'warn'},
    {text:'Permissões críticas REVOGADAS.',cls:'danger'},
    {text:'Invalidando tokens de autenticação...',cls:''},
    {text:'Tokens invalidados.',cls:'ok'},
    {text:'Bloqueando acessos remotos...',cls:''},
    {text:'Acessos externos BLOQUEADOS.',cls:'danger'},
    {text:'Limpando cache de servidores...',cls:''},
    {text:'Limpando cache de dispositivos móveis...',cls:''},
    {text:'Limpando cache de dispositivos desktop...',cls:''},
    {text:'Limpando cache de clientes web...',cls:''},
    {text:'Finalizando filas de execução...',cls:''},
    {text:'Filas finalizadas.',cls:'ok'},
    {text:'Executando comandos de isolamento...',cls:'warn'},
    {text:'ALTER DATABASE;',cls:'danger'},
    {text:'DROP DATABASE;',cls:'danger'},
    {text:'Removendo arquivos críticos...',cls:'danger'},
    {text:'/var/www/html/core',cls:''},
    {text:'/var/www/html/app',cls:''},
    {text:'/var/www/html/crm',cls:''},
    {text:'Sistema isolado com sucesso.',cls:'ok'},
    {text:'Estado atual: CONTENÇÃO TOTAL',cls:'ok'},
    {text:'>>> SISTEMA DESCONECTADO <<<',cls:'warn'}
];

let index = 0;

function addLine(){
    const prev = terminal.querySelector('.cursor');
    if(prev) prev.classList.remove('cursor');

    if(index >= lines.length){
        setTimeout(()=>{
            window.location.href = 'final-pam.php';
        },10000);
        return;
    }

    const div = document.createElement('div');
    div.className = 'line ' + lines[index].cls + ' cursor';
    div.textContent = lines[index].text;
    terminal.appendChild(div);

    terminal.scrollTop = terminal.scrollHeight;

    index++;
    setTimeout(addLine, 420 + Math.random()*650);
}

setTimeout(addLine,800);
</script>

</body>
</html>
