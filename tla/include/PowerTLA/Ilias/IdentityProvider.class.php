<?php
namespace PowerTLA;

class IdentityProvider extends IDPBase
{
    public function authenticate($credentials, $clientToken)
    {
        global $ilUser, $ilDB;
        $userId = $ilUser->getUserIdByLogin($credentials["username"]);

        $retval = null;

        if ($userId > 0)
        {
            $ilUser->setId($userId);
            $ilUser->read();

            $passwordHash = $ilUser->getPasswd(); //returns md5-hashed password

            // however, we continue to use SHA1
            $validate = sha1($credentials["username"].
                             $passwordHash .
                             $clientToken["token"]);

            if ($credentials["challenge"] == $validate)
            {
                $retval = $this->generateAccessToken($clientToken);
            }
            else
            {
                // check PIN
                $now   = time();
                $dtime = 24 * 60 * 60 * 1000; // 1 day expiry time

                $pin = $this->checkAccessPin($now - $dtime);
                if (isset($pin))
                {
                    $validate = sha1($credentials["username"].
                                     $pin .
                                     $clientToken["token"]);
                    if ($credentials["challenge"] == $validate)
                    {
                        // invalidate the pin
                        $q = "DELETE FROM pwrtla_authpins WHERE login =  %s";
                        $ilDB->manipulateF($q, array("text"), array($credentials["username"]));

                        $retval = $this->generateAccessToken($clientToken);
                    }
                }
            }
        }
        return $retval;
    }

    public function logout($clientToken)
    {
        global $ilUser, $ilDB;
        $token = $clientToken["token"];
        $type  = $clientToken["type"];

        $q = "DELETE FROM pwrtla_tokens WHERE user_id = %s AND ";
        if ($type == "Bearer")
        {
            $q .= "token_id = %s";
        }
        else
        {
            $q .= "token_key = %s";
        }

        $ilDB->manipulateF($q,
                           array("integer", "text"),
                           array(
                               $ilUser->getId(),
                               $token
                           ));
    }

    public function getUserId()
    {
        global $ilUser;
        return $ilUser->getId();
    }

    public function isGuestUser()
    {
        global $ilUser;

        if ($ilUser->getId() &&
            $ilUser->getLogin() != "anonymous" &&
            !empty($this->guestuser) &&
            $ilUser->getLogin() != $this->guestuser)
        {
            return FALSE;
        }

        return TRUE;
    }

    protected function generateBearerToken($clientToken)
    {
        global $ilUser, $ilDB;

        $tokenkey = $this->randomString(10);

        $ilDB->insert("pwrtla_tokens", array(
            "token_type" => array("text", "Bearer"),
            "token_id"   => array("text", $tokenkey),
            "user_id"    => array("integer", $ilUser->getID()),
            "client_id"  => array("text", $clientToken["client"]),
            "domain"     => array("text", $clientToken["domain"]),
        ));

        return array(
            "type"   => "Bearer",
            "domain"       => $clientToken["domain"],
            "token"        => $tokenkey
        );
    }

    protected function generateMACToken($clientToken)
    {
        global $ilUser, $ilDB;

        $tokenid = $this->randomString(10);
        $tokenkey = $this->randomString(128);

        $ilDB->insert("pwrtla_tokens", array(
            "token_type" => array("text", "MAC"),
            "token_key"  => array("text", $tokenkey),
            "token_id"   => array("text", $tokenid),
            "domain"     => array("text", $clientToken["domain"]),
            "client_id"  => array("text", $clientToken["client"]),
            "user_id"    => array("integer", $ilUser->getID())
        ));

        return array(
            "type"   => "MAC",
            "domain"       => $clientToken["domain"],
            "token"        => $tokenkey,
            "id"           => $tokenid,
            "algorithm"    => "SHA1",
            "sequence"     => array("client", "token", "domain", "method", "url", "nonce"),
            "parameter"    => array("key", "id", "domain", "nonce")
        );
    }

    protected function storeIdentityToken($userid, $token)
    {
        global $ilDB;

        $ilDB->insert("pwrtla_usertokens", array(
            "user_id"    => array("integer", $userid),
            "user_token" => array("text", $token)
        ));
    }

    protected function checkAccessPin($expirytime=0) {
        global $ilUser, $ilDB;

        $retval = null;
        if ($ilUser->getId() &&
            $ilUser->getLogin() != "anonymous" &&
            !empty($this->guestuser) &&
            $ilUser->getLogin() != $this->guestuser)
        {
            $login = $ilUser->getLogin();
            $q = "SELECT pinhash, created FROM pwrtla_authpins WHERE login = %s ";
            $types = array("text");
            $values = array($login);
            $res = $ilDB->queryF($q,
                                 array("text"),
                                 array($ilUser->getLogin()));
            $row = $ilDB->fetchAssoc($res);
            if ($expirytime > 0 &&
                intval($row["created"]) > $expirytime)
            {
                // drop the pin and return nothing
                $q = "DELETE FROM pwrtla_authpins WHERE login =  %s";
                $ilDB->manipulateF($q, array("text"), array($login));
            }
            else
            {
                $retval = $row["pinhash"];
            }
        }
        return $retval;
    }

