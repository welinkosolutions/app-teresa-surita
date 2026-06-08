<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('America/Boa_Vista');

session_name('ELAB_APP_SESSION');
session_start();

$isAjax = (
    (string)($_POST['ajax'] ?? '') === '1'
    || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
);

function responderJson(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function falhar(string $mensagem, int $status = 400): never
{
    global $isAjax;

    if ($isAjax) {
        responderJson([
            'ok' => false,
            'erro' => $mensagem,
        ], $status);
    }

    die('Erro ao salvar demanda: ' . $mensagem);
}

if (empty($_SESSION['pessoa_id'])) {
    if ($isAjax) {
        responderJson(['ok' => false, 'erro' => 'Sessão expirada. Faça login novamente.'], 401);
    }

    header('Location: /index.php');
    exit;
}

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();
$liderId = (int)($_SESSION['pessoa_id'] ?? 0);

function garantirColunaCodigoDemanda(PDO $pdo): void
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM demandas LIKE 'codigo_demanda'");
        if (!$stmt || !$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE demandas ADD COLUMN codigo_demanda VARCHAR(5) NULL AFTER protocolo");
        }
    } catch (Throwable $e) {
        // Se o usuario do banco nao puder alterar tabela, use o SQL enviado junto no ZIP.
    }
}

garantirColunaCodigoDemanda($pdo);

