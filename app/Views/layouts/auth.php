<?php use App\Core\App; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \App\Core\e(App::config('app.name')) ?></title>
    <script>
        try {
            if (localStorage.getItem("vs-theme") === "dark") {
                document.documentElement.setAttribute("data-theme", "dark");
            }
        } catch (e) {}
    </script>
    <link rel="stylesheet" href="<?= App::url('/assets/css/app.css') ?>?v=<?= filemtime(BASE_PATH . '/public/assets/css/app.css') ?>">
</head>
<body class="auth-page">
    <?= $content ?>
    <script src="<?= App::url('/assets/js/app.js') ?>?v=<?= filemtime(BASE_PATH . '/public/assets/js/app.js') ?>"></script>
</body>
</html>
