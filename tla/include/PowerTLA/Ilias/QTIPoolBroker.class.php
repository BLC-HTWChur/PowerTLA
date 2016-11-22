<?php

namespace PowerTLA;

include_once 'Services/Membership/classes/class.ilParticipants.php';

require_once 'Modules/Course/classes/class.ilObjCourse.php';
require_once 'Modules/TestQuestionPool/classes/class.ilObjQuestionPool.php';
require_once 'Modules/TestQuestionPool/classes/class.assQuestion.php';


class QTIPoolBroker extends \RESTling\Logger
{
    private $iliasVersion;

    public function __construct($system)
    {
        $this->iliasVersion = $system->getVersion();
    }

    /**
     * returns the list of question pools
     */
    public function getPoolList($CourseID)
    {
        global $ilUser, $ilObjDataCache;

        $retval = array();
        $items = ilParticipants::_getMembershipByType($ilUser->getId(), 'crs');
        $itemId = array_search($CourseID, $items);

        if ($itemId !== FALSE && $itemId >= 0)
        {
            $obj_id = $items[$itemId];

            $item_references = ilObject::_getAllReferences($obj_id);
            reset($item_references);

            if (strcmp($this->iliasVersion, "4.2") === 0)
            {
                foreach($item_references as $ref_id => $x)
                {
                    // Antique Ilias
                    require_once 'Modules/Course/classes/class.ilCourseItems.php';

                    $courseItems = new ilCourseItems($ref_id,
                                                     0,
                                                     $ilUser->getId());
                    $courseItemList = $courseItems->getAllItems();
                    $retval = $this->mapItems($courseItemList);
                }
            }
            else
            {
                foreach($item_references as $ref_id => $x ) {
                    $crs = new ilObjCourse($ref_id);
                    $courseItemList = $crs->getSubItems();

                    // TODO check with Ilias 4.4 and 4.3
                    // $this->log(">>> IL >>> " . json_encode($courseItemList));
                    $retval = $this->mapItems($courseItemList["_all"]);
                }
                // Modern Ilias
//                $crs = new ilObjCourse($item_references);
//                $courseItems = new ilCourseItems($crs->getRefId(), 0, $ilUser->getId());
//                $courseItemList = $courseItems->getAllItems();
//
//                $retval  = $this->mapItems($courseItemList);

//                $courseItemList = $crs->getSubItems();
//                $retval = $this->mapItems($courseItemList["_all"]);
            }
        }

        return $retval;
    }

    public function getSinglePool($CourseID, $PoolID)
    {
        global $ilUser, $ilObjDataCache;

        $retval = array();

        $items = ilParticipants::_getMembershipByType($ilUser->getId(), 'crs');

        // $items = ilParticipants::_getMembershipByType(12855, 'crs');

        $itemId = array_search($CourseID, $items);
        if ($itemId !== FALSE && $itemId >= 0)
        {
            $obj_id = $items[$itemId];
            $item_references = ilObject::_getAllReferences($obj_id);
            reset($item_references);

            if (strcmp($this->iliasVersion, "4.2") === 0)
            {
                require_once 'Modules/Course/classes/class.ilCourseItems.php';

                foreach($item_references as $ref_id)
                {
                    // Antique Ilias
                    $courseItems = new ilCourseItems($ref_id,
                                                     0,
                                                     $ilUser->getId());
                    $courseItemList = $courseItems->getAllItems();

                    $retval = $this->mapItems($courseItemList, $PoolID);
                    $retval = $retval[0];
                }
            }
            else
            {
                // Modern Ilias
                $crs = new ilObjCourse($item_references, true);
                $courseItemList = $crs->getSubItems();
                $retval = $this->mapItems($courseItemList["_all"], $PoolID);
                $retval = $retval[0];
            }
        }

        return $retval;
    }

