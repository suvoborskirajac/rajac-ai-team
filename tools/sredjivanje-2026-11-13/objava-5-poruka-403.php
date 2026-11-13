<?php
header('Content-Type: text/plain; charset=utf-8');
if (!isset($_GET['k']) || !hash_equals('__KLJUC__', $_GET['k'])) { http_response_code(404); exit; }
$R = __DIR__; $bk = dirname(__DIR__) . '/_rezerva_403_20261113'; $ht = file_get_contents("$R/.htaccess");
if (!is_file("$R/zabranjeno.html")) exit("СТОП: нема zabranjeno.html");
if (preg_match('~^\s*ErrorDocument\s+403~m', $ht)) exit("СТОП: већ постоји ErrorDocument 403");
$marker = "# END PIO-BEZBEDNOST"; if (substr_count($ht, $marker) !== 1) exit("СТОП: нема ознаке PIO-BEZBEDNOST");
echo "ПРОВЕРА: у реду\n"; if (empty($_GET['apply'])) exit;
@mkdir($bk, 0755, true); copy("$R/.htaccess", "$bk/.htaccess");
$new = str_replace($marker, "# Пријатна порука уместо голог 403 (код остаје 403) — 2026-11-13\nErrorDocument 403 /zabranjeno.html\n" . $marker, $ht);
echo file_put_contents("$R/.htaccess", $new) === strlen($new) ? "Уписано. Резерва: $bk\n" : "ГРЕШКА\n";
