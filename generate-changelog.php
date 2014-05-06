#! /usr/bin/php
<?php

include "config.php";

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

mysql_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWD);
mysql_select_db(MYSQL_DB) or die(mysql_error());;

mysql_query("SET group_concat_max_len = 10000") or die(mysql_error());


$old_version = "5.5.20";
$new_version = "5.5.30";

$title = "Changes between MySQL $old_version and $new_version";

echo "<html>\n";
echo "<head><title>$title</title></head>\n";
echo "<body>\n<h1>$title</h1>";

echo "<table border='0'>\n";
echo "<tr><th>Section</th><th>Version</th><th>Change</th></tr>";

$old_version_id = get_one_value("SELECT id FROM version WHERE name='$old_version'");
if (!$old_version_id) die("invalid version $old_version");

$new_version_id = get_one_value("SELECT id FROM version WHERE name='$new_version'");
if (!$new_version_id) die("invalid version $new_version");

$query = "SELECT v.name AS version
               , e.section
               , GROUP_CONCAT(e.html_text ORDER BY e.id SEPARATOR '\n') AS html
            FROM version AS v
            JOIN entry   AS e
              ON v.id = e.version_id
           WHERE v.id BETWEEN $old_version_id AND $new_version_id
        GROUP BY e.section
               , v.major, v.minor, v.patch, v.extra
               , v.name
          ";
$result = mysql_query($query) or die(mysql_error());

while ($row = mysql_fetch_assoc($result)) {
  echo "<tr valign='top'><td>$row[section]</td><td>$row[version]</td><td><ul>$row[html]</ul></td></tr>\n";
}

echo "</table>";

echo "</body>";


 
