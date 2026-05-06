<?php
session_start();

$PASSWORD = 'natalie';
$DB_PATH  = __DIR__ . '/../dotaznik.db';

// ── Přihlášení ────────────────────────────────────────────────────
if (isset($_POST['password'])) {
    if ($_POST['password'] === $PASSWORD) {
        $_SESSION['admin'] = true;
    } else {
        $loginError = true;
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
if (empty($_SESSION['admin'])) {
    ?><!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin — Jaké Brno chcete?</title>
<style>
  body { font-family: system-ui, sans-serif; background: #f0f0ec; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
  .box { background: #fff; border-radius: 12px; padding: 40px; width: 320px; box-shadow: 0 2px 16px rgba(0,0,0,.08); }
  h2 { margin: 0 0 24px; font-size: 20px; }
  input { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; margin-bottom: 12px; }
  button { width: 100%; padding: 11px; background: #557A53; color: #fff; border: none; border-radius: 8px; font-size: 15px; cursor: pointer; }
  .err { color: #c0392b; font-size: 14px; margin-bottom: 10px; }
</style>
</head>
<body>
<div class="box">
  <h2>🔒 Admin</h2>
  <?php if (!empty($loginError)): ?><p class="err">Špatné heslo.</p><?php endif; ?>
  <form method="post">
    <input type="password" name="password" placeholder="Heslo" autofocus>
    <button type="submit">Přihlásit se</button>
  </form>
</div>
</body>
</html><?php
    exit;
}

// ── Databáze ──────────────────────────────────────────────────────
try {
    $db = new PDO('sqlite:' . $DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Databáze není dostupná: ' . $e->getMessage());
}

// ── Slovník otázek (jednorázový extrakt z index.html) ────────────
$BLOCKS = [
  'a' => 'A — Brno za 10 let',
  'c' => 'C — Velké projekty',
  'e' => 'E — Doprava a veřejný prostor',
  'f' => 'F — Bydlení',
  'g' => 'G — Rodiny a děti',
  'h' => 'H — Sport a volný čas',
  'i' => 'I — Bezpečnost',
  'l' => 'L — Brno osobně',
  'k' => 'K — Celospolečenský pohled',
  'n' => 'N — O vás',
];

// 'n' = krátké označení v UI; 'block' = klíč do $BLOCKS; 'text' = znění; 'long' = full-width volný text
$QUESTIONS = [
  'a2'        => ['n'=>'A2',  'block'=>'a', 'text'=>'Vyberte až 3 pojmy, které by měly vystihovat Brno ZA 10 LET.'],
  'a2-other'  => ['n'=>'A2 — jiné',  'block'=>'a', 'text'=>'A2 — upřesnění "jiné"', 'long'=>true],
  'a3'        => ['n'=>'A3',  'block'=>'a', 'text'=>'Co jsou silné stránky Brna — věci, které tu fungují dobře?', 'long'=>true],
  'a4'        => ['n'=>'A4',  'block'=>'a', 'text'=>'Co považujete za největší problémy Brna a co byste současnému vedení nejvíce vytknul/a?', 'long'=>true],

  'c1'        => ['n'=>'C1',  'block'=>'c', 'text'=>'Které tři záměry jsou pro vás jako obyvatele Brna nejdůležitější?'],
  'c1-other'  => ['n'=>'C1 — jiné',  'block'=>'c', 'text'=>'C1 — upřesnění "jiné"', 'long'=>true],
  'c1b'       => ['n'=>'C1b', 'block'=>'c', 'text'=>'Chybí v seznamu něco, co považujete za opravdu důležité?', 'long'=>true],
  'c2'        => ['n'=>'C2',  'block'=>'c', 'text'=>'Kde má Brno největší nevyužitý potenciál?', 'long'=>true],

  'e1'        => ['n'=>'E1',  'block'=>'e', 'text'=>'Jakými způsoby se pravidelně pohybujete po Brně?'],
  'e1-other'  => ['n'=>'E1 — jak jinak',  'block'=>'e', 'text'=>'E1 — jak jinak', 'long'=>true],
  'e2'        => ['n'=>'E2',  'block'=>'e', 'text'=>'Co by vás motivovalo jezdit autem méně nebo ho omezit?'],
  'e2-other'  => ['n'=>'E2 — jiné',  'block'=>'e', 'text'=>'E2 — upřesnění "jiné"', 'long'=>true],
  'e3a'       => ['n'=>'E3a', 'block'=>'e', 'text'=>'Setkáváte se při pohybu pěšky v Brně s problémy?'],
  'e3b'       => ['n'=>'E3b', 'block'=>'e', 'text'=>'S jakými problémy se nejčastěji setkáváte, případně kde? (chodci)', 'long'=>true],
  'e4a'       => ['n'=>'E4a', 'block'=>'e', 'text'=>'Setkáváte se při jízdě na kole nebo koloběžce s problémy?'],
  'e4b'       => ['n'=>'E4b', 'block'=>'e', 'text'=>'S jakými problémy se nejčastěji setkáváte, případně kde? (kolo/koloběžka)', 'long'=>true],
  'e8a'       => ['n'=>'E8a', 'block'=>'e', 'text'=>'Některá evropská města rozšiřují chodníky, přidávají zeleň, lavičky a místa k posezení namísto části parkovacích míst. Jak byste přijal/a takovou přeměnu a rozšíření pěších zón v centru Brna?'],
  'e9'        => ['n'=>'E9',  'block'=>'e', 'text'=>'Dokázali byste si představit, že by pěší zónou byla například dolní část ulice Veveří?'],
  'e10'       => ['n'=>'E10', 'block'=>'e', 'text'=>'Mělo by město aktivně regulovat vizuální smog v Brně — billboardy, neuspořádané reklamy, plachty a výlohy, které narušují vzhled ulic?'],

  'f1a'       => ['n'=>'F1a', 'block'=>'f', 'text'=>'Jak vážný problém je podle vás dostupnost bydlení v Brně?'],
  'f1b'       => ['n'=>'F1b', 'block'=>'f', 'text'=>'Řešíte vy nebo někdo z vašich blízkých problém s bydlením v Brně — ať už to je shánění bytu, nebo problém ho zaplatit?'],
  'f3'        => ['n'=>'F3',  'block'=>'f', 'text'=>'Co by město mělo dělat pro zvýšení dostupnosti bydlení?'],
  'f3-other'  => ['n'=>'F3 — jiné',  'block'=>'f', 'text'=>'F3 — upřesnění "jiné"', 'long'=>true],

  'g0'        => ['n'=>'G0',  'block'=>'g', 'text'=>'Máte doma dítě či děti do 18 let?'],
  'g1a'       => ['n'=>'G1a', 'block'=>'g', 'text'=>'Zaznamenali jste problémy s dostupností míst ve školce v Brně?'],
  'g1b'       => ['n'=>'G1b', 'block'=>'g', 'text'=>'Jak hodnotíte dostupnost a kvalitu základních škol ve vaší části Brna?'],
  'g1c'       => ['n'=>'G1c', 'block'=>'g', 'text'=>'Narazili jste vy nebo vaši blízcí na problém s kapacitou středních škol v Brně?'],
  'g2'        => ['n'=>'G2',  'block'=>'g', 'text'=>'Co pro rodiny s dětmi v Brně nejvíce chybí nebo potřebuje zlepšit?'],
  'g2-other'  => ['n'=>'G2 — jiné',  'block'=>'g', 'text'=>'G2 — upřesnění "jiné"', 'long'=>true],
  'g3'        => ['n'=>'G3',  'block'=>'g', 'text'=>'Jak město může nejlépe pomoci rodinám s péčí o děti během školních prázdnin?'],
  'g3-other'  => ['n'=>'G3 — jiné',  'block'=>'g', 'text'=>'G3 — upřesnění "jiné"', 'long'=>true],

  'h1a'       => ['n'=>'H1a', 'block'=>'h', 'text'=>'Jak hodnotíte v Brně podmínky pro amatérský sport a běžný pohyb (běh, kolo, plavání, hřiště, sportovní areály pro veřejnost, tělocvičny)?'],
  'h1b'       => ['n'=>'H1b', 'block'=>'h', 'text'=>'Jaká sportoviště nebo místa pro volný čas a v jaké části Brna byste uvítali?', 'long'=>true],
  'h3'        => ['n'=>'H3',  'block'=>'h', 'text'=>'Peníze, které dává město na sport, by měly přednostně směřovat na:'],

  'i1'        => ['n'=>'I1',  'block'=>'i', 'text'=>'Cítíte se v Brně bezpečně?'],
  'i2'        => ['n'=>'I2',  'block'=>'i', 'text'=>'Co je hlavním zdrojem pocitu nebezpečí nebo nepohody? Máte na mysli konkrétní místo?', 'long'=>true],

  'l1'        => ['n'=>'L1',  'block'=>'l', 'text'=>'Co si v Brně ze všeho nejvíce přejete?', 'long'=>true],
  'l2'        => ['n'=>'L2',  'block'=>'l', 'text'=>'Co se v Brně v žádném případě nesmí stát?', 'long'=>true],
  'l3'        => ['n'=>'L3',  'block'=>'l', 'text'=>'Máte nějaký vzkaz nebo téma, které se v dotazníku neobjevilo?', 'long'=>true],

  'k2'        => ['n'=>'K2',  'block'=>'k', 'text'=>'Jak velký vliv má celostátní politická situace na to, koho zvolíte do brněnského zastupitelstva?'],
  'k1'        => ['n'=>'K1',  'block'=>'k', 'text'=>'Které z následujících jevů vás osobně znepokojují? (max 3)'],
  'k1-other'  => ['n'=>'K1 — jiné',  'block'=>'k', 'text'=>'K1 — upřesnění "jiné"', 'long'=>true],

  'n1'        => ['n'=>'N1',  'block'=>'n', 'text'=>'Pohlaví'],
  'n2'        => ['n'=>'N2',  'block'=>'n', 'text'=>'Věková skupina'],
  'n3'        => ['n'=>'N3',  'block'=>'n', 'text'=>'Jak byste popsal/a svoji aktuální ekonomickou situaci?'],
  'n4'        => ['n'=>'N4',  'block'=>'n', 'text'=>'Bydlím v'],
  'n5'        => ['n'=>'N5',  'block'=>'n', 'text'=>'Část Brna, kde bydlím'],
  'n6'        => ['n'=>'N6',  'block'=>'n', 'text'=>'Koho jste volili v posledních sněmovních volbách (2025)?'],
];

// ── Pomocné funkce ────────────────────────────────────────────────
function statusBadge($s) {
    return $s === 'complete'
        ? '<span class="badge badge-ok">✓ kompletní</span>'
        : '<span class="badge badge-partial">… rozpracovaná</span>';
}

function fmtAnswer($v) {
    if (is_array($v)) return htmlspecialchars(implode(', ', $v));
    return nl2br(htmlspecialchars((string)$v));
}

// SQL filtr podle stavu + fulltextu (LIKE přes JSON `data` a `email`)
function buildWhere($status, $q, &$params) {
    $w = [];
    if ($status === 'complete') $w[] = "status = 'complete'";
    if ($status === 'partial')  $w[] = "status = 'partial'";
    if ($q !== '') {
        $w[] = "(data LIKE :q OR IFNULL(email,'') LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }
    return $w ? 'WHERE ' . implode(' AND ', $w) : '';
}

// ── CSV export ────────────────────────────────────────────────────
if (isset($_GET['csv'])) {
    $rows = $db->query('SELECT * FROM responses ORDER BY updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    $allKeys = [];
    foreach ($rows as $row) {
        $d = json_decode($row['data'], true) ?: [];
        foreach (array_keys($d) as $k) {
            if (!in_array($k, $allKeys, true)) $allKeys[] = $k;
        }
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dotaznik-' . date('Y-m-d') . '.csv"');
    $f = fopen('php://output', 'w');
    fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($f, array_merge(['id','uuid','status','last_page','email','created_at','updated_at'], $allKeys), ';');
    foreach ($rows as $row) {
        $d = json_decode($row['data'], true) ?: [];
        $line = [$row['id'], $row['uuid'], $row['status'], $row['last_page'], $row['email'], $row['created_at'], $row['updated_at']];
        foreach ($allKeys as $k) {
            $v = $d[$k] ?? '';
            $line[] = is_array($v) ? implode(', ', $v) : $v;
        }
        fputcsv($f, $line, ';');
    }
    fclose($f);
    exit;
}

// ── Filtry (sdílené pro list + detail prev/next) ──────────────────
$status = $_GET['status'] ?? 'all';
if (!in_array($status, ['all','complete','partial'], true)) $status = 'all';
$qstr   = trim((string)($_GET['q'] ?? ''));
$view   = $_GET['view'] ?? 'list';
if (!in_array($view, ['list','byq'], true)) $view = 'list';

// ── Statistiky ────────────────────────────────────────────────────
$stats = $db->query("
    SELECT COUNT(*) total,
           SUM(status='complete') complete,
           SUM(status='partial')  partial,
           SUM(email IS NOT NULL AND TRIM(email) != '') with_email
    FROM responses
")->fetch(PDO::FETCH_ASSOC);

// ── Detail respondenta + prev/next podle aktuálního filtru ───────
$detail = null; $prevId = null; $nextId = null; $detailPos = null; $detailTotal = null;
if (isset($_GET['id'])) {
    $stmt = $db->prepare('SELECT * FROM responses WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $detail = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($detail) {
        $params = [];
        $where = buildWhere($status, $qstr, $params);
        $idsStmt = $db->prepare("SELECT id FROM responses $where ORDER BY updated_at DESC");
        $idsStmt->execute($params);
        $ids = array_map('intval', $idsStmt->fetchAll(PDO::FETCH_COLUMN));
        $pos = array_search((int)$detail['id'], $ids, true);
        if ($pos === false) {
            // Detail je mimo aktuální filtr — fallback bez filtru
            $ids = array_map('intval', $db->query('SELECT id FROM responses ORDER BY updated_at DESC')->fetchAll(PDO::FETCH_COLUMN));
            $pos = array_search((int)$detail['id'], $ids, true);
        }
        if ($pos !== false) {
            $detailPos   = $pos + 1;
            $detailTotal = count($ids);
            if ($pos > 0) $prevId = $ids[$pos - 1];
            if ($pos < count($ids) - 1) $nextId = $ids[$pos + 1];
        }
    }
}

// ── Listing pro view=list ─────────────────────────────────────────
$rows = []; $total = 0; $pages = 1; $page = 1;
if ($view === 'list') {
    $page   = max(1, (int)($_GET['p'] ?? 1));
    $limit  = 30;
    $offset = ($page - 1) * $limit;

    $params = [];
    $where = buildWhere($status, $qstr, $params);
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM responses $where");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();
    $pages = max(1, (int)ceil($total / $limit));

    $listStmt = $db->prepare("SELECT id, uuid, status, last_page, email, created_at, updated_at
                              FROM responses $where
                              ORDER BY updated_at DESC
                              LIMIT $limit OFFSET $offset");
    $listStmt->execute($params);
    $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Po otázkách (view=byq) ────────────────────────────────────────
$qid = $_GET['qid'] ?? '';
$byqAnswers = []; $byqCounts = [];
if ($view === 'byq') {
    // Spočítat, kolik respondentů odpovědělo na každou otázku — projdeme jen kompletní + rozpracované
    $allData = $db->query("SELECT id, status, updated_at, email, data FROM responses ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($QUESTIONS as $k => $_) $byqCounts[$k] = 0;
    foreach ($allData as $r) {
        $d = json_decode($r['data'], true) ?: [];
        foreach ($d as $k => $v) {
            if (!isset($byqCounts[$k])) continue;
            if (is_array($v) ? count($v) > 0 : trim((string)$v) !== '') $byqCounts[$k]++;
        }
    }
    if ($qid && isset($QUESTIONS[$qid])) {
        foreach ($allData as $r) {
            $d = json_decode($r['data'], true) ?: [];
            if (!isset($d[$qid])) continue;
            $v = $d[$qid];
            if (is_array($v) ? count($v) === 0 : trim((string)$v) === '') continue;
            $byqAnswers[] = ['id'=>$r['id'], 'status'=>$r['status'], 'updated_at'=>$r['updated_at'], 'email'=>$r['email'], 'val'=>$v];
        }
    }
}

// ── Souhrn voleb pro view=byq (jen pro výběrové otázky) ──────────
// Volný text poznáme dle 'long' v $QUESTIONS — ten souhrn nedělá smysl.
$byqSummary = null; $byqIsMulti = false;
if ($view === 'byq' && $qid && isset($QUESTIONS[$qid]) && empty($QUESTIONS[$qid]['long']) && !empty($byqAnswers)) {
    $counts = [];
    foreach ($byqAnswers as $a) {
        $vals = is_array($a['val']) ? $a['val'] : [$a['val']];
        if (count($vals) > 1) $byqIsMulti = true;
        foreach ($vals as $opt) {
            $opt = trim((string)$opt);
            if ($opt === '') continue;
            $counts[$opt] = ($counts[$opt] ?? 0) + 1;
        }
    }
    arsort($counts);
    $byqSummary = $counts;
}

// ── Helper: URL s filtry ──────────────────────────────────────────
function urlWith($overrides = []) {
    $base = ['view'=>$_GET['view'] ?? null, 'status'=>$_GET['status'] ?? null, 'q'=>$_GET['q'] ?? null, 'p'=>$_GET['p'] ?? null, 'qid'=>$_GET['qid'] ?? null, 'id'=>$_GET['id'] ?? null];
    foreach ($overrides as $k => $v) $base[$k] = $v;
    $parts = [];
    foreach ($base as $k => $v) {
        if ($v === null || $v === '' || ($k === 'status' && $v === 'all') || ($k === 'view' && $v === 'list')) continue;
        $parts[] = urlencode($k) . '=' . urlencode((string)$v);
    }
    return 'admin.php' . ($parts ? '?' . implode('&', $parts) : '');
}
?><!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin — Jaké Brno chcete?</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: system-ui, sans-serif; background: #f0f0ec; margin: 0; color: #222; }
  a { color: #2c5e2a; }

  .topbar { background: #557A53; color: #fff; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
  .topbar h1 { margin: 0; font-size: 18px; font-weight: 600; }
  .topbar .tabs { display: flex; gap: 4px; }
  .topbar .tabs a { color: rgba(255,255,255,.75); text-decoration: none; font-size: 14px; padding: 6px 12px; border-radius: 6px; }
  .topbar .tabs a.active { background: rgba(255,255,255,.18); color: #fff; }
  .topbar .tabs a:hover { color: #fff; }
  .topbar .right a { color: rgba(255,255,255,.8); text-decoration: none; font-size: 14px; }

  .wrap { max-width: 1100px; margin: 0 auto; padding: 24px 16px; }
  .stats { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
  .stat { background: #fff; border-radius: 10px; padding: 18px 24px; flex: 1; min-width: 140px; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
  .stat-num { font-size: 36px; font-weight: 700; color: #557A53; line-height: 1; }
  .stat-label { font-size: 13px; color: #666; margin-top: 4px; }

  .toolbar { display: flex; gap: 10px; margin-bottom: 16px; align-items: center; flex-wrap: wrap; }
  .btn { padding: 9px 18px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
  .btn-primary { background: #557A53; color: #fff; }
  .btn-outline { background: #fff; color: #333; border: 1px solid #ddd; }
  .btn:hover { opacity: .85; }
  .chips { display: flex; gap: 6px; }
  .chip { padding: 7px 13px; border-radius: 999px; font-size: 13px; text-decoration: none; background: #fff; color: #333; border: 1px solid #ddd; }
  .chip.active { background: #557A53; color: #fff; border-color: #557A53; }
  .search { display: flex; gap: 6px; margin-left: auto; }
  .search input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; min-width: 220px; }

  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
  th { background: #f7f7f5; text-align: left; padding: 10px 14px; font-size: 13px; color: #555; font-weight: 600; border-bottom: 1px solid #eee; }
  td { padding: 10px 14px; font-size: 14px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #fafaf8; }
  .uuid { font-family: monospace; font-size: 12px; color: #999; }

  .badge { padding: 2px 8px; border-radius: 20px; font-size: 12px; }
  .badge-ok { background: #d4edda; color: #155724; }
  .badge-partial { background: #fff3cd; color: #856404; }

  .pagination { display: flex; gap: 6px; margin-top: 16px; justify-content: center; flex-wrap: wrap; }
  .pagination a, .pagination span { padding: 7px 13px; border-radius: 7px; font-size: 14px; text-decoration: none; background: #fff; color: #333; border: 1px solid #ddd; }
  .pagination .cur { background: #557A53; color: #fff; border-color: #557A53; }

  /* Detail respondenta */
  .modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: flex; align-items: flex-start; justify-content: center; overflow-y: auto; padding: 24px 12px; z-index: 100; }
  .modal { background: #fff; border-radius: 12px; width: 100%; max-width: 760px; padding: 24px 28px 32px; }
  .modal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; gap: 12px; flex-wrap: wrap; }
  .modal-head h2 { margin: 0; font-size: 20px; }
  .modal-nav { display: flex; gap: 6px; align-items: center; font-size: 13px; color: #666; }
  .modal-nav a, .modal-nav .disabled { padding: 6px 10px; border-radius: 6px; border: 1px solid #ddd; text-decoration: none; color: #333; background: #fff; }
  .modal-nav .disabled { opacity: .35; cursor: default; }
  .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; line-height: 1; padding: 0 4px; }
  .meta { font-size: 13px; color: #888; margin-bottom: 16px; line-height: 1.7; padding: 10px 14px; background: #f7f7f5; border-radius: 8px; }

  .block-head { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #557A53; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #e5e5e0; }
  .qa { padding: 12px 0; border-bottom: 1px solid #f3f3f0; }
  .qa:last-child { border-bottom: none; }
  .qa .qtext { font-size: 13px; color: #557A53; font-weight: 600; margin-bottom: 4px; }
  .qa .qtext .qid { color: #999; font-weight: 500; margin-right: 6px; font-family: monospace; }
  .qa .qval { font-size: 14.5px; color: #1f1f1f; line-height: 1.55; }
  .qa.long .qval { background: #fafaf6; border-left: 3px solid #557A53; padding: 10px 14px; border-radius: 4px; white-space: pre-wrap; }
  .qa .qmissing { color: #bbb; font-style: italic; font-size: 13.5px; }

  /* Po otázkách */
  .qlist { background: #fff; border-radius: 10px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,.06); margin-bottom: 24px; }
  .qlist .qblock { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #557A53; letter-spacing: .5px; margin: 14px 0 6px; }
  .qlist .qblock:first-child { margin-top: 0; }
  .qlist a.qrow { display: flex; gap: 10px; padding: 7px 10px; border-radius: 6px; text-decoration: none; color: #222; align-items: baseline; }
  .qlist a.qrow:hover { background: #f5f5f1; }
  .qlist a.qrow.active { background: #eef3ed; }
  .qlist .qrow .qn { font-family: monospace; font-size: 12px; color: #888; width: 48px; flex-shrink: 0; }
  .qlist .qrow .qt { font-size: 14px; flex: 1; }
  .qlist .qrow .qc { font-size: 12px; color: #888; }

  .answers { background: #fff; border-radius: 10px; padding: 20px 24px; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
  .answers h2 { margin: 0 0 6px; font-size: 18px; }
  .answers .qfull { font-size: 14px; color: #555; margin-bottom: 16px; }
  .answers .ans { padding: 12px 0; border-bottom: 1px solid #f1f1ee; display: flex; gap: 16px; align-items: flex-start; }
  .answers .ans:last-child { border-bottom: none; }
  .answers .ans .ameta { font-size: 12px; color: #999; width: 110px; flex-shrink: 0; padding-top: 2px; }
  .answers .ans .ameta a { color: #557A53; text-decoration: none; }
  .answers .ans .aval { font-size: 14.5px; color: #1f1f1f; line-height: 1.55; flex: 1; white-space: pre-wrap; }

  .summary { background: #fafaf6; border: 1px solid #ececec; border-radius: 8px; padding: 14px 16px; margin: 8px 0 18px; }
  .summary-head { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #557A53; margin-bottom: 10px; }
  .sum-row { display: grid; grid-template-columns: minmax(140px, 1fr) 2fr 90px; gap: 10px; align-items: center; padding: 4px 0; font-size: 13.5px; }
  .sum-label { color: #1f1f1f; word-break: break-word; }
  .sum-bar { height: 10px; background: #e7e7df; border-radius: 999px; overflow: hidden; }
  .sum-fill { height: 100%; background: #557A53; border-radius: 999px; }
  .sum-num { text-align: right; color: #333; font-variant-numeric: tabular-nums; }
  .sum-pct { color: #888; margin-left: 6px; font-size: 12px; }
  .summary-divider { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #888; margin: 18px 0 6px; padding-top: 10px; border-top: 1px dashed #e0e0d8; }
  @media (max-width: 640px) {
    .sum-row { grid-template-columns: 1fr 70px; }
    .sum-bar { grid-column: 1 / -1; order: 3; }
  }

  @media (max-width: 640px) {
    .answers .ans { flex-direction: column; gap: 4px; }
    .answers .ans .ameta { width: auto; }
    .modal { padding: 18px; }
  }
</style>
</head>
<body>

<div class="topbar">
  <h1>Jaké Brno chcete? — Admin</h1>
  <div class="tabs">
    <a href="<?= htmlspecialchars(urlWith(['view'=>null, 'qid'=>null, 'id'=>null])) ?>" class="<?= $view==='list'?'active':'' ?>">Respondenti</a>
    <a href="<?= htmlspecialchars(urlWith(['view'=>'byq', 'p'=>null, 'id'=>null])) ?>" class="<?= $view==='byq'?'active':'' ?>">Po otázkách</a>
  </div>
  <div class="right"><a href="?logout=1">Odhlásit se</a></div>
</div>

<div class="wrap">

  <div class="stats">
    <div class="stat"><div class="stat-num"><?= $stats['total'] ?></div><div class="stat-label">celkem odpovědí</div></div>
    <div class="stat"><div class="stat-num"><?= $stats['complete'] ?></div><div class="stat-label">kompletních</div></div>
    <div class="stat"><div class="stat-num"><?= $stats['partial'] ?></div><div class="stat-label">rozpracovaných</div></div>
    <div class="stat"><div class="stat-num"><?= $stats['with_email'] ?></div><div class="stat-label">s e-mailem</div></div>
  </div>

<?php if ($view === 'list'): ?>

  <div class="toolbar">
    <div class="chips">
      <a class="chip <?= $status==='all'?'active':'' ?>" href="<?= htmlspecialchars(urlWith(['status'=>null, 'p'=>null, 'id'=>null])) ?>">Všechny</a>
      <a class="chip <?= $status==='complete'?'active':'' ?>" href="<?= htmlspecialchars(urlWith(['status'=>'complete', 'p'=>null, 'id'=>null])) ?>">Kompletní</a>
      <a class="chip <?= $status==='partial'?'active':'' ?>" href="<?= htmlspecialchars(urlWith(['status'=>'partial', 'p'=>null, 'id'=>null])) ?>">Rozpracované</a>
    </div>
    <a class="btn btn-primary" href="?csv=1">⬇ CSV</a>
    <form class="search" method="get">
      <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>"><?php endif; ?>
      <input type="search" name="q" value="<?= htmlspecialchars($qstr) ?>" placeholder="Hledat v odpovědích a e-mailu…">
      <button class="btn btn-outline" type="submit">Hledat</button>
      <?php if ($qstr !== ''): ?><a class="btn btn-outline" href="<?= htmlspecialchars(urlWith(['q'=>null, 'p'=>null])) ?>">×</a><?php endif; ?>
    </form>
  </div>

  <div style="font-size:13px;color:#888;margin-bottom:10px">Celkem <?= $total ?> záznamů, strana <?= $page ?> z <?= $pages ?></div>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>UUID</th>
        <th>Stav</th>
        <th>Poslední strana</th>
        <th>E-mail</th>
        <th>Aktualizováno</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= $r['id'] ?></td>
        <td class="uuid"><?= htmlspecialchars(substr($r['uuid'], 0, 8)) ?>…</td>
        <td><?= statusBadge($r['status']) ?></td>
        <td><?= htmlspecialchars($r['last_page'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['email'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['updated_at']) ?></td>
        <td><a class="btn btn-outline" href="<?= htmlspecialchars(urlWith(['id'=>$r['id']])) ?>">Detail</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
      <tr><td colspan="7" style="text-align:center;color:#999;padding:32px">Žádné odpovědi neodpovídají filtru.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?>
        <span class="cur"><?= $i ?></span>
      <?php else: ?>
        <a href="<?= htmlspecialchars(urlWith(['p'=>$i])) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

<?php else: /* view=byq */ ?>

  <div style="display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start">
    <div class="qlist">
      <?php
        $curBlock = null;
        foreach ($QUESTIONS as $k => $meta):
          if ($meta['block'] !== $curBlock):
            $curBlock = $meta['block']; ?>
            <div class="qblock"><?= htmlspecialchars($BLOCKS[$curBlock] ?? $curBlock) ?></div>
      <?php endif; ?>
        <a class="qrow <?= $qid===$k?'active':'' ?>" href="<?= htmlspecialchars(urlWith(['view'=>'byq', 'qid'=>$k, 'id'=>null, 'p'=>null])) ?>">
          <span class="qn"><?= htmlspecialchars($meta['n']) ?></span>
          <span class="qt"><?= htmlspecialchars(mb_strimwidth($meta['text'], 0, 60, '…', 'UTF-8')) ?></span>
          <span class="qc"><?= $byqCounts[$k] ?? 0 ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="answers">
      <?php if ($qid && isset($QUESTIONS[$qid])): ?>
        <h2><?= htmlspecialchars($QUESTIONS[$qid]['n']) ?></h2>
        <div class="qfull"><?= htmlspecialchars($QUESTIONS[$qid]['text']) ?></div>
        <div style="font-size:13px;color:#888;margin-bottom:6px"><?= count($byqAnswers) ?> odpovědí<?= $byqIsMulti ? ' (vícenásobný výběr)' : '' ?></div>

        <?php if ($byqSummary !== null): ?>
          <?php
            $respondents = count($byqAnswers);
            $maxC = max($byqSummary);
          ?>
          <div class="summary">
            <div class="summary-head">Souhrn — seřazeno dle četnosti<?= $byqIsMulti ? ' · % počítáno z respondentů, kteří odpověděli' : '' ?></div>
            <?php foreach ($byqSummary as $opt => $c):
              $pct = $respondents > 0 ? round(100 * $c / $respondents) : 0;
              $barW = $maxC > 0 ? round(100 * $c / $maxC) : 0;
            ?>
              <div class="sum-row">
                <div class="sum-label"><?= htmlspecialchars($opt) ?></div>
                <div class="sum-bar"><div class="sum-fill" style="width:<?= $barW ?>%"></div></div>
                <div class="sum-num"><?= $c ?> <span class="sum-pct"><?= $pct ?> %</span></div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="summary-divider">Jednotlivé odpovědi</div>
        <?php endif; ?>

        <?php foreach ($byqAnswers as $a): ?>
          <div class="ans">
            <div class="ameta">
              <a href="<?= htmlspecialchars(urlWith(['view'=>'list', 'qid'=>null, 'id'=>$a['id']])) ?>">#<?= $a['id'] ?></a><br>
              <?= htmlspecialchars(substr($a['updated_at'], 0, 16)) ?><br>
              <?= statusBadge($a['status']) ?>
            </div>
            <div class="aval"><?= fmtAnswer($a['val']) ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($byqAnswers)): ?>
          <p style="color:#999;padding:24px 0">Na tuto otázku zatím nikdo neodpověděl.</p>
        <?php endif; ?>
      <?php else: ?>
        <p style="color:#888;padding:32px 0;text-align:center">Vyberte otázku v levém seznamu.</p>
      <?php endif; ?>
    </div>
  </div>

<?php endif; ?>

</div>

<?php if ($detail): ?>
<?php
  $data = json_decode($detail['data'], true) ?: [];
  // Seskupit odpovědi po blocích podle $QUESTIONS (data nemusí obsahovat všechny klíče)
  $byBlock = [];
  foreach ($QUESTIONS as $k => $meta) {
      if (!array_key_exists($k, $data)) continue;
      $v = $data[$k];
      if (is_array($v) ? count($v) === 0 : trim((string)$v) === '') continue;
      $byBlock[$meta['block']][] = ['key'=>$k, 'meta'=>$meta, 'val'=>$v];
  }
  // "Ostatní" — klíče, které nemáme ve slovníku (utm_*, email, apod.)
  $extra = [];
  foreach ($data as $k => $v) {
      if (isset($QUESTIONS[$k]) || $k === 'email') continue;
      $extra[$k] = $v;
  }
?>
<div class="modal-bg" onclick="if(event.target===this)location.href='<?= htmlspecialchars(urlWith(['id'=>null])) ?>'">
  <div class="modal">
    <div class="modal-head">
      <h2>Odpověď #<?= $detail['id'] ?></h2>
      <div class="modal-nav">
        <?php if ($detailPos !== null): ?><span style="margin-right:6px"><?= $detailPos ?> / <?= $detailTotal ?></span><?php endif; ?>
        <?php if ($prevId): ?><a href="<?= htmlspecialchars(urlWith(['id'=>$prevId])) ?>" id="navPrev" title="←">← Předchozí</a>
        <?php else: ?><span class="disabled">← Předchozí</span><?php endif; ?>
        <?php if ($nextId): ?><a href="<?= htmlspecialchars(urlWith(['id'=>$nextId])) ?>" id="navNext" title="→">Další →</a>
        <?php else: ?><span class="disabled">Další →</span><?php endif; ?>
        <button class="modal-close" onclick="location.href='<?= htmlspecialchars(urlWith(['id'=>null])) ?>'" title="Zavřít (Esc)">×</button>
      </div>
    </div>
    <div class="meta">
      <?= statusBadge($detail['status']) ?>
      &nbsp; UUID: <span style="font-family:monospace"><?= htmlspecialchars($detail['uuid']) ?></span><br>
      Vytvořeno: <?= htmlspecialchars($detail['created_at']) ?> &nbsp;|&nbsp; Aktualizováno: <?= htmlspecialchars($detail['updated_at']) ?>
      <?php if (!empty($detail['last_page'])): ?> &nbsp;|&nbsp; Poslední strana: <?= htmlspecialchars($detail['last_page']) ?><?php endif; ?>
      <?php if (!empty($detail['email'])): ?><br>E-mail: <strong><?= htmlspecialchars($detail['email']) ?></strong><?php endif; ?>
    </div>

    <?php foreach ($BLOCKS as $bk => $bname): ?>
      <?php if (empty($byBlock[$bk])) continue; ?>
      <div class="block-head"><?= htmlspecialchars($bname) ?></div>
      <?php foreach ($byBlock[$bk] as $row):
        $isLong = !empty($row['meta']['long']);
      ?>
        <div class="qa <?= $isLong?'long':'' ?>">
          <div class="qtext"><span class="qid"><?= htmlspecialchars($row['meta']['n']) ?></span><?= htmlspecialchars($row['meta']['text']) ?></div>
          <div class="qval"><?= fmtAnswer($row['val']) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>

    <?php if (!empty($extra)): ?>
      <div class="block-head">Ostatní (UTM apod.)</div>
      <?php foreach ($extra as $k => $v): ?>
        <div class="qa">
          <div class="qtext"><span class="qid"><?= htmlspecialchars($k) ?></span></div>
          <div class="qval"><?= fmtAnswer($v) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (empty($byBlock) && empty($extra)): ?>
      <p style="color:#999">Žádná data.</p>
    <?php endif; ?>
  </div>
</div>
<script>
  document.addEventListener('keydown', function(e){
    if (e.target.matches('input, textarea, select')) return;
    if (e.key === 'Escape') { location.href = '<?= htmlspecialchars(urlWith(['id'=>null])) ?>'; return; }
    if (e.key === 'ArrowLeft')  { var a = document.getElementById('navPrev'); if (a) location.href = a.href; }
    if (e.key === 'ArrowRight') { var a = document.getElementById('navNext'); if (a) location.href = a.href; }
  });
</script>
<?php endif; ?>

</body>
</html>
