<?php
/**
 * OrphanMedia Plugin: Display orphan and missing media files
 * syntax ~~ORPHANMEDIA:<choice>
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     <taggic@t-online.de>
 */

 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_INC.'inc/html.php'); 
require_once(DOKU_INC.'inc/search.php');
 
define('DEBUG', 0);
//---------------------------------------------------------------------------------------
    // flatten the hierarchical arry to store path + file at first "column"
    function array_flat($array) {   
        $out=array();
        foreach($array as $k=>$v){  
            if(is_array($array[$k]))  { $out=array_merge($out,array_flat($array[$k])); }
            else  { $out[]=$v; }
        }     
        return $out;
    }

    function clean_link($xBody)
    {
    // cut the two leading '{{'
         $xBody=ltrim($xBody, '{');
         //cut questionmark and further characters if exist
         if (strpos($xBody, '?') > 0) { $xBody = substr($xBody,0, strpos($xBody, '?')); }
         // sometimes a blank remains at the end to be cut
         $xBody = str_replace(" ", '' ,$xBody);
         //cut pipe and further characters if pipe exist
         if (strpos($xBody, '|') > 0) { $xBody = substr($xBody,0, strpos($xBody, '|')); }
         if (strpos($xBody, '}}') > 0) { $xBody = substr($xBody,0, strpos($xBody, '}}')); }
         return $xBody; 
    }
    
// ---------------------------------------------------------------

    function case1_good_output($c1_medialist,$img)
    {
        $i1 = 0; 
        foreach($c1_medialist as $afile) {
            $i1++;                                                                                         
            $output.= '<tr><td class="col0 centeralign"><a href="' . DOKU_URL . '/lib/plugins/orphanmedia/images/' . $img 
                  . '"><img src="' . DOKU_URL . '/lib/plugins/orphanmedia/images/' . $img 
                  . '" class="media" alt="orphan Media File" /></a></td>'
                  . '<td>'. $i1 . '</td><td>' . $afile . '</td></tr>';
        }             
        $output.= '</td></tr></table></p>';        
        return $output;
    }
 
// --------------------------------------------------------------- 
// ---------------------------------------------------------------
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_orphanmedia extends DokuWiki_Syntax_Plugin {

   /**
    * Get an associative array with plugin info.
    *
    * <p>
    * The returned array holds the following fields:
    * <dl>
    * <dt>author</dt><dd>Author of the plugin</dd>
    * <dt>email</dt><dd>Email address to contact the author</dd>
    * <dt>date</dt><dd>Last modified date of the plugin in
    * <tt>YYYY-MM-DD</tt> format</dd>
    * <dt>name</dt><dd>Name of the plugin</dd>
    * <dt>desc</dt><dd>Short description of the plugin (Text only)</dd>
    * <dt>url</dt><dd>Website with more information on the plugin
    * (eg. syntax description)</dd>
    * </dl>
    * @param none
    * @return Array Information about this plugin class.
    * @public
    * @static
    */
    /**
     * return some info
     */
    function getInfo(){
        return array(
            'author' => 'Taggic',
            'email'  => 'Taggic@t-online.de',
            'date'   => @file_get_contents(dirname(__FILE__) . '/VERSION'),
            'name'   => 'OrphanMedia Plugin',
            'desc'   => 'Display orphan and missing media files.
            syntax ~~ORPHANMEDIA:all|summary|missing|orphan~~' ,
            'url'    => 'http://dokuwiki.org/plugin:orphanmedia',
        );
    }
// --------------------------------------------------------------- 

   /**
    * Get the type of syntax this plugin defines.
    *
    * @param none
    * @return String <tt>'substition'</tt> (i.e. 'substitution').
    * @public
    * @static
    */
    function getType(){
        return 'substition';
    }
	
    /**
     * What kind of syntax do we allow (optional)
     */
//    function getAllowedTypes() {
//        return array();
//    }
   
   /**
    * Define how this plugin is handled regarding paragraphs.
    *
    * <p>
    * This method is important for correct XHTML nesting. It returns
    * one of the following values:
    * </p>
    * <dl>
    * <dt>normal</dt><dd>The plugin can be used inside paragraphs.</dd>
    * <dt>block</dt><dd>Open paragraphs need to be closed before
    * plugin output.</dd>
    * <dt>stack</dt><dd>Special case: Plugin wraps other paragraphs.</dd>
    * </dl>
    * @param none
    * @return String <tt>'block'</tt>.
    * @public
    * @static
    */
    function getPType(){
        return 'normal';
    }

   /**
    * Where to sort in?
    *
    * @param none
    * @return Integer <tt>6</tt>.
    * @public
    * @static
    */
    function getSort(){
        return 999;
    }


   /**
    * Connect lookup pattern to lexer.
    *
    * @param $aMode String The desired rendermode.
    * @return none
    * @public
    * @see render()
    */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~ORPHANMEDIA:[0-9a-zA-Z_:!]+~~',$mode,'plugin_orphanmedia');
//      $this->Lexer->addSpecialPattern('<TEST>',$mode,'plugin_test');
//      $this->Lexer->addEntryPattern('<TEST>',$mode,'plugin_test');
    }
	
