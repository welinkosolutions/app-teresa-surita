<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$pessoaIdFooter = (int) ($_SESSION['pessoa_id'] ?? 0);

if (!isset($pdo) || !$pdo instanceof PDO) {
    require_once '/home/elab/public_html/core/data/config.php';
    require_once '/home/elab/public_html/core/data/data.php';
    $pdo = dbRoraima();
}

if (!function_exists('footerTenantDominioAtual')) {
    function footerTenantDominioAtual(): string
    {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        return preg_replace('/:\d+$/', '', $host) ?: '';
    }
}

if (!function_exists('footerTenantIdAtual')) {
    function footerTenantIdAtual(PDO $pdo): int
    {
        static $tenantId = 0;

        if ($tenantId > 0) {
            return $tenantId;
        }

        $dominio = footerTenantDominioAtual();

        if ($dominio === '') {
            return 0;
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM clientes_elab
            WHERE dominio = ?
              AND status = 'ativo'
            LIMIT 1
        ");
        $stmt->execute([$dominio]);

        $tenantId = (int) ($stmt->fetchColumn() ?: 0);

        return $tenantId;
    }
}

if (!function_exists('footerTemAcessoEspecial')) {
    function footerTemAcessoEspecial(PDO $pdo, int $tenantId, int $pessoaId): bool
    {
        if ($tenantId <= 0 || $pessoaId <= 0) {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM acessos_especiais
            WHERE tenant_cliente_id = ?
              AND pessoa_id = ?
              AND status = 'ativo'
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $pessoaId]);

        return (bool) $stmt->fetchColumn();
    }
}

$tenantIdFooter = footerTenantIdAtual($pdo);
$mostrarMenuEspecial = footerTemAcessoEspecial($pdo, $tenantIdFooter, $pessoaIdFooter);

$footerItems = [
    [
        'key' => 'inicial',
        'label' => 'Inicial',
        'url' => '/dashboard/index.php',
        'icon' => '/assets/footer/icons/home.svg',
    ],
    [
        'key' => 'missao',
        'label_html' => 'Miss&atilde;o',
        'url' => '/missao/painel.php',
        'icon' => '/assets/footer/icons/block.svg',
    ],
    [
        'key' => 'ranking',
        'label' => 'Ranking',
        'url' => '/comunidade/ranking.php',
        'icon' => '/assets/footer/icons/winner.svg',
    ],
    [
        'key' => 'comunidade',
        'label' => 'Comunidade',
        'url' => '/comunidade/social.php',
        'icon' => '/assets/footer/icons/feedback.svg',
    ],
    [
        'key' => 'perfil',
        'label' => 'Perfil',
        'url' => '/pessoas/perfil.php',
        'icon' => '/assets/footer/icons/follower.svg',
    ],
];

if ($mostrarMenuEspecial) {
    $footerItems[] = [
        'key' => 'especial',
        'label' => 'Admin',
        'url' => '/interno/',
        'icon' => '/assets/footer/icons/add-user.svg',
        'special' => true,
    ];
}

$currentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';

if (!function_exists('footerItemAtivo')) {
    function footerItemAtivo(string $currentPath, string $url, string $key): bool
    {
        if ($key === 'inicial') {
            return str_starts_with($currentPath, '/dashboard/index.php');
        }

        if ($key === 'missao') {
            return str_starts_with($currentPath, '/comunidade/painel.php');
        }

        if ($key === 'ranking') {
            return str_starts_with($currentPath, '/comunidade/ranking.php');
        }

        if ($key === 'comunidade') {
            return str_starts_with($currentPath, '/comunidade/social.php');
        }

        if ($key === 'perfil') {
            return str_starts_with($currentPath, '/pessoas/perfil.php')
                || str_starts_with($currentPath, '/pessoas/');
        }

        if ($key === 'especial') {
            return str_starts_with($currentPath, '/interno/');
        }

        if ($url === '/') {
            return $currentPath === '/';
        }

        return str_starts_with($currentPath, rtrim($url, '/'));
    }
}
?>

<link rel="stylesheet" href="/assets/css/footer-v2.css?v=4">

<nav class="elab-footer-v2" aria-label="Menu principal">
    <div class="elab-footer-v2-inner">
        <?php foreach ($footerItems as $item): ?>
            <?php
            $isActive = footerItemAtivo(
                $currentPath,
                (string) $item['url'],
                (string) $item['key']
            );

            $classes = 'elab-footer-v2-item';
            $classes .= $isActive ? ' is-active' : '';
            $classes .= !empty($item['special']) ? ' is-special' : '';
            ?>

            <a
                href="<?= htmlspecialchars((string) $item['url'], ENT_QUOTES, 'UTF-8') ?>"
                class="<?= htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') ?>"
            >
                <span class="elab-footer-v2-icon-wrap">
                    <img
                        src="<?= htmlspecialchars((string) $item['icon'], ENT_QUOTES, 'UTF-8') ?>"
                        alt=""
                        class="elab-footer-v2-icon"
                        width="31"
                        height="31"
                        loading="eager"
                    >
                </span>

                <span class="elab-footer-v2-label">
                    <?php if (isset($item['label_html'])): ?>
                        <?= (string) $item['label_html'] ?>
                    <?php else: ?>
                        <?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</nav>