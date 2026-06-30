<header class="header">

    <div class="header-left">
        <div class="logo-box">
            <img src="assets/images/logosmpn.png" class="logo-img" alt="logo">
            <div>
                <h1 class="logo-title">SPK SMART</h1>
                <small class="logo-sub">Sistem Pendukung Keputusan</small>
            </div>
        </div>
    </div>

    <div class="user-info">

        <div class="user-profile">
            <div class="username">
                <?= htmlspecialchars($_SESSION['username']); ?>
            </div>
            <div class="role">
                <?= ucfirst($_SESSION['role']); ?>
            </div>
        </div>

        <button id="theme-toggle" class="icon-btn" title="Dark Mode">
            <i class="fas fa-moon"></i>
        </button>

        <a href="logout.php" class="icon-btn danger" title="Logout">
            <i class="fas fa-right-from-bracket"></i>
        </a>

    </div>
</header>