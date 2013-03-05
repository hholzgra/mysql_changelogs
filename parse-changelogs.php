#! /usr/bin/php
<?php

function get_one_value($query)
{
  $result = mysql_query($query) or die(mysql_error());
  $row = mysql_fetch_row($result);
  mysql_free_result($result);
  
  if (is_array($row) && isset($row[0])) {
    return $row[0];
  }

  return false;
}

function get_dom($file)
{
  $dom = new DOMDocument;

  $dom->preserveWhiteSpace = false;
  
  @$dom->LoadHTMLFile($file);

  return $dom;
}


/* Get page <title> text, version and release info is in there */

function get_title($xpath)
{
  $entries = $xpath->query("//title");

  if ($entries->length == 0) {
    return "";
  } 

  return $entries->item(0)->textContent;
}

/* Extract release date from page title,
   special cases are "Not released" and
   "Not yet released"
*/

function get_release_date($title)
{
  $regex = '|\((\d{4}-\d{2}-\d{2})|';

  if (preg_match($regex, $title, $matches)) {
    return $matches[1];
  }

  if (strstr($title, "Not released")) {
    return "Not released";
  }

  if (strstr($title, "Not yet released")) {
    return "Not yet released";
  }

  return "???";
}

/* The first release of a new release state
   (alpha, beta, milestone, GA) usually 
   gets some extra text behind the release
   date
*/

function get_release_state($title)
{
  $regex = '|\(\d{4}-\d{2}-\d{2},\s+(.*)\)|';

  if (preg_match($regex, $title, $matches)) {
    return $matches[1];
  }

  return "";
}


/* Get version number from page title */

function get_release_version($title)
{
  $regex = '|(\d+).(\d+).(\d+)(\S*)|';

  if (preg_match($regex, $title, $matches)) {
    $matches[0] = "$matches[1].$matches[2].$matches[3]";
    if (!isset($matches[4])) $matches[4] = "";
    $matches[0].= $matches[4];
    return $matches;
  }
  
  return array("?.?.?", 0, 0, 0, "");
}


/* main code starts here */


/* connect to database (TODO: make configurable) */

mysql_connect("127.0.0.1", "root", "");
mysql_select_db("test") or die(mysql_error());;

/* 
   Empty tables for reimport 

   Tables need to be installed from schema.sql
*/

foreach (array("version","entry","entry_bug","bug") as $table) {
  mysql_query("TRUNCATE TABLE $table") or die(mysql_error());
}

/* Go over all files in the HTML directory

   The fetch-html.sh shell script needs to be 
   used to download all change log pages into
   that directory beforehand ...
*/

$files = glob("html/news*.html");
usort($files, "version_compare");

foreach($files as $file) {

  /* create DOM and Xpath obhects for the page */
  $dom = get_dom($file);
  $xpath = new DOMXPath($dom);

  /* extract release information from page title */
  $title = get_title($xpath);
  $release_date = get_release_date($title);
  $release_state = get_release_state($title);
  list($version_string, $major_version, $minor_version, $patch_version, $extra_version) = get_release_version($title);

  echo "Parsing $version_string "; flush();

  /* store version release information */
  $query = "INSERT INTO version 
               SET product = 'MySQL'
                 , name = '$version_string'
                 , major = $major_version
                 , minor = $minor_version
                 , patch = $patch_version
                 , extra = '$extra_version'
                 , released = '$release_date'
                 , state = '$release_state'
                 ;
           ";
  mysql_query($query) or die(mysql_error());

  /* get auto increment ID of inserted version row */
  /* to be used as FK reference for entry records later */
  $version_id = mysql_insert_id();

  /* now we start to operate on the changelog DOM tree */
 
  /* we are looking for top level items only, top level
     item lists have 'type=disc', nested lists have
     'type=circle' ...
  */
  $query = "//ul[@type='disc']/li[@class='listitem']";
  $entries = $xpath->query($query);

  /* we keep track of the item section we're in ... */
  $section = "none";

  /* processing all found items */
  foreach ($entries as $entry) {
    echo "."; flush();
    
    /* Check for the section heading ...
       parent node is the <ul> list section, grandparent is a <div>
       before that diff is some whitespace, and before that is the 
       paragraph containing the heading like "Bugs Fixed" or
       "Functionality added or changed"
    */
    if (@$entry->parentNode->parentNode->previousSibling->previousSibling->tagName === "p") {
      $section = $entry->parentNode->parentNode->previousSibling->previousSibling->textContent;
      $section = preg_replace("|\s+|", " ", $section);
      $section = mysql_escape_string($section);
    }

    /* we are going to store both pure text and HTML markup
       version of the list item */
    $plain_text = mysql_escape_string($entry->textContent);
    $html_text = mysql_escape_string($entry->C14N());

    /* store changelog entry */
    $query = "INSERT INTO entry
                 SET version_id = $version_id
                   , plain_text = '$plain_text'
                   , html_text = '$html_text'
                   , section = '$section'
             ";    
    mysql_query($query) or die(mysql_error());
    $entry_id = mysql_insert_id();

    /* extract bug references */
    preg_match_all('|bug\s*#\s*(\d+)|i', $plain_text, $matches, PREG_SET_ORDER);

    $bug_numbers = array();
    foreach ($matches as $match) {
      $bug_numbers[$match[1]] = $match[1];
    }

    foreach ($bug_numbers as $number) {
      $system = $number > 1000000 ? "Oracle" : "MySQL";
      
      $bug_id = get_one_value("SELECT id FROM bug WHERE bug_system='$system' AND bug_number=$number");

      if (!$bug_id) {
	if ($system == 'MySQL') {
	  $bug_dom = get_dom("http://bugs.mysql.com/$number");
	  $bug_xpath = new DOMXPath($dom);
	  $bug_title = mysql_escape_string(get_title($xpath));

	  unset($bug_xpath);
	  unset($bug_dom);	  
	}

	$query = "INSERT INTO bug 
                     SET bug_system = '$system'
                       , bug_number = $number
                       , synopsis = '$bug_title'
                 ";
	mysql_query($query) or die(mysql_error());
	$bug_id = mysql_insert_id();
      }

      $query = "INSERT INTO entry_bug 
                   SET entry_id = $entry_id
                     , bug_id = $bug_id
               ";
      mysql_query($query) or die(mysql_error());
    }  
    
  }
  echo "\n";

  unset($xpath);
  unset($dom);
}

