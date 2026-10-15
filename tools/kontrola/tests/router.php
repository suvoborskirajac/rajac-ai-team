<?php
/**
 * САМО ЗА ТЕСТОВЕ — не поставља се на сервер.
 * Глуми веб-сервер који је већ проверио лозинку: ако је при покретању
 * сервера задата променљива окружења KONTROLA_TEST_KORISNIK, поставља
 * REMOTE_USER као што то ради Apache/LiteSpeed после успешне Basic провере.
 * Ништа из захтева (заглавља, параметри) не утиче на REMOTE_USER.
 */
$u = getenv('KONTROLA_TEST_KORISNIK');
if (is_string($u) && $u !== '') {
    $_SERVER['REMOTE_USER'] = $u;
}
$path = (string)parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $_SERVER['DOCUMENT_ROOT'] . $path;
if (substr($path, -4) === '.php' && is_file($file)) {
    chdir(dirname($file));
    $_SERVER['SCRIPT_NAME'] = $path;
    require $file;
    return true;
}
return false;
