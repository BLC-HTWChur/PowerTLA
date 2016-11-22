<?php
//set_include_path(" "  . PATH_SEPARATOR . get_include_path());
require_once('Services/Init/classes/class.ilInitialisation.php');
//require_once('Services/Init/classes/class.ilInitialization.php');

class ilRESTInitialisation extends ilInitialisation {

    function requireCommonIncludes() {
        global $ilBench;

        // get pear
        require_once("include/inc.get_pear.php");
        require_once("include/inc.check_pear.php");

        //include class.util first to start StopWatch
        require_once "Services/Utilities/classes/class.ilUtil.php";
        require_once "classes/class.ilBenchmark.php";

        $ilBench = new ilBenchmark();
        $GLOBALS['ilBench'] = $ilBench;

        // BEGIN Usability: Measure response time until footer is displayed on form
        // The stop statement is in class.ilTemplate.php function addILIASfooter()
        $ilBench->start("Core", "ElapsedTimeUntilFooter");
        // END Usability: Measure response time until footer is displayed on form

        $ilBench->start("Core", "HeaderInclude");

        // start the StopWatch
        $GLOBALS['t_pagestart'] = ilUtil::StopWatch();

        $ilBench->start("Core", "HeaderInclude_IncludeFiles");

        // Major PEAR Includes
        require_once "PEAR.php";
        require_once "Auth/Auth.php";

        //include classes and function libraries
        require_once "include/inc.db_session_handler.php";
        require_once "Services/Database/classes/class.ilDB.php";
        require_once "Services/AuthShibboleth/classes/class.ilShibboleth.php";
        require_once "classes/class.ilias.php";
        require_once 'Services/User/classes/class.ilObjUser.php';
        require_once "classes/class.ilFormat.php";
        require_once "Services/Calendar/classes/class.ilDatePresentation.php";
        require_once "classes/class.ilSaxParser.php";
        require_once "Services/Object/classes/class.ilObjectDefinition.php";
        require_once "Services/Style/classes/class.ilStyleDefinition.php";
        require_once "Services/Tree/classes/class.ilTree.php";
        require_once "Services/Language/classes/class.ilLanguage.php";
        require_once "Services/Logging/classes/class.ilLog.php";
        require_once "classes/class.ilCtrl2.php";
        require_once "Services/AccessControl/classes/class.ilConditionHandler.php";
        require_once "classes/class.ilBrowser.php";
        require_once "classes/class.ilFrameTargetInfo.php";
        require_once "Services/Navigation/classes/class.ilNavigationHistory.php";
        require_once "Services/Help/classes/class.ilHelp.php";
        require_once "include/inc.ilias_version.php";

        //include role based access control system
        require_once "Services/AccessControl/classes/class.ilAccessHandler.php";
        require_once "Services/AccessControl/classes/class.ilRbacAdmin.php";
        require_once "Services/AccessControl/classes/class.ilRbacSystem.php";
        require_once "Services/AccessControl/classes/class.ilRbacReview.php";

        // include object_data cache
        require_once "classes/class.ilObjectDataCache.php";
        require_once 'Services/Tracking/classes/class.ilOnlineTracking.php';

        //include LocatorGUI
        require_once "Services/Locator/classes/class.ilLocatorGUI.php";

        // include error_handling
        require_once "classes/class.ilErrorHandling.php";

        $ilBench->stop("Core", "HeaderInclude_IncludeFiles");
    }

    function determineClient() {
        global $ilIliasIniFile;

        // check whether ini file object exists
        if (!is_object($ilIliasIniFile))
        {
            die ("Fatal Error: ilInitialisation::determineClient called without initialisation of ILIAS ini file object.");
        }

        // CGL: there is some "client" information stored in the ini file.
        // CGL: without this information the database initialization fails.
        $client_id = $ilIliasIniFile->readVariable("clients","default");
        define ("CLIENT_ID", $client_id);
    }

