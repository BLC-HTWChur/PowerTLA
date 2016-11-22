<?php
/**
 * This code is part of PowerTLA and Licensed under GNU Affero License 3.0
 *
 * @author: Christian Glahn
 */

// This script will be invoked by the RSD2 plugin.

/**
 * set up PowerTLA
 */
$TLAConfig = parse_ini_file(__DIR__ .DIRECTORY_SEPARATOR. "powertla.ini", true);

// set the include paths so we can host the common include files outside the LMS folder
set_include_path($TLAConfig["PowerTLA"]["include_path"] . PATH_SEPARATOR .
                 get_include_path());

// Init Autoloaders for RESTling and PowerTLA Classes
include_once('PowerTLA/PowerTLA.auto.php');

/**
 * prepare the powerTLA service link
 */

$tpath  = explode(DIRECTORY_SEPARATOR, __DIR__);
$pathname  = array_pop($tpath);

$engineRoot =  $pathname . "/rest.php/";

$pathname = array_pop($tpath);

if ($pathname == "local") {
    // we are installed in local ... this is enforced on moodle
    $engineRoot = $pathname .DIRECTORY_SEPARATOR. $engineRoot;
}

$owd = getcwd();

chdir($TLAConfig["PowerTLA"]["include_path"] .DIRECTORY_SEPARATOR. "PowerTLA".DIRECTORY_SEPARATOR. "Service");


// Evaluate all Services for the 4 TLA components
foreach (array("LRS", "Content", "Identity", "Competences") as $serviceType) {

    $enginepath = $engineRoot . $serviceType;

    if (file_exists($serviceType)) {
        /**
         * fetch API information for each service
         *
         * Services are always named as 'class.<SERVICENAME>Service.php'.
         * All other files are ignored
         */
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($serviceType)) as $file) {
            // include API files.
            $filename = $file->getFilename();

            list( $classname, $suffix ) = explode('.', $filename, 2);

            if (!empty($classname) &&
                $suffix == "class.php") {

                $class = "\\PowerTLA\\Service\\$serviceType\\$classname";
                $fname = "$class::apiDefinition";
    //            error_log('fname= ' . $fname );
    //            error_log('dname= ' . $file->getPathname());


                    // require_once("." .DIRECTORY_SEPARATOR. $file->getPathname());


                    // Note: because of call_user_func we cannot pass the apis array as reference :(
                    if (class_exists($class, true) && is_callable($fname)) {
                        $tapis = call_user_func($fname, $apis, $enginepath);

                        foreach ($tapis as $k => $v) {
                            $apis[$k] = $v;
                        }
                    }
            }
        }
    }
}

chdir($owd);

?>