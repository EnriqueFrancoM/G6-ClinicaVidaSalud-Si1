<?php use App\Core\App; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \App\Core\e(App::config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= App::url('/assets/css/app.css') ?>">
</head>
<body class="auth-page">
    <?= $content ?>
</body>
</html>