    function goToPublicSection() {
        global $ilAuth;

        // logout and end previous session
        $ilAuth->logout();
        session_unset();
        session_destroy();

        // new session and login as anonymous
        $this->setSessionHandler();
        session_start();
        $_POST["username"] = "anonymous";
        $_POST["password"] = "anonymous";
        ilAuthUtils::_initAuth();

        $oldSid = session_id();

        $ilAuth->start();

        $newSid = session_id();
        //include_once './Services/Payment/classes/class.ilPaymentShoppingCart.php';
        //ilPaymentShoppingCart::_migrateShoppingCart($oldSid, $newSid);

        if (ANONYMOUS_USER_ID == "")
        {
            // REST SERVICES SHOULD RESPOND A 400 error
            die ("Public Section enabled, but no Anonymous user found.");
        }

        if (!$ilAuth->getAuth())
        {
            // REST SERVICES SHOULD RESPOND A 400 error
            die("ANONYMOUS user with the object_id ".ANONYMOUS_USER_ID." not found!");
        }

        // stop immediately to avoid redirection madness.
        return true;
    }

    function initIlias($context = "web") {
        global $ilDB, $ilUser, $ilLog, $ilErr, $ilClientIniFile, $ilIliasIniFile,
                $ilSetting, $ilias, $https, $ilObjDataCache,
                $ilLog, $objDefinition, $lng, $ilCtrl, $ilBrowser, $ilHelp,
                $ilTabs, $ilMainMenu, $rbacsystem, $ilNavigationHistory;

        // remove unsafe characters
        $this->removeUnsafeCharacters();

        // error reporting
        // remove notices from error reporting
        if (version_compare(PHP_VERSION, '5.3.0', '>='))
        {
                error_reporting((ini_get("error_reporting") & ~E_NOTICE) & ~E_DEPRECATED);
        }
        else
        {
                error_reporting(ini_get('error_reporting') & ~E_NOTICE);
        }

        // include common code files
        $this->requireCommonIncludes();
        global $ilBench;

        // set error handler (to do: check preconditions for error handler to work)
        $ilBench->start("Core", "HeaderInclude_GetErrorHandler");
        $ilErr = new ilErrorHandling();
        $GLOBALS['ilErr'] =& $ilErr;
        $ilErr->setErrorHandling(PEAR_ERROR_CALLBACK,array($ilErr,'errorHandler'));
        $ilBench->stop("Core", "HeaderInclude_GetErrorHandler");

        // prepare file access to work with safe mode (has been done in class ilias before)
        umask(0117);
        // set cookie params
        $this->setCookieParams();

        // $ilIliasIniFile initialisation
        $this->initIliasIniFile();

        // CLIENT_ID determination
        $this->determineClient();

        // $ilAppEventHandler initialisation
        $this->initEventHandling();

        // $ilClientIniFile initialisation
        $this->initClientIniFile();
        // removed redirection madness the service should respond with SERVICE UNAVAILABLE

        // $ilDB initialisation
        $this->initDatabase();

        // init plugin admin class
        include_once("Services/Component/classes/class.ilPluginAdmin.php");
        $ilPluginAdmin = new ilPluginAdmin();
        $GLOBALS['ilPluginAdmin'] = $ilPluginAdmin;

        // set session handler
        $this->setSessionHandler();

        // $ilSetting initialisation
        $this->initSettings();

        // $ilLog initialisation
        $this->initLog();

        // $https initialisation
        require_once 'classes/class.ilHTTPS.php';
        $https = new ilHTTPS();
        $GLOBALS['https'] =& $https;
        $https->enableSecureCookies();
        $https->checkPort();

        if($this->returnBeforeAuth()) return;

        $ilCtrl = new ilCtrl2();
        $GLOBALS['ilCtrl'] =& $ilCtrl;

        // $ilAuth initialisation
        include_once("Services/Authentication/classes/class.ilAuthUtils.php");
        ilAuthUtils::_initAuth();
        global $ilAuth;

        $this->includePhp5Compliance();

        // Do not accept external session ids
        if (!ilSession::_exists(session_id()))
        {
            // $_GET["PHPSESSID"] = "";
            session_regenerate_id();
        }

        // $ilias initialisation
        global $ilias, $ilBench;
        $ilBench->start("Core", "HeaderInclude_GetILIASObject");
        $ilias = new ILIAS();
        $GLOBALS['ilias'] =& $ilias;
        $ilBench->stop("Core", "HeaderInclude_GetILIASObject");

        // $ilObjDataCache initialisation
        $ilObjDataCache = new ilObjectDataCache();
        $GLOBALS['ilObjDataCache'] =& $ilObjDataCache;

        // workaround: load old post variables if error handler 'message' was called
        if (isset($_SESSION["message"]) && $_SESSION["message"])
        {
            $_POST = $_SESSION["post_vars"];
        }

        // put debugging functions here
        require_once "include/inc.debug.php";

        // $objDefinition initialisation
        $ilBench->start("Core", "HeaderInclude_getObjectDefinitions");
        $objDefinition = new ilObjectDefinition();
        $GLOBALS['objDefinition'] =& $objDefinition;
        // $objDefinition->startParsing();
        $ilBench->stop("Core", "HeaderInclude_getObjectDefinitions");

        // init tree
        $tree = new ilTree(ROOT_FOLDER_ID);
        $GLOBALS['tree'] =& $tree;

        // $ilAccess and $rbac... initialisation
        $this->initAccessHandling();

        // authenticate & start session
        PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, array($ilErr, "errorHandler"));
        $ilBench->start("Core", "HeaderInclude_Authentication");
        //var_dump($_SESSION);
        ////require_once('Log.php');
        ////$ilAuth->logger = Log::singleton('error_log',PEAR_LOG_TYPE_SYSTEM,'TEST');
        ////$ilAuth->enableLogging = true;

