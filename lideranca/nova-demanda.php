<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('America/Boa_Vista');

session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();
$liderId = (int)$_SESSION['pessoa_id'];

$stmt = $pdo->prepare("SELECT perfil FROM pessoas WHERE id=? LIMIT 1");
$stmt->execute([$liderId]);

$perfil = trim((string)($stmt->fetchColumn() ?? ''));

if (!in_array($perfil, ['lider', 'admin', 'gestor_lideres'], true)) {
    header('Location: /interno/admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Nova Demanda</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{background:#f4f6f8;font-family:system-ui}
.card-box{background:#fff;border-radius:18px;padding:22px;margin-bottom:16px;box-shadow:0 6px 16px rgba(0,0,0,.06)}
.step{display:none}
.step.active{display:block}
.status-card{padding:15px;border-radius:12px;color:#fff;margin-top:10px;background:#198754}
.select-btn{border-radius:12px;padding:8px 14px;margin:5px;border:1px solid #ccc;background:#f8f9fa;cursor:pointer}
.select-btn.active{background:#198754;color:#fff;border-color:#198754}
.preview img,.preview video{max-width:100%;margin-top:10px;border-radius:10px}
.char-counter{font-size:13px;text-align:right;margin-top:5px}
.modal-content{border:0;border-radius:22px;overflow:hidden}
.confirm-icon{width:62px;height:62px;border-radius:999px;background:#dcfce7;color:#166534;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 14px}
.loading-spinner{width:46px;height:46px;border:5px solid #e5e7eb;border-top-color:#198754;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 16px}
.success-icon{width:68px;height:68px;border-radius:999px;background:#dcfce7;color:#166534;display:flex;align-items:center;justify-content:center;font-size:36px;margin:0 auto 14px}
.recent-box{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:14px;padding:14px;font-size:14px;text-align:left}
.recent-item{border-top:1px solid #fed7aa;padding-top:10px;margin-top:10px}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>

<body>

<div class="container py-4">
    <a href="demandas.php" class="btn btn-outline-secondary mb-3">← Voltar</a>
    <h5 class="fw-bold mb-4">📝 Registrar Nova Demanda</h5>

    <form method="post" action="nova-salvar.php" enctype="multipart/form-data" id="formNova">

        <input type="hidden" name="pessoa_id" id="pessoa_id">
        <input type="hidden" name="pessoa_existente" id="pessoa_existente" value="0">
        <input type="hidden" name="data_nascimento" id="data_nascimento">
        <input type="hidden" name="origem" id="origem">
        <input type="hidden" name="categoria" id="categoria">
        <input type="hidden" name="codigo_demanda" id="codigo_demanda">

        <div class="card-box step active" id="step1">
            <label>Telefone *</label>
            <input type="text" id="telefone_busca" class="form-control mb-3" inputmode="numeric">
            <button type="button" class="btn btn-success w-100" onclick="verificarTelefone()">Verificar</button>
            <div id="resultadoPessoa"></div>
        </div>

        <div class="card-box step" id="step2">
            <label>Telefone</label>
            <input name="telefone" id="telefone" class="form-control mb-3" readonly>

            <label>Nome *</label>
            <input name="nome" id="nome" class="form-control mb-3" required>

            <label>Apelido</label>
            <input name="apelido" id="apelido" class="form-control mb-3">

            <label>Data nascimento *</label>
            <input type="date" id="data_input" class="form-control mb-3" required>

            <button type="button" class="btn btn-outline-secondary w-100 mb-2" onclick="voltar()">Voltar</button>
            <button type="button" class="btn btn-success w-100" onclick="validarStep2()">Continuar</button>
        </div>

        <div class="card-box step" id="step3">
            <h6>Endereço</h6>

            <button type="button" class="btn btn-outline-dark w-100 mb-3" onclick="setSemEndereco()">
                Demanda sem endereço físico
            </button>

            <label>CEP</label>
            <input type="text" id="cep" name="cep" class="form-control mb-2" inputmode="numeric">

            <input type="text" id="endereco" name="endereco" class="form-control mb-2" placeholder="Endereço">
            <input type="text" id="numero" name="numero" class="form-control mb-2" placeholder="Número">
            <input type="text" id="complemento" name="complemento" class="form-control mb-2" placeholder="Complemento">
            <input type="text" id="bairro" name="bairro" class="form-control mb-2" placeholder="Bairro">
            <input type="text" id="cidade" name="cidade" class="form-control mb-2" placeholder="Cidade">
            <input type="text" id="estado" name="estado" class="form-control mb-3" placeholder="Estado" maxlength="2">

            <button type="button" class="btn btn-outline-secondary w-100 mb-2" onclick="voltar()">Voltar</button>
            <button type="button" class="btn btn-success w-100" onclick="irPara(4)">Continuar</button>
        </div>

        <div class="card-box step" id="step4">
            <h6>Origem *</h6>
            <div>
                <?php
                $origens = ['gabinete', 'visita_rua', 'instagram', 'evento', 'facebook', 'outros'];
                foreach ($origens as $o) {
                    echo "<button type='button' class='select-btn' onclick=\"selectOption('origem','$o',this)\">$o</button>";
                }
                ?>
            </div>

            <h6 class="mt-3">Categoria *</h6>
            <div>
                <?php
                $cats = ['saude', 'educacao', 'infraestrutura', 'assistencia_social', 'bairro', 'outros'];
                foreach ($cats as $c) {
                    echo "<button type='button' class='select-btn' onclick=\"selectOption('categoria','$c',this)\">$c</button>";
                }
                ?>
            </div>

            <h6 class="mt-3">Código da demanda</h6>
            <div class="mb-3" id="codigoDemandaOpcoes">
                <?php for ($i = 1; $i <= 10; $i++):
                    $codigo = 'DM-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                ?>
                    <button type="button" class="select-btn" onclick="selectCodigoDemanda('<?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?>', this)">
                        <?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?>
                    </button>
                <?php endfor; ?>
            </div>

            <label class="mt-3">Título *</label>
            <input type="text" name="titulo" id="titulo" class="form-control mb-3" required>

            <label>Informações *</label>
            <textarea name="descricao" id="descricao" class="form-control" rows="5" required></textarea>
            <div class="char-counter"><span id="contador">0</span> caracteres</div>

            <label class="mt-3">Mídias (até 3)</label>
            <input type="file" name="midias[]" id="midias" class="form-control" multiple accept="image/*,video/*,audio/*,.pdf">
            <div class="preview" id="preview"></div>

            <button type="button" class="btn btn-outline-secondary w-100 mt-3 mb-2" onclick="voltar()">Voltar</button>
            <button type="submit" class="btn btn-success w-100">Enviar Demanda</button>
        </div>
    </form>
</div>

<div class="modal fade" id="modalConfirmarDemanda" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-4 text-center">
            <div class="confirm-icon">?</div>
            <h5 class="fw-bold mb-2">Cadastrar demanda?</h5>
            <p class="text-muted mb-4">Confirme antes de enviar. A demanda só será cadastrada depois da confirmação.</p>

            <div class="d-grid gap-2">
                <button type="button" class="btn btn-success btn-lg" id="btnConfirmarCadastro">Sim, cadastrar demanda</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Não, voltar para editar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDemandaRecente" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-4">
            <div class="text-center">
                <div class="confirm-icon" style="background:#ffedd5;color:#c2410c">!</div>
                <h5 class="fw-bold mb-2">Já existe demanda recente</h5>
                <p class="text-muted mb-3">Encontramos demanda para este telefone nos últimos 7 dias. Deseja continuar mesmo assim?</p>
            </div>

            <div class="recent-box mb-3" id="demandasRecentesBox"></div>

            <div class="d-grid gap-2">
                <button type="button" class="btn btn-warning btn-lg" id="btnContinuarMesmoAssim">Sim, continuar cadastro</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Não, voltar para editar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalSalvandoDemanda" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-4 text-center">
            <div id="estadoCarregando">
                <div class="loading-spinner"></div>
                <h5 class="fw-bold mb-2">Cadastrando demanda...</h5>
                <p class="text-muted mb-0">Aguarde. Não feche esta tela até finalizar.</p>
            </div>

            <div id="estadoSucesso" style="display:none">
                <div class="success-icon">✓</div>
                <h5 class="fw-bold mb-2">Demanda cadastrada com sucesso</h5>
                <p class="text-muted mb-3" id="sucessoTexto">A demanda foi registrada.</p>
                <a href="demandas.php" class="btn btn-success btn-lg w-100">Sair</a>
            </div>

            <div id="estadoErro" style="display:none">
                <div class="confirm-icon" style="background:#fee2e2;color:#b91c1c">!</div>
                <h5 class="fw-bold mb-2">Erro ao cadastrar</h5>
                <p class="text-muted mb-3" id="erroTexto">Não foi possível salvar a demanda.</p>
                <button type="button" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">Voltar para corrigir</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
let stepAtual = 1;
let envioConfirmado = false;
let podeIgnorarRecente = false;

const modalConfirmar = new bootstrap.Modal(document.getElementById('modalConfirmarDemanda'));
const modalRecente = new bootstrap.Modal(document.getElementById('modalDemandaRecente'));
const modalSalvando = new bootstrap.Modal(document.getElementById('modalSalvandoDemanda'));

function irPara(n){
    if (!document.getElementById('step' + n)) return;
    document.getElementById('step' + stepAtual).classList.remove('active');
    stepAtual = n;
    document.getElementById('step' + stepAtual).classList.add('active');
}

function voltar(){
    if (stepAtual > 1) {
        document.getElementById('step' + stepAtual).classList.remove('active');
        stepAtual--;
        document.getElementById('step' + stepAtual).classList.add('active');
    }
}

function verificarTelefone(){
    let tel = document.getElementById('telefone_busca').value.replace(/\D/g,'');
    if (tel.length < 10) {
        alert('Telefone inválido');
        return;
    }

    fetch('ajax-verificar-telefone.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'telefone=' + encodeURIComponent(tel)
    })
    .then(r => r.json())
    .then(data => {
        let box = document.getElementById('resultadoPessoa');
        box.innerHTML = '';

        if (!data || data.ok === false) {
            alert('Não foi possível verificar o telefone agora.');
            return;
        }

        if (!data.existe) {
            box.innerHTML = `<div class="status-card">
                Pessoa não encontrada
                <button type="button" class="btn btn-light w-100 mt-2" onclick="novaPessoa('${tel}')">
                    Criar nova pessoa
                </button>
            </div>`;
            return;
        }

        let p = data.pessoa || {};
        let e = data.endereco || {};

        let payload = encodeURIComponent(JSON.stringify({
            id: p.id || '',
            telefone: tel,
            nome: p.nome || '',
            apelido: p.apelido || '',
            data: p.data_nascimento || '',
            endereco: e
        }));

        box.innerHTML = `
            <div class="status-card">
                <div class="fw-bold">${escapeHtml(p.nome || 'Pessoa encontrada')}</div>
                <button type="button"
                        class="btn btn-light w-100 mt-2 btn-usar"
                        data-payload="${payload}">
                    Usar cadastro
                </button>
            </div>
        `;
    })
    .catch(() => {
        alert('Erro ao verificar telefone.');
    });
}

document.addEventListener('click', function(e){
    if (e.target.classList.contains('btn-usar')) {
        let dados = JSON.parse(decodeURIComponent(e.target.dataset.payload));
        usarCadastro(dados);
    }
});

function usarCadastro(d){
    document.getElementById('pessoa_id').value = d.id || '';
    document.getElementById('pessoa_existente').value = 1;
    document.getElementById('telefone').value = d.telefone || '';
    document.getElementById('nome').value = d.nome || '';
    document.getElementById('apelido').value = d.apelido || '';
    document.getElementById('data_input').value = d.data || '';
    document.getElementById('data_nascimento').value = d.data || '';

    if (d.endereco && d.endereco.possui_endereco && d.endereco.dados) {
        document.getElementById('cep').value         = d.endereco.dados.cep ?? '';
        document.getElementById('endereco').value    = d.endereco.dados.endereco ?? '';
        document.getElementById('numero').value      = d.endereco.dados.numero ?? '';
        document.getElementById('complemento').value = d.endereco.dados.complemento ?? '';
        document.getElementById('bairro').value      = d.endereco.dados.bairro ?? '';
        document.getElementById('cidade').value      = d.endereco.dados.cidade ?? '';
        document.getElementById('estado').value      = d.endereco.dados.estado ?? '';
    } else {
        setSemEndereco();
    }

    irPara(2);
}

function novaPessoa(tel){
    document.getElementById('pessoa_id').value = '';
    document.getElementById('pessoa_existente').value = 0;
    document.getElementById('telefone').value = tel || '';
    document.getElementById('nome').value = '';
    document.getElementById('apelido').value = '';
    document.getElementById('data_input').value = '';
    document.getElementById('data_nascimento').value = '';
    setSemEndereco();
    irPara(2);
}

function validarStep2(){
    let data = document.getElementById('data_input').value;
    let nome = document.getElementById('nome').value.trim();
    let telefone = document.getElementById('telefone').value.replace(/\D/g,'');

    if (!telefone || telefone.length < 10) {
        alert('Telefone inválido.');
        return;
    }

    if (!nome) {
        alert('Nome obrigatório.');
        return;
    }

    if (!data) {
        alert('Data obrigatória.');
        return;
    }

    document.getElementById('data_nascimento').value = data;
    irPara(3);
}

function setSemEndereco(){
    ['cep','endereco','numero','complemento','bairro','cidade','estado'].forEach(id => {
        document.getElementById(id).value = '';
    });
}

function selectOption(tipo, valor, btn){
    document.getElementById(tipo).value = valor;

    const container = btn.parentElement;
    container.querySelectorAll('.select-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}


function selectCodigoDemanda(valor, btn){
    document.getElementById('codigo_demanda').value = valor;

    const container = document.getElementById('codigoDemandaOpcoes');
    container.querySelectorAll('.select-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function escapeHtml(text){
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function validarFormularioFinal(){
    const origem = document.getElementById('origem').value.trim();
    const categoria = document.getElementById('categoria').value.trim();
    const titulo = document.getElementById('titulo').value.trim();
    const descricao = document.getElementById('descricao').value.trim();

    if (!origem) {
        alert('Selecione a origem da demanda.');
        return false;
    }

    if (!categoria) {
        alert('Selecione a categoria da demanda.');
        return false;
    }

    if (!titulo) {
        alert('Informe o título da demanda.');
        return false;
    }

    if (!descricao) {
        alert('Informe as informações da demanda.');
        return false;
    }

    return true;
}

function renderDemandasRecentes(lista){
    const box = document.getElementById('demandasRecentesBox');

    if (!lista || !lista.length) {
        box.innerHTML = 'Nenhum detalhe encontrado.';
        return;
    }

    box.innerHTML = `
        <strong>Demandas encontradas:</strong>
        ${lista.map(d => `
            <div class="recent-item">
                <div><strong>#${escapeHtml(d.protocolo || d.id || '')}</strong> — ${escapeHtml(d.titulo || 'Sem título')}</div>
                <div class="small">Status: ${escapeHtml(d.status || '-')} · Criada em: ${escapeHtml(d.criado_em || '-')}</div>
            </div>
        `).join('')}
    `;
}

async function checarDemandasRecentes(){
    const telefone = document.getElementById('telefone').value.replace(/\D/g,'');

    const res = await fetch('ajax-demandas-recentes.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'telefone=' + encodeURIComponent(telefone)
    });

    return await res.json();
}

function mostrarEstadoSalvando(){
    document.getElementById('estadoCarregando').style.display = '';
    document.getElementById('estadoSucesso').style.display = 'none';
    document.getElementById('estadoErro').style.display = 'none';
}

function mostrarEstadoSucesso(data){
    document.getElementById('estadoCarregando').style.display = 'none';
    document.getElementById('estadoErro').style.display = 'none';
    document.getElementById('estadoSucesso').style.display = '';

    const protocolo = data.protocolo ? ` Protocolo: ${data.protocolo}.` : '';
    document.getElementById('sucessoTexto').innerText = 'A demanda foi registrada com sucesso.' + protocolo;
}

function mostrarEstadoErro(msg){
    document.getElementById('estadoCarregando').style.display = 'none';
    document.getElementById('estadoSucesso').style.display = 'none';
    document.getElementById('estadoErro').style.display = '';
    document.getElementById('erroTexto').innerText = msg || 'Não foi possível salvar a demanda.';
}

async function salvarDemandaAjax(){
    mostrarEstadoSalvando();
    modalSalvando.show();

    try {
        const form = document.getElementById('formNova');
        const fd = new FormData(form);
        fd.append('ajax', '1');

        const res = await fetch(form.action, {
            method: 'POST',
            body: fd,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });

        const data = await res.json();

        if (!res.ok || !data.ok) {
            throw new Error(data.erro || data.mensagem || 'Erro ao salvar demanda.');
        }

        mostrarEstadoSucesso(data);
    } catch (err) {
        mostrarEstadoErro(err.message || 'Erro ao salvar demanda.');
    }
}

document.getElementById('btnConfirmarCadastro').addEventListener('click', async function(){
    modalConfirmar.hide();

    if (!podeIgnorarRecente) {
        try {
            const data = await checarDemandasRecentes();

            if (data && data.ok && data.tem_recente) {
                renderDemandasRecentes(data.demandas || []);
                modalRecente.show();
                return;
            }
        } catch (e) {
            alert('Não foi possível verificar demandas recentes. Tente novamente.');
            return;
        }
    }

    salvarDemandaAjax();
});

document.getElementById('btnContinuarMesmoAssim').addEventListener('click', function(){
    podeIgnorarRecente = true;
    modalRecente.hide();
    salvarDemandaAjax();
});

document.getElementById('descricao').addEventListener('input', function(){
    let len = this.value.length;
    document.getElementById('contador').innerText = len;
});

document.getElementById('midias').addEventListener('change', function(){
    let preview = document.getElementById('preview');
    preview.innerHTML = '';

    [...this.files].slice(0, 3).forEach(file => {
        let reader = new FileReader();
        reader.onload = e => {
            if (file.type.startsWith('video/')) {
                preview.innerHTML += `<video controls src="${e.target.result}"></video>`;
            } else if (file.type.startsWith('image/')) {
                preview.innerHTML += `<img src="${e.target.result}">`;
            } else {
                preview.innerHTML += `<div class="mt-2 small text-muted">Arquivo selecionado: ${escapeHtml(file.name)}</div>`;
            }
        };
        reader.readAsDataURL(file);
    });
});

document.getElementById('cep').addEventListener('blur', function(){
    let cep = this.value.replace(/\D/g,'');
    if (cep.length !== 8) return;

    fetch('https://viacep.com.br/ws/' + cep + '/json/')
    .then(r => r.json())
    .then(data => {
        if (!data.erro) {
            document.getElementById('endereco').value = data.logradouro || '';
            document.getElementById('bairro').value = data.bairro || '';
            document.getElementById('cidade').value = data.localidade || '';
            document.getElementById('estado').value = data.uf || '';
        }
    })
    .catch(() => {});
});

document.getElementById('formNova').addEventListener('submit', function(e){
    e.preventDefault();

    if (!validarFormularioFinal()) {
        return;
    }

    modalConfirmar.show();
});
</script>

</body>
</html>