<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, bool $ok, string $message): void {
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, false, 'Дозвољено је само слање формулара.');
}

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && !preg_match('~^https?://(www\.)?piorajac\.rs$~i', $origin) && !preg_match('~^https?://(localhost|127\.0\.0\.1)(:\d+)?$~i', $origin)) {
    respond(403, false, 'Захтев није послат са дозвољене странице.');
}

$honeypot = $_POST['website'] ?? '';
if (!is_string($honeypot) || trim($honeypot) !== '') {
    respond(200, true, 'Хвала. Порука је примљена.');
}

function field(string $name, int $max): string {
    $raw = $_POST[$name] ?? '';
    if (!is_string($raw) || !mb_check_encoding($raw, 'UTF-8')) return '';
    $value = trim($raw);
    $value = preg_replace('/\R/u', "\n", $value);
    return mb_substr($value, 0, $max, 'UTF-8');
}

$name = str_replace(["\r", "\n"], '', field('name', 120));
$email = filter_var(field('email', 190), FILTER_VALIDATE_EMAIL) ?: '';
$species = str_replace(["\r", "\n"], '', field('species', 180) ?: field('bird', 180));
$latin = field('latin', 180);
$page = filter_var(field('page', 700), FILTER_VALIDATE_URL) ?: '';
$type = field('type', 100);
$comment = field('comment', 6000);
$correction = field('correction', 6000);
$sources = field('sources', 4000);
$consent = ($_POST['consent'] ?? '') === 'yes';

if ($name === '' || $email === '' || $species === '' || $type === '' || $comment === '' || !$consent) {
    respond(422, false, 'Попуните име, исправан мејл, врсту примедбе и опис, па потврдите слање.');
}

$allowedTypes = [
    'Исправка чињеничне грешке',
    'Допуна или прецизније објашњење',
    'Сугестија за побољшање странице',
    'Литература или поуздан извор',
    'Локални податак са Рајца',
    'Техничка грешка на страници',
    'Остало'
];
if (!in_array($type, $allowedTypes, true)) {
    respond(422, false, 'Изабрана врста примедбе није исправна.');
}

$ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rateDir = sys_get_temp_dir() . '/pio-rajac-sisari-' . sha1(__DIR__);
if ((!is_dir($rateDir) && !@mkdir($rateDir, 0700) && !is_dir($rateDir)) || is_link($rateDir) || !is_dir($rateDir)) {
    respond(503, false, 'Слање тренутно није доступно. Покушајте касније.');
}
if (function_exists('posix_geteuid') && fileowner($rateDir) !== posix_geteuid()) {
    respond(503, false, 'Слање тренутно није доступно. Покушајте касније.');
}
$rateFile = $rateDir . '/' . sha1($ip) . '.rate';
if (is_link($rateFile)) respond(503, false, 'Слање тренутно није доступно. Покушајте касније.');
$rateHandle = @fopen($rateFile, 'c+');
if ($rateHandle === false || !flock($rateHandle, LOCK_EX)) {
    if (is_resource($rateHandle)) fclose($rateHandle);
    respond(503, false, 'Слање тренутно није доступно. Покушајте касније.');
}
$now = time();
$previous = (int)stream_get_contents($rateHandle);
if ($previous > 0 && ($now - $previous) < 45) {
    flock($rateHandle, LOCK_UN);
    fclose($rateHandle);
    respond(429, false, 'Сачекајте мало пре поновног слања.');
}
$rateValue = (string)$now;
$rateSaved = ftruncate($rateHandle, 0) && rewind($rateHandle) && fwrite($rateHandle, $rateValue) === strlen($rateValue) && fflush($rateHandle);
flock($rateHandle, LOCK_UN);
fclose($rateHandle);
if (!$rateSaved) respond(503, false, 'Слање тренутно није доступно. Покушајте касније.');

$attachments = [];
register_shutdown_function(static function () use (&$attachments): void {
    foreach ($attachments as $attachment) {
        if (is_file($attachment)) @unlink($attachment);
    }
});
$allowedMime = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
    'text/plain' => 'txt'
];
$totalSize = 0;

