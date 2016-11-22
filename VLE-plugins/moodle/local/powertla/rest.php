<?php
/**
 * This code is part of PowerTLA and Licensed under GNU Affero License 3.0
 *
 * @author: Christian Glahn
 */

/**
 * Code Logic
 *
 * Part 1: Setup the LMS PowerTLA environment
 * Part 2: Service Discovery
 * Part 3: Service Launch
 */

/** *****************************************************************
 * Part 1: Setup the LMS PowerTLA environment
 *
 * In this part we setup the core environment and include paths
 */

define("TLA_LMS", "Moodle");

// note: if the powertle.ini is missing this script will fail with
// an 500 error code (as it should).

// TODO: The plugin UI should generate the ini file
$TLAConfig = parse_ini_file("powertla.ini", true);

// set the include paths so we can host the common include files outside the LMS folder
set_include_path($TLAConfig["PowerTLA"]["include_path"] . PATH_SEPARATOR .
                 $TLAConfig["PowerTLA"]["system_path"] .  PATH_SEPARATOR .
                 get_include_path());

//we should include the moodle root path into the include files

chdir($TLAConfig["PowerTLA"]["system_path"]);

// ensure that PHP has the local timezone instantiated
date_default_timezone_set($TLAConfig["PowerTLA"]["TLA_TIMEZONE"]);

// setup constants
define("TLA_TOKENTYPE", $TLAConfig["PowerTLA"]["TLA_TOKENTYPE"]);

// Init Autoloaders for RESTling and PowerTLA Classes
//include_once('RESTling/contrib/Restling.auto.php');
include_once('PowerTLA/PowerTLA.auto.php');

/** *****************************************************************
 * Part 2: Service Discovery
 *
 * Identify the service to launch on the grounds of the request URL.
 *
 * During this phase the PATH_INFO variable is stripped, so the
 * service can work as it would have been launched via a dedicated
 * script.
 *
 *
 */
if(array_key_exists("PATH_INFO", $_SERVER) &&
   !empty($_SERVER["PATH_INFO"])) {

    $pi = explode("/", $_SERVER["PATH_INFO"]);
    $e = array_shift($pi);
    $serviceType = strtolower(array_shift($pi));
    $serviceName = array_shift($pi);

    array_unshift($pi, $e);
    $_SERVER["PATH_INFO"] = implode("/", $pi);

    $typemap = array(
        "xapi"        => "LRS",
        "lrs"         => "LRS",
        "content"     => "Content",
        "identity"    => "Identity",
        "idp"         => "Identity",
        "competence"  => "Competences",
        "assessment"  => "Competences"
    );

    if (isset($serviceName) &&
        !empty($serviceName) &&
        in_array($serviceType, array_keys($typemap)) &&
        isset($serviceName) &&
        !empty($serviceName)) {

        $aSN = explode("-", strtolower($serviceName));

        $serviceName = "";
        foreach ($aSN as $sn) {
            $serviceName  .= ucfirst($sn);
        }

        // $serviceName .= "Service";

        // preload the service class
        $phpServiceName = "PowerTLA\\Service\\".$typemap[$serviceType]."\\$serviceName";
    }
}

/** *****************************************************************
 * Part 3: Service Launch
 *
 * Instanciate the service class and launch the service
 *
 * Note: if something goes seriously wrong until this point we will launch our
 * Error Service.
 */
if (empty($phpServiceName)) {
    $service = new PowerTLA\Service\ErrorService("invalid call", "Missing Service");
}
else if (class_exists($phpServiceName, true)) {
    $service = new $phpServiceName($TLAConfig);
}
else {
    $service = new PowerTLA\Service\ErrorService("instantiation", "service $serviceType/$serviceName does not exist ($phpServiceName)");
}
// run the service
$service->run();

?>
