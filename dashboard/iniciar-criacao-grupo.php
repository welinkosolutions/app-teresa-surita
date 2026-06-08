<?php
session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    header('Location: index.php');
    exit;
}

header("Refresh:0.2; url=criar-grupo.php");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Preparando criação</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f4f6f9;
    font-family:system-ui,-apple-system,BlinkMacSystemFont,sans-serif;
}
.card{
    max-width:420px;
    margin:120px auto;
    border-radius:16px;
    box-shadow:0 6px 20px rgba(0,0,0,.08);
}
</style>
</head>
<body>

<div class="card">
<div class="card-body text-center">

    <div class="spinner-border text-primary mb-3"></div>

    <h5>Preparando seu grupo</h5>
    <p class="text-muted mb-0">
        Estamos iniciando o processo…
    </p>

</div>
</div>

</body>
</html>
