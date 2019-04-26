#! /usr/bin/php
<?php

$exitcount = 0;

include "config.php";

define("BUG_CACHE", "cache/bug-html");
define("MANUAL_CACHE", "cache/manual-html");

function get_one_value($db, $query)
{
    $result = $db->query($query) or die($db->error);
    $row = $result->fetch_array();
    $result->close();

    if (is_array($row) && isset($row[0])) {
        return $row[0];
    }

    return false;
}

function get_dom($file)
{
    $dom = new DOMDocument("1.0", "utf8");

    $dom->preserveWhiteSpace = false;

    @$dom->LoadHTMLFile($file);

    return $dom;
}

function get_bug_dom($id) 
{
    $cacheFile = BUG_CACHE."/bug_$id.html";

    if (!file_exists($cacheFile)) {
        copy("http://bugs.mysql.com/$id", $cacheFile);
    }

    return get_dom($cacheFile);
}

function DOMinnerText(DOMNode $element) 
{ 
    $innerText = ""; 
    $children  = $element->childNodes;

    foreach ($children as $child) 
    { 
        $innerText .= $child->textContent;
    }
    
    return $innerText; 
} 

function DOMinnerHTML(DOMNode $element) 
{ 
    $innerHTML = ""; 
    $children  = $element->childNodes;

    foreach ($children as $child) 
    { 
        $innerHTML .= $child->C14N();
    }

    return $innerHTML; 
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


function section_lookup($db, $name)
{
    $id = get_one_value($db, "SELECT id FROM section WHERE name ='".$db->real_escape_string($name)."'");

    if (!$id) {
        $db->query("INSERT INTO section SET name = '".$db->real_escape_string($name)."'");
        $id = $db->insert_id;
    }

    return $id;
}

function subsections_lookup($db, $names)
{
    $ids = array();

    foreach(explode(";", $names) as $name) {
        $name = trim($name);
        if ($name === "") continue;
        
        $id = get_one_value($db, "SELECT id FROM subsection WHERE name ='".$db->escape_string($name)."'");

        if (!$id) {
            $db->query("INSERT INTO subsection SET name = '".$db->escape_string($name)."'");
            $id = $db->insert_id;
        }

        $ids[] = $id;
    }

    return $ids;
}

/* main code starts here */

if (!file_exists(BUG_CACHE)) {
    mkdir(BUG_CACHE);
}

if (!file_exists(BUG_CACHE)) {
    mkdir(BUG_CACHE);
}

/* connect to database */

$db = mysqli_init();
if (!$db) {
    die('mysqli_init failed');
}

$db = mysqli_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWD, MYSQL_DB);
if (!$db) {
    die('Connect Error (' . mysqli_connect_errno() . ') '
    . mysqli_connect_error());
}

$db->query("SET NAMES UTF8");

if ($argc == 2 && $argv[1] == "--replace") {
    $replace = true;
}

if ($replace) {
    /* 
       Empty tables for reimport 

       Tables need to be installed from schema.sql
    */

    foreach (array("version","entry","entry_bug","bug","section","subsection","entry_section","entry_subsection") as $table) {
        $db->query("TRUNCATE TABLE $table") or die($db->error);
    }
}


/* Go over all files in the HTML directory

   The fetch-html.sh shell script needs to be 
   used to download all change log pages into
   that directory beforehand ...
*/

$files = glob(MANUAL_CACHE."/news-*.html");

usort($files, "version_compare");

$db->query("BEGIN");

foreach($files as $file) {
    parse_release_file($db, $file, $replace);
}

$db->query("COMMIT");