        if (!defined("IL_PHPUNIT_TEST"))
        {
            $oldSid = session_id();

            $ilAuth->start();

            $newSid = session_id();
            include_once 'Services/Payment/classes/class.ilPaymentShoppingCart.php';
            ilPaymentShoppingCart::_migrateShoppingCart($oldSid, $newSid);
        }

        //var_dump($_SESSION);
        $ilias->setAuthError($ilErr->getLastError());
        $ilBench->stop("Core", "HeaderInclude_Authentication");

        // workaround: force login
        if ((!empty($_GET["cmd"]) && $_GET["cmd"] == "force_login") || $this->script == "login.php")
        {
            $ilAuth->logout();
            if(!isset($_GET['forceShoppingCartRedirect']))
                    $_SESSION = array();
            $_SESSION["AccountId"] = "";
            $ilAuth->start();
            $ilias->setAuthError($ilErr->getLastError());
        }

        // check correct setup
        if (!$ilias->getSetting("setup_ok"))
        {
            die("Setup is not completed. Please run setup routine again.");
        }

        // $ilUser initialisation (1)
        $ilBench->start("Core", "HeaderInclude_getCurrentUser");
        $ilUser = new ilObjUser();
        $ilias->account =& $ilUser;
        $GLOBALS['ilUser'] =& $ilUser;
        $ilBench->stop("Core", "HeaderInclude_getCurrentUser");

        // $ilCtrl initialisation
        //$ilCtrl = new ilCtrl();

        // determin current script and up-path to main directory
        // (sets $this->script and $this->updir)
        $this->determineScriptAndUpDir();

        // $styleDefinition initialisation and style handling for login and co.
        $this->initStyle();
        if (in_array($this->script,
            array("login.php", "register.php", "view_usr_agreement.php"))
            || $_GET["baseClass"] == "ilStartUpGUI")
        {
            $this->handleStyle();
        }

        // init locale
        $this->initLocale();

