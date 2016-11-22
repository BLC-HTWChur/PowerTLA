<?php
namespace PowerTLA;

/**
 * The LRS Manager Class implements the DB Interface for the VLE
 *
 * LRS Base provides all validatation functions functions
 */
class LRSManager extends LRSBase
{
    private $db;

    static public $types = array(
        "uuid"          => "text",
        "statement_id"  => "text",
        "document"      => "text",
        "doctype"       => "text",
        "statement"     => "text",
        "object_id"     => "text",
        "agent"         => "text",
        "actor_id"      => "text",
        "registration"  => "text",
        "verb_id"       => "text",
        "user_id"       => "integer",
        "stored"        => "integer",
        "duration"      => "integer",
        "score"         => "integer",
        "tsyear"        => "integer",
        "tsmonth"       => "integer",
        "tsday"         => "integer",
        "tshour"        => "integer",
        "tsminute"      => "integer"
    );

    public function __construct()
    {
        global $ilDB;
        $this->db = $ilDB;
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
                $r = $this->db->queryF("SELECT usr_id FROM usr_data ".
                                   "WHERE email = %s",
                                   array("text"),
                                   array($email));

                if ($data = $this->db->fetchAssoc($r))
                {
                    return $data["usr_id"];
                }
            }
        }

        if (array_key_exists("openid", $actor))
        {
            // note if the itoken is not there, then it is
            // not our user and we will reject.

            // TODO we MUST check if the URL belongs to us, too

            $itoken = array_pop(explode("/", $actor["openid"]));
        }

        if (array_key_exists("account", $actor) &&
            array_key_exists("homepage", $actor["account"]) &&
            !empty($actor["account"]["homepage"]))
        {
            $itoken = array_pop(explode("/", $actor["account"]["homepage"]));
        }

        if (isset($itoken))
        {
            $r = $this->db->queryF("SELECT user_id FROM pwrtla_usertokens ".
                               "WHERE user_token= %s",
                               array("text"),
                               array($itoken));

            if ($data = $this->db->fetchAssoc($r))
            {
                return $data["user_id"];
            }
        }

        return -1;
    }

    protected function findStatementByUUID($uuid)
    {
        $this->mark();
        if (!empty($uuid))
        {
            $r = $this->db->queryF("SELECT statement FROM pwrtla_xapistatements WHERE uuid = %s",
                                   array("text"),
                                   array($uuid));

            if ($data = $this->db->fetchAssoc($r))
            {
                return json_decode($data["statement"]);
            }
        }
        return null;
    }

    protected function addStatement($aLRSStatement)
    {
        $this->mark();
        $dbstatement = array();
        foreach ($aLRSStatement as $col => $val)
        {
            if (array_key_exists($col, self::$types))
            {
                $dbstatement[$col] = array(self::$types[$col],
                                           $aLRSStatement[$col]);
            }
        }

        $this->db->insert("pwrtla_xapistatements", $dbstatement);
    }

    protected function updateStatement($aLRSStatement, $aOptions)
    {
        $this->mark();
        $where = $this->buildWhere($aOptions);
        if (isset($aLRSStatement) && !empty($where))
        {
            $dbtypes = array();
            $dbvals  = array();
            $dbset   = array();
            foreach ($aLRSStatement as $col => $val)
            {
                if (array_key_exists($col, self::$types))
                {
                    $dbtypes[] = self::$types[$col];
                    $dbvals[]  = $aLRSStatement[$col];
                    $dbset[]   = $col . " = %s";
                }
            }
            $sql = "UPDATE TABLE pwrtla_xapistatements SET " . implode(", ", $dbset);
            $sql.= " WHERE " . $where;

            $this->db->manipulateF($sql, $dbtypes, $dbvals);
        }
    }

    protected function voidStatement($uuid, $vuuid)
    {
        $this->mark();
        $statement = $this->findStatementByUUID($uuid);
        if (!empty($vuuid) &&
            isset($statement))
        {
            $uuid   = $this->quote($uuid);
            $vuuid  = $this->quote($vuuid);
            $sql = "UPDATE TABLE pwrtla_xapistatements SET voided = %s  WHERE uuid = %s";

            $this->db->manipulateF($sql,
                                   array("text", "text"),
                                   array($vuuid, $uuid));
        }
    }

    protected function deleteStatement($uuid)
    {
        $this->mark();
        $sql = "DELETE FROM pwrtla_xapistatements WHERE uuid = %s";
        $this->db->manipulateF($sql, array("text"), array($uuid));
    }

    protected function readActivityStream($aOptions = [])
    {
        $this->mark();
        $aStream = array();

        $where = $this->buildWhere($aOptions);
        $sql = "SELECT statement FROM pwrtla_xapistatements ";
        if (!empty($where))
        {
            $sql .= "WHERE voided is null AND " . $where;
        }

        $r = $this->db->queryF($sql, array(), array());
        while ($data = $this->db->fetchAssoc($r))
        {
            // fixme: use a callback
            $aStream[] = json_decode($data["statement"]);
        }

        return $aStream;
    }

    protected function readActivityStreamWithCallback($cb, $aOptions = [])
    {
        $where = $this->buildWhere($aOptions);
        $sql = "SELECT statement FROM pwrtla_xapistatements ";
        if (!empty($where))
        {
            $sql .= "WHERE voided is null AND " . $where;
        }

        $r = $this->db->queryF($sql, array(), array());
        while ($data = $this->db->fetchAssoc($r))
        {
            call_user_func($cb, $data["statement"]);
        }
    }

    /**
     * returns the parsed document if it exists for the given uuid.
     *
     * if the UUID does not exist, then the function returns null.
     *
     * Verify whether the a document uuid is in the database
     */
    protected function findDocumentByUUID($uuid)
    {
        if (!empty($uuid))
        {
            $r = $this->db->queryF("SELECT statement FROM pwrtla_xapidocuments WHERE uuid = %s",
                                   array(self::$types["uuid"]),
                                   array($uuid));

            if ($data = $this->db->fetchAssoc($r))
            {
                return json_decode($data["document"]);
            }
        }
        return null;
    }

    /**
     * adds a new document data set to the database.
     */
    protected function addDocument($aDocument, $aOptions)
    {
        $dbstatement = array(
            "document" => array("text", json_encode($aDocument))
        );

        foreach ($aOptions as $col => $val)
        {
            if (array_key_exists($col, self::$types))
            {
                $dbstatement[$col] = array(self::$types[$col],
                                           $aOptions[$col]);
            }
        }
        $this->db->insert("pwrtla_xapidocuments", $dbstatement);
    }

    protected function readDocument($aOptions)
    {
        $aDocs = null;
        $where = $this->buildWhere($aOptions);
        if (!empty($where))
        {
            $aDocs = array();
            $sql = "SELECT document FROM pwrtla_xapidocuments WHERE " . $where;
            $r = $this->db->queryF($sql, array(), array());
            while ($data = $this->db->fetchAssoc($r))
            {
                // fixme: use a callback
                $aDocs[] = json_decode($data["document"]);
            }
        }
        return $aDocs;
    }

    protected function updateDocument($aDocument, $aOptions)
    {
        $dbDoc = $this->quote(json_encode($aDocument));
        $where = $this->buildWhere($aOptions);
        if (!empty($where))
        {
            $sql = "UPDATE TABLE pwrtla_xapidocument SET document = " . $dbDoc . " WHERE ";
            $sql .= $where;
            $this->db->manipulateF($sql , array(), array());
        }
    }

    protected function deleteDocument($aOptions)
    {
        $where = $this->buildWhere($aOptions);
        if (!empty($where))
        {
            $sql = "DELETE FROM pwrtla_xapidocument WHERE " . $where;
            $this->db->manipulateF($sql, array(), array());
        }
    }
}

?>
