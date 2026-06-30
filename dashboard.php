<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/* ═══════════════════════════════════════════
   FETCH STATISTIK DASHBOARD
═══════════════════════════════════════════ */

$totalSiswa    = (int)$pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
$totalKriteria = (int)$pdo->query("SELECT COUNT(*) FROM kriteria")->fetchColumn();
$sudahDinilai  = (int)$pdo->query("SELECT COUNT(DISTINCT id_siswa) FROM nilai")->fetchColumn();
$belumDinilai  = $totalSiswa - $sudahDinilai;
$persen        = $totalSiswa > 0 ? round(($sudahDinilai / $totalSiswa) * 100) : 0;

// Distribusi per kelas
$kelasData = $pdo->query("
    SELECT kelas, COUNT(*) as jumlah
    FROM siswa
    GROUP BY kelas
    ORDER BY kelas ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Top 5 ranking
try {
    $topSiswa = $pdo->query("
        SELECT s.nama_siswa, s.kelas, ROUND(h.nilai_akhir, 4) as skor
        FROM hasil h
        JOIN siswa s ON h.id_siswa = s.id_siswa
        ORDER BY h.nilai_akhir DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $topSiswa = []; }

// Aktivitas terbaru
try {
    $aktivitas = $pdo->query("
        SELECT s.nama_siswa, s.kelas, MAX(n.created_at) as waktu
        FROM nilai n
        JOIN siswa s ON n.id_siswa = s.id_siswa
        GROUP BY n.id_siswa
        ORDER BY waktu DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    try {
        $aktivitas = $pdo->query("
            SELECT DISTINCT s.nama_siswa, s.kelas, NULL as waktu
            FROM nilai n JOIN siswa s ON n.id_siswa = s.id_siswa
            LIMIT 8
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) { $aktivitas = []; }
}

// Nilai rata-rata per kriteria
try {
    $nilaiPerKriteria = $pdo->query("
        SELECT k.nama_kriteria, ROUND(AVG(n.nilai), 1) as rata
        FROM nilai n
        JOIN kriteria k ON n.kode_kriteria = k.kode_kriteria
        GROUP BY n.kode_kriteria, k.nama_kriteria
        ORDER BY k.kode_kriteria ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $nilaiPerKriteria = []; }

// Tren penilaian per hari (7 hari terakhir)
try {
    $trenData = $pdo->query("
        SELECT DATE(created_at) as tgl, COUNT(DISTINCT id_siswa) as jml
        FROM nilai
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY tgl ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $trenData = []; }

$trenMap = [];
foreach ($trenData as $t) $trenMap[$t['tgl']] = $t['jml'];
$trenLabels = []; $trenValues = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $trenLabels[] = date('d/m', strtotime($d));
    $trenValues[] = $trenMap[$d] ?? 0;
}

// Ringkasan per kelas
try {
    $ringkasanKelas = $pdo->query("
        SELECT s.kelas,
               COUNT(s.id_siswa) as total,
               COUNT(DISTINCT n.id_siswa) as dinilai
        FROM siswa s
        LEFT JOIN nilai n ON s.id_siswa = n.id_siswa
        GROUP BY s.kelas
        ORDER BY s.kelas ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $ringkasanKelas = []; }

$role      = $_SESSION['role'];
$username  = $_SESSION['username'];
$namaDepan = explode(' ', $username)[0];

$hariArr  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulanArr = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$hariIni  = $hariArr[date('w')] . ', ' . date('d') . ' ' . $bulanArr[(int)date('m')] . ' ' . date('Y');
$jamIni   = date('H:i:s');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard · SPK SMART</title>

<link rel="stylesheet" href="assets/style.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
    
/* ════════════════════════════════════
   RESET & BASE
════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.main-content {
    font-family: 'Plus Jakarta Sans', sans-serif;
    padding: 28px;
    color: #076cf0;
    min-height: 100vh;
}

/* ════════════════════════════════════
   WELCOME BANNER
════════════════════════════════════ */
.welcome-banner {
    border-radius: 24px;
    padding: 30px 36px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, #0f4c81 0%, #0891b2 42%, #7c3aed 100%);
    box-shadow: 0 8px 40px rgba(8,145,178,.35), 0 2px 8px rgba(0,0,0,.3);
    animation: fadeUp .5s ease both;
}
.welcome-banner::before {
    content: '';
    position: absolute; top: -60px; right: -60px;
    width: 280px; height: 280px;
    background: radial-gradient(circle, rgba(255,255,255,.12) 0%, transparent 70%);
    pointer-events: none;
}
.welcome-banner::after {
    content: '';
    position: absolute; bottom: -80px; left: 30%;
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(255,255,255,.07) 0%, transparent 70%);
    pointer-events: none;
}
.wb-orb {
    position: absolute; top: 50%; right: 200px;
    transform: translateY(-50%);
    width: 120px; height: 120px;
    border-radius: 50%;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.10);
    pointer-events: none;
}
.wb-text { position: relative; z-index: 1; }
.wb-greeting {
    font-size: 11px; font-weight: 700;
    color: rgba(255,255,255,.6);
    letter-spacing: .08em; margin-bottom: 6px;
}
.wb-text h2 {
    font-size: 26px; font-weight: 800; color: #ffffff;
    margin-bottom: 8px;
    text-shadow: 0 2px 12px rgba(0,0,0,.2);
}
.wb-text p {
    font-size: 13px; color: rgba(255,255,255,.7);
    line-height: 1.6; max-width: 480px;
}
.wb-chips { display: flex; gap: 8px; margin-top: 14px; flex-wrap: wrap; }
.wb-chip {
    background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.22);
    border-radius: 99px; padding: 5px 14px;
    font-size: 11px; font-weight: 700;
    color: rgba(255, 255, 255, 0.9);
    display: flex; align-items: center; gap: 6px;
    backdrop-filter: blur(8px);
}
.wb-chip i { font-size: 10px; }
.wb-right {
    display: flex; flex-direction: column;
    align-items: center; gap: 10px;
    position: relative; z-index: 1; flex-shrink: 0;
}
.wb-icon {
    width: 80px; height: 80px;
    background: rgba(255,255,255,.15);
    border: 2px solid rgba(255,255,255,.25);
    border-radius: 24px;
    display: flex; align-items: center; justify-content: center;
    backdrop-filter: blur(12px);
    animation: float 3s ease-in-out infinite;
    box-shadow: 0 8px 32px rgba(0,0,0,.2);
}
.wb-icon i { font-size: 34px; color: #fff; }
.wb-clock {
    font-family: 'JetBrains Mono', monospace;
    font-size: 22px; font-weight: 600;
    color: rgba(255,255,255,.9);
    letter-spacing: .06em;
    text-shadow: 0 2px 8px rgba(0,0,0,.2);
}
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-7px); }
}

/* ════════════════════════════════════
   STAT CARDS
════════════════════════════════════ */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px; margin-bottom: 18px;
    animation: fadeUp .5s .07s ease both;
}
.stat {
    border-radius: 20px; padding: 20px 18px;
    display: flex; align-items: center; gap: 14px;
    transition: all .28s cubic-bezier(.4,0,.2,1);
    cursor: default; position: relative; overflow: hidden;
    border: 1px solid transparent;
}
.stat:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(0,0,0,.3); }

