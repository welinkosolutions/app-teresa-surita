<?php
declare(strict_types=1);

/**
 * ======================================================
 * CAMINHO: /home/elab/app.elab.social/pessoas/editar.php
 * NOME: Editar Cadastro V2
 *
 * DESCRIÇÃO:
 * - Edita dados principais da pessoa logada
 * - Carrega todos os dados com SELECT *
 * - Edita endereço em pessoas_enderecos
 * - Preenche endereço via API ViaCEP
 * - Permite enviar nova senha por e-mail usando core/data/mailer.php
 * - Layout V2 gamificado integrado ao footer fixo
 * ======================================================
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';
require_once '/home/elab/public_html/core/data/mailer.php';

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pdo = dbRoraima();
$pessoaId = (int) $_SESSION['pessoa_id'];

function edEsc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function edFlashSet(string $tipo, string $mensagem): void
{
    $_SESSION['pessoas_editar_flash'] = [
        'tipo' => $tipo,
        'mensagem' => $mensagem,
    ];
}

function edFlashGet(): ?array
{
    if (empty($_SESSION['pessoas_editar_flash']) || !is_array($_SESSION['pessoas_editar_flash'])) {
        return null;
    }

    $flash = $_SESSION['pessoas_editar_flash'];
    unset($_SESSION['pessoas_editar_flash']);

    return $flash;
}

function edGarantirCsrf(): string
{
    if (empty($_SESSION['pessoas_editar_csrf']) || !is_string($_SESSION['pessoas_editar_csrf'])) {
        $_SESSION['pessoas_editar_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['pessoas_editar_csrf'];
}

function edValidarCsrf(?string $token): bool
{
    $sess = (string) ($_SESSION['pessoas_editar_csrf'] ?? '');
    $token = (string) $token;

    if ($sess === '' || $token === '') {
        return false;
    }

    return hash_equals($sess, $token);
}

function edColunaExiste(PDO $pdo, string $tabela, string $coluna): bool
{
    static $cache = [];

    $key = $tabela . '.' . $coluna;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$tabela` LIKE ?");
        $stmt->execute([$coluna]);
        $cache[$key] = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

function edNomeExibicao(array $pessoa): string
{
    $nome = trim((string) ($pessoa['nome'] ?? ''));
    $apelido = trim((string) ($pessoa['apelido'] ?? ''));
    $chamarPor = trim((string) ($pessoa['chamar_por'] ?? ''));

    if ($chamarPor === 'apelido' && $apelido !== '') {
        return $apelido;
    }

    if ($nome === '') {
        return 'Participante';
    }

    $partes = preg_split('/\s+/', $nome);
    $curto = trim(implode(' ', array_slice($partes ?: [], 0, 2)));

    return $curto !== '' ? $curto : $nome;
}

function edNormalizarHandle(string $valor): string
{
    $valor = trim($valor);
    $valor = ltrim($valor, '@');
    $valor = strtolower($valor);
    $valor = preg_replace('/[^a-z0-9._]/', '', $valor) ?: '';
    $valor = preg_replace('/\.{2,}/', '.', $valor) ?: '';
    $valor = trim($valor, '._');

    return $valor;
}

function edNormalizarInstagram(string $valor): string
{
    $valor = trim($valor);
    $valor = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $valor) ?: $valor;
    $valor = trim($valor, '/');
    $valor = ltrim($valor, '@');

    return $valor;
}

function edNormalizarTiktok(string $valor): string
{
    $valor = trim($valor);
    $valor = preg_replace('#^https?://(www\.)?tiktok\.com/@?#i', '', $valor) ?: $valor;
    $valor = trim($valor, '/');
    $valor = ltrim($valor, '@');

    return $valor;
}

function edHandleDisponivel(PDO $pdo, string $handle, int $pessoaId): bool
{
    if (!edColunaExiste($pdo, 'pessoas', 'usuario_handle')) {
        return true;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM pessoas
        WHERE usuario_handle = ?
          AND id <> ?
        LIMIT 1
    ");
    $stmt->execute([$handle, $pessoaId]);

    return !(bool) $stmt->fetchColumn();
}

function edLogSenha(string $tipo, string $mensagem): void
{
    $logPath = '/home/elab/logs/app-editar-senha.log';

    @file_put_contents(
        $logPath,
        date('Y-m-d H:i:s') .
        ' | ' . $tipo .
        ' | ' . $mensagem .
        ' | IP=' . ($_SERVER['REMOTE_ADDR'] ?? '-') . PHP_EOL,
        FILE_APPEND
    );
}

function edNovoPin(): string
{
    return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
}

function edNomeCurtoSenha(string $nome): string
{
    $nome = trim(preg_replace('/\s+/', ' ', $nome));

    if ($nome === '') {
        return 'amigo(a)';
    }

    $partes = explode(' ', $nome);

    return trim($partes[0] ?? $nome);
}

function edMaskEmailSenha(string $email): string
{
    $email = trim($email);

    if ($email === '' || !str_contains($email, '@')) {
        return $email;
    }

    [$user, $domain] = explode('@', $email, 2);

    $userMasked = strlen($user) <= 2
        ? substr($user, 0, 1) . '*'
        : substr($user, 0, 2) . str_repeat('*', max(2, strlen($user) - 2));

    return $userMasked . '@' . $domain;
}

function edRegistrarAuditoriaSenha(PDO $pdo, int $pessoaId, string $descricao): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO auditoria_interna
                (pessoa_id, acao, tabela_afetada, descricao, ip)
            VALUES
                (?, 'reset_pin_email', 'pessoas', ?, ?)
        ");
        $stmt->execute([
            $pessoaId,
            $descricao,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
        ]);
    } catch (Throwable $e) {
        edLogSenha('WARN', 'AUDITORIA_FALHOU ID=' . $pessoaId . ' MSG=' . $e->getMessage());
    }
}

function edEnviarNovoPinPorEmail(string $email, string $nome, string $novoPin): bool
{
    $nomeExibicao = trim($nome) !== '' ? trim($nome) : 'Olá';
    $nomeCurto = edNomeCurtoSenha($nomeExibicao);
    $assunto = 'Sua NOVA SENHA de acesso';

    $loginUrl = 'https://v3.elab.social/publico/login.php';

    $html = '
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sua NOVA SENHA de acesso</title>
    </head>
    <body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#20303a;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f3f4f6;margin:0;padding:14px 0;">
            <tr>
                <td align="center" style="padding:0 10px;">

                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 10px 24px rgba(0,0,0,.08);">
                        <tr>
                            <td style="padding:22px 18px 20px; text-align:center;">

                                <div style="font-size:16px;font-weight:600;line-height:1.45;color:#00221c;margin-bottom:14px;text-align:center;">
                                    🔐 Olá, <strong>' . htmlspecialchars($nomeCurto, ENT_QUOTES, 'UTF-8') . '</strong>.<br>
                                    Sua nova senha foi gerada com sucesso!
                                </div>

                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 14px;">
                                    <tr>
                                        <td style="background:#fbf3d9;border:1px solid #efd980;border-radius:18px;padding:18px 14px;text-align:center;">
                                            <div style="font-size:16px;font-weight:800;color:#9b7300;margin-bottom:10px;">
                                                SENHA DE ACESSO AO APLICATIVO
                                            </div>

                                            <div style="font-size:50px;line-height:1;font-weight:900;color:#d40000;letter-spacing:10px;font-family:Arial Black,Arial,Helvetica,sans-serif;margin-bottom:12px;">
                                                ' . htmlspecialchars($novoPin, ENT_QUOTES, 'UTF-8') . '
                                            </div>

                                            <div style="font-size:14px;line-height:1.6;color:#7a6420;max-width:380px;margin:0 auto;">
                                                Guarde essa senha agora. Salve, copie ou tire um print deste e-mail.
                                            </div>
                                        </td>
                                    </tr>
                                </table>

                                <div style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#2f2f2f;font-weight:700;">
                                    No celular, toque e segure sobre a senha para copiar.
                                </div>

                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 14px;">
                                    <tr>
                                        <td align="center">
                                            <a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '" style="display:block;background:#1f8f4f;color:#ffffff;text-decoration:none;font-size:18px;font-weight:800;padding:15px 20px;border-radius:14px;">
                                                ➜ Ir para o login
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                                <div style="font-size:14px;line-height:1.7;color:#6b7280;text-align:center;max-width:430px;margin:0 auto;">
                                    Se você não pediu essa senha, ignore este e-mail.
                                </div>
                            </td>
                        </tr>
                    </table>

                </td>
            </tr>
        </table>
    </body>
    </html>';

    $texto =
        "Oi, {$nomeCurto}!\n\n" .
        "Sua nova senha foi gerada com sucesso!\n\n" .
        "Sua NOVA SENHA: {$novoPin}\n\n" .
        "No celular, toque e segure sobre a senha para copiar.\n\n" .
        "Login: {$loginUrl}\n\n";

    return sendMail($email, $nomeExibicao, $assunto, $html, $texto);
}

function edTelefoneFormatado(?string $telefone): string
{
    $raw = preg_replace('/\D+/', '', (string) $telefone) ?: '';

    if (strlen($raw) === 11) {
        return sprintf(
            '(%s) %s-%s',
            substr($raw, 0, 2),
            substr($raw, 2, 5),
            substr($raw, 7)
        );
    }

    if (strlen($raw) === 10) {
        return sprintf(
            '(%s) %s-%s',
            substr($raw, 0, 2),
            substr($raw, 2, 4),
            substr($raw, 6)
        );
    }

    return $raw;
}

function edCepFormatado(?string $cep): string
{
    $raw = preg_replace('/\D+/', '', (string) $cep) ?: '';

    if (strlen($raw) === 8) {
        return substr($raw, 0, 5) . '-' . substr($raw, 5);
    }

    return $raw;
}

function edValorInstagram(array $pessoa): string
{
    $valor = trim((string) ($pessoa['instagram_username'] ?? ''));

    if ($valor === '') {
        $valor = trim((string) ($pessoa['instagram_user'] ?? ''));
    }

    if ($valor === '') {
        $valor = trim((string) ($pessoa['instagram'] ?? ''));
    }

    return edNormalizarInstagram($valor);
}

function edValorTiktok(array $pessoa): string
{
    $valor = trim((string) ($pessoa['tiktok_username'] ?? ''));

    if ($valor === '') {
        $valor = trim((string) ($pessoa['tiktok'] ?? ''));
    }

    return edNormalizarTiktok($valor);
}

function edValorFacebook(array $pessoa): string
{
    $valor = trim((string) ($pessoa['facebook_username'] ?? ''));

    if ($valor === '') {
        $valor = trim((string) ($pessoa['facebook'] ?? ''));
    }

    return $valor;
}

$csrfToken = edGarantirCsrf();

/*
========================================
USUÁRIO LOGADO
========================================
*/
$stmt = $pdo->prepare("
    SELECT *
    FROM pessoas
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$pessoaId]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$usuario || ($usuario['status'] ?? '') !== 'ativo') {
    header('Location: /publico/logout.php');
    exit;
}

