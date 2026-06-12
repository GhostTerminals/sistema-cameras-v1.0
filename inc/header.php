<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($APP_BASE_PATH ?? '', ENT_QUOTES, 'UTF-8') ?>/images/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($APP_BASE_PATH ?? '', ENT_QUOTES, 'UTF-8') ?>/images/favicon-16x16.png" sizes="16x16" />
    <link rel="shortcut icon" href="<?= htmlspecialchars($APP_BASE_PATH ?? '', ENT_QUOTES, 'UTF-8') ?>/images/favicon.ico" type="image/x-ico">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars($APP_BASE_PATH ?? '', ENT_QUOTES, 'UTF-8') ?>/images/apple-touch-icon.png" />
    <link rel="manifest" href="<?= htmlspecialchars($APP_BASE_PATH ?? '', ENT_QUOTES, 'UTF-8') ?>/images/site.webmanifest" />
    <link rel="icon" type="image/png" sizes="512x512" href="<?= htmlspecialchars($APP_BASE_PATH ?? '', ENT_QUOTES, 'UTF-8') ?>/images/android-chrome-512x512.png" />
    <link rel="icon" type="image/png" sizes="192x192" href="<?= htmlspecialchars($APP_BASE_PATH ?? '', ENT_QUOTES, 'UTF-8') ?>/images/android-chrome-192x192.png" />

    <title>Sistema de Gerenciamento de Cameras e Alarmes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= htmlspecialchars($APP_BASE_PATH ?? '', ENT_QUOTES, 'UTF-8') ?>/assets/css/main.css">
    <!-- Theme Enhancements (Dark Mode, Skeleton Loading, Accessibility, Responsive) -->
    <link rel="stylesheet" href="<?= htmlspecialchars($APP_BASE_PATH ?? '', ENT_QUOTES, 'UTF-8') ?>/assets/css/theme-enhancements.css">
    <script nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
        const BASE_URL = '<?= htmlspecialchars(rtrim($APP_PUBLIC_PATH ?? '/public', '/') . '/', ENT_QUOTES, 'UTF-8') ?>';
        const APP_API_BASE = `${BASE_URL}index.php?page=api/`;
        window.BASE_URL = BASE_URL;
        window.APP_API_BASE = APP_API_BASE;
        window.CSRF_TOKEN = function() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        };
    </script>
    <meta name="csrf-token" content="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="page-<?= htmlspecialchars((string)($CURRENT_PAGE ?? 'default'), ENT_QUOTES, 'UTF-8') ?>">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
<script nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
    window.FETCH_TIMEOUT = 30000;
    if (window.DEBUG !== true) {
        window.console.log = function () {};
        window.console.warn = function () {};
        window.console.error = function () {};
    }
</script>
<script src="<?= htmlspecialchars($APP_BASE_PATH ?? '', ENT_QUOTES, 'UTF-8') ?>/assets/js/utils/fetchWithTimeout.js"></script>
<script src="<?= htmlspecialchars($APP_BASE_PATH ?? '', ENT_QUOTES, 'UTF-8') ?>/assets/js/main.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/main.js') ?: 0 ?>"></script>
<!-- Theme Manager (Dark Mode Toggle + Skeleton Loader + Accessibility) -->
<script src="<?= htmlspecialchars($APP_BASE_PATH ?? '', ENT_QUOTES, 'UTF-8') ?>/assets/js/utils/ui/theme-manager.js"></script>
<script nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
  // Registrar Service Worker para PWA support
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?= htmlspecialchars($APP_BASE_PATH ?? '', ENT_QUOTES, 'UTF-8') ?>/sw.js')
      .then(registration => console.log('✅ Service Worker registered'))
      .catch(error => console.warn('⚠️ Service Worker registration failed:', error));
  }
</script>






