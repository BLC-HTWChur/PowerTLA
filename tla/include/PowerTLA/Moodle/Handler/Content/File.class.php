<?php
namespace PowerTLA\Moodle\Handler\Content;
use PowerTLA\Handler\BaseHandler;

class File extends BaseHandler
{
    private $file;
    private $fileref;
    private $ownercontext;

    public function setFile($options) {
        $optionList = array("owner", "filename", "path");
        $opt = array();

        if (!empty($options)) {
            foreach ($options as $k => $v) {
                $opt[$k] = $v;
            }
        }

        // get owner context
        if (!empty($opt["owner"]))
        {
        	$this->ownercontext = \context_user::instance($opt["owner"]);
        }

        // add path slashes
        if (!empty($opt["owner"]))
        {
        	$opt['path'] = '/' . $opt['path'] . '/';
        }

        $this->file = $opt;
    }

    public function checkPermission($user) {
        return true; // return false if the user must not access the object.
    }

    public function exists() {
    	$this->fileref = $this->getFile();
		if ($this->fileref)
		{
			return true;
		}
		else
		{
			return false;
		}
    }

    public function streamFileContent() {
    	if (!$this->fileref)
    	{
    		$this->fileref = $this->getFile();
    	}
		\send_stored_file($this->fileref);
    }

    private function getFile(){
    	$fs = \get_file_storage();

    	return $fs->get_file($this->ownercontext->id, 'user', 'private', 0, $this->file['path'], $this->file['filename']);
    }
}

?>