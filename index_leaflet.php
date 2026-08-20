<?php

require_once("defines.php");
require_once("pomm_conf.php");
require_once("func.php");
require_once("map_english.php");

?>
<!DOCTYPE html>
<HTML><HEAD><title>Online Playermap</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Leaflet + Leaflet.markercluster, via CDN. No self-hosting needed --
     this project is small enough that pulling in a CDN dependency is a
     reasonable tradeoff for not maintaining vendored copies. -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.Default.css" />

<style type="text/css">
body {
    margin: 0;
    padding: 0;
    color: #EABA28;
    background-color: #000000;
    font-family: verdana, arial, sans-serif, helvetica;
}

/* ---- Leaflet map area ----
   2026-08-19 rewrite: replaces the old #map-wrapper / CSS-zoom / manual
   pixel-positioned <img> approach entirely. Leaflet owns its own internal
   pan/zoom state within this fixed-size container -- the container's CSS
   size never changes regardless of map zoom level, which eliminates the
   whole "reposition everything below the map based on current zoom"
   problem the old version had (MAP_LAYER_HEIGHT, repositionBelowMap(),
   etc. are gone -- nothing below the map needs to know what zoom level
   it's at anymore). */
#leaflet-map {
    width: 100%;
    height: 78vh;
    background: #000000;
    z-index: 1;
}

#top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 16px;
    background: #111111;
    border-bottom: 2px solid #EABA28;
}

#top-bar h1 {
    font-family: Georgia, "Times New Roman", Times, serif;
    font-style: italic;
    font-size: 20px;
    color: #FFFF99;
    margin: 0;
}

#continent-switcher button {
    background-color: rgba(0, 0, 0, 0.7);
    color: #EABA28;
    border: 1px solid #EABA28;
    border-radius: 4px;
    padding: 6px 12px;
    margin-left: 6px;
    font-size: 13px;
    cursor: pointer;
    font-family: verdana, arial, sans-serif, helvetica;
}
#continent-switcher button.active {
    background-color: #EABA28;
    color: #000000;
    font-weight: bold;
}
#continent-switcher button:hover:not(.active) {
    background-color: rgba(234, 186, 40, 0.2);
}

#serverstatus-bar {
    text-align: center;
    padding: 8px;
    font-family: Georgia, "Times New Roman", Times, serif;
    font-size: 16px;
    font-style: italic;
    color: #FFFF99;
    background: #0a0a0a;
    border-bottom: 1px solid #333;
}
#serverstatus-bar .statustext {
    font-family: verdana, arial, sans-serif, helvetica;
    font-size: 12px;
    font-style: normal;
    color: #EABA28;
}

/* Temporary calibration debug readout, added 2026-08-20. Click anywhere
   on the map to see that point's native-image pixel coordinates (i.e.
   the same space wowToPixel() outputs into) -- used to get precise,
   reliable ground-truth pixel positions for known landmarks directly from
   the live Leaflet render, instead of an ambiguous screenshot. Left in
   place after the EK fix below in case Kalimdor/Outland/Northrend ever
   need the same treatment -- remove entirely (this CSS block + the HTML
   div + the map click handler in initMap()) once calibration work is
   fully done across all continents. */
#calib-readout {
    position: fixed;
    top: 60px;
    right: 12px;
    z-index: 500;
    background: rgba(0,0,0,0.85);
    border: 1px solid #EABA28;
    border-radius: 4px;
    padding: 8px 12px;
    font-size: 12px;
    font-family: verdana, arial, sans-serif, helvetica;
    color: #EABA28;
    display: none;
    max-width: 260px;
}
#calib-readout strong { color: #FFFF99; }

/* ---- Player table (unchanged design from the previous version, just no
   longer absolutely positioned relative to a map that could be any
   height -- it's normal document flow below the map now) ---- */
#player-table-wrapper {
    max-width: 900px;
    margin: 16px auto;
    padding: 0 16px;
}
#player-table-wrapper h3 {
    font-family: Georgia, "Times New Roman", Times, serif;
    color: #FFFF99;
    font-size: 16px;
    margin: 0 0 6px 0;
    text-align: center;
}
/* Search box + pagination, added 2026-08-20 alongside the performance
   fix below -- rendering all ~5000 rows in one unpaginated table on every
   poll was the actual cause of the slowness reported; paginating and only
   building DOM for the current page's rows fixes both the performance
   complaint and gives a natural place to add search. */
