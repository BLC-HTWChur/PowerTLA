<?php
namespace PowerTLA\Service\Content;

use \PowerTLA\Service\BaseService;

class File extends BaseService
{
    public static function apiDefinition($apis, $prefix, $link="file", $name="")
    {
        return parent::apiDefinition($apis, $prefix, $link, "powertla.content.fileaccess");
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
    protected function get_user()
    {
        $ownerid  = array_shift($this->path_info);
        $filename = array_pop($this->path_info);
        $path     = implode("/", $this->path_info);

        $fh = $this->VLE->getHandler("File", "Content");
        $fh->setFile(array("owner"    => $ownerid,
                           "filename" => $filename,
                           "path"     => $path));

        if (!$fh->exists()) {
            $this->not_found();
        }
        else {
            // figure out content type
            $this->streamData();
        }
    }

    protected function stream() {
        // perform data streaming

        $this->VLE->getHandler("File", "Content")->streamFileContent();
    }


}
?>