function postString(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

function somenteDigitos(?string $valor): string
{
    return preg_replace('/\D+/', '', (string)$valor);
}

function nomeBusca(?string $nome): ?string
{
    $nome = trim((string)$nome);

    if ($nome === '') {
        return null;
    }

    $map = [
        'Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','Ä'=>'a',
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a',
        'É'=>'e','È'=>'e','Ê'=>'e','Ë'=>'e',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'Í'=>'i','Ì'=>'i','Î'=>'i','Ï'=>'i',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'Ó'=>'o','Ò'=>'o','Ô'=>'o','Õ'=>'o','Ö'=>'o',
        'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'Ú'=>'u','Ù'=>'u','Û'=>'u','Ü'=>'u',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'Ç'=>'c','ç'=>'c',
    ];

    $nome = strtr($nome, $map);
    $nome = mb_strtolower($nome, 'UTF-8');
    $nome = preg_replace('/\s+/', ' ', $nome);

    return trim((string)$nome);
}

function possuiEnderecoInformado(array $endereco): bool
{
    foreach ($endereco as $valor) {
        if (trim((string)$valor) !== '') {
            return true;
        }
    }

    return false;
}

$stmtPerfil = $pdo->prepare("
    SELECT perfil
    FROM pessoas
    WHERE id = ?
    LIMIT 1
");
$stmtPerfil->execute([$liderId]);

$perfilLogado = trim((string)($stmtPerfil->fetchColumn() ?? ''));

if (!in_array($perfilLogado, ['lider', 'admin', 'gestor_lideres'], true)) {
    if ($isAjax) {
        responderJson(['ok' => false, 'erro' => 'Você não tem permissão para cadastrar demanda.'], 403);
    }

    header('Location: /interno/admin.php');
    exit;
}

$pessoaExistente = (int)($_POST['pessoa_existente'] ?? 0);
$pessoaId = (int)($_POST['pessoa_id'] ?? 0);

$telefone = somenteDigitos((string)($_POST['telefone'] ?? ''));
$nome = postString('nome');
$nomeBusca = nomeBusca($nome);
$apelido = postString('apelido');
$dataNascimento = postString('data_nascimento');

$origem = postString('origem');
$categoria = postString('categoria');
$titulo = postString('titulo');
$descricao = postString('descricao');
$codigoDemanda = postString('codigo_demanda');
$codigosPermitidos = [];
for ($i = 1; $i <= 10; $i++) {
    $codigosPermitidos[] = 'DM-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
}
if ($codigoDemanda !== '' && !in_array($codigoDemanda, $codigosPermitidos, true)) {
    $codigoDemanda = '';
}

$cep = somenteDigitos((string)($_POST['cep'] ?? ''));
$endereco = postString('endereco');
$numero = postString('numero');
$complemento = postString('complemento');
$bairro = postString('bairro');
$cidade = postString('cidade');
$estado = mb_strtoupper(postString('estado'), 'UTF-8');

$dadosEndereco = [
    'cep' => $cep,
    'endereco' => $endereco,
    'numero' => $numero,
    'complemento' => $complemento,
    'bairro' => $bairro,
    'cidade' => $cidade,
    'estado' => $estado,
];

$origensPermitidas = ['gabinete', 'visita_rua', 'instagram', 'evento', 'facebook', 'outros'];
$categoriasPermitidas = ['saude', 'educacao', 'infraestrutura', 'assistencia_social', 'bairro', 'outros'];

if ($nome === '' || $telefone === '' || $dataNascimento === '') {
    falhar('Dados obrigatórios da pessoa não informados.');
}

if (strlen($telefone) < 10) {
    falhar('Telefone inválido.');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataNascimento)) {
    falhar('Data de nascimento inválida.');
}

if (
    $origem === ''
    || $categoria === ''
    || $titulo === ''
    || $descricao === ''
    || !in_array($origem, $origensPermitidas, true)
    || !in_array($categoria, $categoriasPermitidas, true)
) {
    falhar('Dados da demanda inválidos.');
}

if ($estado !== '' && mb_strlen($estado, 'UTF-8') > 2) {
    $estado = mb_substr($estado, 0, 2, 'UTF-8');
    $dadosEndereco['estado'] = $estado;
}

try {
    $pdo->beginTransaction();

    $stmtTelefoneExiste = $pdo->prepare("
        SELECT id
        FROM pessoas
        WHERE telefone = ?
        LIMIT 1
    ");
    $stmtTelefoneExiste->execute([$telefone]);
    $pessoaIdPorTelefone = (int)($stmtTelefoneExiste->fetchColumn() ?: 0);

    if ($pessoaIdPorTelefone > 0) {
        $pessoaId = $pessoaIdPorTelefone;
        $pessoaExistente = 1;
    }

    if ($pessoaExistente === 0) {
        $stmtPessoa = $pdo->prepare("
            INSERT INTO pessoas
            (nome, nome_busca, apelido, telefone, data_nascimento, perfil, status, criado_por)
            VALUES (?, ?, ?, ?, ?, 'pessoa', 'ativo', ?)
        ");

        $stmtPessoa->execute([
            $nome,
            $nomeBusca,
            $apelido !== '' ? $apelido : null,
            $telefone,
            $dataNascimento,
            $liderId
        ]);

        $pessoaId = (int)$pdo->lastInsertId();
    } else {
        if ($pessoaId <= 0) {
            throw new RuntimeException('Pessoa informada é inválida.');
        }

        $stmtPessoaExiste = $pdo->prepare("
            SELECT id
            FROM pessoas
            WHERE id = ?
            LIMIT 1
        ");
        $stmtPessoaExiste->execute([$pessoaId]);

        if (!(int)$stmtPessoaExiste->fetchColumn()) {
            throw new RuntimeException('Pessoa selecionada não foi encontrada.');
        }

        $stmtPessoa = $pdo->prepare("
            UPDATE pessoas
            SET nome = ?,
                nome_busca = ?,
                apelido = ?,
                telefone = ?,
                data_nascimento = ?,
                atualizado_em = NOW()
            WHERE id = ?
            LIMIT 1
        ");

        $stmtPessoa->execute([
            $nome,
            $nomeBusca,
            $apelido !== '' ? $apelido : null,
            $telefone,
            $dataNascimento,
            $pessoaId
        ]);
    }

    if (possuiEnderecoInformado($dadosEndereco)) {
        $stmtEnderecoAtual = $pdo->prepare("
            SELECT id
            FROM pessoas_enderecos
            WHERE pessoa_id = ?
              AND tipo = 'residencial'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmtEnderecoAtual->execute([$pessoaId]);

        $enderecoId = (int)($stmtEnderecoAtual->fetchColumn() ?? 0);

        if ($enderecoId > 0) {
            $stmtEndereco = $pdo->prepare("
                UPDATE pessoas_enderecos
                SET cep = ?,
                    endereco = ?,
                    numero = ?,
                    complemento = ?,
                    bairro = ?,
                    cidade = ?,
                    estado = ?
                WHERE id = ?
                LIMIT 1
            ");

            $stmtEndereco->execute([
                $cep !== '' ? $cep : null,
                $endereco !== '' ? $endereco : null,
                $numero !== '' ? $numero : null,
                $complemento !== '' ? $complemento : null,
                $bairro !== '' ? $bairro : null,
                $cidade !== '' ? $cidade : null,
                $estado !== '' ? $estado : null,
                $enderecoId
            ]);
        } else {
            $stmtEndereco = $pdo->prepare("
                INSERT INTO pessoas_enderecos
                (pessoa_id, cep, endereco, numero, complemento, bairro, cidade, estado, tipo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'residencial')
            ");

            $stmtEndereco->execute([
                $pessoaId,
                $cep !== '' ? $cep : null,
                $endereco !== '' ? $endereco : null,
                $numero !== '' ? $numero : null,
                $complemento !== '' ? $complemento : null,
                $bairro !== '' ? $bairro : null,
                $cidade !== '' ? $cidade : null,
                $estado !== '' ? $estado : null
            ]);
        }
    }

    $pdo->exec("INSERT INTO controle_sequencial_demandas VALUES (NULL, NOW())");
    $sequencialGlobal = (int)$pdo->lastInsertId();

    $anoAtual = date('Y');
    $sequencialFormatado = str_pad((string)$sequencialGlobal, 8, '0', STR_PAD_LEFT);
    $protocolo = $liderId . '-' . $anoAtual . '-' . $sequencialFormatado;

    $stmtDemanda = $pdo->prepare("
        INSERT INTO demandas
        (
            sequencial_global,
            protocolo,
            codigo_demanda,
            pessoa_id,
            criado_por,
            titulo,
            descricao,
            origem,
            categoria,
            responsavel_id,
            autor_acao_id,
            status,
            prioridade,
            resolucao,
            criado_em,
            atualizado_em
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aberto', 'normal', 'pendente', NOW(), NOW())
    ");

    $stmtDemanda->execute([
        $sequencialGlobal,
        $protocolo,
        $codigoDemanda !== '' ? $codigoDemanda : null,
        $pessoaId,
        $liderId,
        $titulo,
        $descricao,
        $origem,
        $categoria,
        $liderId,
        $liderId
    ]);

    $demandaId = (int)$pdo->lastInsertId();

    $stmtEvento = $pdo->prepare("
        INSERT INTO demandas_eventos
        (demanda_id, tipo, autor_id, autor_tipo, criado_em)
        VALUES (?, 'criada', ?, 'admin', NOW())
    ");
    $stmtEvento->execute([$demandaId, $liderId]);

    $stmtResponsavel = $pdo->prepare("
        INSERT INTO demandas_responsaveis
        (demanda_id, lider_id, ativo, assumido_em, definido_por)
        VALUES (?, ?, 'sim', NOW(), ?)
    ");
    $stmtResponsavel->execute([
        $demandaId,
        $liderId,
        $liderId
    ]);

    try {
        $stmtPontos = $pdo->prepare("
            INSERT INTO gamificacao_pontos_temporada
            (
                temporada_id,
                pessoa_id,
                origem_tipo,
                conversao_id,
                pontos_base,
                multiplicador,
                pontos_final
            )
            VALUES
            (1, ?, 'demanda_criada', ?, 10, 1.00, 10)
        ");
        $stmtPontos->execute([
            $liderId,
            $demandaId
        ]);
    } catch (Throwable $e) {}

    if (!empty($_FILES['midias']['name'][0])) {
        $baseUpload = '/home/elab/app.elab.social/uploads/demandas';
        $pastaDemanda = $baseUpload . '/' . $demandaId;

        if (!is_dir($baseUpload)) {
            throw new RuntimeException('A pasta base de uploads não existe: ' . $baseUpload);
        }

        if (!is_writable($baseUpload)) {
            throw new RuntimeException('A pasta base de uploads não tem permissão de escrita: ' . $baseUpload);
        }

        if (!is_dir($pastaDemanda)) {
            if (!mkdir($pastaDemanda, 0775, true) && !is_dir($pastaDemanda)) {
                throw new RuntimeException('Não foi possível criar a pasta de upload da demanda: ' . $pastaDemanda);
            }
        }

        if (!is_writable($pastaDemanda)) {
            throw new RuntimeException('A pasta da demanda não tem permissão de escrita: ' . $pastaDemanda);
        }

        $limiteArquivos = min(count($_FILES['midias']['tmp_name']), 3);

        for ($i = 0; $i < $limiteArquivos; $i++) {
            $tmpName = $_FILES['midias']['tmp_name'][$i] ?? '';
            $nomeOriginal = $_FILES['midias']['name'][$i] ?? '';
            $tamanho = (int)($_FILES['midias']['size'][$i] ?? 0);
            $mimeInformado = (string)($_FILES['midias']['type'][$i] ?? '');
            $erroUpload = (int)($_FILES['midias']['error'][$i] ?? UPLOAD_ERR_NO_FILE);

            if ($erroUpload === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($erroUpload !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Erro no upload de uma das mídias. Código: ' . $erroUpload);
            }

            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                continue;
            }

            if ($tamanho <= 0) {
                continue;
            }

            $ext = strtolower((string)pathinfo($nomeOriginal, PATHINFO_EXTENSION));
            $extPermitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov', 'm4v', 'mp3', 'ogg', 'wav', 'pdf'];

            if ($ext !== '' && !in_array($ext, $extPermitidas, true)) {
                throw new RuntimeException('Tipo de arquivo não permitido: ' . $ext);
            }

            $novoNome = uniqid('midia_', true) . ($ext !== '' ? '.' . $ext : '');
            $destino = $pastaDemanda . '/' . $novoNome;

            if (!move_uploaded_file($tmpName, $destino)) {
                throw new RuntimeException('Falha ao mover uma das mídias da demanda.');
            }

            @chmod($destino, 0644);

            $mimeReal = (string)(mime_content_type($destino) ?: $mimeInformado ?: 'application/octet-stream');

            $tipo = 'arquivo';
            if (str_starts_with($mimeReal, 'image/')) {
                $tipo = 'imagem';
            } elseif (str_starts_with($mimeReal, 'video/')) {
                $tipo = 'video';
            } elseif (str_starts_with($mimeReal, 'audio/')) {
                $tipo = 'audio';
            }

            $caminhoBanco = '/uploads/demandas/' . $demandaId . '/' . $novoNome;

            $stmtMidia = $pdo->prepare("
                INSERT INTO demandas_midias
                (demanda_id, tipo, arquivo, mime, tamanho, criado_por)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmtMidia->execute([
                $demandaId,
                $tipo,
                $caminhoBanco,
                $mimeReal,
                $tamanho,
                $liderId
            ]);
        }
    }

    $pdo->commit();

    if ($isAjax) {
        responderJson([
            'ok' => true,
            'mensagem' => 'Demanda cadastrada com sucesso.',
            'demanda_id' => $demandaId,
            'protocolo' => $protocolo,
            'redirect' => 'ver-demanda.php?id=' . $demandaId,
        ]);
    }

    header('Location: ver-demanda.php?id=' . $demandaId);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    falhar($e->getMessage(), 500);
}