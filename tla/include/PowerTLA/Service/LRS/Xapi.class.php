<?php
namespace PowerTLA\Service\LRS;

use \PowerTLA\Service\BaseService;
/**
 *
 */
class Xapi extends BaseService
{
    private $lrs;

    public static function apiDefinition($apis, $prefix, $link="xapi", $name="")
    {
        // TODO Extend the api definition with the required information
        return parent::apiDefinition($apis, $prefix, $link, "gov.adlnet.xapi");
    }

    protected function initializeRun()
    {
        parent::initializeRun();
        $this->lrs = $this->VLE->getLRS();
    }

    protected function validateOperation()
    {
        // this function checks whether the current user is eligible to
        // push or fetch the requested information. validateOperation() covers entry
        // level access control.
        if ($this->VLE->isGuestUser())
        {
            // in production set the status to not authenticated
            // both, web-based cookie sessions and oauth should be valid
            $this->status = RESTling::OPERATION_FORBIDDEN;
            $this->authentication_required();
        }
    }

    protected function validateData()
    {
        // get API
        $op  = explode("_", $this->operation);
        $m   = array_shift($op);
        $api = implode("_", $op);

        if (array_key_exists("agent", $this->queryParam))
        {
            $agent    = $this->queryParam["agent"];
        }

        if (!empty($agent))
        {
            try
            {
                $agentO = json_decode($agent, true);
                $agentInfo = $this->VLE->validateAgent($agentO);
                if (!isset($agentInfo))
                {
                    // unknown agent, agents need to have a valid
                    // UserId in the system
                    throw new Exception("BAD DATA");
                }
            }
            catch (Exception $e)
            {
                $this->state = RESTling::BAD_DATA;
            }
        }

        // load the core capabilities
        $ctxt = new \stdClass();
        $ctxt->object = $this->queryParam;
        $privs = $this->VLE->getPrivileges($ctxt);

        // per API ACL
        if ($this->status == RESTling::OK &&
            (!isset($agentInfo) ||
            !$this->VLE->checkAgentProfile($agentInfo)))
        {
            /**
             * the acl can be only reltive to the context.
             *
             * based on the context the VLEHandler must determine which statements
             * the active user can see.
             *
             * By default a user can see only those statements where it is an actor.
             *
             * The VLE handler may extend this to certain contexts.
             */


            // check writeObjectSelf because we allow only authenticated users to
            // access the LRS. Only authenticated users have the privilege
            // to access the LRS, guestusers are excluded
            if (!$privs ||
                !$privs->writeObjectSelf)
            {
                $this->status = RESTling::OPERATION_FORBIDDEN;
            }
        }

        if ($this->status == RESTling::OK &&
            isset($privs))
        {
            /**
             * Need to verify that the active user is the same as the
             * user in the provided actions.
             */

            if($this->operation == "put_statements")
            {
                if (isset($this->inputData) &&
                    !array_key_exists("id", $this->inputData))
                {
                    $this->state = RESTling::BAD_DATA;
                }
            }
            else if ($this->operation == "post_statements")
            {
                if (isset($this->queryParam) &&
                          array_key_exists("statementId", $this->queryParam))
                {
                    if (isset($this->inputData) &&
                        !array_key_exists("id", $this->inputData))
                    {
                        $this->state = RESTling::BAD_DATA;
                    }
                }
                else if (isset($this->inputData) &&
                         array_key_exists("id", $this->inputData))
                {
                    $this->state = RESTling::BAD_DATA;
                }
            }
            else if ($this->operation == "delete_statements")
            {
                if (isset($this->queryParam) &&
                    !array_key_exists("statementId", $this->queryParam))
                {
                    $this->state = RESTling::BAD_DATA;
                }
            }
            else if ($this->operation != "get")
            {
                // in this case the document api is requested
                $this->queryParam["doctype"] = $api;

                switch ($api)
                {
                    case "activities_profile":
                        if (isset($this->queryParam) &&
                            !array_key_exists("activityId", $this->queryParam) ||
                            ($m != "get" &&
                             !array_key_exists("profileId", $this->queryParam)))
                        {
                            $this->state = RESTling::BAD_DATA;
                        }
                        break;
                    case "activities_state":
                        if (isset($this->queryParam) &&
                            !array_key_exists("activityId", $this->queryParam) &&
                            !array_key_exists("agent", $this->queryParam))
                        {
                            $this->state = RESTling::BAD_DATA;
                        }
                        break;
                    case "agent_profile":
                        if (isset($this->queryParam) &&
                            !array_key_exists("agent", $this->queryParam)||
                            ($m != "get" &&
                             !array_key_exists("profileId", $this->queryParam)))
                        {
                            $this->state = RESTling::BAD_DATA;
                        }
                        break;
                    case "statements":
                        unset($this->queryParam["doctype"]);
                        if (isset($this->queryParam) &&
                            !array_key_exists("agent", $this->queryParam))
                        {
                            if (!($privs->readObject ||
                                  $privs->readContext))
                            {
                                $this->status = RESTling::OPERATION_FORBIDDEN;
                            }
                        }
                        else
                        {
                            if (isset($agentO) &&
                                array_key_exists("_system", $agentO) &&
                                $agentO["_system"] != $this->VLE->getUserId() &&
                                !($privs->readObject ||
                                  $privs->readContext))
                            {
                                $this->state = RESTling::OPERATION_FORBIDDEN;
                            }
                        }
                        break;
                    default:
                        $this->state = RESTling::BAD_DATA;
                        break;
                }
            }
        }
    }

