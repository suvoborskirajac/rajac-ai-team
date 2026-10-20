<?php
declare(strict_types=1);
$config=require __DIR__.'/src/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?><!doctype html>
<html lang="sr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($config['app_name'],ENT_QUOTES,'UTF-8')?></title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <div><h1>Биодиверзитет ПИО „Рајац“</h1><p>Biologer налази — просторна, временска и таксономска анализа</p></div>
  <div><div id="syncStatus" class="sync">Учитавање стања…</div></div>
</header>

<main class="layout">
<aside class="panel">
  <section class="cards">
    <article><b id="recordsCount">0</b><span>налаза</span></article>
    <article><b id="speciesCount">0</b><span>врста</span></article>
    <article><b id="latestDate">—</b><span>последњи датум</span></article>
  </section>

  <section class="filters">
    <h2>Филтери</h2>
    <label>Претрага<input id="q" type="search" placeholder="Врста, народни назив, посматрач"></label>
    <label>Група<select id="group"><option value="">Све групе</option></select></label>
    <label>Врста<select id="species"><option value="">Све врсте</option></select></label>
    <div class="grid2">
      <label>Година<select id="year"><option value="">Све</option></select></label>
      <label>Месец<select id="month"><option value="">Сви</option></select></label>
    </div>
    <label>Режим заштите<select id="zone"><option value="">Све зоне</option><option value="1">I степен</option><option value="2">II степен</option><option value="3">III степен</option></select></label>
    <label class="check"><input id="uncertain" type="checkbox"> Прикажи и непрецизне/осетљиве налазе</label>
    <label>Начин приказа<select id="mode"><option value="clusters">Груписане тачке</option><option value="points">Појединачне тачке</option><option value="heat">Топлотна карта</option></select></label>
    <p class="hint" id="heatHint">За научно исправну топлотну карту изаберите једну врсту.</p>
    <div class="actions"><button id="apply">Примени</button><button id="reset" class="secondary">Поништи</button></div>
  </section>

  <section>
    <h2>Легенда</h2>
    <div id="legend" class="legend"></div>
  </section>
</aside>

<section class="content">
  <div id="map"></div>
  <div class="analysis">
    <article class="chartbox"><h2>Фенологија — налази по месецима</h2><canvas id="monthlyChart"></canvas></article>
    <article class="chartbox"><h2>Најчешће забележене врсте</h2><canvas id="topChart"></canvas></article>
  </div>
  <section class="tablebox">
    <div class="tablehead"><h2>Приказани налази</h2><button id="downloadGeojson" class="secondary">Преузми GeoJSON</button></div>
    <div class="tablewrap"><table><thead><tr><th>Врста</th><th>Група</th><th>Датум</th><th>Зона</th><th>Прецизност</th></tr></thead><tbody id="obsTable"></tbody></table></div>
  </section>
</section>
</main>

<script>window.APP_CONFIG={zonesUrl:"data/pio_rajac_zones.geojson",defaultBasemap:<?=json_encode($config['default_basemap'])?>};</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="assets/app.js?v=20261020"></script>
</body></html>
