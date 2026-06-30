<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/* =========================================
   FILTER
========================================= */
$filter_kelas = $_GET['kelas'] ?? '';
$search       = trim($_GET['q'] ?? '');
$sort_asc     = isset($_GET['sort']) && $_GET['sort'] === 'asc';

/* =========================================
   DATA SISWA
========================================= */
$whereKelas = $filter_kelas
    ? "WHERE s.kelas = " . $pdo->quote($filter_kelas)
    : "";

$siswaRows = $pdo->query("
    SELECT s.id_siswa, s.nis, s.nama_siswa, s.kelas
    FROM siswa s
    $whereKelas
    ORDER BY s.nama_siswa ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   KRITERIA
========================================= */
$kriteria = $pdo->query("
    SELECT *
    FROM kriteria
    ORDER BY bobot DESC
")->fetchAll(PDO::FETCH_ASSOC);

$bobot    = [];
$labelMap = [];

foreach ($kriteria as $k) {
    $bobot[$k['kode']]    = (float)$k['bobot'];
    $labelMap[$k['kode']] = $k['nama_kriteria'];
}

/* =========================================
   MAX NORMALISASI
========================================= */
$maxVal = [];

foreach ($kriteria as $k) {
    $st = $pdo->prepare("SELECT MAX(nilai) FROM nilai WHERE kode_kriteria=?");
    $st->execute([$k['kode']]);
    $maxVal[$k['kode']] = (float)($st->fetchColumn() ?: 1);
}

/* =========================================
   HITUNG SMART
========================================= */
$hasil = [];

foreach ($siswaRows as $s) {
    $st = $pdo->prepare("SELECT * FROM nilai WHERE id_siswa=?");
    $st->execute([$s['id_siswa']]);
    $nilais = $st->fetchAll(PDO::FETCH_ASSOC);

    $total  = 0;
    $detail = [];

    foreach ($nilais as $n) {
        $kode   = $n['kode_kriteria'];
        $nilai  = (float)$n['nilai'];
        $normal = $nilai / $maxVal[$kode];
        $hasilKriteria = $normal * ($bobot[$kode] ?? 0);

        $detail[$kode] = [
            'nilai'  => $nilai,
            'normal' => $normal,
            'hasil'  => $hasilKriteria
        ];

        $total += $hasilKriteria;
    }

    $hasil[] = [
        'id'      => $s['id_siswa'],
        'nis'     => $s['nis'],
        'nama'    => $s['nama_siswa'],
        'kelas'   => $s['kelas'],
        'total'   => $total,
        'detail'  => $detail,
        'lengkap' => count($detail) === count($kriteria)
    ];
}

/* =========================================
   SORT
========================================= */
usort($hasil, fn($a,$b) => $sort_asc
    ? $a['total'] <=> $b['total']
    : $b['total'] <=> $a['total']
);

/* =========================================
   SEARCH
========================================= */
if ($search !== '') {
    $hasil = array_values(array_filter($hasil, function($h) use ($search) {
        return
            stripos($h['nama'],  $search) !== false ||
            stripos($h['nis'],   $search) !== false ||
            stripos($h['kelas'], $search) !== false;
    }));
}

/* =========================================
   STATS
========================================= */
$scores   = array_column($hasil, 'total');
$maxScore = $scores ? max($scores)                        : 0;
$avgScore = $scores ? array_sum($scores) / count($scores) : 0;

$listKelas = $pdo->query("
    SELECT DISTINCT kelas FROM siswa ORDER BY kelas
")->fetchAll(PDO::FETCH_COLUMN);

/* =========================================
   HELPER
========================================= */
function inisial($nama)
{
    $w = explode(' ', trim($nama));
    return strtoupper(
        substr($w[0], 0, 1) .
        (isset($w[1]) ? substr($w[1], 0, 1) : '')
    );
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hasil Ranking SMART</title>

<link rel="stylesheet" href="assets/style.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* =========================================
   THEME
========================================= */
:root {
    --bg:           #f5f7fb;
    --card:         #ffffff;
    --card-soft:    #f8fafc;
    --border:       #e2e8f0;
    --text:         #0f172a;
    --text-soft:    #475569;
    --text-muted:   #94a3b8;
    --primary:      #2563eb;
    --primary-soft: #dbeafe;
    --success:      #16a34a;
    --warning:      #d97706;
    --shadow:       0 4px 14px rgba(15,23,42,.06);
}

[data-theme="dark"] {
    --bg:           #0f172a;
    --card:         #111827;
    --card-soft:    #1e293b;
    --border:       #334155;
    --text:         #f1f5f9;
    --text-soft:    #cbd5e1;
    --text-muted:   #64748b;
    --primary:      #60a5fa;
    --primary-soft: rgba(96,165,250,.12);
    --shadow:       none;
}

/* =========================================
   BASE
========================================= */
* {
    margin: 0; padding: 0; box-sizing: border-box;
    transition: background-color .25s ease, border-color .25s ease, color .25s ease;
}

body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Plus Jakarta Sans', sans-serif;
}

.main-content { padding: 24px; min-height: 100vh; }

/* =========================================
   PAGE HEADER
========================================= */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.ph-left { display: flex; align-items: center; gap: 14px; }

.ph-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    background: var(--primary-soft);
    color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    border: 1px solid var(--border);
    font-size: 20px;
}

.ph-title { font-size: 24px; font-weight: 700; }
.ph-sub   { margin-top: 3px; font-size: 13px; color: var(--text-soft); }

/* =========================================
   BUTTON
========================================= */
.btn-group { display: flex; gap: 10px; flex-wrap: wrap; }

.btn {
    border: none; outline: none;
    background: var(--card);
    color: var(--text);
    border: 1px solid var(--border);
    padding: 10px 16px;
    border-radius: 10px;
    cursor: pointer;
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; font-weight: 600;
    text-decoration: none;
}
.btn:hover { background: var(--card-soft); }
.btn-primary { background: var(--primary); color: white; border: none; }

/* =========================================
   STATS
========================================= */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}

.stat {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 18px;
    display: flex; align-items: center; gap: 14px;
    box-shadow: var(--shadow);
}

.si {
    width: 46px; height: 46px;
    border-radius: 14px;
    background: var(--card-soft);
    color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
}

.stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--text-muted); }
.stat-val   { margin-top: 4px; font-size: 24px; font-weight: 700; }
.stat-desc  { margin-top: 4px; font-size: 12px; color: var(--text-soft); }