    public function generateAccessPin() {
        global $ilUser, $ilDB;
        $retval = null;
        if ($ilUser->getId() &&
            $ilUser->getLogin() != "anonymous" &&
            !empty($this->guestuser) &&
            $ilUser->getLogin() != $this->guestuser)
        {
            $tid = sha1($this->randomString(10));
            $startid = rand(0, strlen($tid) - 4);
            $accessPin  = substr($tid, $startid, 4);

            $pinhash = sha1($accessPin);

            // check if there is already a PIN
            $now   = time();
            $dtime = 24 * 60 * 60 * 1000; // 1 day expiry time

            $pin = $this->checkAccessPin($now - $dtime);
            if (isset($pin))
            {
                // invalidate the old pin
                $q = "DELETE FROM pwrtla_authpins WHERE login =  %s";
                $ilDB->manipulateF($q, array("text"), array($ilUser->getLogin()));
            }

            // now create the new pin
            $ilDB->insert("pwrtla_authpins", array(
                "login"     => array("text", $ilUser->getLogin()),
                "created"   => array("integer", $now),
                "pinhash"   => array("text", $pinhash)
            ));
            $retval = $accessPin;
        }

        return $retval;
    }

    /**
     * Load function to handle Identities of *different* users
     */
    public function findUserByID($userid)
    {
        $retval = null;
        // first verify the userid
        $oUser = new ilObjUser($userid);
        $oUser->read();

        if ($oUser->getLogin())
        {
            $tData = $this->loadUserToken($userid);
            $retval = $this->makeUserInfo($oUser, $tData);
        }
        return $retval;
    }

    public function findUserByMail($usermail)
    {
        global $ilDB;
        $retval = null;
        $r = $ilDB->queryF("SELECT usr_id FROM usr_data ".
                           "WHERE email= %s", array("text"), array($usermail));

        if ($data = $ilDB->fetchAssoc($r))
        {
            $oUser  = new ilObjUser($data["usr_id"]);
            $tData  = $this->loadUserToken($data["usr_id"]);
            $retval = $this->makeUserInfo($oUser, $tData);
        }
        return $retval;
    }

    public function findUserByToken($idtoken)
    {
        $retval = null;
        $tData = $this->loadTokenUser($idtoken);
        if (isset($tData) && array_key_exists("id", $tData))
        {
            // load the rest
            $oUser = new ilObjUser($tData["id"]);
            $retval = $this->makeUserInfo($oUser, $tData);
        }
        return $retval;
    }

    public function findUserByLogin($loginname)
    {
        global $ilUser;

        $retval = null;
        $userId = $ilUser->getUserIdByLogin($credentials["username"]);
        if (isset($userId) && $userId > 0)
        {
            $tData = $this->loadUserToken($userId);

            // now get the rest of the data
            $oUser = new ilObjUser($userId);
            $oUser->read();
            $retval = $this->makeUserInfo($oUser, $tData);
        }

        return $retval;
    }

    public function findUserByHomepage($homepage)
    {
        // Ilias has no homepage profile
        if (isset($homepage) &&
            !empty($homepage))
        {
            $aHomepage = explode("/", $homepage);
            $token = array_pop($aHomepage);
            return $this->findUserByToken($token);
        }
        return null;
    }

    protected function loadUserToken($userid)
    {
        if (isset($userid) && $userid > 0)
        {
            $tokenData = $this->loadTokenData(array("id" => $userid));
            if (!isset($tokenData))
            {
                $tokenData = array("id"=> $userid);
                $tokenData["token"] = $this->createIdentityToken($userid);
            }

            return $tokenData;
        }
        return null;
    }

    protected function loadTokenUser($idtoken)
    {
        if (!empty($idtoken))
        {
            return $this->loadTokenData(array("token" => $idtoken));
        }
        return null;
    }

    private function makeUserInfo($oUser, $tData)
    {
        return array(
            "_system"    => $oUser->getId(),
            "name"       => $oUser->getFullname(),
            "id"         => $tData["token"],
            "login"      => $oUser->getLogin(),
            "email"      => $oUser->getEmail(),
            "givenname"  => $oUser->getFirstName(),
            "familyname" => $oUser->getLastName(),
            "language"   => $oUser->getLanguage()
        );
    }

    private function loadTokenData($whereO)
    {
        global $ilDB;
        $sql = "select * from pwrtla_usertokens where ";
        $at = array();
        $av = array();
        if (array_key_exists("id", $whereO))
        {
            $sql .= "user_id = %s";
            $at[] = "integer";
            $av[] = $whereO["id"];
        }
        else if (array_key_exists("token", $whereO))
        {
            $sql .= "user_token = %s";
            $at[] = "text";
            $av[] = $whereO["token"];
        }

        if (!empty($av))
        {
            $res = $ilDB->queryF($sql,
                                $at,
                                $av);
            $row = $ilDB->fetchAssoc($res);
            if (isset($row))
            {
                return array(
                    "id" => $row["user_id"],
                    "token" => $row["user_token"]
                );
            }
        }
        return null;
    }
}

?>
