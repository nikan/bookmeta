<?php 
/**
*	 Version: 0.1
*  Author: Nikos Anagnostou (http://github.com/nikan)
*	 Acknowledge: S.C. Chen (http://sourceforge.net/projects/simplehtmldom/),
*								Keith Nunn (http://www.phpkode.com/scripts/item/isbn-check/)
*	 Licensed under The GNU General Licence v 2.0
*	 Redistributions of files must retain the above copyright notice.
*
*  DESCRIPTION:
*  Retrieves book metadata from biblionet.gr based on isbn search and returns them as a json object
*  Query for the data at the endpoint: http://<yourserver>/index.php?isbn=<an isbn>
*/
require_once('isbn.test.php');
require_once('simple_html_dom.php');

$isbn = $_GET['isbn'];

$checkisbn = new ISBNtest;
$checkisbn->set_isbn($isbn);
if($checkisbn->valid_isbn10() === TRUE || $checkisbn->valid_isbn13() === TRUE){
	
	// Website url to open
	$page = "http://biblionet.gr/main.asp?page=results&isbn=" . $isbn;
	
	// Get that website's content
	$html = file_get_html($page);
	$html->set_callback('my_callback');
	
	//Remove tables except the one with the book data
	$table = $html->find('table');
	$size = count($table);
	$table[0]->outertext='';
	$table[1]->outertext='';
	$table[2]->outertext='';
	$table[($size - 1)]->outertext='';
	$cell = $table[3]->find('tbody tr td[width=200]');
	$cell[0]->outertext = "";
	$row = $table[3]->find('tr');
	$size = count($row);
	$row[$size -1]->outertext = "";
  $table = $html->find('table[width=780]');
	$table[0]->outertext = "";
	
	//Preparing data for json output
	$cover_url = $html->find('img');
	$cover_url = "http://biblionet.gr/". $cover_url[0]->src;
	$title = '';
	$subtitle = '';
	$authors= '';
	$publisher= '';
	$yr_published= '';
	$original_language= '';
	$original_title= '';
	$categories = '';
	
	//Get all a links
	//first category links
	$a = $html->find('a[class=subjectlink]');
	$categories = $a[0]->innertext;
	//then booklinks
	$a = $html->find('a[class=booklink]');	
	foreach($a as $_a){
		$idpos = strpos($_a->href, 'bookid=');
		$personpos = strpos($_a->href, 'personsid=');
		$compos = strpos($_a->href, 'comid=');
		
		if($idpos){ //if it is a number, there is a string, else it is false
			$biblionetid = substr($_a->href, $idpos + 7 );
			$title[] = $_a->innertext;
		} else if($personpos){
			$persons[] = $_a->innertext;
		} else if($compos){
			$publisher[] = $_a->innertext;
		} 
	}
	$other = $html->find('span[class=small]');
	$td = $other[0]->parent();
	$other = explode('<br>', $other[0]->innertext);
	foreach($other as $detail){
	$original_language_pos = mb_strpos($detail, 'Γλώσσα');
	$original_title_pos = mb_strpos($detail, 'Τίτλος');
		if($original_language_pos !== false){
			$original_language = explode(':', $detail);
		}
		if($original_title_pos !== false){
			$original_title = explode(':', $detail);
		}
	}
	
	$txt = $td->find('text');
	$txt = implode($txt);
	$pattern = "/\s\d\d\d\d\./";
	$result = preg_match($pattern, $txt, $matches);
	if($result === false){
		$yr_published = "An error occured";
	} else if($result === 0){
		$yr_published = "No publishing year found";		
	} else {
		$yr_published = substr($matches[0], 1, 5);

	}
	
	$html = <<<JSON_RESPONSE
	{"biblionetid" : "$biblionetid",
	"cover_url" : "$cover_url",
	"title" : "$title[0]",
	"subtitle" : "$subtitle",
	"authors" : "$persons[0]", 
	"translators" : "$persons[1]",
	"publisher" : "$publisher[0]",
	"yr_published" : "$yr_published",
	"original_language" : "$original_language[1]",
	"original_title" : "$original_title[1]",
	"categories" : "$categories"}
JSON_RESPONSE;

	echo $html;
} else {
	echo  "No valid isbn prοvided.";
}



function my_callback($element) {
	global $page;
	global $html;
	global $head;
	global $isbn;
 
	$head = <<<HEAD
  <title>Metadata for isnbn  $isbn </title>
  <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
HEAD;


	// Replace Head 	
	if ($element->tag=='head'){
	          $element->innertext = $head;            
	}
	//Remove scripts
	else if ($element->tag=='script'){
		$element->outertext = '';	
	}
	//Remove map
	else if ($element->tag=='map'){
		$element->outertext = '';	
	}	else if ($element->tag=='title'){
		$element->outertext = '';	
	}	else if ($element->tag=='img'){
		$element->src = "http://biblionet.gr". $element->src;	
	}	else if($element->tag=='img'){
		$element->style = '';
	} else if($element->tag=='body'){
		$element->style = '';
	}
} 
?>