<?php
namespace PowerTLA\Moodle\Handler\Content;
use PowerTLA\Handler\BaseHandler;

class Survey extends BaseHandler
{
    private $analyseCourseModuleId = 0;
    private $analyseCourseId = 0;
    private $analyseResults;

    // sets the parameter for filtering the analysis
    public function setAnalyseFilter($options) {
        $this->setDebugMode(true);
        $optionList = array("courseModuleId", "courseId");
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
        /*
        if (!empty($opt["courseId"]))
        {
        	$this->analyseCourseId = $opt["courseId"];
        }
        */

    }

    // checks if a analyseResult exists
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

    // retrieve the data from moodle
    public function analyse() {
        global $DB;

        $this->analyseResults = null;
        
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

            // only multichoice, multichoicerated and numeric will be analysed
            if (!($item->typ == "multichoice") AND !($item->typ == "multichoicerated") AND !($item->typ == "numeric")) { 
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
            } 

            //get the values
            $values = \feedback_get_group_values($item, $mygroupid, $this->analyseCourseId, $ignoreempty);

            if (!$values) {
                continue;
            }
            $this->log("got values:" . count($values));

            $result = new \stdClass();
            $sum = 0.0;
            $anscount = 0;
            $quotient = 0.0;
            foreach ($values as $value) {
                // multichoicerated
                // achtung Value gibt an welche antwort gewählt wurde (index beginn mit 1) und nicht den Wert den Skalawert
                $this->log("value :" . $value->value);

                if ($item->typ == "multichoice"){
                    //todo
                    null;
                }elseif ($item->typ == "multichoicerated"){
                    $sizeoflines = count($lines);
                    for ($i = 1; $i <= $sizeoflines; $i++) {
                        $item_values = explode(FEEDBACK_MULTICHOICERATED_VALUE_SEP, $lines[$i-1]);
                        if ($value->value == $i) {
                            $sum += $item_values[0];
                            //$anscount++;
                        }
                    }
                }elseif ($item->typ == "numeric"){
                    //todo
                    null;
                }
                
            }
            
            $result->id = $item->id;
            $result->typ = $item->typ;
            $result->label = $item->label;
            $result->question = $item->name;
            if (!empty($quotient)){
              $result->quotient = $quotient;  
            }
            $result->average_value = doubleval($sum) / doubleval(count($values));;

            array_push($this->analyseResults,$result);
        }

    }

    // gets  the results of analyse
    public function getAnalyseResult(){
    	return $this->analyseResults;
    }
}
?>