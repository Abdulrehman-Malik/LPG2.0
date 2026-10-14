<?php

use CodeIgniter\Boot;
use Config\Paths;

/*
 *---------------------------------------------------------------
 * CHECK PHP VERSION
 *---------------------------------------------------------------
 */

$minPhpVersion = '8.2'; // If you update this, don't forget to update `spark`.
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $message = sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION,
    );

    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo $message;

    exit(1);
}

/*
 *---------------------------------------------------------------
 * CHECK REQUIRED PHP EXTENSIONS
 *---------------------------------------------------------------
 * Run this before CodeIgniter boots so local/server configuration
 * problems produce a useful message instead of a generic 500 page.
 */

$requiredExtensions = [
    'intl'   => 'Internationalization (intl)',
    'mbstring' => 'Multibyte String (mbstring)',
    'mysqli' => 'MySQLi',
];

$missingExtensions = [];

foreach ($requiredExtensions as $extension => $label) {
    if (! extension_loaded($extension)) {
        $missingExtensions[$extension] = $label;
    }
}

if ($missingExtensions !== []) {
    http_response_code(503);

    $phpIni = php_ini_loaded_file() ?: 'PHP configuration file could not be detected';
    $extensionList = '';

    foreach ($missingExtensions as $extension => $label) {
        $extensionList .= '<li><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong> (<code>' .
            htmlspecialchars($extension, ENT_QUOTES, 'UTF-8') . '</code>)</li>';
    }

    echo '<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Configuration Required</title>
    <style>
        body { margin: 0; padding: 32px 16px; background: #f5f7fa; color: #1f2937; font-family: Arial, sans-serif; }
        .card { max-width: 760px; margin: 40px auto; padding: 28px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 4px 18px rgba(0,0,0,.06); }
        h1 { margin-top: 0; font-size: 24px; }
        .error { padding: 14px 16px; background: #fff1f2; border-left: 4px solid #dc2626; border-radius: 4px; }
        li { margin: 8px 0; }
        code { background: #f3f4f6; padding: 2px 5px; border-radius: 3px; }
        .steps { line-height: 1.6; }
        .muted { color: #6b7280; font-size: 14px; word-break: break-word; }
    </style>
</head>
<body>
<div class="card">
    <h1>System Configuration Required</h1>
    <div class="error">
        <strong>Perfect LPG cannot start because a required PHP extension is missing.</strong>
        <ul>' . $extensionList . '</ul>
    </div>

    <h2>How to fix</h2>
    <ol class="steps">
        <li>Open your PHP configuration file:
            <br><code>' . htmlspecialchars($phpIni, ENT_QUOTES, 'UTF-8') . '</code>
        </li>
        <li>Enable each missing extension. For XAMPP, for example:
            <br><code>;extension=intl</code> → <code>extension=intl</code>
        </li>
        <li>Save <code>php.ini</code> and restart Apache.</li>
        <li>Refresh this page.</li>
    </ol>

    <p class="muted">PHP version: ' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') . '</p>
</div>
</body>
</html>';

    exit(1);
}

/*
 *---------------------------------------------------------------
 * SET THE CURRENT DIRECTORY
 *---------------------------------------------------------------
 */

// Path to the front controller (this file)
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Ensure the current directory is pointing to the front controller's directory
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 * This process sets up the path constants, loads and registers
 * our autoloader, along with Composer's, loads our constants
 * and fires up an environment-specific bootstrapping.
 */

// LOAD OUR PATHS CONFIG FILE
// This is the line that might need to be changed, depending on your folder structure.
require FCPATH . '../app/Config/Paths.php';
// ^^^ Change this line if you move your application folder

$paths = new Paths();

// LOAD THE FRAMEWORK BOOTSTRAP FILE
require $paths->systemDirectory . '/Boot.php';

exit(Boot::bootWeb($paths));
