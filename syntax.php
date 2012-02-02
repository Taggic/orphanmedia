<?php
/**
 * OrphanMedia Plugin: Display orphan and missing media files
 * syntax ~~ORPHANMEDIA:<choice>
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     <taggic@t-online.de>
 */
/******************************************************************************/ 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_INC.'inc/search.php');
include(DOKU_INC.'inc/JpegMeta.php');
/******************************************************************************
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_orphanmedia extends DokuWiki_Syntax_Plugin {
/******************************************************************************/
/* return some info
*/
    function getInfo(){
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    function getType() { return 'substition';}
    function getPType(){ return 'block';}
    function getSort() { return 999;}
    
/******************************************************************************/
/* Connect pattern to lexer
*/
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~ORPHANMEDIA:[0-9a-zA-Z_:!]+~~',$mode,'plugin_orphanmedia');
    }
/******************************************************************************/
/* Handle the match
*/
    function handle($match, $state, $pos, &$handler){
        $match_array = array();
        //strip ~~ORPHANMEDIA: from start and ~~ from end
        $match = substr($match,14,-2);
        // split parameters 
        $match_array = explode("!", $match);
        // $match_array[0] will be summary, missing, orphan or syntax error
        // once, if there are excluded namespaces, they will be in $match_array[1] .. [x]
        // this return value appears in render() as the $data param there
        return $match_array;
    }

