<?php
declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);

session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Lista Telefônica</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{background:#f4f6f8;font-family:system-ui}

.top-bar{
    position:sticky;
    top:0;
    z-index:50;
    background:#f4f6f8;
    padding:10px 12px;
}

.card-pessoa{
    background:#fff;
    border-radius:16px;
    padding:16px;
    margin-bottom:14px;
    box-shadow:0 6px 16px rgba(0,0,0,.06);
}

.nome-principal{
    font-weight:700;
    font-size:15px;
}

.nome-secundario{
    font-size:12px;
    color:#6c757d;
}

mark{
    background:#fff3cd;
    padding:0 2px;
}

.btn-topo{
    position:fixed;
    bottom:20px;
    right:20px;
    display:none;
    z-index:99;
}
</style>
</head>
<body>

<div class="top-bar">
    <a href="/interno/admin.php"
       class="btn btn-outline-success btn-sm mb-2">
       ← Voltar
    </a>

    <div class="fw-bold mb-2">
        Lista de Cadastro Geral
    </div>

    <input type="text"
           id="busca"
           class="form-control"
           placeholder="Buscar nome, apelido, telefone, cidade ou bairro">
</div>

<div class="container py-3">
    <div id="lista"></div>
</div>

<button class="btn btn-success btn-topo" id="btnTopo">↑ Topo</button>

<script>

let offset = 0;
let loading = false;
let fim = false;
let buscaAtual = '';
let modoBusca = false;

const lista = document.getElementById('lista');
const input = document.getElementById('busca');
const btnTopo = document.getElementById('btnTopo');

function destacar(texto, termo){
    if(!texto) return '';
    if(!termo || termo.length < 3) return texto;
    const safe = termo.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
    const regex = new RegExp(`(${safe})`,'ig');
    return texto.replace(regex,'<mark>$1</mark>');
}

function gerarLinkWhats(tel){
    if(!tel) return null;
    let n = tel.replace(/\D/g,'');
    if(n.length < 10) return null;
    if(!n.startsWith('55')) n = '55'+n;
    return 'https://wa.me/'+n;
}

function gerarMapa(lat,lng){
    if(!lat || !lng) return null;
    return `https://www.google.com/maps?q=${lat},${lng}`;
}

function reset(){
    offset = 0;
    fim = false;
    lista.innerHTML = '';
}

function carregar(){
    if(loading || fim) return;
    loading = true;

    fetch(`/lideranca/lista-geral-buscar.php?q=${encodeURIComponent(buscaAtual)}&offset=${offset}`)
    .then(r=>r.json())
    .then(dados=>{

        if(!Array.isArray(dados) || dados.length === 0){
            fim = true;
            return;
        }

        dados.forEach(p=>{

            const chamar = (p.chamar_por === 'apelido' && p.apelido)
                ? p.apelido
                : p.nome;

            const whats = gerarLinkWhats(p.telefone);
            const mapa  = gerarMapa(p.latitude,p.longitude);

            const card = document.createElement('div');
            card.className = 'card-pessoa';

            card.innerHTML = `
                <div class="nome-principal">
                    ${destacar(chamar,buscaAtual)}
                </div>

                <div class="nome-secundario mb-2">
                    Nome completo: ${destacar(p.nome,buscaAtual)}
                </div>

                <div class="small mb-1">
                    Bairro: ${destacar(p.bairro ?? '-',buscaAtual)} |
                    Cidade: ${destacar(p.cidade ?? '-',buscaAtual)}
                </div>

                <div class="d-flex gap-2 flex-wrap mt-2">

                    ${whats ? `
                        <a href="${whats}" target="_blank"
                           class="btn btn-success btn-sm">
                           WhatsApp
                        </a>
                    `:''}

                    ${mapa ? `
                        <a href="${mapa}" target="_blank"
                           class="btn btn-outline-primary btn-sm">
                           Mapa
                        </a>
                    `:''}

                </div>
            `;

            lista.appendChild(card);
        });

        offset += dados.length;

    })
    .finally(()=> loading = false);
}

/* scroll infinito */
window.addEventListener('scroll', ()=>{

    if(window.innerHeight + window.scrollY >= document.body.offsetHeight - 400){
        carregar();
    }

    btnTopo.style.display = window.scrollY > 500 ? 'block' : 'none';
});

btnTopo.onclick = () => {
    window.scrollTo({top:0, behavior:'smooth'});
};

/* busca inteligente */
let debounce;
input.addEventListener('input', ()=>{
    clearTimeout(debounce);

    debounce = setTimeout(()=>{

        const valor = input.value.trim();

        if(valor === ''){
            modoBusca = false;
            buscaAtual = '';
            reset();
            carregar();
            return;
        }

        if(valor.length < 3){
            modoBusca = true;
            buscaAtual = valor;
            reset();
            return;
        }

        modoBusca = true;
        buscaAtual = valor;
        reset();
        carregar();

    },250);
});

carregar();

</script>

</body>
</html>