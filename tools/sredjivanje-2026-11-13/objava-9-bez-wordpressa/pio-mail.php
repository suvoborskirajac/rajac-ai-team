<?php
// Слање поште са сајта без WordPress-а — замена за wp_mail() (ПИО „Рајац“, 13. 11. 2026).
// Обрасци „Пошаљи допринос“ учитавају овај фајл уместо wp-load.php.
if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = [], $attachments = [])
    {
        $to = is_array($to) ? implode(', ', $to) : (string) $to;
        $lines = is_array($headers) ? $headers : preg_split("~\r?\n~", (string) $headers);
        $replyTo = '';
        $ctype = 'text/plain; charset=UTF-8';
        foreach ($lines as $h) {
            if (preg_match('~^\s*Reply-To:\s*(.+)$~i', $h, $m)) $replyTo = trim($m[1]);
            elseif (preg_match('~^\s*Content-Type:\s*(.+)$~i', $h, $m)) $ctype = trim($m[1]);
        }
        $replyTo = str_replace(["\r", "\n"], '', $replyTo);
        $enc = static function ($s) { return '=?UTF-8?B?' . base64_encode((string) $s) . '?='; };
        if ($replyTo !== '' && preg_match('~^(.*?)\s*<([^<>\s]+@[^<>\s]+)>$~', $replyTo, $m)) {
            $ime = trim($m[1], " \"");
            $replyTo = ($ime !== '' ? $enc($ime) . ' ' : '') . '<' . $m[2] . '>';
        }
        $from = 'office@piorajac.rs';
        $hdr = ['From: ' . $enc('ПИО „Рајац“ — сајт') . ' <' . $from . '>', 'MIME-Version: 1.0'];
        if ($replyTo !== '') $hdr[] = 'Reply-To: ' . $replyTo;
        $files = array_values(array_filter((array) $attachments, 'is_readable'));
        if (!$files) {
            $hdr[] = 'Content-Type: ' . $ctype;
            $hdr[] = 'Content-Transfer-Encoding: base64';
            $body = chunk_split(base64_encode((string) $message));
        } else {
            $b = 'pio' . bin2hex(random_bytes(12));
            $hdr[] = 'Content-Type: multipart/mixed; boundary="' . $b . '"';
            $body = "--$b\r\nContent-Type: $ctype\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode((string) $message));
            foreach ($files as $f) {
                $n = basename($f);
                $mt = function_exists('mime_content_type') ? (mime_content_type($f) ?: 'application/octet-stream') : 'application/octet-stream';
                $body .= "--$b\r\nContent-Type: $mt; name=\"$n\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$n\"\r\n\r\n"
                    . chunk_split(base64_encode((string) file_get_contents($f)));
            }
            $body .= "--$b--\r\n";
        }
        return mail($to, $enc($subject), $body, implode("\r\n", $hdr));
    }
}
