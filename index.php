<?php

date_default_timezone_set('Europe/Paris');
if(isset($argv[1]) && $argv[1]) {
    $_GET['date'] = $argv[1];
}
if(isset($argv[2]) && $argv[2]) {
    $_GET['mode'] = $argv[2];
}

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'metros';

$datePage = new DateTime(isset($_GET['date']) ? $_GET['date'].' 05:00:00' : date('Y-m-d H:i:s'));
$datePage->modify('-3 hours');
$dateStart = $datePage->format('Ymd').'T050000';
$datePage->modify('+ 1 day');
$dateEnd = $datePage->format('Ymd').'T020000';

$disruptions = [];

$previousDisruptions = [];
$currentDisruptions = [];

foreach(scandir('datas/json') as $file) {
  if(!is_file('datas/json/'.$file)) {
      continue;
  }
  $datas = json_decode(file_get_contents('datas/json/'.$file));
  foreach($datas->disruptions as $disruption) {
      if(preg_match('/modifications horaires/', $disruption->title)) {
          $disruption->cause = 'INFORMATION';
      }

      if(preg_match('/Modification de desserte/', $disruption->title)) {
          $disruption->cause = 'INFORMATION';
      }

      if(preg_match('/train court/', $disruption->title)) {
          $disruption->cause = 'INFORMATION';
      }

      //$disruption->id = preg_replace('/^[a-z0-9]+-/', '', $disruption->id);
      if(isset($disruptions[$disruption->id])) {
          $disruptions[$disruption->id] = $disruption;
          $currentDisruptions[$disruption->id] = $disruption;
          continue;
      }
      $isInPeriod = false;
      foreach($disruption->applicationPeriods as $period) {
          if($dateStart >= $period->begin || $dateEnd >= $period->begin) {
              $isInPeriod = true;
              break;
          }
      }

      if(!$isInPeriod) {
          continue;
      }

      $isLine = false;
      foreach($datas->lines as $line) {
          foreach($line->impactedObjects as $object) {
              if($object->type != "line") {
                continue;
              }
              if(in_array($disruption->id, $object->disruptionIds)) {
                $isLine = true;
              }
          }
      }

      if(!$isLine) {
          continue;
      }

      $disruptions[$disruption->id] = $disruption;
      $currentDisruptions[$disruption->id] = $disruption;
  }
  foreach($previousDisruptions as $previousDisruption) {
      $dateFile = preg_replace("/^([0-9]{8})/", '\1T', str_replace("_disruptions.json", "", $file));
      if(!isset($currentDisruptions[$previousDisruption->id]) && $disruptions[$previousDisruption->id]->applicationPeriods[0]->end > $dateFile) {
          $disruptions[$previousDisruption->id]->applicationPeriods[0]->end = $dateFile;
      }
  }
  $previousDisruptions = $currentDisruptions;
  $currentDisruptions = [];
}

function get_color_class($nbMinutes, $disruptions, $ligne) {
    $datePage = new DateTime(isset($_GET['date']) ? $_GET['date'].' 05:00:00' : date('Y-m-d H:i:s'));
    $datePage->modify('-3 hours');
    $dateStart = $datePage->format('Ymd').'T050000';
    $dateStartObject = new DateTime($dateStart);
    $dateStartObject->modify("+ ".$nbMinutes." minutes");
    $now = new DateTime();
    $severity = null;
    if($dateStartObject->format('YmdHis') > $now->format('YmdHis')) {
        return 'e';
    }
    $dateCurrent = $dateStartObject->format('Ymd\THis');
    foreach($disruptions as $disruption) {
        if(!preg_match('/'.$ligne.'[^0-9A-Z]+/', $disruption->title)) {
            continue;
        }
        if($disruption->severity == 'INFORMATION') {
            continue;
        }

        foreach($disruption->applicationPeriods as $period) {
            if($dateCurrent >= $period->begin && $dateCurrent <= $period->end && $disruption->cause == "PERTURBATION" && $severity != "BLOQUANTE") {
                $severity = $disruption->severity;
            }
        }
    }

    if($severity && $severity == 'BLOQUANTE') {
        return 'bloque';
    } elseif($severity) {
        return 'perturbe';
    }

    return "ok";
}

