<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/* ========================= FILTER ========================= */
$filter_kelas = $_GET['kelas'] ?? '';
$search       = $_GET['q'] ?? '';
$sort_asc     = isset($_GET['sort']) && $_GET['sort'] === 'asc';

$where = $filter_kelas ? "WHERE kelas='" . $pdo->quote($filter_kelas) . "'" : "";

/* ========================= DATA ========================= */
$siswa    = $pdo->query("SELECT * FROM siswa $where")->fetchAll(PDO::FETCH_ASSOC);
$kriteria = $pdo->query("SELECT * FROM kriteria ORDER BY bobot DESC")->fetchAll(PDO::FETCH_ASSOC);

$bobot     = [];
$label_map = [];
foreach ($kriteria as $k) {
    $bobot[$k['kode']]     = (float)$k['bobot'];
    $label_map[$k['kode']] = $k['nama_kriteria'] ?? $k['kode'];
}

/* ========================= MAX ========================= */
$max = [];
foreach ($kriteria as $k) {
    $stmt = $pdo->prepare("SELECT MAX(nilai) FROM nilai WHERE kode_kriteria=?");
    $stmt->execute([$k['kode']]);
    $max[$k['kode']] = (float)($stmt->fetchColumn() ?: 1);
}

/* ========================= HITUNG SMART ========================= */
$hasil = [];
foreach ($siswa as $s) {
    $stmt = $pdo->prepare("SELECT * FROM nilai WHERE id_siswa=?");
    $stmt->execute([$s['id_siswa']]);
    $nilais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total  = 0;
    $detail = [];
    foreach ($nilais as $n) {
        $kode    = $n['kode_kriteria'];
        $val     = (float)$n['nilai'];
        $normal  = $val / $max[$kode];
        $w       = $bobot[$kode] ?? 0;
        $hasil_k = $normal * $w;
        $detail[$kode] = [
            'nilai'   => $val,
            'max'     => $max[$kode],
            'normal'  => $normal,
            'bobot'   => $w,
            'hasil'   => $hasil_k,
        ];
        $total += $hasil_k;
    }
    $hasil[] = [
        'id'     => $s['id_siswa'],
        'nama'   => $s['nama_siswa'],
        'kelas'  => $s['kelas'],
        'total'  => $total,
        'detail' => $detail,
    ];
}

/* ========================= SORT ========================= */
usort($hasil, fn($a, $b) => $sort_asc
    ? $a['total'] <=> $b['total']
    : $b['total'] <=> $a['total']);

/* ========================= SEARCH ========================= */
if ($search) {
    $hasil = array_values(array_filter($hasil,
        fn($h) => stripos($h['nama'], $search) !== false));
}

/* ========================= STATS ========================= */
$total_scores = array_column($hasil, 'total');
$max_score    = $total_scores ? max($total_scores) : 0;
$avg_score    = $total_scores ? array_sum($total_scores) / count($total_scores) : 0;
$all_kelas    = array_unique(array_column($hasil, 'kelas'));

$list_kelas = $pdo->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas")->fetchAll(PDO::FETCH_ASSOC);

$avatar_palettes = [
    ['bg' => 'rgba(99,102,241,0.18)',  'fg' => '#818cf8'],
    ['bg' => 'rgba(20,184,166,0.18)',  'fg' => '#2dd4bf'],
    ['bg' => 'rgba(245,158,11,0.18)',  'fg' => '#fbbf24'],
    ['bg' => 'rgba(236,72,153,0.18)',  'fg' => '#f472b6'],
    ['bg' => 'rgba(34,197,94,0.18)',   'fg' => '#4ade80'],
];

$step_colors = ['#818cf8','#2dd4bf','#fbbf24','#f472b6','#4ade80','#60a5fa'];

