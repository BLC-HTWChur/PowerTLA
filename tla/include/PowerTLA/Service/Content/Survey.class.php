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
     * @method get_analysis()
     *
     * returns the data for analysis of a feedback activity
     * example: [{"id":"15593","typ":"multichoicerated","label":"Test","question":"","average_value":6,"range_from":"0","range_to":"8"},
     * {"id":"15594","typ":"numeric","label":"labelnum","question":"Numeric","average_value":7,"range_from":"1","range_to":"11"}]
     * 
     */
    protected function get_analysis()
    {
        $courseModuleId  = array_shift($this->path_info);
        $courseId = array_shift($this->path_info);
        
        $fh = $this->VLE->getHandler("Survey", "Content");
        $fh->setFilter(array("courseModuleId"    => $courseModuleId,
                                      "courseId"     => $courseId));
        // analyse the data
        try {
            if (!$fh->checkPermission()) {
                $this->forbidden("no permission");
                return;
            };
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

    /**
     * @method get_results()
     *
     * returns all answers of a feedback activity
     * 
     */
    protected function get_results()
    {
        $courseModuleId  = array_shift($this->path_info);
        $courseId = array_shift($this->path_info);
        
        $fh = $this->VLE->getHandler("Survey", "Content");
        $fh->setFilter(array("courseModuleId"    => $courseModuleId,
                                      "courseId"     => $courseId));
        // gets the data
        try {
            if (!$fh->checkPermission()) {
                $this->forbidden("no permission");
                return;
            };
            $this->data = $fh->getResults();
        }catch (Exception $e) {
            $this->log($e->getMessage());
            $this->not_found();
            return;
        }

        // check if data recieved
        if (!$this->data) {
            $this->not_found();
        }
    }
}
?>