.stat.s-cyan   { background: linear-gradient(135deg, rgba(14,165,233,.22), rgba(6,182,212,.08)); border-color: rgba(14,165,233,.32); box-shadow: 0 4px 20px rgba(14,165,233,.13); }
.stat.s-green  { background: linear-gradient(135deg, rgba(34,197,94,.20), rgba(16,185,129,.08)); border-color: rgba(34,197,94,.28);   box-shadow: 0 4px 20px rgba(34,197,94,.11);  }
.stat.s-amber  { background: linear-gradient(135deg, rgba(245,158,11,.20), rgba(251,191,36,.08)); border-color: rgba(245,158,11,.28); box-shadow: 0 4px 20px rgba(245,158,11,.11); }
.stat.s-violet { background: linear-gradient(135deg, rgba(139,92,246,.20), rgba(167,139,250,.08)); border-color: rgba(139,92,246,.28); box-shadow: 0 4px 20px rgba(139,92,246,.11); }

.stat-icon {
    width: 50px; height: 50px;
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.stat-icon i { font-size: 20px; }
.stat.s-cyan   .stat-icon { background: rgba(14,165,233,.25); }
.stat.s-green  .stat-icon { background: rgba(34,197,94,.25);  }
.stat.s-amber  .stat-icon { background: rgba(245,158,11,.25); }
.stat.s-violet .stat-icon { background: rgba(139,92,246,.25); }

.stat.s-cyan   .stat-icon i, .stat.s-cyan   .stat-value { color: #22d3ee; }
.stat.s-green  .stat-icon i, .stat.s-green  .stat-value { color: #4ade80; }
.stat.s-amber  .stat-icon i, .stat.s-amber  .stat-value { color: #fbbf24; }
.stat.s-violet .stat-icon i, .stat.s-violet .stat-value { color: #c4b5fd; }

.stat-label { font-size: 10px; font-weight: 700; letter-spacing: .10em; text-transform: uppercase; color: rgba(255,255,255,.4); margin-bottom: 4px; }
.stat-value { font-size: 30px; font-weight: 800; font-family: 'JetBrains Mono', monospace; line-height: 1; }
.stat-delta { font-size: 11px; margin-top: 5px; color: rgba(255,255,255,.38); display: flex; align-items: center; gap: 4px; }
.stat-delta i { font-size: 10px; }

/* ════════════════════════════════════
   PROGRESS STRIP
════════════════════════════════════ */
.progress-strip {
    background: linear-gradient(135deg, rgba(8,145,178,.10), rgba(139,92,246,.06));
    border: 1px solid rgba(8,145,178,.18);
    border-radius: 18px; padding: 18px 24px;
    margin-bottom: 18px;
    animation: fadeUp .5s .10s ease both;
}
.ps-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.ps-label { font-size: 13px; font-weight: 700; color: #94a3b8; }
.ps-pct {
    font-size: 26px; font-weight: 800;
    font-family: 'JetBrains Mono', monospace;
    background: linear-gradient(90deg, #22d3ee, #818cf8);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.progress-bar { height: 12px; background: rgba(255,255,255,.055); border-radius: 99px; overflow: hidden; }
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #0891b2, #06b6d4, #818cf8, #7c3aed);
    background-size: 200% 100%;
    border-radius: 99px;
    transition: width 1.4s cubic-bezier(.4,0,.2,1);
    animation: gradMove 3s linear infinite;
}
@keyframes gradMove { 0% { background-position: 0% 0%; } 100% { background-position: 200% 0%; } }
.ps-stats { display: flex; gap: 18px; margin-top: 10px; font-size: 12px; }
.ps-stat  { display: flex; align-items: center; gap: 6px; color: #475569; }
.ps-dot   { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

/* ════════════════════════════════════
   SHORTCUT GRID
════════════════════════════════════ */
.shortcut-grid {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 10px; margin-bottom: 18px;
    animation: fadeUp .5s .13s ease both;
}
.shortcut {
    border-radius: 18px; padding: 18px 12px;
    display: flex; flex-direction: column; align-items: center; gap: 10px;
    text-decoration: none;
    transition: all .28s cubic-bezier(.4,0,.2,1);
    border: 1px solid transparent;
    position: relative; overflow: hidden;
}
.shortcut:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,.25); }
.sc-1 { background: linear-gradient(135deg, rgba(14,165,233,.18), rgba(6,182,212,.06)); border-color: rgba(14,165,233,.26); }
.sc-2 { background: linear-gradient(135deg, rgba(139,92,246,.18), rgba(167,139,250,.06)); border-color: rgba(139,92,246,.26); }
.sc-3 { background: linear-gradient(135deg, rgba(34,197,94,.18), rgba(16,185,129,.06)); border-color: rgba(34,197,94,.26); }
.sc-4 { background: linear-gradient(135deg, rgba(245,158,11,.18), rgba(251,191,36,.06)); border-color: rgba(245,158,11,.26); }
.sc-icon { width: 46px; height: 46px; border-radius: 14px; display: flex; align-items: center; justify-content: center; position: relative; z-index: 1; }
.sc-icon i { font-size: 19px; }
.sc-label { font-size: 12px; font-weight: 700; color: #94a3b8; text-align: center; position: relative; z-index: 1; transition: color .2s; }
.shortcut:hover .sc-label { color: #e2e8f0; }

/* ════════════════════════════════════
   CARD BASE
════════════════════════════════════ */
.card {
    background: rgba(255,255,255,.022);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 22px; overflow: hidden;
    animation: fadeUp .5s .16s ease both;
}
.card-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 22px 14px;
    border-bottom: 1px solid rgba(255,255,255,.05);
}
.card-title { display: flex; align-items: center; gap: 11px; }
.card-title-icon {
    width: 36px; height: 36px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.card-title-icon i { font-size: 15px; }
.card-title-text { font-size: 14px; font-weight: 700; color: #0769e9; }
.card-title-sub  { font-size: 11px; color: #475569; margin-top: 1px; }
.card-badge {
    background: rgba(255,255,255,.045); border: 1px solid rgba(255,255,255,.07);
    border-radius: 7px; padding: 3px 10px;
    font-size: 11px; font-weight: 700; color: #64748b;
    font-family: 'JetBrains Mono', monospace;
}
.card-link {
    font-size: 12px; color: #22d3ee; font-weight: 700;
    text-decoration: none; transition: opacity .2s;
}
.card-link:hover { opacity: .7; }
.card-body { padding: 18px 22px; }

/* ════════════════════════════════════
   GRID LAYOUTS
════════════════════════════════════ */
.grid-2   { display: grid; grid-template-columns: 1fr 1fr;     gap: 14px; margin-bottom: 14px; }
.grid-2-3 { display: grid; grid-template-columns: 1fr 1.5fr;   gap: 14px; margin-bottom: 14px; }

/* ════════════════════════════════════
   DONUT CENTER LABEL
════════════════════════════════════ */
.donut-wrap { position: relative; height: 230px; }
.donut-center {
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -62%);
    text-align: center; pointer-events: none;
}
.donut-center-num { font-size: 26px; font-weight: 800; font-family: 'JetBrains Mono', monospace; color: #22d3ee; }
.donut-center-lbl { font-size: 10px; color: #475569; margin-top: 2px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }

/* ════════════════════════════════════
   CHART LEGEND
════════════════════════════════════ */
.chart-legend {
    display: flex; flex-wrap: wrap; gap: 12px;
    margin-bottom: 10px; font-size: 11px; color: #64748b;
}
.chart-legend span {
    display: flex; align-items: center; gap: 5px;
}
.chart-legend-dot { width: 10px; height: 10px; border-radius: 2px; display: inline-block; }

/* ════════════════════════════════════
   KELAS BAR
════════════════════════════════════ */
.kelas-list { display: flex; flex-direction: column; gap: 9px; }
.kelas-row  { display: flex; align-items: center; gap: 10px; }
.kelas-label { font-size: 11px; font-weight: 700; color: #94a3b8; width: 46px; flex-shrink: 0; font-family: 'JetBrains Mono', monospace; }
.kelas-bar-wrap { flex: 1; height: 9px; background: rgba(255,255,255,.05); border-radius: 99px; overflow: hidden; }
.kelas-bar { height: 100%; border-radius: 99px; transition: width 1.1s cubic-bezier(.4,0,.2,1) .3s; }
.kelas-num { font-size: 12px; font-weight: 800; color: #64748b; font-family: 'JetBrains Mono', monospace; width: 24px; text-align: right; flex-shrink: 0; }

/* ════════════════════════════════════
   TOP RANKING
════════════════════════════════════ */
.rank-list { display: flex; flex-direction: column; gap: 8px; }
.rank-item {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 14px;
    border-radius: 14px;
    transition: all .2s; border: 1px solid transparent;
}
.rank-item:nth-child(1) { background: linear-gradient(135deg, rgba(245,158,11,.15), rgba(251,191,36,.05)); border-color: rgba(245,158,11,.22); }
.rank-item:nth-child(2) { background: linear-gradient(135deg, rgba(148,163,184,.09), rgba(100,116,139,.04)); border-color: rgba(148,163,184,.14); }
.rank-item:nth-child(3) { background: linear-gradient(135deg, rgba(205,124,47,.11), rgba(180,83,9,.04)); border-color: rgba(205,124,47,.17); }
.rank-item:nth-child(n+4) { background: rgba(255,255,255,.018); border-color: rgba(255,255,255,.045); }
.rank-item:hover { transform: translateX(3px); border-color: rgba(255,255,255,.12); }

.rank-num { width: 30px; height: 30px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; flex-shrink: 0; font-family: 'JetBrains Mono', monospace; }
.rn-1 { background: linear-gradient(135deg, #f59e0b, #d97706); color: #0352ff; box-shadow: 0 4px 14px rgba(245,158,11,.5); }
.rn-2 { background: linear-gradient(135deg, #94a3b8, #64748b); color: #0352ff; }
.rn-3 { background: linear-gradient(135deg, #cd7c2f, #b45309); color: #0352ff; }
.rn-x { background: rgba(255,255,255,.07); color: #475569; }

.rank-info  { flex: 1; }
.rank-name  { font-size: 13px; font-weight: 700; color: #e2e8f0; }
.rank-kelas { font-size: 11px; color: #475569; margin-top: 1px; }
.rank-score {
    font-size: 15px; font-weight: 800;
    font-family: 'JetBrains Mono', monospace;
    background: linear-gradient(135deg, #22d3ee, #818cf8);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.rank-empty { text-align: center; padding: 32px; color: #334155; font-size: 13px; }
.rank-empty i { font-size: 30px; display: block; margin-bottom: 10px; opacity: .3; }

/* ════════════════════════════════════
   AKTIVITAS
════════════════════════════════════ */
.activity-list { display: flex; flex-direction: column; }
.act-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,.04); }
.act-item:last-child { border-bottom: none; }
.act-avatar { width: 38px; height: 38px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 800; }
.act-info   { flex: 1; }
.act-name   { font-size: 13px; font-weight: 700; color: #cbd5e1; }
.act-sub    { font-size: 11px; color: #475569; margin-top: 1px; }
.act-time   { font-size: 10px; color: #334155; font-family: 'JetBrains Mono', monospace; white-space: nowrap; }
.act-dot    { width: 6px; height: 6px; border-radius: 50%; background: #22d3ee; flex-shrink: 0; box-shadow: 0 0 8px rgba(34,211,238,.7); }

/* ════════════════════════════════════
   TABEL RINGKASAN KELAS
════════════════════════════════════ */
.tbl-kelas { width: 100%; border-collapse: collapse; }
.tbl-kelas thead th {
    background: rgba(0,0,0,.18);
    padding: 10px 14px;
    font-size: 10px; font-weight: 700;
    letter-spacing: .10em; text-transform: uppercase;
    color: #475569; text-align: left;
}
.tbl-kelas tbody tr { border-bottom: 1px solid rgba(255,255,255,.04); transition: background .15s; }
.tbl-kelas tbody tr:last-child { border-bottom: none; }
.tbl-kelas tbody tr:hover { background: rgba(255,255,255,.03); }
.tbl-kelas tbody td { padding: 10px 14px; font-size: 12px; color: #cbd5e1; vertical-align: middle; }
.tbl-kelas .kl-name { font-weight: 700; color: #e2e8f0; font-family: 'JetBrains Mono', monospace; }
.mini-prog { height: 6px; background: rgba(255,255,255,.06); border-radius: 99px; overflow: hidden; min-width: 70px; }
.mini-fill { height: 100%; border-radius: 99px; transition: width 1s ease .5s; }
.pct-tag   { font-size: 11px; font-weight: 700; font-family: 'JetBrains Mono', monospace; margin-left: 6px; }
.tbl-scroll { overflow-x: auto; }

/* ════════════════════════════════════
   EMPTY STATE
════════════════════════════════════ */
.empty-state { text-align: center; padding: 28px; color: #334155; font-size: 13px; }
.empty-state i { font-size: 28px; display: block; margin-bottom: 10px; opacity: .3; }

/* ════════════════════════════════════
   ANIMATIONS
════════════════════════════════════ */
@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }

/* ════════════════════════════════════
   RESPONSIVE
════════════════════════════════════ */
@media (max-width: 1024px) {
    .stats-row    { grid-template-columns: repeat(2, 1fr); }
    .grid-2, .grid-2-3 { grid-template-columns: 1fr; }
    .shortcut-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 540px) {
    .main-content  { padding: 14px; }
    .stats-row     { grid-template-columns: 1fr; }
    .welcome-banner { flex-direction: column; gap: 18px; }
    .wb-right      { flex-direction: row; justify-content: center; }
}

/* =========================================================
   LIGHT MODE FIX ONLY
   Tambahkan paling bawah style dashboard.php
========================================================= */

body.light-mode .main-content{
    background:#f1f5f9 !important;
    color:#0f172a !important;
}

/* =========================================================
   CARD
========================================================= */
body.light-mode .card{
    background:#ffffff !important;
    border:1px solid #e2e8f0 !important;
    box-shadow:0 4px 20px rgba(15,23,42,.05) !important;
}

body.light-mode .card-head{
    border-bottom:1px solid #e2e8f0 !important;
}

body.light-mode .card-title-text{
    color:#0f172a !important;
}

body.light-mode .card-title-sub{
    color:#64748b !important;
}

body.light-mode .card-badge{
    background:#f8fafc !important;
    border:1px solid #e2e8f0 !important;
    color:#475569 !important;
}

/* =========================================================
   WELCOME
========================================================= */
body.light-mode .welcome-banner{
    box-shadow:0 8px 30px rgba(37,99,235,.18) !important;
}

/* =========================================================
   STATS CARD
========================================================= */
body.light-mode .stat{
    background:#ffffff !important;
    border:1px solid #e2e8f0 !important;
    box-shadow:0 4px 20px rgba(15,23,42,.05) !important;
}

body.light-mode .stat:hover{
    box-shadow:0 12px 28px rgba(15,23,42,.08) !important;
}

body.light-mode .stat-label{
    color:#64748b !important;
}

body.light-mode .stat-delta{
    color:#64748b !important;
}

/* text number */
body.light-mode .rank-name,
body.light-mode .act-name,
body.light-mode .kl-name,
body.light-mode .tbl-kelas td,
body.light-mode .kelas-label{
    color:#0f172a !important;
}

/* =========================================================
   PROGRESS
========================================================= */
body.light-mode .progress-strip{
    background:#ffffff !important;
    border:1px solid #e2e8f0 !important;
    box-shadow:0 4px 20px rgba(15,23,42,.05) !important;
}

body.light-mode .ps-label{
    color:#334155 !important;
}

body.light-mode .ps-stat{
    color:#475569 !important;
}

body.light-mode .progress-bar{
    background:#e2e8f0 !important;
}

/* =========================================================
   SHORTCUT
========================================================= */
body.light-mode .shortcut{
    background:#ffffff !important;
    border:1px solid #e2e8f0 !important;
    box-shadow:0 4px 14px rgba(15,23,42,.04) !important;
}

body.light-mode .shortcut:hover{
    box-shadow:0 12px 25px rgba(15,23,42,.08) !important;
}

body.light-mode .sc-label{
    color:#0f172a !important;
}

/* =========================================================
   TABLE
========================================================= */
body.light-mode .tbl-kelas thead th{
    background:#f8fafc !important;
    color:#475569 !important;
}

body.light-mode .tbl-kelas tbody tr{
    border-bottom:1px solid #e2e8f0 !important;
}

body.light-mode .tbl-kelas tbody tr:hover{
    background:#f8fafc !important;
}

/* =========================================================
   RANKING
========================================================= */
body.light-mode .rank-item:nth-child(n+4){
    background:#f8fafc !important;
    border:1px solid #e2e8f0 !important;
}

body.light-mode .rank-kelas{
    color:#64748b !important;
}

body.light-mode .rank-empty{
    color:#64748b !important;
}

/* =========================================================
   ACTIVITY
========================================================= */
body.light-mode .act-item{
    border-bottom:1px solid #e2e8f0 !important;
}

body.light-mode .act-sub{
    color:#64748b !important;
}

body.light-mode .act-time{
    color:#94a3b8 !important;
}

/* =========================================================
   DISTRIBUSI KELAS
========================================================= */
body.light-mode .kelas-bar-wrap{
    background:#e2e8f0 !important;
}

body.light-mode .kelas-num{
    color:#475569 !important;
}

/* =========================================================
   CHART TEXT
========================================================= */
body.light-mode canvas{
    filter:none !important;
}

/* =========================================================
   EMPTY STATE
========================================================= */
body.light-mode .empty-state,
body.light-mode .rank-empty{
    color:#64748b !important;
}

/* =========================================================
   TEXT WARNA GLOBAL
========================================================= */
body.light-mode .chart-legend,
body.light-mode .card-link,
body.light-mode .kelas-num,
body.light-mode .act-sub,
body.light-mode .rank-kelas,
body.light-mode .stat-delta{
    color:#64748b !important;
}

/* =========================================================
   BACKGROUND AREA
========================================================= */
body.light-mode{
    background:#f1f5f9 !important;
}

</style>
</head>
<body>

<?php include __DIR__ . '/layout/sidebar.php'; ?>

<main class="main-content">

    <?php include __DIR__ . '/layout/header.php'; ?>

    <!-- ══════════════════════════════════
         WELCOME BANNER
    ══════════════════════════════════ -->
    <div class="welcome-banner">
        <div class="wb-orb"></div>
        <div class="wb-text">
            <div class="wb-greeting"><i class="fas fa-sparkles" style="margin-right:5px;"></i>SELAMAT DATANG</div>
            <h2>Halo, <?= htmlspecialchars($namaDepan) ?>! 👋</h2>
            <p>Kelola sistem pendukung keputusan Kemajuan Belajar Siswa dengan  metode <strong style="color:#fff;">SMART</strong> — pantau progres, analisa nilai, dan lihat ranking terbaik dari satu halaman.</p>
            <div class="wb-chips">
                <div class="wb-chip"><i class="fas fa-calendar-days"></i><?= $hariIni ?></div>
                <div class="wb-chip"><i class="fas fa-user-shield"></i><?= ucfirst($role) ?></div>
                <div class="wb-chip"><i class="fas fa-chart-line"></i><?= $persen ?>% selesai</div>
            </div>
        </div>
        <div class="wb-right">
            <div class="wb-icon"><i class="fas fa-graduation-cap"></i></div>
            <div class="wb-clock" id="liveClock"><?= $jamIni ?></div>
        </div>
    </div>

    <!-- ══════════════════════════════════
         STAT CARDS
    ══════════════════════════════════ -->
    <div class="stats-row">
        <div class="stat s-cyan">
            <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
            <div>
                <div class="stat-label">Total Siswa</div>
                <div class="stat-value" data-count="<?= $totalSiswa ?>"><?= $totalSiswa ?></div>
                <div class="stat-delta"><i class="fas fa-users"></i> terdaftar dalam sistem</div>
            </div>
        </div>
        <div class="stat s-green">
            <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
            <div>
                <div class="stat-label">Sudah Dinilai</div>
                <div class="stat-value" data-count="<?= $sudahDinilai ?>"><?= $sudahDinilai ?></div>
                <div class="stat-delta"><i class="fas fa-arrow-trend-up" style="color:#4ade80;"></i> <?= $persen ?>% dari total siswa</div>
            </div>
        </div>
        <div class="stat s-amber">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div>
                <div class="stat-label">Belum Dinilai</div>
                <div class="stat-value" data-count="<?= $belumDinilai ?>"><?= $belumDinilai ?></div>
                <div class="stat-delta"><i class="fas fa-triangle-exclamation" style="color:#fbbf24;"></i> perlu diinput segera</div>
            </div>
        </div>
        <div class="stat s-violet">
            <div class="stat-icon"><i class="fas fa-sliders"></i></div>
            <div>
                <div class="stat-label">Kriteria SMART</div>
                <div class="stat-value" data-count="<?= $totalKriteria ?>"><?= $totalKriteria ?></div>
                <div class="stat-delta"><i class="fas fa-list-check"></i> parameter penilaian</div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════
         PROGRESS STRIP
    ══════════════════════════════════ -->
    <div class="progress-strip">
        <div class="ps-head">
            <div style="display:flex;align-items:center;gap:9px;">
                <i class="fas fa-bars-progress" style="color:#0891b2;font-size:14px;"></i>
                <span class="ps-label">Progress Penilaian Keseluruhan</span>
            </div>
            <div class="ps-pct"><?= $persen ?>%</div>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill" style="width:0%"></div>
        </div>
        <div class="ps-stats">
            <div class="ps-stat"><div class="ps-dot" style="background:#22d3ee;box-shadow:0 0 6px #22d3ee;"></div><?= $sudahDinilai ?> siswa selesai</div>
            <div class="ps-stat"><div class="ps-dot" style="background:#fbbf24;box-shadow:0 0 6px #fbbf24;"></div><?= $belumDinilai ?> belum dinilai</div>
            <div class="ps-stat"><div class="ps-dot" style="background:#c4b5fd;box-shadow:0 0 6px #c4b5fd;"></div><?= $totalSiswa ?> total siswa</div>
        </div>
    </div>

    <!-- ══════════════════════════════════
         SHORTCUT MENU
    ══════════════════════════════════ -->
    <?php if ($role === 'admin' || $role === 'guru'): ?>
    <div class="shortcut-grid">
        <a href="siswa.php" class="shortcut sc-1">
            <div class="sc-icon" style="background:rgba(14,165,233,.22);"><i class="fas fa-user-graduate" style="color:#22d3ee;"></i></div>
            <div class="sc-label">Data Siswa</div>
        </a>
        <a href="kriteria.php" class="shortcut sc-2">
            <div class="sc-icon" style="background:rgba(139,92,246,.22);"><i class="fas fa-list-ul" style="color:#c4b5fd;"></i></div>
            <div class="sc-label">Data Kriteria</div>
        </a>
        <a href="penilaian.php" class="shortcut sc-3">
            <div class="sc-icon" style="background:rgba(34,197,94,.22);"><i class="fas fa-pencil" style="color:#4ade80;"></i></div>
            <div class="sc-label">Input Nilai</div>
        </a>
        <a href="perhitungan.php" class="shortcut sc-4">
            <div class="sc-icon" style="background:rgba(245,158,11,.22);"><i class="fas fa-calculator" style="color:#fbbf24;"></i></div>
            <div class="sc-label">Perhitungan SMART</div>
        </a>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════
         ROW 1 : DONUT + LINE CHART
    ══════════════════════════════════ -->
    <div class="grid-2">

        <!-- DONUT -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">
                    <div class="card-title-icon" style="background:rgba(14,165,233,.16);"><i class="fas fa-chart-pie" style="color:#22d3ee;"></i></div>
                    <div>
                        <div class="card-title-text">Status Penilaian</div>
                        <div class="card-title-sub">Sudah vs belum diinput</div>
                    </div>
                </div>
                <span class="card-badge">Donut</span>
            </div>
            <div class="card-body">
                <div class="donut-wrap">
                    <canvas id="chartDonut"></canvas>
                    <div class="donut-center">
                        <div class="donut-center-num"><?= $persen ?>%</div>
                        <div class="donut-center-lbl">Selesai</div>
                    </div>
                </div>
                <div class="chart-legend" style="justify-content:center;margin-top:10px;">
                    <span><span class="chart-legend-dot" style="background:#0891b2;"></span>Sudah Dinilai (<?= $sudahDinilai ?>)</span>
                    <span><span class="chart-legend-dot" style="background:#f59e0b;"></span>Belum Dinilai (<?= $belumDinilai ?>)</span>
                </div>
            </div>
        </div>

        <!-- LINE TREN -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">
                    <div class="card-title-icon" style="background:rgba(139,92,246,.16);"><i class="fas fa-chart-line" style="color:#c4b5fd;"></i></div>
                    <div>
                        <div class="card-title-text">Tren Penilaian</div>
                        <div class="card-title-sub">7 hari terakhir</div>
                    </div>
                </div>
                <span class="card-badge">Line</span>
            </div>
            <div class="card-body">
                <div style="position:relative;height:250px;">
                    <canvas id="chartLine"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- ══════════════════════════════════
         ROW 2 : BAR + RADAR
    ══════════════════════════════════ -->
    <div class="grid-2">

        <!-- BAR KRITERIA -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">
                    <div class="card-title-icon" style="background:rgba(34,197,94,.16);"><i class="fas fa-chart-bar" style="color:#4ade80;"></i></div>
                    <div>
                        <div class="card-title-text">Rata-rata Nilai per Kriteria</div>
                        <div class="card-title-sub">Performa seluruh siswa</div>
                    </div>
                </div>
                <span class="card-badge">Bar</span>
            </div>
            <div class="card-body">
                <?php if (empty($nilaiPerKriteria)): ?>
                <div class="empty-state"><i class="fas fa-chart-bar"></i>Belum ada data nilai</div>
                <?php else: ?>
                <div style="position:relative;height:250px;">
                    <canvas id="chartBar"></canvas>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RADAR KRITERIA -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">
                    <div class="card-title-icon" style="background:rgba(245,158,11,.16);"><i class="fas fa-crosshairs" style="color:#fbbf24;"></i></div>
                    <div>
                        <div class="card-title-text">Radar Performa Kriteria</div>
                        <div class="card-title-sub">Visualisasi multidimensi</div>
                    </div>
                </div>
                <span class="card-badge">Radar</span>
            </div>
            <div class="card-body">
                <?php if (empty($nilaiPerKriteria)): ?>
                <div class="empty-state"><i class="fas fa-crosshairs"></i>Belum ada data nilai</div>
                <?php else: ?>
                <div style="position:relative;height:250px;">
                    <canvas id="chartRadar"></canvas>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ══════════════════════════════════
         ROW 3 : TOP RANKING + AKTIVITAS
    ══════════════════════════════════ -->
    <div class="grid-2">

        <!-- TOP RANKING -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">
                    <div class="card-title-icon" style="background:rgba(245,158,11,.16);"><i class="fas fa-trophy" style="color:#fbbf24;"></i></div>
                    <div>
                        <div class="card-title-text">Top 5 Ranking</div>
                        <div class="card-title-sub">Nilai akhir SMART tertinggi</div>
                    </div>
                </div>
                <a href="hasil.php" class="card-link">Lihat semua →</a>
            </div>
            <div class="card-body">
                <?php if (empty($topSiswa)): ?>
                <div class="rank-empty">
                    <i class="fas fa-calculator"></i>
                    Belum ada data ranking.<br>
                    Jalankan <strong>Perhitungan SMART</strong> terlebih dahulu.
                </div>
                <?php else: ?>
                <div class="rank-list">
                    <?php foreach ($topSiswa as $i => $s):
                        $nc = match(true) { $i===0=>'rn-1', $i===1=>'rn-2', $i===2=>'rn-3', default=>'rn-x' }; ?>
                    <div class="rank-item">
                        <div class="rank-num <?= $nc ?>"><?= $i + 1 ?></div>
                        <div class="rank-info">
                            <div class="rank-name"><?= htmlspecialchars($s['nama_siswa']) ?></div>
                            <div class="rank-kelas">Kelas <?= htmlspecialchars($s['kelas']) ?></div>
                        </div>
                        <div class="rank-score"><?= number_format($s['skor'], 4) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- AKTIVITAS TERBARU -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">
                    <div class="card-title-icon" style="background:rgba(14,165,233,.13);"><i class="fas fa-clock-rotate-left" style="color:#22d3ee;"></i></div>
                    <div>
                        <div class="card-title-text">Aktivitas Terbaru</div>
                        <div class="card-title-sub">Penilaian yang baru diinput</div>
                    </div>
                </div>
                <?php if ($role === 'admin' || $role === 'guru'): ?>
                <a href="penilaian.php" class="card-link" style="color:#4ade80;">+ Input →</a>
                <?php endif; ?>
            </div>
            <div class="card-body" style="padding-top:8px;">
                <?php if (empty($aktivitas)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    Belum ada aktivitas penilaian
                </div>
                <?php else:
                    $ac = [
                        ['rgba(14,165,233,.2)','#22d3ee'],
                        ['rgba(34,197,94,.2)','#4ade80'],
                        ['rgba(139,92,246,.2)','#c4b5fd'],
                        ['rgba(245,158,11,.2)','#fbbf24'],
                        ['rgba(244,63,94,.2)','#fb7185'],
                        ['rgba(20,184,166,.2)','#2dd4bf'],
                        ['rgba(96,165,250,.2)','#60a5fa'],
                        ['rgba(251,113,133,.2)','#fb7185'],
                    ];
                    foreach ($aktivitas as $i => $a):
                        $cl  = $ac[$i % count($ac)];
                        $ini = strtoupper(mb_substr($a['nama_siswa'], 0, 1));
                        $wk  = $a['waktu'] ? date('d/m H:i', strtotime($a['waktu'])) : '—'; ?>
                <div class="act-item">
                    <div class="act-dot"></div>
                    <div class="act-avatar" style="background:<?= $cl[0] ?>;color:<?= $cl[1] ?>;"><?= $ini ?></div>
                    <div class="act-info">
                        <div class="act-name"><?= htmlspecialchars($a['nama_siswa']) ?></div>
                        <div class="act-sub">Kelas <?= htmlspecialchars($a['kelas']) ?> · nilai diinput</div>
                    </div>
                    <div class="act-time"><?= $wk ?></div>
                </div>
                    <?php endforeach; endif; ?>
            </div>
        </div>

    </div>

    <!-- ══════════════════════════════════
         ROW 4 : DISTRIBUSI KELAS + TABEL RINGKASAN
    ══════════════════════════════════ -->
    <div class="grid-2-3">

        <!-- DISTRIBUSI KELAS BAR -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">
                    <div class="card-title-icon" style="background:rgba(139,92,246,.16);"><i class="fas fa-school" style="color:#c4b5fd;"></i></div>
                    <div>
                        <div class="card-title-text">Distribusi per Kelas</div>
                        <div class="card-title-sub">Jumlah siswa tiap kelas</div>
                    </div>
                </div>
                <span class="card-badge"><?= count($kelasData) ?> kelas</span>
            </div>
            <div class="card-body">
                <div class="kelas-list">
                <?php
                $maxK    = count($kelasData) > 0 ? max(array_column($kelasData,'jumlah')) : 1;
                $kGrad   = [
                    'linear-gradient(90deg,#0891b2,#06b6d4)',
                    'linear-gradient(90deg,#7c3aed,#a78bfa)',
                    'linear-gradient(90deg,#16a34a,#4ade80)',
                    'linear-gradient(90deg,#d97706,#fbbf24)',
                    'linear-gradient(90deg,#db2777,#fb7185)',
                    'linear-gradient(90deg,#0d9488,#2dd4bf)',
                    'linear-gradient(90deg,#2563eb,#60a5fa)',
                    'linear-gradient(90deg,#9333ea,#c4b5fd)',
                    'linear-gradient(90deg,#059669,#34d399)',
                ];
                foreach ($kelasData as $i => $k):
                    $w = $maxK > 0 ? round(($k['jumlah'] / $maxK) * 100) : 0;
                    $g = $kGrad[$i % count($kGrad)];
                ?>
                <div class="kelas-row">
                    <div class="kelas-label"><?= htmlspecialchars($k['kelas']) ?></div>
                    <div class="kelas-bar-wrap">
                        <div class="kelas-bar" style="width:0%;background:<?= $g ?>;" data-w="<?= $w ?>"></div>
                    </div>
                    <div class="kelas-num"><?= $k['jumlah'] ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($kelasData)): ?>
                <div class="empty-state" style="padding:16px;"><i class="fas fa-school"></i>Belum ada data kelas</div>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TABEL RINGKASAN KELAS -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">
                    <div class="card-title-icon" style="background:rgba(34,197,94,.13);"><i class="fas fa-table-list" style="color:#4ade80;"></i></div>
                    <div>
                        <div class="card-title-text">Ringkasan per Kelas</div>
                        <div class="card-title-sub">Status penilaian tiap kelas</div>
                    </div>
                </div>
                <span class="card-badge">Tabel</span>
            </div>
            <div class="tbl-scroll">
                <table class="tbl-kelas">
                    <thead>
                        <tr>
                            <th>Kelas</th>
                            <th>Total</th>
                            <th>Dinilai</th>
                            <th>Progress</th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ringkasanKelas as $r):
                        $p    = $r['total'] > 0 ? round(($r['dinilai'] / $r['total']) * 100) : 0;
                        $col  = $p >= 100 ? '#4ade80' : ($p >= 50 ? '#fbbf24' : '#fb7185');
                        $grad = $p >= 100
                            ? 'linear-gradient(90deg,#16a34a,#4ade80)'
                            : ($p >= 50 ? 'linear-gradient(90deg,#d97706,#fbbf24)' : 'linear-gradient(90deg,#dc2626,#fb7185)');
                    ?>
                    <tr>
                        <td class="kl-name"><?= htmlspecialchars($r['kelas']) ?></td>
                        <td><?= $r['total'] ?></td>
                        <td style="color:#4ade80;font-weight:700;"><?= $r['dinilai'] ?></td>
                        <td>
                            <div class="mini-prog">
                                <div class="mini-fill" style="width:0%;background:<?= $grad ?>;" data-w="<?= $p ?>"></div>
                            </div>
                        </td>
                        <td><span class="pct-tag" style="color:<?= $col ?>;"><?= $p ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($ringkasanKelas)): ?>
                    <tr><td colspan="5" class="empty-state" style="padding:20px;">Belum ada data</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</main>

<script src="assets/script.js"></script>
<script>
/* ════════════════════════════════════
   LIVE CLOCK
════════════════════════════════════ */
(function() {
    function tick() {
        const n = new Date();
        const pad = v => String(v).padStart(2, '0');
        const el = document.getElementById('liveClock');
        if (el) el.textContent = pad(n.getHours()) + ':' + pad(n.getMinutes()) + ':' + pad(n.getSeconds());
    }
    tick();
    setInterval(tick, 1000);
})();

/* ════════════════════════════════════
   PROGRESS BAR ANIMASI
════════════════════════════════════ */
setTimeout(() => {
    document.getElementById('progressFill').style.width = '<?= $persen ?>%';
}, 280);

/* ════════════════════════════════════
   KELAS BAR + MINI BAR ANIMASI
════════════════════════════════════ */
setTimeout(() => {
    document.querySelectorAll('.kelas-bar, .mini-fill').forEach(b => {
        b.style.width = (b.dataset.w || 0) + '%';
    });
}, 420);

/* ════════════════════════════════════
   COUNTER ANIMASI
════════════════════════════════════ */
document.querySelectorAll('.stat-value[data-count]').forEach(el => {
    const target = parseInt(el.dataset.count) || 0;
    let cur = 0;
    const step = Math.max(1, Math.ceil(target / 36));
    const t = setInterval(() => {
        cur = Math.min(cur + step, target);
        el.textContent = cur;
        if (cur >= target) clearInterval(t);
    }, 28);
});

/* ════════════════════════════════════
   CHART.JS GLOBAL DEFAULTS
════════════════════════════════════ */
const isLight = document.body.classList.contains('light-mode');

Chart.defaults.color = isLight ? '#006aff' : '#0b2c5b';
Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
Chart.defaults.font.size = 11;




/* ════════════════════════════════════
   DONUT CHART — STATUS PENILAIAN
════════════════════════════════════ */
new Chart(document.getElementById('chartDonut'), {
    type: 'doughnut',
    data: {
        labels: ['Sudah Dinilai', 'Belum Dinilai'],
        datasets: [{
            data: [<?= $sudahDinilai ?>, <?= $belumDinilai ?>],
            backgroundColor: ['rgba(8,145,178,.88)', 'rgba(245,158,11,.72)'],
            borderColor: ['#0891b2', '#f59e0b'],
            borderWidth: 2,
            hoverOffset: 10,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
            legend: { display: false },
            tooltip: { ...tooltipDefaults, callbacks: { label: c => '  ' + c.formattedValue + ' siswa' } }
        },
        animation: { animateRotate: true, duration: 1000 }
    }
});

/* ════════════════════════════════════
   LINE CHART — TREN PENILAIAN
════════════════════════════════════ */
(function() {
    const ctx = document.getElementById('chartLine').getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, 220);
    grad.addColorStop(0, 'rgba(139,92,246,.45)');
    grad.addColorStop(1, 'rgba(139,92,246,.02)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($trenLabels) ?>,
            datasets: [{
                label: 'Siswa Dinilai',
                data: <?= json_encode($trenValues) ?>,
                borderColor: '#a78bfa',
                backgroundColor: grad,
                borderWidth: 2.5,
                pointBackgroundColor: '#7c3aed',
                pointBorderColor: '#c4b5fd',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 8,
                tension: .45,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { ...tooltipDefaults }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,.04)' },
                    ticks: { color: '#475569' },
                    border: { color: 'rgba(255,255,255,.05)' }
                },
                y: {
                    grid: { color: 'rgba(255,255,255,.04)' },
                    ticks: { color: '#475569', stepSize: 1, precision: 0 },
                    border: { color: 'rgba(255,255,255,.05)' },
                    min: 0
                }
            },
            animation: { duration: 1000 }
        }
    });
})();

/* ════════════════════════════════════
   BAR CHART — RATA-RATA PER KRITERIA
════════════════════════════════════ */
<?php if (!empty($nilaiPerKriteria)): ?>
(function() {
    const palette = {
        bg:     ['rgba(8,145,178,.82)','rgba(139,92,246,.82)','rgba(34,197,94,.82)','rgba(245,158,11,.82)','rgba(244,63,94,.82)','rgba(20,184,166,.82)','rgba(96,165,250,.82)','rgba(251,113,133,.82)'],
        border: ['#0891b2','#7c3aed','#16a34a','#f59e0b','#f43f5e','#14b8a6','#60a5fa','#fb7185'],
    };
    const n = <?= count($nilaiPerKriteria) ?>;
    new Chart(document.getElementById('chartBar'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($nilaiPerKriteria, 'nama_kriteria')) ?>,
            datasets: [{
                label: 'Rata-rata Nilai',
                data: <?= json_encode(array_column($nilaiPerKriteria, 'rata')) ?>,
                backgroundColor: palette.bg.slice(0, n),
                borderColor: palette.border.slice(0, n),
                borderWidth: 1.5,
                borderRadius: 9,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { ...tooltipDefaults }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,.04)' },
                    ticks: { color: '#475569', maxRotation: 30 },
                    border: { color: 'rgba(255,255,255,.05)' }
                },
                y: {
                    grid: { color: 'rgba(255,255,255,.04)' },
                    ticks: { color: '#475569', stepSize: 20 },
                    border: { color: 'rgba(255,255,255,.05)' },
                    min: 0, max: 100
                }
            },
            animation: { duration: 1000 }
        }
    });
})();
<?php endif; ?>

/* ════════════════════════════════════
   RADAR CHART — PERFORMA KRITERIA
════════════════════════════════════ */
<?php if (!empty($nilaiPerKriteria)): ?>
(function() {
    const ctx = document.getElementById('chartRadar').getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, 220);
    grad.addColorStop(0, 'rgba(245,158,11,.40)');
    grad.addColorStop(1, 'rgba(245,158,11,.05)');

    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: <?= json_encode(array_column($nilaiPerKriteria, 'nama_kriteria')) ?>,
            datasets: [{
                label: 'Rata-rata',
                data: <?= json_encode(array_column($nilaiPerKriteria, 'rata')) ?>,
                borderColor: '#f59e0b',
                backgroundColor: grad,
                borderWidth: 2.5,
                pointBackgroundColor: '#d97706',
                pointBorderColor: '#fbbf24',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { ...tooltipDefaults }
            },
            scales: {
                r: {
                    grid:        { color: 'rgba(255,255,255,.07)' },
                    angleLines:  { color: 'rgba(255,255,255,.07)' },
                    pointLabels: { color: '#64748b', font: { size: 10 } },
                    ticks:       { color: '#334155', backdropColor: 'transparent', stepSize: 20 },
                    min: 0, max: 100
                }
            },
            animation: { duration: 1100 }
        }
    });
})();
<?php endif; ?>


</script>
</body>
</html>