function get_infos($nbMinutes, $disruptions, $ligne) {
    $datePage = new DateTime(isset($_GET['date']) ? $_GET['date'].' 05:00:00' : date('Y-m-d H:i:s'));
    $datePage->modify('-3 hours');
    $dateStart = $datePage->format('Ymd').'T050000';
    $dateStartObject = new DateTime($dateStart);
    $dateStartObject->modify("+ ".$nbMinutes." minutes");
    $now = new DateTime();
    $message = null;
    //echo $dateStartObject->format('YmdHis')."\n";
    if($dateStartObject->format('YmdHis') > $now->format('YmdHis')) {
      return "À venir";
    }
    $dateCurrent = $dateStartObject->format('Ymd\THis');
    foreach($disruptions as $disruption) {
      if(!preg_match('/'.$ligne.'[^0-9A-Z]+/', $disruption->title)) {
          continue;
      }
      if($disruption->severity == 'INFORMATION') {
          continue;
      }
      foreach($disruption->applicationPeriods as $period) {
          if($dateCurrent >= $period->begin && $dateCurrent <= $period->end && $disruption->cause == "PERTURBATION") {

            $message .= $disruption->title." (".$disruption->id." - ".$disruption->severity.")\n";
          }
      }
    }

    if($message) {
        return strip_tags($message);
    }

    return "OK";
}

$baseUrlLogo = "https://www.ratp.fr/sites/default/files/lines-assets/picto";
$modesLibelle = ["metros" => "Ⓜ️ Métros", "trains" => "🚆 RER/Trains", "tramways" => "🚈 Tramways"];
$lignes = [
    "metros" => [
        "Métro 1" => $baseUrlLogo."/metro/picto_metro_ligne-1.svg",
        "Métro 2" => $baseUrlLogo."/metro/picto_metro_ligne-2.svg",
        "Métro 3" => $baseUrlLogo."/metro/picto_metro_ligne-3.svg",
        "Métro 3B" => $baseUrlLogo."/metro/picto_metro_ligne-3b.svg",
        "Métro 4" => $baseUrlLogo."/metro/picto_metro_ligne-4.svg",
        "Métro 5" => $baseUrlLogo."/metro/picto_metro_ligne-5.svg",
        "Métro 6" => $baseUrlLogo."/metro/picto_metro_ligne-6.svg",
        "Métro 7" => $baseUrlLogo."/metro/picto_metro_ligne-7.svg",
        "Métro 7B" => $baseUrlLogo."/metro/picto_metro_ligne-7b.svg",
        "Métro 8" => $baseUrlLogo."/metro/picto_metro_ligne-8.svg",
        "Métro 9" => $baseUrlLogo."/metro/picto_metro_ligne-9.svg",
        "Métro 10" => $baseUrlLogo."/metro/picto_metro_ligne-10.svg",
        "Métro 11" => $baseUrlLogo."/metro/picto_metro_ligne-11.svg",
        "Métro 12" => $baseUrlLogo."/metro/picto_metro_ligne-12.svg",
        "Métro 13" => $baseUrlLogo."/metro/picto_metro_ligne-13.svg",
        "Métro 14" => $baseUrlLogo."/metro/picto_metro_ligne-14.svg",
    ],
    "trains" => [
        "Ligne A" => $baseUrlLogo."/rer/picto_rer_ligne-a.svg",
        "Ligne B" => $baseUrlLogo."/rer/picto_rer_ligne-b.svg",
        "Ligne C" => $baseUrlLogo."/rer/picto_rer_ligne-c.svg",
        "Ligne D" => $baseUrlLogo."/rer/picto_rer_ligne-d.svg",
        "Ligne E" => $baseUrlLogo."/rer/picto_rer_ligne-e.svg",
        "Ligne H" => $baseUrlLogo."/sncf/picto_sncf_ligne-h.svg",
        "Ligne J" => $baseUrlLogo."/sncf/picto_sncf_ligne-j.svg",
        "Ligne K" => $baseUrlLogo."/sncf/picto_sncf_ligne-k.svg",
        "Ligne L" => $baseUrlLogo."/sncf/picto_sncf_ligne-l.svg",
        "Ligne N" => $baseUrlLogo."/sncf/picto_sncf_ligne-n.svg",
        "Ligne P" => $baseUrlLogo."/sncf/picto_sncf_ligne-p.svg",
        "Ligne R" => $baseUrlLogo."/sncf/picto_sncf_ligne-r.svg",
        "Ligne U" => $baseUrlLogo."/sncf/picto_sncf_ligne-u.svg",
    ],
    "tramways" => [
        "Tramway T1" => $baseUrlLogo."/tram/picto_tram_ligne-t1.svg",
        "Tramway T2" => $baseUrlLogo."/tram/picto_tram_ligne-t2.svg",
        "Tramway T3a" => $baseUrlLogo."/tram/picto_tram_ligne-t3a.svg",
        "Tramway T3b" => $baseUrlLogo."/tram/picto_tram_ligne-t3b.svg",
        "Tramway T4" => $baseUrlLogo."/tram/picto_tram_ligne-t4.svg",
        "Tramway T5" => $baseUrlLogo."/tram/picto_tram_ligne-t5.svg",
        "Tramway T6" => $baseUrlLogo."/tram/picto_tram_ligne-t6.svg",
        "Tramway T7" => $baseUrlLogo."/tram/picto_tram_ligne-t7.svg",
        "Tramway T8" => $baseUrlLogo."/tram/picto_tram_ligne-t8.svg",
        "Tramway T9" => $baseUrlLogo."/tram/picto_tram_ligne-t9.svg",
        "Tramway T10" => $baseUrlLogo."/tram/picto_tram_ligne-t10.svg",
        "Tramway T11" => $baseUrlLogo."/tram/picto_tram_ligne-t11.svg",
        "Tramway T12" => $baseUrlLogo."/tram/picto_tram_ligne-t12.svg",
        "Tramway T13" => $baseUrlLogo."/tram/picto_tram_ligne-t13.svg",
    ]
]
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="fr">
<head>