#player-search {
    display: block;
    width: 100%;
    box-sizing: border-box;
    margin-bottom: 8px;
    padding: 7px 10px;
    background: rgba(0,0,0,0.6);
    border: 1px solid #EABA28;
    border-radius: 4px;
    color: #EABA28;
    font-size: 13px;
    font-family: verdana, arial, sans-serif, helvetica;
}
#player-search::placeholder { color: #7a6218; }
#player-table a.char-link {
    color: inherit;
    text-decoration: none;
    border-bottom: 1px dotted currentColor;
}
#player-table a.char-link:hover { color: #FFFF99; }
#player-table-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 10px;
    font-size: 12px;
    color: #cccccc;
}
#player-table-pagination button {
    background: rgba(0,0,0,0.6);
    color: #EABA28;
    border: 1px solid #EABA28;
    border-radius: 4px;
    padding: 4px 10px;
    cursor: pointer;
    font-size: 12px;
    font-family: verdana, arial, sans-serif, helvetica;
}
#player-table-pagination button:disabled { opacity: 0.35; cursor: default; }
#player-table-pagination button:not(:disabled):hover { background: rgba(234, 186, 40, 0.2); }
#player-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    background: rgba(0,0,0,0.6);
}
#player-table th {
    background: #2a2a2a;
    color: #EABA28;
    text-align: left;
    padding: 6px 8px;
    cursor: pointer;
    border-bottom: 2px solid #EABA28;
    user-select: none;
}
#player-table th:hover { background: #3a3a3a; }
#player-table th .sort-arrow { color: #FFFF99; margin-left: 4px; }
#player-table td { padding: 5px 8px; color: #dddddd; border-bottom: 1px solid #333333; }
#player-table tr:hover td { background: rgba(234, 186, 40, 0.08); }
#player-table td.faction-alliance { color: #4FB3D9; }
#player-table td.faction-horde { color: #E05A45; }
#player-table-empty {
    text-align: center;
    color: #999999;
    font-style: italic;
    padding: 12px;
    font-size: 12px;
}

/* Leaflet's own popup styling, restyled to match the site's dark theme
   instead of Leaflet's default white popup box */
