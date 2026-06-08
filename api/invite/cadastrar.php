<?php
declare(strict_types=1);

date_default_timezone_set('America/Boa_Vista');

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

/*
=====================================================
CORE
=====================================================
*/
$CORE = '/home/elab/public_html/core';

require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';
require_once $CORE . '/invite/engine.php';

$pdo = dbRoraima();

/*
=====================================================
HELPERS
=====================================================
*/
function inviteJsonOut(array $payload, int $httpCode = 200): never
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function normalizar(?string $v): ?string
{
    if ($v === null) {
        return null;
    }

    $v = trim((string) preg_replace('/\s+/', ' ', $v));

    if ($v === '') {
        return null;
    }

    return mb_convert_case($v, MB_CASE_TITLE, 'UTF-8');
}

function normalizarNomeBusca(?string $v): ?string
{
    if ($v === null) {
        return null;
    }

    $v = trim((string) preg_replace('/\s+/', ' ', $v));

    if ($v === '') {
        return null;
    }

    if (function_exists('iconv')) {
        $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
        if ($convertido !== false) {
            $v = $convertido;
        }
    }

    $v = mb_strtolower($v, 'UTF-8');
    $v = preg_replace('/[^a-z0-9\s]/', ' ', $v);
    $v = preg_replace('/\s+/', ' ', $v);
    $v = trim($v);

    return $v !== '' ? $v : null;
}

function inviteLogCadastro(string $tipo, string $msg): void
{
    error_log('[INVITE_CADASTRO][' . $tipo . '] ' . $msg);
}

function inviteIdadeMinimaValida(string $data, int $idadeMinima = 16): bool
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $data);

    if (!$dt || $dt->format('Y-m-d') !== $data) {
        return false;
    }

    $hoje = new DateTimeImmutable('today');
    $idade = $dt->diff($hoje)->y;

    return $idade >= $idadeMinima;
}

function inviteUserAgentBloqueado(?string $ua): bool
{
    $ua = trim((string) $ua);

    if ($ua === '') {
        return true;
    }

    $bloqueados = [
        'curl/',
        'python-requests',
        'wget',
        'aiohttp',
        'scrapy',
        'httpclient',
        'libwww-perl',
        'go-http-client',
        'postmanruntime',
        'insomnia',
    ];

    $uaLower = strtolower($ua);

    foreach ($bloqueados as $assinatura) {
        if (str_contains($uaLower, strtolower($assinatura))) {
            return true;
        }
    }

    return false;
}

function inviteGerarPin4Digitos(): string
{
    return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
}

function inviteTableMetaLocal(PDO $pdo, string $table): array
{
    static $cache = [];

    $key = spl_object_id($pdo) . ':' . $table;

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $meta = [];
    foreach ($rows as $row) {
        if (!empty($row['Field'])) {
            $meta[(string) $row['Field']] = $row;
        }
    }

    $cache[$key] = $meta;

    return $meta;
}

function inviteHasColumnLocal(PDO $pdo, string $table, string $column): bool
{
    $meta = inviteTableMetaLocal($pdo, $table);
    return isset($meta[$column]);
}

function inviteEnumValuesLocal(PDO $pdo, string $table, string $column): array
{
    $meta = inviteTableMetaLocal($pdo, $table);

    if (!isset($meta[$column]['Type'])) {
        return [];
    }

    $type = (string) $meta[$column]['Type'];

    if (!preg_match('/^enum\((.*)\)$/i', $type, $m)) {
        return [];
    }

    $raw = $m[1];
    $values = str_getcsv($raw, ',', "'");

    return array_values(array_filter(array_map('trim', $values), static fn($v) => $v !== ''));
}

function inviteResolveEnumValueLocal(PDO $pdo, string $table, string $column, array $preferidos, string $fallback = ''): string
{
    $values = inviteEnumValuesLocal($pdo, $table, $column);

    if ($values === []) {
        return $fallback;
    }

    foreach ($preferidos as $preferido) {
        if (in_array($preferido, $values, true)) {
            return $preferido;
        }
    }

    if ($fallback !== '' && in_array($fallback, $values, true)) {
        return $fallback;
    }

    return (string) $values[0];
}