/******************************************************************************/
/* Create output 
*/
    function render($format, &$renderer, $data) {

        if($format !== 'xhtml'){ return false; }  // cancel if not xhtml
            global $INFO, $conf;
            
            // Media Filter by Extension ---------------------------------------
            $defFileTypes = explode(':',$data[0]); // split use case entry and file extensions
            $data[0] = $defFileTypes[0]; // store pure use case entry back to data
            unset($defFileTypes[0]); // delete the use case entry to keep file extensions only
            $defFileTypes2 = implode(', ',$defFileTypes); // just for output the filter on summary
            $defFileTypes = implode('',$defFileTypes); // string of file extensions to easily compare it by strpos
            if($defFileTypes2==false) $defFileTypes2='none';
            // -----------------------------------------------------------------
            // $data is an array
            // $data[0] is the report type: 'all' or 'valid' or 'missing' or 'orphan' or 'summary'
            // $defFileTypes is string of excluded media file types


// -----------------------------------------------------------------------------
// ! CHECK: Where are the excluded namespaces ?
// -----------------------------------------------------------------------------            
            
            // retrive all media files
            $listMediaFiles     = array();
            $listMediaFiles     = $this->_get_allMediaFiles($conf['mediadir'], $defFileTypes);
            $listMediaFiles     = $this->array_flat($listMediaFiles);
            $media_file_counter = count($listMediaFiles);

            // retrieve all page files
            $listPageFiles = array();
            $listPageFiles = $this->_get_allPageFiles($conf['datadir']);
            $listPageFiles = $this->array_flat($listPageFiles);
            $page_counter  = count($listPageFiles);
            
            // retrieve all media links per page file
            $listPageFile_MediaLinks = array();
            $listPageFile_MediaLinks = $this->_get_allMediaLinks($listPageFiles, $defFileTypes);
//            echo sprintf("<p>%s</p>\n", var_dump($listPageFile_MediaLinks));
            
            // analyse matches of media files and pages->media links
            // what if savedir option is used ? => $conf['mediadir']
            $doku_media = $conf['mediadir'].'/';           
            $doku_pages = $conf['datadir'].'/';
            //$doku_media = str_replace("\\","/",DOKU_MEDIA);
            //$doku_pages = str_replace("\\","/",DOKU_PAGES);
            
            $listMediaFiles = array($listMediaFiles,array_pad(array(),count($listMediaFiles),'0'));
            $position = 0;
                        
            foreach($listMediaFiles[0] as &$media_file_path) {
              // strip ...dokuwiki/data/media path
              $media_file_path = str_replace("\\","/",$media_file_path);
              $media_file_path = str_replace($doku_media,"",$media_file_path);
              
              // 1. direct matches where pages->media links are identical to media file path
              foreach($listPageFile_MediaLinks as &$perPage_MediaLinks) {
                  for($i = 1; $i < count($perPage_MediaLinks); $i++) {
                      // prevent destroying unc path
                      if((stripos($perPage_MediaLinks[$i],":\\")===false) && stripos($perPage_MediaLinks[$i],"://")===false) {
                          $perPage_MediaLinks[$i] = str_replace(":","/",$perPage_MediaLinks[$i]);
                      }
                      // strip initial slash if exist
                      if(strpos($perPage_MediaLinks[$i],"/") === 0)  $perPage_MediaLinks[$i] = ltrim($perPage_MediaLinks[$i],"/");
                      
                      // case 1: find full qualified links: Page_MediaLink = media_file path
                      if($perPage_MediaLinks[$i] === $media_file_path) {
                          $perPage_MediaLinks[$i] .= "|valid";
                          $listMediaFiles[1][$position] = "found";                         
                          continue;
                      }

                      // case 2: find relative links: Page_path + Page_MediaLink = media_file path
                      //example: Page = tst:start with a media link syntax like {{picture}} = mediafile(tst:picture)
                      if((strpos($perPage_MediaLinks[$i],"|valid")===false) && (strpos($perPage_MediaLinks[$i],"|relative")===false)) {
                          $pagePath = rtrim($perPage_MediaLinks[0],end(explode("/",$perPage_MediaLinks[0] ))).$perPage_MediaLinks[$i];
                          // strip ...dokuwiki/data/pages path
                          $pagePath = str_replace("\\","/",$pagePath);
                          $pagePath = str_replace($doku_pages,"",$pagePath);
                          //echo $pagePath.'<br />';
                          if($pagePath === $media_file_path) {
                              $perPage_MediaLinks[$i] .= "|relative";
                              $listMediaFiles[1][$position] = "found";                         
                              continue;
                          }
                      }
                      
                      // case 3: it is an external linked media by http or https
                      if((strpos($perPage_MediaLinks[$i],'http:')!==false) || (strpos($perPage_MediaLinks[$i],'https:')!==false)) { 
                          if((strpos($perPage_MediaLinks[$i],"|valid")===false) && (strpos($perPage_MediaLinks[$i],"|relative")===false)) {
                              $t_flag = $this->url_exist($perPage_MediaLinks[$i]);

                              if($t_flag !== false) {
                                  $perPage_MediaLinks[$i] .= "|relative";
                                  $listMediaFiles[1][$position] = "found";
//                                  msg($perPage_MediaLinks[$i],1);
                                  continue;
                              }
//                              else msg($perPage_MediaLinks[$i],-1); 
                          }
                      }
                  }
              }
              $position++;
            }
            // 2. missing media files
            $ok_img = "ok.png";
            $nok_img= "nok.png";
            if(strlen($defFileTypes) > 1) $filterInfo.= '<span>Filter settings: '.$defFileTypes.'<br />';
            $output_valid = '<div class="level1">'.
                              '  <span>The following existing media files are referenced by full qualified path:</span><br />'.
                              '<table class="inline">'.
                              '<tr><th  class="orph_col0 centeralign">i</th>
                                   <th  class="orph_col1 centeralign">#</th>
                                   <th  class="orph_col2 centeralign"> Page files </th>
                                   <th  class="orph_col3 centeralign"> valid Media </th></tr>';   
            $output_relative = '<div class="level1">'.
                              '  <span>The following existing media files are referenced by relative path:</span><br />'.
                              '<table class="inline">'.
                              '<tr><th  class="orph_col0 centeralign">i</th>
                                   <th  class="orph_col1 centeralign">#</th>
                                   <th  class="orph_col2 centeralign"> Page files </th>
                                   <th  class="orph_col3 centeralign"> relative Media </th></tr>';
            $output_missing = '<div class="level1">'.
                              '  <span>The following media files are missing:</span><br />'.
                              '<table class="inline">'.
                              '<tr><th  class="orph_col0 centeralign">i</th>
                                   <th  class="orph_col1 centeralign">#</th>
                                   <th  class="orph_col2 centeralign"> Page files </th>
                                   <th  class="orph_col3 centeralign"> missing Media </th></tr>';
            $output_orphan = '<div class="level1">'.
                              '  <span>The following media files are orphan:</span><br />'.
                              '<table class="inline">'.
                              '<tr><th  class="orph_col0 centeralign">i</th>
                                   <th  class="orph_col1 centeralign">#</th>
                                   <th  class="orph_col2 centeralign"> Media files </th>
                                   <th  class="orph_col3 centeralign"> Preview orphan Media </th></tr>';
                              
            foreach($listPageFile_MediaLinks as $perPage_MediaLinks) {
                for($i = 1; $i < count($perPage_MediaLinks); $i++) {
                    $refLink_counter ++;
                    if((strpos($perPage_MediaLinks[$i],"|valid")>0)) {
                        $valid_counter++;
                        $output_valid .= $this->_prepare_output(rtrim($perPage_MediaLinks[$i],"|valid"),$perPage_MediaLinks[0],$ok_img,$valid_counter);
                    }
                    if((strpos($perPage_MediaLinks[$i],"|relative")>0)) {
                        $relative_counter++;
                        $output_relative .= $this->_prepare_output(rtrim($perPage_MediaLinks[$i],"|relative"),$perPage_MediaLinks[0],$ok_img,$relative_counter);
                    }
                    if((strpos($perPage_MediaLinks[$i],"|valid")===false) && (strpos($perPage_MediaLinks[$i],"|relative")===false)) {
                        $missing_counter++;
                        $output_missing .= $this->_prepare_output($perPage_MediaLinks[$i],$perPage_MediaLinks[0],$nok_img,$missing_counter);
                        
                    }
                }
            }
            
            $position = 0;            
            foreach($listMediaFiles[1] as $check) {
                if($check === '0') {
                  $orphan_counter++;
                  if(!$conf['useslash']) $rt2 = str_replace("/", ":", $listMediaFiles[0][$position]);
                  else $rt2 = $listMediaFiles[0][$position];
                  
                  $m_link = $conf['mediadir'].'/'.$listMediaFiles[0][$position];
                  $style = '';
                  $w_max = 200;
                  $h_max = 75;
                  if (preg_match("/\.(jpe?g|gif|png)$/", $m_link) && file_exists($m_link)) {
                       $minfo = getimagesize($m_link);
                       $w = (int) $minfo[0];
                       $h = (int) $minfo[1];

                       if($w > $w_max || $h > $h_max) {
                           if($h > $h_max) {
                              $ratio = $h_max / $h;
                              $h = $h_max;
                              $w = floor($w * $ratio);
                           }
                           else {
                              $ratio = $w_max / $w;
                              $w = $w_max;
                              $h = floor($h * $ratio);
                           }
                       }
                       $style = ' style="width: '.$w.'px; height: '.$h.'px;"';
                       $picturepreview = '<a href="' . DOKU_URL . 'lib/exe/detail.php?media=' . $rt2  
                                    . '" class="media" title="'. $listMediaFiles[0][$position]  
                                    . '"><img src="'. DOKU_URL . 'lib/exe/fetch.php?media=' . $rt2 
                                    . '" class="media" ' .$style. 'alt="' . $listMediaFiles[0][$position] .'" /></a>';
                  }
                  else {
                      list($ext,$mime,$dl) = mimetype(mediaFN($m_link),false);
                  
                      if (@file_exists(DOKU_INC.'lib/images/fileicons/'.$ext.'.png')) {
                          $icon = DOKU_BASE.'lib/images/fileicons/'.$ext.'.png';
                      } else {
                          $icon = DOKU_BASE.'lib/images/fileicons/file.png';
                      }
                      $icon = '<img src="'.$icon.'" alt="'.$m_link.'" class="icon" />';
                      $picturepreview = '<a href="' . $m_link   
                                   . '" class="wikilink" title="'. $m_link  
                                   . '">'.$icon.'&nbsp;'.$rt2.'</a>';
                  }
        

                  
                  
                  $output_orphan .= '<tr>'.NL.
                              '<td>'.NL.
                                  '<img src="'.DOKU_URL.'\/lib\/plugins\/orphanmedia\/images\/'.$nok_img.'" alt="nok" title="orphan" align="middle" />'.NL.
                              '</td>'.NL.
                              '<td>'.$orphan_counter.'</td>'.NL.
                              '<td>'.$listMediaFiles[0][$position].'</td>'.NL.
                              '<td>'.$picturepreview.'</td>'.NL.'</tr>'.NL;
                }
                $position++;  
            }

            $output_valid    .= '</table></div>';
            $output_relative .= '</table></div>';
            $output_missing  .= '</table></div>';
            $output_orphan   .= '</table></div>';
            $output_summary = '<div class="level1">'.NL.
                              '  <span class="orph_sum_head">Summary</span><br />'.NL.
                              '<table class="oprph_sum_tbl">'.NL.
                              ' <tr>'.NL.
                              '   <td class="oprph_sum_col0" rowspan="8">&nbsp;</td>'.NL.
                              '   <td class="oprph_sum_col1">Page files</td>'.NL.
                              '   <td class="oprph_sum_col2">'.$page_counter.'</td>'.NL.
                              ' </tr>'.NL.
                              ' <tr>'.NL.
                              '   <td>Media files</td>'.NL.
                              '   <td>'.$media_file_counter.'</td>'.NL.
                              ' </tr>'.NL.
                              ' <tr>'.NL.
                              '   <td>Media references</td>'.NL.
                              '   <td>'.$refLink_counter.'</td>'.NL.
                              ' </tr>'.NL.
                              ' <tr>'.NL.
                              '   <td>Filter</td>'.NL.
                              '   <td>'.$defFileTypes2.'</td>'.NL.
                              ' </tr>'.NL.
                              ' <tr>'.NL.
                              '   <td><b>Valid</b>, qualified references</td>'.NL.
                              '   <td>'.$valid_counter.'</td>'.NL.
                              ' </tr>'.NL.
                              ' <tr>'.NL.
                              '   <td><b>Valid</b>, relative references</td>'.NL.
                              '   <td>'.$relative_counter.'</td>'.NL.
                              ' </tr>'.NL.
                              ' <tr>'.NL.
                              '   <td><b>Missing</b> media files</td>'.NL.
                              '   <td>'.$missing_counter.'</td>'.NL.
                              ' </tr>'.NL.
                              ' <tr>'.NL.
                              '   <td><b>Orphan</b> media files</td>'.NL.
                              '   <td>'.$orphan_counter.'</td>'.NL.
                              ' </tr>'.NL.
                              '</table></div>'.NL; 


            if((stristr($data[0], "valid")===false) && (stristr($data[0], "all")===false)){
                $output_valid='';
            }
            if((stristr($data[0], "relative")===false) && (stristr($data[0], "all")===false)){
                $output_relative='';
            }
            if((stristr($data[0], "missing")===false) && (stristr($data[0], "all")===false)){
                $output_missing='';
            }
            if((stristr($data[0], "orphan")===false) && (stristr($data[0], "all")===false)){
                $output_orphan='';
            }
            
            $renderer->doc .= $output_summary.$output_valid.$output_relative.$output_missing.$output_orphan;          

            return true;
    }
