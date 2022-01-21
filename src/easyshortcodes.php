<?php
/**
 * @version 	0.0.1
 * @author 		Valentín García https://htmgarcia.com
 * @copyright 	Copyright (C) 2022 Valentín García
 * @license 	http://www.gnu.org/licenses/gpl-2.0.html
 */

// no direct access
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;

class PlgSystemEasyshortcodes extends CMSPlugin {

	var $_library;

	function __construct(& $subject, $config = array()) {
		parent :: __construct($subject, $config);
    }

	function onAfterRoute() {
		$this->_library = $this->_readIniFile();
	}

    // Do BBCode replacements on the whole page
	function onAfterRender() {

		$app		= Factory::getApplication();
		$document 	= Factory::getDocument();
		$doctype 	= $document->getType();
		if ($doctype == 'html') {
			$body = $app->getBody();
			if ($this->_replaceCode($body)) {
				$app->setBody($body);
			}
		}
	}

	//process on content items first
	function onContentPrepare($context, &$article, &$params, $page = 0)
	{

		if ($this->_replaceCode($article->text)) {
		    return $article->text;
		}

    }

	function _replaceCode(&$body) {

        if(empty($this->_library)) return true;

	    foreach ($this->_library as $key => $val) {

	    	$script_tag_matches = array();
	    	$search         = array();
			$replace        = array();
			$tokens         = array();

	    	// create a working body
	        $working_body = $body;

	        // remove the script tag contents from the working body
			$find_scipt_tag = '#(<script.*type="text/javascript"[^>]*>(?!<script)(.*)</script>)#iUs';
			preg_match_all  (  $find_scipt_tag  ,  $working_body  ,  $script_tag_matches);
			foreach($script_tag_matches[2] as $scripttagbody) {
				if(!empty($scripttagbody)){
					$working_body = str_replace($scripttagbody,'',$working_body);
				}
			}


        	// build the regexp for the tag
			$opentag = substr($key,0,strpos($key,']')+1);
			$partial_open_tag = substr($opentag,0,(strpos($opentag,' '))?strpos($opentag,' '):strpos($opentag,']'));
			$tokened_opentag =  preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>.*?)',$opentag);
            if (strpos($opentag,"/]")){
                $escaped_key = $this->_addEscapes($tokened_opentag);
            }
            else {
                $tag_contents = substr($key, strpos($key,']')+1, strrpos($key,'[') - (strpos($key,']')+1));
			    $tokened_tag_contens = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>(?s:(?!'.$partial_open_tag.').)*?)',$tag_contents);
			    $closetag = substr($key,strrpos($key,'['),strrpos($key,']')-strrpos($key,'[')+1);
			    $escaped_key = $this->_addEscapes($tokened_opentag.$tokened_tag_contens.$closetag);
            }
			$final_tag_patern = "%".$escaped_key."%";

	        // run the matching for the tag on the working body
	        //var_dump ($final_tag_patern);
	        preg_match_all($final_tag_patern, $working_body, $results);
	        if (!empty($results[0])) {
	            //if var_dump ($results);
    	        $search = array_merge($search, $results[0]);
    	        foreach ($results as $k => $v) {
    	            if (!is_numeric($k)) {
    	                $tokens[] = $k;
    	            }
    	        }
                for($i=0;$i< count($results[0]);$i++) {
                    $tmpval = $val;
                    foreach ($tokens as $token) {
                        $tmpval = str_replace("{".$token."}",$results[$token][$i],$tmpval);
                    }
                    $replace[] = $tmpval;
                }
	        }
	        // do actual replacement on the real body
	        $body = str_replace($search,$replace,$body);
	    }

        return true;
	}

	function _addEscapes($fullstring) {
		$fullstring            = str_replace("\\","\\\\",$fullstring);
		$fullstring            = str_replace("[","\[",$fullstring);
		$fullstring            = str_replace("]","\\]",$fullstring);
		return $fullstring;
	}

	function _readIniFile() {

        $app		= Factory::getApplication();
		$template 	= $app->getTemplate();
		$path     	= JPATH_SITE."/templates/".$template."/html/shortcodes.ini";
        $library 	= array();

        if (file_exists($path)) {
            $content = file_get_contents($path);
            $data = explode("\n",$content);

            if (!empty($data)){
                foreach ($data as $line) {
                    //skip comments
                    if (strpos($line,"#")!==0 and trim($line)!="" ) {
                       $div = strpos($line,"]=");
                       $library[substr($line,0,$div+1)] = substr($line,$div+2);
                    }
                }
            }
    	}
		return $library;
    }
}
