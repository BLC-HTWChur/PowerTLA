<?php
namespace PowerTLA\Moodle\Handler\Content;

use PowerTLA\Handler\BaseHandler;

class Course extends BaseHandler
{

    public function __construct($system) {
        parent::__construct($system);

        global $CFG;

        include_once($CFG->libdir . '/moodlelib.php');
        include_once($CFG->dirroot . '/course/lib.php');
        include_once($CFG->libdir . '/coursecatlib.php');
    }
    /**
     * get course details
     */
    public function getUserCourse($courseId)
    {
        global $DB;

        // return course details, including a list list of available content types

        return null;
    }

    public function getCourseList()
    {
        $fieldMap = [
            "id" => "course_id",
            "shortname" => "short_name",
            "fullname" => "display_name"
        ];

        $retval = [];

        $sortorder = 'visible DESC, sortorder ASC';

        if ($courses = enrol_get_my_courses(NULL, $sortorder)) {
            foreach ($courses as $k => $course) {
                $this->mark($course->id . " " . $course->visible);
                if ($course->visible < 1) {
                    next;
                }

                $c = [];

                foreach ($fieldMap as $k => $v) {
                    if (property_exists($course, $k)) {
                        $c[$v] = $course->$k;
                    }
                }

                // ignore course files/images
                // FIXME: add references to icons and images.

                $retval[] = $c;
            }
        }

        return $retval;
    }

    public function getPublicCourses()
    {
        // should return all available public courses in the system
        //  public is either with guest access or self enrollment
        return [];
    }
}

?>
