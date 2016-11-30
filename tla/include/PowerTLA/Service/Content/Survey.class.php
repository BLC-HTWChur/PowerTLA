<?php
namespace PowerTLA\Service\Content;

use \PowerTLA\Service\BaseService;

class Survey extends BaseService
{
    public static function apiDefinition($apis, $prefix, $link="survey", $name="")
    {
        return parent::apiDefinition($apis, $prefix, $link, "powertla.content.survey");
    }

    /**
     * @protected @function get_analysis()
     *
     * returns the data for analysis of a feedback activity
     *
     */
    protected function get_analysis()
    {
        $courseItemFilterTyp  = array_shift($this->path_info);
        $courseItemFilter = array_shift($this->path_info);

        $fh = $this->VLE->getHandler("Survey", "Content");
        $fh->setAnalyseFilter(array("courseItemFilterTyp"    => $courseItemFilterTyp,
                                      "courseItemFilter"     => $courseItemFilter));
        $fh->analyse();
        if ($fh->analyseResultExists()) {
           
            $this->data = $fh->getAnalyseResult();       
        }
        else {
            $this->not_found();
        }
    }
}
?>