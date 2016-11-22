<?php
namespace PowerTLA;

include_once 'Services/Membership/classes/class.ilParticipants.php';

require_once 'Modules/Course/classes/class.ilObjCourse.php';

class CourseBroker extends \RESTling\Logger
{
    private $iliasVersion;

    public function __construct($system)
    {
        $this->iliasVersion = $system->getVersion();
    }

    /**
     * returns a list of courses for the active user.
     */
    public function getCourseList()
    {
        global $ilUser, $ilObjDataCache;

        $retval = array();

        // got all courses for the user

        $items = ilParticipants::_getMembershipByType($ilUser->getId(), 'crs');

        foreach($items as $obj_id)
        {
            // 1 get course meta data
            $crs = new ilObjCourse($obj_id, false);
            $crs->read();

            $title       = $crs->getTitle();
			$description = $crs->getDescription();

            // $this->log($crs->getOfflineStatus());
            if ($crs->getOfflineStatus() == 1)
            {
                // skip offline courses
                continue;
            }

            $course = array("id" => $obj_id,
                            "title" => $title,
                            "description" => $description);

            // 2 get course objects
            $item_references = ilObject::_getAllReferences($obj_id);
            reset($item_references);

            if (strcmp($this->iliasVersion, "4.2") === 0)
            {
                foreach($item_references as $ref_id => $x ) {
                    // Antique Ilias

                    // For some strange reason remains the $crs->getRefId() remains empty
                    $crs = new ilObjCourse($ref_id);
                    // TODO: Verify that the course is online
                    // TODO: If the course is offline, check if the user is admin.
                    // TODO: skip offline student courses

                    require_once 'Modules/Course/classes/class.ilCourseItems.php';
                    $courseItems = new ilCourseItems($crs->getRefId(), 0, $ilUser->getId());
                    $courseItemList = $courseItems->getAllItems();
                    $course["content-type"] = $this->mapItemTypes($courseItemList, false);
                    break;
                 }
            }
            else
            {
                // Modern Ilias
                foreach($item_references as $ref_id => $x ) {
                    $crs = new ilObjCourse($ref_id);
                    // TODO: Verify that the course is online
                    // TODO: If the course is offline, check if the user is admin.
                    // TODO: skip offline student courses

                    $courseItemList = $crs->getSubItems();

                    // TODO check with Ilias 4.4 and 4.3
//                    $this->log(">>> IL >>> " . json_encode($courseItemList["_all"]));
                    $course["content-type"] = $this->mapItemTypes($courseItemList, true);
                }
            }
            array_push($retval, $course);
        }
        return $retval;
    }

    public function getUserCourse($courseId)
    {
        global $ilUser, $ilObjDataCache;
        $this->log($courseId);

        $items = ilParticipants::_getMembershipByType($ilUser->getId(), 'crs');
        foreach($items as $obj_id)
        {
            $this->log($obj_id . " :: " . $courseId);

            if ($obj_id == $courseId)
            {
                $crs = new ilObjCourse($obj_id, false);
                $crs->read();

                $title       = $crs->getTitle();
    			$description = $crs->getDescription();

                // $this->log($crs->getOfflineStatus());
                if ($crs->getOfflineStatus() == 1)
                {
                    // skip offline courses
                    continue;
                }
                $course = array("id" => $obj_id,
                                "title" => $title,
                                "description" => $description);

                // 2 get course objects
                $item_references = ilObject::_getAllReferences($obj_id);
                reset($item_references);

                if (strcmp($this->iliasVersion, "4.2") === 0)
                {
                    foreach($item_references as $ref_id => $x ) {
                        // Antique Ilias

                        // For some strange reason remains the $crs->getRefId() remains empty
                        $crs = new ilObjCourse($ref_id);
                        // TODO: Verify that the course is online
                        // TODO: If the course is offline, check if the user is admin.
                        // TODO: skip offline student courses

                        require_once 'Modules/Course/classes/class.ilCourseItems.php';
                        $courseItems = new ilCourseItems($crs->getRefId(), 0, $ilUser->getId());
                        $courseItemList = $courseItems->getAllItems();
                        $course["content-type"] = $this->mapItemTypes($courseItemList, false);
                        break;
                     }
                }
                else
                {
                    // Modern Ilias
                    foreach($item_references as $ref_id => $x ) {
                        $crs = new ilObjCourse($ref_id);
                        // TODO: Verify that the course is online
                        // TODO: If the course is offline, check if the user is admin.
                        // TODO: skip offline student courses

                        $courseItemList = $crs->getSubItems();

                        // TODO check with Ilias 4.4 and 4.3
    //                    $this->log(">>> IL >>> " . json_encode($courseItemList["_all"]));
                        $course["content-type"] = $this->mapItemTypes($courseItemList, true);
                    }
                }
                return $course;
            }
        }
        return null;
    }

    /**
     * maps the Ilias object types to content types
     */
    protected function mapItemTypes($itemList, $looptype)
    {
        $ctList = array();
        if ($itemList && count($itemList)) {
            if (looptype) {
                foreach($itemList as $courseItem => $foo)
                {
                    $ctList = $this->mapType($courseItem, $ctList);
                }
            }
            else
            {
                foreach($itemList as $courseItem )
                {
                    $ctList = $this->mapType($courseItem["type"], $ctList);
                }
            }
        }
        return $ctList;
    }

    private function mapType($ilType, $ctList)
    {
        switch ($ilType)
        {
            case "crs":
            case "lm":
                $type = "x-application/imscp"; // IMS Content Package
                break;
            case "sco":
                $type = "x-application/imscp+imsss"; // IMS Content Package
                break;
            case "pg":
            case "page":
            case "chap":
            case "htlm":
                $type = "text/html";
                break;
            case "tst": // tst should be the same as qpl
                $type = "x-application/imsqti-test";
                break;
            case "qpl":
                $type = "x-application/imsqti";
                break;
            case "spl": // generic survey pool
            case "svy": // a survey form
                $type = "x-application/x-form";
                break;
            case "glo":
                $type = "x-application/x-glossary";
                break;
            case "webr":
                $type = "text/url";
                break;
            case "file":
            case "ass":
                // Images or Files
                $type = "x-application/asset";
                break;
            default:
                break;
        }

        if (!empty($type) &&
            !array_search($type, $ctList))
        {
            array_push($ctList, $type);
        }
        return $ctList;
    }
}
?>