    /**
     * THE ACTUAL API
     **/

    // About resource
    protected function get()
    {
        $this->data = array("version" => array("1.0.3"));
    }

    // Statement API
    protected function get_statements()
    {
        // get the activity stream
        if (array_key_exists("statementId", $this->queryParam))
        {
            $this->lrs->getAction($this->queryParam["statementId"]);
        }
        else
        {
            $this->data = $this->lrs->getStream($this->queryParam);
        }
    }

    protected function put_statements()
    {
        $this->data = $this->lrs->handleStatement($this->inputData);
    }

    protected function post_statements()
    {
        if (array_key_exists("statementId", $this->queryParam))
        {
            $this->lrs->extendStatement($this->inputData);
        }
        else
        {
            $this->data = $this->lrs->processStatementStream($this->inputData);
        }
    }

    protected function delete_statements()
    {
        if (array_key_exists("statementId", $this->queryParam)) {
            $this->lrs->deleteStatement($this->queryParam["statementId"]);
        }
    }

    // Agent Document API
    private function getDocument($type)
    {
        if ($type == "activities_state")
        {
            if (!array_key_exists("stateId", $this->queryParam))
            {
                $this->data = $this->lrs->getDocumentList($this->queryParam);
            }
            else
            {
                $this->data = $this->lrs->getDocument($this->queryParam);
            }
        }
        else if (!array_key_exists("profileId", $this->queryParam))
        {
            $this->data = $this->lrs->getDocumentList($this->queryParam);
        }
        else
        {
            $this->data = $this->lrs->getDocument($this->queryParam);
        }
    }

    private function createDocument($type)
    {
        $this->data = [];
        if (!$this->lrs->createDocument($this->inputData,
                                        $this->queryParam))
        {
            $this->state = RESTling::BAD_DATA;
        }
    }

    private function updateDocument($type)
    {
        $this->data = [];
        if (!$this->lrs->storeDocument($this->inputData,
                                       $this->queryParam))
        {
            $this->state = RESTling::BAD_DATA;
        }
    }

    private function deleteDocument($type)
    {
        $this->data = [];
        if (!$this->lrs->removeDocument($this->inputData,
                                        $this->queryParam))
        {
            $this->state = RESTling::BAD_DATA;
        }
    }

    protected function get_agents_profile()
    {
        $this->getDocument("agents_profile");
    }

    protected function put_agents_profile()
    {
        $this->createDocument("agents_profile");
    }

    protected function post_agents_profile()
    {
        $this->updateDocument("agents_profile");
    }

    protected function delete_agents_profile()
    {
        $this->deleteDocument("agents_profile");
    }

    // Activities Document API

    // activity profiles
    protected function get_activities_profile()
    {
        $this->getDocument("activities_profile");
    }

    protected function put_activities_profile()
    {
        $this->createDocument("activities_profile");
    }

    protected function post_activities_profile()
    {
        $this->updateDocument("activities_profile");
    }

    protected function delete_activities_profile()
    {
        $this->deleteDocument("activities_profile");
    }

    // state api

    protected function get_activities_state()
    {
        $this->getDocument("activities_state");
    }

    protected function put_activities_state()
    {
        $this->createDocument("activities_state");
    }

    protected function post_activities_state()
    {
        $this->updateDocument("activities_state");
    }

    protected function delete_activities_state()
    {
        $this->deleteDocument("activities_state");
    }
}

?>
