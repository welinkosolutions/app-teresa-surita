<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pessoa_id = (int)$_SESSION['pessoa_id'];
$pdo = dbRoraima();

/*
========================================
SALVAR
========================================
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome            = trim((string)($_POST['nome'] ?? ''));
    $apelido         = trim((string)($_POST['apelido'] ?? ''));
    $nomeMae         = trim((string)($_POST['nome_mae'] ?? ''));
    $dataNascimento  = trim((string)($_POST['data_nascimento'] ?? ''));
    $telefone        = preg_replace('/\D+/', '', (string)($_POST['telefone'] ?? ''));
    $email           = trim((string)($_POST['email'] ?? ''));
    $instagram       = trim((string)($_POST['instagram'] ?? ''));
    $facebook        = trim((string)($_POST['facebook'] ?? ''));

    if ($nome !== '') {
        $stmt = $pdo->prepare("
            UPDATE pessoas
            SET
                nome = ?,
                apelido = ?,
                nome_mae = ?,
                data_nascimento = ?,
                telefone = ?,
                email = ?,
                instagram = ?,
                facebook = ?,
                atualizado_em = NOW()
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $nome,
            $apelido !== '' ? $apelido : null,
            $nomeMae !== '' ? $nomeMae : null,
            $dataNascimento !== '' ? $dataNascimento : null,
            $telefone !== '' ? $telefone : null,
            $email !== '' ? $email : null,
            $instagram !== '' ? $instagram : null,
            $facebook !== '' ? $facebook : null,
            $pessoa_id
        ]);
    }

    header('Location: /pessoas/perfil.php');
    exit;
}

/*
========================================
BUSCAR DADOS
========================================
*/
$stmt = $pdo->prepare("
    SELECT
        nome,
        apelido,
        nome_mae,
        data_nascimento,
        telefone,
        email,
        instagram,
        facebook
    FROM pessoas
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$pessoa_id]);
$pessoa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pessoa) {
    exit('Perfil não encontrado.');
}

/*
========================================
TELEFONE FORMATADO
========================================
*/
$telefoneFormatado = '';
$telefoneRaw = preg_replace('/\D+/', '', (string)($pessoa['telefone'] ?? ''));

