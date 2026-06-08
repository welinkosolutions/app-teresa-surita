<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/pessoas/index.php
 * NOME: Pessoas – A–Z + Busca Inteligente + Infinite Scroll
 * ======================================================
 */

declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);

session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Pessoas</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#f4f6f8; font-family:system-ui; }

.top-bar {
    position:sticky;
    top:0;
    z-index:50;
    background:#f4f6f8;
    padding:8px 12px;
}

.letter {
    padding:4px 10px;
    font-size:12px;
    font-weight:700;
    background:#eef1f3;
    color:#6c757d;
}

.person {
    background:#fff;
    padding:10px 12px;
    border-bottom:1px solid #eee;
    display:flex;
    align-items:center;
    justify-content:space-between;
}

.avatar {
    width:40px;
    height:40px;
    border-radius:50%;
    background:#0b6e7a;
    color:#fff;
    font-weight:700;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-right:12px;
    text-transform:uppercase;
    flex-shrink:0;
}

.nome-principal {
    font-weight:700;
    line-height:1.2;
}

.nome-secundario {
    font-size:12px;
    color:#6c757d;
    line-height:1.2;
}

mark {
    padding:0 2px;
    background:#fff3cd;
}

.btn-topo {
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
    <a href="/dashboard/index.php" class="btn btn-outline-success mb-2">← Voltar</a>
    <input
        type="text"
        id="busca"
        class="form-control"
        placeholder="Pesquisar nome, apelido ou telefone"
        autocomplete="off"
    >
</div>

<div id="lista"></div>

<button class="btn btn-success btn-topo" id="btnTopo">↑ Topo</button>

<script>
let offset = 0;
let loading = false;
let fim = false;
let buscaAtual = '';
let modoBusca = false;

const lista   = document.getElementById('lista');
const input   = document.getElementById('busca');
const btnTopo = document.getElementById('btnTopo');

let ultimaLetra = '';

function iniciais(texto) {
    return texto
        .split(' ')
        .slice(0,2)
        .map(p => p.charAt(0))
        .join('')
        .toUpperCase();
}

/* destaque do termo buscado */
function destacar(texto, termo) {
    if (!termo || termo.length < 3) return texto;
    const safe = termo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${safe})`, 'ig');
    return texto.replace(regex, '<mark>$1</mark>');
}

/* monta duas linhas: principal + secundária */
function montarNome(p) {

    const nome = p.nome || '';
    const apelido = p.apelido || '';
    const chamarPor = p.chamar_por || 'nome';

    let linhaPrincipal = '';
    let linhaSecundaria = '';

    if (chamarPor === 'apelido' && apelido) {
        linhaPrincipal = destacar(apelido, buscaAtual);
        linhaSecundaria = nome ? destacar(nome, buscaAtual) : '';
    } else {
        linhaPrincipal = destacar(nome, buscaAtual);
        linhaSecundaria = apelido ? destacar(apelido, buscaAtual) : '';
    }

    const baseOrdenacao = (p.nome_busca || nome).trim();

    return {
        html: `
            <div class="nome-principal">${linhaPrincipal}</div>
            ${linhaSecundaria ? `<div class="nome-secundario">${linhaSecundaria}</div>` : ''}
        `,
        avatar: baseOrdenacao,
        letraAZ: baseOrdenacao.charAt(0).toUpperCase(),
        tooltip: nome
    };
}

function resetLista() {
    offset = 0;
    fim = false;
    loading = false;
    ultimaLetra = '';
    lista.innerHTML = '';
}

function adicionarLetra(letra) {
    const div = document.createElement('div');
    div.className = 'letter';
    div.textContent = letra;
    lista.appendChild(div);
}

function carregar() {
    if (loading || fim) return;
    loading = true;

    fetch(`/pessoas/buscar_pessoas.php?q=${encodeURIComponent(buscaAtual)}&offset=${offset}`)
        .then(r => r.json())
        .then(dados => {
            if (!Array.isArray(dados) || dados.length === 0) {
                fim = true;
                return;
            }

            dados.forEach(p => {

                const nome = montarNome(p);

                /* A–Z só fora do modo busca */
                if (!modoBusca) {
                    if (nome.letraAZ !== ultimaLetra) {
                        ultimaLetra = nome.letraAZ;
                        adicionarLetra(nome.letraAZ);
                    }
                }

                const div = document.createElement('div');
                div.className = 'person';
                div.innerHTML = `
                    <div class="d-flex align-items-center" title="${nome.tooltip}">
                        <div class="avatar">${iniciais(nome.avatar)}</div>
                        <div>${nome.html}</div>
                    </div>
                    <a href="/pessoas/ver.php?id=${p.id}" class="btn btn-sm btn-outline-success">VER</a>
                `;
                lista.appendChild(div);
            });

            offset += dados.length;
        })
        .finally(() => loading = false);
}

/* scroll infinito apenas no modo A–Z */
window.addEventListener('scroll', () => {
    if (modoBusca) return;

    if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 300) {
        carregar();
    }

    btnTopo.style.display = window.scrollY > 400 ? 'block' : 'none';
});

btnTopo.onclick = () => window.scrollTo({ top:0, behavior:'smooth' });

/* busca inteligente */
let debounce;
input.addEventListener('input', () => {
    const valor = input.value.trim();

    clearTimeout(debounce);
    debounce = setTimeout(() => {

        if (valor === '') {
            modoBusca = false;
            buscaAtual = '';
            resetLista();
            carregar();
            return;
        }

        if (valor.length < 3) {
            modoBusca = true;
            buscaAtual = valor;
            resetLista();
            return;
        }

        modoBusca = true;
        buscaAtual = valor;
        resetLista();
        carregar();

    }, 250);
});

/* carga inicial */
carregar();
</script>

</body>
</html>