/******************************************************************************/
/* loop through media directory and collect all media files 
/* consider: filter for media file extension if given 
*/ 
    function _get_allMediaFiles($dir, $defFileTypes) {
        $listDir = array();
        if(is_dir($dir)) { 
            if($handler = opendir($dir)) { 
                while (FALSE !== ($sub = readdir($handler))) { 
                    if ($sub !== "." && $sub !== "..") { 
                        if(is_file($dir."/".$sub)) {   
                            //get the current file extension ---------------------
                            $parts = explode(".", $sub);
                            if (is_array($parts) && count($parts) > 1) {
                                 $extension = end($parts);
                                 $extension = ltrim($extension, ".");
                            }
                            //-------------------------------------------- 
                            if($defFileTypes === '') {
                                $listDir[] = $dir."/".$sub;
                                //echo sprintf("<p><b>%s</b></p>\n", $dir."/".$sub);
                            } 
                            // if media file extension filters are set on syntax line the $defFileTypes containing a string of all
                            // and is the string to search the currnt file extension in 
                            elseif(strpos($defFileTypes, $extension)!==false) {
                                $listDir[] = $dir."/".$sub;
                                //echo sprintf("<p><b>%s</b></p>\n", $dir."/".$sub);
                            }
                        }
                        elseif(is_dir($dir."/".$sub)) { 
                            $listDir[$sub] = $this->_get_allMediaFiles($dir."/".$sub, $defFileTypes);
                            //echo sprintf("<p><b>%s</b></p>\n", $dir."/".$sub);;
                        } 
                    } 
                }    
                closedir($handler); 
            }
        }
        return $listDir;    
    }    
