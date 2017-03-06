<?php
namespace PowerTLA\Moodle\Handler\Content;
use PowerTLA\Handler\BaseHandler;

class Survey extends BaseHandler
{
    private $analyseCourseModuleId = 0;
    private $analyseCourseId = 0;
    private $analyseResults;


    /**
     * @public @function setAnalyseFilter()
     *
     * sets the parameter for filtering the analysis
     *
     *  analyseCourseModuleId: the moodle course module
     */
    public function setFilter($options) {
        $this->setDebugMode(false);
        $this->analyseCourseModuleId=0;
        $this->analyseCourseId=0;        
        //$optionList = array("courseModuleId", "courseId");
        $opt = array();

        if (!empty($options)) {
            foreach ($options as $k => $v) {
                $opt[$k] = $v;
            }
        }

        if (!empty($opt["courseModuleId"]))
        {
        	$this->analyseCourseModuleId = $opt["courseModuleId"];
        }
        
        if (!empty($opt["courseId"]))
        {
        	$this->analyseCourseId = $opt["courseId"];
        }      
    }


    /**
     * @method analyseResultExists()
     *
     * checks if an analyseResult exists
     *
     * @return bool
     */
    public function analyseResultExists() {
		if (!empty($this->analyseResults))
		{
			return true;
		}
		else
		{
			return false;
		}
    }

    /**
     * @method checkPermission()
     *
     * checks if the logged in user has the permission to view analysis data
     *
     * @return bool
     */
    public function checkPermission() {
        global $DB;

        
        list($course, $cm) = \get_course_and_cm_from_cmid($this->analyseCourseModuleId, 'feedback');
        $feedback = $DB->get_record('feedback', array('id' => $cm->instance));
        $feedbackstructure = new \mod_feedback_structure($feedback, $cm, $this->analyseCourseId);
        
        // Checks permission
        return $feedbackstructure->can_view_analysis();

    }

    /**
     * @method analyse()
     *
     * retrieve the data from moodle
     * sets the data into $this->analyseResults
     * 
     */
    public function analyse() {
        global $DB;

        $this->analyseResults = null;

        // return if the user has no permission
        if (!$this->checkPermission()){
            return;
        };
        $this->log("analyseCourseModuleId: " . $this->analyseCourseModuleId);
        $this->log("analyseCourseId: " . $this->analyseCourseId);
        list($course, $cm) = \get_course_and_cm_from_cmid($this->analyseCourseModuleId, 'feedback');
        $this->log("get feedbackdata with id: " . $cm->instance);
        $feedback = $DB->get_record('feedback', array('id' => $cm->instance));
        $this->log("got feedbackdata with id: " . $feedback->id);

        // set the value for mygroupid; unset for course analyse
        if (empty($this->analyseCourseId)){
            $mygroupid = \groups_get_activity_group($cm, true);
        }
        else{
            $mygroupid = false;
        }
        $this->log("mygroupid: " . $mygroupid);

        $feedbackstructure = new \mod_feedback_structure($feedback, $cm, $this->analyseCourseId);

        // Get the items of the feedback.
        $items = $feedbackstructure->get_items(true);
        $this->log("got items :" . count($items));

        foreach ($items as $item){

            $ignoreempty = false;
            $rangefrom = null;
            $rangeto = null;
            $result = new \stdClass();
            $sum = 0.0;
            $anscount = 0;
            $quotient = 0.0;
            $reallines = [];

            // only multichoicerated and numeric will be analysed
            if (!($item->typ == "multichoicerated") AND !($item->typ == "numeric")) {
                continue;
            }else{
                if ($this->analyseResults == null){
                    $this->analyseResults = array();
                }
            }

            $itemobj = \feedback_get_item_class($item->typ);

            // sets the value for ignoreempty
            if ($item->typ == "multichoice")
            {
                $ignoreempty = (strstr($item->options, FEEDBACK_MULTICHOICE_IGNOREEMPTY));
            }
            elseif ($item->typ == "multichoicerated"){
                $ignoreempty = (strstr($item->options, FEEDBACK_MULTICHOICERATED_IGNOREEMPTY));
                //extract the answers
                $info = $itemobj->get_info($item);
                $this->log("got info");
                $lines = null;
                $lines = explode (FEEDBACK_MULTICHOICERATED_LINE_SEP, $info->presentation);
                if (!is_array($lines)) {
                    continue;
                }
                $sizeoflines = count($lines);
                for ($i = 1; $i <= $sizeoflines; $i++) {
                    $item_values = explode(FEEDBACK_MULTICHOICERATED_VALUE_SEP, $lines[$i-1]);
                    $rangefrom = $rangefrom == null ? $item_values[0] : ($rangefrom > $item_values[0] ? $item_values[0] : $rangefrom );
                    $rangeto = $rangeto == null ? $item_values[0] : ($rangeto < $item_values[0] ? $item_values[0] : $rangeto );
                    $reallines[] = $item_values;
                }
            }
            elseif($item->typ == "numeric"){
                $ignoreempty = false;
                list($rangefrom, $rangeto) = explode('|', $item->presentation);
            }

            //get the values
            $values = \feedback_get_group_values($item, $mygroupid, $this->analyseCourseId, $ignoreempty);

            if (!$values) {
                continue;
            }
            $this->log("got values:" . count($values));

            foreach ($values as $value) {
                // multichoicerated
                // value indicates the index of the answer and not the value of the scale
                $this->log("value :" . $value->value);

                if ($item->typ == "numeric"){
                    $sum += $value->value;
                    $anscount++;
                }elseif ($item->typ == "multichoicerated"){
                    
                    if ($value->value > 0) {
                        $anscount++;
                        $sum += $reallines[$value->value - 1][0];
                    }

                }elseif ($item->typ == "multichoice"){
                    //todo
                    null;
                }
                
            }
            
            $result->id = $item->id;
            $result->typ = $item->typ;
            $result->label = $item->label;
            $result->question = $item->name;
            $result->average_value = $anscount > 0 ? doubleval($sum) /  doubleval($anscount) : null;
            if (isset($rangefrom)){
                $result->range_from = $rangefrom;
            }
            if (isset($rangeto)){
                $result->range_to = $rangeto;
            }

            array_push($this->analyseResults,$result);
        }

    }


