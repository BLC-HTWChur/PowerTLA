<?php
namespace PowerTLA\Moodle\Handler\LRS;

use PowerTLA\Handler\LRSBase;

/**
 * The LRS Manager Class implements the DB Interface for the VLE
 *
 * LRS Base provides all validatation functions
 */
class Xapi extends LRSBase
{
    private $db;

    public function __construct()
    {
        global $DB;
        $this->db = $DB;
    }

    /**
     * returns the actor's internal user id
     */
    protected function getActorUserID($actor)
    {
        if (array_key_exists("mbox", $actor))
        {
            $email = array_pop(explode(":", $actor["mbox"]));
            if (!empty($email))
            {
                // get user id from the user table
                if ($user = $this->db->get_record('user',
                                                  array("mail" => $email)))
                {
                    return $user->id;
                }
            }
        }

        if (array_key_exists("openid", $actor))
        {
            // note if the itoken is not there, then it is
            // not our user and we will reject.

            // TODO we MUST check if the URL belongs to us, too
            $aTmp = explode("/", $actor["openid"]);
            $itoken = array_pop($aTmp);
        }


        if (!empty($itoken))
        {
            if ($utoken = $this->db->get_record("pwrtla_usertokens",
                                             array("user_token" => $itoken)))
            {
                return $utoken->user_id;
            }
        }

        if (array_key_exists("account", $actor) &&
            array_key_exists("homepage", $actor["account"]) &&
            !empty($actor["account"]["homepage"]))
        {
            // moodle has an uri attribute
            // $itoken = array_pop(explode("/", $actor["account"]["homepage"]));
            if ($user = $this->db->get_record('user',
                                              array("url" => $actor["account"]["homepage"])))
            {
                return $user->id;
            }
        }

        return -1;
    }

    protected function findStatementByUUID($uuid)
    {
        $statement = null;
        if(!empty($uuid))
        {
            if ($rec = $this->db->get_record("pwrtla_xapistatements",
                                         array("uuid" => $uuid)))
            {
                $statement = json_decode($rec->statement);
            }
        }
        return $statement;
    }
    protected function findDocumentByUUID($uuid)
    {
        $document = null;
        if(!empty($uuid))
        {
            if ($rec = $this->db->get_record("pwrtla_xapidocuments",
                                         array("uuid" => $uuid)))
            {
                $document = json_decode($rec->document);
            }
        }
        return $document;
    }

    protected function readActivityStreamWithCallback($callback, $aOptions) {}

    protected function readActivityStream($aOptions = [])
    {
        if(array_key_exists("agent", $aOptions))
        {
            // because our own stupid logic requires this :(
            unset($aOptions["agent"]);
        }

        // note: moodle sumbles upon question mark
        $aStream = array();
        $where = $this->buildWhere($aOptions);
        if (!empty($where))
        {
            $records = $this->db->get_records("pwrtla_xapistatements",
                                                     $aOptions);
            foreach ($records as $s)
            {
                $aStream[] = json_decode($s->statement);
            }
        }
        return $aStream;
    }

    protected function readDocument($aOptions = [])
    {
        if(array_key_exists("agent", $aOptions))
        {
            // because our own stupid logic requires this :(
            unset($aOptions["agent"]);
        }
        $aStream = array();
        $where = $this->buildWhere($aOptions);
        if (!empty($where))
        {
            $records = $this->db->get_records("pwrtla_xapidocuments",
                                              $aOptions);
            foreach ($records as $s)
            {
                $aStream[] = json_decode($s->document);
            }
        }
        return $aStream;
    }

    protected function readActivtyStreamWithCallback($cb, $aOptions = [])
    {
        if(array_key_exists("agent", $aOptions))
        {
            // because our own stupid logic requires this :(
            unset($aOptions["agent"]);
        }
        $where = $this->buildWhere($aOptions);
        if (!empty($where))
        {
            $records = $this->db->get_records("pwrtla_xapistatements",
                                                     $aOptions);
            foreach ($records as $s)
            {
                call_user_func($cb, $s->statement);
            }
        }
    }

    protected function readDocumentWithCallback($cb, $aOptions = [])
    {
        if(array_key_exists("agent", $aOptions))
        {
            // because our own stupid logic requires this :(
            unset($aOptions["agent"]);
        }
        $where = $this->buildWhere($aOptions);
        if (!empty($where))
        {
            $records = $this->db->get_records("pwrtla_xapidocuments",
                                                     $aOptions);
            foreach ($records as $s)
            {
                call_user_func($cb, $s->document);
            }
        }
    }

    protected function addStatement($aLRSStatement)
    {
        if(array_key_exists("agent", $aLRSStatement))
        {
            // because our own stupid logic requires this :(
            unset($aLRSStatement["agent"]);
        }
        $this->db->insert_record("pwrtla_xapistatements",
                                 $aLRSStatement);
    }

    protected function updateStatement($aLRSStatement, $aOptions)
    {
        if(array_key_exists("agent", $aOptions))
        {
            // because our own stupid logic requires this :(
            unset($aOptions["agent"]);
        }
        $where = $this->buildWhere($aOptions);
        if (isset($aStatement) &&
            !empty($where))
        {
            $strDoc = json_encode($aStatement);
            $sql = "UPDATE TABLE pwrtla_xapidocument SET document = ? WHERE " . $where;
            $this->db->execute($sql, array($strDoc));
        }
    }
    protected function deleteStatement($aOptions)
    {
        if(array_key_exists("agent", $aOptions))
        {
            // because our own stupid logic requires this :(
            unset($aOptions["agent"]);
        }
        if($where = $this->buildWhere($aOptions))
        {
            $this->db->delete_records("pwrtla_xapistatements",
                                      $aOptions);
        }
    }

    protected function addDocument($aDocument, $aOptions)
    {
        $dbstatement = array(
            "document" => json_encode($aDocument)
        );

        foreach ($aOptions as $col => $val)
        {
            $dbstatement[$col] = $aOptions[$col];
        }

        $this->db->insert_record("pwrtla_xapidocuments",
                                 $dbstatement);
    }

    protected function updateDocument($aDocument, $aOptions)
    {
        if(array_key_exists("agent", $aOptions))
        {
            // because our own stupid logic requires this :(
            unset($aOptions["agent"]);
        }
        $where = $this->buildWhere($aOptions);
        if (isset($aDocument) &&
            !empty($where))
        {
            $strDoc = json_encode($aDocument);
            $sql = "UPDATE TABLE pwrtla_xapidocument SET document = ? WHERE " . $where;
            $this->db->execute($sql, array($strDoc));
        }
    }

    protected function deleteDocument($aOptions)
    {
        if(array_key_exists("agent", $aOptions))
        {
            // because our own stupid logic requires this :(
            unset($aOptions["agent"]);
        }
        if($where = $this->buildWhere($aOptions))
        {
            $this->db->delete_records("pwrtla_xapidocuments",
                                             $aOptions);
        }
    }

    protected function voidStatement($uuid, $vuuid)
    {
        $statement = $this->findStatementByUUID($uuid);
        if (!empty($vuuid) &&
            isset($statement))
        {
            $uuid   = $this->quote($uuid);
            $vuuid  = $this->quote($vuuid);
            $sql = "UPDATE TABLE pwrtla_xapistatements SET voided = ? WHERE uuid = ?";

            $this->db->execute($sql, array($vuuid, $uuid));
        }
    }
}
?>