<?php
/**
 * ======================================================
 * CAMINHO: crm.elab.social/final-pam.php
 * NOME: Sistema Bloqueado – PAM Ativo
 * DESCRIÇÃO:
 * - Tela final após ativação do PAM
 * - Estado consolidado de contenção
 * - Sem interações
 * - Mobile + Desktop
 * ======================================================
 */
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>SISTEMA BLOQUEADO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            margin: 0;
            background: #0b0b0b;
            color: #e5e7eb;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .pam-box {
            width: 100%;
            max-width: 560px;
            background: #111;
            border: 1px solid #1f2933;
            border-radius: 14px;
            padding: 28px 26px;
            box-shadow: 0 20px 50px rgba(0,0,0,.6);
            text-align: center;
        }

        .pam-icon {
            font-size: 48px;
            margin-bottom: 14px;
            color: #ef4444;
        }

        h1 {
            font-size: clamp(20px, 5vw, 24px);
            margin-bottom: 12px;
            font-weight: 700;
            letter-spacing: .5px;
        }

        .status {
            font-size: 14px;
            color: #9ca3af;
            margin-bottom: 22px;
        }

        .info {
            text-align: left;
            background: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 10px;
            padding: 16px;
            font-size: 14px;
            line-height: 1.6;
            color: #e5e7eb;
        }

        .info strong {
            color: #f8fafc;
        }

        .footer {
            margin-top: 22px;
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>

<div class="pam-box">

    <div class="pam-icon">☠️</div>

    <h1>SISTEMA DELETADO</h1>

    <div class="status">
        Protocolo de Proteção e Contenção ativo
    </div>

    <div class="footer">
        elab.social • segurança operacional
    </div>

</div>

</body>
</html>
