<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Data Kriteria</title>

<link rel="stylesheet" href="assets/style.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>

/* =========================
/* =========================
   GRID
========================= */
.k-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

/* =========================
   CARD ULTRA BLUE GLASS
========================= */
.k-card {
    position: relative;
    padding: 1.6rem;
    border-radius: 20px;

    background: rgba(30, 64, 175, 0.08); /* biru glass */
    backdrop-filter: blur(18px);

    border: 1px solid rgba(59,130,246,0.2);

    overflow: hidden;
    transition: 0.35s ease;
}

/* animated blue gradient */
.k-card::before {
    content: "";
    position: absolute;
    inset: 0;

    background: linear-gradient(
        120deg,
        #3b82f6,
        #2563eb,
        #60a5fa,
        #3b82f6
    );

    background-size: 300% 300%;
    opacity: 0.15;
    animation: blueMove 10s ease infinite;
}

/* hover light sweep */
.k-card::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(
        120deg,
        transparent,
        rgba(255,255,255,0.25),
        transparent
    );
    transform: translateX(-100%);
    transition: 0.6s;
}

/* HOVER EFFECT (PREMIUM) */
.k-card:hover {
    border-color: rgba(59,130,246,0.6);
    box-shadow: 
        0 10px 30px rgba(59,130,246,0.2),
        0 0 40px rgba(59,130,246,0.15);
}

/* sweep jalan */
.k-card:hover::after {
    transform: translateX(100%);
}

/* gradient anim */
@keyframes blueMove {
    0% { background-position: 0% }
    50% { background-position: 100% }
    100% { background-position: 0% }
}

/* =========================
   ICON
========================= */
.k-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: linear-gradient(135deg,#3b82f6,#1d4ed8);
    color: white;

    margin-bottom: 1rem;
    position: relative;
    z-index: 2;

    box-shadow: 0 5px 15px rgba(59,130,246,0.4);
}

/* =========================
   TEXT
========================= */
.k-code {
    font-size: 0.7rem;
    letter-spacing: 1px;
    color: var(--text-light);
    position: relative;
    z-index: 2;
}

.k-title {
    font-size: 1.1rem;
    font-weight: 600;
    position: relative;
    z-index: 2;
}

.k-weight {
    font-size: 1.8rem;
    font-weight: 700;
    margin-top: 0.4rem;
    position: relative;
    z-index: 2;
}

/* =========================
   PROGRESS BAR
========================= */
.k-bar {
    margin-top: 1rem;
    height: 6px;
    border-radius: 10px;
    background: rgba(255,255,255,0.1);
    overflow: hidden;
    position: relative;
    z-index: 2;
}

.k-bar div {
    height: 100%;
    background: linear-gradient(90deg,#3b82f6,#60a5fa);
}

/* =========================
   TABLE
========================= */
.table-modern {
    width: 100%;
    border-collapse: collapse;
}

.table-modern th {
    background: var(--secondary-color);
    padding: 14px;
}

.table-modern td {
    padding: 14px;
    border-bottom: 1px solid var(--border-color);
}

/* badge */
.badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-benefit {
    background: rgba(59,130,246,0.15);
    color: #3b82f6;
}

</style>

</head>

<body>

<div class="dashboard-container">

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="main-content">



<!-- =========================
     ULTRA CARDS
========================= -->
<div class="k-grid">

    <div class="k-card">
        <div class="k-icon"><i class="fas fa-book"></i></div>
        <div class="k-code">C1</div>
        <div class="k-title">Kognitif</div>
        <div class="k-weight">30%</div>
        <div class="k-bar"><div style="width:25%"></div></div>
    </div>

    <div class="k-card">
        <div class="k-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="k-code">C2</div>
        <div class="k-title">Psikomotor</div>
        <div class="k-weight">25%</div>
        <div class="k-bar"><div style="width:20%"></div></div>
    </div>

    <div class="k-card">
        <div class="k-icon"><i class="fas fa-user-check"></i></div>
        <div class="k-code">C3</div>
        <div class="k-title">Afektif</div>
        <div class="k-weight">15%</div>
        <div class="k-bar"><div style="width:20%"></div></div>
    </div>

    <div class="k-card">
        <div class="k-icon"><i class="fas fa-running"></i></div>
        <div class="k-code">C4</div>
        <div class="k-title">Akhlak</div>
        <div class="k-weight">15%</div>
        <div class="k-bar"><div style="width:15%"></div></div>
    </div>

    <div class="k-card">
        <div class="k-icon"><i class="fas fa-clock"></i></div>
        <div class="k-code">C5</div>
        <div class="k-title">Kehadiran</div>
        <div class="k-weight">15%</div>
        <div class="k-bar"><div style="width:20%"></div></div>
    </div>

</div>

<!-- =========================
     TABLE
========================= -->
<div class="card table-container">
    <div class="card-header">
        <h3>Detail Kriteria</h3>
    </div>

    <table class="table-modern">
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama</th>
                <th>Bobot</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>C1</td><td>Kognitif</td><td>30%</td></tr>
            <tr><td>C2</td><td>Psikomotor</td><td>25%</td></tr>
            <tr><td>C3</td><td>Afektif</td><td>15%</td></tr>
            <tr><td>C4</td><td>Akhlak</td><td>15%</td></tr>
            <tr><td>C5</td><td>Kehadiran</td><td>15%</td></tr>
        </tbody>
    </table>
</div>

</main>
</div>

<script src="assets/script.js"></script>

</body>
</html>