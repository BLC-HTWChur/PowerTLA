<?php
namespace PowerTLA\Moodle\Validator;

/**
 * Validation of moodle sessions
 *
 * During the moodle initialisation
 *
 * Note: Moodle runs PowerTLA as Ajax Services and not as web-services. Therefore no
 * Service token validation is conducted at this point.
 */
class Session extends \RESTling\Validator
{
    protected function validate()
    {
        global $USER;

        if ($USER &&
            $USER->id)
        {
            $this->clientId = $USER->id;
            return TRUE;
        }

        return FALSE;
    }
}

?>
