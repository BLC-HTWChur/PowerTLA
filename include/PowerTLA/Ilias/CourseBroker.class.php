<?php

include_once 'Services/Membership/classes/class.ilParticipants.php';

require_once 'Modules/Course/classes/class.ilCourseItems.php';
require_once 'Modules/Course/classes/class.ilObjCourse.php';

class CourseBroker extends Logger
{
    private $iliasVersion;

    public function __construct($iV)
    {
        $this->iliasVersion = $iV;
    }

    public function getCourseList()
    {
        global $ilUser, $ilObjDataCache;

        $retval = array();

        // got all courses for the user

        $items = ilParticipants::_getMembershipByType($ilUser->getId(), 'crs');

        // $items = ilParticipants::_getMembershipByType(12855, 'crs');

        foreach($items as $obj_id)
        {
            // 1 get course meta data
            $title       = $ilObjDataCache->lookupTitle($obj_id);
			$description = $ilObjDataCache->lookupDescription($obj_id);

            $course = array("id" => $obj_id,
                            "title" => $title,
                            "description" => $description);

            // 2 get course objects
            $item_references = ilObject::_getAllReferences($obj_id);
            reset($item_references);

            $this->log($this->iliasVersion);
            if (strcmp($this->iliasVersion, "4.2") === 0)
            {
                foreach($item_references as $ref_id) {
                    // Antique Ilias
                    $courseItems = new ilCourseItems($ref_id);
                    $courseItemList = $courseItems->getAllItems();
                    $course["content-type"] = $this->mapItems($courseItemList);
                }
            }
            else
            {
                // Modern Ilias
                $crs = new ilObjCourse($item_references, true);
                $courseItemList = $crs->getSubItems();
                $course["content-type"] = $this->mapItems($courseItemList["_all"]);
            }

            array_push($retval, $course);
        }
        return $retval;
    }

    protected function mapItems($itemList)
    {
        $ctList = array();
        if ($itemList && count($itemList)) {
            foreach($itemList as $courseItem) {
                // $this->log("Course Item: " . json_encode($courseItem));
                // map the ILIAS types to fake MIME types
                switch ($courseItem["type"])
                {
                    case "crs":
                    case "lm":
                        $type = "x-application/imscp"; // IMS Content Package
                        if (array_search($type, $ctList) === FALSE)
                        {
                            array_push($ctList, $type);
                        }
                        break;
                    case "sco":
                        $type = "x-application/imscp+imsss"; // IMS Content Package
                        if (array_search($type, $ctList) === FALSE)
                        {
                            array_push($ctList, $type);
                        }
                        break;
                    case "pg":
                    case "page":
                    case "chap":
                    case "htlm":
                        $type = "text/html";
                        if (array_search($type, $ctList) === FALSE)
                        {
                            array_push($ctList, $type);
                        }
                        break;
                    case "tst": // tst should be the same as qpl
                        $type = "x-application/imsqti-test";
                        if (array_search($type, $ctList) === FALSE)
                        {
                            array_push($ctList, $type);
                        }
                        break;
                    case "qpl":
                        $type = "x-application/imsqti";
                        if (array_search($type, $ctList) === FALSE)
                        {
                            array_push($ctList, $type);
                        }
                        break;
                    case "spl": // generic survey pool
                    case "svy": // a survey form
                        $type = "x-application/x-form";
                        if (array_search($type, $ctList) === FALSE)
                        {
                            array_push($ctList, $type);
                        }
                        break;
                    case "glo":
                        $type = "x-application/x-glossary";
                        if (array_search($type, $ctList) === FALSE)
                        {
                            array_push($ctList, $type);
                        }
                        break;
                    case "webr":
                        $type = "text/url";
                        if (array_search($type, $ctList) === FALSE)
                        {
                            array_push($ctList, $type);
                        }
                        break;
                    case "file":
                    case "ass":
                        // Images or Files
                        $type = "x-application/assest";
                        if (array_search($type, $ctList) === FALSE)
                        {
                            array_push($ctList, $type);
                        }
                        break;
                    default:
                        break;
                }
            }
        }
        return $ctList;
    }
}
?>