/* =========================================
   TOOLBAR
========================================= */
.toolbar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }

.search-box {
    flex: 1; min-width: 230px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px 14px;
    display: flex; align-items: center; gap: 10px;
}

.search-box input {
    border: none; outline: none;
    width: 100%; background: transparent; color: var(--text);
}
.search-box input::placeholder { color: var(--text-muted); }

.sel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px 14px;
    color: var(--text);
    min-width: 180px;
}

/* =========================================
   TABLE
========================================= */
.table-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--shadow);
}

.tbl-scroll { overflow: auto; }

table { width: 100%; border-collapse: collapse; min-width: 760px; }

thead th {
    background: var(--card-soft);
    color: var(--text-soft);
    padding: 14px 16px;
    text-align: left;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .08em;
    border-bottom: 1px solid var(--border);
}

tbody td   { padding: 15px 16px; border-bottom: 1px solid var(--border); }
tbody tr:hover { background: var(--card-soft); }

/* =========================================
   RANK
========================================= */
.rank-badge {
    width: 34px; height: 34px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
    border: 1px solid var(--border);
}
.rb1 { background: #fef3c7; color: #92400e; }
.rb2 { background: #e2e8f0; color: #334155; }
.rb3 { background: #fde68a; color: #78350f; }
.rbx { background: var(--card-soft); color: var(--text); }

/* =========================================
   NAME
========================================= */
.name-cell { display: flex; align-items: center; gap: 12px; }

.avatar {
    width: 42px; height: 42px;
    border-radius: 12px;
    background: var(--card-soft);
    border: 1px solid var(--border);
    color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
}

.name-main { font-weight: 700; }
.name-id   { margin-top: 3px; font-size: 12px; color: var(--text-soft); }

/* =========================================
   KELAS
========================================= */
.kelas-pill {
    display: inline-flex;
    padding: 5px 12px;
    border-radius: 999px;
    background: var(--primary-soft);
    color: var(--primary);
    font-size: 12px; font-weight: 600;
}

/* =========================================
   STATUS
========================================= */
.status-ok { color: var(--success); font-size: 13px; font-weight: 600; }
.status-no { color: var(--warning); font-size: 13px; font-weight: 600; }

/* =========================================
   SCORE
========================================= */
.score { font-size: 15px; font-weight: 700; }

/* =========================================
   DETAIL
========================================= */
.detail-row        { display: none; }
.detail-row.open   { display: table-row; }

.detail-inner {
    padding: 18px;
    background: var(--card-soft);
    display: flex; gap: 12px; flex-wrap: wrap;
}

.krit-card {
    flex: 1; min-width: 180px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px;
}

.krit-name  { font-size: 12px; color: var(--text-soft); }
.krit-value { margin: 10px 0; font-size: 22px; font-weight: 700; }
.krit-meta  { font-size: 12px; color: var(--text-muted); }

/* =========================================
   MOBILE
========================================= */
@media (max-width: 768px) {
    .main-content { padding: 16px; }
    .stats-row    { grid-template-columns: 1fr 1fr; }
    .toolbar      { flex-direction: column; }
    .page-header  { flex-direction: column; align-items: flex-start; }
}

/* =========================================================
   PRINT STYLES
   Hides semua UI normal, tampilkan khusus print-section
========================================================= */
@media print {

    /* Sembunyikan seluruh halaman web normal */
    body > *:not(#print-section) { display: none !important; }

    /* Tampilkan print section */
    #print-section {
        display: block !important;
        font-family: 'Times New Roman', Times, serif;
        font-size: 11pt;
        color: #000;
        background: #fff;
        margin: 0;
        padding: 0;
    }

    /* Setup halaman */
    @page {
        size: A4 landscape;
        margin: 1.5cm 2cm 2cm 2cm;
    }

    /* ---- KOP ---- */
    .print-kop {
        display: flex;
        align-items: center;
        gap: 18px;
        border-bottom: 3px double #000;
        padding-bottom: 10px;
        margin-bottom: 10px;
    }

    .print-kop img.logo-sekolah {
        width: 75px;
        height: 75px;
        object-fit: contain;
    }

    /* Placeholder logo jika gambar tidak ada */
    .logo-placeholder {
        width: 75px;
        height: 75px;
        border: 2px solid #000;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 9pt;
        font-weight: bold;
        text-align: center;
        line-height: 1.2;
        flex-shrink: 0;
    }

    .kop-text { flex: 1; text-align: center; }

    .kop-instansi {
        font-size: 17pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        line-height: 1.2;
    }

    .kop-sub {
        font-size: 10pt;
        font-weight: bold;
        margin-top: 2px;
    }

    .kop-alamat {
        font-size: 9pt;
        margin-top: 3px;
        color: #333;
    }

    /* ---- JUDUL DOKUMEN ---- */
    .print-judul {
        text-align: center;
        margin: 16px 0 6px;
    }

    .print-judul h2 {
        font-size: 13pt;
        font-weight: bold;
        text-decoration: underline;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .print-judul p {
        font-size: 10pt;
        margin-top: 4px;
    }

    /* ---- META INFO ---- */
    .print-meta {
        display: flex;
        gap: 30px;
        font-size: 10pt;
        margin: 10px 0;
    }

    .print-meta span { display: block; }

    /* ---- TABEL PRINT ---- */
    .print-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10pt;
        margin-top: 12px;
        page-break-inside: auto;
    }

    .print-table thead { display: table-header-group; }
    .print-table tfoot { display: table-footer-group; }

    .print-table th {
        background: #d4e4f7 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        border: 1px solid #000;
        padding: 7px 8px;
        text-align: center;
        font-weight: bold;
        font-size: 10pt;
    }

    .print-table td {
        border: 1px solid #555;
        padding: 5px 8px;
        vertical-align: middle;
    }

    .print-table tbody tr:nth-child(even) td {
        background: #f5f9ff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .print-table .rank-1 td { background: #fffbe6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .print-table .rank-2 td { background: #f0f4f8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .print-table .rank-3 td { background: #fdf6d3 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    .print-table .col-rank  { text-align: center; width: 36px; font-weight: bold; }
    .print-table .col-nis   { text-align: center; width: 90px; }
    .print-table .col-kelas { text-align: center; width: 70px; }
    .print-table .col-score { text-align: center; width: 80px; font-weight: bold; }
    .print-table .col-krit  { text-align: center; width: 70px; }

    /* ---- TANDA TANGAN ---- */
    .print-ttd {
        margin-top: 32px;
        display: flex;
        justify-content: flex-end;
    }

    .ttd-block { text-align: center; font-size: 10pt; }
    .ttd-block .ttd-kota  { margin-bottom: 4px; }
    .ttd-block .ttd-ruang { height: 56px; }
    .ttd-block .ttd-nama  { font-weight: bold; text-decoration: underline; }
    .ttd-block .ttd-nip   { font-size: 9pt; margin-top: 2px; }

    /* ---- FOOTER ---- */
    .print-footer {
        margin-top: 20px;
        border-top: 1px solid #999;
        padding-top: 6px;
        font-size: 8pt;
        color: #555;
        display: flex;
        justify-content: space-between;
    }

    /* ---- CATATAN ---- */
    .print-note {
        margin-top: 10px;
        font-size: 9pt;
        color: #444;
    }

    .print-note ul { padding-left: 16px; }
    .print-note li { margin-top: 3px; }
}

/* Sembunyikan print-section di layar */
@media screen {
    #print-section { display: none; }
}

</style>
</head>

<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<!-- ===================================================
     TAMPILAN LAYAR (UI NORMAL)
==================================================== -->
<main class="main-content">

    <!-- HEADER -->
    <div class="page-header">
        <div class="ph-left">
            <div class="ph-icon"><i class="fas fa-ranking-star"></i></div>
            <div>
                <div class="ph-title">Hasil Ranking SMART</div>
                <div class="ph-sub">Hasil penilaian siswa menggunakan metode SMART</div>
            </div>
        </div>

        <div class="btn-group">
            <button onclick="window.print()" class="btn">
                <i class="fas fa-print"></i>
                Cetak
            </button>
            <a href="#" class="btn btn-primary">
                <i class="fas fa-file-excel"></i>
                Export
            </a>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat">
            <div class="si"><i class="fas fa-users"></i></div>
            <div>
                <div class="stat-label">Total Siswa</div>
                <div class="stat-val"><?= count($hasil) ?></div>
                <div class="stat-desc">Siswa dinilai</div>
            </div>
        </div>

        <div class="stat">
            <div class="si"><i class="fas fa-trophy"></i></div>
            <div>
                <div class="stat-label">Nilai Tertinggi</div>
                <div class="stat-val"><?= number_format($maxScore*100,1) ?>%</div>
                <div class="stat-desc"><?= htmlspecialchars($hasil[0]['nama'] ?? '-') ?></div>
            </div>
        </div>

        <div class="stat">
            <div class="si"><i class="fas fa-chart-line"></i></div>
            <div>
                <div class="stat-label">Rata-rata</div>
                <div class="stat-val"><?= number_format($avgScore*100,1) ?>%</div>
                <div class="stat-desc">Semua siswa</div>
            </div>
        </div>

        <div class="stat">
            <div class="si"><i class="fas fa-school"></i></div>
            <div>
                <div class="stat-label">Jumlah Kelas</div>
                <div class="stat-val"><?= count(array_unique(array_column($hasil,'kelas'))) ?></div>
                <div class="stat-desc">Kelas aktif</div>
            </div>
        </div>
    </div>

    <!-- FILTER -->
    <form method="GET" class="toolbar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" name="q" placeholder="Cari siswa..."
                   value="<?= htmlspecialchars($search) ?>">
        </div>

        <select name="kelas" class="sel">
            <option value="">Semua Kelas</option>
            <?php foreach ($listKelas as $k): ?>
                <option value="<?= htmlspecialchars($k) ?>"
                    <?= $filter_kelas === $k ? 'selected' : '' ?>>
                    <?= htmlspecialchars($k) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="btn"><i class="fas fa-filter"></i> Filter</button>
    </form>

    <!-- TABLE -->
    <div class="table-card">
        <div class="tbl-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Nama Siswa</th>
                        <th>NIS</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th>Nilai Akhir</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($hasil as $i => $h):
                    $rank  = $i + 1;
                    $rbCls = $rank==1 ? 'rb1' : ($rank==2 ? 'rb2' : ($rank==3 ? 'rb3' : 'rbx'));
                ?>

                <!-- ROW -->
                <tr onclick="toggleDetail(<?= $i ?>)" style="cursor:pointer;">
                    <td>
                        <div class="rank-badge <?= $rbCls ?>"><?= $rank ?></div>
                    </td>
                    <td>
                        <div class="name-cell">
                            <div class="avatar"><?= inisial($h['nama']) ?></div>
                            <div>
                                <div class="name-main"><?= htmlspecialchars($h['nama']) ?></div>
                                <div class="name-id">ID #<?= str_pad($h['id'],4,'0',STR_PAD_LEFT) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($h['nis']) ?></td>
                    <td><span class="kelas-pill"><?= htmlspecialchars($h['kelas']) ?></span></td>
                    <td>
                        <?php if ($h['lengkap']): ?>
                            <span class="status-ok">Lengkap</span>
                        <?php else: ?>
                            <span class="status-no">Belum Lengkap</span>
                        <?php endif; ?>
                    </td>
                    <td><div class="score"><?= number_format($h['total']*100,2) ?>%</div></td>
                    <td><i class="fas fa-chevron-down"></i></td>
                </tr>

                <!-- DETAIL -->
                <tr class="detail-row" id="detail<?= $i ?>">
                    <td colspan="7">
                        <div class="detail-inner">
                            <?php foreach ($h['detail'] as $kode => $d): ?>
                                <div class="krit-card">
                                    <div class="krit-name"><?= htmlspecialchars($labelMap[$kode]) ?></div>
                                    <div class="krit-value"><?= $d['nilai'] ?></div>
                                    <div class="krit-meta">Normalisasi : <?= number_format($d['normal'],3) ?></div>
                                    <div class="krit-meta" style="margin-top:5px;">Hasil : <?= number_format($d['hasil'],4) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>

                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>


<!-- ===================================================
     TAMPILAN CETAK (hanya muncul saat print)
==================================================== -->
<div id="print-section">

    <!-- KOP INSTANSI -->
    <div class="print-kop">

        <!-- Logo sekolah — ganti src dengan path logo asli Anda -->
        <img src="assets/images/logosmpn.png"
             alt="Logo Sekolah"
             class="logo-sekolah"
             onerror="this.style.display='none';document.getElementById('logo-placeholder').style.display='flex';">

        <!-- Placeholder jika logo tidak ditemukan -->
        <div class="logo-placeholder" id="logo-placeholder" style="display:none;">
            LOGO<br>SMP
        </div>

        <div class="kop-text">
            <div class="kop-instansi">Sekolah Menengah Pertama Negeri 1 Jogoroto</div>
            <div class="kop-sub">DINAS PENDIDIKAN KABUPATEN JOMBANG</div>
            <div class="kop-alamat">
                Jl. Pendidikan No. 1, Jombang, Jawa Timur 61411
                &nbsp;|&nbsp; Telp. (0321) 000000
                &nbsp;|&nbsp; Email: smpn1jogoroto@disdik.jombang.go.id
            </div>
        </div>

    </div>

    <!-- JUDUL DOKUMEN -->
    <div class="print-judul">
        <h2>Hasil Perankingan Siswa Metode SMART</h2>
        <p>
            <?= $filter_kelas ? 'Kelas : ' . htmlspecialchars($filter_kelas) : 'Semua Kelas' ?>
            &nbsp;&bull;&nbsp;
            Tahun Pelajaran 2025/2026
        </p>
    </div>

    <!-- META INFO -->
    <div class="print-meta">
        <div>
            <span><strong>Total Siswa</strong> : <?= count($hasil) ?> siswa</span>
            <span><strong>Nilai Tertinggi</strong> : <?= number_format($maxScore*100,2) ?>%</span>
            <span><strong>Rata-rata</strong> : <?= number_format($avgScore*100,2) ?>%</span>
        </div>
        <div>
            <span><strong>Dicetak</strong> : <?= date('d F Y, H:i') ?> WIB</span>
            <span><strong>Operator</strong> : <?= htmlspecialchars($_SESSION['username'] ?? '-') ?></span>
        </div>
    </div>

    <!-- TABEL NILAI -->
    <table class="print-table">
        <thead>
            <tr>
                <th class="col-rank">No.</th>
                <th>NIS</th>
                <th>Nama Siswa</th>
                <th class="col-kelas">Kelas</th>
                <?php foreach ($kriteria as $k): ?>
                    <th class="col-krit"><?= htmlspecialchars($k['nama_kriteria']) ?><br>(<?= $k['kode'] ?>)</th>
                <?php endforeach; ?>
                <th class="col-krit">Normalisasi</th>
                <th class="col-score">Nilai Akhir</th>
                <th class="col-rank">Rank</th>
            </tr>
        </thead>

        <tbody>
        <?php foreach ($hasil as $i => $h):
            $rank    = $i + 1;
            $rankCls = $rank==1 ? 'rank-1' : ($rank==2 ? 'rank-2' : ($rank==3 ? 'rank-3' : ''));
        ?>
            <tr class="<?= $rankCls ?>">
                <td class="col-rank"><?= $rank ?></td>
                <td class="col-nis"><?= htmlspecialchars($h['nis']) ?></td>
                <td><?= htmlspecialchars($h['nama']) ?></td>
                <td class="col-kelas"><?= htmlspecialchars($h['kelas']) ?></td>

                <?php foreach ($kriteria as $k):
                    $d = $h['detail'][$k['kode']] ?? null;
                ?>
                    <td class="col-krit">
                        <?= $d ? $d['nilai'] : '-' ?>
                    </td>
                <?php endforeach; ?>

                <td class="col-krit">
                    <?php
                    // Tampilkan rata-rata normalisasi semua kriteria
                    $sumNormal = array_sum(array_column($h['detail'], 'normal'));
                    $cntNormal = count($h['detail']);
                    echo $cntNormal ? number_format($sumNormal/$cntNormal, 4) : '-';
                    ?>
                </td>

                <td class="col-score"><?= number_format($h['total']*100,2) ?>%</td>

                <td class="col-rank" style="font-weight:bold;">
                    <?= $rank ?>
                    <?php if ($rank==1): ?>&#9733;<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- CATATAN -->
    <div class="print-note">
        <strong>Keterangan :</strong>
        <ul>
            <li>Nilai akhir dihitung menggunakan metode SMART (Simple Multi-Attribute Rating Technique).</li>
            <li>Normalisasi = Nilai Kriteria / Nilai Maksimum Kriteria pada seluruh siswa.</li>
            <li>Nilai Akhir = &Sigma; (Normalisasi &times; Bobot Kriteria).</li>
            <?php if ($filter_kelas): ?>
            <li>Data ditampilkan hanya untuk kelas: <strong><?= htmlspecialchars($filter_kelas) ?></strong>.</li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- TANDA TANGAN -->
    <div class="print-ttd">
        <div class="ttd-block">
            <div class="ttd-kota">Jombang, <?= date('d F Y') ?></div>
            <div>Kepala Sekolah,</div>
            <div class="ttd-ruang"></div>
            <div class="ttd-nama">Drs. Nama Kepala Sekolah, M.Pd.</div>
            <div class="ttd-nip">NIP. 19XX0101 XXXXXX 1 001</div>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="print-footer">
        <span>SMPN 1 Jogoroto &mdash; Sistem Informasi Penilaian Siswa</span>
        <span>Dokumen ini dicetak secara otomatis oleh sistem &mdash; <?= date('d/m/Y H:i') ?></span>
    </div>

</div><!-- /#print-section -->


<script src="assets/script.js"></script>
<script>
function toggleDetail(i) {
    const row = document.getElementById('detail' + i);
    row.classList.toggle('open');
}
</script>

</body>
</html>