function invitePessoaStatusParaLogin(PDO $pdo): string
{
    if (!inviteHasColumnLocal($pdo, 'pessoas', 'status')) {
        return 'ativo';
    }

    return inviteResolveEnumValueLocal(
        $pdo,
        'pessoas',
        'status',
        ['ativo'],
        'ativo'
    );
}

function invitePessoaStatusValidacaoPendente(PDO $pdo): ?string
{
    if (!inviteHasColumnLocal($pdo, 'pessoas', 'status_validacao')) {
        return null;
    }

    return inviteResolveEnumValueLocal(
        $pdo,
        'pessoas',
        'status_validacao',
        ['pendente', 'nao', 'aguardando_aprovacao', 'aguardando', 'em_analise', 'nao_validado'],
        'pendente'
    );
}

function invitePessoaOrigemConvite(PDO $pdo): ?string
{
    if (!inviteHasColumnLocal($pdo, 'pessoas', 'origem')) {
        return null;
    }

    return inviteResolveEnumValueLocal(
        $pdo,
        'pessoas',
        'origem',
        ['convite', 'indicacao'],
        'convite'
    );
}

/*
=====================================================
METHOD
=====================================================
*/
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    inviteJsonOut([
        'status' => 'erro',
        'msg'    => 'Método não permitido'
    ], 405);
}

/*
=====================================================
BLOQUEIO BÁSICO DE AUTOMAÇÃO
=====================================================
*/
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

if (inviteUserAgentBloqueado($ua)) {
    inviteLogCadastro('BLOQUEIO_UA', 'IP=' . $ip . ' UA=' . $ua);

    inviteJsonOut([
        'status' => 'erro',
        'msg'    => 'Não foi possível validar seu acesso. Tente novamente pelo navegador do celular.'
    ], 403);
}

/*
=====================================================
INPUT
=====================================================
*/
$token            = trim((string) ($_POST['invite_token'] ?? ''));
$codigo           = trim((string) ($_POST['invite_codigo'] ?? ''));
$nome             = normalizar($_POST['nome'] ?? null);
$nome_busca       = normalizarNomeBusca($_POST['nome'] ?? null);
$apelido          = normalizar($_POST['apelido'] ?? null);
$chamar_por       = (($_POST['chamar_por'] ?? 'nome') === 'apelido') ? 'apelido' : 'nome';
$data_nascimento  = trim((string) ($_POST['data_nascimento'] ?? ''));
$nome_mae         = normalizar($_POST['nome_mae'] ?? null);
$sexo             = trim((string) ($_POST['sexo'] ?? ''));
$telefone         = preg_replace('/\D+/', '', (string) ($_POST['telefone'] ?? ''));

$cep              = preg_replace('/\D+/', '', (string) ($_POST['cep'] ?? ''));
$endereco         = normalizar($_POST['endereco'] ?? null);
$numero           = normalizar($_POST['numero'] ?? null);
$complemento      = normalizar($_POST['complemento'] ?? null);
$bairro           = normalizar($_POST['bairro'] ?? null);
$cidade           = normalizar($_POST['cidade'] ?? null);
$estado           = normalizar($_POST['estado'] ?? null);

$local_trabalho   = normalizar($_POST['local_trabalho'] ?? null);

$latitude         = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (string) $_POST['latitude'] : null;
$longitude        = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (string) $_POST['longitude'] : null;

/*
=====================================================
VALIDAÇÃO BÁSICA
=====================================================
*/
if ($token === '' && $codigo === '') {
    inviteJsonOut([
        'status' => 'erro',
        'msg'    => 'Convite inválido'
    ], 400);
}