if (strlen($telefoneRaw) === 11) {
    $telefoneFormatado = sprintf(
        '(%s) %s-%s',
        substr($telefoneRaw, 0, 2),
        substr($telefoneRaw, 2, 5),
        substr($telefoneRaw, 7)
    );
} elseif (strlen($telefoneRaw) === 10) {
    $telefoneFormatado = sprintf(
        '(%s) %s-%s',
        substr($telefoneRaw, 0, 2),
        substr($telefoneRaw, 2, 4),
        substr($telefoneRaw, 6)
    );
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editar Perfil | ELAB Social</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root{
  --bg:#eef2f5;
  --card:#ffffff;
  --text:#102331;
  --muted:#607080;
  --brand1:#0b6e7a;
  --brand2:#1aa8b2;
  --success:#169d93;
  --success-dark:#15897f;
  --line:#e6edf3;
  --shadow:0 12px 28px rgba(16,35,49,.10);
  --radius:24px;
}

*{
  box-sizing:border-box;
}

body{
  margin:0;
  background:linear-gradient(180deg,#eef2f5 0%, #e9eef2 100%);
  font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
  color:var(--text);
}

.page-wrap{
  max-width:680px;
  margin:0 auto;
  padding:18px 18px 110px;
}

.card-box{
  background:var(--card);
  border-radius:26px;
  padding:18px;
  margin-bottom:18px;
  box-shadow:var(--shadow);
}

.page-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  margin-bottom:18px;
}

.btn-back{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:44px;
  padding:0 16px;
  border-radius:999px;
  border:1px solid #d9e3ec;
  background:#fff;
  color:#173247;
  text-decoration:none;
  font-size:14px;
  font-weight:1000;
  box-shadow:0 8px 18px rgba(16,35,49,.08);
}

.btn-back:hover{
  color:#173247;
  text-decoration:none;
}

.page-title-wrap{
  flex:1;
  min-width:0;
  text-align:right;
}

.page-title{
  margin:0;
  font-size:24px;
  line-height:1.05;
  font-weight:1000;
  color:#173247;
}

.page-subtitle{
  margin-top:4px;
  font-size:13px;
  color:var(--muted);
  font-weight:800;
}

.card-title{
  margin:0 0 14px;
  font-size:17px;
  font-weight:1000;
  color:#1b2d3a;
}

.field-group + .field-group{
  margin-top:12px;
}

.form-label{
  display:block;
  margin-bottom:6px;
  font-size:13px;
  font-weight:900;
  color:#223846;
}

.form-control{
  min-height:50px;
  border-radius:16px;
  border:1px solid #d9e3ec;
  background:#fdfefe;
  color:#173247;
  font-size:15px;
  padding:12px 14px;
  box-shadow:none;
}

.form-control:focus{
  border-color:#169d93;
  box-shadow:0 0 0 3px rgba(22,157,147,.10);
}

.actions{
  display:flex;
  flex-direction:column;
  gap:10px;
}

.btn-profile{
  width:100%;
  min-height:54px;
  border-radius:16px;
  border:none;
  text-decoration:none;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:16px;
  font-weight:1000;
  transition:.18s ease;
}

.btn-profile.main{
  background:linear-gradient(135deg,#169d93,#15897f);
  color:#fff;
  box-shadow:0 12px 24px rgba(21,137,127,.18);
}

.btn-profile.outline{
  background:#fff;
  color:#15897f;
  border:2px solid #15897f;
}

.btn-profile:hover{
  text-decoration:none;
}

.btn-profile:active{
  transform:scale(.985);
}

@media (max-width:420px){
  .page-wrap{
    padding-left:14px;
    padding-right:14px;
  }

  .page-title{
    font-size:21px;
  }

  .btn-back{
    min-height:42px;
    padding:0 14px;
    font-size:13px;
  }
}
</style>
</head>
<body>

<div class="page-wrap">

  <div class="page-head">
    <a href="/pessoas/perfil.php" class="btn-back">← Voltar</a>

    <div class="page-title-wrap">
      <h1 class="page-title">Editar perfil</h1>
      <div class="page-subtitle">Atualize seus dados pessoais</div>
    </div>
  </div>

  <form method="post" autocomplete="off">

    <div class="card-box">
      <h2 class="card-title">Dados pessoais</h2>

      <div class="field-group">
        <label class="form-label" for="nome">Nome completo</label>
        <input
          type="text"
          name="nome"
          id="nome"
          class="form-control"
          value="<?= htmlspecialchars((string)($pessoa['nome'] ?? '')) ?>"
          required
        >
      </div>

      <div class="field-group">
        <label class="form-label" for="apelido">Apelido</label>
        <input
          type="text"
          name="apelido"
          id="apelido"
          class="form-control"
          value="<?= htmlspecialchars((string)($pessoa['apelido'] ?? '')) ?>"
        >
      </div>

      <div class="field-group">
        <label class="form-label" for="nome_mae">Nome da mãe</label>
        <input
          type="text"
          name="nome_mae"
          id="nome_mae"
          class="form-control"
          value="<?= htmlspecialchars((string)($pessoa['nome_mae'] ?? '')) ?>"
        >
      </div>

      <div class="field-group">
        <label class="form-label" for="data_nascimento">Data de nascimento</label>
        <input
          type="date"
          name="data_nascimento"
          id="data_nascimento"
          class="form-control"
          value="<?= htmlspecialchars((string)($pessoa['data_nascimento'] ?? '')) ?>"
        >
      </div>
    </div>

    <div class="card-box">
      <h2 class="card-title">Contatos</h2>

      <div class="field-group">
        <label class="form-label" for="telefone">Telefone</label>
        <input
          type="text"
          name="telefone"
          id="telefone"
          class="form-control"
          inputmode="numeric"
          placeholder="(95) 99999-9999"
          value="<?= htmlspecialchars($telefoneFormatado) ?>"
        >
      </div>

      <div class="field-group">
        <label class="form-label" for="email">E-mail</label>
        <input
          type="email"
          name="email"
          id="email"
          class="form-control"
          value="<?= htmlspecialchars((string)($pessoa['email'] ?? '')) ?>"
        >
      </div>
    </div>

    <div class="card-box">
      <h2 class="card-title">Redes sociais</h2>

      <div class="field-group">
        <label class="form-label" for="instagram">Instagram</label>
        <input
          type="text"
          name="instagram"
          id="instagram"
          class="form-control"
          placeholder="@usuario ou link"
          value="<?= htmlspecialchars((string)($pessoa['instagram'] ?? '')) ?>"
        >
      </div>

      <div class="field-group">
        <label class="form-label" for="facebook">Facebook</label>
        <input
          type="text"
          name="facebook"
          id="facebook"
          class="form-control"
          placeholder="perfil ou link"
          value="<?= htmlspecialchars((string)($pessoa['facebook'] ?? '')) ?>"
        >
      </div>
    </div>

    <div class="card-box">
      <div class="actions">
        <button type="submit" class="btn-profile main">Salvar alterações</button>
        <a href="/pessoas/perfil.php" class="btn-profile outline">Cancelar</a>
      </div>
    </div>

  </form>

</div>

<script>
const tel = document.getElementById('telefone');

if (tel) {
  tel.addEventListener('input', () => {
    let v = tel.value.replace(/\D/g, '').slice(0, 11);

    if (v.length > 10) {
      tel.value = `(${v.slice(0,2)}) ${v.slice(2,7)}-${v.slice(7)}`;
    } else if (v.length > 6) {
      tel.value = `(${v.slice(0,2)}) ${v.slice(2,6)}-${v.slice(6)}`;
    } else if (v.length > 2) {
      tel.value = `(${v.slice(0,2)}) ${v.slice(2)}`;
    } else {
      tel.value = v;
    }
  });
}
</script>

</body>
</html>