    private function mapItems($itemList, $poolid=null)
    {
        $ctList = array();
        if ($itemList && count($itemList)) {
            foreach($itemList as $courseItem) {
                // $this->log("Course Item: " . json_encode($courseItem));
                // map the ILIAS types to fake MIME types
                if (isset($poolid) &&
                    intval($poolid) != intval($courseItem["ref_id"]))
                {
                    // in this case only 1 question pool is requested
                    $this->log("skip");
                    continue;
                }

                switch ($courseItem["type"])
                {
                    case "tst": // tst should be the same as qpl
                        break;
                    case "qpl":
                        $qpList = $this->loadQPList($courseItem);

                        if (!empty($qpList))
                        {
                            array_push($ctList, $qpList);
                        }
                        break;
                    default:
                        break;
                }
            }
        }
        return $ctList;
    }

    private function loadQPList($qpItem)
    {
        $retval = array();
        $curT = intval(time());
        // $this->log(json_encode($qpItem));

        // check predefined timings
        if (intval($qpItem["timing_type"]) == 0)
        {
            // ensure that the correct timings are used
            if ((array_key_exists("start", $qpItem) &&
                 ($curT < intval($qpItem["start"]) ||
                  $curT > intval($qpItem["end"]))) ||
                (array_key_exists("timing_start", $qpItem) &&
                 ($curT < intval($qpItem["timing_start"]) ||
                  $curT > intval($qpItem["timing_end"]))))
            {
                $this->log('bad timig, skip question pool');
                return $retval;
            }
        }

        $questionPool = new ilObjQuestionPool($qpItem["ref_id"]);
        $questionPool->read();

        // check online state
        if ($questionPool->getOnline() == 0)
        {
            $this->log('offline, skip question pool');
            return $retval;
        }


        $questions = $questionPool->getQuestionList();

        if (empty($questions))
        {
            // ignore empty question pools
            return $retval;
        }

        // load the actual questions.

        if ($this->iliasVersion != "4.2")
        {
            $retval["questions"] = $this->getQuestionListNew($questions);
        }
        else
        {
            $retval["questions"] = $this->getQuestionListOld($questions);
        }

        $retval["id"]          = $qpItem["ref_id"]; // pass only the reference id
        $retval["title"]       = $questionPool->getTitle();
        $retval["description"] = $questionPool->getDescription();

        if (intval($qpItem["timing_type"]) == 0)
        {
            // pass the end-date on, so the remote broker can switch
            // the pool off if necessary.
            if (array_key_exists("end", $qpItem))
            {
                $retval["end-date"] = intval($qpItem["end"]);
            }
            else if (array_key_exists("timing_end", $qpItem))
            {
                $retval["end-date"] = intval($qpItem["timing_end"]);
            }
        }

        return $retval;
    }

    private function getQuestionListNew($questionList)
    {
        $questions = array();

        foreach ($questionList as $question)
        {
            //get the question type
            $assQuestion = assQuestion::_instanciateQuestion($question["question_id"]);

            array_push($questions, $this->calculateQuestion($question, $assQuestion));
        }
        return $questions;
    }

    private function getQuestionListOld($questionList)
    {
        $questions = array();

        foreach ($questionList as $question)
        {
            //get the question type
            $type = $question["type_tag"];

            // $this->log(">> ". $type);
            require_once 'Modules/TestQuestionPool/classes/class.' . $type . '.php';

            $assQuestion = new $type();
            if ($assQuestion)
            {
                $assQuestion->loadFromDb($question["question_id"]);

                //add question into the question list
                array_push($questions, $this->calculateQuestion($question, $assQuestion));
            }
            else
            {
                $this->log("fail to load type " . $type);
            }
        }
        return $questions;
    }

