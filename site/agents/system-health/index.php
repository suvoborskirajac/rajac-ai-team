<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/auth.php';
rajac_sh_auth();

$s = rajac_sh_load_latest();
if (!$s) {
    $s = [
        'status'=>'nepoznato','status_label'=>'НЕПОЗНАТО','timestamp'=>null,
        'counts'=>['radi'=>0,'radi_podatak_kasni'=>0,'delimicno_radi'=>0,'greska'=>0,'nepoznato'=>0],
        'services'=>[],'alerts'=>[],
    ];
}
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function cls($status): string {
    return match($status) {
        'radi'=>'ok',
        'radi_podatak_kasni'=>'late',
        'delimicno_radi'=>'partial',
        'greska'=>'bad',
        default=>'unknown',
    };
}
?>
<!doctype html>
<html lang="sr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Агент надзора система — ПИО Рајац</title>
<style>
:root{--bg:#07150f;--panel:#0e2419;--line:#284938;--text:#eef8f1;--muted:#9ab3a4;--ok:#2fe184;--late:#e5b94f;--partial:#f08b4b;--bad:#ff6666}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif}
.wrap{max-width:1320px;margin:0 auto;padding:26px 18px 60px}
.top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:20px}.ey{letter-spacing:.18em;color:#8bd2a7;font-size:12px;font-weight:800}.top h1{margin:7px 0 6px;font-size:clamp(30px,4vw,52px)}.sub{color:var(--muted);max-width:850px;line-height:1.5}
.badge{padding:10px 14px;border-radius:999px;border:1px solid var(--line);font-weight:900;white-space:nowrap}
.badge.ok{color:var(--ok)}.badge.late{color:var(--late)}.badge.partial{color:var(--partial)}.badge.bad{color:var(--bad)}.badge.unknown{color:#ccc}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:18px 0}.card{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:16px}.card b{display:block;font-size:32px;margin-top:5px}.card span{color:var(--muted);font-size:12px;font-weight:800}
table{width:100%;border-collapse:collapse;background:var(--panel);border:1px solid var(--line);border-radius:16px;overflow:hidden}
th,td{padding:12px 10px;border-bottom:1px solid #1d3d2d;text-align:left;vertical-align:top}th{color:#b7d4c1;font-size:12px;text-transform:uppercase;letter-spacing:.05em}
.small{font-size:12px;color:var(--muted)}.status{font-weight:900}.status.ok{color:var(--ok)}.status.late{color:var(--late)}.status.partial{color:var(--partial)}.status.bad{color:var(--bad)}.status.unknown{color:#ccc}
.alerts{margin-top:18px}.alert{border:1px solid #6d4a30;background:#2a1c14;border-radius:14px;padding:12px 14px;margin:8px 0}.actions{display:flex;gap:10px;flex-wrap:wrap;margin:14px 0}.actions a{color:#07150f;background:#dff5e6;text-decoration:none;padding:10px 12px;border-radius:11px;font-weight:800}
@media(max-width:850px){.grid{grid-template-columns:1fr 1fr}.top{display:block}.badge{display:inline-block;margin-top:12px}table{font-size:13px}}
@media(max-width:520px){.grid{grid-template-columns:1fr}.wrap{padding:18px 10px}th:nth-child(4),td:nth-child(4){display:none}}
</style>
</head>
<body><main class="wrap">
<section class="top">
<div><div class="ey">RAJAC AI · СИСТЕМСКИ НАДЗОР</div><h1>Агент надзора система</h1>
<div class="sub">Одвојено прати доступност странице, одзив, свежину података и стање извора. Зелена страница сама по себи више није доказ да је податак свеж.</div></div>
<div class="badge <?=h(cls($s['status']??'nepoznato'))?>"><?=h($s['status_label']??'НЕПОЗНАТО')?></div>
</section>

<div class="actions">
<a href="api.php">JSON снимак</a>
<a href="api.php?run=1">Покрени проверу сада</a>
<a href="?odjava=1">Одјава</a>
</div>

<section class="grid">
<div class="card"><span>РАДИ</span><b><?=h($s['counts']['radi']??0)?></b></div>
<div class="card"><span>ПОДАТАК КАСНИ</span><b><?=h($s['counts']['radi_podatak_kasni']??0)?></b></div>
<div class="card"><span>ДЕЛИМИЧНО РАДИ</span><b><?=h($s['counts']['delimicno_radi']??0)?></b></div>
<div class="card"><span>ГРЕШКА</span><b><?=h($s['counts']['greska']??0)?></b></div>
</section>

<p class="small">Последња провера: <?=h($s['timestamp']??'још није покренуто')?> · Верзија <?=h($s['version']??RAJAC_SH_VERSION)?></p>

<div style="overflow:auto">
<table>
<thead><tr><th>Сервис / агент</th><th>Статус</th><th>HTTP</th><th>Одзив</th><th>Свежина</th><th>Напомена</th></tr></thead>
<tbody>
<?php foreach(($s['services']??[]) as $r): ?>
<tr>
<td><strong><?=h($r['name']??'')?></strong><div class="small"><?=h($r['id']??'')?></div></td>
<td><span class="status <?=h(cls($r['status']??'nepoznato'))?>"><?=h($r['status_label']??'НЕПОЗНАТО')?></span></td>
<td><?=h($r['http_code']??'—')?></td>
<td><?=h(isset($r['response_ms']) ? $r['response_ms'].' ms' : '—')?></td>
<td><?php
if(isset($r['data_age_s']) && $r['data_age_s']!==null){
  $d=(int)floor($r['data_age_s']/86400); $hh=(int)floor(($r['data_age_s']%86400)/3600);
  echo h($d.' д '.$hh.' ч');
}else echo '—';
?></td>
<td class="small"><?=h($r['reason']??'')?></td>
</tr>
<?php endforeach; ?>
<?php if(empty($s['services'])): ?><tr><td colspan="6">Још нема снимка. Покрените проверу сада или cron.php.</td></tr><?php endif; ?>
</tbody>
</table>
</div>

<?php if(!empty($s['alerts'])): ?>
<section class="alerts"><h2>Сигнали за проверу</h2>
<?php foreach($s['alerts'] as $a): ?>
<div class="alert"><strong><?=h($a['service_name']??'')?></strong> — <?=h($a['message']??'')?></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
</main></body></html>
