<?php
declare(strict_types=1);
date_default_timezone_set('America/Boa_Vista');

session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pessoa_id = (int) $_SESSION['pessoa_id'];
$usuariosExclusivos = [7160, 6168, 6607];

if (!in_array($pessoa_id, $usuariosExclusivos, true)) {
    header('Location: /interno/admin.php');
    exit;
}

$hoje   = date('Y-m-d');
$filtro = $_GET['filtro'] ?? null;
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Lista de Aniversários</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body{background:#f4f6f9;font-family:system-ui}

.card-item{
    background:#fff;
    border-radius:16px;
    padding:16px;
    border:1px solid #e5e7eb;
    margin-bottom:14px;
}

.nome{font-weight:700;font-size:1rem}
.data{font-size:.85rem;color:#6c757d}

.badge-nova{background:#0d6efd}
.badge-antiga{background:#ffc107;color:#000}

.badge-60{background:#17a2b8}
.badge-70{background:#6f42c1}
.badge-80{background:#dc3545}
</style>
</head>

<body>

<div class="container py-3" style="max-width:600px">

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="/exclusivo/aniversarios.php" class="btn btn-outline-secondary btn-sm">
        ← Dashboard
    </a>

    <strong id="dataAtual"></strong>

    <div>
        <button class="btn btn-outline-secondary btn-sm me-1" onclick="mudarDia(-1)">←</button>
        <button class="btn btn-outline-secondary btn-sm" onclick="mudarDia(1)">→</button>
    </div>
</div>

<div id="lista"></div>
<div id="loading" class="text-center text-muted py-3">Carregando…</div>

</div>

<script>

let dataAtual   = '<?= $hoje ?>';
let filtroAtual = <?= json_encode($filtro) ?>;

function carregar(){

    const lista = document.getElementById('lista');
    lista.innerHTML = '';
    document.getElementById('loading').style.display = 'block';

    let url = `/api/exclusivo/aniversarios-dia.php?data=${dataAtual}`;

    if (filtroAtual) {
        url += `&filtro=${filtroAtual}`;
    }

    fetch(url)
        .then(r => {
            if (!r.ok) throw new Error('Erro na API');
            return r.json();
        })
        .then(res => {

            document.getElementById('dataAtual').innerText =
                res.header.data_formatada;

            if (!res.items || res.items.length === 0) {
                lista.innerHTML = '<div class="text-center text-muted py-4">Nenhum aniversário nesta data.</div>';
                document.getElementById('loading').style.display='none';
                return;
            }

            res.items.forEach(item => {

                const div = document.createElement('div');
                div.className = 'card-item';

                /* IDADE */
                let idadeBadge = '';
                if(item.idade !== null && item.idade !== undefined){

                    idadeBadge = ` <span class="badge bg-secondary ms-2">${item.idade} anos</span>`;

                    if(item.idade >= 80){
                        idadeBadge += ' <span class="badge badge-80 ms-1">80+</span>';
                    } else if(item.idade >= 70){
                        idadeBadge += ' <span class="badge badge-70 ms-1">70+</span>';
                    } else if(item.idade >= 60){
                        idadeBadge += ' <span class="badge badge-60 ms-1">60+</span>';
                    }
                }

                let prataBadge = '';
                if(item.idade !== null && item.idade >= 60){
                    prataBadge = ' <span class="badge bg-dark ms-1">Cabelos de Prata</span>';
                }

                let vinculoInfo = '';
                if(item.vinculo){
                    vinculoInfo = `
                        <div class="mt-2 small text-muted">
                            <i class="bi bi-link-45deg"></i>
                            ${item.vinculo}
                        </div>
                    `;
                }

                div.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="nome">
                                ${item.nome}${idadeBadge}${prataBadge}
                            </div>
                            <div class="data">${item.data_aniversario}</div>
                        </div>

                        <span class="badge ${item.base==='nova'?'badge-nova':'badge-antiga'}">
                            ${item.base==='nova'?'Base Atual':'Base Antiga'}
                        </span>
                    </div>

                    ${vinculoInfo}

                    <div class="mt-3">
                        ${item.whatsapp ? `
                        <a class="btn btn-success btn-sm w-100 mb-1"
                           href="https://wa.me/${item.whatsapp}"
                           target="_blank">
                           <i class="bi bi-whatsapp"></i> WhatsApp
                        </a>` : ''}

                        ${item.telefone ? `
                        <a class="btn btn-outline-primary btn-sm w-100"
                           href="tel:${item.telefone}">
                           <i class="bi bi-telephone"></i> Ligar
                        </a>` : ''}
                    </div>
                `;

                lista.appendChild(div);
            });

            document.getElementById('loading').style.display='none';
        })
        .catch(err => {
            document.getElementById('loading').innerText = 'Erro ao carregar.';
            console.error(err);
        });
}

function mudarDia(delta){

    const partes = dataAtual.split('-');
    const d = new Date(partes[0], partes[1]-1, partes[2]);
    d.setDate(d.getDate() + delta);

    const ano = d.getFullYear();
    const mes = String(d.getMonth()+1).padStart(2,'0');
    const dia = String(d.getDate()).padStart(2,'0');

    dataAtual = `${ano}-${mes}-${dia}`;
    carregar();
}

carregar();

</script>

</body>
</html>