//    function postConnect() {
//      $this->Lexer->addExitPattern('</TEST>','plugin_test');
//    }


   /**
    * Handler to prepare matched data for the rendering process.
    *
    * <p>
    * The <tt>$aState</tt> parameter gives the type of pattern
    * which triggered the call to this method:
    * </p>
    * <dl>
    * <dt>DOKU_LEXER_ENTER</dt>
    * <dd>a pattern set by <tt>addEntryPattern()</tt></dd>
    * <dt>DOKU_LEXER_MATCHED</dt>
    * <dd>a pattern set by <tt>addPattern()</tt></dd>
    * <dt>DOKU_LEXER_EXIT</dt>
    * <dd> a pattern set by <tt>addExitPattern()</tt></dd>
    * <dt>DOKU_LEXER_SPECIAL</dt>
    * <dd>a pattern set by <tt>addSpecialPattern()</tt></dd>
    * <dt>DOKU_LEXER_UNMATCHED</dt>
    * <dd>ordinary text encountered within the plugin's syntax mode
    * which doesn't match any pattern.</dd>
    * </dl>
    * @param $aMatch String The text matched by the patterns.
    * @param $aState Integer The lexer state for the match.
    * @param $aPos Integer The character position of the matched text.
    * @param $aHandler Object Reference to the Doku_Handler object.
    * @return Integer The current lexer state for the match.
    * @public
    * @see render()
    * @static
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

   /**
    * Handle the actual output creation.
    *
    * <p>
    * The method checks for the given <tt>$aFormat</tt> and returns
    * <tt>FALSE</tt> when a format isn't supported. <tt>$aRenderer</tt>
    * contains a reference to the renderer object which is currently
    * handling the rendering. The contents of <tt>$aData</tt> is the
    * return value of the <tt>handle()</tt> method.
    * </p>
    * @param $aFormat String The output format to generate.
    * @param $aRenderer Object A reference to the renderer object.
    * @param $aData Array The data created by the <tt>handle()</tt>
    * method.
    * @return Boolean <tt>TRUE</tt> if rendered successfully, or
    * <tt>FALSE</tt> otherwise.
    * @public
    * @see handle()
    */
// --------------------------------------------------------------- 
     // Create output
     function render($format, &$renderer, $data) {
        global $INFO, $conf;
        if($format == 'xhtml'){ 
            // $data is an array
            // $data[0] is the report type
            // $data[1]..[x] are excluded namespaces
            //handle choices
            switch ($data[0]){    
                case ('all' || 'missing' || 'orphan' || 'summary'):       
                    $renderer->doc .= $this->all_media_pages($data);
                    break;
                default:
                  $renderer->doc .= '<b>' . htmlspecialchars($data[0]) . "ORPHANMEDIA syntax error</b>";
            }    
             return true;
        }
        return false;
     }
