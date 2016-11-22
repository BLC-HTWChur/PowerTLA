<?php
namespace PowerTLA;

class PluginConfig extends PluginBase
{
    private $servername;
    private $lang;
    public function getEngine($tlapath)
    {
        global $ilClientIniFile;

        $this->servername = $ilClientIniFile->readVariable('client',
                                                     'description');
        $this->lang =       $ilClientIniFile->readVariable('language',
                                                     'default');

        $requrl = $this->buildExternalSystemUrl($tlapath);

        $retval = array(
            "version" => ILIAS_VERSION_NUMERIC,
            "type"=> "ILIAS",
            "link"=> $requrl, // official link
            "servicelink" => $requrl . $tlapath . "/"
        );

        return $retval;
    }

    public function getDisplayName()
    {
        global $ilClientIniFile;

        if(!isset($this->servername))
        {
            $this->servername = $ilClientIniFile->readVariable('client',
                                                               'description');
        }

        return $this->servername;
    }

    public function getDefaultLanguage()
    {
        global $ilClientIniFile;

        if (empty($this->lang))
        {
            $this->lang = $ilClientIniFile->readVariable('language',
                                                         'default');
        }

        return $this->lang;
    }

    /**
     * provides information on on the activation state/maintenance mode
     */
    public function isActive()
    {
        // TODO - find out how to hook a headless plugin

//        global $ilPluginAdmin;
//        if (isset($ilPluginAdmin))
//        {
//            return $ilPluginAdmin->isActive(IL_COMP_SERVICE,
//                                            "PowerTLA",      // name of the plugin slot
//                                            "ptla",          // ILIAS requires 4 character ids
//                                            "PowerTLA");     // name of the plugin
//        }
//
//        return false;
        return true;
    }
}

?>