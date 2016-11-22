<?php

    /**
     * Autoloader for the Resting TLA subsystem for the PHP5 autoloader system.
     *
     * In order to use autoloading this file needs to be loaded during the
     * initialization of your root script.
     */

    define("TLA_VERSION", "0.9");

    // make the include path available to the entire system.
    global $powertlapath;

    // determine the relevant include path only once.
    $powertlapath = "";
    $prefixes = explode(PATH_SEPARATOR, get_include_path());

    foreach ( $prefixes as $p )
    {
        //    error_log($p);
        if (file_exists($p .DIRECTORY_SEPARATOR.'PowerTLA.auto.php' ))
        {
            $powertlapath = $p;
            break;
        }
    }
    // error_log("auto: " . $powertlapath);

    spl_autoload_register(function ($class) {
        $class = ltrim($class, '\\');

        $parts = explode('\\', $class);

        $root = array_shift($parts);

        if (!empty($root)) {
            $cpath = array();
            // direct namespace
            $cpath[] = $root .DIRECTORY_SEPARATOR. implode(DIRECTORY_SEPARATOR, $parts) . ".class.php";

            // sub-directory namespaces
            $cpath[] = $root .DIRECTORY_SEPARATOR. "classes" .DIRECTORY_SEPARATOR. implode(DIRECTORY_SEPARATOR, $parts) . ".class.php";
            $cpath[] = $root .DIRECTORY_SEPARATOR. "src" .DIRECTORY_SEPARATOR. implode(DIRECTORY_SEPARATOR, $parts) . ".class.php";
            $cpath[] = $root .DIRECTORY_SEPARATOR. "lib" .DIRECTORY_SEPARATOR. implode(DIRECTORY_SEPARATOR, $parts) . ".class.php";

            // for developer prefixed namespaces
            $root = array_shift($parts);
            $cpath[] = strtolower($root) .DIRECTORY_SEPARATOR. "src" .DIRECTORY_SEPARATOR. implode(DIRECTORY_SEPARATOR, $parts) . ".class.php";
            $cpath[] = strtolower($root) .DIRECTORY_SEPARATOR. "lib" .DIRECTORY_SEPARATOR. implode(DIRECTORY_SEPARATOR, $parts) . ".class.php";

            $prefixes = explode(PATH_SEPARATOR, get_include_path());

            foreach ( $prefixes as $p ) {
                foreach ($cpath as $path) {
                    if (file_exists($p . DIRECTORY_SEPARATOR . $path)) {
                        include_once $p . DIRECTORY_SEPARATOR . $path;
                        break 2;
                    }
                }
            }
        }

    });
?>
