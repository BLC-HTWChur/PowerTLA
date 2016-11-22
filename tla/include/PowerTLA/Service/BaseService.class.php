<?php

namespace PowerTLA\Service;

class BaseService extends \RESTling\Service {

    /**
     * @property $VLE
     *
     */
    public $VLE;

    private $config;

    public static function apiDefinition($apis, $prefix, $link, $name)
    {
        // trim all leading slashes
        $prefix = preg_replace("/^\/+/", "", $prefix);

        $apis[$name] = array(
            "apiLink" => $prefix . "/" . $link,
            "transport" => array("REST")
        );

        return $apis;
    }

    public function __construct($config)
    {
        parent::__construct();
        $this->config = $config;
        $this->setVLE();

        // CORS should be OK for the testing.
        // In production code we need to have additional access control
        // for CORS Sites

        // $service->allowCORS();
        // $service->addCORSHost('*', array('GET', 'POST', 'PUT'));
    }

    public function setVLE()
    {
        $systemClass = "\\PowerTLA\\" . TLA_LMS . "\\Handler\\System";
        $this->VLE = new $systemClass();
        $this->VLE->setGuestUser($this->config["PowerTLA"]["TLA_GUESTUSER"]);

        // any of the two has to yield true
        $validator = $this->VLE->getSessionValidator();
        if (isset($validator))
        {
            $this->addHeaderValidator($validator);
        }

        $validator = $this->VLE->getTokenValidator();
        if (isset($validator))
        {
            $this->addHeaderValidator($validator);
        }
    }

    /**
     * @method void
     */
    protected function initializeRun()
    {
        $this->response_type = "json";

        if ($this->status == \RESTling\Service::OK)
        {
            // now the lms can work.
            // moodle might be confused that something snatched the data first.

            if (!$this->VLE)
            {
                $this->fatal("VLE handler is missing");
                $this->status = \RESTling\Service::UNINITIALIZED;
            }
            else if (!$this->VLE->getPluginManager()->isActive())
            {
                $this->log("PowerTLA has been deactivated!");
                // we need a different status for this case.
                $this->status = \RESTling\Service::UNINITIALIZED;
                // TODO: add additional information if in maintenace mode
            }
            else
            {
                parent::initializeRun();
            }
        }
    }
}

?>
