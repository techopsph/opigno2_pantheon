<?php
namespace Dompdf;

/**
 * Autoloads Dompdf classes
 *
 * @package Dompdf
 */
class Autoloader
{
    const PREFIX = 'Dompdf';

    /**
     * Register the autoloader
     */
    public static function register()
    {
        spl_autoload_register([new self, 'autoload']);
    }

    /**
     * Autoloader
     *
     * @param string
     */
    public static function autoload($class)
    {
        if ($class === 'Dompdf\Cpdf') {
            require_once __DIR__ . "/../lib/Cpdf.php";
            return;
        }


        // Autoload html 5 libs.
        if (strpos($class, 'HTML5_') !== FALSE) {
            $class_name = str_replace('HTML5_', '', $class);
            require_once __DIR__ . "/../lib/html5lib/" . $class_name . ".php";
            return;
        }

        $prefixLength = strlen(self::PREFIX);
        if (0 === strncmp(self::PREFIX, $class, $prefixLength)) {
            $file = str_replace('\\', '/', substr($class, $prefixLength));
            $file = realpath(__DIR__ . (empty($file) ? '' : '/') . $file . '.php');
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
}