if (!$nome || !$nome_mae || !$data_nascimento || !in_array($sexo, ['F', 'M', 'O'], true)) {
    inviteJsonOut([
        'status' => 'erro',
        'msg'    => 'Dados pessoais incompletos'
    ], 400);
}

if (!inviteIdadeMinimaValida($data_nascimento, 16)) {
    inviteJsonOut([
        'status' => 'erro',
        'msg'    => 'É necessário ter pelo menos 16 anos para participar'
    ], 400);
}

if (strlen($telefone) !== 11) {
    inviteJsonOut([
        'status' => 'erro',
        'msg'    => 'Telefone inválido'
    ], 400);
}

if (!$cep || !$endereco || !$numero || !$bairro || !$cidade || !$estado) {
    inviteJsonOut([
        'status' => 'erro',
        'msg'    => 'Endereço incompleto'
    ], 400);
}

/*
=====================================================
VALIDAR TELEFONE
=====================================================
*/
$stmt = $pdo->prepare("
    SELECT id
    FROM pessoas
    WHERE telefone = ?
    LIMIT 1
");
$stmt->execute([$telefone]);

if ($stmt->fetchColumn()) {
    inviteJsonOut([
        'status' => 'erro',
        'msg'    => 'Telefone já cadastrado'
    ], 409);
}

/*
=====================================================
RESOLVER ORIGEM DO CONVITE
=====================================================
*/
$origemResolvida = inviteResolverOrigemEntrada($pdo, $token, $codigo);

if (!$origemResolvida) {
    inviteJsonOut([
        'status' => 'erro',
        'msg'    => 'Convite inválido'
    ], 404);
}

$modo = (string) ($origemResolvida['modo'] ?? '');
$convidadorId = (int) ($origemResolvida['convidador_id'] ?? 0);

if ($modo === 'token') {
    $convite = is_array($origemResolvida['convite'] ?? null) ? $origemResolvida['convite'] : null;

    if (!$convite) {
        inviteJsonOut([
            'status' => 'erro',
            'msg'    => 'Convite inválido'
        ], 404);
    }

    $conviteAceito = inviteConviteAceitaCadastro($pdo, $convite);

    if (!$conviteAceito) {
        inviteJsonOut([
            'status' => 'erro',
            'msg'    => 'Convite indisponível no momento'
        ], 409);
    }

    $convidadorId = (int) ($convite['convidador_id'] ?? 0);
}

if ($modo === 'codigo') {
    $linkPublico = is_array($origemResolvida['link_publico'] ?? null) ? $origemResolvida['link_publico'] : null;

    if (!$linkPublico || !inviteLinkPublicoEstaAtivo($linkPublico)) {
        inviteJsonOut([
            'status' => 'erro',
            'msg'    => 'Convite indisponível no momento'
        ], 409);
    }

    $convidadorId = (int) ($linkPublico['pessoa_id'] ?? 0);
}

if ($convidadorId <= 0) {
    inviteJsonOut([
        'status' => 'erro',
        'msg'    => 'Convite inválido'
    ], 400);
}

/*
=====================================================
VALIDAR CONVIDADOR
=====================================================
*/
$stmt = $pdo->prepare("
    SELECT id
    FROM pessoas
    WHERE id = ?
      AND status = 'ativo'
    LIMIT 1
");
$stmt->execute([$convidadorId]);

if (!$stmt->fetchColumn()) {
    inviteJsonOut([
        'status' => 'erro',
        'msg'    => 'Convite indisponível no momento'
    ], 409);
}

/*
=====================================================
PREPARAR STATUS / ORIGEM
=====================================================
*/
$statusPessoa = invitePessoaStatusParaLogin($pdo);
$statusValidacao = invitePessoaStatusValidacaoPendente($pdo);
$origemPessoa = invitePessoaOrigemConvite($pdo);

/*
=====================================================
GERAR PIN ÚNICO DE 4 DÍGITOS
=====================================================
*/
$pinProvisorio = inviteGerarPin4Digitos();
$pinHash = password_hash($pinProvisorio, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    /*
    =====================================================
    CRIAR PESSOA
    =====================================================
    */
    $sql = "
        INSERT INTO pessoas
        (
            nome,
            nome_busca,
            apelido,
            chamar_por,
            data_nascimento,
            nome_mae,
            sexo,
            telefone,
            local_trabalho,
            pin,
            perfil,
            vinculo,
            status,
            origem,
            status_validacao,
            criado_por,
            latitude,
            longitude,
            criado_em
        )
        VALUES
        (
            :nome,
            :nome_busca,
            :apelido,
            :chamar_por,
            :data_nascimento,
            :nome_mae,
            :sexo,
            :telefone,
            :local_trabalho,
            :pin,
            'pessoa',
            'geral',
            :status,
            :origem,
            :status_validacao,
            :criado_por,
            :latitude,
            :longitude,
            NOW()
        )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome'              => $nome,
        ':nome_busca'        => $nome_busca,
        ':apelido'           => $apelido,
        ':chamar_por'        => $chamar_por,
        ':data_nascimento'   => $data_nascimento,
        ':nome_mae'          => $nome_mae,
        ':sexo'              => $sexo,
        ':telefone'          => $telefone,
        ':local_trabalho'    => $local_trabalho,
        ':pin'               => $pinHash,
        ':status'            => $statusPessoa,
        ':origem'            => $origemPessoa ?? 'convite',
        ':status_validacao'  => $statusValidacao ?? 'pendente',
        ':criado_por'        => $convidadorId,
        ':latitude'          => $latitude,
        ':longitude'         => $longitude
    ]);

    $novoUsuarioId = (int) $pdo->lastInsertId();

    if ($novoUsuarioId <= 0) {
        throw new RuntimeException('Falha ao criar a pessoa.');
    }

    /*
    =====================================================
    INSERIR ENDEREÇO
    =====================================================
    */
    $stmt = $pdo->prepare("
        INSERT INTO pessoas_enderecos
        (
            pessoa_id,
            cep,
            endereco,
            numero,
            complemento,
            bairro,
            cidade,
            estado,
            latitude,
            longitude,
            tipo
        )
        VALUES
        (
            :pessoa_id,
            :cep,
            :endereco,
            :numero,
            :complemento,
            :bairro,
            :cidade,
            :estado,
            :latitude,
            :longitude,
            'residencial'
        )
    ");

    $stmt->execute([
        ':pessoa_id'   => $novoUsuarioId,
        ':cep'         => $cep ?: null,
        ':endereco'    => $endereco,
        ':numero'      => $numero,
        ':complemento' => $complemento,
        ':bairro'      => $bairro,
        ':cidade'      => $cidade,
        ':estado'      => $estado,
        ':latitude'    => $latitude,
        ':longitude'   => $longitude
    ]);

    /*
    =====================================================
    REGISTRAR PENDÊNCIA DE APROVAÇÃO
    =====================================================
    */
    if ($modo === 'codigo') {
        $registrado = inviteRegistrarCadastroPorCodigo($pdo, $codigo, $novoUsuarioId);
    } else {
        $registrado = inviteRegistrarCadastro($pdo, $token, $novoUsuarioId);
    }

    if ($registrado === null) {
        throw new RuntimeException('Não foi possível registrar a pendência de aprovação.');
    }

    $pdo->commit();

    inviteJsonOut([
        'status'            => 'ok',
        'aguarda_aprovacao' => true,
        'pin_provisorio'    => $pinProvisorio,
        'msg'               => 'Cadastro realizado com sucesso. Guarde seu PIN e aguarde a ativação manual da sua conta.'
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[INVITE_CADASTRO] ' . $e->getMessage());

    echo json_encode([
        'status'  => 'erro',
        'msg'     => 'Erro ao criar cadastro',
        'debug'   => $e->getMessage(),
        'linha'   => $e->getLine(),
        'arquivo' => $e->getFile()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}