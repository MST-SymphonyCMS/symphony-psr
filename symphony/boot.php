<?php

require_once __DIR__.'/functions.php';
require_once __DIR__.'/defines.php';

if (PHP_VERSION_ID >= 50300) {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
} else {
    error_reporting(E_ALL & ~E_NOTICE);
}

ini_set('magic_quotes_runtime', 0);

if (!file_exists(CONFIG)) {
    $isInstaller = (bool)preg_match('%(/|\\\\)install(\.php)?$%', $_SERVER['SCRIPT_FILENAME']);

    if (!$isInstaller && file_exists(DOCROOT . '/install.php')) {
        header(sprintf('Location: %s/install/', URL));

        exit;
    } elseif (!$isInstaller) {
        die('<h2>Error</h2><p>Could not locate Symphony configuration file. Please check <code>manifest/config.php</code> exists.</p>');
    }
}