if (!empty($_FILES['attachments']) && !is_array($_FILES['attachments']['name'] ?? null)) {
    respond(422, false, 'Подаци о прилозима нису исправни.');
}
if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'] ?? null)) {
    $count = count($_FILES['attachments']['name']);
    if ($count > 3) respond(422, false, 'Можете послати највише три прилога.');
    foreach (['name', 'error', 'tmp_name', 'size'] as $key) {
        if (!isset($_FILES['attachments'][$key]) || !is_array($_FILES['attachments'][$key]) || count($_FILES['attachments'][$key]) !== $count) {
            respond(422, false, 'Подаци о прилозима нису исправни.');
        }
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    for ($i = 0; $i < $count; $i++) {
        foreach (['name', 'error', 'tmp_name', 'size'] as $key) {
            if (!array_key_exists($i, $_FILES['attachments'][$key]) || !is_scalar($_FILES['attachments'][$key][$i])) {
                respond(422, false, 'Подаци о прилозима нису исправни.');
            }
        }
        $error = (int)($_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            respond(422, false, 'Један од прилога није успешно примљен.');
        }
        $tmp = (string)($_FILES['attachments']['tmp_name'][$i] ?? '');
        $size = (int)($_FILES['attachments']['size'][$i] ?? 0);
        $mime = $tmp !== '' && is_uploaded_file($tmp) ? (string)$finfo->file($tmp) : '';
        if (!isset($allowedMime[$mime]) || $size < 1 || $size > 8 * 1024 * 1024) {
            respond(422, false, 'Прилози могу бити JPG, PNG, WEBP, PDF или TXT, до 8 MB по фајлу.');
        }
        $totalSize += $size;
        if ($totalSize > 15 * 1024 * 1024) {
            respond(422, false, 'Укупна величина прилога не сме бити већа од 15 MB.');
        }
        $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo((string)$_FILES['attachments']['name'][$i], PATHINFO_FILENAME));
        $safeBase = trim((string)$safeBase, '.-_') ?: 'prilog';
        $destination = sys_get_temp_dir() . '/pio-rajac-' . bin2hex(random_bytes(8)) . '-' . $safeBase . '.' . $allowedMime[$mime];
        if (!move_uploaded_file($tmp, $destination)) {
            respond(500, false, 'Прилог није могао да буде припремљен за слање.');
        }
        $attachments[] = $destination;
    }
}

$subject = 'Примедба за сисара: ' . $species;
$body = "НОВИ УРЕДНИЧКИ ДОПРИНОС\n\n"
    . "Сисар: {$species}\n"
    . ($latin !== '' ? "Латински назив: {$latin}\n" : '')
    . ($page !== '' ? "Страница: {$page}\n" : '')
    . "Врста примедбе: {$type}\n\n"
    . "ПРИМЕДБА / УОЧЕНА ГРЕШКА\n{$comment}\n\n"
    . "ПРЕДЛОЖЕНА ИСПРАВКА ИЛИ ДОПУНА\n" . ($correction !== '' ? $correction : 'Није наведена.') . "\n\n"
    . "ЛИТЕРАТУРА И ИЗВОРИ\n" . ($sources !== '' ? $sources : 'Нису наведени.') . "\n\n"
    . "ПОШИЉАЛАЦ\n{$name}\n{$email}\n";

$sent = false;
$wpLoadCandidates = [
    dirname(__DIR__, 3) . '/wp-load.php',
    dirname(__DIR__, 2) . '/wp-load.php',
    dirname(__DIR__) . '/wp-load.php'
];
foreach ($wpLoadCandidates as $wpLoad) {
    if (is_file($wpLoad)) {
        require_once $wpLoad;
        break;
    }
}

if (function_exists('wp_mail')) {
    $sent = wp_mail(
        'sisari@piorajac.rs',
        $subject,
        $body,
        ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $email],
        $attachments
    );
} else {
    $boundary = 'pio-rajac-' . bin2hex(random_bytes(12));
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        'From: PIO Rajac sisari <sisari@piorajac.rs>',
        'Reply-To: ' . $email
    ];
    $message = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $body . "\r\n";
    foreach ($attachments as $attachment) {
        $filename = basename($attachment);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($attachment) ?: 'application/octet-stream';
        $message .= '--' . $boundary . "\r\n"
            . 'Content-Type: ' . $mime . '; name="' . addcslashes($filename, '"\\') . '"' . "\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . 'Content-Disposition: attachment; filename="' . addcslashes($filename, '"\\') . '"' . "\r\n\r\n"
            . chunk_split(base64_encode((string)file_get_contents($attachment))) . "\r\n";
    }
    $message .= '--' . $boundary . "--\r\n";
    $sent = function_exists('mail') && mail('sisari@piorajac.rs', '=?UTF-8?B?' . base64_encode($subject) . '?=', $message, implode("\r\n", $headers));
}

foreach ($attachments as $attachment) {
    @unlink($attachment);
}

if (!$sent) {
    respond(500, false, 'Порука тренутно није могла да буде послата. Покушајте поново касније.');
}

respond(200, true, 'Хвала. Ваша примедба је директно послата уредништву каталога сисара.');