/******************************************************************************/
/* loop through data/pages directory and collect all page files 
*/ 
    function _get_allPageFiles($dir) {
        $listDir = array();
        if(is_dir($dir)) {
            if($handler = opendir($dir)) { 
                while (FALSE !== ($sub = readdir($handler))) { 
                    if ($sub !== "." && $sub !== "..") { 
                        if(is_file($dir."/".$sub)) {   
                            //get the current file extension ---------------------
                            $parts = explode(".", $sub);
                            if (is_array($parts) && count($parts) > 1) {
                                 $extension = end($parts);
                                 $extension = ltrim($extension, ".");
                            }
                            //-------------------------------------------- 
                            if(($extension === "txt")){
                                $listDir[] = $dir."/".$sub;
                                //echo sprintf("<p><b>%s</b></p>\n", $dir."/".$sub); 
                              }
                        }
                        elseif(is_dir($dir."/".$sub)) { 
                            $listDir[$sub] = $this->_get_allPageFiles($dir."/".$sub, $delim,$excludes);
                        } 
                    } 
                }    
                closedir($handler); 
            } 
        }
        return $listDir;    
    }    

/******************************************************************************/
/* loop through pages and extract their media links 
*/ 
    function _get_allMediaLinks($listPageFiles, $defFileTypes) {
        $_all_links = array();
        $pageCounter = 0;
        $linkCounter = 1;
        define('LINK_PATTERN', "/\{\{.*?\}\}/i");
        define('LINK_PATTERNtwo', "/<flashplayer.*>file=(?<link>.*)\x26.*<\/flashplayer>|<flashplayer.*>file=(?<all>.*)<\/flashplayer>/");
        define('LINK_PATTERNthree', "/\[\[(?<link>\\\\.*)\|.*\]\]|\[\[(?<all>\\\\.*)\]\]/");
        define('LINK_PATTERNfour', "/'\{\{gallery>[^}]*?\}\}'/");        
        
        // get all defined tags where media links inbetween are to be ignored
        $ignore_tags = array();
        $ignore_tags = parse_ini_file(DOKU_PLUGIN."orphanmedia/config.ini");
        
        foreach($listPageFiles as $page_filepath) {
            $_all_links[$pageCounter][0] = $page_filepath;
            // read the content of the page file to be analyzed for media links
            $body = file_get_contents($page_filepath);
            // -----------------------------------
            // ignore content between defined tags e.g. '<code> ... </code>'
            $body = preg_replace($ignore_tags,' ',$body);
            // find all page-> media links defined by Link pattern into $links
             $links = array(); 
             if( preg_match(LINK_PATTERN, $body) ) {
                 preg_match_all(LINK_PATTERN, $body, $links);
                 $links = $this->array_flat($links);
             }
            // -----------------------------------
            // Exception for flashplayer plugin where file reference is not inside curly brackets
            // RegEx -> SubPattern and Alternate used as follows
            // /<flashplayer.*>file=(?<link>.*)\x26.*<\/flashplayer>|<flashplayer.*>file=(?<all>.*)<\/flashplayer>/
            // check online at http://www.solmetra.com/scripts/regex/index.php
            // -----------------------------------
            // Case 0: link with appended options -> initial pattern applies
            // Case 1: link without options -> alternate pattern applies
            // -----------------------------------
            // results in:
            /* Array
              (   [0] => Array
                      ( [0] => <flashplayer width=610 height=480>file=/doku/_media/foo/bar.flv&autostart=true</flashplayer>
                        [1] => <flashplayer width=610 height=480>file=/doku/_media/foo/bar.flv</flashplayer> )
                  [link] => Array
                      ( [0] => /doku/_media/foo/bar.flv
                        [1] => )
                  [1] => Array
                      ( [0] => /doku/_media/foo/bar.flv
                        [1] => )
                  [all] => Array
                      ( [0] => 
                        [1] => /doku/_media/foo/bar.flv )
                  [2] => Array
                      ( [0] => 
                        [1] => /doku/_media/foo/bar.flv )
              ) */
            $flashpl_links = array();
            $a_links = array();
            if( preg_match(LINK_PATTERNtwo, $body) ) {
                preg_match_all(LINK_PATTERNtwo, $body, $flashpl_links);
                //finally loop through link and all and pick-up all non-empty fields
                foreach($flashpl_links['link'] as $flashpl_link) {
                    if(strlen($flashpl_link)>3) $a_links[] = $flashpl_link;
                }
                foreach($flashpl_links['all'] as $flashpl_link) {
                    if(strlen($flashpl_link)>3) $a_links[] = $flashpl_link;
                }
                unset($flashpl_links);
                $flashpl_links = $a_links;
                
            }
            // -----------------------------------
            // Exception for Windows Shares like [[\\server\share|this]] are recognized, too.
            // RegEx -> SubPattern and Alternate used as follows
            // /\[\[(?<link>\\\\.*)\x7c.*\]\]|\[\[(?<all>\\\\.*)\]\]/
            // check online at http://www.solmetra.com/scripts/regex/index.php
            // -----------------------------------
            // Case 0: link with appended options -> initial pattern applies
            // Case 1: link without options -> alternate pattern applies
            // -----------------------------------
            // results in:
            /*Array
              ( [0] => Array
                      ( [0] => [[\\server\share|this]]
                        [1] => [[\\server\share]] )
                [link] => Array
                      ( [0] => server\share
                        [1] => )
                [1] => Array
                      ( [0] => server\share
                        [1] => )
                [all] => Array
                      ( [0] => 
                        [1] => server\share )
                [2] => Array
                      ( [0] => 
                        [1] => server\share )
              ) */
             $fileshares = array();
             $b_links = array(); 
             if( preg_match(LINK_PATTERNthree, $body) ) {
                 preg_match_all(LINK_PATTERNthree, $body, $fileshares);
                //finally loop through link and all and pick-up all non-empty fields
                foreach($fileshares['link'] as $flshare_link) {
                    if(strlen($flshare_link)>3) $b_links[] = $flshare_link;
                }
                foreach($fileshares['all'] as $flshare_link) {
                    if(strlen($flshare_link)>3) $b_links[] = $flshare_link;
                }
                unset($fileshares);
                $fileshares = $b_links;
             }                                                            
            // -----------------------------------
            // loop through page-> media link array and prepare links
            foreach($links as $media_link) {
                // exclude http, tag and topic links
                if(strlen($media_link)<3) continue;
                //if(stristr($media_link, "http:")!==false) continue;
                if(stristr($media_link, "tag>")!==false) continue;
                if(stristr($media_link, "blog>")!==false) continue;
                if(stristr($media_link, "topic>")!==false) continue;
                if(stristr($media_link, "wikistatistics>")!==false) continue;
            // ---------------------------------------------------------------
                $media_link = $this->clean_link($media_link);
                if(!$media_link) continue;    // ignore empty fields
            // ---------------------------------------------------------------  

                // filter according $defFileTypes
                if($defFileTypes !==""){
                    $parts = explode(".", $media_link);
                    if (is_array($parts) && count($parts) > 1) {
                       $extension = end($parts);
                       $extension = ltrim($extension, ".");
                    }
                  if(stristr($defFileTypes, $extension)===false) continue;
                } 
                // collect all media links of the current page
                //$page_filepath .= "|" . strtolower($media_link);
                $_all_links[$pageCounter][$linkCounter] = strtolower($media_link);
                $linkCounter++;
             }
             
             // loop through page-> flashplayer link array and prepare links
             if(count($flashpl_links)>0) {
                 foreach($flashpl_links as $flashpl_link) {
                    if(strlen($flashpl_link)<3) continue;
                    // filter according $defFileTypes
                    if($defFileTypes !==""){
                        $parts = explode(".", $flashpl_link);
                        if (is_array($parts) && count($parts) > 1) {
                           $extension = end($parts);
                           $extension = ltrim($extension, ".");
                        }
                      if(stristr($defFileTypes, $extension)===false) continue;
                    }
                    
                    // exclude external flashplayer links
                    if((strlen($flashpl_link)>1) && strpos($flashpl_link, "://")<1) {
                         // collect all flashplayer links of the current page
                         $_all_links[$pageCounter][$linkCounter] = strtolower($flashpl_link);
                         $linkCounter++;
                    }
                 }
             }
             // loop through page-> fileshare link array and prepare links
             if(count($fileshares)>0) {         
                 foreach($fileshares as $fileshare_link) {
                    if(strlen($fileshare_link)<3) continue;
                    // filter according $defFileTypes
                    if($defFileTypes !==""){
                        $parts = explode(".", $fileshare_link);
                        if (is_array($parts) && count($parts) > 1) {
                           $extension = end($parts);
                           $extension = ltrim($extension, ".");
                        }
                      if(stristr($defFileTypes, $extension)===false) continue;
                    }
                    // exclude external flashplayer links
                    if((strlen($fileshare_link)>1) && strpos($fileshare_link, "://")<1) {
                         // collect all flashplayer links of the current page
                         $_all_links[$pageCounter][$linkCounter] = strtolower($fileshare_link);
                         $linkCounter++;
                    }
                 }
             }

            // do merge media and flashplayer arrays
            // $page_filepath string does already contain all local media and flashplayer links separated by "|"
            //$page_filepath = preg_replace(":","/",$page_filepath);
            //$page_filepath = preg_replace("|","<br />",$page_filepath);            
//            echo var_dump($_all_links[$pageCounter]).'<br />';
            $pageCounter++;
            $linkCounter = 1;
        }
        
        return $_all_links;
    }
