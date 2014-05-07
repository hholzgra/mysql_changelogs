<?php

header('Content-Type: text/html; charset=utf-8');

ini_set("display_errors", 1);

include "config.php";

mysql_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWD) or die("can't connect");
mysql_select_db(MYSQL_DB);
mysql_query("SET group_concat_max_len = 100000") or die(mysql_error());
mysql_query("SET NAMES UTF8");

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

function opt_values($version) 
{
  $query = "SELECT * FROM version ORDER BY major DESC, minor DESC, patch DESC";

  $res = mysql_query($query) or die(mysql_error());

  echo "<option value='0'/>";
  while ($row = mysql_fetch_assoc($res)) {
    echo "<option value='$row[id]'";
    if (@$_GET[$version] == $row['id']) echo " selected ";
    echo ">$row[major].$row[minor].$row[patch]$row[extra]";
    echo "</option>\n";
  }
}

function buglink($matches) 
{
  if ($matches[1] < 200000) {
    $title = get_one_value("SELECT synopsis FROM bug WHERE bug_number = $matches[1]");
    
    return "<a href='http://bugs.mysql.com/$matches[1]' title='$title'>$matches[0]</a>";
  }
  
  return $matches[0];
}

?>
<html>
<head>
<title>Change Logs</title>
<meta name="charset" value="UTF8"/>
</script>
</head>
<body>
List combined change log entries between two versions:<hr/>
<form>
Version 1: 
<select name="version1">
<?php opt_values("version1"); ?>
</select>
Version 2: 
<select name="version2">
<?php opt_values("version2"); ?>
</select>
<input type="submit" value="Compare"/>
</form>
<hr/> 
<em>All data (c) Oracle
<hr/>
<?php
if (isset($_GET["version1"]) && isset($_GET["version2"]))
{
$old_version_id = min($_GET["version1"], $_GET["version2"]);
$new_version_id = max($_GET["version1"], $_GET["version2"]);

$query = "SELECT v.name AS version
               , s.name AS section
               , e.html_text as html
            FROM version AS v
            JOIN entry   AS e
              ON v.id = e.version_id
            JOIN entry_section AS e_s
              ON e.id = e_s.entry_id
            JOIN section AS s
              ON e_s.section_id = s.id
           WHERE v.id BETWEEN $old_version_id + 1 AND $new_version_id
        ORDER BY s.name
               , v.major, v.minor, v.patch, v.extra
               , v.name
	       , e.id
          ";

$result = mysql_query($query) or die(mysql_error());

echo "<table border='0'>\n";
echo "<tr><th>Section</th><th>Version</th><th>Change</th></tr>";

$last_section = "";
$latst_version = "";

while ($row = mysql_fetch_assoc($result)) {
  echo "<tr valign='top'>";
  if ($row["section"] != $last_section) {
    echo "<td>$row[section]</td>";
    $last_section = $row["section"];
    $last_version = "";
  } else {
    echo "<td></td>";
  }
  if ($row["version"] != $last_version) {
    echo "<td>$row[version]</td>";
    $last_version = $row["version"];
  } else {
    echo "<td></td>";
  }
  echo "<td><ul>\n";
  echo preg_replace_callback('|Bug #(\d+)|', "buglink", $row['html'])."\n";
  echo "</ul></td></tr>\n";
}

echo "</table>";
}
?>
</body>
</html>