    /**
     * @method analyse()
     *
     * returns the data of analysis
     *
     * @return array()  the results of analyse
     * 
     */
    public function getAnalyseResult(){
    	return $this->analyseResults;
    }

  /**
     * @method getResults()
     *
     * retrieve all feedback answers from moodle
     * 
     */
    public function getResults() {
        global $DB;
        global $CFG;

        // include lib to get the constant values for
        require_once($CFG->dirroot.'/mod/feedback/item/multichoicerated/lib.php');
        require_once($CFG->dirroot.'/mod/feedback/item/multichoice/lib.php');
        
        $this->data = [];
        // return if the user has no permission
        if (!$this->checkPermission()){
            return;
        };
        $this->log("analyseCourseModuleId: " . $this->analyseCourseModuleId);
        $this->log("analyseCourseId: " . $this->analyseCourseId);
        list($course, $cm) = \get_course_and_cm_from_cmid($this->analyseCourseModuleId, 'feedback');
        $this->log("get feedbackdata with id: " . $cm->instance);
        $feedback = $DB->get_record('feedback', array('id' => $cm->instance));
        $this->log("got feedbackdata with id: " . $feedback->id);

        // set the value for mygroupid; unset for course analyse
        if (empty($this->analyseCourseId)){
            $mygroupid = \groups_get_activity_group($cm, true);
        }
        else{
            $mygroupid = false;
        }
        $this->log("mygroupid: " . $mygroupid);

        $feedbackstructure = new \mod_feedback_structure($feedback, $cm, $this->analyseCourseId);

        // Get the items of the feedback.
        $items = $feedbackstructure->get_items(true);
        $this->log("got items :" . count($items));

        foreach ($items as $item){

            $ignoreempty = false;

            $rangefrom = null;
            $rangeto = null;
            $result = new \stdClass();
            $sum = 0.0;
            $anscount = 0;
            $quotient = 0.0;

            $reallines = [];
            $answers = [];

            $itemobj = \feedback_get_item_class($item->typ);
            
            if (!empty($item->options)) { // handle options if any
                // set the correct ignore empty value
                $ignoreList = [
                    FEEDBACK_MULTICHOICE_IGNOREEMPTY,
                    FEEDBACK_MULTICHOICERATED_IGNOREEMPTY
                ];

                foreach ($ignoreList as $flag) {
                    if (strstr($item->options, $flag)) {
                        $ignoreempty = true;
                        break;
                    }
                }
            }

            // sets the value for ignoreempty
            if ($item->typ == "multichoicerated"){
                //extract the answers
                $info = $itemobj->get_info($item);
                $this->log("got info ".json_encode($info));
                $lines = null;
                $lines = explode (FEEDBACK_MULTICHOICERATED_LINE_SEP, $info->presentation);
                
                if (!is_array($lines)) {
                    continue;
                }
                $sizeoflines = count($lines);
                $reallines = [];

                for ($i = 0; $i < $sizeoflines; $i++) {
                    $item_values = explode(FEEDBACK_MULTICHOICERATED_VALUE_SEP, $lines[$i]);
                    $rangefrom = $rangefrom == null ? $item_values[0] : ($rangefrom > $item_values[0] ? $item_values[0] : $rangefrom );
                    $rangeto = $rangeto == null ? $item_values[0] : ($rangeto < $item_values[0] ? $item_values[0] : $rangeto );
                    $reallines[] = $item_values;
                }
            }
            elseif($item->typ == "numeric"){
                list($rangefrom, $rangeto) = explode('|', $item->presentation);
            }
            elseif($item->typ == "multichoice"){
                //extract the answers
                $info = $itemobj->get_info($item);
                $this->log("got info ".json_encode($info));
                $lines = null;
                $lines = explode (FEEDBACK_MULTICHOICE_LINE_SEP, $info->presentation);
                
                if (!is_array($lines)) {
                    continue;
                }
                $sizeoflines = count($lines);
                $reallines = [];

                for ($i = 0; $i < $sizeoflines; $i++) {
                    $reallines[] = [$i+1, trim($lines[$i])];
                }
            }

            //get the result values
            $values = \feedback_get_group_values($item, $mygroupid, $this->analyseCourseId, $ignoreempty);

            if (!$values) {
                continue;
            }
            $this->log("got values:" . count($values));

            foreach ($values as $value) {                
                $this->log("value :" . $value->value);
                switch ($item->typ) {
                    case "multichoicerated":
                         // value indicates the index of the answer and not the value of the scale
                         $answers[] = $reallines[$value->value - 1][0];
                         break;

                    case "multichoice":
                         // split values of multipe answers into an array
                         if ($info->subtype = 'c'){
                            $answers[] = explode (FEEDBACK_MULTICHOICE_LINE_SEP, $value->value);
                         }else{
                            $answers[] = $value->value;
                         }
                         break;
                         
                    default:
                        $answers[] = $value->value;
                        break;
                }                 
            }
            
            $result->id = $item->id;
            $result->typ = $item->typ;
            $result->label = $item->label;
            $result->question = $item->name;
            $result->answerValues = $reallines;
            $result->answers = $answers;
            $result->range_from = $rangefrom;
            $result->range_to = $rangeto;

            $this->data[] = $result;
        }
        return $this->data;
    }

}
?>