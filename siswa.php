<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/* ═══════════════════════════════════════════
   EXPORT EXCEL
═══════════════════════════════════════════ */
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $rows = $pdo->query("SELECT id_siswa, nis, nama_siswa, kelas FROM siswa ORDER BY nama_siswa ASC")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="data_siswa_' . date('Ymd') . '.xls"');
    header('Pragma: no-cache');
    echo "\xEF\xBB\xBF";
    echo "<table border='1'>";
    echo "<tr style='background:#0891b2;color:white;font-weight:bold;'>";
    foreach (['No','NIS','Nama Siswa','Kelas'] as $h) echo "<th>$h</th>";
    echo "</tr>";
    foreach ($rows as $i => $r) {
        echo "<tr>";
        echo "<td>" . ($i+1) . "</td>";
        echo "<td>" . htmlspecialchars($r['nis'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($r['nama_siswa']) . "</td>";
        echo "<td>" . htmlspecialchars($r['kelas']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}

/* ═══════════════════════════════════════════
   EXPORT PDF
═══════════════════════════════════════════ */
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $rows = $pdo->query("SELECT * FROM siswa ORDER BY nama_siswa ASC")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html><html lang="id"><head>
    <meta charset="UTF-8">
    <title>Data Siswa - SPK SMART</title>
    <style>
        @page{margin:15mm;size:A4;}
        body{font-family:Arial,sans-serif;font-size:11px;color:#1e293b;}
        .rh{text-align:center;margin-bottom:20px;border-bottom:2px solid #0891b2;padding-bottom:12px;}
        .rh h2{font-size:16px;color:#0891b2;margin:0 0 4px;}
        .rh p{color:#64748b;margin:0;font-size:10px;}
        table{width:100%;border-collapse:collapse;}
        thead th{background:#0891b2;color:white;padding:8px 10px;text-align:left;font-size:10px;}
        tbody td{padding:7px 10px;border-bottom:1px solid #e2e8f0;}
        tbody tr:nth-child(even) td{background:#f8fafc;}
        .ft{margin-top:16px;font-size:10px;color:#94a3b8;text-align:right;}
        @media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}
    </style>
    </head><body>
    <div class="rh">
        <h2>Daftar Data Siswa</h2>
        <p>SPK SMART &mdash; Dicetak: <?= date('d F Y, H:i') ?> WIB</p>
    </div>
    <table>
        <thead><tr><th style="width:32px;">No</th><th>Nama Siswa</th><th>Kelas</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $i => $r): ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><?= htmlspecialchars($r['nama_siswa']) ?></td>
            <td><?= htmlspecialchars($r['kelas']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="ft">Total: <?= count($rows) ?> siswa &mdash; SPK SMART</div>
    <script>window.onload=function(){window.print();}</script>
    </body></html>
    <?php
    exit;
}

/* ═══════════════════════════════════════════
   IMPORT CSV
═══════════════════════════════════════════ */
if (isset($_POST['aksi']) && $_POST['aksi'] === 'import') {
    if (!empty($_FILES['csv_file']['tmp_name'])) {
        $file   = fopen($_FILES['csv_file']['tmp_name'], 'r');
        fgetcsv($file); // skip header
        $ok = 0; $skip = 0;
        $stmt = $pdo->prepare("INSERT IGNORE INTO siswa (nis, nama_siswa, kelas) VALUES (?,?,?)");
        while (($row = fgetcsv($file)) !== false) {
            if (count($row) < 2) { $skip++; continue; }
            // Support: [nis, nama, kelas] atau [nama, kelas]
            if (count($row) >= 3) {
                $res = $stmt->execute([trim($row[0] ?? ''), trim($row[1] ?? ''), trim($row[2] ?? '')]);
            } else {
                $res = $stmt->execute(['', trim($row[0] ?? ''), trim($row[1] ?? '')]);
            }
            $res ? $ok++ : $skip++;
        }
        fclose($file);
        header("Location: siswa.php?success=import&ok=$ok&skip=$skip");
        exit;
    }
    header('Location: siswa.php?success=import_fail');
    exit;
}

/* ═══════════════════════════════════════════
   CRUD — hanya kolom yang ada
═══════════════════════════════════════════ */
if (isset($_POST['aksi']) && $_POST['aksi'] === 'tambah') {
    $nis = trim($_POST['nis'] ?? '');
    // Cek duplikat NIS
    if ($nis !== '') {
        $cek = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE nis = ?");
        $cek->execute([$nis]);
        if ((int)$cek->fetchColumn() > 0) {
            header('Location: siswa.php?success=nis_duplikat&nis=' . urlencode($nis));
            exit;
        }
    }
    $stmt = $pdo->prepare("INSERT INTO siswa (nis, nama_siswa, kelas) VALUES (?,?,?)");
    $stmt->execute([$nis, trim($_POST['nama_siswa']), trim($_POST['kelas'])]);
    header('Location: siswa.php?success=tambah');
    exit;
}

if (isset($_POST['aksi']) && $_POST['aksi'] === 'edit') {
    $nis = trim($_POST['nis'] ?? '');
    // Cek duplikat NIS (kecuali milik dirinya sendiri)
    if ($nis !== '') {
        $cek = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE nis = ? AND id_siswa != ?");
        $cek->execute([$nis, (int)$_POST['id_siswa']]);
        if ((int)$cek->fetchColumn() > 0) {
            header('Location: siswa.php?success=nis_duplikat&nis=' . urlencode($nis));
            exit;
        }
    }
    $stmt = $pdo->prepare("UPDATE siswa SET nis=?, nama_siswa=?, kelas=? WHERE id_siswa=?");
    $stmt->execute([$nis, trim($_POST['nama_siswa']), trim($_POST['kelas']), (int)$_POST['id_siswa']]);
    header('Location: siswa.php?success=edit');
    exit;
}

if (isset($_GET['hapus'])) {
    $pdo->prepare("DELETE FROM siswa WHERE id_siswa = ?")->execute([(int)$_GET['hapus']]);
    header('Location: siswa.php?success=hapus');
    exit;
}

/* ═══════════════════════════════════════════
   FETCH DATA
═══════════════════════════════════════════ */
$search  = trim($_GET['search'] ?? '');
$fKelas  = $_GET['kelas']       ?? '';
$sortBy  = in_array($_GET['sort'] ?? '', ['nama_siswa','kelas','id_siswa']) ? $_GET['sort'] : 'nama_siswa';
$sortDir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = "(nama_siswa LIKE ?)";
    $params[] = "%$search%";
}
if ($fKelas !== '') {
    $where[]  = "kelas = ?";
    $params[] = $fKelas;
}

$whereStr = implode(' AND ', $where);
$perPage  = 10;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;

$stmtC = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE $whereStr");
$stmtC->execute($params);
$totalData = (int)$stmtC->fetchColumn();
$totalPage = (int)ceil($totalData / $perPage);

$stmtD = $pdo->prepare("SELECT * FROM siswa WHERE $whereStr ORDER BY $sortBy $sortDir LIMIT $perPage OFFSET $offset");
$stmtD->execute($params);
$siswaList = $stmtD->fetchAll(PDO::FETCH_ASSOC);

// Stats
$statTotal = (int)$pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
$kelasList = $pdo->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas")->fetchAll(PDO::FETCH_COLUMN);
$statKelas = count($kelasList);

// Jumlah per jenjang
$statX   = (int)$pdo->query("SELECT COUNT(*) FROM siswa WHERE kelas LIKE 'X %' AND kelas NOT LIKE 'XI%' AND kelas NOT LIKE 'XII%'")->fetchColumn();
$statXI  = (int)$pdo->query("SELECT COUNT(*) FROM siswa WHERE kelas LIKE 'XI %' AND kelas NOT LIKE 'XII%'")->fetchColumn();
$statXII = (int)$pdo->query("SELECT COUNT(*) FROM siswa WHERE kelas LIKE 'XII%'")->fetchColumn();

// Edit mode
$editData = null;
if (isset($_GET['edit'])) {
    $se = $pdo->prepare("SELECT * FROM siswa WHERE id_siswa = ?");
    $se->execute([(int)$_GET['edit']]);
    $editData = $se->fetch(PDO::FETCH_ASSOC);
}

/* ═══════════════════════════════════════════
   HELPERS
═══════════════════════════════════════════ */
function initials(string $n): string {
    $w = explode(' ', trim($n));
    return strtoupper(substr($w[0],0,1) . (isset($w[1]) ? substr($w[1],0,1) : ''));
}
$avColors = ['av-cyan','av-teal','av-violet','av-rose','av-amber','av-green'];
function avColor(int $id, array $c): string { return $c[$id % count($c)]; }

function sortLink(string $col, string $label, string $cur, string $curDir, array $get): string {
    $dir  = ($cur === $col && $curDir === 'asc') ? 'desc' : 'asc';
    $icon = $cur === $col ? ($curDir === 'asc' ? '↑' : '↓') : '↕';
    $q    = http_build_query(array_merge($get, ['sort'=>$col,'dir'=>$dir,'page'=>1]));
    $cls  = $cur === $col ? 'sort-link active' : 'sort-link';
    return "<a href='siswa.php?$q' class='$cls'>$label <span class='sort-icon'>$icon</span></a>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Data Siswa · SPK SMART</title>

<link rel="stylesheet" href="assets/style.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
/* ════════════════════════════════════
   BASE
════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
.main-content{
    font-family:'Plus Jakarta Sans',sans-serif;
    padding:30px 28px;
    color:#e2e8f0;
    min-height:100vh;
}

/* ════════════════════════════════════
   PAGE HEADER
════════════════════════════════════ */
.page-header{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:26px;
    animation:fadeUp .5s ease both;
}
.ph-left{display:flex;align-items:center;gap:16px;}
.ph-icon{
    width:54px;height:54px;
    background:linear-gradient(135deg,#0891b2,#0369a1);
    border-radius:17px;
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 0 28px rgba(8,145,178,.45);
    flex-shrink:0;position:relative;overflow:hidden;
}
.ph-icon::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.15),transparent);}
.ph-icon i{font-size:21px;color:white;position:relative;z-index:1;}
.ph-title{
    font-size:23px;font-weight:800;
    background:linear-gradient(90deg,#e2e8f0 60%,#64748b);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.ph-sub{font-size:12px;color:#475569;margin-top:3px;letter-spacing:.02em;}

.btn-group{display:flex;align-items:center;gap:8px;}

.btn-primary{
    display:flex;align-items:center;gap:7px;
    background:linear-gradient(135deg,#0891b2,#0369a1);
    color:white;border:none;border-radius:11px;
    padding:10px 18px;font-size:13px;font-weight:700;
    font-family:'Plus Jakarta Sans',sans-serif;
    cursor:pointer;text-decoration:none;
    box-shadow:0 4px 16px rgba(8,145,178,.35);
    transition:all .2s;position:relative;overflow:hidden;
}
.btn-primary::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.12),transparent);pointer-events:none;}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 22px rgba(8,145,178,.5);}
.btn-primary i{font-size:12px;}

.btn-secondary{
    display:flex;align-items:center;gap:7px;
    background:rgba(255,255,255,.05);
    color:#94a3b8;border:1px solid rgba(255,255,255,.10);
    border-radius:11px;padding:10px 16px;
    font-size:13px;font-weight:600;
    font-family:'Plus Jakarta Sans',sans-serif;
    cursor:pointer;text-decoration:none;transition:all .2s;
}
.btn-secondary:hover{background:rgba(255,255,255,.10);color:#e2e8f0;border-color:rgba(255,255,255,.18);}
.btn-secondary i{font-size:12px;}

.export-wrap{position:relative;}
.export-dropdown{
    display:none;position:absolute;right:0;top:calc(100% + 8px);
    background:#0f2137;border:1px solid rgba(255,255,255,.10);
    border-radius:13px;padding:6px;min-width:190px;
    box-shadow:0 16px 40px rgba(0,0,0,.5);z-index:200;
    animation:dropIn .18s ease;
}
.export-dropdown.open{display:block;}
.export-item{
    display:flex;align-items:center;gap:10px;
    padding:10px 13px;border-radius:9px;
    font-size:13px;font-weight:600;color:#cbd5e1;
    text-decoration:none;cursor:pointer;transition:background .15s;
}
.export-item:hover{background:rgba(255,255,255,.07);color:#e2e8f0;}
.export-item i{width:16px;text-align:center;font-size:13px;}
.export-item.xl i{color:#22c55e;}
.export-item.pdf i{color:#f87171;}

/* ════════════════════════════════════
   STAT CARDS
════════════════════════════════════ */
.stats-row{
    display:grid;grid-template-columns:repeat(4,1fr);
    gap:13px;margin-bottom:22px;
    animation:fadeUp .5s .06s ease both;
}
.stat{
    background:rgba(255,255,255,.03);
    border:1px solid rgba(255,255,255,.06);
    border-radius:17px;padding:18px 20px;
    display:flex;align-items:center;gap:14px;
    transition:all .2s;cursor:default;
    position:relative;overflow:hidden;
}
.stat::before{
    content:'';position:absolute;inset:0;
    background:radial-gradient(circle at 100% 0%,var(--glow) 0%,transparent 65%);
    opacity:0;transition:opacity .3s;
}
.stat:hover::before{opacity:1;}
.stat:hover{border-color:rgba(255,255,255,.12);transform:translateY(-2px);}

.stat-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-icon i{font-size:17px;}
.stat-label{font-size:11px;font-weight:600;letter-spacing:.10em;text-transform:uppercase;color:#475569;margin-bottom:5px;}
.stat-value{font-size:26px;font-weight:800;font-family:'JetBrains Mono',monospace;line-height:1;}

.stat.s-cyan {--glow:rgba(8,145,178,.15);}
.stat.s-cyan .stat-icon{background:rgba(8,145,178,.15);}
.stat.s-cyan .stat-icon i,.stat.s-cyan .stat-value{color:#22d3ee;}

.stat.s-green{--glow:rgba(34,197,94,.15);}
.stat.s-green .stat-icon{background:rgba(34,197,94,.15);}
.stat.s-green .stat-icon i,.stat.s-green .stat-value{color:#4ade80;}

.stat.s-amber{--glow:rgba(245,158,11,.15);}
.stat.s-amber .stat-icon{background:rgba(245,158,11,.15);}
.stat.s-amber .stat-icon i,.stat.s-amber .stat-value{color:#fbbf24;}

.stat.s-violet{--glow:rgba(139,92,246,.15);}
.stat.s-violet .stat-icon{background:rgba(139,92,246,.15);}
.stat.s-violet .stat-icon i,.stat.s-violet .stat-value{color:#c4b5fd;}

/* ════════════════════════════════════
   TOOLBAR
════════════════════════════════════ */
.toolbar{
    display:flex;align-items:center;gap:10px;
    margin-bottom:16px;
    animation:fadeUp .5s .12s ease both;
}
.search-box{
    display:flex;align-items:center;gap:9px;flex:1;
    background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.08);
    border-radius:12px;padding:10px 14px;transition:all .2s;
}
.search-box:focus-within{
    border-color:rgba(8,145,178,.5);
    background:rgba(8,145,178,.06);
    box-shadow:0 0 0 3px rgba(8,145,178,.10);
}
.search-box i{color:#334155;font-size:13px;flex-shrink:0;}
.search-box input{
    flex:1;background:transparent;border:none;outline:none;
    color:#e2e8f0;font-size:13px;font-family:'Plus Jakarta Sans',sans-serif;
}
.search-box input::placeholder{color:#334155;}

.sel{
    background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.08);
    border-radius:12px;padding:10px 13px;
    color:#94a3b8;font-size:13px;
    font-family:'Plus Jakarta Sans',sans-serif;
    outline:none;cursor:pointer;transition:border-color .2s;
    appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 12px center;
    padding-right:32px;
}
.sel:focus{border-color:rgba(8,145,178,.45);}
.sel option{background:#1e293b;color:#e2e8f0;}

.filter-badge{
    display:flex;align-items:center;gap:6px;
    background:rgba(8,145,178,.12);border:1px solid rgba(8,145,178,.25);
    border-radius:8px;padding:5px 11px;
    font-size:12px;font-weight:600;color:#22d3ee;
    text-decoration:none;transition:background .2s;
}
.filter-badge:hover{background:rgba(8,145,178,.22);}
.filter-badge i{font-size:11px;}

/* ════════════════════════════════════
   TABLE CARD
════════════════════════════════════ */
.table-card{
    background:rgba(255,255,255,.025);
    border:1px solid rgba(255,255,255,.07);
    border-radius:20px;overflow:hidden;
    animation:fadeUp .5s .18s ease both;
}
.table-top{
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 20px 14px;
    border-bottom:1px solid rgba(255,255,255,.05);
}
.table-top-left{font-size:12px;color:#475569;display:flex;align-items:center;gap:8px;}
.table-top-left span{
    background:rgba(255,255,255,.06);border-radius:6px;
    padding:3px 9px;font-family:'JetBrains Mono',monospace;
    font-size:11px;color:#94a3b8;
}
.table-scroll{overflow-x:auto;}

table{width:100%;border-collapse:collapse;min-width:500px;}

thead th{
    background:rgba(0,0,0,.25);padding:12px 16px;
    font-size:10px;font-weight:700;
    letter-spacing:.12em;text-transform:uppercase;
    color:#475569;text-align:left;white-space:nowrap;
}
thead th:last-child{text-align:center;}

.sort-link{
    display:inline-flex;align-items:center;gap:5px;
    color:#475569;text-decoration:none;transition:color .15s;
}
.sort-link:hover,.sort-link.active{color:#22d3ee;}
.sort-icon{font-size:10px;opacity:.6;}
.sort-link.active .sort-icon{opacity:1;color:#22d3ee;}

tbody tr{border-bottom:1px solid rgba(255,255,255,.035);transition:background .15s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:rgba(255,255,255,.035);}
tbody td{padding:13px 16px;font-size:13px;color:#cbd5e1;vertical-align:middle;}

.row-num{font-family:'JetBrains Mono',monospace;font-size:11px;color:#334155;width:44px;text-align:center;}

.avatar{
    width:38px;height:38px;border-radius:11px;
    display:flex;align-items:center;justify-content:center;
    font-size:13px;font-weight:700;flex-shrink:0;letter-spacing:.02em;
}
.av-cyan  {background:rgba(8,145,178,.18);color:#22d3ee;}
.av-teal  {background:rgba(20,184,166,.18);color:#2dd4bf;}
.av-violet{background:rgba(139,92,246,.18);color:#c4b5fd;}
.av-rose  {background:rgba(244,63,94,.18); color:#fb7185;}
.av-amber {background:rgba(245,158,11,.18);color:#fbbf24;}
.av-green {background:rgba(34,197,94,.18); color:#4ade80;}

.name-cell{display:flex;align-items:center;gap:11px;}
.name-main{font-weight:700;color:#e2e8f0;font-size:13px;line-height:1.3;}
.name-sub{font-size:11px;color:#475569;font-family:'JetBrains Mono',monospace;margin-top:2px;}

.kelas-pill{
    display:inline-flex;align-items:center;
    padding:4px 11px;border-radius:20px;
    font-size:11px;font-weight:700;
    background:rgba(8,145,178,.12);color:#22d3ee;
    border:1px solid rgba(8,145,178,.22);white-space:nowrap;
}

.action-wrap{display:flex;align-items:center;justify-content:center;gap:6px;}
.act{
    width:31px;height:31px;border-radius:9px;border:none;cursor:pointer;
    display:flex;align-items:center;justify-content:center;transition:all .18s;
}
.act i{font-size:12px;}
.act-edit{background:rgba(8,145,178,.14);color:#22d3ee;}
.act-edit:hover{background:rgba(8,145,178,.30);transform:scale(1.08);}
.act-del{background:rgba(239,68,68,.11);color:#f87171;}
.act-del:hover{background:rgba(239,68,68,.28);transform:scale(1.08);}

.empty{padding:60px 20px;text-align:center;color:#334155;}
.empty i{font-size:38px;margin-bottom:12px;display:block;color:#1e293b;}
.empty p{font-size:13px;}

/* ════════════════════════════════════
   PAGINATION
════════════════════════════════════ */
.pagination{
    display:flex;align-items:center;justify-content:space-between;
    padding:15px 20px;border-top:1px solid rgba(255,255,255,.05);
}
.pg-info{font-size:12px;color:#334155;}
.pg-btns{display:flex;gap:5px;}
.pg-btn{
    min-width:32px;height:32px;padding:0 9px;border-radius:8px;
    background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);
    color:#64748b;font-size:12px;font-weight:600;
    cursor:pointer;display:flex;align-items:center;justify-content:center;
    text-decoration:none;font-family:'JetBrains Mono',monospace;transition:all .18s;
}
.pg-btn:hover {background:rgba(8,145,178,.15);border-color:rgba(8,145,178,.30);color:#22d3ee;}
.pg-btn.active{background:rgba(8,145,178,.22);border-color:rgba(8,145,178,.40);color:#22d3ee;}
.pg-btn.disabled{opacity:.3;pointer-events:none;}

/* ════════════════════════════════════
   MODALS / OVERLAY
════════════════════════════════════ */
.overlay{
    display:none;position:fixed;inset:0;z-index:800;
    background:rgba(0,0,0,.70);backdrop-filter:blur(5px);
    align-items:center;justify-content:center;padding:20px;
}
.overlay.open{display:flex;animation:overlayIn .2s ease;}

.modal{
    background:#0b1d33;border:1px solid rgba(8,145,178,.18);
    border-radius:22px;padding:26px;
    width:100%;max-width:520px;
    box-shadow:0 28px 70px rgba(0,0,0,.65);
    animation:modalIn .25s cubic-bezier(.34,1.4,.64,1);
}

.modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;}
.modal-ttl{display:flex;align-items:center;gap:10px;font-size:16px;font-weight:800;color:#e2e8f0;}
.ttl-icon{
    width:36px;height:36px;border-radius:10px;
    background:rgba(8,145,178,.18);
    display:flex;align-items:center;justify-content:center;
}
.ttl-icon i{font-size:15px;color:#22d3ee;}
.modal-close{
    width:32px;height:32px;border-radius:9px;
    background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);
    color:#64748b;display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:all .2s;font-size:13px;border:none;
}
.modal-close:hover{background:rgba(255,255,255,.12);color:#e2e8f0;}

.fg{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.fg-full{grid-column:1/-1;}
.fl{font-size:10px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#334155;margin-bottom:6px;display:block;}
.fi{
    width:100%;background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.08);
    border-radius:10px;padding:10px 13px;
    color:#e2e8f0;font-size:13px;
    font-family:'Plus Jakarta Sans',sans-serif;
    outline:none;transition:all .2s;
}
.fi:focus{border-color:rgba(8,145,178,.55);background:rgba(8,145,178,.07);box-shadow:0 0 0 3px rgba(8,145,178,.12);}
.fi::placeholder{color:#1e3a52;}
select.fi option{background:#1e293b;color:#e2e8f0;}

.modal-foot{
    display:flex;gap:9px;justify-content:flex-end;
    margin-top:22px;padding-top:18px;
    border-top:1px solid rgba(255,255,255,.06);
}
.btn-cancel{
    padding:10px 18px;background:rgba(255,255,255,.05);
    border:1px solid rgba(255,255,255,.08);border-radius:10px;
    color:#64748b;font-size:13px;font-weight:700;
    font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:all .2s;
}
.btn-cancel:hover{background:rgba(255,255,255,.10);color:#e2e8f0;}
.btn-save{
    display:flex;align-items:center;gap:7px;padding:10px 22px;
    background:linear-gradient(135deg,#0891b2,#0369a1);
    border:none;border-radius:10px;color:white;
    font-size:13px;font-weight:700;
    font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;
    box-shadow:0 4px 14px rgba(8,145,178,.35);transition:all .2s;
}
.btn-save:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(8,145,178,.5);}
.btn-save i{font-size:12px;}

/* Import modal */
.import-zone{
    border:2px dashed rgba(255,255,255,.10);border-radius:14px;
    padding:36px 20px;text-align:center;cursor:pointer;
    transition:all .2s;position:relative;
}
.import-zone:hover,.import-zone.drag{border-color:rgba(8,145,178,.50);background:rgba(8,145,178,.06);}
.import-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.import-zone i{font-size:34px;color:#1e3a52;margin-bottom:10px;display:block;transition:color .2s;}
.import-zone:hover i,.import-zone.drag i{color:#22d3ee;}
.iz-title{font-size:14px;font-weight:700;color:#64748b;margin-bottom:5px;}
.iz-sub{font-size:12px;color:#334155;}
.iz-fname{margin-top:10px;font-size:12px;font-weight:600;color:#22d3ee;display:none;}

.import-info{
    background:rgba(8,145,178,.08);border:1px solid rgba(8,145,178,.18);
    border-radius:11px;padding:13px 15px;margin-top:14px;
    font-size:12px;color:#64748b;line-height:1.7;
}
.import-info strong{color:#22d3ee;}

/* Confirm delete */
.confirm-box{
    background:#0b1d33;border:1px solid rgba(239,68,68,.22);
    border-radius:20px;padding:28px 24px;
    width:100%;max-width:360px;text-align:center;
    box-shadow:0 28px 70px rgba(0,0,0,.65);
    animation:modalIn .25s cubic-bezier(.34,1.4,.64,1);
}
.confirm-del-icon{
    width:56px;height:56px;border-radius:50%;
    background:rgba(239,68,68,.12);
    display:flex;align-items:center;justify-content:center;margin:0 auto 14px;
}
.confirm-del-icon i{font-size:24px;color:#f87171;}
.confirm-ttl{font-size:17px;font-weight:800;color:#e2e8f0;margin-bottom:8px;}
.confirm-msg{font-size:13px;color:#475569;line-height:1.65;margin-bottom:22px;}
.btn-del{
    display:inline-flex;align-items:center;gap:7px;
    padding:10px 22px;background:linear-gradient(135deg,#dc2626,#b91c1c);
    border:none;border-radius:10px;color:white;
    font-size:13px;font-weight:700;
    font-family:'Plus Jakarta Sans',sans-serif;
    text-decoration:none;cursor:pointer;
    box-shadow:0 4px 14px rgba(220,38,38,.35);transition:all .2s;
}
.btn-del:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(220,38,38,.5);}

/* Toast */
.toast{
    position:fixed;bottom:26px;left:50%;
    transform:translateX(-50%) translateY(80px);
    color:white;padding:13px 22px;border-radius:13px;
    font-family:'Plus Jakarta Sans',sans-serif;
    font-size:13px;font-weight:700;
    display:flex;align-items:center;gap:9px;
    transition:transform .35s cubic-bezier(.34,1.56,.64,1);
    z-index:9999;pointer-events:none;min-width:240px;
}
.toast.show{transform:translateX(-50%) translateY(0);}
.toast i{font-size:14px;}

/* Animations */
@keyframes fadeUp{from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);}}
@keyframes overlayIn{from{opacity:0;}to{opacity:1;}}
@keyframes dropIn{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}
@keyframes modalIn{from{opacity:0;transform:scale(.95) translateY(10px);}to{opacity:1;transform:scale(1) translateY(0);}}


/* ═══════════════════════════════════════
   MODERN LIGHT MODE REBUILD - SISWA.PHP
   TEMPATKAN PALING BAWAH CSS
═══════════════════════════════════════ */

/* ROOT LIGHT */
body.light-mode,
body.light,
html.light,
[data-theme="light"]{
    background:#f1f5f9 !important;
    color:#0f172a !important;
}

/* MAIN CONTENT */
body.light-mode .main-content,
body.light .main-content,
html.light .main-content,
[data-theme="light"] .main-content{
    background:#f8fafc !important;
    color:#0f172a !important;
}

/* PAGE HEADER */
body.light-mode .ph-title,
body.light .ph-title,
html.light .ph-title,
[data-theme="light"] .ph-title{
    background:none !important;
    -webkit-text-fill-color:#0f172a !important;
    color:#0f172a !important;
}

body.light-mode .ph-sub,
body.light .ph-sub,
html.light .ph-sub,
[data-theme="light"] .ph-sub{
    color:#64748b !important;
}

/* BUTTON */
body.light-mode .btn-secondary,
body.light .btn-secondary,
html.light .btn-secondary,
[data-theme="light"] .btn-secondary{
    background:#ffffff !important;
    border:1px solid #dbeafe !important;
    color:#334155 !important;
    box-shadow:0 2px 10px rgba(15,23,42,.04);
}

body.light-mode .btn-secondary:hover,
body.light .btn-secondary:hover,
html.light .btn-secondary:hover,
[data-theme="light"] .btn-secondary:hover{
    background:#f8fafc !important;
    color:#0891b2 !important;
    transform:translateY(-1px);
}

body.light-mode .btn-primary,
body.light .btn-primary,
html.light .btn-primary,
[data-theme="light"] .btn-primary{
    box-shadow:0 8px 20px rgba(8,145,178,.18) !important;
}

/* EXPORT */
body.light-mode .export-dropdown,
body.light .export-dropdown,
html.light .export-dropdown,
[data-theme="light"] .export-dropdown{
    background:#ffffff !important;
    border:1px solid #e2e8f0 !important;
    box-shadow:0 12px 30px rgba(15,23,42,.10);
}

body.light-mode .export-item,
body.light .export-item,
html.light .export-item,
[data-theme="light"] .export-item{
    color:#334155 !important;
}

body.light-mode .export-item:hover,
body.light .export-item:hover,
html.light .export-item:hover,
[data-theme="light"] .export-item:hover{
    background:#f8fafc !important;
    color:#0891b2 !important;
}

/* STATS */
body.light-mode .stat,
body.light .stat,
html.light .stat,
[data-theme="light"] .stat{
    background:#ffffff !important;
    border:1px solid #e2e8f0 !important;
    box-shadow:0 4px 14px rgba(15,23,42,.05);
}

body.light-mode .stat:hover,
body.light .stat:hover,
html.light .stat:hover,
[data-theme="light"] .stat:hover{
    transform:translateY(-3px);
    box-shadow:0 12px 28px rgba(15,23,42,.08);
}

body.light-mode .stat-label,
body.light .stat-label,
html.light .stat-label,
[data-theme="light"] .stat-label{
    color:#64748b !important;
}

/* SEARCH */
body.light-mode .search-box,
body.light .search-box,
html.light .search-box,
[data-theme="light"] .search-box{
    background:#ffffff !important;
    border:1px solid #cbd5e1 !important;
    box-shadow:0 2px 10px rgba(15,23,42,.03);
}

body.light-mode .search-box:focus-within,
body.light .search-box:focus-within,
html.light .search-box:focus-within,
[data-theme="light"] .search-box:focus-within{
    border-color:#38bdf8 !important;
    box-shadow:0 0 0 4px rgba(56,189,248,.12);
}

body.light-mode .search-box i,
body.light .search-box i,
html.light .search-box i,
[data-theme="light"] .search-box i{
    color:#64748b !important;
}

body.light-mode .search-box input,
body.light .search-box input,
html.light .search-box input,
[data-theme="light"] .search-box input{
    color:#0f172a !important;
}

body.light-mode .search-box input::placeholder,
body.light .search-box input::placeholder,
html.light .search-box input::placeholder,
[data-theme="light"] .search-box input::placeholder{
    color:#94a3b8 !important;
}

/* SELECT */
body.light-mode .sel,
body.light .sel,
html.light .sel,
[data-theme="light"] .sel{
    background:#ffffff !important;
    border:1px solid #cbd5e1 !important;
    color:#334155 !important;
    box-shadow:0 2px 10px rgba(15,23,42,.03);
}

body.light-mode .sel option,
body.light .sel option,
html.light .sel option,
[data-theme="light"] .sel option{
    background:#ffffff !important;
    color:#0f172a !important;
}

/* TABLE */
body.light-mode .table-card,
body.light .table-card,
html.light .table-card,
[data-theme="light"] .table-card{
    background:#ffffff !important;
    border:1px solid #e2e8f0 !important;
    box-shadow:0 10px 30px rgba(15,23,42,.05);
}

body.light-mode .table-top,
body.light .table-top,
html.light .table-top,
[data-theme="light"] .table-top{
    border-bottom:1px solid #e2e8f0 !important;
}

body.light-mode thead th,
body.light thead th,
html.light thead th,
[data-theme="light"] thead th{
    background:#f8fafc !important;
    color:#475569 !important;
    border-bottom:1px solid #e2e8f0;
}

body.light-mode tbody tr,
body.light tbody tr,
html.light tbody tr,
[data-theme="light"] tbody tr{
    border-bottom:1px solid #f1f5f9 !important;
}

body.light-mode tbody tr:hover,
body.light tbody tr:hover,
html.light tbody tr:hover,
[data-theme="light"] tbody tr:hover{
    background:#f8fafc !important;
}

body.light-mode tbody td,
body.light tbody td,
html.light tbody td,
[data-theme="light"] tbody td{
    color:#334155 !important;
}

body.light-mode .name-main,
body.light .name-main,
html.light .name-main,
[data-theme="light"] .name-main{
    color:#0f172a !important;
    font-weight:700;
}

body.light-mode .name-sub,
body.light .name-sub,
html.light .name-sub,
[data-theme="light"] .name-sub{
    color:#64748b !important;
}

body.light-mode .row-num,
body.light .row-num,
html.light .row-num,
[data-theme="light"] .row-num{
    color:#94a3b8 !important;
}

/* PAGINATION */
body.light-mode .pagination,
body.light .pagination,
html.light .pagination,
[data-theme="light"] .pagination{
    border-top:1px solid #e2e8f0 !important;
}

body.light-mode .pg-btn,
body.light .pg-btn,
html.light .pg-btn,
[data-theme="light"] .pg-btn{
    background:#ffffff !important;
    border:1px solid #cbd5e1 !important;
    color:#334155 !important;
}

body.light-mode .pg-btn:hover,
body.light .pg-btn:hover,
html.light .pg-btn:hover,
[data-theme="light"] .pg-btn:hover{
    background:#ecfeff !important;
    color:#0891b2 !important;
}

body.light-mode .pg-btn.active,
body.light .pg-btn.active,
html.light .pg-btn.active,
[data-theme="light"] .pg-btn.active{
    background:#0891b2 !important;
    border-color:#0891b2 !important;
    color:#ffffff !important;
}

/* MODAL */
body.light-mode .modal,
body.light .modal,
html.light .modal,
[data-theme="light"] .modal,
body.light-mode .confirm-box,
body.light .confirm-box,
html.light .confirm-box,
[data-theme="light"] .confirm-box{
    background:#ffffff !important;
    border:1px solid #e2e8f0 !important;
    box-shadow:0 20px 50px rgba(15,23,42,.12);
}

body.light-mode .modal-ttl,
body.light .modal-ttl,
html.light .modal-ttl,
[data-theme="light"] .modal-ttl{
    color:#0f172a !important;
}

body.light-mode .confirm-msg,
body.light .confirm-msg,
html.light .confirm-msg,
[data-theme="light"] .confirm-msg{
    color:#64748b !important;
}

/* FORM */
body.light-mode .fl,
body.light .fl,
html.light .fl,
[data-theme="light"] .fl{
    color:#475569 !important;
}

body.light-mode .fi,
body.light .fi,
html.light .fi,
[data-theme="light"] .fi{
    background:#ffffff !important;
    border:1px solid #cbd5e1 !important;
    color:#0f172a !important;
}

body.light-mode .fi:focus,
body.light .fi:focus,
html.light .fi:focus,
[data-theme="light"] .fi:focus{
    border-color:#38bdf8 !important;
    box-shadow:0 0 0 4px rgba(56,189,248,.12);
}

body.light-mode .fi::placeholder,
body.light .fi::placeholder,
html.light .fi::placeholder,
[data-theme="light"] .fi::placeholder{
    color:#94a3b8 !important;
}

/* IMPORT */
body.light-mode .import-zone,
body.light .import-zone,
html.light .import-zone,
[data-theme="light"] .import-zone{
    background:#ffffff !important;
    border-color:#cbd5e1 !important;
}

body.light-mode .import-zone:hover,
body.light .import-zone:hover,
html.light .import-zone:hover,
[data-theme="light"] .import-zone:hover{
    background:#ecfeff !important;
    border-color:#67e8f9 !important;
}

/* EMPTY */
body.light-mode .empty,
body.light .empty,
html.light .empty,
[data-theme="light"] .empty{
    color:#64748b !important;
}

body.light-mode .empty i,
body.light .empty i,
html.light .empty i,
[data-theme="light"] .empty i{
    color:#cbd5e1 !important;
}



</style>
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="ph-left">
            <div class="ph-icon"><i class="fa-solid fa-users"></i></div>
            <div>
                <div class="ph-title">Data Siswa</div>
                <div class="ph-sub">Manajemen &amp; monitoring data seluruh siswa</div>
            </div>
        </div>
        <div class="btn-group">
            <button class="btn-secondary" onclick="openOverlay('importOverlay')">
                <i class="fa-solid fa-file-arrow-up"></i> Import
            </button>
            <div class="export-wrap">
                <button class="btn-secondary" onclick="toggleExport()">
                    <i class="fa-solid fa-file-arrow-down"></i> Export
                    <i class="fa-solid fa-chevron-down" style="font-size:10px;margin-left:2px;"></i>
                </button>
                <div class="export-dropdown" id="exportDrop">
                    <a href="siswa.php?export=excel" class="export-item xl" target="_blank">
                        <i class="fa-solid fa-file-excel"></i> Export Excel (.xls)
                    </a>
                    <a href="siswa.php?export=pdf" class="export-item pdf" target="_blank">
                        <i class="fa-solid fa-file-pdf"></i> Export PDF (Print)
                    </a>
                </div>
            </div>
            <button class="btn-primary" onclick="openModal('tambah')">
                <i class="fa-solid fa-user-plus"></i> Tambah Siswa
            </button>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="stats-row">
        <div class="stat s-cyan">
            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div>
                <div class="stat-label">Total Siswa</div>
                <div class="stat-value"><?= $statTotal ?></div>
            </div>
        </div>
        <div class="stat s-green">
            <div class="stat-icon"><i class="fa-solid fa-graduation-cap"></i></div>
            <div>
                <div class="stat-label">Kelas 7</div>
                <div class="stat-value"><?= $statX ?></div>
            </div>
        </div>
        <div class="stat s-amber">
            <div class="stat-icon"><i class="fa-solid fa-book-open"></i></div>
            <div>
                <div class="stat-label">Kelas 8</div>
                <div class="stat-value"><?= $statXI ?></div>
            </div>
        </div>
        <div class="stat s-violet">
            <div class="stat-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
            <div>
                <div class="stat-label">Kelas 9 </div>
                <div class="stat-value"><?= $statXII ?></div>
            </div>
        </div>
    </div>

    <!-- TOOLBAR -->
    <form method="GET" action="siswa.php" id="filterForm">
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
        <input type="hidden" name="dir"  value="<?= htmlspecialchars($sortDir) ?>">
        <div class="toolbar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="search"
                       value="<?= htmlspecialchars($search) ?>"
                       placeholder="Cari nama siswa..."
                       oninput="debounce()">
            </div>
            <select name="kelas" class="sel" onchange="this.form.submit()">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelasList as $k): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= $fKelas===$k?'selected':'' ?>>
                    <?= htmlspecialchars($k) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if ($search || $fKelas): ?>
            <a href="siswa.php" class="filter-badge">
                <i class="fa-solid fa-filter-circle-xmark"></i> Reset
            </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- TABLE -->
    <div class="table-card">
        <div class="table-top">
            <div class="table-top-left">
                Menampilkan <span><?= $totalData ?> siswa</span>
                <?= ($search||$fKelas) ? '<span style="color:#22d3ee;background:rgba(8,145,178,.12);border-color:rgba(8,145,178,.2);">difilter</span>' : '' ?>
            </div>
            <div style="font-size:11px;color:#334155;font-family:'JetBrains Mono',monospace;">
                Hal <?= $page ?> / <?= max(1,$totalPage) ?>
            </div>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th style="width:44px;text-align:center;">#</th>
                        <th>NIS</th>
                        <th><?= sortLink('nama_siswa','Nama Siswa',$sortBy,$sortDir,$_GET) ?></th>
                        <th><?= sortLink('kelas','Kelas',$sortBy,$sortDir,$_GET) ?></th>
                        <th style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($siswaList)): ?>
                <tr><td colspan="5">
                    <div class="empty">
                        <i class="fa-solid fa-users-slash"></i>
                        <p>Tidak ada data siswa<?= $search ? " untuk &ldquo;".htmlspecialchars($search)."&rdquo;" : '' ?></p>
                    </div>
                </td></tr>
                <?php else: ?>
                <?php foreach ($siswaList as $i => $s):
                    $no    = $offset + $i + 1;
                    $init  = initials($s['nama_siswa']);
                    $avCls = avColor($s['id_siswa'], $avColors);
                ?>
                <tr>
                    <td class="row-num"><?= str_pad($no,2,'0',STR_PAD_LEFT) ?></td>
                    <td>
                        <?php if (!empty($s['nis'])): ?>
                        <span style="
                            font-family:'JetBrains Mono',monospace;font-size:12px;font-weight:600;
                            background:rgba(139,92,246,.12);color:#c4b5fd;
                            border:1px solid rgba(139,92,246,.22);
                            border-radius:8px;padding:4px 10px;white-space:nowrap;">
                            <?= htmlspecialchars($s['nis']) ?>
                        </span>
                        <?php else: ?>
                        <span style="font-size:11px;color:#334155;font-style:italic;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="name-cell">
                            <div class="avatar <?= $avCls ?>"><?= $init ?></div>
                            <div>
                                <div class="name-main"><?= htmlspecialchars($s['nama_siswa']) ?></div>
                                <div class="name-sub">ID #<?= str_pad($s['id_siswa'],4,'0',STR_PAD_LEFT) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="kelas-pill"><?= htmlspecialchars($s['kelas']) ?></span></td>
                    <td>
                        <div class="action-wrap">
                            <button class="act act-edit"
                                onclick="openModal('edit', <?= htmlspecialchars(json_encode($s)) ?>)"
                                title="Edit siswa">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button class="act act-del"
                                onclick="openConfirm(<?= $s['id_siswa'] ?>, '<?= htmlspecialchars(addslashes($s['nama_siswa'])) ?>')"
                                title="Hapus siswa">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($totalPage > 1): ?>
        <div class="pagination">
            <div class="pg-info"><?= $offset+1 ?>–<?= min($offset+$perPage,$totalData) ?> dari <?= $totalData ?> siswa</div>
            <div class="pg-btns">
                <?php
                $q = array_merge($_GET,['page'=>1]);
                echo "<a href='siswa.php?".http_build_query($q)."' class='pg-btn ".($page<=1?'disabled':'')."'>«</a>";
                $q['page']=max(1,$page-1);
                echo "<a href='siswa.php?".http_build_query($q)."' class='pg-btn ".($page<=1?'disabled':'')."'>‹</a>";
                for($p=max(1,$page-2);$p<=min($totalPage,$page+2);$p++){
                    $q['page']=$p;
                    echo "<a href='siswa.php?".http_build_query($q)."' class='pg-btn ".($p===$page?'active':'')."'>$p</a>";
                }
                $q['page']=min($totalPage,$page+1);
                echo "<a href='siswa.php?".http_build_query($q)."' class='pg-btn ".($page>=$totalPage?'disabled':'')."'>›</a>";
                $q['page']=$totalPage;
                echo "<a href='siswa.php?".http_build_query($q)."' class='pg-btn ".($page>=$totalPage?'disabled':'')."'>»</a>";
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</main>

<!-- MODAL TAMBAH / EDIT -->
<div class="overlay" id="formOverlay">
    <div class="modal">
        <div class="modal-head">
            <div class="modal-ttl">
                <div class="ttl-icon"><i class="fa-solid fa-user-plus" id="mIcon"></i></div>
                <span id="mTitle">Tambah Siswa Baru</span>
            </div>
            <button class="modal-close" onclick="closeOverlay('formOverlay')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="siswa.php">
            <input type="hidden" name="aksi"     id="fAksi" value="tambah">
            <input type="hidden" name="id_siswa" id="fId"   value="">
            <div class="fg">
                <div class="fg-full">
                    <label class="fl">NIS (Nomor Induk Siswa)</label>
                    <input class="fi" type="text" name="nis" id="fNis"
                           placeholder="Contoh: 2024001"
                           maxlength="20"
                           style="font-family:'JetBrains Mono',monospace;letter-spacing:.05em;">
                    <div style="font-size:11px;color:#334155;margin-top:5px;">
                        <i class="fa-solid fa-circle-info" style="color:#475569;"></i>
                        Kosongkan jika belum ada NIS
                    </div>
                </div>
                <div class="fg-full">
                    <label class="fl">Nama Lengkap *</label>
                    <input class="fi" type="text" name="nama_siswa" id="fNama"
                           placeholder="Nama lengkap siswa" required>
                </div>
                <div class="fg-full">
                    <label class="fl">Kelas *</label>
                    <select class="fi" name="kelas" id="fKelas" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php
                        $ko=['8A','8B','8C','8D',
                             '8E','8F','8G','8H',
                             '8I'];
                        foreach($ko as $v) echo "<option value='$v'>$v</option>";
                        ?>
                    </select>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeOverlay('formOverlay')">Batal</button>
                <button type="submit" class="btn-save">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span id="btnTxt">Simpan Siswa</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL IMPORT CSV -->
<div class="overlay" id="importOverlay">
    <div class="modal" style="max-width:480px;">
        <div class="modal-head">
            <div class="modal-ttl">
                <div class="ttl-icon"><i class="fa-solid fa-file-arrow-up" style="color:#4ade80;"></i></div>
                <span>Import Data Massal</span>
            </div>
            <button class="modal-close" onclick="closeOverlay('importOverlay')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="siswa.php" enctype="multipart/form-data">
            <input type="hidden" name="aksi" value="import">
            <div class="import-zone" id="dropZone">
                <input type="file" name="csv_file" id="csvFile" accept=".csv" onchange="onFileChange(this)">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <div class="iz-title">Drag & Drop file CSV di sini</div>
                <div class="iz-sub">atau klik untuk memilih file</div>
                <div class="iz-fname" id="fileName"></div>
            </div>
            <div class="import-info">
                Format kolom CSV: <strong>NIS, Nama Siswa, Kelas</strong><br>
                Atau tanpa NIS: <strong>Nama Siswa, Kelas</strong><br>
                Baris pertama (header) otomatis dilewati.
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeOverlay('importOverlay')">Batal</button>
                <button type="submit" class="btn-save" style="background:linear-gradient(135deg,#16a34a,#15803d);box-shadow:0 4px 14px rgba(22,163,74,.35);">
                    <i class="fa-solid fa-file-arrow-up"></i> Import Sekarang
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL HAPUS KONFIRMASI -->
<div class="overlay" id="delOverlay">
    <div class="confirm-box">
        <div class="confirm-del-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="confirm-ttl">Hapus Siswa?</div>
        <div class="confirm-msg" id="delMsg">Data akan dihapus permanen.</div>
        <div style="display:flex;gap:9px;justify-content:center;">
            <button class="btn-cancel" onclick="closeOverlay('delOverlay')">Batal</button>
            <a href="#" id="delLink" class="btn-del">
                <i class="fa-solid fa-trash-can"></i> Ya, Hapus
            </a>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast">
    <i id="toastIcon"></i>
    <span id="toastMsg"></span>
</div>

<script src="assets/script.js"></script>
<script>
function openOverlay(id){document.getElementById(id).classList.add('open');document.body.style.overflow='hidden';}
function closeOverlay(id){document.getElementById(id).classList.remove('open');document.body.style.overflow='';}

document.querySelectorAll('.overlay').forEach(el=>{
    el.addEventListener('click',e=>{if(e.target===el){el.classList.remove('open');document.body.style.overflow='';}});
});

function openModal(mode, data=null){
    document.getElementById('mIcon').className   = 'fa-solid '+(mode==='tambah'?'fa-user-plus':'fa-user-pen');
    document.getElementById('mTitle').textContent = mode==='tambah'?'Tambah Siswa Baru':'Edit Data Siswa';
    document.getElementById('btnTxt').textContent = mode==='tambah'?'Simpan Siswa':'Update Siswa';
    document.getElementById('fAksi').value  = mode;
    document.getElementById('fId').value    = data?.id_siswa   || '';
    document.getElementById('fNis').value   = data?.nis        || '';
    document.getElementById('fNama').value  = data?.nama_siswa || '';
    document.getElementById('fKelas').value = data?.kelas      || '';
    openOverlay('formOverlay');
}

function openConfirm(id, nama){
    document.getElementById('delMsg').textContent = `"${nama}" akan dihapus permanen dan tidak dapat dikembalikan.`;
    document.getElementById('delLink').href = `siswa.php?hapus=${id}`;
    openOverlay('delOverlay');
}

function toggleExport(){document.getElementById('exportDrop').classList.toggle('open');}
document.addEventListener('click',e=>{if(!e.target.closest('.export-wrap'))document.getElementById('exportDrop').classList.remove('open');});

function onFileChange(input){
    const fn=document.getElementById('fileName');
    if(input.files[0]){fn.textContent='✓ '+input.files[0].name;fn.style.display='block';}
}
const dz=document.getElementById('dropZone');
dz.addEventListener('dragover',e=>{e.preventDefault();dz.classList.add('drag');});
dz.addEventListener('dragleave',()=>dz.classList.remove('drag'));
dz.addEventListener('drop',e=>{e.preventDefault();dz.classList.remove('drag');const fi=document.getElementById('csvFile');fi.files=e.dataTransfer.files;onFileChange(fi);});

let _t;
function debounce(){clearTimeout(_t);_t=setTimeout(()=>document.getElementById('filterForm').submit(),450);}

function showToast(msg,color='#10b981',icon='fa-circle-check'){
    const t=document.getElementById('toast');
    document.getElementById('toastMsg').textContent=msg;
    document.getElementById('toastIcon').className='fa-solid '+icon;
    t.style.background=color;
    t.style.boxShadow=`0 8px 28px ${color}55`;
    t.classList.add('show');
    clearTimeout(t._t);
    t._t=setTimeout(()=>t.classList.remove('show'),3800);
}

(function(){
    const p=new URLSearchParams(location.search);
    const s=p.get('success');
    if(s==='tambah') showToast('Siswa berhasil ditambahkan!','#10b981','fa-circle-check');
    else if(s==='edit')  showToast('Data siswa berhasil diupdate!','#0891b2','fa-pen-to-square');
    else if(s==='hapus') showToast('Siswa berhasil dihapus!','#ef4444','fa-trash-can');
    else if(s==='nis_duplikat') showToast(`NIS "${p.get('nis')}" sudah digunakan siswa lain!`,'#f59e0b','fa-triangle-exclamation');
    else if(s==='import') showToast(`Import selesai: ${p.get('ok')} berhasil, ${p.get('skip')} dilewati`,'#16a34a','fa-file-arrow-up');
    else if(s==='import_fail') showToast('Gagal import. Pastikan file CSV valid!','#dc2626','fa-triangle-exclamation');
    if(s){
        const clean=location.pathname+'?'+[...p.entries()].filter(([k])=>!['success','ok','skip'].includes(k)).map(([k,v])=>`${k}=${encodeURIComponent(v)}`).join('&');
        history.replaceState(null,'',clean);
    }
})();

<?php if($editData): ?>openModal('edit',<?= json_encode($editData) ?>);<?php endif; ?>
</script>
</body>
</html>