function getInitials($name) {
    $parts = explode(' ', $name);
    return strtoupper(substr($parts[0],0,1).(isset($parts[1])?substr($parts[1],0,1):''));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Perhitungan SMART</title>

<link rel="stylesheet" href="assets/style.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

<style>
/* ─────────────────────────────────────────────────
   BASE
───────────────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}

.smart-page{
    font-family:'Plus Jakarta Sans',sans-serif;
    color:#e2e8f0;
    padding:24px;
    position:relative;
    min-height:100vh;
}

/* ─── GLOW ORBS ─── */
.glow-orb{position:fixed;border-radius:50%;pointer-events:none;z-index:0;filter:blur(70px);}
.glow-orb.a{top:-100px;right:-80px;width:340px;height:340px;background:rgba(99,102,241,.12);}
.glow-orb.b{bottom:-60px;left:-60px;width:260px;height:260px;background:rgba(20,184,166,.09);}

/* ─── HEADER ─── */
.smart-header{
    display:flex;align-items:center;justify-content:space-between;
    flex-wrap:wrap;gap:12px;margin-bottom:22px;position:relative;z-index:1;
}
.page-title{
    font-family:'Space Grotesk',sans-serif;
    font-size:22px;font-weight:700;color:#fff;
    display:flex;align-items:center;gap:10px;
}
.icon-box{
    width:38px;height:38px;
    background:linear-gradient(135deg,#6366f1,#818cf8);
    border-radius:10px;
    display:flex;align-items:center;justify-content:center;font-size:18px;
}
.badge-live{
    font-size:11px;font-weight:600;
    background:rgba(20,184,166,.15);color:#2dd4bf;
    border:1px solid rgba(20,184,166,.3);
    padding:3px 10px;border-radius:20px;letter-spacing:.5px;
}

/* ─── STAT CARDS ─── */
.stat-grid{
    display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
    gap:12px;margin-bottom:20px;position:relative;z-index:1;
}
.stat-card{
    background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.08);
    border-radius:14px;padding:16px;transition:all .2s;
}
.stat-card:hover{background:rgba(255,255,255,.07);border-color:rgba(99,102,241,.4);transform:translateY(-2px);}
.stat-label{font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;}
.stat-val{font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:700;color:#fff;}
.stat-sub{font-size:11px;color:#94a3b8;margin-top:2px;}

/* ─── CONTROLS ─── */
.controls-bar{
    display:flex;flex-wrap:wrap;gap:10px;align-items:center;
    margin-bottom:20px;position:relative;z-index:1;
}
.search-box,.filter-select{
    background:rgba(255,255,255,.05);
    border:1px solid rgba(255,255,255,.10);
    border-radius:10px;padding:9px 14px;
    color:#e2e8f0;font-size:13px;font-family:inherit;outline:none;
    transition:border-color .2s;
}
.search-box{flex:1;min-width:180px;}
.search-box::placeholder{color:#475569;}
.search-box:focus,.filter-select:focus{border-color:rgba(99,102,241,.6);background:rgba(99,102,241,.07);}
.sort-btn{
    font-family:inherit;cursor:pointer;outline:none;transition:all .2s;
    font-size:12px;font-weight:600;
    background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);
    color:#818cf8;border-radius:10px;padding:9px 16px;letter-spacing:.3px;
}
.sort-btn:hover{background:rgba(99,102,241,.25);}
.sort-btn.asc{background:#6366f1;color:#fff;border-color:#6366f1;}
.view-toggle{
    display:flex;background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.08);border-radius:10px;overflow:hidden;
}
.view-btn{
    padding:9px 14px;border:none;background:transparent;
    color:#64748b;font-size:15px;cursor:pointer;transition:all .2s;
    font-family:inherit;font-weight:600;
}
.view-btn.active{background:rgba(99,102,241,.2);color:#818cf8;}

/* ─── TABLE ─── */
.table-wrap{overflow-x:auto;position:relative;z-index:1;}
table.rank-table{width:100%;border-collapse:separate;border-spacing:0 6px;}
table.rank-table th{
    font-size:11px;font-weight:600;color:#475569;
    text-transform:uppercase;letter-spacing:.8px;
    padding:8px 14px;text-align:left;
}
.student-row{cursor:pointer;animation:fadeIn .4s both;}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}
.student-row td{
    background:rgba(255,255,255,.03);
    border-top:1px solid rgba(255,255,255,.05);
    border-bottom:1px solid rgba(255,255,255,.05);
    padding:13px 14px;transition:all .2s;vertical-align:middle;
}
.student-row td:first-child{border-left:1px solid rgba(255,255,255,.05);border-radius:10px 0 0 10px;}
.student-row td:last-child{border-right:1px solid rgba(255,255,255,.05);border-radius:0 10px 10px 0;}
.student-row:hover td{background:rgba(99,102,241,.08);border-color:rgba(99,102,241,.2);}
.student-row.open td{border-bottom-color:transparent;border-radius:10px 10px 0 0 !important;}

.top-1 td{background:rgba(234,179,8,.05) !important;border-color:rgba(234,179,8,.25) !important;}
.top-2 td{background:rgba(148,163,184,.04)!important;border-color:rgba(148,163,184,.2)!important;}
.top-3 td{background:rgba(180,83,9,.04) !important;border-color:rgba(180,83,9,.2) !important;}

/* ─── RANK BADGE ─── */
.rank-badge{
    width:32px;height:32px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;
    font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:14px;
}
.r1{background:rgba(234,179,8,.2);color:#fbbf24;border:1px solid rgba(234,179,8,.4);}
.r2{background:rgba(148,163,184,.15);color:#94a3b8;border:1px solid rgba(148,163,184,.3);}
.r3{background:rgba(180,83,9,.2);color:#fb923c;border:1px solid rgba(180,83,9,.3);}
.rn{background:rgba(255,255,255,.05);color:#64748b;border:1px solid rgba(255,255,255,.08);}

/* ─── AVATAR / NAME ─── */
.avatar{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;}
.name-cell{display:flex;align-items:center;gap:10px;}
.student-name{font-size:14px;font-weight:600;color:#e2e8f0;}
.student-id{font-size:11px;color:#475569;margin-top:1px;}

/* ─── PILL ─── */
.kelas-pill{
    font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;
    background:rgba(99,102,241,.15);color:#818cf8;border:1px solid rgba(99,102,241,.25);white-space:nowrap;
}

/* ─── SCORE ─── */
.score-cell{text-align:right;}
.score-big{font-family:'Space Grotesk',sans-serif;font-size:16px;font-weight:700;color:#e2e8f0;}
.score-bar-wrap{width:80px;height:4px;background:rgba(255,255,255,.06);border-radius:2px;margin-top:5px;margin-left:auto;overflow:hidden;}
.score-bar-fill{height:100%;border-radius:2px;}

/* ─── EXPAND BUTTON ─── */
.expand-btn{
    width:30px;height:30px;background:rgba(255,255,255,.05);
    border:1px solid rgba(255,255,255,.1);color:#94a3b8;
    border-radius:8px;display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:all .2s;margin-left:auto;font-size:12px;
    font-weight:700;
}
.expand-btn.active{background:rgba(99,102,241,.2);color:#818cf8;border-color:rgba(99,102,241,.4);}

/* ═══════════════════════════════════════════════════
   DETAIL / TAHAPAN PERHITUNGAN
═══════════════════════════════════════════════════ */
.detail-row{display:none;}
.detail-row.open{display:table-row;}

.detail-row td{
    padding:0 !important;
    border-top:none !important;
    border-left:1px solid rgba(99,102,241,.25) !important;
    border-right:1px solid rgba(99,102,241,.25) !important;
    border-bottom:1px solid rgba(99,102,241,.25) !important;
    border-radius:0 0 12px 12px !important;
    background:rgba(6,12,28,.6) !important;
    backdrop-filter:blur(10px);
}

.calc-panel{padding:20px 20px 24px;}

/* ── Step indicator ── */
.steps-nav{
    display:flex;align-items:center;gap:0;
    margin-bottom:22px;overflow-x:auto;padding-bottom:4px;
}
.step-item{
    display:flex;align-items:center;gap:0;flex-shrink:0;cursor:pointer;
}
.step-dot{
    width:28px;height:28px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:11px;font-weight:700;
    background:rgba(255,255,255,.06);
    border:1.5px solid rgba(255,255,255,.12);
    color:#64748b;transition:all .25s;flex-shrink:0;
}
.step-label{
    font-size:11px;font-weight:600;color:#475569;
    margin:0 8px;white-space:nowrap;transition:color .25s;
}
.step-line{width:28px;height:1.5px;background:rgba(255,255,255,.08);flex-shrink:0;}

.step-item.active .step-dot{
    background:rgba(99,102,241,.25);
    border-color:#818cf8;color:#818cf8;
}
.step-item.active .step-label{color:#c7d2fe;}
.step-item.done .step-dot{background:rgba(99,102,241,.35);border-color:#6366f1;color:#fff;}
.step-item.done .step-dot::after{content:'✓';}
.step-item.done .step-label{color:#818cf8;}

/* ── Step content ── */
.step-content{display:none;}
.step-content.active{display:block;animation:stepIn .3s ease;}
@keyframes stepIn{from{opacity:0;transform:translateX(10px);}to{opacity:1;transform:translateX(0);}}

/* ── Step nav buttons ── */
.step-actions{
    display:flex;justify-content:space-between;align-items:center;
    margin-top:18px;
}
.stn-btn{
    font-family:inherit;font-size:12px;font-weight:600;cursor:pointer;
    padding:7px 16px;border-radius:8px;transition:all .2s;outline:none;
    display:flex;align-items:center;gap:6px;
}
.stn-prev{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#94a3b8;}
.stn-prev:hover{background:rgba(255,255,255,.09);color:#e2e8f0;}
.stn-next{background:rgba(99,102,241,.2);border:1px solid rgba(99,102,241,.35);color:#818cf8;}
.stn-next:hover{background:rgba(99,102,241,.3);color:#c7d2fe;}
.stn-progress{font-size:11px;color:#475569;}

/* ────────────────────────────────────────────────
   STEP 1 — NILAI ASLI
──────────────────────────────────────────────── */
.step-title{
    font-family:'Space Grotesk',sans-serif;
    font-size:14px;font-weight:700;color:#e2e8f0;
    margin-bottom:4px;
}
.step-desc{font-size:12px;color:#64748b;margin-bottom:16px;line-height:1.5;}

.nilai-grid{
    display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;
}
.nilai-card{
    background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.07);
    border-radius:12px;padding:14px;
    position:relative;overflow:hidden;
}
.nilai-card::before{
    content:'';position:absolute;top:0;left:0;right:0;height:3px;
    border-radius:12px 12px 0 0;
}
.nilai-kode{font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;}
.nilai-nama{font-size:12px;color:#94a3b8;margin-bottom:10px;line-height:1.4;}
.nilai-num{
    font-family:'Space Grotesk',sans-serif;
    font-size:28px;font-weight:700;
}
.nilai-max{font-size:11px;color:#475569;margin-top:3px;}
.nilai-bar-bg{height:5px;background:rgba(255,255,255,.06);border-radius:3px;margin-top:10px;overflow:hidden;}
.nilai-bar-fill{height:100%;border-radius:3px;transition:width .6s ease;}

/* ────────────────────────────────────────────────
   STEP 2 — BOBOT KRITERIA
──────────────────────────────────────────────── */
.formula-box{
    background:rgba(0,0,0,.25);
    border:1px solid rgba(99,102,241,.2);
    border-radius:10px;padding:12px 16px;
    margin-bottom:16px;
    font-family:'JetBrains Mono',monospace;
    font-size:12px;color:#c7d2fe;line-height:1.8;
}
.formula-comment{color:#475569;}

.bobot-table-wrap{overflow-x:auto;}
.bobot-table{
    width:100%;border-collapse:collapse;
    font-size:12px;
}
.bobot-table th{
    color:#475569;font-size:10px;text-transform:uppercase;letter-spacing:.7px;
    padding:8px 12px;border-bottom:1px solid rgba(255,255,255,.06);text-align:left;
}
.bobot-table td{padding:9px 12px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;}
.bobot-table tr:last-child td{border-bottom:none;}
.bobot-table tr:hover td{background:rgba(255,255,255,.03);}

.bobot-pill{
    display:inline-block;
    font-family:'JetBrains Mono',monospace;
    font-size:12px;font-weight:600;
    padding:3px 10px;border-radius:6px;
}
.bobot-bar-wrap{width:90px;height:6px;background:rgba(255,255,255,.06);border-radius:3px;display:inline-block;margin-left:8px;vertical-align:middle;overflow:hidden;}
.bobot-bar-fill{height:100%;border-radius:3px;}

/* ────────────────────────────────────────────────
   STEP 3 — NORMALISASI
──────────────────────────────────────────────── */
.norm-cards{
    display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;
}
.norm-card{
    background:rgba(0,0,0,.2);
    border:1px solid rgba(255,255,255,.06);
    border-radius:12px;padding:14px;
}
.norm-card-head{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:12px;
}
.norm-kode{font-size:11px;font-weight:700;color:#818cf8;text-transform:uppercase;letter-spacing:.7px;}
.norm-nama{font-size:11px;color:#64748b;margin-top:2px;}

.norm-formula{
    font-family:'JetBrains Mono',monospace;
    font-size:11px;color:#94a3b8;
    background:rgba(255,255,255,.04);
    border-radius:7px;padding:8px 10px;
    margin-bottom:10px;
    text-align:center;line-height:1.8;
}
.norm-fraction{display:flex;flex-direction:column;align-items:center;gap:2px;}
.norm-line{width:50px;height:1px;background:#475569;}
.norm-num-big{
    font-family:'Space Grotesk',sans-serif;
    font-size:20px;font-weight:700;
    text-align:center;margin-top:6px;
}
.norm-progress{
    height:6px;background:rgba(255,255,255,.05);
    border-radius:3px;overflow:hidden;margin-top:8px;
}
.norm-fill{height:100%;border-radius:3px;transition:width .6s .1s ease;}

/* ────────────────────────────────────────────────
   STEP 4 — PERKALIAN BOBOT
──────────────────────────────────────────────── */
.weight-cards{
    display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;
    margin-bottom:16px;
}
.weight-card{
    background:rgba(0,0,0,.2);
    border:1px solid rgba(255,255,255,.06);
    border-radius:12px;padding:14px;
}
.weight-eq{
    font-family:'JetBrains Mono',monospace;
    font-size:11px;color:#64748b;
    margin-bottom:8px;line-height:2;
    text-align:center;
}
.weight-eq .eq-hl{color:#c7d2fe;}
.weight-eq .eq-op{color:#fbbf24;}
.weight-eq .eq-res{color:#4ade80;font-size:14px;font-weight:600;}

.weight-mini-bar{height:4px;background:rgba(255,255,255,.05);border-radius:2px;overflow:hidden;margin-top:8px;}
.weight-mini-fill{height:100%;border-radius:2px;}
.weight-kode{font-size:10px;color:#475569;text-transform:uppercase;letter-spacing:.7px;margin-bottom:4px;}

/* ────────────────────────────────────────────────
   STEP 5 — PENJUMLAHAN & HASIL AKHIR
──────────────────────────────────────────────── */
.sum-table-wrap{margin-bottom:18px;}
.sum-table{width:100%;border-collapse:collapse;font-size:12px;}
.sum-table th{
    color:#475569;font-size:10px;text-transform:uppercase;letter-spacing:.7px;
    padding:8px 12px;border-bottom:1px solid rgba(255,255,255,.06);text-align:right;
}
.sum-table th:first-child{text-align:left;}
.sum-table td{padding:9px 12px;border-bottom:1px solid rgba(255,255,255,.04);text-align:right;font-family:'JetBrains Mono',monospace;font-size:11px;}
.sum-table td:first-child{text-align:left;font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;color:#94a3b8;}
.sum-table tr:last-child td{border-bottom:none;}
.sum-table .sum-total td{
    border-top:1px solid rgba(99,102,241,.3);
    padding-top:12px;color:#c7d2fe;
    font-family:'Space Grotesk',sans-serif;font-weight:700;
}
.sum-table .sum-total td:first-child{color:#818cf8;}

/* Hasil akhir highlight */
.result-box{
    background:linear-gradient(135deg,rgba(99,102,241,.15),rgba(20,184,166,.08));
    border:1px solid rgba(99,102,241,.35);
    border-radius:14px;padding:20px;
    display:flex;align-items:center;justify-content:space-between;gap:16px;
    flex-wrap:wrap;
}
.result-label{font-size:12px;color:#94a3b8;margin-bottom:4px;text-transform:uppercase;letter-spacing:.8px;}
.result-score{
    font-family:'Space Grotesk',sans-serif;
    font-size:40px;font-weight:700;
    background:linear-gradient(135deg,#818cf8,#2dd4bf);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
}
.result-formula-final{
    font-family:'JetBrains Mono',monospace;
    font-size:11px;color:#475569;
    background:rgba(0,0,0,.25);
    border-radius:8px;padding:10px 14px;
    line-height:2;flex:1;min-width:200px;
}
.rf-hl{color:#818cf8;}
.rf-num{color:#fbbf24;}
.rf-res{color:#4ade80;font-weight:600;}

/* ── PODIUM VIEW ── */
.podium-section{display:none;padding:10px 0 24px;}
.podium-section.active{display:block;}
.podium-grid{display:flex;align-items:flex-end;justify-content:center;gap:12px;padding:0 20px;}
.podium-item{text-align:center;flex:1;max-width:160px;}
.podium-block{border-radius:12px 12px 0 0;display:flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:10px;}
.p1 .podium-block{height:120px;background:linear-gradient(to top,rgba(234,179,8,.3),rgba(234,179,8,.1));border:1px solid rgba(234,179,8,.4);}
.p2 .podium-block{height:80px;background:linear-gradient(to top,rgba(148,163,184,.2),rgba(148,163,184,.05));border:1px solid rgba(148,163,184,.3);}
.p3 .podium-block{height:60px;background:linear-gradient(to top,rgba(180,83,9,.2),rgba(180,83,9,.05));border:1px solid rgba(180,83,9,.25);}
.podium-name{font-size:13px;font-weight:600;color:#e2e8f0;}
.podium-kelas{font-size:11px;color:#64748b;margin-top:2px;}
.podium-score{font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700;color:#818cf8;margin-top:4px;}

/* ── EXPORT ── */
.export-row{display:flex;justify-content:flex-end;gap:8px;margin-top:20px;}
.export-btn{
    background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);
    color:#94a3b8;border-radius:8px;padding:7px 14px;font-size:12px;font-weight:500;
    font-family:inherit;cursor:pointer;transition:all .2s;
    display:inline-flex;align-items:center;gap:6px;text-decoration:none;
}
.export-btn:hover{background:rgba(255,255,255,.08);color:#e2e8f0;}

/* ── LIGHT MODE ── */
:root[data-theme="light"] .smart-page{background:#f8fafc;color:#0f172a;}
:root[data-theme="light"] .page-title{color:#0f172a;}
:root[data-theme="light"] .stat-card{background:#fff;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(15,23,42,.04);}
:root[data-theme="light"] .stat-val{color:#0f172a;}
:root[data-theme="light"] .stat-label,:root[data-theme="light"] .stat-sub{color:#64748b;}
:root[data-theme="light"] .search-box,:root[data-theme="light"] .filter-select{background:#fff;border:1px solid #dbe3ee;color:#0f172a;}
:root[data-theme="light"] .search-box::placeholder{color:#94a3b8;}
:root[data-theme="light"] .view-toggle{background:#fff;border:1px solid #dbe3ee;}
:root[data-theme="light"] .view-btn{color:#64748b;}
:root[data-theme="light"] .view-btn.active{background:rgba(99,102,241,.1);color:#4f46e5;}
:root[data-theme="light"] table.rank-table th{color:#64748b;}
:root[data-theme="light"] .student-row td{background:#fff;border-color:#e2e8f0;}
:root[data-theme="light"] .student-row:hover td{background:rgba(99,102,241,.04);border-color:rgba(99,102,241,.15);}
:root[data-theme="light"] .student-name{color:#0f172a;}
:root[data-theme="light"] .student-id{color:#64748b;}
:root[data-theme="light"] .score-big{color:#0f172a;}
:root[data-theme="light"] .score-bar-wrap{background:#e2e8f0;}
:root[data-theme="light"] .expand-btn{background:#fff;border:1px solid #dbe3ee;color:#64748b;}
:root[data-theme="light"] .detail-row td{background:#f8fafc !important;border-color:rgba(99,102,241,.2) !important;}
:root[data-theme="light"] .calc-panel{background:#f8fafc;}
:root[data-theme="light"] .formula-box{background:#fff;border-color:rgba(99,102,241,.2);color:#4f46e5;}
:root[data-theme="light"] .formula-comment{color:#94a3b8;}
:root[data-theme="light"] .bobot-table th,:root[data-theme="light"] .sum-table th{color:#64748b;}
:root[data-theme="light"] .bobot-table td,:root[data-theme="light"] .sum-table td{color:#0f172a;}
:root[data-theme="light"] .norm-card,:root[data-theme="light"] .weight-card,:root[data-theme="light"] .nilai-card{background:#fff;border:1px solid #e2e8f0;}
:root[data-theme="light"] .norm-formula{background:#f1f5f9;color:#334155;}
:root[data-theme="light"] .step-title{color:#0f172a;}
:root[data-theme="light"] .step-desc{color:#64748b;}
:root[data-theme="light"] .stn-prev{background:#fff;border:1px solid #dbe3ee;color:#64748b;}
:root[data-theme="light"] .stn-next{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.25);color:#4f46e5;}
:root[data-theme="light"] .result-box{background:linear-gradient(135deg,rgba(99,102,241,.07),rgba(20,184,166,.04));border-color:rgba(99,102,241,.2);}
:root[data-theme="light"] .result-formula-final{background:#f1f5f9;color:#475569;}
:root[data-theme="light"] .podium-name{color:#0f172a;}
:root[data-theme="light"] .podium-kelas{color:#64748b;}
:root[data-theme="light"] .export-btn{background:#fff;border:1px solid #dbe3ee;color:#475569;}

/* responsive */
@media(max-width:640px){
    .smart-page{padding:14px;}
    .stat-grid{grid-template-columns:1fr 1fr;}
    .steps-nav{gap:0;}
    .step-label{display:none;}
}
</style>
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="main-content">
<div class="smart-page">

<div class="glow-orb a"></div>
<div class="glow-orb b"></div>

<!-- HEADER -->
<div class="smart-header">
    <div class="page-title">
        <div class="icon-box"><i class="fas fa-calculator"></i></div>
        Perhitungan SMART
        <span class="badge-live">LIVE</span>
    </div>
</div>

<!-- STAT CARDS -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">Total Siswa</div>
        <div class="stat-val"><?= count($hasil) ?></div>
        <div class="stat-sub">data terfilter</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Nilai Tertinggi</div>
        <div class="stat-val"><?= number_format($max_score*100,1) ?>%</div>
        <div class="stat-sub"><?= htmlspecialchars($hasil[0]['nama'] ?? '-') ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Rata-rata</div>
        <div class="stat-val"><?= number_format($avg_score*100,1) ?>%</div>
        <div class="stat-sub">semua siswa</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Jumlah Kelas</div>
        <div class="stat-val"><?= count($all_kelas) ?></div>
        <div class="stat-sub">kelas aktif</div>
    </div>
</div>

<!-- CONTROLS -->
<form method="GET" class="controls-bar" id="filterForm">
    <input class="search-box" type="text" name="q"
           placeholder="&#128269;  Cari nama siswa..."
           value="<?= htmlspecialchars($search) ?>"
           oninput="this.form.submit()">

    <select class="filter-select" name="kelas" onchange="this.form.submit()">
        <option value="">Semua Kelas</option>
        <?php foreach ($list_kelas as $k): ?>
            <option value="<?= $k['kelas'] ?>" <?= $filter_kelas===$k['kelas']?'selected':'' ?>>
                <?= htmlspecialchars($k['kelas']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input type="hidden" name="sort" id="sortInput" value="<?= $sort_asc?'asc':'desc' ?>">
    <button type="button" class="sort-btn <?= $sort_asc?'asc':'' ?>" onclick="toggleSort()">
        <?= $sort_asc ? '↑ Terendah' : '↓ Tertinggi' ?>
    </button>

    <div class="view-toggle">
        <button type="button" class="view-btn active" id="btnTable"  onclick="setView('table')">☰</button>
        <button type="button" class="view-btn"        id="btnPodium" onclick="setView('podium')">🏆</button>
    </div>
</form>

<!-- TABLE VIEW -->
<div class="table-wrap" id="tableView">
<table class="rank-table">
    <thead>
        <tr>
            <th style="width:44px">Rank</th>
            <th>Nama Siswa</th>
            <th>Kelas</th>
            <th>Nilai Akhir</th>
            <th style="width:44px"></th>
        </tr>
    </thead>
    <tbody>

    <?php
    $top_score = $hasil[0]['total'] ?? 1;
    foreach ($hasil as $i => $h):
        $rank    = $i + 1;
        $cls     = $rank <= 3 ? "top-$rank" : '';
        $rbadge  = $rank <= 3 ? "r$rank" : 'rn';
        $icon    = $rank===1 ? '🥇' : ($rank===2 ? '🥈' : ($rank===3 ? '🥉' : $rank));
        $pal     = $avatar_palettes[$i % count($avatar_palettes)];
        $pct     = number_format($h['total']*100, 2);
        $bar_w   = round(($h['total'] / max($top_score,0.001))*100);
        $bar_clr = $rank===1?'#fbbf24':($rank===2?'#94a3b8':($rank===3?'#fb923c':'#6366f1'));
        $delay   = $i * 40;
        $uid     = 'row_' . $i;
    ?>

    <!-- MAIN ROW -->
    <tr class="student-row <?= $cls ?>" id="sr_<?= $i ?>"
        onclick="toggleCalc(<?= $i ?>)"
        style="animation-delay:<?= $delay ?>ms">

        <td><div class="rank-badge <?= $rbadge ?>"><?= $icon ?></div></td>

        <td>
            <div class="name-cell">
                <div class="avatar" style="background:<?= $pal['bg'] ?>;color:<?= $pal['fg'] ?>">
                    <?= getInitials($h['nama']) ?>
                </div>
                <div>
                    <div class="student-name"><?= htmlspecialchars($h['nama']) ?></div>
                    <div class="student-id">ID #<?= str_pad($h['id'],4,'0',STR_PAD_LEFT) ?> &bull; <?= htmlspecialchars($h['kelas']) ?></div>
                </div>
            </div>
        </td>

        <td><span class="kelas-pill"><?= htmlspecialchars($h['kelas']) ?></span></td>

        <td class="score-cell">
            <div class="score-big"><?= $pct ?>%</div>
            <div class="score-bar-wrap">
                <div class="score-bar-fill" style="width:<?= $bar_w ?>%;background:<?= $bar_clr ?>"></div>
            </div>
        </td>

        <td>
            <div class="expand-btn" id="ebtn_<?= $i ?>">▼</div>
        </td>
    </tr>

    <!-- DETAIL / PERHITUNGAN ROW -->
    <tr class="detail-row" id="dr_<?= $i ?>">
    <td colspan="5">
    <div class="calc-panel">

        <!-- ══ STEP INDICATOR ══ -->
        <div class="steps-nav" id="snav_<?= $i ?>">
            <?php
            $steps = [
                ['num'=>1,'label'=>'Nilai Asli'],
                ['num'=>2,'label'=>'Bobot Kriteria'],
                ['num'=>3,'label'=>'Normalisasi'],
                ['num'=>4,'label'=>'Nilai × Bobot'],
                ['num'=>5,'label'=>'Hasil Akhir'],
            ];
            foreach ($steps as $si => $st): ?>
                <div class="step-item <?= $si===0?'active':'' ?>" id="si_<?= $i ?>_<?= $si ?>"
                     onclick="gotoStep(<?= $i ?>,<?= $si ?>)">
                    <div class="step-dot"><?= $st['num'] ?></div>
                    <div class="step-label"><?= $st['label'] ?></div>
                </div>
                <?php if($si < count($steps)-1): ?>
                <div class="step-line"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- ════════════════════════════
             STEP 1 — NILAI ASLI
        ════════════════════════════ -->
        <div class="step-content active" id="sc_<?= $i ?>_0">
            <div class="step-title">Langkah 1 — Nilai Asli Setiap Kriteria</div>
            <div class="step-desc">
                Data nilai mentah yang dimasukkan untuk siswa
                <strong style="color:#c7d2fe"><?= htmlspecialchars($h['nama']) ?></strong>
                pada masing-masing kriteria penilaian.
            </div>

            <div class="nilai-grid">
                <?php
                $cIdx = 0;
                foreach ($h['detail'] as $kode => $d):
                    $clr = $step_colors[$cIdx % count($step_colors)];
                    $pctBar = round(($d['nilai']/$d['max'])*100);
                    $cIdx++;
                ?>
                <div class="nilai-card" style="border-top:3px solid <?= $clr ?>">
                    <div class="nilai-kode"><?= htmlspecialchars($kode) ?></div>
                    <div class="nilai-nama"><?= htmlspecialchars($label_map[$kode]) ?></div>
                    <div class="nilai-num" style="color:<?= $clr ?>"><?= $d['nilai'] ?></div>
                    <div class="nilai-max">Nilai maks: <?= $d['max'] ?></div>
                    <div class="nilai-bar-bg">
                        <div class="nilai-bar-fill" style="width:<?= $pctBar ?>%;background:<?= $clr ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="step-actions">
                <span class="stn-progress">Langkah 1 dari 5</span>
                <button class="stn-btn stn-next" onclick="nextStep(<?= $i ?>)">Bobot Kriteria →</button>
            </div>
        </div>

        <!-- ════════════════════════════
             STEP 2 — BOBOT KRITERIA
        ════════════════════════════ -->
        <div class="step-content" id="sc_<?= $i ?>_1">
            <div class="step-title">Langkah 2 — Bobot Setiap Kriteria</div>
            <div class="step-desc">
                Setiap kriteria memiliki bobot kepentingan yang telah ditentukan.
                Total bobot harus bernilai 1 (100%).
            </div>

            <div class="formula-box">
                <span class="formula-comment">/* Bobot menunjukkan tingkat kepentingan relatif tiap kriteria */</span><br>
                Σ bobot = <?php
                    $tot_bobot = array_sum(array_column(array_intersect_key(
                        array_combine(array_keys($bobot), $bobot), $h['detail']), null));
                    echo number_format(array_sum(array_map(fn($d)=>$d['bobot'], $h['detail'])), 4);
                ?> ≈ 1.0000
            </div>

            <div class="bobot-table-wrap">
            <table class="bobot-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Kriteria</th>
                        <th>Bobot (w)</th>
                        <th>Persentase</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $cIdx=0;
                foreach ($h['detail'] as $kode => $d):
                    $clr = $step_colors[$cIdx % count($step_colors)];
                    $bpct = round($d['bobot']*100, 1);
                    $cIdx++;
                ?>
                <tr>
                    <td>
                        <span class="bobot-pill" style="background:<?= $clr ?>22;color:<?= $clr ?>;border:1px solid <?= $clr ?>44">
                            <?= htmlspecialchars($kode) ?>
                        </span>
                    </td>
                    <td style="color:#94a3b8"><?= htmlspecialchars($label_map[$kode]) ?></td>
                    <td>
                        <span style="font-family:'JetBrains Mono',monospace;font-size:12px;color:#c7d2fe">
                            <?= number_format($d['bobot'],4) ?>
                        </span>
                    </td>
                    <td>
                        <span style="font-size:12px;color:#94a3b8"><?= $bpct ?>%</span>
                        <span class="bobot-bar-wrap">
                            <span class="bobot-bar-fill" style="width:<?= $bpct ?>%;background:<?= $clr ?>;display:block;height:100%"></span>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <div class="step-actions">
                <button class="stn-btn stn-prev" onclick="prevStep(<?= $i ?>)">← Nilai Asli</button>
                <span class="stn-progress">Langkah 2 dari 5</span>
                <button class="stn-btn stn-next" onclick="nextStep(<?= $i ?>)">Normalisasi →</button>
            </div>
        </div>

        <!-- ════════════════════════════
             STEP 3 — NORMALISASI
        ════════════════════════════ -->
        <div class="step-content" id="sc_<?= $i ?>_2">
            <div class="step-title">Langkah 3 — Normalisasi Nilai</div>
            <div class="step-desc">
                Normalisasi dilakukan dengan membagi nilai siswa dengan nilai maksimum
                pada setiap kriteria: <code style="background:rgba(99,102,241,.15);color:#c7d2fe;padding:1px 6px;border-radius:4px">r<sub>ij</sub> = x<sub>ij</sub> / max(x<sub>j</sub>)</code>
            </div>

            <div class="norm-cards">
                <?php
                $cIdx=0;
                foreach ($h['detail'] as $kode => $d):
                    $clr   = $step_colors[$cIdx % count($step_colors)];
                    $npct  = round($d['normal']*100);
                    $cIdx++;
                ?>
                <div class="norm-card">
                    <div class="norm-card-head">
                        <div>
                            <div class="norm-kode" style="color:<?= $clr ?>"><?= htmlspecialchars($kode) ?></div>
                            <div class="norm-nama"><?= htmlspecialchars($label_map[$kode]) ?></div>
                        </div>
                    </div>
                    <div class="norm-formula">
                        <div class="norm-fraction">
                            <span style="color:#fbbf24"><?= $d['nilai'] ?></span>
                            <div class="norm-line"></div>
                            <span style="color:#94a3b8"><?= $d['max'] ?></span>
                        </div>
                    </div>
                    <div class="norm-num-big" style="color:<?= $clr ?>">
                        <?= number_format($d['normal'],4) ?>
                    </div>
                    <div class="norm-progress">
                        <div class="norm-fill" style="width:<?= $npct ?>%;background:<?= $clr ?>"></div>
                    </div>
                    <div style="font-size:10px;color:#475569;margin-top:4px;text-align:right"><?= $npct ?>%</div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="step-actions">
                <button class="stn-btn stn-prev" onclick="prevStep(<?= $i ?>)">← Bobot</button>
                <span class="stn-progress">Langkah 3 dari 5</span>
                <button class="stn-btn stn-next" onclick="nextStep(<?= $i ?>)">Nilai × Bobot →</button>
            </div>
        </div>

        <!-- ════════════════════════════
             STEP 4 — PERKALIAN BOBOT
        ════════════════════════════ -->
        <div class="step-content" id="sc_<?= $i ?>_3">
            <div class="step-title">Langkah 4 — Perkalian Nilai Normal × Bobot</div>
            <div class="step-desc">
                Setiap nilai yang telah dinormalisasi dikalikan dengan bobot kriterianya masing-masing:
                <code style="background:rgba(99,102,241,.15);color:#c7d2fe;padding:1px 6px;border-radius:4px">v<sub>ij</sub> = r<sub>ij</sub> × w<sub>j</sub></code>
            </div>

            <div class="weight-cards">
                <?php
                $cIdx=0;
                foreach ($h['detail'] as $kode => $d):
                    $clr = $step_colors[$cIdx % count($step_colors)];
                    $wpct = round($d['hasil']*100 / max($h['total'],0.001));
                    $cIdx++;
                ?>
                <div class="weight-card" style="border-top:2px solid <?= $clr ?>33">
                    <div class="weight-kode" style="color:<?= $clr ?>"><?= htmlspecialchars($label_map[$kode]) ?></div>
                    <div class="weight-eq">
                        <span class="eq-hl"><?= number_format($d['normal'],4) ?></span>
                        <span class="eq-op"> × </span>
                        <span class="eq-hl"><?= number_format($d['bobot'],4) ?></span>
                        <span class="eq-op"> = </span>
                        <span class="eq-res"><?= number_format($d['hasil'],4) ?></span>
                    </div>
                    <div class="weight-mini-bar">
                        <div class="weight-mini-fill" style="width:<?= min($wpct,100) ?>%;background:<?= $clr ?>"></div>
                    </div>
                    <div style="font-size:10px;color:#475569;margin-top:4px;text-align:right">
                        Kontribusi: <?= number_format($d['hasil']*100,2) ?>%
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="step-actions">
                <button class="stn-btn stn-prev" onclick="prevStep(<?= $i ?>)">← Normalisasi</button>
                <span class="stn-progress">Langkah 4 dari 5</span>
                <button class="stn-btn stn-next" onclick="nextStep(<?= $i ?>)">Hasil Akhir →</button>
            </div>
        </div>

        <!-- ════════════════════════════
             STEP 5 — HASIL AKHIR
        ════════════════════════════ -->
        <div class="step-content" id="sc_<?= $i ?>_4">
            <div class="step-title">Langkah 5 — Penjumlahan & Nilai Akhir</div>
            <div class="step-desc">
                Jumlahkan semua hasil perkalian untuk mendapatkan nilai akhir SMART siswa.
                Semakin tinggi nilai, semakin baik peringkat siswa.
            </div>

            <div class="sum-table-wrap">
            <table class="sum-table">
                <thead>
                    <tr>
                        <th style="text-align:left">Kriteria</th>
                        <th>Nilai</th>
                        <th>÷ Maks</th>
                        <th>Normal (r)</th>
                        <th>Bobot (w)</th>
                        <th>r × w</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $cIdx=0;
                foreach ($h['detail'] as $kode => $d):
                    $clr = $step_colors[$cIdx % count($step_colors)];
                    $cIdx++;
                ?>
                <tr>
                    <td>
                        <span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:<?= $clr ?>;margin-right:6px;vertical-align:middle"></span>
                        <?= htmlspecialchars($label_map[$kode]) ?>
                    </td>
                    <td style="color:#fbbf24"><?= $d['nilai'] ?></td>
                    <td style="color:#94a3b8"><?= $d['max'] ?></td>
                    <td style="color:#c7d2fe"><?= number_format($d['normal'],4) ?></td>
                    <td style="color:#94a3b8"><?= number_format($d['bobot'],4) ?></td>
                    <td style="color:#4ade80"><?= number_format($d['hasil'],4) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr class="sum-total">
                    <td colspan="5" style="text-align:right;padding-right:8px;color:#818cf8">Nilai Akhir (V)</td>
                    <td style="color:#4ade80;font-size:14px"><?= number_format($h['total'],4) ?></td>
                </tr>
                </tfoot>
            </table>
            </div>

            <!-- Hasil Akhir Highlight Box -->
            <div class="result-box" style="margin-top:16px">
                <div>
                    <div class="result-label">Nilai SMART Akhir</div>
                    <div class="result-score"><?= number_format($h['total']*100,2) ?>%</div>
                    <div style="font-size:12px;color:#64748b;margin-top:4px">
                        Peringkat ke-<strong style="color:#818cf8"><?= $rank ?></strong>
                        dari <?= count($hasil) ?> siswa
                    </div>
                </div>
                <div class="result-formula-final">
                    <span class="formula-comment">/* Formula Akhir SMART */</span><br>
                    V(<?= htmlspecialchars(getInitials($h['nama'])) ?>) = Σ (r × w)<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;= <?php
                        $parts = [];
                        foreach ($h['detail'] as $kode => $d) {
                            $parts[] = '<span class="rf-num">'.number_format($d['hasil'],4).'</span>';
                        }
                        echo implode(' <span class="rf-hl">+</span> ', $parts);
                    ?><br>
                    &nbsp;&nbsp;&nbsp;&nbsp;= <span class="rf-res"><?= number_format($h['total'],4) ?></span>
                    &nbsp;(<span class="rf-res"><?= number_format($h['total']*100,2) ?>%</span>)
                </div>
            </div>

            <div class="step-actions">
                <button class="stn-btn stn-prev" onclick="prevStep(<?= $i ?>)">← Nilai × Bobot</button>
                <span class="stn-progress">Selesai ✓</span>
                <button class="stn-btn stn-next" onclick="toggleCalc(<?= $i ?>)" style="background:rgba(20,184,166,.2);border-color:rgba(20,184,166,.4);color:#2dd4bf">
                    Tutup ✕
                </button>
            </div>
        </div>

    </div><!-- .calc-panel -->
    </td>
    </tr>

    <?php endforeach; ?>
    </tbody>
</table>
</div><!-- #tableView -->

<!-- PODIUM VIEW -->
<div class="podium-section" id="podiumView">
    <?php
    $top3  = array_slice($hasil, 0, 3);
    $order = [];
    if (count($top3)>=2) $order[]=['data'=>$top3[1],'cls'=>'p2','icon'=>'🥈'];
    if (count($top3)>=1) $order[]=['data'=>$top3[0],'cls'=>'p1','icon'=>'🥇'];
    if (count($top3)>=3) $order[]=['data'=>$top3[2],'cls'=>'p3','icon'=>'🥉'];
    ?>
    <div class="podium-grid">
        <?php foreach ($order as $po): ?>
        <div class="podium-item <?= $po['cls'] ?>">
            <div class="podium-block"><?= $po['icon'] ?></div>
            <div class="podium-name"><?= htmlspecialchars($po['data']['nama']) ?></div>
            <div class="podium-kelas"><?= htmlspecialchars($po['data']['kelas']) ?></div>
            <div class="podium-score"><?= number_format($po['data']['total']*100,2) ?>%</div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- EXPORT -->
<div class="export-row">
    <a class="export-btn" href="export_smart.php?kelas=<?= urlencode($filter_kelas) ?>">
        <i class="fas fa-download"></i> Export CSV
    </a>
    <button class="export-btn" onclick="window.print()">
        <i class="fas fa-print"></i> Cetak
    </button>
</div>

</div><!-- .smart-page -->
</main>

<script>
/* ─── State tracker per baris ─── */
const stepState = {};   /* {rowIdx: currentStep} */

function toggleCalc(i) {
    const dr   = document.getElementById('dr_' + i);
    const btn  = document.getElementById('ebtn_' + i);
    const sr   = document.getElementById('sr_' + i);
    const open = dr.classList.contains('open');

    if (open) {
        dr.classList.remove('open');
        dr.style.display = 'none';
        btn.classList.remove('active');
        btn.textContent  = '▼';
        sr.classList.remove('open');
    } else {
        dr.classList.add('open');
        dr.style.display = 'table-row';
        btn.classList.add('active');
        btn.textContent  = '▲';
        sr.classList.add('open');
        if (stepState[i] === undefined) {
            stepState[i] = 0;
            gotoStep(i, 0);
        }
    }
}

function gotoStep(rowIdx, stepIdx) {
    const TOTAL = 5;

    /* update step indicator */
    for (let s = 0; s < TOTAL; s++) {
        const item = document.getElementById('si_' + rowIdx + '_' + s);
        if (!item) continue;
        item.classList.remove('active','done');
        if (s < stepIdx)      item.classList.add('done');
        else if (s === stepIdx) item.classList.add('active');
    }

    /* hide all content, show current */
    for (let s = 0; s < TOTAL; s++) {
        const sc = document.getElementById('sc_' + rowIdx + '_' + s);
        if (sc) sc.classList.remove('active');
    }
    const current = document.getElementById('sc_' + rowIdx + '_' + stepIdx);
    if (current) current.classList.add('active');

    stepState[rowIdx] = stepIdx;
}

function nextStep(rowIdx) {
    const cur = stepState[rowIdx] ?? 0;
    if (cur < 4) gotoStep(rowIdx, cur + 1);
}

function prevStep(rowIdx) {
    const cur = stepState[rowIdx] ?? 0;
    if (cur > 0) gotoStep(rowIdx, cur - 1);
}

/* ─── Sort / View ─── */
function toggleSort() {
    const inp = document.getElementById('sortInput');
    inp.value = inp.value === 'desc' ? 'asc' : 'desc';
    document.getElementById('filterForm').submit();
}

function setView(v) {
    const tv = document.getElementById('tableView');
    const pv = document.getElementById('podiumView');
    const bt = document.getElementById('btnTable');
    const bp = document.getElementById('btnPodium');
    if (v === 'table') {
        tv.style.display = '';
        pv.classList.remove('active');
        bt.classList.add('active');
        bp.classList.remove('active');
    } else {
        tv.style.display = 'none';
        pv.classList.add('active');
        bp.classList.add('active');
        bt.classList.remove('active');
    }
}
</script>

<script src="assets/script.js"></script>
</body>
</html>