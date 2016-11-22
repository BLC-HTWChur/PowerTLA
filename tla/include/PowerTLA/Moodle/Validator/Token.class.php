<?php

namespace PowerTLA\Moodle\Validator;

/**
 * Validation of moodle tokens
 *
 * Note: Moodle runs PowerTLA as Ajax Services and not as web-services. Therefore we
 * will not receive any user information at this point, but have to build it from the token.
 */
class Token extends \RESTling\Validator
{
    private $token;

    protected function validate()
    {
        global $DB;
        global $USER;

        // in Moodle tokens are normally passed as part of the payload
        // PowerTLA however requires the token to be in the Authorization header

        // NOTE: we use multiple validators, which all can grant service access, individually.
        // Therefore, the validator MUST NOT yield any responst codes

        if (!($this->validate_header() ||
              $this->validate_input())) {
            // no token found, respond 401
            //$this->service->authentication_required();
            return false;
        }

        $recToken = (object)$DB->get_record('external_tokens',
                                             array('token' => $this->token));

        if (empty($recToken)) {
            // token unknown; respond 403
            // $this->service->forbidden();
            return false;
        }

        if ($recToken->validuntil > 0 &&
            $recToken->validuntil < time()) {
            // token has expired
            // $this->service->authentication_required();
            return false;
        }

        // if we reached this point we assume silently that the users have the
        // rights to use rest services
        $user = (object)$DB->get_record('user',
                                        array('id' => $recToken->userid));

        if (!($user &&
              $user->id))
        {
            // oops, user does not exist.
            // $this->service->forbidden();
            return false;
        }

        $USER = $user;

        global $CFG;

        $this->clientId = $USER->id;
        // update last access
        $recToken->lastaccess = time();
        $DB->set_field('external_tokens',
                       'lastaccess',
                       $recToken->lastaccess,
                       ['id' => $recToken->id]);

        return true;
    }

    private function validate_header()
    {
        $headers = getallheaders();

        if (empty($headers)) {
            // should never happen
            return false;
        }

        if (!array_key_exists("Authorization", $headers) &&
            !empty($headers["Authorization"]))
        {
            return false;
        }

        $aHeadElems = explode(' ',  $headers["Authorization"]);

        if ($aHeadElems[0] != "Bearer") {
            return false;
        }

        $this->token  = $aHeadElems[1];

        return true;
    }

    private function validate_input()
    {
        // wstoken
        if (empty($this->data)) {
            return false;
        }

        if (!array_key_exists("wstoken", $this->data)) {
            return false;
        }

        if (empty($this->data["wstoken"])) {
            return false;
        }

        $this->token = $this->data["wstoken"];
        return true;
    }
}

?>