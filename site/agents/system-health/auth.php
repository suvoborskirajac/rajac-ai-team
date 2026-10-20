<?php
declare(strict_types=1);
/*
 * Улаз само уз лозинку. Хеш лозинке је у config/lozinka.php (није у репозиторијуму;
 * фасцикла config/ је забрањена за приступ из прегледача).
 */
function rajac_sh_auth(bool $json = false): void {
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, max-age=0');
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('rajac_sh');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/agents/system-health/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
    if (isset($_GET['odjava'])) {
        $_SESSION = [];
        session_destroy();
        header('Location: ./');
        exit;
    }
    if (!empty($_SESSION['rajac_sh_ok'])) return;

    $hashFile = __DIR__ . '/config/lozinka.php';
    $hash = is_file($hashFile) ? (string)(require $hashFile) : '';
    $greska = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['lozinka'])) {
        if ($hash !== '' && password_verify((string)$_POST['lozinka'], $hash)) {
            session_regenerate_id(true);
            $_SESSION['rajac_sh_ok'] = true;
            header('Location: ' . strtok((string)($_SERVER['REQUEST_URI'] ?? './'), '?'));
            exit;
        }
        sleep(2);
        $greska = 'Погрешна лозинка.';
    }
    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Потребна је пријава.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(401);
    header('Content-Type: text/html; charset=utf-8');
    $g = htmlspecialchars($greska, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!doctype html><html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Пријава — ПИО „РАЈАЦ“</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#07150f;color:#eef8f1;font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif;padding:16px}
form{background:#0e2419;border:1px solid #284938;border-radius:16px;padding:24px;width:100%;max-width:360px}
h1{font-size:20px;margin:0 0 6px}p{color:#9ab3a4;margin:0 0 16px;font-size:14px}
input{width:100%;box-sizing:border-box;padding:12px;border-radius:10px;border:1px solid #284938;background:#07150f;color:#eef8f1;font-size:16px}
button{margin-top:12px;width:100%;padding:12px;border:0;border-radius:10px;background:#dff5e6;color:#07150f;font-weight:800;font-size:16px;cursor:pointer}
.err{color:#ff6666;margin-top:10px;font-size:14px}a{color:#8bd2a7}</style></head><body>
<form method="post"><h1>Агент надзора система</h1><p>ПИО „РАЈАЦ“ — интерна страница. Унесите лозинку.</p>
<input type="password" name="lozinka" autocomplete="current-password" autofocus required aria-label="Лозинка">
<button type="submit">Пријава</button><div class="err">{$g}</div>
<p style="margin-top:16px"><a href="https://piorajac.rs/">← Почетна страна</a></p></form></body></html>
HTML;
    exit;
}
