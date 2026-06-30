<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['role'] == 'kepsek') {
    header('Location: dashboard.php');
    exit;
}

// Ambil semua siswa + status sudah/belum dinilai
$siswa = $pdo->query("
    SELECT s.*,
           CASE WHEN COUNT(n.id_siswa) > 0 THEN 1 ELSE 0 END as sudah_dinilai
    FROM siswa s
    LEFT JOIN nilai n ON s.id_siswa = n.id_siswa
    GROUP BY s.id_siswa
    ORDER BY s.kelas ASC, s.nama_siswa ASC
")->fetchAll(PDO::FETCH_ASSOC);

$kriteria = $pdo->query("SELECT * FROM kriteria ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$total    = count($kriteria);

// Daftar kelas unik
$kelasList = array_values(array_unique(array_column($siswa, 'kelas')));
sort($kelasList);

// Group siswa per kelas untuk panel
$siswaPerKelas = [];
foreach ($siswa as $s) {
    $siswaPerKelas[$s['kelas']][] = $s;
}

// Statistik
$totalSudah = count(array_filter($siswa, fn($s) => $s['sudah_dinilai']));
$totalBelum = count($siswa) - $totalSudah;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Input Penilaian · SPK SMART</title>

<link rel="stylesheet" href="assets/style.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════
   RESET & BASE
══════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.main-content {
    font-family: 'Plus Jakarta Sans', sans-serif;
    padding: 32px 28px;
    color: #e2e8f0;
}

/* ══════════════════════════════════════
   PAGE HEADER
══════════════════════════════════════ */
.page-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 28px;
    animation: fadeUp 0.5s ease both;
}

.page-icon {
    width: 52px; height: 52px;
    background: linear-gradient(135deg, #2563eb, #1e40af);
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 0 24px rgba(37,99,235,0.45);
    flex-shrink: 0;
}
.page-icon i        { font-size: 20px; color: white; }
.page-heading       { font-size: 22px; font-weight: 700; color: #e2e8f0; }
.page-subheading    { font-size: 13px; color: #475569; margin-top: 3px; }

/* ══════════════════════════════════════
   SUMMARY CARDS
══════════════════════════════════════ */
.summary-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 24px;
    animation: fadeUp 0.5s 0.05s ease both;
}

.s-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 16px;
    padding: 18px 20px;
    text-align: center;
    transition: border-color 0.2s;
}
.s-card:hover { border-color: rgba(255,255,255,0.12); }
.s-card .s-label {
    font-size: 11px; font-weight: 600;
    letter-spacing: 0.10em; text-transform: uppercase;
    color: #475569; margin-bottom: 8px;
}
.s-card .s-value {
    font-size: 28px; font-weight: 700;
    font-family: 'JetBrains Mono', monospace;
    line-height: 1;
}
.s-card.blue  .s-value { color: #60a5fa; }
.s-card.green .s-value { color: #34d399; }
.s-card.amber .s-value { color: #fbbf24; }

/* ══════════════════════════════════════
   MAIN CARDS
══════════════════════════════════════ */
.spk-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 20px;
}
.spk-card.anim-1 { animation: fadeUp 0.5s 0.10s ease both; }
.spk-card.anim-2 { animation: fadeUp 0.5s 0.15s ease both; }

.card-label {
    font-size: 11px; font-weight: 600;
    letter-spacing: 0.12em; text-transform: uppercase;
    color: #475569; margin-bottom: 14px;
}

/* ══════════════════════════════════════
   SELECT2 OVERRIDES
══════════════════════════════════════ */
.select2-container--default .select2-selection--single {
    height: 46px !important;
    background: rgba(255,255,255,0.05) !important;
    border: 1px solid rgba(255,255,255,0.10) !important;
    border-radius: 12px !important;
    display: flex; align-items: center;
    transition: all 0.2s;
}
.select2-container--default .select2-selection--single:hover {
    border-color: rgba(255,255,255,0.18) !important;
}
.select2-container--default.select2-container--open .select2-selection--single {
    border-color: rgba(37,99,235,0.60) !important;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.15) !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #e2e8f0 !important;
    line-height: 46px !important;
    padding-left: 16px !important;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 14px;
}
.select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: #475569 !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 46px !important; right: 12px !important;
}
.select2-dropdown {
    background: #1e293b !important;
    border: 1px solid rgba(255,255,255,0.10) !important;
    border-radius: 12px !important;
    overflow: hidden;
    box-shadow: 0 16px 40px rgba(0,0,0,0.50) !important;
}
.select2-search--dropdown .select2-search__field {
    background: rgba(255,255,255,0.05) !important;
    border: 1px solid rgba(255,255,255,0.10) !important;
    border-radius: 8px !important;
    color: #e2e8f0 !important;
    padding: 8px 12px !important;
    font-family: 'Plus Jakarta Sans', sans-serif;
}
.select2-results__option {
    color: #cbd5e1 !important;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 14px;
    padding: 10px 14px !important;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background: rgba(37,99,235,0.35) !important; color: #e2e8f0 !important;
}
.select2-container--default .select2-results__option[aria-selected=true] {
    background: rgba(37,99,235,0.20) !important;
}

/* STUDENT CHIPS */
.student-chips { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
.chip {
    display: flex; align-items: center; gap: 7px;
    background: rgba(37,99,235,0.12);
    border: 1px solid rgba(37,99,235,0.25);
    border-radius: 10px; padding: 7px 13px;
    font-size: 12px; font-weight: 500; color: #93c5fd;
}
.chip i { font-size: 11px; color: #60a5fa; }

/* ══════════════════════════════════════
   KRITERIA TABLE
══════════════════════════════════════ */
.k-header-row {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 16px;
}
.progress-pill {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px; font-weight: 500;
    background: rgba(37,99,235,0.15); color: #60a5fa;
    border: 1px solid rgba(37,99,235,0.25);
    border-radius: 20px; padding: 4px 13px;
}

.k-row {
    display: grid;
    grid-template-columns: 70px 1fr auto 160px;
    gap: 12px; align-items: center;
    padding: 14px 0;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    opacity: 0;
    animation: slideIn 0.4s ease forwards;
}
.k-row:last-child { border-bottom: none; }

<?php for ($i = 0; $i < $total; $i++): ?>
.k-row:nth-child(<?= $i + 1 ?>) { animation-delay: <?= ($i + 1) * 0.07 ?>s; }
<?php endfor; ?>

.k-code {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px; font-weight: 500;
    background: rgba(255,255,255,0.06);
    border-radius: 8px; padding: 6px 10px;
    color: #94a3b8; text-align: center;
}
.k-name { font-size: 14px; font-weight: 500; color: #cbd5e1; }

/* Bobot badge — warna berdasarkan besar bobot */
.bobot-pill {
    font-size: 11px; font-weight: 600;
    padding: 4px 11px; border-radius: 20px; white-space: nowrap;
}
.bobot-high { background: rgba(16,185,129,0.12); color: #34d399; border: 1px solid rgba(16,185,129,0.22); }
.bobot-mid  { background: rgba(59,130,246,0.12); color: #60a5fa; border: 1px solid rgba(59,130,246,0.22); }
.bobot-low  { background: rgba(148,163,184,0.10); color: #94a3b8; border: 1px solid rgba(148,163,184,0.15); }

/* NILAI INPUT */
.nilai-wrap { position: relative; }

.nilai-input {
    width: 100%;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 10px;
    padding: 10px 12px;
    color: #e2e8f0;
    font-size: 15px; font-weight: 600;
    font-family: 'JetBrains Mono', monospace;
    text-align: center;
    outline: none;
    transition: all 0.2s;
    -moz-appearance : textfield;
}
.nilai-input::-webkit-inner-spin-button,
.nilai-input::-webkit-outer-spin-button { -webkit-appearance: none; }
.nilai-input:focus {
    border-color: rgba(37,99,235,0.60);
    background: rgba(37,99,235,0.08);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
    color: #93c5fd;
}

.nilai-bar {
    height: 3px; border-radius: 2px;
    background: rgba(255,255,255,0.06);
    margin-top: 5px; overflow: hidden;
}
.nilai-fill {
    height: 100%; border-radius: 2px; width: 0%;
    background: linear-gradient(90deg, #1d4ed8, #3b82f6);
    transition: width 0.35s ease, background 0.35s ease;
}

/* ══════════════════════════════════════
   SUBMIT BUTTON
══════════════════════════════════════ */
.submit-btn {
    width: 100%;
    background: linear-gradient(135deg, #2563eb, #1e40af);
    color: white; border: none; border-radius: 14px;
    padding: 16px 24px;
    font-size: 15px; font-weight: 700;
    font-family: 'Plus Jakarta Sans', sans-serif;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    transition: all 0.2s;
    box-shadow: 0 4px 20px rgba(37,99,235,0.35);
    position: relative; overflow: hidden;
    animation: fadeUp 0.5s 0.2s ease both;
}
.submit-btn::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.10), transparent);
    pointer-events: none;
}
.submit-btn:hover  { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(37,99,235,0.50); }
.submit-btn:active { transform: translateY(0); }

/* ══════════════════════════════════════
   TOAST
══════════════════════════════════════ */
.toast {
    position: fixed; bottom: 28px; left: 50%;
    transform: translateX(-50%) translateY(80px);
    color: white; padding: 13px 24px; border-radius: 12px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 14px; font-weight: 600;
    display: flex; align-items: center; gap: 9px;
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    z-index: 9999; pointer-events: none;
}
.toast.show { transform: translateX(-50%) translateY(0); }

/* ══════════════════════════════════════
   ANIMATIONS
══════════════════════════════════════ */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes slideIn {
    from { opacity: 0; transform: translateX(-10px); }
    to   { opacity: 1; transform: translateX(0); }
}

/* ══════════════════════════════════════
   DARK MODE COMPAT
══════════════════════════════════════ */
[data-theme="dark"] .spk-card,
[data-theme="dark"] .s-card {
    background: rgba(255,255,255,0.02) !important;
    border-color: rgba(255,255,255,0.05) !important;
}

/* ══════════════════════════════════════
   FILTER KELAS TABS
══════════════════════════════════════ */
.kelas-filter-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.kelas-tab {
    display: flex; align-items: center; gap: 6px;
    padding: 7px 16px;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.03);
    color: #64748b;
    font-size: 12px; font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    user-select: none;
}
.kelas-tab:hover {
    border-color: rgba(37,99,235,0.35);
    color: #93c5fd;
    background: rgba(37,99,235,0.08);
}
.kelas-tab.active {
    background: rgba(37,99,235,0.18);
    border-color: rgba(37,99,235,0.45);
    color: #60a5fa;
    box-shadow: 0 0 14px rgba(37,99,235,0.15);
}
.kelas-tab .kt-count {
    background: rgba(255,255,255,0.08);
    border-radius: 6px; padding: 1px 7px;
    font-size: 10px; font-weight: 700;
    color: #475569;
}
.kelas-tab.active .kt-count {
    background: rgba(37,99,235,0.25); color: #93c5fd;
}

/* Search bar */
.search-bar-wrap {
    position: relative; margin-bottom: 14px;
}
.search-bar-wrap i {
    position: absolute; left: 14px; top: 50%;
    transform: translateY(-50%);
    color: #475569; font-size: 13px; pointer-events: none;
}
.siswa-search-input {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 12px;
    padding: 11px 14px 11px 38px;
    color: #e2e8f0;
    font-size: 13px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    outline: none;
    transition: all 0.2s;
}
.siswa-search-input:focus {
    border-color: rgba(37,99,235,0.5);
    background: rgba(37,99,235,0.06);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.10);
}
.siswa-search-input::placeholder { color: #334155; }

/* Daftar siswa panel */
.siswa-panel {
    display: flex; flex-direction: column; gap: 6px;
    max-height: 320px; overflow-y: auto;
    padding-right: 4px;
}
.siswa-panel::-webkit-scrollbar { width: 4px; }
.siswa-panel::-webkit-scrollbar-track { background: transparent; }
.siswa-panel::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.09); border-radius: 99px; }

.siswa-item {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 14px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.05);
    background: rgba(255,255,255,0.02);
    cursor: pointer;
    transition: all 0.18s;
    user-select: none;
}
.siswa-item:hover {
    background: rgba(37,99,235,0.10);
    border-color: rgba(37,99,235,0.28);
    transform: translateX(3px);
}
.siswa-item.selected {
    background: rgba(37,99,235,0.18);
    border-color: rgba(37,99,235,0.50);
    box-shadow: 0 0 16px rgba(37,99,235,0.15);
}
.si-avatar {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; flex-shrink: 0;
}
.si-info { flex: 1; }
.si-nama { font-size: 13px; font-weight: 600; color: #e2e8f0; }
.si-kelas { font-size: 11px; color: #475569; margin-top: 1px; }
.si-badge {
    font-size: 10px; font-weight: 700; padding: 3px 9px;
    border-radius: 99px; white-space: nowrap; flex-shrink: 0;
}
.si-badge.done { background: rgba(16,185,129,0.12); color: #34d399; border: 1px solid rgba(16,185,129,0.22); }
.si-badge.todo { background: rgba(245,158,11,0.10); color: #fbbf24; border: 1px solid rgba(245,158,11,0.18); }

.no-result {
    text-align: center; padding: 28px;
    color: #334155; font-size: 13px;
}
.no-result i { font-size: 24px; display: block; margin-bottom: 8px; color: #1e293b; }

/* Grid layout untuk form */
.form-grid {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 16px;
    align-items: start;
}
@media(max-width: 900px) {
    .form-grid { grid-template-columns: 1fr; }
}

/* Panel kiri sticky */
.sticky-panel { position: sticky; top: 20px; }

/* Counter info bar */
.kelas-info-bar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 10px;
}
.kelas-info-label { font-size: 12px; color: #475569; }
.kelas-info-label strong { color: #94a3b8; }
.kelas-empty-badge {
    font-size: 10px; font-weight: 700; padding: 2px 9px;
    border-radius: 99px; background: rgba(244,63,94,0.1);
    color: #fb7185; border: 1px solid rgba(244,63,94,0.2);
}

/* ══════════════════════════════════════
   LIGHT MODE IMPROVEMENTS
══════════════════════════════════════ */

:root {
    --light-bg: #f8fafc;
    --light-card: #ffffff;
    --light-border: #dbe3ee;
    --light-text: #0f172a;
    --light-text-soft: #475569;
    --light-muted: #64748b;
}

/* MAIN */
body:not([data-theme="dark"]) .main-content {
    color: var(--light-text);
}

/* PAGE */
body:not([data-theme="dark"]) .page-heading {
    color: #0f172a;
}

body:not([data-theme="dark"]) .page-subheading,
body:not([data-theme="dark"]) .card-label,
body:not([data-theme="dark"]) .kelas-info-label,
body:not([data-theme="dark"]) .si-kelas,
body:not([data-theme="dark"]) .s-card .s-label {
    color: var(--light-text-soft);
}

/* CARDS */
body:not([data-theme="dark"]) .spk-card,
body:not([data-theme="dark"]) .s-card {
    background: rgba(255,255,255,0.96);
    border: 1px solid var(--light-border);
    box-shadow: 0 4px 18px rgba(15,23,42,0.05);
}

/* SUMMARY */
body:not([data-theme="dark"]) .s-card:hover {
    border-color: #93c5fd;
}

/* TABS */
body:not([data-theme="dark"]) .kelas-tab {
    background: #ffffff;
    border-color: #dbe3ee;
    color: #334155;
}

body:not([data-theme="dark"]) .kelas-tab:hover {
    background: #eff6ff;
    color: #2563eb;
}

body:not([data-theme="dark"]) .kelas-tab.active {
    background: #dbeafe;
    color: #1d4ed8;
    border-color: #93c5fd;
}

body:not([data-theme="dark"]) .kelas-tab .kt-count {
    background: #e2e8f0;
    color: #334155;
}

/* SEARCH */
body:not([data-theme="dark"]) .siswa-search-input {
    background: #ffffff;
    border-color: #cbd5e1;
    color: #0f172a;
}

body:not([data-theme="dark"]) .siswa-search-input::placeholder {
    color: #64748b;
}

body:not([data-theme="dark"]) .search-bar-wrap i {
    color: #64748b;
}

/* SISWA ITEM */
body:not([data-theme="dark"]) .siswa-item {
    background: #ffffff;
    border-color: #dbe3ee;
}

body:not([data-theme="dark"]) .siswa-item:hover {
    background: #eff6ff;
    border-color: #93c5fd;
}

body:not([data-theme="dark"]) .siswa-item.selected {
    background: #dbeafe;
    border-color: #60a5fa;
}

body:not([data-theme="dark"]) .si-nama {
    color: #0f172a;
}

/* KRITERIA */
body:not([data-theme="dark"]) .k-row {
    border-bottom: 1px solid #e2e8f0;
}

body:not([data-theme="dark"]) .k-code {
    background: #f1f5f9;
    color: #334155;
}

body:not([data-theme="dark"]) .k-name {
    color: #0f172a;
}

/* INPUT */
body:not([data-theme="dark"]) .nilai-input {
    background: #ffffff;
    border-color: #cbd5e1;
    color: #0f172a;
}

body:not([data-theme="dark"]) .nilai-input:focus {
    background: #eff6ff;
    color: #1d4ed8;
    border-color: #60a5fa;
}

/* PROGRESS */
body:not([data-theme="dark"]) .progress-pill {
    background: #dbeafe;
    border-color: #93c5fd;
    color: #1d4ed8;
}

body:not([data-theme="dark"]) #avgPill {
    background: #f1f5f9 !important;
    border-color: #cbd5e1 !important;
    color: #334155 !important;
}

/* SELECTED CHIP */
body:not([data-theme="dark"]) .chip {
    background: #dbeafe;
    border-color: #93c5fd;
    color: #1d4ed8;
}

/* NO RESULT */
body:not([data-theme="dark"]) .no-result {
    color: #64748b;
}

body:not([data-theme="dark"]) .no-result i {
    color: #94a3b8;
}

/* SELECT2 */
body:not([data-theme="dark"]) .select2-container--default .select2-selection--single {
    background: #ffffff !important;
    border-color: #cbd5e1 !important;
}

body:not([data-theme="dark"]) .select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #0f172a !important;
}

body:not([data-theme="dark"]) .select2-dropdown {
    background: #ffffff !important;
    border-color: #cbd5e1 !important;
}

body:not([data-theme="dark"]) .select2-results__option {
    color: #0f172a !important;
}

body:not([data-theme="dark"]) .select2-search--dropdown .select2-search__field {
    background: #ffffff !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}

/* SCROLLBAR */
body:not([data-theme="dark"]) .siswa-panel::-webkit-scrollbar-thumb {
    background: #cbd5e1;
}
</style>
</head>

<body>
<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-icon">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div>
            <div class="page-heading">Input Penilaian Siswa</div>
            <div class="page-subheading">Sistem Pendukung Keputusan &middot; Metode SMART</div>
        </div>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="summary-row">
        <div class="s-card blue">
            <div class="s-label">Total Kriteria</div>
            <div class="s-value"><?= $total ?></div>
        </div>
        <div class="s-card green">
            <div class="s-label">Sudah Dinilai</div>
            <div class="s-value"><?= $totalSudah ?></div>
        </div>
        <div class="s-card amber">
            <div class="s-label">Belum Dinilai</div>
            <div class="s-value"><?= $totalBelum ?></div>
        </div>
    </div>

    <form method="POST" action="simpan_nilai.php" id="formNilai">
    <input type="hidden" name="id_siswa" id="hiddenSiswa">

    <div class="form-grid">

        <!-- ══════════════════════════════════
             PANEL KIRI — FILTER + DAFTAR SISWA
        ══════════════════════════════════ -->
        <div class="sticky-panel">
            <div class="spk-card anim-1" style="margin-bottom:0;">

                <div class="card-label">Filter & Pilih Siswa</div>

                <!-- FILTER KELAS TABS -->
                <div class="kelas-filter-wrap" id="kelasTabs">
                    <div class="kelas-tab active" data-kelas="semua" onclick="filterKelas('semua')">
                        <i class="fas fa-users" style="font-size:11px;"></i>
                        Semua
                        <span class="kt-count"><?= count($siswa) ?></span>
                    </div>
                    <?php foreach($kelasList as $kl):
                        $jumlah = count($siswaPerKelas[$kl] ?? []);
                        $belumKelas = count(array_filter($siswaPerKelas[$kl] ?? [], fn($s)=>!$s['sudah_dinilai']));
                    ?>
                    <div class="kelas-tab" data-kelas="<?= htmlspecialchars($kl) ?>" onclick="filterKelas('<?= htmlspecialchars($kl) ?>')">
                        <i class="fas fa-door-open" style="font-size:11px;"></i>
                        <?= htmlspecialchars($kl) ?>
                        <span class="kt-count"><?= $jumlah ?></span>
                        <?php if($belumKelas > 0): ?>
                        <span style="width:7px;height:7px;border-radius:50%;background:#fbbf24;flex-shrink:0;box-shadow:0 0 6px rgba(251,191,36,.6);"></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- SEARCH INPUT -->
                <div class="search-bar-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" class="siswa-search-input" id="siswaSearchInput"
                           placeholder="Cari nama siswa..." oninput="filterSiswa()">
                </div>

                <!-- INFO BAR -->
                <div class="kelas-info-bar">
                    <div class="kelas-info-label">Menampilkan <strong id="countShown"><?= count($siswa) ?></strong> siswa</div>
                    <div id="belumBadge" class="kelas-empty-badge" style="display:<?= $totalBelum>0?'block':'none' ?>;">
                        <?= $totalBelum ?> belum dinilai
                    </div>
                </div>

                <!-- DAFTAR SISWA -->
                <div class="siswa-panel" id="siswaPanelList">
                    <?php
                    $avatarColors = [
                        ['rgba(37,99,235,.18)','#60a5fa'],
                        ['rgba(16,185,129,.18)','#34d399'],
                        ['rgba(139,92,246,.18)','#a78bfa'],
                        ['rgba(245,158,11,.18)','#fbbf24'],
                        ['rgba(244,63,94,.18)','#fb7185'],
                        ['rgba(6,182,212,.18)','#22d3ee'],
                        ['rgba(249,115,22,.18)','#f97316'],
                        ['rgba(99,102,241,.18)','#818cf8'],
                    ];
                    foreach($siswa as $idx => $s):
                        [$avBg,$avC] = $avatarColors[$idx % count($avatarColors)];
                        $inisial = strtoupper(substr($s['nama_siswa'],0,1));
                        $badgeClass = $s['sudah_dinilai'] ? 'done' : 'todo';
                        $badgeText  = $s['sudah_dinilai'] ? '✓ Dinilai' : '⏳ Belum';
                    ?>
                    <div class="siswa-item"
                         data-id="<?= $s['id_siswa'] ?>"
                         data-nama="<?= htmlspecialchars($s['nama_siswa']) ?>"
                         data-kelas="<?= htmlspecialchars($s['kelas']) ?>"
                         data-dinilai="<?= $s['sudah_dinilai'] ?>"
                         onclick="pilihSiswa(this)">
                        <div class="si-avatar" style="background:<?= $avBg ?>;color:<?= $avC ?>;"><?= $inisial ?></div>
                        <div class="si-info">
                            <div class="si-nama"><?= htmlspecialchars($s['nama_siswa']) ?></div>
                            <div class="si-kelas">Kelas <?= htmlspecialchars($s['kelas']) ?></div>
                        </div>
                        <span class="si-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                    </div>
                    <?php endforeach; ?>

                    <div class="no-result" id="noResult" style="display:none;">
                        <i class="fas fa-search-minus"></i>
                        Tidak ada siswa ditemukan
                    </div>
                </div>

            </div><!-- /.spk-card -->
        </div>

        <!-- ══════════════════════════════════
             PANEL KANAN — FORM INPUT NILAI
        ══════════════════════════════════ -->
        <div>

            <!-- SELECTED STUDENT INFO -->
            <div class="spk-card anim-1" id="selectedStudentCard" style="margin-bottom:16px;display:none;">
                <div class="card-label">Siswa Dipilih</div>
                <div class="student-chips" id="studentChips"></div>
            </div>

            <!-- TABEL KRITERIA -->
            <div class="spk-card anim-2">
                <div class="k-header-row">
                    <div class="card-label" style="margin:0;">Kriteria Penilaian</div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="progress-pill" id="progressPill">0 / <?= $total ?> diisi</div>
                        <div style="font-family:'JetBrains Mono',monospace;font-size:11px;color:#475569;
                            background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);
                            border-radius:20px;padding:4px 13px;" id="avgPill">avg: —</div>
                    </div>
                </div>

                <?php
                function bobotClass(float $b): string {
                    $p = $b * 100;
                    if ($p >= 25) return 'bobot-high';
                    if ($p >= 15) return 'bobot-mid';
                    return 'bobot-low';
                }
                ?>

                <?php foreach ($kriteria as $idx => $k):
                    $pct = round((float)$k['bobot'] * 100);
                    $cls = bobotClass((float)$k['bobot']);
                ?>
                <div class="k-row">
                    <div class="k-code"><?= htmlspecialchars($k['kode']) ?></div>
                    <div class="k-name"><?= htmlspecialchars($k['nama_kriteria']) ?></div>
                    <div class="bobot-pill <?= $cls ?>"><?= $pct ?>%</div>
                    <div class="nilai-wrap">
                        <input
                            class="nilai-input"
                            type="number"
                            name="nilai[<?= htmlspecialchars($k['kode']) ?>]"
                            min="0" max="100"
                            placeholder="0–100"
                            data-idx="<?= $idx ?>"
                            oninput="onNilai(this)"
                            required>
                        <div class="nilai-bar">
                            <div class="nilai-fill" id="bar<?= $idx ?>"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

            </div><!-- /.spk-card kriteria -->

            <!-- SUBMIT -->
            <button type="submit" class="submit-btn">
                <i class="fa-solid fa-floppy-disk"></i>
                Simpan Nilai
            </button>

        </div><!-- /panel kanan -->
    </div><!-- /form-grid -->
    </form>

</main>

<!-- TOAST -->
<div class="toast" id="toast">
    <i class="fa-solid fa-circle-check" id="toastIcon" style="font-size:15px;"></i>
    <span id="toastMsg">Nilai berhasil disimpan!</span>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="assets/script.js"></script>

<script>
/* ══════════════════════════════════════
   STATE
══════════════════════════════════════ */
const TOTAL      = <?= $total ?>;
const vals       = new Array(TOTAL).fill(null);
let   activeKelas = 'semua';
let   selectedId  = null;

/* ══════════════════════════════════════
   FILTER KELAS
══════════════════════════════════════ */
function filterKelas(kelas) {
    activeKelas = kelas;

    // Update tab aktif
    document.querySelectorAll('.kelas-tab').forEach(t => {
        t.classList.toggle('active', t.dataset.kelas === kelas);
    });

    // Kosongkan search
    document.getElementById('siswaSearchInput').value = '';
    filterSiswa();
}

/* ══════════════════════════════════════
   FILTER SISWA (SEARCH + KELAS)
══════════════════════════════════════ */
function filterSiswa() {
    const q     = document.getElementById('siswaSearchInput').value.toLowerCase().trim();
    const items = document.querySelectorAll('.siswa-item');
    let   shown = 0;

    items.forEach(el => {
        const nama  = el.dataset.nama.toLowerCase();
        const kelas = el.dataset.kelas;

        const matchKelas = (activeKelas === 'semua') || (kelas === activeKelas);
        const matchQ     = !q || nama.includes(q) || kelas.toLowerCase().includes(q);

        const visible = matchKelas && matchQ;
        el.style.display = visible ? 'flex' : 'none';
        if (visible) shown++;
    });

    document.getElementById('countShown').textContent = shown;
    document.getElementById('noResult').style.display = shown === 0 ? 'block' : 'none';
}

/* ══════════════════════════════════════
   PILIH SISWA
══════════════════════════════════════ */
function pilihSiswa(el) {
    // Deselect semua
    document.querySelectorAll('.siswa-item').forEach(i => i.classList.remove('selected'));

    // Select yg diklik
    el.classList.add('selected');
    selectedId = el.dataset.id;

    const nama  = el.dataset.nama;
    const kelas = el.dataset.kelas;
    const sudah = el.dataset.dinilai === '1';

    // Set hidden input
    document.getElementById('hiddenSiswa').value = selectedId;

    // Tampilkan card info siswa
    const card = document.getElementById('selectedStudentCard');
    card.style.display = 'block';

    const chips = document.getElementById('studentChips');
    chips.style.display = 'flex';
    chips.innerHTML = `
        <div class="chip"><i class="fa-solid fa-user-graduate"></i> ${nama}</div>
        <div class="chip"><i class="fa-solid fa-door-open"></i> Kelas ${kelas}</div>
        ${sudah
            ? `<div class="chip" style="background:rgba(16,185,129,.12);border-color:rgba(16,185,129,.25);color:#34d399;">
                <i class="fa-solid fa-circle-check"></i> Sudah pernah dinilai — akan diperbarui</div>`
            : `<div class="chip" style="background:rgba(245,158,11,.10);border-color:rgba(245,158,11,.2);color:#fbbf24;">
                <i class="fa-solid fa-clock"></i> Belum pernah dinilai</div>`
        }`;

    // Reset form nilai
    resetNilai();

    // Scroll ke panel kanan di mobile
    if(window.innerWidth < 900) {
        document.getElementById('selectedStudentCard').scrollIntoView({behavior:'smooth', block:'start'});
    }

    showToast(`${nama} dipilih`, '#2563eb', 'fa-user-check');
}

/* ══════════════════════════════════════
   RESET NILAI INPUT
══════════════════════════════════════ */
function resetNilai() {
    vals.fill(null);
    document.querySelectorAll('.nilai-input').forEach(inp => { inp.value = ''; });
    document.querySelectorAll('.nilai-fill').forEach(bar => { bar.style.width='0%'; });
    document.getElementById('progressPill').textContent = '0 / ' + TOTAL + ' diisi';
    document.getElementById('avgPill').textContent = 'avg: —';
}

/* ══════════════════════════════════════
   NILAI HANDLER
══════════════════════════════════════ */
function onNilai(input) {
    const idx = parseInt(input.dataset.idx);
    let v = parseInt(input.value);

    if (isNaN(v)) {
        vals[idx] = null;
    } else {
        v = Math.min(100, Math.max(0, v));
        input.value = v;
        vals[idx]   = v;

        const bar = document.getElementById('bar' + idx);
        bar.style.width = v + '%';

        if      (v >= 80) bar.style.background = 'linear-gradient(90deg,#059669,#10b981)';
        else if (v >= 60) bar.style.background = 'linear-gradient(90deg,#1d4ed8,#3b82f6)';
        else if (v >= 40) bar.style.background = 'linear-gradient(90deg,#b45309,#f59e0b)';
        else              bar.style.background = 'linear-gradient(90deg,#dc2626,#ef4444)';
    }

    const filled     = vals.filter(x => x !== null).length;
    const filledVals = vals.filter(x => x !== null);

    document.getElementById('progressPill').textContent = filled + ' / ' + TOTAL + ' diisi';
    document.getElementById('avgPill').textContent = filledVals.length
        ? 'avg: ' + Math.round(filledVals.reduce((a,b)=>a+b,0)/filledVals.length)
        : 'avg: —';
}

/* ══════════════════════════════════════
   FORM SUBMIT
══════════════════════════════════════ */
document.getElementById('formNilai').addEventListener('submit', function(e) {
    if (!selectedId) {
        e.preventDefault();
        showToast('Pilih siswa terlebih dahulu!', '#ef4444', 'fa-triangle-exclamation');
        return;
    }
    if (vals.some(v => v === null)) {
        e.preventDefault();
        showToast('Lengkapi semua nilai kriteria!', '#f59e0b', 'fa-triangle-exclamation');
        return;
    }
    showToast('Menyimpan nilai...', '#2563eb', 'fa-spinner fa-spin');
});

/* ══════════════════════════════════════
   TOAST HELPER
══════════════════════════════════════ */
function showToast(msg, color='#10b981', icon='fa-circle-check') {
    const toast = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    document.getElementById('toastIcon').className  = `fa-solid ${icon}`;
    toast.style.background = color;
    toast.style.boxShadow  = `0 8px 24px ${color}66`;
    toast.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(()=>toast.classList.remove('show'), 3000);
}

/* ══════════════════════════════════════
   SUCCESS TOAST (dari redirect)
══════════════════════════════════════ */
<?php if(isset($_GET['success'])): ?>
showToast('Nilai berhasil disimpan!', '#10b981', 'fa-circle-check');
<?php endif; ?>
</script>
</body>
</html>