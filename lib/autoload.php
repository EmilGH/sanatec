<?php

declare(strict_types=1);

/**
 * The two vendored PDF libraries, loaded without Composer.
 *
 *   FPDF 1.86  — lib/fpdf   (fpdf.org; permissive licence, see LICENSE.txt)
 *   FPDI 2.6.3 — lib/fpdi   (Setasign; MIT)
 *
 * FPDF draws text and images onto pages; FPDI imports the pages of an
 * existing PDF as templates to draw over. Together they fill the shop's
 * source forms. Nothing under lib/ is served: .htaccess blocks it.
 */

if (!defined('FPDF_FONTPATH')) {
    define('FPDF_FONTPATH', __DIR__ . '/fpdf/font/');
}
require_once __DIR__ . '/fpdf/fpdf.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'setasign\\Fpdi\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/fpdi/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