        // handle ILIAS 2 imported users:
        // check ilias 2 password, if authentication failed
        // only if AUTH_LOCAL
        //echo "A";
        if (AUTH_CURRENT == AUTH_LOCAL && !$ilAuth->getAuth() && $this->script == "login.php" && $_POST["username"] != "")
        {
            if (ilObjUser::_lookupHasIlias2Password(ilUtil::stripSlashes($_POST["username"])))
            {
                if (ilObjUser::_switchToIlias3Password(ilUtil::stripSlashes($_POST["username"]),
                                                       ilUtil::stripSlashes($_POST["password"])))
                {
                    $ilAuth->start();
                    $ilias->setAuthError($ilErr->getLastError());
                                         ilUtil::redirect("index.php");
                }
            }
        }

        //
        // SUCCESSFUL AUTHENTICATION
        //
        if($ilAuth->getStatus() == '' &&
                $ilias->account->isCurrentUserActive() ||
                (defined("IL_PHPUNIT_TEST") && DEVMODE))
        {
            //echo "C"; exit;
            $ilBench->start("Core", "HeaderInclude_getCurrentUserAccountData");
            //var_dump($_SESSION);
            // get user data
            $this->initUserAccount();

            //var_dump($_SESSION);

            // differentiate account security mode
            require_once('Services/PrivacySecurity/classes/class.ilSecuritySettings.php');
            $security_settings = ilSecuritySettings::_getInstance();
            if( $security_settings->getAccountSecurityMode() ==
                ilSecuritySettings::ACCOUNT_SECURITY_MODE_CUSTOMIZED )
            {
                // reset counter for failed logins
                ilObjUser::_resetLoginAttempts( $ilUser->getId() );
            }

            $ilBench->stop("Core", "HeaderInclude_getCurrentUserAccountData");
        }
        else if(!$ilAuth->getAuth())
        {
            require_once('Services/PrivacySecurity/classes/class.ilSecuritySettings.php');
            // differentiate account security mode
            $security = ilSecuritySettings::_getInstance();
            if( $security->getAccountSecurityMode() ==
                ilSecuritySettings::ACCOUNT_SECURITY_MODE_CUSTOMIZED )
            {
                if(isset($_POST['username']) &&
                   $_POST['username'] &&
                   $ilUser->getId() == 0)
                {
                    $username = ilUtil::stripSlashes( $_POST['username'] );
                    $usr_id = ilObjUser::_lookupId( $username );

                    if( $usr_id != ANONYMOUS_USER_ID )
                    {
                        ilObjUser::_incrementLoginAttempts($usr_id);

                        $login_attempts = ilObjUser::_getLoginAttempts( $usr_id );
                        $max_attempts = $security->getLoginMaxAttempts();

                        if( $login_attempts >= $max_attempts &&
                                $usr_id != SYSTEM_USER_ID &&
                                $max_attempts > 0 )
                        {
                            ilObjUser::_setUserInactive( $usr_id );
                        }
                    }
                }
            }
        }
        //
        // SUCCESSFUL AUTHENTICATED or NON-AUTH-AREA (Login, Registration, ...)
        //

        // $lng initialisation
        $this->initLanguage();

        // store user language in tree
        $GLOBALS['tree']->initLangCode();

        // ### AA 03.10.29 added new LocatorGUI class ###
        // when locator data array does not exist, initialise
        if ( !isset($_SESSION["locator_level"]) )
        {
            $_SESSION["locator_data"] = array();
            $_SESSION["locator_level"] = -1;
        }
        // initialise global ilias_locator object

        // ECS Tasks
        include_once('Services/WebServices/ECS/classes/class.ilECSTaskScheduler.php');
        $scheduler = ilECSTaskScheduler::start();

        $ilBench->stop("Core", "HeaderInclude");
    }

    /**
     * EMPTY FUNCTIONS!
     *
     * These function must not get executed, so they are set to emptiness.
     */
    function handleMaintenanceMode() {}
    function setCookieParams() {}
    function checkUserClientIP() {}
    function checkUserAgreement() {}
    function goToLogin($a_auth_stat="") {}
}


?>
