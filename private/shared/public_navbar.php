<script defer>
    // Login redirect
    /* const loginButton = document.querySelector('#header-login');
    loginButton.onclick = () => {
        alert(12)
    }
    const loginButtonMobile = document.querySelector('#header-login-mobile');
    loginButtonMobile.onclick = () => {
        location.href = "login.php";
    } */

    function loginRedir() {
        location.href = "<?= WWW_ROOT . "/login.php" ?>";
    }
</script>
<script src="<?= WWW_ROOT . "/js/header-dropdown.js" ?>" defer></script>
<nav class="topnav">
    <div>
        <a href="<?= WWW_ROOT . '/index.php' ?>"><img src="<?= WWW_ROOT . '/img/logo/elevguiden_logo_light.svg' ?>" alt="Elevguiden Logo"></a>
    </div>
    <div>
        <a href="<?= WWW_ROOT . '/index.php' ?>">Hem</a><a href="<?= WWW_ROOT . '/links.php' ?>">Links</a><a href="<?= WWW_ROOT . '/matkort.php' ?>">Matkort</a>
    </div>
    <?php if (!$loggedIn) : ?>
        <div>
            <button id="header-login" onclick="loginRedir()">Logga in</button>
        </div>
    <?php else : ?>
        <div class="login-container">
            <a href="<?= WWW_ROOT . "/profile/profile.php?username=" . $absoluteUsername ?>">
                <img src="<?= WWW_ROOT . '/img/svg/user-icon.svg' ?>" alt="Profil sida">
                <span><?= $absoluteUsername ?></span>
            </a>
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
                <button name="logout-account" value="Logga ut"><img src="<?= WWW_ROOT . '/img/svg/logout-icon.svg' ?>" alt="Logga ut"></button>
            </form>
        </div>
    <?php endif; ?>
    <div class="hamburger-menu-container">
        <div class="hamburger-menu-button-topnav">
            <div></div>
            <div></div>
            <div></div>
        </div>
    </div>
</nav>
<nav class="topnav-dropdown">
    <div class="close-dropdown">
        <img src="<?= WWW_ROOT . '/img/svg/red-alert.svg' ?>" alt="Stäng meny">
    </div>
    <div>
        <div class="user">
            <?php if (!$loggedIn) : ?>
                <div class="img-container">
                    <img src="<?= WWW_ROOT . '/img/logo/elevguiden_logo_light.svg' ?>">
                </div>
                <div class="logged-in-status">
                    <div class="username">
                        Gäst
                    </div>
                </div>
            <?php else : ?>
                <div class="img-container">
                    <img src="<?= WWW_ROOT . '/img/logo/elevguiden_logo_light.svg' ?>">
                </div>
                <div class="logged-in-status">
                    <div class="username">
                        <a href="<?= WWW_ROOT . "/profile/profile.php?username=" . $absoluteUsername ?>">
                            <?= $absoluteUsername ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="navigation">
            <a href="<?= WWW_ROOT . '/index.php' ?>">Hem</a>
            <a href="<?= WWW_ROOT . '/profile/profile.php?username=' . $absoluteUsername ?>">Profil</a>
            <a href="<?= WWW_ROOT . '/links.php' ?>">Links</a>
            <a href="<?= WWW_ROOT . '/matkort.php' ?>">Matkort</a>
        </div>
    </div>
    <div class="log-in-out">
        <?php if (!$loggedIn) : ?>
            <div class="login">
                <button id="header-login-mobile" onclick="loginRedir()">Logga in</button>
            </div>
        <?php else : ?>
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
                <input type="submit" value="Logga ut" name="logout-account">
            </form>
        <?php endif; ?>
    </div>
</nav>