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

// ── CSV export ────────────────────────────────────────────────────
if (isset($_GET['csv'])) {
    $rows = $db->query('SELECT * FROM responses ORDER BY updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);

    // Sbíráme všechny klíče z JSON dat pro hlavičku
    $allKeys = [];
    foreach ($rows as $row) {
        $d = json_decode($row['data'], true) ?: [];
        foreach (array_keys($d) as $k) {
            if (!in_array($k, $allKeys)) $allKeys[] = $k;
        }
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dotaznik-' . date('Y-m-d') . '.csv"');
    $f = fopen('php://output', 'w');
    fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM pro Excel

    fputcsv($f, array_merge(['id', 'uuid', 'status', 'last_page', 'email', 'created_at', 'updated_at'], $allKeys), ';');
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

// ── Detail odpovědi ───────────────────────────────────────────────
$detail = null;
if (isset($_GET['id'])) {
    $stmt = $db->prepare('SELECT * FROM responses WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $detail = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ── Statistiky ────────────────────────────────────────────────────
$stats = $db->query("
    SELECT
        COUNT(*) as total,
        SUM(status = 'complete') as complete,
        SUM(status = 'partial')  as partial
    FROM responses
")->fetch(PDO::FETCH_ASSOC);

// ── Seznam odpovědí ───────────────────────────────────────────────
$page   = max(1, (int)($_GET['p'] ?? 1));
$limit  = 30;
$offset = ($page - 1) * $limit;
$total  = (int)$db->query('SELECT COUNT(*) FROM responses')->fetchColumn();
$pages  = (int)ceil($total / $limit);

$rows = $db->query("SELECT id, uuid, status, last_page, email, created_at, updated_at FROM responses ORDER BY updated_at DESC LIMIT $limit OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);

function statusBadge($s) {
    return $s === 'complete'
        ? '<span style="background:#d4edda;color:#155724;padding:2px 8px;border-radius:20px;font-size:12px">✓ kompletní</span>'
        : '<span style="background:#fff3cd;color:#856404;padding:2px 8px;border-radius:20px;font-size:12px">… rozpracovaná</span>';
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
  .topbar { background: #557A53; color: #fff; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; }
  .topbar h1 { margin: 0; font-size: 18px; font-weight: 600; }
  .topbar a { color: rgba(255,255,255,.8); text-decoration: none; font-size: 14px; }
  .topbar a:hover { color: #fff; }
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
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
  th { background: #f7f7f5; text-align: left; padding: 10px 14px; font-size: 13px; color: #555; font-weight: 600; border-bottom: 1px solid #eee; }
  td { padding: 10px 14px; font-size: 14px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #fafaf8; }
  .uuid { font-family: monospace; font-size: 12px; color: #999; }
  .pagination { display: flex; gap: 6px; margin-top: 16px; justify-content: center; }
  .pagination a, .pagination span { padding: 7px 13px; border-radius: 7px; font-size: 14px; text-decoration: none; background: #fff; color: #333; border: 1px solid #ddd; }
  .pagination .cur { background: #557A53; color: #fff; border-color: #557A53; }

  /* Detail */
  .modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; align-items: flex-start; justify-content: center; overflow-y: auto; padding: 40px 16px; z-index: 100; }
  .modal { background: #fff; border-radius: 12px; width: 100%; max-width: 680px; padding: 28px; }
  .modal h2 { margin: 0 0 20px; font-size: 20px; }
  .modal-close { float: right; background: none; border: none; font-size: 22px; cursor: pointer; color: #666; margin-top: -4px; }
  .answer-row { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
  .answer-row:last-child { border-bottom: none; }
  .answer-key { font-size: 13px; font-weight: 600; color: #557A53; width: 100px; flex-shrink: 0; }
  .answer-val { font-size: 14px; color: #333; }
  .meta { font-size: 13px; color: #888; margin-bottom: 16px; line-height: 1.8; }
</style>
</head>
<body>

<div class="topbar">
  <h1>Jaké Brno chcete? — Admin</h1>
  <a href="?logout=1">Odhlásit se</a>
</div>

<div class="wrap">

  <div class="stats">
    <div class="stat"><div class="stat-num"><?= $stats['total'] ?></div><div class="stat-label">celkem odpovědí</div></div>
    <div class="stat"><div class="stat-num"><?= $stats['complete'] ?></div><div class="stat-label">kompletních</div></div>
    <div class="stat"><div class="stat-num"><?= $stats['partial'] ?></div><div class="stat-label">rozpracovaných</div></div>
  </div>

  <div class="toolbar">
    <a class="btn btn-primary" href="?csv=1">⬇ Stáhnout CSV</a>
    <span style="font-size:13px;color:#888">Celkem <?= $total ?> záznamů, strana <?= $page ?> z <?= max(1,$pages) ?></span>
  </div>

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
        <td><a class="btn btn-outline" href="?id=<?= $r['id'] ?>">Detail</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
      <tr><td colspan="7" style="text-align:center;color:#999;padding:32px">Zatím žádné odpovědi.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?>
        <span class="cur"><?= $i ?></span>
      <?php else: ?>
        <a href="?p=<?= $i ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

</div>

<?php if ($detail): ?>
<?php $data = json_decode($detail['data'], true) ?: []; ?>
<div class="modal-bg" onclick="if(event.target===this)location.href='admin.php'">
  <div class="modal">
    <button class="modal-close" onclick="location.href='admin.php'">×</button>
    <h2>Odpověď #<?= $detail['id'] ?></h2>
    <div class="meta">
      <?= statusBadge($detail['status']) ?>
      &nbsp; UUID: <span style="font-family:monospace"><?= htmlspecialchars($detail['uuid']) ?></span><br>
      Vytvořeno: <?= htmlspecialchars($detail['created_at']) ?> &nbsp;|&nbsp; Aktualizováno: <?= htmlspecialchars($detail['updated_at']) ?><br>
      <?php if ($detail['email']): ?>E-mail: <strong><?= htmlspecialchars($detail['email']) ?></strong><?php endif; ?>
    </div>
    <?php foreach ($data as $key => $val): ?>
      <?php if ($key === 'email') continue; ?>
      <div class="answer-row">
        <div class="answer-key"><?= htmlspecialchars($key) ?></div>
        <div class="answer-val"><?= htmlspecialchars(is_array($val) ? implode(', ', $val) : $val) ?></div>
      </div>
    <?php endforeach; ?>
    <?php if (empty($data)): ?>
      <p style="color:#999">Žádná data.</p>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

</body>
</html>
