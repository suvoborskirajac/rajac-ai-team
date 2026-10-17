<?php
/**
 * rajac_inat_api.php — iNaturalist опсервације унутар границе ПИО „Рајац“
 *
 * Исправка 2026-06-16:
 * - више се НЕ узимају само research grade записи, већ и needs_id;
 * - више се НЕ користи слика таксона као главна слика, већ фотографија саме опсервације;
 * - не избацују се записи који су још на нивоу рода, нпр. Rod Echium;
 * - освежавање кеша је безбедније, са lock фајлом, да SQLite не остане закључан.
 *
 * Параметри:
 *   ?grupa=sve|biljke|leptiri|ptice|insekti|gmizavci|vodozemci|gljive|sisari|ostalo
 *   ?refresh=1       форсира ново повлачење са iNaturalist-а
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private, max-age=0');

const API_VERSION = '2026-10-16-privacy-v14';
// Raw sightings database must NEVER be reachable as a static public URL.
const DB_PATH = '/home/piorajac/rajac-private/inat/rajac_inat_cache.sqlite';
const LOCK_PATH = '/home/piorajac/rajac-private/inat/rajac_inat_refresh.lock';
require_once __DIR__ . '/rajac_inat_privacy.php';
const GRANICA     = __DIR__ . '/rajac_granica.json';
const CACHE_SATI  = 3;
const MAX_STRANA  = 10;   // по quality_grade вредности; 10 × 200 × 2 = до 4000 записа

$GRUPE_FILTER = [
    'sve'       => null,
    'biljke'    => ['Plantae'],
    'leptiri'   => ['__LEPTIRI__'],
    'ptice'     => ['Aves'],
    'insekti'   => ['Insecta'],
    'gmizavci'  => ['Reptilia'],
    'vodozemci' => ['Amphibia'],
    'gljive'    => ['Fungi'],
    'sisari'    => ['Mammalia'],
    'ostalo'    => ['Animalia','Arachnida','Mollusca','Protozoa','Chromista','Unknown'],
];

$grupa = $_GET['grupa'] ?? 'sve';
if (!array_key_exists($grupa, $GRUPE_FILTER)) $grupa = 'sve';

if (!is_file(GRANICA)) {
    odgovor_greska('Недостаје rajac_granica.json — нема границе за филтрирање.');
}

$g = json_decode((string)file_get_contents(GRANICA), true);
if (!is_array($g) || empty($g['bbox']) || empty($g['polygon'])) {
    odgovor_greska('rajac_granica.json није исправан JSON или нема bbox/polygon.');
}
$BBOX = $g['bbox'];
$POLY = $g['polygon'];

if (!is_file(DB_PATH)) {
    odgovor_greska('Приватна база није инсталирана — безбедносна блокада; немој враћати базу у public_html.');
}
$db = new SQLite3(DB_PATH, SQLITE3_OPEN_READWRITE);
$db->busyTimeout(8000);
@$db->exec('PRAGMA journal_mode=WAL');
@$db->exec('PRAGMA synchronous=NORMAL');
@$db->exec('PRAGMA temp_store=MEMORY');

$db->exec("CREATE TABLE IF NOT EXISTS meta (kljuc TEXT PRIMARY KEY, vrednost TEXT)");

$zadnje = intval(meta_get($db, 'zadnje_sve', '0'));
$verzija = meta_get($db, 'api_version', '');
$ima_obs = table_exists($db, 'obs');
$treba = PHP_SAPI === 'cli' && (in_array('--refresh', $argv ?? [], true) || !$ima_obs || $zadnje <= 0 || (time() - $zadnje) > CACHE_SATI * 3600 || $verzija !== API_VERSION);
$napomena = '';

if ($treba) {
    $ok = povuci_sve($db, $BBOX, $POLY);
    if (!$ok) {
        $napomena = 'Ново повлачење није успело или је већ у току; приказан је последњи доступан кеш.';
    }
}

$lista = citaj_listu($db, $grupa, $GRUPE_FILTER[$grupa]);
$osvezeno_ts = intval(meta_get($db, 'zadnje_sve', '0'));
if ($osvezeno_ts <= 0) $osvezeno_ts = time();

$json = [
    'podrucje'     => 'ПИО Рајац (унутар KML границе)',
    'grupa'        => $grupa,
    'broj'         => count($lista),
    'osvezeno'     => date('c', $osvezeno_ts),
    'izvor'        => 'iNaturalist',
    'api_version'  => API_VERSION,
    'napomena'     => $napomena,
    'brojevi'      => brojevi_grupa($db),
    'opservacije'  => $lista,
];

echo json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
$db->close();
exit;

function odgovor_greska(string $poruka): void {
    http_response_code(500);
    echo json_encode([
        'podrucje' => 'ПИО Рајац',
        'grupa' => 'sve',
        'broj' => 0,
        'osvezeno' => date('c'),
        'izvor' => 'iNaturalist',
        'napomena' => $poruka,
        'opservacije' => []
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function table_exists(SQLite3 $db, string $name): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=:name");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $r = $stmt->execute();
    return $r && intval($r->fetchArray(SQLITE3_NUM)[0] ?? 0) > 0;
}

function meta_get(SQLite3 $db, string $key, string $default=''): string {
    $stmt = $db->prepare("SELECT vrednost FROM meta WHERE kljuc=:k");
    if (!$stmt) return $default;
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $r = $stmt->execute();
    if (!$r) return $default;
    $row = $r->fetchArray(SQLITE3_ASSOC);
    return $row ? strval($row['vrednost']) : $default;
}

function meta_set(SQLite3 $db, string $key, string $value): void {
    $stmt = $db->prepare("INSERT OR REPLACE INTO meta(kljuc,vrednost) VALUES(:k,:v)");
    if (!$stmt) return;
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $stmt->bindValue(':v', $value, SQLITE3_TEXT);
    $stmt->execute();
}

function citaj_listu(SQLite3 $db, string $grupa, ?array $filter): array {
    if (!table_exists($db, 'obs')) return [];

    if ($grupa === 'sve') {
        $sql = "SELECT * FROM obs ORDER BY datum DESC, obs_id DESC";
    } elseif ($grupa === 'leptiri') {
        $sql = "SELECT * FROM obs WHERE COALESCE(je_leptir,0)=1 ORDER BY datum DESC, obs_id DESC";
    } elseif ($grupa === 'insekti') {
        $sql = "SELECT * FROM obs WHERE grupa_taxon='Insecta' AND COALESCE(je_leptir,0)=0 ORDER BY datum DESC, obs_id DESC";
    } else {
        $escaped = [];
        foreach (($filter ?? []) as $x) $escaped[] = "'" . SQLite3::escapeString($x) . "'";
        if (!$escaped) return [];
        $sql = "SELECT * FROM obs WHERE grupa_taxon IN (" . implode(',', $escaped) . ") ORDER BY datum DESC, obs_id DESC";
    }

    $r = $db->query($sql);
    if (!$r) return [];
    $lista = [];
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
        $lista[] = rajac_public_observation($row);
    }
    return $lista;
}

function db_count(SQLite3 $db, string $sql): int {
    $r = $db->querySingle($sql);
    return intval($r ?? 0);
}

function brojevi_grupa(SQLite3 $db): array {
    if (!table_exists($db, 'obs')) {
        return [
            'sve' => 0, 'biljke' => 0, 'ptice' => 0, 'leptiri' => 0, 'insekti' => 0,
            'gmizavci' => 0, 'vodozemci' => 0, 'gljive' => 0, 'sisari' => 0, 'ostalo' => 0
        ];
    }

    return [
        'sve'       => db_count($db, "SELECT COUNT(*) FROM obs"),
        'biljke'    => db_count($db, "SELECT COUNT(*) FROM obs WHERE grupa_taxon='Plantae'"),
        'ptice'     => db_count($db, "SELECT COUNT(*) FROM obs WHERE grupa_taxon='Aves'"),
        'leptiri'   => db_count($db, "SELECT COUNT(*) FROM obs WHERE COALESCE(je_leptir,0)=1"),
        'insekti'   => db_count($db, "SELECT COUNT(*) FROM obs WHERE grupa_taxon='Insecta' AND COALESCE(je_leptir,0)=0"),
        'gmizavci'  => db_count($db, "SELECT COUNT(*) FROM obs WHERE grupa_taxon='Reptilia'"),
        'vodozemci' => db_count($db, "SELECT COUNT(*) FROM obs WHERE grupa_taxon='Amphibia'"),
        'gljive'    => db_count($db, "SELECT COUNT(*) FROM obs WHERE grupa_taxon='Fungi'"),
        'sisari'    => db_count($db, "SELECT COUNT(*) FROM obs WHERE grupa_taxon='Mammalia'"),
        'ostalo'    => db_count($db, "SELECT COUNT(*) FROM obs WHERE grupa_taxon IN ('Animalia','Arachnida','Mollusca','Protozoa','Chromista','Unknown')"),
    ];
}

function povuci_sve(SQLite3 $db, array $bbox, array $poly): bool {
    $lock = @fopen(LOCK_PATH, 'c');
    if (!$lock) return false;
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return false;
    }

    $sve = [];
    $vidjeno = [];
    $uspeo_bar_jedan_poziv = false;
    $neuspeh = false; // било који неуспео захтев = не дирати постојећу евиденцију

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 45,
            'header'  => "User-Agent: piorajac.rs biodiversity monitoring (contact: office@piorajac.rs)\r\nAccept: application/json\r\n"
        ]
    ]);

    foreach (['research', 'needs_id'] as $quality) {
        for ($strana = 1; $strana <= MAX_STRANA; $strana++) {
            $url = 'https://api.inaturalist.org/v1/observations'
                 . '?swlat=' . rawurlencode(strval($bbox['swlat']))
                 . '&swlng=' . rawurlencode(strval($bbox['swlng']))
                 . '&nelat=' . rawurlencode(strval($bbox['nelat']))
                 . '&nelng=' . rawurlencode(strval($bbox['nelng']))
                 . '&quality_grade=' . rawurlencode($quality)
                 . '&per_page=200&page=' . $strana
                 . '&order_by=observed_on&order=desc&locale=sr';

            $json = @file_get_contents($url, false, $ctx);
            if ($json === false || $json === '') {
                $neuspeh = true;
                break 2;
            }
            $d = json_decode($json, true);
            if (!is_array($d) || !isset($d['results']) || !is_array($d['results'])) {
                $neuspeh = true;
                break 2;
            }
            $uspeo_bar_jedan_poziv = true;
            if (count($d['results']) === 0) break;

            foreach ($d['results'] as $o) {
                $id = intval($o['id'] ?? 0);
                if ($id <= 0 || isset($vidjeno[$id])) continue;

                if (empty($o['geojson']['coordinates']) || count($o['geojson']['coordinates']) < 2) continue;
                $lng = floatval($o['geojson']['coordinates'][0]);
                $lat = floatval($o['geojson']['coordinates'][1]);
                // Publicly obscured points must never be called exact inside-boundary coordinates.
                if (!u_poligonu($lng, $lat, $poly)) continue;

                $t = (isset($o['taxon']) && is_array($o['taxon'])) ? $o['taxon'] : [];
                $anc = (isset($t['ancestor_ids']) && is_array($t['ancestor_ids'])) ? $t['ancestor_ids'] : [];
                $je_leptir = in_array(47157, $anc, true) ? 1 : 0; // Lepidoptera

                $foto = izvuci_foto($o, $t);
                $naucni = strval($t['name'] ?? ($o['species_guess'] ?? ''));
                $narodni = strval($t['preferred_common_name'] ?? '');
                if ($narodni === '' && isset($o['species_guess']) && strval($o['species_guess']) !== $naucni) {
                    $narodni = strval($o['species_guess']);
                }

                $sve[] = [
                    'obs_id'        => $id,
                    'taxon_id'      => intval($t['id'] ?? 0),
                    'naucni'        => $naucni,
                    'narodni'       => $narodni,
                    'rank'          => strval($t['rank'] ?? ''),
                    'quality_grade' => strval($o['quality_grade'] ?? $quality),
                    'grupa_taxon'   => strval($t['iconic_taxon_name'] ?? 'Unknown'),
                    'je_leptir'     => $je_leptir,
                    'lat'           => $lat,
                    'lng'           => $lng,
                    'datum'         => strval($o['observed_on'] ?? ''),
                    'korisnik'      => strval($o['user']['login'] ?? ''),
                    'foto_url'      => $foto['url'],
                    'photo_source'  => $foto['source'],
                    'positional_accuracy' => isset($o['positional_accuracy']) && is_numeric($o['positional_accuracy']) ? floatval($o['positional_accuracy']) : null,
                    'coordinates_obscured' => !empty($o['coordinates_obscured']) ? 1 : 0,
                    'geoprivacy' => strval($o['geoprivacy'] ?? ''),
                    'taxon_geoprivacy' => strval($o['taxon_geoprivacy'] ?? ''),
                    'family_orchidaceae' => rajac_is_orchid_taxon($t) ? 1 : 0,
                    'url'           => 'https://www.inaturalist.org/observations/' . $id,
                ];
                $vidjeno[$id] = true;
            }

            if (count($d['results']) < 200) break;
            if ($strana === MAX_STRANA) { $neuspeh = true; break 2; } // непотпун списак
            usleep(1100000);
        }
    }

    if (!$uspeo_bar_jedan_poziv || $neuspeh) {
        fwrite(STDERR, date('c') . " iNaturalist освежавање није потпуно; задржана је постојећа евиденција.\n");
        flock($lock, LOCK_UN);
        fclose($lock);
        return false;
    }

    usnimi_cache($db, $sve);

    flock($lock, LOCK_UN);
    fclose($lock);
    return true;
}

function usnimi_cache(SQLite3 $db, array $rows): void {
    $db->exec('BEGIN IMMEDIATE');
    $db->exec('DROP TABLE IF EXISTS obs');
    $db->exec("CREATE TABLE obs (
        obs_id INTEGER PRIMARY KEY,
        taxon_id INTEGER DEFAULT 0,
        naucni TEXT,
        narodni TEXT,
        rank TEXT,
        quality_grade TEXT,
        grupa_taxon TEXT,
        je_leptir INTEGER DEFAULT 0,
        lat REAL,
        lng REAL,
        datum TEXT,
        korisnik TEXT,
        foto_url TEXT,
        photo_source TEXT,
        url TEXT,
        positional_accuracy REAL,
        coordinates_obscured INTEGER DEFAULT 0,
        geoprivacy TEXT,
        taxon_geoprivacy TEXT,
        family_orchidaceae INTEGER DEFAULT 0
    )");

    $ins = $db->prepare("INSERT OR REPLACE INTO obs
        (obs_id,taxon_id,naucni,narodni,rank,quality_grade,grupa_taxon,je_leptir,lat,lng,datum,korisnik,foto_url,photo_source,url,positional_accuracy,coordinates_obscured,geoprivacy,taxon_geoprivacy,family_orchidaceae)
        VALUES (:id,:taxon_id,:nau,:nar,:rank,:qg,:gt,:lep,:lat,:lng,:dat,:kor,:foto,:ps,:url,:accuracy,:obscured,:geoprivacy,:taxon_privacy,:orchid)");

    if ($ins) {
        foreach ($rows as $r) {
            $ins->reset();
            $ins->clear();
            $ins->bindValue(':id',       intval($r['obs_id']), SQLITE3_INTEGER);
            $ins->bindValue(':taxon_id', intval($r['taxon_id']), SQLITE3_INTEGER);
            $ins->bindValue(':nau',      strval($r['naucni']), SQLITE3_TEXT);
            $ins->bindValue(':nar',      strval($r['narodni']), SQLITE3_TEXT);
            $ins->bindValue(':rank',     strval($r['rank']), SQLITE3_TEXT);
            $ins->bindValue(':qg',       strval($r['quality_grade']), SQLITE3_TEXT);
            $ins->bindValue(':gt',       strval($r['grupa_taxon']), SQLITE3_TEXT);
            $ins->bindValue(':lep',      intval($r['je_leptir']), SQLITE3_INTEGER);
            $ins->bindValue(':lat',      floatval($r['lat']), SQLITE3_FLOAT);
            $ins->bindValue(':lng',      floatval($r['lng']), SQLITE3_FLOAT);
            $ins->bindValue(':dat',      strval($r['datum']), SQLITE3_TEXT);
            $ins->bindValue(':kor',      strval($r['korisnik']), SQLITE3_TEXT);
            $ins->bindValue(':foto',     strval($r['foto_url']), SQLITE3_TEXT);
            $ins->bindValue(':ps',       strval($r['photo_source']), SQLITE3_TEXT);
            $ins->bindValue(':accuracy', $r['positional_accuracy']===null?null:floatval($r['positional_accuracy']), $r['positional_accuracy']===null?SQLITE3_NULL:SQLITE3_FLOAT);
            $ins->bindValue(':obscured', intval($r['coordinates_obscured']), SQLITE3_INTEGER);
            $ins->bindValue(':geoprivacy', strval($r['geoprivacy']), SQLITE3_TEXT);
            $ins->bindValue(':taxon_privacy', strval($r['taxon_geoprivacy']), SQLITE3_TEXT);
            $ins->bindValue(':orchid', intval($r['family_orchidaceae']), SQLITE3_INTEGER);
            $ins->bindValue(':url',      strval($r['url']), SQLITE3_TEXT);
            $ins->execute();
        }
    }

    meta_set($db, 'zadnje_sve', strval(time()));
    meta_set($db, 'api_version', API_VERSION);
    $db->exec('COMMIT');
}

function izvuci_foto(array $o, array $t): array {
    // 1) Прво стварна фотографија опсервације — то је оно што корисник очекује да види.
    if (!empty($o['photos']) && is_array($o['photos'])) {
        foreach ($o['photos'] as $p) {
            if (!empty($p['url'])) {
                return ['url' => veca_slika(strval($p['url'])), 'source' => 'observation'];
            }
        }
    }
    if (!empty($o['observation_photos']) && is_array($o['observation_photos'])) {
        foreach ($o['observation_photos'] as $op) {
            if (!empty($op['photo']['url'])) {
                return ['url' => veca_slika(strval($op['photo']['url'])), 'source' => 'observation'];
            }
        }
    }

    // 2) Тек ако опсервација нема фотографију, користи слику таксона као резерву.
    if (!empty($t['default_photo']['medium_url'])) {
        return ['url' => strval($t['default_photo']['medium_url']), 'source' => 'taxon'];
    }
    if (!empty($t['default_photo']['url'])) {
        return ['url' => veca_slika(strval($t['default_photo']['url'])), 'source' => 'taxon'];
    }
    return ['url' => '', 'source' => 'none'];
}

function veca_slika(string $url): string {
    $url = str_replace('/square.', '/medium.', $url);
    $url = str_replace('_square.', '_medium.', $url);
    $url = str_replace('square.jpg', 'medium.jpg', $url);
    $url = str_replace('square.jpeg', 'medium.jpeg', $url);
    $url = str_replace('square.png', 'medium.png', $url);
    return $url;
}

// Ray-casting: да ли тачка (lng,lat) пада унутар полигона.
function u_poligonu(float $x, float $y, array $poly): bool {
    $n = count($poly);
    if ($n < 3) return false;
    $u = false;
    $j = $n - 1;
    for ($i = 0; $i < $n; $i++) {
        $xi = floatval($poly[$i][0]);
        $yi = floatval($poly[$i][1]);
        $xj = floatval($poly[$j][0]);
        $yj = floatval($poly[$j][1]);
        if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1.0e-12) + $xi)) {
            $u = !$u;
        }
        $j = $i;
    }
    return $u;
}