$nomeUsuario = edNomeExibicao($usuario);

/*
========================================
POST - SALVAR / ENVIAR SENHA
========================================
*/
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!edValidarCsrf($_POST['csrf_token'] ?? null)) {
        edFlashSet('erro', 'Falha de segurança na ação. Recarregue a página e tente novamente.');
        header('Location: /pessoas/editar.php');
        exit;
    }

    $acao = trim((string) ($_POST['acao'] ?? 'salvar'));

    if ($acao === 'enviar_senha_email') {
        try {
            $stmt = $pdo->prepare("
                SELECT id, nome, email, status
                FROM pessoas
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$pessoaId]);
            $pessoaSenha = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pessoaSenha) {
                throw new RuntimeException('Cadastro não encontrado.');
            }

            if (($pessoaSenha['status'] ?? '') !== 'ativo') {
                throw new RuntimeException('Esse cadastro está temporariamente indisponível para recuperação.');
            }

            $emailDestino = trim((string) ($pessoaSenha['email'] ?? ''));
            $nomePessoa = trim((string) ($pessoaSenha['nome'] ?? ''));

            if ($emailDestino === '' || !filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Cadastre um e-mail válido antes de solicitar uma nova senha.');
            }

            $novoPin = edNovoPin();
            $novoHash = password_hash($novoPin, PASSWORD_DEFAULT);

            $pdo->beginTransaction();

            $setsSenha = ['pin = ?'];
            $paramsSenha = [$novoHash];

            if (edColunaExiste($pdo, 'pessoas', 'pin_tentativas')) {
                $setsSenha[] = 'pin_tentativas = 0';
            }

            if (edColunaExiste($pdo, 'pessoas', 'pin_bloqueado_em')) {
                $setsSenha[] = 'pin_bloqueado_em = NULL';
            }

            if (edColunaExiste($pdo, 'pessoas', 'atualizado_em')) {
                $setsSenha[] = 'atualizado_em = NOW()';
            }

            $paramsSenha[] = $pessoaId;

            $stmt = $pdo->prepare("
                UPDATE pessoas
                SET " . implode(",\n                    ", $setsSenha) . "
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute($paramsSenha);

            edRegistrarAuditoriaSenha(
                $pdo,
                $pessoaId,
                'Nova SENHA enviada por e-mail através da edição de cadastro.'
            );

            if (!edEnviarNovoPinPorEmail($emailDestino, $nomePessoa, $novoPin)) {
                $pdo->rollBack();

                edLogSenha(
                    'ERRO',
                    'MAILER_FALHOU ID=' . $pessoaId . ' EMAIL=' . $emailDestino
                );

                edFlashSet('erro', 'Não foi possível enviar o e-mail agora. A senha não foi alterada.');
            } else {
                $pdo->commit();

                edLogSenha(
                    'OK',
                    'PIN_ENVIADO_EDITAR ID=' . $pessoaId . ' EMAIL=' . $emailDestino
                );

                edFlashSet(
                    'sucesso',
                    'Enviamos uma nova SENHA para ' . edMaskEmailSenha($emailDestino) . '. Verifique também a caixa de spam.'
                );
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            edLogSenha('ERRO', 'EXCEPTION ID=' . $pessoaId . ' MSG=' . $e->getMessage());

            edFlashSet(
                'erro',
                $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Não foi possível gerar a nova senha agora.'
            );
        }

        header('Location: /pessoas/editar.php');
        exit;
    }

    $nome = trim((string) ($_POST['nome'] ?? ''));
    $apelido = trim((string) ($_POST['apelido'] ?? ''));
    $usuarioHandle = edNormalizarHandle((string) ($_POST['usuario_handle'] ?? ''));
    $chamarPor = trim((string) ($_POST['chamar_por'] ?? 'nome'));
    $sexo = trim((string) ($_POST['sexo'] ?? ''));
    $nomeMae = trim((string) ($_POST['nome_mae'] ?? ''));
    $dataNascimento = trim((string) ($_POST['data_nascimento'] ?? ''));

    $telefone = preg_replace('/\D+/', '', (string) ($_POST['telefone'] ?? '')) ?: '';
    $email = trim((string) ($_POST['email'] ?? ''));

    $instagram = edNormalizarInstagram((string) ($_POST['instagram_username'] ?? ''));
    $tiktok = edNormalizarTiktok((string) ($_POST['tiktok_username'] ?? ''));
    $facebook = trim((string) ($_POST['facebook_username'] ?? ''));

    $cep = preg_replace('/\D+/', '', (string) ($_POST['cep'] ?? '')) ?: '';
    $enderecoRua = trim((string) ($_POST['endereco'] ?? ''));
    $numero = trim((string) ($_POST['numero'] ?? ''));
    $complemento = trim((string) ($_POST['complemento'] ?? ''));
    $referencia = trim((string) ($_POST['referencia'] ?? ''));
    $bairro = trim((string) ($_POST['bairro'] ?? ''));
    $cidade = trim((string) ($_POST['cidade'] ?? ''));
    $estado = strtoupper(trim((string) ($_POST['estado'] ?? '')));

    $localTrabalho = trim((string) ($_POST['local_trabalho'] ?? ''));
    $pontoReferencia = trim((string) ($_POST['ponto_referencia'] ?? ''));
    $transporte = trim((string) ($_POST['transporte'] ?? 'nenhum'));
    $cidadeVotacao = trim((string) ($_POST['cidade_votacao'] ?? ''));
    $estadoVotacao = strtoupper(trim((string) ($_POST['estado_votacao'] ?? '')));
    $observacoes = trim((string) ($_POST['observacoes'] ?? ''));

    $erros = [];

    if ($nome === '') {
        $erros[] = 'Informe seu nome completo.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail válido.';
    }

    if ($telefone !== '' && strlen($telefone) < 10) {
        $erros[] = 'Informe um telefone válido com DDD.';
    }

    if (!in_array($chamarPor, ['nome', 'apelido'], true)) {
        $chamarPor = 'nome';
    }

    if ($sexo !== '' && !in_array($sexo, ['M', 'F', 'O'], true)) {
        $sexo = '';
    }

    if ($usuarioHandle !== '') {
        if (strlen($usuarioHandle) < 3) {
            $erros[] = 'Seu usuário precisa ter pelo menos 3 caracteres.';
        } elseif (strlen($usuarioHandle) > 50) {
            $erros[] = 'Seu usuário pode ter no máximo 50 caracteres.';
        } elseif (!preg_match('/^[a-z0-9](?:[a-z0-9._]*[a-z0-9])?$/', $usuarioHandle)) {
            $erros[] = 'Use apenas letras, números, ponto ou underline no usuário.';
        } elseif (!edHandleDisponivel($pdo, $usuarioHandle, $pessoaId)) {
            $erros[] = 'Esse usuário já está em uso. Tente outro.';
        }
    }

    if ($dataNascimento !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataNascimento)) {
        $erros[] = 'Informe uma data de nascimento válida.';
    }

    if ($cep !== '' && strlen($cep) !== 8) {
        $erros[] = 'Informe um CEP válido com 8 números.';
    }

    if ($estado !== '' && strlen($estado) !== 2) {
        $erros[] = 'Informe o estado com 2 letras.';
    }

    if ($estadoVotacao !== '' && strlen($estadoVotacao) !== 2) {
        $erros[] = 'Informe o estado de votação com 2 letras.';
    }

    if (!in_array($transporte, ['nenhum', 'carro', 'moto', 'bicicleta', 'onibus', 'app', 'outro'], true)) {
        $transporte = 'nenhum';
    }

    if ($erros) {
        edFlashSet('erro', implode(' ', $erros));
        header('Location: /pessoas/editar.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $sets = [];
        $params = [];

        $mapaPessoa = [
            'nome' => $nome,
            'nome_busca' => mb_strtolower($nome, 'UTF-8'),
            'apelido' => $apelido !== '' ? $apelido : null,
            'usuario_handle' => $usuarioHandle !== '' ? $usuarioHandle : null,
            'chamar_por' => $chamarPor,
            'data_nascimento' => $dataNascimento !== '' ? $dataNascimento : null,
            'nome_mae' => $nomeMae !== '' ? $nomeMae : null,
            'sexo' => $sexo !== '' ? $sexo : null,
            'telefone' => $telefone !== '' ? $telefone : null,
            'email' => $email !== '' ? $email : null,

            'instagram' => $instagram !== '' ? 'https://www.instagram.com/' . $instagram : null,
            'instagram_username' => $instagram !== '' ? $instagram : null,
            'instagram_user' => $instagram !== '' ? $instagram : null,

            'tiktok' => $tiktok !== '' ? $tiktok : null,
            'tiktok_username' => $tiktok !== '' ? $tiktok : null,

            'facebook' => $facebook !== '' ? $facebook : null,
            'facebook_username' => $facebook !== '' ? $facebook : null,

            'local_trabalho' => $localTrabalho !== '' ? $localTrabalho : null,
            'ponto_referencia' => $pontoReferencia !== '' ? $pontoReferencia : null,
            'transporte' => $transporte !== '' ? $transporte : null,
            'cidade_votacao' => $cidadeVotacao !== '' ? $cidadeVotacao : null,
            'estado_votacao' => $estadoVotacao !== '' ? $estadoVotacao : null,
            'observacoes' => $observacoes !== '' ? $observacoes : null,
            'observacao' => $observacoes !== '' ? $observacoes : null,
        ];

        foreach ($mapaPessoa as $coluna => $valor) {
            if (edColunaExiste($pdo, 'pessoas', $coluna)) {
                $sets[] = "`$coluna` = ?";
                $params[] = $valor;
            }
        }

        if (edColunaExiste($pdo, 'pessoas', 'atualizado_em')) {
            $sets[] = 'atualizado_em = NOW()';
        }

        if ($sets) {
            $params[] = $pessoaId;

            $stmt = $pdo->prepare("
                UPDATE pessoas
                SET " . implode(",\n                    ", $sets) . "
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute($params);
        }

        $stmtEndereco = $pdo->prepare("
            SELECT id
            FROM pessoas_enderecos
            WHERE pessoa_id = ?
              AND tipo = 'residencial'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmtEndereco->execute([$pessoaId]);
        $enderecoId = $stmtEndereco->fetchColumn();

        $temEndereco = (
            $cep !== '' ||
            $enderecoRua !== '' ||
            $numero !== '' ||
            $complemento !== '' ||
            $referencia !== '' ||
            $bairro !== '' ||
            $cidade !== '' ||
            $estado !== ''
        );

        if ($temEndereco) {
            if ($enderecoId) {
                $stmt = $pdo->prepare("
                    UPDATE pessoas_enderecos
                    SET
                        cep = ?,
                        endereco = ?,
                        numero = ?,
                        complemento = ?,
                        referencia = ?,
                        bairro = ?,
                        cidade = ?,
                        estado = ?
                    WHERE id = ?
                      AND pessoa_id = ?
                    LIMIT 1
                ");
                $stmt->execute([
                    $cep !== '' ? $cep : null,
                    $enderecoRua !== '' ? $enderecoRua : null,
                    $numero !== '' ? $numero : null,
                    $complemento !== '' ? $complemento : null,
                    $referencia !== '' ? $referencia : null,
                    $bairro !== '' ? $bairro : null,
                    $cidade !== '' ? $cidade : null,
                    $estado !== '' ? $estado : null,
                    $enderecoId,
                    $pessoaId,
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO pessoas_enderecos
                        (
                            pessoa_id,
                            cep,
                            endereco,
                            numero,
                            complemento,
                            referencia,
                            bairro,
                            cidade,
                            estado,
                            tipo
                        )
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, 'residencial')
                ");
                $stmt->execute([
                    $pessoaId,
                    $cep !== '' ? $cep : null,
                    $enderecoRua !== '' ? $enderecoRua : null,
                    $numero !== '' ? $numero : null,
                    $complemento !== '' ? $complemento : null,
                    $referencia !== '' ? $referencia : null,
                    $bairro !== '' ? $bairro : null,
                    $cidade !== '' ? $cidade : null,
                    $estado !== '' ? $estado : null,
                ]);
            }
        }

        $pdo->commit();

        edFlashSet('sucesso', 'Cadastro atualizado com sucesso.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[EDITAR_PESSOA_V2_SALVAR] ' . $e->getMessage());

        edFlashSet('erro', 'Não foi possível salvar agora. Tente novamente.');
    }

    header('Location: /pessoas/editar.php');
    exit;
}

/*
========================================
DADOS DA PESSOA
========================================
*/
$stmt = $pdo->prepare("
    SELECT *
    FROM pessoas
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$pessoaId]);
$pessoa = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$pessoa) {
    header('Location: /publico/logout.php');
    exit;
}

/*
========================================
ENDEREÇO
========================================
*/
$stmt = $pdo->prepare("
    SELECT *
    FROM pessoas_enderecos
    WHERE pessoa_id = ?
    ORDER BY
        CASE WHEN tipo = 'residencial' THEN 0 ELSE 1 END,
        id DESC
    LIMIT 1
");
$stmt->execute([$pessoaId]);
$enderecoPessoa = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$flash = edFlashGet();

$usuarioHandle = trim((string) ($pessoa['usuario_handle'] ?? ''));
$chamarPor = trim((string) ($pessoa['chamar_por'] ?? 'nome'));
$sexoAtual = trim((string) ($pessoa['sexo'] ?? ''));
$telefoneFormatado = edTelefoneFormatado($pessoa['telefone'] ?? '');
$cepFormatado = edCepFormatado($enderecoPessoa['cep'] ?? '');
$instagramValue = edValorInstagram($pessoa);
$tiktokValue = edValorTiktok($pessoa);
$facebookValue = edValorFacebook($pessoa);

$transporteAtual = trim((string) ($pessoa['transporte'] ?? 'nenhum'));
if ($transporteAtual === '') {
    $transporteAtual = 'nenhum';
}

$observacoesValue = trim((string) ($pessoa['observacoes'] ?? ''));
if ($observacoesValue === '') {
    $observacoesValue = trim((string) ($pessoa['observacao'] ?? ''));
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Editar cadastro | elab.social</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <link rel="stylesheet" href="/assets/css/footer-v2.css?v=5">

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
        }

        body.editar-page-body {
            margin: 0;
            background:
                radial-gradient(circle at 20% -10%, rgba(255, 193, 7, .14), transparent 32%),
                linear-gradient(180deg, #fff8ec 0%, #ffffff 34%, #f7f9fc 100%);
            color: #172033;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        button,
        a,
        input,
        select,
        textarea {
            font: inherit;
        }

        .editar-page {
            width: 100%;
            max-width: 520px;
            min-height: 100vh;
            margin: 0 auto;
            padding-bottom: 112px;
            background: transparent;
        }

        .editar-hero {
            position: relative;
            min-height: 246px;
            padding: calc(18px + env(safe-area-inset-top)) 20px 22px;
            overflow: hidden;
            background:
                radial-gradient(circle at 14% 16%, rgba(255, 255, 255, .32), transparent 28%),
                radial-gradient(circle at 88% 25%, rgba(255, 255, 255, .18), transparent 31%),
                linear-gradient(135deg, #ffb423 0%, #f28a12 42%, #ea6214 100%);
            border-radius: 0 0 34px 34px;
            box-shadow: 0 18px 42px rgba(236, 118, 20, .22);
        }

        .editar-hero::before {
            content: "";
            position: absolute;
            left: -42px;
            bottom: -62px;
            width: 168px;
            height: 168px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .16);
        }

        .editar-hero::after {
            content: "";
            position: absolute;
            right: -30px;
            top: 78px;
            width: 112px;
            height: 112px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .11);
        }

        .editar-hero-top {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }

        .editar-kicker {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 0 10px;
            margin-bottom: 9px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .20);
            color: rgba(255, 255, 255, .90);
            font-size: 10px;
            font-weight: 950;
            letter-spacing: .08em;
        }

        .editar-hero h1 {
            margin: 0;
            max-width: 320px;
            color: #ffffff;
            font-size: 31px;
            line-height: .98;
            font-weight: 950;
            letter-spacing: -0.065em;
            text-shadow: 0 4px 18px rgba(120, 54, 0, .18);
        }

        .editar-hero p {
            position: relative;
            z-index: 2;
            max-width: 330px;
            margin: 10px 0 0;
            color: rgba(255, 255, 255, .92);
            font-size: 13px;
            line-height: 1.35;
            font-weight: 800;
        }

        .editar-back-btn {
            position: relative;
            z-index: 2;
            appearance: none;
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border: 0;
            border-radius: 999px;
            background: rgba(255, 255, 255, .20);
            color: #ffffff;
            font-size: 25px;
            font-weight: 950;
            text-decoration: none;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            -webkit-tap-highlight-color: transparent;
        }

        .editar-back-btn:active {
            transform: scale(.94);
        }

        .editar-hero-card {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 18px;
            padding: 10px;
            border-radius: 24px;
            background: rgba(255, 255, 255, .94);
            box-shadow: 0 14px 28px rgba(128, 58, 0, .16);
        }

        .editar-hero-stat {
            min-height: 62px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            border-right: 1px solid #f0e6d9;
            text-align: center;
        }

        .editar-hero-stat:last-child {
            border-right: 0;
        }

        .editar-hero-stat strong {
            display: block;
            color: #172033;
            font-size: 17px;
            line-height: 1;
            font-weight: 950;
            letter-spacing: -0.04em;
        }

        .editar-hero-stat span {
            display: block;
            color: #8a95a8;
            font-size: 10.5px;
            line-height: 1.1;
            font-weight: 850;
        }

        .editar-content {
            padding: 18px 20px 0;
        }

        .editar-flash {
            margin: 0 0 14px;
            padding: 13px 14px;
            border-radius: 18px;
            font-size: 13px;
            line-height: 1.35;
            font-weight: 850;
            text-align: center;
            border: 2px solid transparent;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .045);
        }

        .editar-flash.is-success {
            background: #ecfdf3;
            color: #166534;
            border-color: #c7f0d3;
        }

        .editar-flash.is-error {
            background: #fff1f2;
            color: #991b1b;
            border-color: #fecdd3;
        }

        .editar-card {
            position: relative;
            margin-bottom: 15px;
            padding: 16px;
            border-radius: 28px;
            background:
                radial-gradient(circle at 90% 8%, rgba(34, 197, 94, .08), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 2px solid #edf0f5;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .055);
            overflow: hidden;
        }

        .editar-card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 14px;
        }

        .editar-card-icon {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 17px;
            background: linear-gradient(180deg, #fff8dc 0%, #fff1b7 100%);
            box-shadow: inset 0 -4px 0 rgba(180, 119, 0, .08);
            font-size: 22px;
        }

        .editar-card-title h2 {
            margin: 0;
            color: #172033;
            font-size: 20px;
            line-height: 1.05;
            font-weight: 950;
            letter-spacing: -0.045em;
        }

        .editar-card-title p {
            margin: 5px 0 0;
            color: #8a95a8;
            font-size: 12px;
            line-height: 1.28;
            font-weight: 750;
        }

        .editar-field {
            margin-top: 12px;
        }

        .editar-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .editar-label {
            display: block;
            margin: 0 0 7px;
            color: #172033;
            font-size: 12px;
            line-height: 1;
            font-weight: 950;
        }

        .editar-input,
        .editar-select,
        .editar-textarea {
            width: 100%;
            min-height: 52px;
            padding: 0 14px;
            border: 2px solid #e5eaf2;
            border-radius: 17px;
            background: #ffffff;
            color: #172033;
            font-size: 14px;
            font-weight: 850;
            outline: none;
            box-shadow: inset 0 -2px 0 rgba(15, 23, 42, .02);
        }

        .editar-select {
            appearance: none;
        }

        .editar-textarea {
            min-height: 94px;
            padding-top: 13px;
            padding-bottom: 13px;
            resize: vertical;
            line-height: 1.35;
        }

        .editar-input:focus,
        .editar-select:focus,
        .editar-textarea:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 4px rgba(96, 165, 250, .16);
        }

        .editar-hint {
            margin-top: 7px;
            color: #8a95a8;
            font-size: 11px;
            line-height: 1.3;
            font-weight: 750;
        }

        .editar-choice-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .editar-choice {
            position: relative;
            display: block;
        }

        .editar-choice input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .editar-choice span {
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #e5eaf2;
            border-radius: 17px;
            background: #ffffff;
            color: #64748b;
            font-size: 13px;
            font-weight: 950;
            text-align: center;
        }

        .editar-choice input:checked + span {
            color: #ffffff;
            border-color: transparent;
            background: linear-gradient(180deg, #25d366 0%, #16a34a 100%);
            box-shadow: 0 5px 0 #0f7f38, 0 10px 20px rgba(22, 163, 74, .18);
        }

        .editar-security-card {
            background:
                radial-gradient(circle at 90% 8%, rgba(96, 165, 250, .12), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
        }

        .editar-leader-card {
            background:
                radial-gradient(circle at 90% 8%, rgba(245, 158, 11, .14), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #fffdf4 100%);
        }

        .editar-card-text {
            margin: 0 0 13px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.42;
            font-weight: 800;
        }

        .editar-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .editar-btn {
            appearance: none;
            width: 100%;
            min-height: 54px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 17px;
            font-size: 14px;
            line-height: 1.05;
            font-weight: 950;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            transition: transform .14s ease, box-shadow .14s ease, filter .14s ease;
        }

        .editar-btn:active {
            transform: translateY(3px);
        }

        .editar-btn.is-save {
            background: linear-gradient(180deg, #25d366 0%, #16a34a 100%);
            color: #ffffff;
            box-shadow: 0 5px 0 #0f7f38, 0 10px 20px rgba(22, 163, 74, .22);
        }

        .editar-btn.is-save:active {
            box-shadow: 0 2px 0 #0f7f38, 0 8px 16px rgba(22, 163, 74, .18);
        }

        .editar-btn.is-outline {
            background: #ffffff;
            color: #16a34a;
            border: 2px solid #bbf7d0;
            box-shadow: 0 5px 0 #a7e8ba;
        }

        .editar-btn.is-soft {
            background: #f8fafc;
            color: #334155;
            border: 2px solid #e5eaf2;
            box-shadow: 0 5px 0 #d7dee7;
        }

        .is-loading {
            opacity: .65;
        }

        .editar-confirm-overlay {
            position: fixed;
            inset: 0;
            z-index: 10040;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(15, 23, 42, .48);
        }

        .editar-confirm-overlay.is-open {
            display: flex;
        }

        .editar-confirm-box {
            width: 100%;
            max-width: 380px;
            padding: 22px;
            border-radius: 28px;
            background: #ffffff;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .24);
            text-align: center;
            animation: editarConfirmIn .18s ease;
        }

        .editar-confirm-icon {
            width: 72px;
            height: 72px;
            display: grid;
            place-items: center;
            margin: 0 auto 14px;
            border-radius: 999px;
            background: #fff7d6;
            font-size: 34px;
        }

        .editar-confirm-box h3 {
            margin: 0;
            color: #172033;
            font-size: 23px;
            line-height: 1.05;
            font-weight: 950;
            letter-spacing: -0.05em;
        }

        .editar-confirm-box p {
            margin: 8px 0 0;
            color: #7b8798;
            font-size: 13px;
            line-height: 1.45;
            font-weight: 750;
        }

        .editar-confirm-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 18px;
        }

        .editar-confirm-actions button {
            appearance: none;
            min-height: 48px;
            border: 0;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 950;
            -webkit-tap-highlight-color: transparent;
        }

        .editar-confirm-cancel {
            background: #f1f5f9;
            color: #64748b;
        }

        .editar-confirm-ok {
            background: linear-gradient(180deg, #25d366 0%, #16a34a 100%);
            color: #ffffff;
            box-shadow: 0 5px 0 #0f7f38;
        }

        @keyframes editarConfirmIn {
            from {
                opacity: 0;
                transform: translateY(8px) scale(.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 380px) {
            .editar-hero {
                min-height: 232px;
            }

            .editar-hero h1 {
                font-size: 27px;
            }

            .editar-content {
                padding-left: 16px;
                padding-right: 16px;
            }

            .editar-grid,
            .editar-choice-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="editar-page-body">

<main class="editar-page">

    <section class="editar-hero">
        <div class="editar-hero-top">
            <div>
                <span class="editar-kicker">EDITAR CADASTRO</span>
                <h1>Olá, <?= edEsc($nomeUsuario) ?>.</h1>
                <p>Atualize seus dados para manter seu perfil, missões, ranking e comunicação funcionando direitinho.</p>
            </div>

            <a href="/pessoas/perfil.php" class="editar-back-btn" aria-label="Voltar ao perfil">‹</a>
        </div>

        <div class="editar-hero-card" aria-label="Resumo do cadastro">
            <div class="editar-hero-stat">
                <strong><?= edEsc($pessoa['perfil'] ?? 'pessoa') ?></strong>
                <span>perfil</span>
            </div>

            <div class="editar-hero-stat">
                <strong><?= number_format((int) ($pessoa['pontos'] ?? 0), 0, ',', '.') ?></strong>
                <span>moedas</span>
            </div>

            <div class="editar-hero-stat">
                <strong><?= edEsc($pessoa['status'] ?? 'ativo') ?></strong>
                <span>status</span>
            </div>
        </div>
    </section>

    <section class="editar-content">

        <?php if ($flash): ?>
            <div class="editar-flash <?= ($flash['tipo'] ?? '') === 'sucesso' ? 'is-success' : 'is-error' ?>">
                <?= edEsc($flash['mensagem'] ?? '') ?>
            </div>
        <?php endif; ?>

        <form method="post" id="editarForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= edEsc($csrfToken) ?>">

            <section class="editar-card">
                <div class="editar-card-title">
                    <div class="editar-card-icon">🙂</div>
                    <div>
                        <h2>Identidade</h2>
                        <p>Como você aparece no app.</p>
                    </div>
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="nome">Nome completo</label>
                    <input type="text" name="nome" id="nome" class="editar-input" value="<?= edEsc((string) ($pessoa['nome'] ?? '')) ?>" required>
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="apelido">Apelido</label>
                    <input type="text" name="apelido" id="apelido" class="editar-input" value="<?= edEsc((string) ($pessoa['apelido'] ?? '')) ?>">
                    <div class="editar-hint">Esse nome pode aparecer no perfil, ranking e missões.</div>
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="usuario_handle">Usuário exclusivo</label>
                    <input type="text" name="usuario_handle" id="usuario_handle" class="editar-input" placeholder="@seuusuario" value="<?= edEsc($usuarioHandle !== '' ? '@' . ltrim($usuarioHandle, '@') : '') ?>">
                    <div class="editar-hint">Use letras, números, ponto ou underline. Exemplo: @joaofelipeat</div>
                </div>

                <div class="editar-field">
                    <label class="editar-label">Como quer ser chamado?</label>
                    <div class="editar-choice-grid">
                        <label class="editar-choice">
                            <input type="radio" name="chamar_por" value="nome" <?= $chamarPor === 'nome' ? 'checked' : '' ?>>
                            <span>Nome</span>
                        </label>

                        <label class="editar-choice">
                            <input type="radio" name="chamar_por" value="apelido" <?= $chamarPor === 'apelido' ? 'checked' : '' ?>>
                            <span>Apelido</span>
                        </label>
                    </div>
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="sexo">Personagem do perfil</label>
                    <select name="sexo" id="sexo" class="editar-select">
                        <option value="" <?= $sexoAtual === '' ? 'selected' : '' ?>>Padrão</option>
                        <option value="M" <?= $sexoAtual === 'M' ? 'selected' : '' ?>>Masculino</option>
                        <option value="F" <?= $sexoAtual === 'F' ? 'selected' : '' ?>>Feminino</option>
                        <option value="O" <?= $sexoAtual === 'O' ? 'selected' : '' ?>>Outro / não informar</option>
                    </select>
                </div>
            </section>

            <section class="editar-card">
                <div class="editar-card-title">
                    <div class="editar-card-icon">🪪</div>
                    <div>
                        <h2>Dados pessoais</h2>
                        <p>Informações básicas do cadastro.</p>
                    </div>
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="nome_mae">Nome da mãe</label>
                    <input type="text" name="nome_mae" id="nome_mae" class="editar-input" value="<?= edEsc((string) ($pessoa['nome_mae'] ?? '')) ?>">
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="data_nascimento">Data de nascimento</label>
                    <input type="date" name="data_nascimento" id="data_nascimento" class="editar-input" value="<?= edEsc((string) ($pessoa['data_nascimento'] ?? '')) ?>">
                </div>
            </section>

            <section class="editar-card">
                <div class="editar-card-title">
                    <div class="editar-card-icon">📱</div>
                    <div>
                        <h2>Contato</h2>
                        <p>Telefone e e-mail de comunicação.</p>
                    </div>
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="telefone">Telefone</label>
                    <input type="text" name="telefone" id="telefone" class="editar-input" inputmode="numeric" placeholder="(95) 99999-9999" value="<?= edEsc($telefoneFormatado) ?>">
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="email">E-mail</label>
                    <input type="email" name="email" id="email" class="editar-input" value="<?= edEsc((string) ($pessoa['email'] ?? '')) ?>">
                </div>
            </section>

            <section class="editar-card editar-security-card">
                <div class="editar-card-title">
                    <div class="editar-card-icon">🔐</div>
                    <div>
                        <h2>Segurança</h2>
                        <p>Recupere seu acesso pelo e-mail.</p>
                    </div>
                </div>

                <p class="editar-card-text">
                    Envie uma nova senha para o e-mail cadastrado neste perfil. A senha atual será substituída.
                </p>

                <button type="button" class="editar-btn is-soft" id="btnEnviarSenha">
                    Enviar nova senha por e-mail
                </button>
            </section>

            <section class="editar-card">
                <div class="editar-card-title">
                    <div class="editar-card-icon">📍</div>
                    <div>
                        <h2>Endereço</h2>
                        <p>Digite o CEP para preencher mais rápido.</p>
                    </div>
                </div>

                <div class="editar-grid">
                    <div class="editar-field">
                        <label class="editar-label" for="cep">CEP</label>
                        <input type="text" name="cep" id="cep" class="editar-input" inputmode="numeric" placeholder="00000-000" value="<?= edEsc($cepFormatado) ?>">
                        <div class="editar-hint" id="cepStatus">Digite o CEP para preencher o endereço.</div>
                    </div>

                    <div class="editar-field">
                        <label class="editar-label" for="numero">Número</label>
                        <input type="text" name="numero" id="numero" class="editar-input" value="<?= edEsc((string) ($enderecoPessoa['numero'] ?? '')) ?>">
                    </div>
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="endereco">Endereço</label>
                    <input type="text" name="endereco" id="endereco" class="editar-input" value="<?= edEsc((string) ($enderecoPessoa['endereco'] ?? '')) ?>">
                </div>

                <div class="editar-grid">
                    <div class="editar-field">
                        <label class="editar-label" for="bairro">Bairro</label>
                        <input type="text" name="bairro" id="bairro" class="editar-input" value="<?= edEsc((string) ($enderecoPessoa['bairro'] ?? '')) ?>">
                    </div>

                    <div class="editar-field">
                        <label class="editar-label" for="cidade">Cidade</label>
                        <input type="text" name="cidade" id="cidade" class="editar-input" value="<?= edEsc((string) ($enderecoPessoa['cidade'] ?? '')) ?>">
                    </div>
                </div>

                <div class="editar-grid">
                    <div class="editar-field">
                        <label class="editar-label" for="estado">Estado</label>
                        <input type="text" name="estado" id="estado" class="editar-input" maxlength="2" value="<?= edEsc((string) ($enderecoPessoa['estado'] ?? '')) ?>">
                    </div>

                    <div class="editar-field">
                        <label class="editar-label" for="complemento">Complemento</label>
                        <input type="text" name="complemento" id="complemento" class="editar-input" value="<?= edEsc((string) ($enderecoPessoa['complemento'] ?? '')) ?>">
                    </div>
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="referencia">Ponto de referência</label>
                    <input type="text" name="referencia" id="referencia" class="editar-input" placeholder="Ex: perto da escola, mercado, igreja..." value="<?= edEsc((string) ($enderecoPessoa['referencia'] ?? '')) ?>">
                </div>
            </section>

            <section class="editar-card">
                <div class="editar-card-title">
                    <div class="editar-card-icon">🌐</div>
                    <div>
                        <h2>Redes sociais</h2>
                        <p>Conecte seus perfis sociais.</p>
                    </div>
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="instagram_username">Instagram</label>
                    <input type="text" name="instagram_username" id="instagram_username" class="editar-input" placeholder="@usuario" value="<?= edEsc($instagramValue !== '' ? '@' . $instagramValue : '') ?>">
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="tiktok_username">TikTok</label>
                    <input type="text" name="tiktok_username" id="tiktok_username" class="editar-input" placeholder="@usuario" value="<?= edEsc($tiktokValue !== '' ? '@' . $tiktokValue : '') ?>">
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="facebook_username">Facebook</label>
                    <input type="text" name="facebook_username" id="facebook_username" class="editar-input" placeholder="perfil, usuário ou link" value="<?= edEsc($facebookValue) ?>">
                </div>
            </section>

            <section class="editar-card">
                <div class="editar-card-title">
                    <div class="editar-card-icon">🧭</div>
                    <div>
                        <h2>Dados extras</h2>
                        <p>Informações úteis para organização da rede.</p>
                    </div>
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="local_trabalho">Local de trabalho</label>
                    <input type="text" name="local_trabalho" id="local_trabalho" class="editar-input" value="<?= edEsc((string) ($pessoa['local_trabalho'] ?? '')) ?>">
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="ponto_referencia">Ponto de referência pessoal</label>
                    <input type="text" name="ponto_referencia" id="ponto_referencia" class="editar-input" value="<?= edEsc((string) ($pessoa['ponto_referencia'] ?? '')) ?>">
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="transporte">Transporte</label>
                    <select name="transporte" id="transporte" class="editar-select">
                        <option value="nenhum" <?= $transporteAtual === 'nenhum' ? 'selected' : '' ?>>Nenhum</option>
                        <option value="carro" <?= $transporteAtual === 'carro' ? 'selected' : '' ?>>Carro</option>
                        <option value="moto" <?= $transporteAtual === 'moto' ? 'selected' : '' ?>>Moto</option>
                        <option value="bicicleta" <?= $transporteAtual === 'bicicleta' ? 'selected' : '' ?>>Bicicleta</option>
                        <option value="onibus" <?= $transporteAtual === 'onibus' ? 'selected' : '' ?>>Ônibus</option>
                        <option value="app" <?= $transporteAtual === 'app' ? 'selected' : '' ?>>Aplicativo</option>
                        <option value="outro" <?= $transporteAtual === 'outro' ? 'selected' : '' ?>>Outro</option>
                    </select>
                </div>

                <div class="editar-grid">
                    <div class="editar-field">
                        <label class="editar-label" for="cidade_votacao">Cidade de votação</label>
                        <input type="text" name="cidade_votacao" id="cidade_votacao" class="editar-input" value="<?= edEsc((string) ($pessoa['cidade_votacao'] ?? '')) ?>">
                    </div>

                    <div class="editar-field">
                        <label class="editar-label" for="estado_votacao">Estado de votação</label>
                        <input type="text" name="estado_votacao" id="estado_votacao" class="editar-input" maxlength="2" value="<?= edEsc((string) ($pessoa['estado_votacao'] ?? '')) ?>">
                    </div>
                </div>

                <div class="editar-field">
                    <label class="editar-label" for="observacoes">Observações</label>
                    <textarea name="observacoes" id="observacoes" class="editar-textarea"><?= edEsc($observacoesValue) ?></textarea>
                </div>
            </section>

            <section class="editar-card editar-leader-card">
                <div class="editar-card-title">
                    <div class="editar-card-icon">👑</div>
                    <div>
                        <h2>Líder</h2>
                        <p>Solicitação de mudança na rede.</p>
                    </div>
                </div>

                <p class="editar-card-text">
                    A alteração de líder muda sua posição na rede. Por segurança, essa ação fica em uma tela própria com confirmação e validação da sua estrutura.
                </p>

                <a href="/pessoas/lider.php" class="editar-btn is-soft">
                    Solicitar alteração de líder
                </a>
            </section>

            <section class="editar-card">
                <div class="editar-actions">
                    <button type="submit" name="acao" value="salvar" class="editar-btn is-save">
                        Salvar alterações
                    </button>

                    <a href="/pessoas/perfil.php" class="editar-btn is-outline">
                        Cancelar
                    </a>
                </div>
            </section>
        </form>
    </section>
</main>

<div id="editarConfirmOverlay" class="editar-confirm-overlay" aria-hidden="true">
    <div class="editar-confirm-box" role="dialog" aria-modal="true" aria-labelledby="editarConfirmTitle">
        <div class="editar-confirm-icon" id="editarConfirmIcon">🔐</div>

        <h3 id="editarConfirmTitle">Enviar nova senha?</h3>
        <p id="editarConfirmText">
            Uma nova senha será enviada para o e-mail cadastrado. A senha atual será substituída.
        </p>

        <div class="editar-confirm-actions">
            <button type="button" class="editar-confirm-cancel" id="editarConfirmCancel">
                Cancelar
            </button>

            <button type="button" class="editar-confirm-ok" id="editarConfirmOk">
                Enviar
            </button>
        </div>
    </div>
</div>

<?php
$footerPath = __DIR__ . '/../assets/footer/menu.php';

if (is_file($footerPath)) {
    require_once $footerPath;
}
?>

<script>
(() => {
    const form = document.getElementById('editarForm');

    const telefoneInput = document.getElementById('telefone');

    if (telefoneInput) {
        telefoneInput.addEventListener('input', () => {
            let v = telefoneInput.value.replace(/\D/g, '').slice(0, 11);

            if (v.length > 10) {
                telefoneInput.value = `(${v.slice(0, 2)}) ${v.slice(2, 7)}-${v.slice(7)}`;
            } else if (v.length > 6) {
                telefoneInput.value = `(${v.slice(0, 2)}) ${v.slice(2, 6)}-${v.slice(6)}`;
            } else if (v.length > 2) {
                telefoneInput.value = `(${v.slice(0, 2)}) ${v.slice(2)}`;
            } else {
                telefoneInput.value = v;
            }
        });
    }

    const handleInput = document.getElementById('usuario_handle');

    if (handleInput) {
        handleInput.addEventListener('input', () => {
            let v = handleInput.value.trim().toLowerCase();

            v = v.replace(/^@+/, '');
            v = v.replace(/[^a-z0-9._]/g, '');
            v = v.replace(/\.{2,}/g, '.');

            handleInput.value = v ? `@${v}` : '';
        });
    }

    ['instagram_username', 'tiktok_username'].forEach((id) => {
        const input = document.getElementById(id);

        if (!input) {
            return;
        }

        input.addEventListener('input', () => {
            let v = input.value.trim();
            v = v.replace(/^@+/, '');

            input.value = v ? `@${v}` : '';
        });
    });

    ['estado', 'estado_votacao'].forEach((id) => {
        const input = document.getElementById(id);

        if (!input) {
            return;
        }

        input.addEventListener('input', () => {
            input.value = input.value.replace(/[^a-zA-Z]/g, '').slice(0, 2).toUpperCase();
        });
    });

    const cepInput = document.getElementById('cep');
    const cepStatus = document.getElementById('cepStatus');
    const enderecoInput = document.getElementById('endereco');
    const bairroInput = document.getElementById('bairro');
    const cidadeInput = document.getElementById('cidade');
    const estadoInput = document.getElementById('estado');
    const numeroInput = document.getElementById('numero');

    let ultimoCepBuscado = '';

    if (cepInput) {
        cepInput.addEventListener('input', () => {
            let v = cepInput.value.replace(/\D/g, '').slice(0, 8);

            if (v.length > 5) {
                cepInput.value = `${v.slice(0, 5)}-${v.slice(5)}`;
            } else {
                cepInput.value = v;
            }

            if (v.length === 8 && v !== ultimoCepBuscado) {
                ultimoCepBuscado = v;
                buscarCep(v);
            } else if (v.length !== 8 && cepStatus) {
                cepStatus.textContent = 'Digite o CEP para preencher o endereço.';
            }
        });
    }

    function buscarCep(cep) {
        if (!cepInput || !enderecoInput || !bairroInput || !cidadeInput || !estadoInput) {
            return;
        }

        cepInput.classList.add('is-loading');

        if (cepStatus) {
            cepStatus.textContent = 'Buscando endereço...';
        }

        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then((response) => response.json())
            .then((dados) => {
                if (dados.erro) {
                    if (cepStatus) {
                        cepStatus.textContent = 'CEP não encontrado. Preencha manualmente.';
                    }
                    return;
                }

                enderecoInput.value = dados.logradouro || '';
                bairroInput.value = dados.bairro || '';
                cidadeInput.value = dados.localidade || '';
                estadoInput.value = dados.uf || '';

                if (cepStatus) {
                    cepStatus.textContent = 'Endereço preenchido automaticamente.';
                }

                if (numeroInput && numeroInput.value.trim() === '') {
                    numeroInput.focus();
                }
            })
            .catch(() => {
                if (cepStatus) {
                    cepStatus.textContent = 'Não foi possível buscar o CEP agora.';
                }
            })
            .finally(() => {
                cepInput.classList.remove('is-loading');
            });
    }

    const overlay = document.getElementById('editarConfirmOverlay');
    const cancelBtn = document.getElementById('editarConfirmCancel');
    const okBtn = document.getElementById('editarConfirmOk');
    const btnEnviarSenha = document.getElementById('btnEnviarSenha');

    const fecharConfirm = () => {
        if (overlay) {
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
        }
    };

    const abrirConfirm = () => {
        if (overlay) {
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
        }
    };

    if (btnEnviarSenha) {
        btnEnviarSenha.addEventListener('click', abrirConfirm);
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', fecharConfirm);
    }

    if (overlay) {
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                fecharConfirm();
            }
        });
    }

    if (okBtn) {
        okBtn.addEventListener('click', () => {
            if (!form) {
                return;
            }

            okBtn.disabled = true;
            okBtn.textContent = 'Enviando...';

            const inputAcao = document.createElement('input');
            inputAcao.type = 'hidden';
            inputAcao.name = 'acao';
            inputAcao.value = 'enviar_senha_email';

            form.appendChild(inputAcao);
            form.submit();
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            fecharConfirm();
        }
    });
})();
</script>

</body>
</html>