    private function calculateQuestion($question, $assQuestion)
    {
        $questionText = $question["question_text"];

        if (strcmp($type, "assClozeTest") == 0) {
            $questionText = $question["description"];
            // $this->log("questionText for cloze questions".$questionText);
        }

        //get answers
        $type       = $question["type_tag"];

        // $this->log($type);
        switch ($type)
        {
            case "assNumeric":
                $answerList = $this->calculateNumericAnswer($assQuestion);
                break;
            case "assOrderingHorizontal":
                $answerList = $this->calculateOrderingHorizontalAnswer($assQuestion);
                break;
            case"assClozeTest":
                $answerList = $this->calculateClozeAnswer($assQuestion);
                break;
            default:
                $answerList = $this->calculateAnswerOtherTypes($assQuestion);
                break;
        }

        //get feedback
        if ($this->iliasVersion != "4.2")
        {
            $feedbackCorrect = $assQuestion->feedbackOBJ->getGenericFeedbackContent($assQuestion->getId(), true);
            $feedbackError  = $assQuestion->feedbackOBJ->getGenericFeedbackContent($assQuestion->getId(), false);;
        }
        else
        {
            $feedbackCorrect = $assQuestion->getFeedbackGeneric(1);
            $feedbackError  = $assQuestion->getFeedbackGeneric(0);
        }
        $questionId = $question["question_id"];

        return array(
            "id" => $questionId,
            "title" => $question["title"],
            "type" => $type,
            "question" => $questionText,
            "answer" => $answerList,
            "correctFeedback" => $feedbackCorrect,
            "errorFeedback" => $feedbackError);
    }

    /**
      *gets the answer list for numeric answering questions
     * for each question
     * @function calculateNumericAnswer
     * @return {array} $answerlist
     */
    private function calculateNumericAnswer($assQuestion)
    {
        //only lower and upper limit are returned
        $answerList = array($assQuestion->getLowerLimit(), $assQuestion->getUpperLimit());
        // $this->log("answerList for Numeric Question".json_encode($answerList));
        return $answerList;
    }

    /**
     * gets the answer list for horizontal answering questions
     * @function calculateOrderingHorizontalAnswer
     * @return {array} $answerlist
     */
    private function calculateOrderingHorizontalAnswer($assQuestion)
    {
        //horizontal ordering questions have no "getAnswers()" method!
        //they use the OrderText variable to store the answers and the getOrderText function to retrieve them
        $answers = $assQuestion->getOrderingElements();
        $points = $assQuestion->getPoints();

        $arr = array();
        foreach ($answers as $order => $answer)
            //foreach ($answers as $order => $answer)
        {
            array_push($arr, array(
            "answertext" => (string) $answer,
            "points"=> $points,
            "order" => (int)$order+1,
            "id" => "-1"));
        }
        $answerList = $arr;
        // $this->log("answerList for Horizontal Question".json_encode($answerList));
        return $answerList;
    }

    /**
     * gets the answer list for cloze questions
     * @function calculateClozeAnswer
     * @return {array} $answerlist
     */
    private function calculateClozeAnswer($assQuestion)
    {

        $gaps = $assQuestion->getGaps();

        $this->log(json_encode($gaps));

        $oGaps = array();

        foreach ($gaps as $gap)
        {
            $thegap = new \stdClass();
            $thegap->type = $gap->type;

            $tItems = array();
            foreach ($gap->items as $item)
            {
                $tItems[] = array("text" => $item->getAnswertext(),
                                  "points"=> $item->getPoints());
            }

            $thegap->items = $tItems;

            $oGaps[] = $thegap;
        }

        $clozeText = $assQuestion->getClozeText();
        //$this->log("cloze text for answer view in cloze question is ".$clozeText);
        // $pattern="/\[gap\].*?\[\/gap\]/";
//        for($gapid =0; $gapid<= count($gaps); $gapid++ ){
//            $replacement="<gap identifier=\"gap_".$gapid."\"></gap>";
//            $clozeText = preg_replace($pattern,$replacement,$clozeText,1);
//        }

        // the clozeText will be displayed in answer view
        // we need also the gaps for the calculation of the score
        $answerList = array(
                "clozeText"  => $clozeText,
                "correctGaps" => $oGaps
        );
        //$this->log("answerList for close questions".json_encode($answerList));
        return $answerList;
    }

    /**
     * gets the answer list for single choice, multiple choice and vertical horizontal questions
     * for each question
     * @function calculateClozeAnswer
     * @return {array} $answerlist
     */
    private function calculateAnswerOtherTypes($assQuestion)
    {
        $answers = $assQuestion->toJSON();

        // $this->log("answerList for other types of Question".$answers);

        $answerList = json_decode($answers);
        return $answerList->answers;
    }
}
?>
