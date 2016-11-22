<?php
namespace PowerTLA\Service\Content;

use \PowerTLA\Service\BaseService;

class Course extends BaseService
{
    public static function apiDefinition($apis, $prefix, $link="course", $name="")
    {
        return parent::apiDefinition($apis, $prefix, $link, "powertla.content.courselist");
    }

    /**
     * @protected @function get()
     *
     * returns the courselist of the present user.
     *
     * if no user is authenticated (!VLESession->active()) then the function
     * will return a dummy course for the public data. Each public data will get
     * wrapped into a dummy course with a single item.
     *
     * if the guest user is enrolled into a course then the service will return these
     * courses, too.
     */
    protected function get()
    {
        $this->data = array();

        $cbH = $this->VLE->getCourseBroker();
        if ($cbH)
        {
            // check for course id
            if (!empty($this->path_info)) {
                $cid = $this->path_info[0];
            }
            if (!empty($cid))
            {
                $this->log("load course id " . $cid);
                $this->data = $cbH->getUserCourse($cid);
                $this->log("one user course (with id = $cid)");
            }
            else
            {
                $this->log("user course list");
                $this->data = $cbH->getCourseList();
            }
        }
    }

    protected function get_system()
    {
        // dummy stub for system courses
        $this->data = array();
    }
}
?>