//---------------------------------------------------------------------------------------
    // flatten the hierarchical arry to store path + file at first "column"
    function array_flat($array) {   
        $out=array();
        foreach($array as $k=>$v){  
            if(is_array($array[$k]))  { $out=array_merge($out,$this->array_flat($array[$k])); }
            else  { $out[]=$v; }
        }     
        return $out;
    }
//---------------------------------------------------------------------------------------
    function clean_link($xBody)
    { // evaluate the media link by DW embedded function
       $link = Doku_Handler_Parse_Media($xBody);
       if (stripos($link['src'],'>') === false) $xBody = $link['src'];
       else $xBody = '';
       return $xBody; 
    }
// ---------------------------------------------------------------
    function _prepare_output($m_link,$page,$img,$counter,$class=NULL)
    {
            global $conf;
            // all media files checked with current media link from current page
            //extract page file name
            $p_filename = basename($page);
            //cut everything before pages/ from link
            $y_pos=strpos($page, "pages");
            $t1 = substr($page, $y_pos);
            $t1 = substr(str_replace( ".txt" , "" , $t1 ) , 5, 9999);

            if(!$conf['useslash']) $t2 = str_replace("/", ":", $t1);
            else $t2 = $t1;

            $t2 = '<a class=wikilink1 href="'. DOKU_URL . "doku.php?id=" . substr($t2, 1, strlen($t2));
            $t1 =  $t2 . '" title="' . $t1 . '" rel="nofollow">' . $t1 . '</a>';                   
                    
                
            $output.= '<tr>'.NL.
                      '   <td class="col0 centeralign"><img src="'.DOKU_URL.'\/lib\/plugins\/orphanmedia\/images\/'.$img.'" align="middle" /></td>'.NL.
                      '   <td>'.$counter.'</td>'.NL.
                      '   <td>' . $t1 . "</td><td>" . $m_link . '</td>'.
                      '</tr>'.NL;                   
        return $output;
    }
