<?php

namespace PowerTLA\Handler;

abstract class VLE extends \RESTling\Logger
{
    protected $lmstype;

    protected $guestuserid;

    protected $privileges;

    /**
     * internal data store for LMS Interfaces.
     */
    private $handler;

    public function __construct($lmstype)
    {
        $this->lmstype = $lmstype;
        $this->handler = new \stdClass();
        $this->initLMS();
    }

    abstract protected function initLMS();

    public function getUserId()
    {
        return $this->getIdentityProvider()->getUserId();
    }

    public function getUserToken()
    {
        $retval = null;
        $userDetails = $this->getIdentityProvider()->getUserDetails();

        if ($userDetails)
        {
            $retval = $userDetails["id"];
        }

        return $retval;
    }

    public function getAgentProfile()
    {
        if (!$this->isGuestUser())
        {
            return $this->getUserToken();
        }
        return null;
    }

    /**
     * validates an agent object (as used by XAPI)
     */
    public function validateAgent($agent)
    {
        if(!empty($agent) && is_array($agent))
        {
            $idp = $this->getIdentityProvider();

            if(array_key_exists("mbox", $agent))
            {
                $mail = array_pop(explode(":", $agent["mbox"]));
                return $idp->findUserByMail($mail);
            }

            if(array_key_exists("openid", $agent))
            {
                $aTmp = explode("/", $agent["openid"]);
                $token = array_pop($aTmp);

                return $idp->findUserByToken($token);
            }

            if(array_key_exists("account", $agent))
            {
                if(array_key_exists("name", $agent["account"]))
                {
                    return $idp->findUserByLogin($agent["account"]["name"]);
                }

                if(array_key_exists("homepage", $agent["account"]))
                {
                    return $idp->findUserByHomepage($agent["account"]["homepage"]);
                }
            }
        }

        return null;
    }

    public function checkAgentProfile($profile)
    {
        if (isset($profile) &&
            $profile["id"] == $this->getAgentProfile())
        {
            return true;
        }
        return FALSE;
    }

    public function setGuestUser($username)
    {
        if (!empty($username))
        {
            $this->guestuserid = $username;
        }
    }

    public function isGuestUser()
    {
        return $this->getIdentityProvider()->isGuestUser();
    }

    public function isActiveUser()
    {
        return !$this->isGuestUser();
    }

    public function apiDefinition()
    {
        $pm = $this->getPluginManager();
        return $pm->getAPI();
    }

    /**
     * this function loads the actual privileges for the given system.
     *
     * This function expects two contexts: the object context and the user context
     * Both contexts hold exactly one id.
     *
     * The user context hods a user id. checkPrivileges() should check if the
     * present user id is allowed to access information for the provided user id.
     *
     * The object context holds a content/activity object id. checkPrivileges()
     * must set the object privileges depending the the assigned privileges of the
     * active user.
     *
     * if both user and object context are present, then checkPrivileges() must
     * check whether the provided user context has access to the provided object
     * context. In this configuration, the function must check if the active user
     * is undefined OR the guest user. If this is the case, the function MUST NOT
     * retrieve the actual privileges, but an object that has all properties
     * set to false.
     *
     * If the context is empty the function must assume the user context of the
     * active user.
     *
     * This function MUST return the completed privilege object.
     */
    abstract protected function checkPrivileges($context, $privileges);

    /**
     * creates the default privilege object
     */
    protected function initPrivileges()
    {
        return array();
    }

    /**
     * loads the privileges of a user for a given context.
     * The context is an array of the kind of a query parameter list.
     *
     * The function accepts a context, which will be either an object
     * context or a user context
     *
     * PowerTLA supports differnt privilege types.
     *
     * 1. personal privileges
     * 2. context privileges
     *
     * each privilege set has the same set of attributes, which define the
     * range of data that can get exposed to the active user. The ACL should
     * always check the personal privileges before the context privileges.
     *
     * NOTE: Most LMS build on a role system rather than a user-based AC.
     * Within this scope, the user privileges are typically in the form of
     * "can do" and "can do for others". The former refers to personal
     * privileges, while the latter refers to context definitions.
     *
     * PowerTLA is ignorant about the user roles and just accepts the
     * different role specific privileges and permissions. In order to
     * provide a consistent API to the privileges, it abstracts from the
     * various forms.
     *
     * * readObject: can read a given object
     * * writeObject: can write a given object
     * * readProfile: can read a user profile
     * * writeProfile: can change a user profile
     * * readActionStream: can read the LRS action stream
     * * writeActionStream: can write the LRS action stream
     */
    public function getPrivileges($context)
    {
        // enable privilege caching
        if (!isset($this->privileges))
        {
            // return global privileges
            $this->privileges = $this->checkPrivileges($context,
                                                       $this->initPrivileges());
        }

        return $this->privileges;
    }


    /**
     * The session validator is the prime interface to user authorisation.
     */
    public function getSessionValidator()
    {
        if (!property_exists($this->handler, "validator"))
        {
            $validatorClass = "PowerTLA\\" . $this->lmstype . "\\Validator\\Session";

            if (class_exists($validatorClass, true)) {
                $this->handler->validator = new $validatorClass($this);
            }
        }

        if (property_exists($this->handler, "validator")) {
            return $this->handler->validator;
        }
        return null;
    }

    public function getTokenValidator()
    {
        if (!property_exists($this->handler, "token_validator"))
        {
            $validatorClass = "PowerTLA\\" . $this->lmstype . "\\Validator\\Token";
            if (class_exists($validatorClass, true)) {
                $this->handler->token_validator = new $validatorClass($this);
            }
        }

        if (property_exists($this->handler, "token_validator")) {
            return $this->handler->token_validator;
        }
        return null;
    }

    final public function getHandler($name, $component="") {
        if (!empty($name) &&
            is_string($name)) {

            $pname = $name = ucfirst(strtolower($name));

            if (!empty($component)) {

                $pname = $name . "_". $component;
            }

            if (!property_exists($this->handler, $pname)) {

                $handlerClass = "\\PowerTLA\\" . $this->lmstype . "\\Handler\\" . (!empty($component) ? "$component\\" : "") . $name;

                // $this->log("load handler for $pname class " .$handlerClass );
                if (class_exists($handlerClass, true)) {
                    $this->handler->$pname = new $handlerClass($this);
                }
            }

            // be 100% certain that the property now exists
            // (may not becaue the class is missing)
            if (property_exists($this->handler, $pname)) {
                return $this->handler->$pname;
            }
        }
        return null;
    }

    /**
     * Plugin provides the core Interface to the LMS, such as Access Control
     * and Mode handling
     */
    public function getPluginManager()
    {
        return $this->getHandler("Plugin");
    }

    public function getCourseBroker()
    {
        return $this->getHandler("course", "Content");
    }

    public function getQTIPoolBroker()
    {
        return $this->getHandler("qtipool", "Content");
    }

    public function getLRS()
    {
        return $this->getHandler("xapi", "LRS");
    }

    public function getIdentityProvider()
    {
        return $this->getHandler("idp", "Identity");
    }
}

?>
