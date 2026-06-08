<?php
declare(strict_types=1);

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';
require_once '/home/elab/app.elab.social/vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/* ================= SEGURANÇA SIMPLES ================= */
/* PROTEGER ISSO DEPOIS COM TOKEN OU PERFIL ADMIN */

$pdo = db();

$stmt = $pdo->query("
    SELECT endpoint, p256dh, auth
    FROM app_push_subscriptions
");

$subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($subs)) {
    die('Nenhuma subscription encontrada.');
}

$auth = [
    'VAPID' => [
        'subject' => PUSH_VAPID_SUBJECT,
        'publicKey' => PUSH_VAPID_PUBLIC,
        'privateKey' => PUSH_VAPID_PRIVATE,
    ],
];

$webPush = new WebPush($auth);

foreach ($subs as $sub) {

    $subscription = Subscription::create([
        'endpoint' => $sub['endpoint'],
        'keys' => [
            'p256dh' => $sub['p256dh'],
            'auth' => $sub['auth'],
        ],
    ]);

    $payload = json_encode([
        'title' => '😮 Eiii!! Acabou de cair 3 casas no ranking! ',
        'body'  => 'Clica agora para ver no Instagram, curtir e comentar para voltar a sua posição!!!.',
        'url'   => '/dashboard/compartilhar.php'
    ]);

    $webPush->queueNotification($subscription, $payload);
}

echo "<pre>";

foreach ($webPush->flush() as $report) {

    $endpoint = $report->getRequest()->getUri()->__toString();

    if ($report->isSuccess()) {
        echo "SUCESSO: $endpoint\n";
    } else {
        echo "ERRO: $endpoint\n";
        echo $report->getReason() . "\n";
    }
}

echo "</pre>";