// --------------------------------------------------------------- 
    function url_exist($url){
        $fb_url = $url;
        $url = @parse_url($url);  
        if (!$url) return false;    
        $url = array_map('trim', $url);  
        $url['port'] = (!isset($url['port'])) ? 80 : (int)$url['port'];  
        $path = (isset($url['path'])) ? $url['path'] : '';  
        if ($path == '')   $path = '/';    
        $path .= (isset($url['query'])) ?  "?$url[query] " : '';  
        if (isset($url['host']) AND $url['host'] != gethostbyname($url['host']))  
        {  
           $headers = @get_headers( "$url[scheme]://$url[host]:$url[port]$path ");  
           $headers = (is_array($headers)) ? implode( "n ", $headers) : $headers;    
//           return (bool)preg_match('#^HTTP/.*s+[(200|301|302)]+s#i', $headers);
           return true;   
        }  
        return false;  
    } 
// --------------------------------------------------------------- 
      function getResizeRatio($maxwidth,$maxheight=0){
          if(!$maxheight) $maxheight = $maxwidth;
          $ratio = 1;
          if($w >= $h) {
              if($w >= $maxwidth){ $ratio = $maxwidth/$w; }
              elseif($h > $maxheight) { $ratio = $maxheight/$h; }
          }
          else {
              if($h >= $maxheight) { $ratio = $maxheight/$h; }
              elseif($w > $maxwidth) { $ratio = $maxwidth/$w; }
          }
          return $ratio;
      }
// ---------------------------------------------------------------   
}
?>