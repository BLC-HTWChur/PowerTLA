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
        $this->setDebugMode(true);
        $courseModuleId  = array_shift($this->path_info);
        $courseId = array_shift($this->path_info);
        
        $fh = $this->VLE->getHandler("Survey", "Content");
        $fh->setAnalyseFilter(array("courseModuleId"    => $courseModuleId,
                                      "courseId"     => $courseId));

        // analyse the data
        try {
            $fh->analyse();
        }catch (Exception $e) {
            $this->log($e->getMessage());
            $this->not_found();
            return;
        }

        // gets and assigns the data
        if ($fh->analyseResultExists()) {
           
            $this->data = $fh->getAnalyseResult();       
        }
        else {
            $this->not_found();
        }
    }
}
?>