function parse_release_file($db, $file, $replace = false) {
    echo "Checking $file "; flush();

    /* create DOM and Xpath objects for the page */
    $dom = get_dom($file);
    $xpath = new DOMXPath($dom);

    /* extract release information from page title */
    $title = get_title($xpath);
    $release_date = get_release_date($title);
    $release_state = get_release_state($title);
    list($version_string, $major_version, $minor_version, $patch_version, $extra_version) = get_release_version($title);

    echo " $version_string "; flush();

    $version_id = get_one_value($db, "SELECT id FROM version WHERE name = '$version_string'");
    
    if ($version_id)
    {
        if ($replace) {
            echo "(purging ...) "; flush();
            $db->query("DELETE FROM entry WHERE version_id = $version_id");
            $db->query("DELETE FROM version WHERE id = $version_id");
        } else {
            echo "already in database \n"; flush();
            return;
        }
    }

    echo "Parsing "; flush();

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
    $db->query($query) or die($db->error);

    /* get auto increment ID of inserted version row */
    /* to be used as FK reference for entry records later */
    $version_id = $db->insert_id;

    /* now we start to operate on the changelog DOM tree */
 
    /* we are looking for top level items only, top level
       item lists have 'type=disc', nested lists have
       'type=circle' ...
    */
    if ($major_version >= 5) {
        $query = "//div[@class='section']/div[@class='simplesect']/div[@class='itemizedlist']/ul[@class='itemizedlist']/li[@class='listitem']";
    } else {
        $query = "//div[@class='section']/div[@class='itemizedlist']/ul[@class='itemizedlist']/li[@class='listitem']";
    }
    $entries = $xpath->query($query);

    /* we keep track of the item section we're in ... */
    $section = "";

    /* processing all found items */
    foreach ($entries as $entry) {
        echo "."; flush();
    
        /* Check for the section heading ...
           parent node is the <ul> list section, grandparent is a <div>
           before that diff is some whitespace, and before that is the 
           paragraph containing the heading like "Bugs Fixed" or
           "Functionality added or changed"
        */
        $section_id = "NULL";
        if ($major_version >= 5) {
            $section = @$entry->parentNode->parentNode->parentNode->firstChild->nextSibling->textContent;
            $section = trim(preg_replace("|\s+|", " ", $section));
            $section_id = section_lookup($db, $section);
        } else {
            if (@$entry->parentNode->parentNode->previousSibling->previousSibling->firstChild->firstChild->tagName === "strong") {
                $section = $entry->parentNode->parentNode->previousSibling->previousSibling->firstChild->firstChild->textContent;
                $section = trim(preg_replace("|\s+|", " ", $section));
                $section_id = section_lookup($section);
            }
        } 
        
        /* we are going to store both pure text and HTML markup
           version of the list item */

        $plain_text = $db->real_escape_string(DOMinnerText($entry));
        $html_text = $db->real_escape_string(DOMinnerHTML($entry));

        /* check for subsection at start of text */
        $result = $xpath->query("./p/span[@class='bold']", $entry);
        if ($result->length) {
            $subsections = trim($result[0]->textContent);
            $subsections = trim(preg_replace('/:$/m', '', $subsections));
        } else {
            $subsections = "";
        }

        $subsection_ids = subsections_lookup($db, $subsections);

        /* store changelog entry */
        $query = "INSERT INTO entry
                     SET version_id = $version_id
                       , plain_text = '$plain_text'
                       , html_text = '$html_text'
             ";    
        $db->query($query) or die($db->error);
        $entry_id = $db->insert_id;

        $db->query("INSERT INTO entry_section 
                       SET entry_id=$entry_id
                         , section_id=$section_id
                   ");

        foreach($subsection_ids as $subsection_id) {
            $db->query("INSERT INTO entry_subsection 
                           SET entry_id=$entry_id
                             , subsection_id=$subsection_id"
                       );      
        }

        /* extract bug references */
        preg_match_all('|bug\s*#\s*(\d+)|mi', $plain_text, $matches, PREG_SET_ORDER);

        $bug_numbers = array();
        foreach ($matches as $match) {
            $bug_numbers[$match[1]] = $match[1];
        }

        foreach ($bug_numbers as $number) {
            $system = $number > 200000 ? "Oracle" : "MySQL";
      
            $bug_id = get_one_value($db, "SELECT id 
                                            FROM bug 
                                           WHERE bug_system='$system' 
                                             AND bug_number=$number
                                         ");

            if (!$bug_id) {
                if ($system == 'MySQL') {
                    $bug_dom = get_bug_dom($number);
                    $bug_xpath = new DOMXPath($bug_dom);
                    $bug_title = $db->real_escape_string(get_title($bug_xpath));

                    unset($bug_xpath);
                    unset($bug_dom);	  
                } else {
                    $bug_title = '';
                }

                $query = "INSERT INTO bug 
                             SET bug_system = '$system'
                               , bug_number = $number
                               , synopsis = '$bug_title'
                 ";
                $db->query($query) or die($db->error);
                $bug_id = $db->insert_id;
            }

            $query = "INSERT INTO entry_bug 
                         SET entry_id = $entry_id
                           , bug_id = $bug_id
               ";
            $db->query($query) or die($db->error);
        }  
    
    }
    echo "\n";

    unset($xpath);
    unset($dom);
}

