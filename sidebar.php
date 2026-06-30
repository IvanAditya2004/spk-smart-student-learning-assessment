<?php
$current = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'];
?>

<div class="sidebar">

    <!-- LOGO -->
    <div style="padding: 20px; text-align:center; color:white; border-bottom:1px solid rgba(255,255,255,0.1);">
        <h2 style="font-size:18px;">SPK SMART</h2>
    </div>

    <ul>

        <!-- MENU UTAMA -->
        <li style="margin-top:10px; padding-left:20px; color:#aaa; font-size:12px;">
            MENU UTAMA
        </li>

        <!-- SEMUA ROLE -->
        <li>
            <a href="dashboard.php" class="<?= $current=='dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </li>

        <!-- ADMIN & GURU -->
        <?php if ($role == 'admin' || $role == 'guru'): ?>

        <!-- DATA MASTER -->
        <li style="margin-top:15px; padding-left:20px; color:#aaa; font-size:12px;">
            DATA MASTER
        </li>

        <!-- KHUSUS ADMIN -->
        <?php if ($role == 'admin'): ?>
        <li>
            <a href="siswa.php" class="<?= $current=='siswa.php' ? 'active' : '' ?>">
                <i class="fas fa-user-graduate"></i> Data Siswa
            </a>
        </li>
        <?php endif; ?>

        <!-- ADMIN & GURU -->
        <li>
            <a href="kriteria.php" class="<?= $current=='kriteria.php' ? 'active' : '' ?>">
                <i class="fas fa-list"></i> Data Kriteria
            </a>
        </li>

        <!-- PROSES -->
        <li style="margin-top:15px; padding-left:20px; color:#aaa; font-size:12px;">
            PROSES
        </li>

        <li>
            <a href="penilaian.php" class="<?= $current=='penilaian.php' ? 'active' : '' ?>">
                <i class="fas fa-edit"></i> Input Nilai
            </a>
        </li>

        <li>
            <a href="perhitungan.php" class="<?= $current=='perhitungan.php' ? 'active' : '' ?>">
                <i class="fas fa-calculator"></i> Perhitungan SMART
            </a>
        </li>

        <?php endif; ?>

        <!-- SEMUA ROLE -->
        <li style="margin-top:15px; padding-left:20px; color:#aaa; font-size:12px;">
            LAPORAN
        </li>

        <li>
            <a href="hasil.php" class="<?= $current=='hasil.php' ? 'active' : '' ?>">
                <i class="fas fa-file-alt"></i> Hasil & Ranking
            </a>
        </li>

    </ul>
</div>

<!-- Overlay Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>