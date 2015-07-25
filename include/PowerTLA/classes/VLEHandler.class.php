<?php

class VLEHandler extends Logger
{
    protected $dbhandler;
    protected $user;

    protected $guestuserid;

    public function __construct()
    {}

    public function getUserId()
    {
        return $this->user;
    }

    public function isGuestUser()
    {
        return TRUE;
    }

    public function getDBHandler()
    {
        return $this->dbhandler;
    }

    protected function setDBHandler($dbh)
    {
        $this->dbhandler = $dbh;
    }

    public function isPluginActive($pname)
    {
        return false;
    }

    // active()  should return true after validateSession() and initiateSession() succeeded
    public function validateSession($sessionid)
    {}

    public function initiateSession($credentials)
    {}

    public function active()
    {
        return false;
    }

    public function getCourseBroker()
    {
        return null;
    }

    public function getQTIPoolBroker()
    {
        return null;
    }

    public function setGuestUser($username)
    {
        if (isset($username) && !empty($username))
        {
            $this->guestuserid = $username;
        }
    }
}

?>