// ---------------------------------------------------------------
    function all_media_pages($params_array) {
                     
      global $conf;
      $data = array();
      $result = '';
      $operator = 1;
           
  // 1. load all media files into array
      // create an array of all media files incl. path   
      $delim = 'all';
      clearstatcache();
      $listMediaFiles = $this->list_files_in_array($conf['mediadir'], $delim, $nothing);
      $listMediaFiles = array_flat($listMediaFiles);
      $media_file_counter = count($listMediaFiles);
      sort($listMediaFiles);
      $c1_medialist = $listMediaFiles;       // result array for Case 1:  check if page refrenced media exist at media directory
      $count_medialist_array = count($listMediaFiles);
      
  // 2. load all page files into array
     // create an array of all page files incl. path   
     $delim = ".txt";
     clearstatcache();
     $listPageFiles = $this->list_files_in_array($conf['datadir'], $delim, $params_array);
     $xArray =array();
     $listPageFiles = array_flat($listPageFiles);
     sort($listPageFiles);
     $page_counter = count($listPageFiles);
     
  // 3. loop through page-> media links and media list
    foreach($listPageFiles as &$page_filepath) {                            
                  
         //read the content of the page file to be analyzed for media links
         $body = '';
         $body = file_get_contents($page_filepath);
          
         // 4. find all page-> media links defined by Link pattern into $links
         $links = array();
//         define('LINK_PATTERN', "/\{\{.*\}\}/"); 
         define('LINK_PATTERN', "/\{\{(?!.*\x3E).*\}\}/");
         if( preg_match(LINK_PATTERN, $body) ) {
             preg_match_all(LINK_PATTERN, $body, $links);
             $links = array_flat($links);
         }
         if (count($links)<1) continue;
         // 5. loop through all link matches of this $current_page_filepath
         $media_links_array = array();
         foreach($links as $media_link) {                           
             // split $lBody if more than one media link caught at once
             $media_links_array = array_merge($media_links_array, preg_split ('/{{/', $media_link));
         }
         $media_links_array = array_unique($media_links_array);
         
         // 6. loop through page-> media link array ($lBody_array) and prepare links
         foreach($media_links_array as $media_link) {
             if(strlen($media_link)<3) continue;
          // ---------------------------------------------------------------
             $media_link = clean_link($media_link);
          // ---------------------------------------------------------------  
             // exclude http, tag and topic links
             if((strlen($media_link)>1) && strpos($media_link, "://")<1 && strpos($media_link, "tag>")<1 
              && strpos($media_link, "topic>")<1  && strpos($media_link, "blog>")<1   
              && strpos($media_link, "wikistatistics>")<1) {
             
                  $page_filepath = $page_filepath . "|" . strtolower($media_link);
             }
             if(strlen($media_link)>0) $tmp_link_counter++;
         }
    }

//  $params_array[0] = 'all' || 'summary' || 'orphan' || 'missing'  <- one of that is mandatory
    switch ($params_array[0]){
// ---------------------------------------------------------------------------//
//   Case 1: check for 100% orphan media files                                //
//           => media filename is referenced nowhere                          //
//           Weakpoint: identical media filenames (e.g. multiple image001.gif)//
// ---------------------------------------------------------------------------//
    case ('all' || 'summary' || 'orphan'):    
       // 1.1 loop through media file list to find direct links
      $a_counter = 0;
      $b_counter = 0;
      $c_counter = 0;
      $d_counter = 0;
      $mediareferences = array();
      
      foreach($c1_medialist as $media_file) { 
         $checkFlag = FALSE;
         $c_counter++;
         // 1.2 loop page files 
         foreach($listPageFiles as $x_link) { 
              
//              echo sprintf("<p><b>%s</b></p>\n", strtolower($x_link) . ' ==> ' . strtolower(basename($media_file)));
                               
              // 1.3 check if media filename exist at page media links 
              if(strpos(strtolower($x_link),strtolower(basename($media_file)))>1) {
                  $checkFlag = TRUE;
                  $a_counter++; 
                  break;
              }              
         }
         // all pages checked with current media filename
         if($checkFlag === FALSE) {
            //extract media path and media filename
            $m_filename = basename($media_file);
            
            //cut everything before incl. "media"
            $y_pos=strpos($media_file, "media");
            $t1 = substr($media_file, $y_pos + 5);
            $len_m_filename = strlen($m_filename);
            $m_path = substr($t1, 0, -$len_m_filename);
            
            // /lib/exe/fetch.php?media=namespace/image.jpg&w=100
            // /_media/namespace/image.jpg?w=100
            if(strlen($m_path)>1) {
                $rt1 = $m_path . $m_filename;
                $rt2 = str_replace("/", ":", $m_path . $m_filename);
            }
            else {
                $rt1 = $m_filename;
                $rt2 = ":" . $m_filename;
            }
   
            //echo sprintf("<p><b>%s</b></p>\n", $rt1);

            $picturepreview = '<a href="' . DOKU_URL . 'lib/exe/detail.php?media=' . $rt2  
                              . '" class="media" title="'. $m_filename  
                              . '"><img src="'. DOKU_URL . 'lib/exe/fetch.php?media=' . $rt2 
                              . '&w=100" class="media" width=100 alt="' . $m_filename . '" /></a>';  

            $orphan_Media_Files[] = $m_path . $m_filename . "</td><td>" . $picturepreview;
            $b_counter++;
                        
         }
      }

// ---------------------------------------------------------------------------//
//   Case 2: check for missing media files                                    //
//          (page links to not existing media file)                           //
// ---------------------------------------------------------------------------//
    case ('all' || 'summary' || 'missing'):    
      $e_counter = 0;
      
      // 2.1 loop page files
      foreach($listPageFiles as $x_link) {
          // $x_array[0]        = page_file (path + filename)
          // $x_array[1 ... n]  = media links
          $x_array = explode('|', $x_link);
          
          // 2.2 loop media links of current page file
          foreach ($x_array as $tst) {
              // ignore page file at $x_array[0]
              if($tst == $x_array[0]) continue;
              // ignore empty links
              if(strlen($tst)<3) continue;
              $m_link = str_replace(":", '/' ,$tst);
              $m_link = str_replace("//", '/' ,$m_link);
              $checkFlag = FALSE;
              
              // 2.3 loop media files
              foreach($c1_medialist as $media_file) {
                  
                  // 2.4 compare media link and media files
                  if(strpos(strtolower($media_file), strtolower($m_link))>0) {
                      $checkFlag = TRUE;
                      // link targets to existing file, continue with next link
                      break;
                  }
              }
              // all media files checked with current media link from current page
              if($checkFlag === FALSE) {
                  //extract page file name
                  $p_filename = basename($x_array[0]);
                  
                  //cut everything before pages/ from link
                  $y_pos=strpos($x_array[0], "pages");
                  $t1 = substr($x_array[0], $y_pos);
            
                  
                  $t1 = substr(str_replace( ".txt" , "" , $t1 ) , 5, 9999);
                  // turn it into wiki link without "pages"
                  /*  $t1= html_wikilink($t1,$t1);  */
                  $t2 = str_replace("/", ":", $t1);
                  $t2 = substr($t2, 1, strlen($t2));
                   
                   $t1 = '<a class=wikilink1 href="'. DOKU_URL . "doku.php?id=" . $t2 . '" title="' . $t1 . '" rel="nofollow">' . $t1 . '</a>';                   

                  // store page file and media link for output
                  $missing_Media_Files[] = $t1 . "</td><td>" . $m_link;
                  $e_counter++;
                  
              }
          }
      }
    }  
//--------------------------------------------------------------------------------------------------------   
      // Now show the result
//--------------------------------------------------------------------------------------------------------   
        $output = '';
        // for valid html - need to close the <p> that is feed before this
        $output .= '</p>';
    switch ($params_array[0]){
        case 'all':
            $output.= '<h2><a name="summary" id="summary">Summary</a></h2><div class="level1">';
            $output.= '<p>Media list contains: '. $count_medialist_array . " files" . '</p>';    
            $output.= '<p>Page list contains: '. $page_counter . " files" . '</p>';    
            $output.= '<p>Found  media filename references: '. $a_counter . " in " . count($listPageFiles) . ' pages</p>';    
            $output.= '<p>Different media links extracted from pages: '. $tmp_link_counter . '</p>';    
            $output.= '<p>Missing media files detected: '. $e_counter . '</p>';    
            $output.= '<p>Found orphan media files: '. $b_counter . '</p>'; 
            $output.= '</div>';   
            
            $output.= '<h2><a name="missing_media" id="missing_media">Missing Media Files</a></h2>';
            $output.= '<div class="level1">';
            $output.= '<p>The following media files are referenced within pages but the media files do not exist at defined path:</p>';    
            $output.= '<table class="inline"><tr><th  class="col0 centeralign">i</th><th  class="col1 centeralign">#</th><th  class="col2 centeralign"> Page files </th><th  class="col3 centeralign"> missing Media </th></tr>';   
            $img = "q.png";
            if (count($missing_Media_Files)>=1)  $c2_output = case1_good_output($missing_Media_Files, $img);
            $output .= $c2_output;                  
            $output.= '</table></div>';   
            
            $output.= '<h2><a name="orphan_links" id="good_links">Orphan Media</a></h2><div class="level1">';
            $output.= '<p>The following orphan media files were detected:</p>';    
            $output.= '<table class="inline"><tr><th  class="col0 centeralign">i</th><th  class="col1 centeralign">#</th><th  class="col2 centeralign"> orphan Media </th><th  class="col3 centeralign"> Preview </th></tr>';   
            $img = "nok.png";
            if (count($orphan_Media_Files)>=1)  $c1_output = case1_good_output($orphan_Media_Files, $img);
            $output .= $c1_output;                  
            $output.= '</table></div>';   
            break;
        
        case 'summary':    
            $output.= '<p>Media list contains: '. $count_medialist_array . " files" . '</p>';    
            $output.= '<p>Page list contains: '. $page_counter . " files" . '</p>';    
            $output.= '<p>Found  media filename references: '. $a_counter . " in " . count($listPageFiles) . ' pages</p>';    
            $output.= '<p>Different media links extracted from pages: '. $tmp_link_counter . '</p>';    
            $output.= '<p>Missing media files detected: '. $e_counter . '</p>';    
            $output.= '<p>Found orphan media files: '. $b_counter . '</p>';    
            break;

        case 'missing':
            $output.= '<table class="inline"><tr><th  class="col0 centeralign">i</th><th  class="col1 centeralign">#</th><th  class="col2 centeralign"> Page files </th><th  class="col3 centeralign"> missing Media </th></tr>';   
            $img = "q.png";
            if (count($missing_Media_Files)>=1)  $c2_output = case1_good_output($missing_Media_Files, $img);
            $output .= $c2_output;                  
            break;
    
        case 'orphan':
            $output.= '<table class="inline"><tr><th  class="col0 centeralign">i</th><th  class="col1 centeralign">#</th><th  class="col2 centeralign"> orphan Media </th><th  class="col3 centeralign"> Preview </th></tr>';   
            $img = "nok.png";
            if (count($orphan_Media_Files)>=1)  $c1_output = case1_good_output($orphan_Media_Files, $img);
            $output .= $c1_output;                  
            break;
  }    	
        foreach($mediareferences as $link) {
            echo sprintf("<p><b>%s</b></p>\n", printf($link));
        }
        //for valid html = need to reopen a <p>
      	$output .= '<p>'; 
        return $output;
    }