<meta charset="utf-8">
<meta name="viewport" content="height=device-height, width=device-width, initial-scale=1.0, minimum-scale=1.0, target-densitydpi=device-dpi">
<link rel="stylesheet" href="/css/style.css">
<title>Suivi du trafic des <?php echo $modesLibelle[$mode] ?> du <?php echo date_format(new DateTime($dateStart), "d/m/Y"); ?> - RATP Status</title>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if(document.querySelector('.ligne .e')) {
            window.scrollTo({ left: document.querySelector('.ligne .e').offsetLeft - window.innerWidth + 66 });
        }
    })
</script>
</head>
<body>
<div id="container">
<div id="header">
<a id="lien_infos" href="https://github.com/wincelau/ratpstatus">ℹ</a>
<a id="lien_refresh" href="" onclick="location.reload(); return false;">↻</a>
<h1><a href="/<?php echo date_format((new DateTime($dateStart))->modify('-1 day'), "Ymd"); ?>/<?php echo $mode ?>.html"><</a> Suivi trafic du <?php echo date_format(new DateTime($dateStart), "d/m/Y"); ?> <a href="/<?php echo date_format((new DateTime($dateStart))->modify('+1 day'), "Ymd"); ?>/<?php echo $mode ?>.html">></a></h1>
<div id="nav_mode"><?php foreach($lignes as $m => $ligne): ?><a style="<?php if($mode == $m): ?>font-weight: bold;<?php endif; ?>" href="/<?php echo (new DateTime($dateStart))->format('Ymd') ?>/<?php echo $m ?>.html"><?php echo $modesLibelle[$m] ?></a><?php endforeach; ?></div>
<div class="hline"><?php for($i = 0; $i <= 1260; $i = $i + 60): ?><div class="ih"><?php if($i % 60 == 0): ?><small><?php echo sprintf("%02d", (intval($i / 60) + 5) % 24) ?>h</small><?php endif; ?></div><?php endfor; ?></div>
</div>
<div id="lignes">
<?php foreach($lignes[$mode] as $ligne => $logo): ?>
<div class="ligne"><div class="logo"><img alt="<?php echo $ligne ?>" title="<?php echo $ligne ?>" src="<?php echo $logo ?>" /></div>
<?php for($i = 0; $i < 1260; $i = $i + 2): ?><a class="i <?php echo get_color_class($i, $disruptions, $ligne) ?> <?php if($i % 60 == 0): ?>i1h<?php elseif($i % 10 == 0): ?>i10m<?php endif; ?>" title="<?php echo sprintf("%02d", (intval($i / 60) + 5) % 24) ?>h<?php echo sprintf("%02d", ($i % 60) ) ?> - <?php echo get_infos($i, $disruptions, $ligne) ?>"></a>
<?php endfor; ?></div>
<?php endforeach; ?>
</div>
</div>
</body>
</html>
