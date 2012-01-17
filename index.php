<?php 
/**
*	 Version: 0.1.1
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
$format = $_GET['format'];

if(empty($format)){
	$format = 'json';
} 

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
	$covers = $html->find('img[src*=s'.$biblionetid .']');
	if(count($covers) >= 1){
		$cover_url = "http://biblionet.gr/images/covers/b" . $biblionetid . ".jpg";
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
		$yr_published = substr($matches[0], 1, 4);

	}
	
	if($format === 'json'){
		header('Content-type: application/json');
		$html = <<<JSON_RESPONSE
		{"biblionetid" : "$biblionetid",
		"cover_url" : "$cover_url",
		"title" : "$title[0]",
		"authors" : "$persons[0]", 
		"translators" : "$persons[1]",
		"publisher" : "$publisher[0]",
		"yr_published" : "$yr_published",
		"original_language" : "$original_language[1]",
		"original_title" : "$original_title[1]",
		"categories" : "$categories"}
JSON_RESPONSE;
	} else {
			$html = <<<HTML_RESPONSE
		<html>
		<head>
		<title>Metadata for book with isbn: $isbn</title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		</head>
		<body>
		<p id="biblionetid">$biblionetid</p>
		<p id="cover_url">$cover_url</p>
		<p id="title">$title[0]</p>
		<p id="authors">$persons[0]</p> 
		<p id="translators">$persons[1]</p>
		<p id="publisher">$publisher[0]</p>
		<p id="yr_published">$yr_published</p>
		<p id="original_language">$original_language[1]</p>
		<p id="original_title">$original_title[1]</p>
		<p id="categories">$categories</p>
		</body>
		</html>
HTML_RESPONSE;
	}

	echo $html;
} else {
	echo  "No valid isbn prοvided.";
}



function my_callback($element) {
	global $page;
	global $html;
	global $head;
	global $isbn;
 	global $format;
 	if($format === 'html'){
		$head = "<title>Metadata for isnbn  $isbn </title>";	
		// Replace Head 	
		if ($element->tag=='head'){
		          $element->innertext = $head;            
		} 	
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
	} else if($element->tag=='body'){
		$element->style = '';
	}
} 
?>