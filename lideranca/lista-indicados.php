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

$pdo = dbRoraima();

$pessoa_id = (int)$_SESSION['pessoa_id'];
$base_id   = (int)($_GET['id'] ?? 0);
$nivel     = max(1,(int)($_GET['nivel'] ?? 1));

if ($base_id <= 0) {
    header('Location: /lideranca/minha-equipe.php');
    exit;
}

/* Nome da pessoa base */
$stmt = $pdo->prepare("
    SELECT nome, apelido, chamar_por
    FROM pessoas
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$base_id]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    header('Location: /lideranca/minha-equipe.php');
    exit;
}

$nomeBase = ($base['chamar_por'] === 'apelido' && $base['apelido'])
    ? $base['apelido']
    : $base['nome'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Indicados</title>
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

.nivel-badge{
    background:#ffc107;
    color:#000;
    font-weight:600;
}

mark{
    background:#fff3cd;
    padding:0 2px;
}
</style>
</head>
<body>

<div class="top-bar">

    <div class="d-flex justify-content-between align-items-center mb-2">
        <a href="/lideranca/minha-equipe.php"
           class="btn btn-outline-success btn-sm">
           ← Voltar à Minha Equipe
        </a>

        <span class="badge nivel-badge">
            Nível <?= $nivel ?>
        </span>
    </div>

    <div class="fw-bold mb-2">
        Indicados de <?= htmlspecialchars($nomeBase) ?>
    </div>

    <input type="text"
           id="busca"
           class="form-control"
           placeholder="Buscar nome, apelido ou telefone">
</div>

<div class="container py-3">
    <div id="lista"></div>
</div>

<script>

let offset = 0;
let loading = false;
let fim = false;
let buscaAtual = '';

const baseId = <?= $base_id ?>;
const nivel  = <?= $nivel ?>;
const lista  = document.getElementById('lista');
const input  = document.getElementById('busca');

function destacar(texto, termo){
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

function reset(){
    offset = 0;
    fim = false;
    lista.innerHTML = '';
}

function carregar(){
    if(loading || fim) return;
    loading = true;

    fetch(`/lideranca/lista-indicados-buscar.php?id=${baseId}&q=${encodeURIComponent(buscaAtual)}&offset=${offset}`)
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
                    Telefone: ${p.telefone ?? '-'}
                </div>

                <div class="small mb-1">
                    Pontos: <strong>${p.pontos ?? 0}</strong>
                    | Indicados: <strong>${p.total_indicados ?? 0}</strong>
                </div>

                <div class="d-flex gap-2 flex-wrap mt-2">

                    ${whats ? `
                        <a href="${whats}" target="_blank"
                           class="btn btn-success btn-sm">
                           WhatsApp
                        </a>
                    `:''}

                    <a href="/lideranca/lista-indicados.php?id=${p.id}&nivel=${nivel+1}"
                       class="btn btn-dark btn-sm">
                       Ver Indicados
                    </a>

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
    if(window.innerHeight + window.scrollY >= document.body.offsetHeight - 300){
        carregar();
    }
});

/* busca */
let debounce;
input.addEventListener('input', ()=>{
    clearTimeout(debounce);
    debounce = setTimeout(()=>{
        buscaAtual = input.value.trim();
        reset();
        carregar();
    },250);
});

carregar();

</script>

</body>
</html>