.leaflet-popup-content-wrapper {
    background: #000000;
    color: #ffffff;
    border: 1px solid #EABA28;
    border-radius: 4px;
}
.leaflet-popup-tip { background: #000000; border: 1px solid #EABA28; }
.leaflet-popup-content { margin: 8px 10px; font-size: 12px; }
.popup-header {
    background: #bb0000;
    font-weight: bold;
    color: #ffffff;
    padding: 3px 6px;
    margin: -8px -10px 6px -10px;
    border-radius: 3px 3px 0 0;
}
.popup-row { display: flex; align-items: center; gap: 4px; padding: 1px 0; }
</style>
</HEAD>
<script TYPE="text/javascript" src="libs/js/JsHttpRequest/Js.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/leaflet.markercluster.min.js"></script>
<script TYPE="text/javascript">

var time = <?php echo $time ?>;
var show_time = <?php echo $show_time ?>;
var show_status = <?php echo $show_status ?>;
var maps_count = <?php echo count($lang_defs['maps_names']); ?>;
var maps_array = new Array(<?php echo $maps_for_points ?>);
var maps_name_array = new Array(<?php echo "'".implode("','", $lang_defs['maps_names'])."'" ?>);
var IMG_BASE = "<?php echo $img_base ?>";
var IMG_BASE2 = "<?php echo $img_base2 ?>";

// Armory linking, added 2026-08-20. Realm slug is matched
// case-insensitively by the Armory app's router (/character/:realm/:name)
// -- "hollowpeak" assumed to match the realm name configured in the
// Armory's own config.json on the droplet, which isn't visible from this
// repo (not committed, deployment-specific). Worth a quick check if links
// 404 -- may need adjusting to match the actual configured realm name.
var ARMORY_BASE = "https://armory.tylerconlee.com";
var ARMORY_REALM = "hollowpeak";

var race_name = {<?php echo "0:''"; foreach ($character_race as $id => $race) {
    echo(", ".$id.":'".$race."'");
} ?>}

var class_name = {<?php echo "0:''"; foreach ($character_class as $id => $class) {
    echo(", ".$id.":'".$class."'");
} ?>}

// Fixed placeholder positions for bots inside instances/dungeons (they
// have no real open-world x/y while inside) -- unchanged from the
// previous version, just re-keyed by continent name instead of a numeric
// "Extention" index, since that's what the new per-continent structure
// below uses.
var instances_x = {
  azeroth: { 2:0,13:0,17:0,30:762,33:712,34:732,35:732,36:712,37:0,43:245,44:0,47:238,48:172,70:833,90:738,109:849,129:254,150:0,169:0,189:773,209:269,229:782,230:778,249:290,269:315,289:816,309:782,329:834,349:123,369:745,389:308,409:783,429:164,449:741,450:305,451:0,469:778,489:244,509:160,529:820,531:144,532:798,534:317,560:320,568:897,572:750,580:868,585:883,595:322,618:313 },
  outland: { 540:593,542:586,543:593,544:588,545:393,546:399,547:388,548:399,550:683,552:680,553:672,554:669,555:495,556:506,557:495,558:483,559:408,562:443,564:740,565:485 },
  northrend: { 533:568,574:749,575:751,576:161,578:159,599:553,600:605,601:395,602:575,603:559,604:740,608:470,615:491,616:155,617:457,619:400,624:363,631:400,632:415,649:475,650:465,658:393,668:410,724:491 }
};
var instances_y = {
  azeroth: { 2:0,13:0,17:0,30:278,33:295,34:511,35:503,36:567,37:0,43:419,44:0,47:508,48:291,70:443,90:419,109:551,129:516,150:0,169:0,189:216,209:568,229:481,230:484,249:514,269:601,289:258,309:589,329:203,349:432,369:497,389:352,409:484,429:496,449:508,450:352,451:0,469:480,489:364,509:607,529:321,531:603,532:569,534:596,560:606,568:172,572:245,580:26,585:16,595:601,618:348 },
  outland: { 540:399,542:398,543:405,544:402,545:355,546:350,547:353,548:357,550:226,552:215,553:210,554:239,555:569,556:557,557:545,558:557,559:489,562:239,564:567,565:204 },
  northrend: { 533:456,574:577,575:583,576:443,578:451,599:195,600:406,601:462,602:180,603:169,604:292,608:360,615:465,616:447,617:352,619:462,624:369,631:350,632:350,649:207,650:207,658:362,668:365,724:455 }
};

var fade_colors = Array('C6B711','BDAF10','B7A910','B1A40F','AB9E0F','A4980E','9E920E','988C0D','92870D','8B800C','857B0B','7F750B','79700A','746B0A','6E6609','686009','625B08','5C5508','564F07','504A07','4A4406','443F05','3E3905','383404','312D04','2A2703','232002','1C1A02','141201','000000');
var fade_cur_color = fade_colors.length-1;
var status_text = Array('OffLine','DB connect error','uptime','max online','GM online');
var status_data = Array(1,0,0,0);
var status_process = Array();
var status_cur_time = 0;
var status_next_process = 0;
var statusUpdateInterval = 50;
var status_process_started;

// ============================================================
// Per-continent map configuration (2026-08-19 rewrite)
//
// Each entry: source image + native pixel dimensions + the WoW-coordinate
// formula for that continent. wowToPixel(x, y) returns {x, y} in the
// image's own NATIVE pixel space (origin top-left, Y increasing downward
// -- normal image convention). toLatLng() below handles the Y-flip
// Leaflet's CRS.Simple needs.
//
// EK RECALIBRATED 2026-08-20. Live screenshots showed a genuine,
// consistent misalignment for Eastern Kingdoms specifically -- clusters
// near Stormwind plotted into open ocean west of the actual coastline.
// Ruled out an image-dimension mismatch first (confirmed served
// azeroth.jpg is genuinely 2600x2400 via `docker exec ... file`). Root
// cause was the EK formula itself -- the same calibration inaccuracy
// flagged as unresolved back when the old map was originally hidden (a
// prior recalibration pass was only ever validated against a static
// processed image, never successfully cross-checked against a live
// render). Fixed properly this time using the click-to-read-pixel-
// coordinates debug tool (#calib-readout, click handler in initMap()):
// Tyler clicked precisely on Stormwind and Ironforge on the live Leaflet
// map, giving exact, deterministic pixel targets (1772,1673) and
// (1909,1324) respectively -- no screenshot-scale ambiguity this time.
// Paired with the same real WoW .gps ground truth used previously
// (Stormwind: -8833.38,628.628 / Kharanos~=Ironforge: -5450.2036,
// -529.36316) and re-solved the exact 2-point linear system. Verified:
// both points reproduce their exact target pixels under the new formula.
// Kalimdor's formula is UNCHANGED -- no screenshot evidence of a problem
// there, only EK (Stormwind/Dun Morogh) was flagged.
// ============================================================
var CONTINENTS = {
  azeroth: {
    label: "Azeroth",
    image: IMG_BASE + "azeroth.jpg",
    width: 2600,
    height: 2400,
    // map ids 0 (Eastern Kingdoms) and 1 (Kalimdor) share this one image
    mapIds: [0, 1],
    wowToPixel: function(x, y, m) {
      if (m == 1) {
        // Kalimdor -- unchanged, not flagged as misaligned
        return { x: 392.1471 - y * 0.156309, y: 1120.7884 - x * 0.100326 };
      }
      // Eastern Kingdoms (m == 0, and default fallback) -- recalibrated
      // 2026-08-20, see comment block above CONTINENTS for the full
      // derivation.
      return { x: 1846.3719 - y * 0.118308, y: 761.7707 - x * 0.103157 };
    }
  },
  outland: {
    label: "Outland",
    image: IMG_BASE + "outland.jpg",
    width: 966,
    height: 732,
    mapIds: [530],
    wowToPixel: function(x, y, m) {
      // Outland (map 530) covers three sub-regions with different offsets
      // depending on which part of the zone a character is in --
      // unchanged from the original.
      var where530 = 0;
      if (y < -1000 && y > -10000 && x > 5000) { // Blood Elf starting area
        x = x - 10349; y = y + 6357; where530 = 1;
      } else if (y < -7000 && x < 0) { // Draenei starting area
        x = x + 3961; y = y + 13931; where530 = 2;
      } else { // rest of Outland
        x = x - 3070; y = y - 1265; where530 = 3;
      }
      var xpos = Math.round(x * 0.051446);
      var ypos = Math.round(y * 0.051446);
      if (where530 == 1) return { x: 858 - ypos, y: 84 - xpos };
      if (where530 == 2) return { x: 103 - ypos, y: 261 - xpos };
      return { x: 684 - ypos, y: 229 - xpos };
    }
  },
  northrend: {
    label: "Northrend",
    image: IMG_BASE + "northrend.jpg",
    width: 966,
    height: 732,
    // 609 = Ebon Hold (Death Knight starting area) -- geographically part
    // of Northrend, plotted on the same image. The previous version's
    // switch-statement had a latent bug here: case 609 referenced xpos/
    // ypos without a matching branch ever computing them for m==609,
    // meaning Ebon Hold positions were silently using stale/undefined
    // values. Fixed here by explicitly giving 609 the same scale Northrend
    // itself uses, rather than carrying the bug forward.
    mapIds: [571, 609],
    wowToPixel: function(x, y, m) {
      if (m == 609) {
        x = x - 2355; y = y + 5662;
      }
      var xpos = Math.round(x * 0.050085);
      var ypos = Math.round(y * 0.050085);
      return { x: 505 - ypos, y: 642 - xpos };
    }
  }
};

// Reverse lookup: AC map id -> continent key
var MAP_ID_TO_CONTINENT = {};
Object.keys(CONTINENTS).forEach(function(key) {
  CONTINENTS[key].mapIds.forEach(function(id) {
    MAP_ID_TO_CONTINENT[id] = key;
  });
});

var map; // Leaflet map instance
var currentContinentKey = "azeroth";
var currentImageOverlay = null;
var currentClusterGroup = null;
var last_online_data = null;

function toLatLng(pixelX, pixelY, continentKey) {
  // Leaflet's L.CRS.Simple treats [0,0] as bottom-left with Y increasing
  // upward -- the opposite of normal image pixel coordinates (origin
  // top-left, Y increasing downward). Flipping here is the one piece of
  // bookkeeping CRS.Simple requires; everything else (pan, zoom, bounds)
  // Leaflet handles natively rather than needing hand-rolled math.
  var h = CONTINENTS[continentKey].height;
  return [h - pixelY, pixelX];
}

// Inverse of toLatLng -- given a Leaflet lat/lng, return the native image
// pixel coordinates it corresponds to. Used only by the calibration
// debug click handler below.
function latLngToPixel(lat, lng, continentKey) {
  var h = CONTINENTS[continentKey].height;
  return { x: lng, y: h - lat };
}

function initMap() {
  map = L.map('leaflet-map', {
    crs: L.CRS.Simple,
    minZoom: -2,
    maxZoom: 3,
    zoomControl: true,
    attributionControl: false
  });
  switchContinent('azeroth');

  // Calibration debug tool -- click anywhere on the map to see that
  // point's native pixel coordinates (same space wowToPixel() outputs),
  // shown in the #calib-readout panel. Left in place after the EK fix in
  // case Kalimdor/Outland/Northrend need the same treatment later.
  map.on('click', function(e) {
    var px = latLngToPixel(e.latlng.lat, e.latlng.lng, currentContinentKey);
    var panel = document.getElementById('calib-readout');
    panel.style.display = 'block';
    panel.innerHTML =
      '<strong>Calibration readout</strong><br>' +
      'Continent: ' + CONTINENTS[currentContinentKey].label + '<br>' +
      'Native pixel X: ' + Math.round(px.x) + '<br>' +
      'Native pixel Y: ' + Math.round(px.y) + '<br>' +
      '<span style="opacity:0.7;">(click elsewhere to update)</span>';
  });
}

function switchContinent(key) {
  var conf = CONTINENTS[key];
  if (!conf) return;
  currentContinentKey = key;

  if (currentImageOverlay) { map.removeLayer(currentImageOverlay); }
  if (currentClusterGroup) { map.removeLayer(currentClusterGroup); }

  var bounds = [[0, 0], [conf.height, conf.width]];
  currentImageOverlay = L.imageOverlay(conf.image, bounds).addTo(map);
  map.setMaxBounds(bounds);
  map.fitBounds(bounds);

  currentClusterGroup = L.markerClusterGroup({
    maxClusterRadius: 45,
    spiderfyOnMaxZoom: true,
    showCoverageOnHover: false
  });
  map.addLayer(currentClusterGroup);

  // Update the continent-switch button styling
  document.querySelectorAll('#continent-switcher button').forEach(function(btn) {
    btn.classList.toggle('active', btn.dataset.continent === key);
  });

  // Re-render whatever data we last received onto the newly-switched map
  if (last_online_data) {
    renderMapPoints(last_online_data);
  }
}

function factionIcon(isHorde) {
  return L.icon({
    iconUrl: IMG_BASE + (isHorde ? "horde.gif" : "allia.gif"),
    iconSize: [16, 16],
    iconAnchor: [8, 8]
  });
}

function instanceIcon() {
  return L.icon({
    iconUrl: IMG_BASE + "inst-icon.gif",
    iconSize: [20, 20],
    iconAnchor: [10, 10]
  });
}

function buildPopupHtml(entries) {
  // entries: array of {name, level, className, raceName, zone, isDead}
  var zone = entries[0].zone;
  var html = '<div class="popup-header">' + zone + '</div>';
  entries.forEach(function(e) {
    var charImg = e.isDead
      ? '<img src="' + IMG_BASE + 'dead.gif" width="16" height="16">'
      : '<img src="' + IMG_BASE2 + e.raceId + '-' + e.gender + '.gif" width="16" height="16">';
    html += '<div class="popup-row">' + charImg +
      '<span>' + e.name + ' (Lvl ' + e.level + ' ' + e.raceName + ' ' + e.className + ')</span></div>';
  });
  return html;
}

function renderMapPoints(data)
{
  currentClusterGroup.clearLayers();

  var i = maps_count;
  // group entries that land in the same instance icon slot so they share
  // one popup, same spirit as the old "combine nearby points" behavior --
  // but for open-world positions, clustering is now handled entirely by
  // Leaflet.markercluster instead of hand-rolled distance math.
  var instanceGroups = {}; // key: continent+dungeonMapId -> [entries]

  while (i < data.length)
  {
    var d = data[i];
    var isHorde = (d.race==2 || d.race==5 || d.race==6 || d.race==8 || d.race==10);
    var entry = {
      name: d.name, level: d.level, className: class_name[d.cl],
      raceName: race_name[d.race], raceId: d.race, gender: d.gender,
      zone: d.zone, isDead: (d.dead == 1)
    };

    if (in_array(d.map, maps_array)) {
      // Open-world position: real x/y on one of the three continent images
      var continentKey = MAP_ID_TO_CONTINENT[d.map];
      if (!continentKey) { i++; continue; }
      if (continentKey !== currentContinentKey) { i++; continue; } // only plot what's on the currently-viewed continent

      var conf = CONTINENTS[continentKey];
      var pixel = conf.wowToPixel(d.x, d.y, d.map);
      var latlng = toLatLng(pixel.x, pixel.y, continentKey);

      var marker = L.marker(latlng, { icon: factionIcon(isHorde) });
      marker.bindPopup(buildPopupHtml([entry]));
      currentClusterGroup.addLayer(marker);
    } else {
      // Inside an instance/dungeon/battleground -- no real position,
      // plotted at a fixed representative spot for that instance, on
      // whichever continent it's associated with.
      var extKey = ['azeroth', 'outland', 'northrend'][d.Extention] || 'azeroth';
      if (extKey !== currentContinentKey) { i++; continue; }
      var groupKey = extKey + ':' + d.map;
      if (!instanceGroups[groupKey]) { instanceGroups[groupKey] = { mapId: d.map, extKey: extKey, entries: [] }; }
      instanceGroups[groupKey].entries.push(entry);
    }
    i++;
  }

  Object.keys(instanceGroups).forEach(function(key) {
    var g = instanceGroups[key];
    var px = instances_x[g.extKey][g.mapId];
    var py = instances_y[g.extKey][g.mapId];
    if (px === undefined || py === undefined) return;
    var latlng = toLatLng(px, py, g.extKey);
    var marker = L.marker(latlng, { icon: instanceIcon() });
    marker.bindPopup(buildPopupHtml(g.entries));
    currentClusterGroup.addLayer(marker);
  });
}

function in_array(value, arr)
{
  for (var i = 0; i < arr.length; i++) {
    if (value == arr[i]) return true;
  }
  return false;
}

// ---- Sortable, searchable, paginated player table ----
// Rewritten 2026-08-20: the previous version built one giant HTML string
// for every online character (up to ~5000 with the current bot count) and
// replaced the whole tbody with it on every single poll cycle -- genuinely
// heavy DOM work, and the reported cause of the page feeling slow.
// currentPlayerList still holds the FULL unfiltered/unpaginated dataset
// (needed so sorting/searching/pagination all operate on the same source
// of truth); only the current page's rows actually get built into DOM.
var tableSortColumn = "name";
var tableSortDirection = 1;
var currentPlayerList = [];
var searchTerm = "";
var currentPage = 1;
var ROWS_PER_PAGE = 50;

var TABLE_COLUMNS = [
  { key: "name",    label: "Name" },
  { key: "level",   label: "Level" },
  { key: "class",   label: "Class" },
  { key: "race",    label: "Race" },
  { key: "zone",    label: "Zone" },
  { key: "faction", label: "Faction" }
];

function sortTableBy(column)
{
  if (tableSortColumn === column) {
    tableSortDirection = -tableSortDirection;
  } else {
    tableSortColumn = column;
    tableSortDirection = 1;
  }
  currentPage = 1; // changing sort order resets to page 1, same as a new search would
  renderPlayerTableRows();
}

function updateSearch(value)
{
  searchTerm = value.trim().toLowerCase();
  currentPage = 1;
  renderPlayerTableRows();
}

function goToPage(delta)
{
  currentPage += delta;
  renderPlayerTableRows();
}

function buildPlayerTableHeader()
{
  var thead = document.getElementById("player-table-head");
  var html = "<tr>";
  for (var i = 0; i < TABLE_COLUMNS.length; i++) {
    var col = TABLE_COLUMNS[i];
    var arrow = "";
    if (tableSortColumn === col.key) {
      arrow = '<span class="sort-arrow">' + (tableSortDirection === 1 ? "\u25B2" : "\u25BC") + '</span>';
    }
    html += '<th onclick="sortTableBy(\'' + col.key + '\');">' + col.label + arrow + '</th>';
  }
  html += "</tr>";
  thead.innerHTML = html;
}

function armoryLink(name) {
  return ARMORY_BASE + "/character/" + encodeURIComponent(ARMORY_REALM) + "/" + encodeURIComponent(name);
}

function renderPlayerTableRows()
{
  buildPlayerTableHeader();
  var tbody = document.getElementById("player-table-body");
  var emptyMsg = document.getElementById("player-table-empty");
  var table = document.getElementById("player-table");
  var pagination = document.getElementById("player-table-pagination");

  var filtered = searchTerm
    ? currentPlayerList.filter(function(p) { return p.name.toLowerCase().indexOf(searchTerm) !== -1; })
    : currentPlayerList;

  if (!filtered.length) {
    table.style.display = "none";
    pagination.style.display = "none";
    emptyMsg.style.display = "block";
    emptyMsg.textContent = searchTerm ? "No online players match \"" + searchTerm + "\"." : "No players currently online.";
    return;
  }
  table.style.display = "table";
  emptyMsg.style.display = "none";

  var sorted = filtered.slice().sort(function(a, b) {
    var av = a[tableSortColumn], bv = b[tableSortColumn];
    if (typeof av === "string") { av = av.toLowerCase(); bv = bv.toLowerCase(); }
    if (av < bv) return -1 * tableSortDirection;
    if (av > bv) return 1 * tableSortDirection;
    return 0;
  });

  var totalPages = Math.max(1, Math.ceil(sorted.length / ROWS_PER_PAGE));
  if (currentPage > totalPages) currentPage = totalPages;
  if (currentPage < 1) currentPage = 1;

  var startIdx = (currentPage - 1) * ROWS_PER_PAGE;
  var pageRows = sorted.slice(startIdx, startIdx + ROWS_PER_PAGE);

  var html = "";
  for (var i = 0; i < pageRows.length; i++) {
    var p = pageRows[i];
    var factionClass = p.faction === "Horde" ? "faction-horde" : "faction-alliance";
    html += "<tr><td><a class=\"char-link\" href=\"" + armoryLink(p.name) + "\" target=\"_blank\" rel=\"noopener\">" + p.name + "</a></td><td>" + p.level + "</td><td>" + p.className +
      "</td><td>" + p.raceName + "</td><td>" + p.zone + "</td><td class=\"" + factionClass + "\">" + p.faction + "</td></tr>";
  }
  tbody.innerHTML = html;

  pagination.style.display = "flex";
  pagination.innerHTML =
    '<button onclick="goToPage(-1);" ' + (currentPage <= 1 ? "disabled" : "") + '>&laquo; Prev</button>' +
    '<span>Page ' + currentPage + ' of ' + totalPages + ' (' + sorted.length + (searchTerm ? " matching" : " online") + ')</span>' +
    '<button onclick="goToPage(1);" ' + (currentPage >= totalPages ? "disabled" : "") + '>Next &raquo;</button>';
}

function renderPlayerTable(data)
{
  currentPlayerList = [];
  if (!data) { renderPlayerTableRows(); return; }

  var i = maps_count;
  while (i < data.length) {
    var isHorde = (data[i].race==2 || data[i].race==5 || data[i].race==6 || data[i].race==8 || data[i].race==10);
    currentPlayerList.push({
      name: data[i].name,
      level: parseInt(data[i].level, 10),
      className: class_name[data[i].cl],
      raceName: race_name[data[i].race],
      zone: data[i].zone,
      faction: isHorde ? "Horde" : "Alliance"
    });
    i++;
  }
  renderPlayerTableRows();
}

// ---- Server status text (unchanged) ----
function statusController(status_process_id, diff)
{
  var action = status_process[status_process_id].action;
  if (action) {
    var obj = document.getElementById("status");
    var text_type = status_process[status_process_id].text_type;
    if (text_type == 0) {
      var status_process_now = new Date();
      var status_process_diff = status_process_now.getTime() - status_process_started.getTime();
      var objDate = new Date(status_data[status_process[status_process_id].status_data]*1000 + status_process_diff);
      var days = parseInt(status_data[status_process[status_process_id].status_data]/86400);
      var hours = objDate.getUTCHours(), min = objDate.getUTCMinutes(), sec = objDate.getUTCSeconds();
      if (hours < 10) hours = '0'+hours;
      if (min < 10) min = '0'+min;
      if (sec < 10) sec = '0'+sec;
      if (days) days = days+' '; else days = '';
      obj.innerHTML = status_text[status_process[status_process_id].text_id]+' - '+days+''+hours+':'+min+':'+sec;
    } else if (text_type == 1) {
      obj.innerHTML = status_text[status_process[status_process_id].text_id]+' - '+status_data[status_process[status_process_id].status_data];
    } else {
      obj.innerHTML = status_text[status_process[status_process_id].text_id];
    }
    if (action == 1 && fade_cur_color > 0) { fade_cur_color--; obj.style.color = '#'+fade_colors[fade_cur_color]; }
    else if (action == 2 && fade_cur_color < (fade_colors.length-1)) { fade_cur_color++; obj.style.color = '#'+fade_colors[fade_cur_color]; }
  }
  status_cur_time += diff;
  if (status_next_process || status_cur_time >= status_process[status_process_id].time) {
    status_cur_time = status_next_process ? statusUpdateInterval*fade_colors.length : 0;
    do {
      status_process_id++;
      if (status_process_id >= status_process.length) status_process_id = 0;
    } while (status_next_process && status_process[status_process_id].action == 2);
    status_next_process = 0;
  }
  setTimeout('statusController('+status_process_id+','+statusUpdateInterval+')', statusUpdateInterval);
}

function statusInit()
{
  var blinkTime = statusUpdateInterval*fade_colors.length;
  var time_to_show_uptime = <?php echo $time_to_show_uptime ?>;
  var time_to_show_maxonline = <?php echo $time_to_show_maxonline ?>;

  if (status_process.length == 0) setTimeout('statusController(0,'+statusUpdateInterval+')', statusUpdateInterval);

  status_process = [];
  if (status_data[0] == 1) {
    if (time_to_show_uptime) {
      status_process.push({text_id:2, status_data:1, text_type:0, action:1, time:time_to_show_uptime});
      status_process.push({text_id:2, status_data:1, text_type:0, action:2, time:blinkTime});
    }
    if (time_to_show_maxonline) {
      status_process.push({text_id:3, status_data:2, text_type:1, action:1, time:time_to_show_maxonline});
      status_process.push({text_id:3, status_data:2, text_type:1, action:2, time:blinkTime});
    }
  } else if (status_data[0] == 0) {
    status_process.push({text_id:0, status_data:0, text_type:2, action:1, time:blinkTime});
    status_process.push({text_id:0, status_data:0, text_type:2, action:2, time:blinkTime});
  } else {
    status_process.push({text_id:1, status_data:0, text_type:2, action:1, time:blinkTime});
    status_process.push({text_id:1, status_data:0, text_type:2, action:2, time:blinkTime});
  }
}

function load_data()
{
  var req = new Subsys_JsHttpRequest_Js();
  req.onreadystatechange = function()
  {
    if (req.readyState == 4)
    {
      if (show_status && req.responseJS.status) {
        if (status_data[0] != req.responseJS.status.online) {
          status_data[0] = req.responseJS.status.online;
        }
        if (req.responseJS.status.uptime < status_data[1] || status_data[1] == 0) {
          status_process_started = new Date();
          status_data[1] = req.responseJS.status.uptime;
        }
        status_data[2] = req.responseJS.status.maxplayers;
        status_data[3] = req.responseJS.status.gmonline;
        statusInit();
      }
      last_online_data = req.responseJS.online;
      renderPlayerTable(last_online_data);
      renderMapPoints(last_online_data);
    }
  }
  req.open('GET', 'pomm_play.php', true);
  req.send({ });
}

function reset() { load_data(); }

function start()
{
  initMap();
  reset();
  if (time != 0) { setInterval(reset, time*1000); }
}
</script>
<BODY onload="start();">

<div id="top-bar">
    <h1>Hollowpeak Playermap</h1>
    <div id="continent-switcher">
        <button data-continent="azeroth" class="active" onclick="switchContinent('azeroth');">Azeroth</button>
        <button data-continent="outland" onclick="switchContinent('outland');">Outland</button>
        <button data-continent="northrend" onclick="switchContinent('northrend');">Northrend</button>
    </div>
</div>

<div id="calib-readout"></div>

<div id="leaflet-map"></div>

<div id="serverstatus-bar" onMouseDown="">
    <span id="status" class="statustext"></span>
</div>

<!-- Sortable, searchable, paginated player table. Click a column header
     to sort, type in the search box to filter by name, character names
     link out to their Armory page. -->
<div id="player-table-wrapper">
    <h3>Online Players</h3>
    <input type="text" id="player-search" placeholder="Search by character name..." oninput="updateSearch(this.value);">
    <table id="player-table">
        <thead id="player-table-head"></thead>
        <tbody id="player-table-body"></tbody>
    </table>
    <div id="player-table-pagination"></div>
    <div id="player-table-empty" style="display:none;">No players currently online.</div>
</div>

</BODY></HTML>
