<?php

header('Content-Type: text/html; charset=utf-8');

ini_set("display_errors", 1);

include "config.php";

require_once "vendor/autoload.php";

require_once "generate-changelog.php";

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

$t_loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($t_loader, [
    'cache' => false // 'cache/twig_compilation_cache',
]);

$twig_params = [];

$sections    = $_REQUEST['sections'] ?? [];
$topics      = $_REQUEST['topics']   ?? [];

$old_version = $_REQUEST['version1'] ?? latest_version($db);
$new_version = $_REQUEST['version2'] ?? latest_version($db, 1);

if ( 1 == version_compare($old_version, $new_version)) {
    $swap = $old_version;
    $old_version = $new_version;
    $new_version = $swap;
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $twig_params['rows'] = generate_changelog($db, $old_version, $new_version, $sections, $topics);
}

$twig_params['version1'] = version_items($db, $twig, "version1", $old_version);
$twig_params['version2'] = version_items($db, $twig, "version2", $new_version);
$twig_params['sections'] = section_items($db, $twig, "sections", $sections);
$twig_params['topics']   = topic_items(  $db, $twig, "topics",   $topics);


echo $twig->render('changes.html', $twig_params);

// phpinfo(INFO_VARIABLES);




function version_items($db, $twig, $name, $version) 
{
    $query = "SELECT CONCAT(major,'.',minor) AS series
                   , CONCAT(major,'.',minor,'.',patch,extra) AS version
                FROM version 
               WHERE released > '1980-01-01'
            ORDER BY major DESC
                   , minor DESC
                   , patch DESC
              ";

    $result = $db->query($query) or die($db->error);

    $options = [];
  
    while ($row = $result->fetch_assoc()) {
        $val = $row['version'];
        $options[] = [ 'id' => $val, 'series' => $row['series'], 'name' => $val ];
    }

    $result->close();
  
    return $twig->render('version-select.html', [ 'name' => $name, 'options' => $options, 'selected' => $version ]);
}

function latest_version($db, $offset = 0)
{
    $query = "SELECT CONCAT(major,'.',minor,'.',patch,extra) AS version
              FROM version 
             WHERE released > '1980-01-01'
          ORDER BY major DESC
                 , minor DESC
                 , patch DESC
             LIMIT $offset, 1
             ";
    
    return get_one_value($db, $query);
}

function section_items($db, $twig, $name, $sections) 
{
    $query = "SELECT s.id
                 , s.name 
              FROM section s
              JOIN entry_section e
                ON e.section_id = s.id
          GROUP BY s.id, s.name
          ORDER BY s.name";

    $result = $db->query($query) or die($db->error);

    $options = [];
  
    while ($row = $result->fetch_assoc()) {
        $options[] = $row;
    }

    $result->close();
  
    return $twig->render('section-select.html', [ 'name' => $name, 'options' => $options, 'selected' => $sections ]);
}

function topic_items($db, $twig, $name, $topics) 
{
    $query = "SELECT s.id, s.name 
              FROM subsection s
              JOIN entry_subsection e
                ON e.subsection_id = s.id
          GROUP BY s.id, s.name
          ORDER BY s.name";

    $result = $db->query($query) or die($db->error);

    $options = [];
    
    while ($row = $result->fetch_assoc()) {
        $options[] = $row;
    }
    
    $result->close();
    
    return $twig->render('section-select.html', [ 'name' => $name, 'options' => $options, 'selected' => $topics ]);
}