//---------------------------------------------------------------------------------------
    // search given directory recursively and store all files into array 
    function list_files_in_array($dir, $delim, $excludes) 
    { 
        $max_count_files = 10;
        $listDir = array(); 
        if($handler = opendir($dir)) { 
            while (FALSE !== ($sub = readdir($handler))) { 
                if ($sub !== "." && $sub !== "..") { 
                    if(is_file($dir."/".$sub)) {   
                        $x = strpos(basename($dir."/".$sub),".txt");                        
                        if(($delim === '.txt') && ($x > 0)){
                            $listDir[] = $dir."/".$sub; 
                          }            
                        elseif($delim === 'all') {
                            $listDir[] = $dir."/".$sub;
                        } 
                        //if(DEBUG) echo sprintf("<p><b>%s</b></p>\n", $dir."/".$sub);
                    }
                    elseif(is_dir($dir."/".$sub)) { 
                        $listDir[$sub] = $this->list_files_in_array($dir."/".$sub, $delim,$excludes);
                        //if(DEBUG) echo sprintf("<p><b>%s</b></p>\n", $dir."/".$sub);
                    } 
                } 
            }    
            closedir($handler); 
        } 
        return $listDir;    
    }
//---------------------------------------------------------------------------------------
}
//Setup VIM: ex: et ts=4 enc=utf-8 :
?>