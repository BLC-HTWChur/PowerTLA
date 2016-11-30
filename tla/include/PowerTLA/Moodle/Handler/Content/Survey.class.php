<?php
namespace PowerTLA\Moodle\Handler\Content;
use PowerTLA\Handler\BaseHandler;

class Survey extends BaseHandler
{
    private $courseItemFilterTyp;
    private $courseItemFilter;
    private $analyseResults;

    // sets the parameter for filtering the analysis
    public function setAnalyseFilter($options) {
        $optionList = array("courseItemFilterTyp", "courseItemFilter");
        $opt = array();

        if (!empty($options)) {
            foreach ($options as $k => $v) {
                $opt[$k] = $v;
            }
        }

        if (!empty($opt["courseItemFilterTyp"]))
        {
        	$this->courseItemFilterTyp = $opt["courseItemFilterTyp"];
        }

        if (!empty($opt["courseItemFilter"]))
        {
        	$this->courseItemFilter = $opt["courseItemFilter"];
        }

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
        
        if ($this->courseItemFilter > 0) 
        {
            $sumvalue = 'SUM(' . $DB->sql_cast_char2real('value', true) . ')';
            $sql = "SELECT fv.course_id, c.shortname, $sumvalue AS sumvalue, COUNT(value) as countvalue
                    FROM {feedback_value} fv, {course} c, {feedback_item} fi
                    WHERE fv.course_id = c.id AND fi.id = fv.item AND fi.typ = ? AND fv.item = ?
                    GROUP BY course_id, shortname
                    ORDER BY sumvalue desc";

            $this->analyseResults = $DB->get_records_sql($sql, array($this->courseItemFilterTyp, $this->courseItemFilter));
        }
        else{
            $this->analyseresults = null;
        }
    }

    // gets  the results of analyse
    public function getAnalyseResult(){
    	return $this->analyseResults;
    }
}
?>