<?php

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

function createBugLink($matches)
{
    global $db;
    
    if (strlen($matches[1]) <= 6) {
        $query = "SELECT synopsis FROM bug WHERE bug_number=$matches[1]";
        $synopsis = get_one_value($db, $query);
        
        return sprintf('<a class="btn btn-outline-primary btn-sm" href="http://bugs.mysql.com/%d" title="%s" target="_blank">%s</a>',
        $matches[1], $synopsis, $matches[0]);
    } else {
        return "<button class='btn btn-outline-dark btn-sm disabled' title='Internal Oracle Bug, no public access'>$matches[0]</button>";
    }
}

function generate_changelog($db, $old_version, $new_version, $sections, $topics)
{
    $title = "Changes between MySQL $old_version and $new_version";

    $old_version_id = get_one_value($db, "SELECT id FROM version WHERE name='$old_version'");
    if (!$old_version_id) die("invalid version $old_version");

    $new_version_id = get_one_value($db, "SELECT id FROM version WHERE name='$new_version'");
    if (!$new_version_id) die("invalid version $new_version");

    $where = "v.id BETWEEN $old_version_id +1 AND $new_version_id";

    if (is_array($sections) && count($sections) > 0) {
        $where .= " AND s.id IN (" . join(',', $sections). ") ";
    }

    if (is_array($topics) && count($topics) > 0) {
        $where .= " AND ss.id IN (" . join(',', $topics). ") ";
    }

    $query = "SELECT v.name AS version
               , s.name AS section
               , GROUP_CONCAT(ss.name ORDER BY ss.name SEPARATOR '; ') AS topic
               , e.html_text AS html
            FROM version AS v
            JOIN entry   AS e
              ON v.id = e.version_id
            JOIN entry_section AS e_s
              ON e_s.entry_id = e.id
            JOIN section AS s
              ON e_s.section_id = s.id
       LEFT JOIN entry_subsection AS e_ss
              ON e_ss.entry_id = e.id
       LEFT JOIN subsection AS ss
              ON e_ss.subsection_id = ss.id
           WHERE $where
        GROUP BY e.id
        ORDER BY s.name, topic, v.major, v.minor, v.patch, v.extra
          ";

    echo "<!-- \n$query;\n -->";


    $result = $db->query($query) or die($db->error);

    $rows = [];

    while ($row = $result->fetch_assoc()) {
        if ( empty($row['topic'])) $row['topic'] = 'Misc.';
    
        $row['html'] = preg_replace_callback('/Bug\s*#(\d+)/', "createBugLink", $row['html']);
        $row['html'] = preg_replace('|href=(.)/doc/refman/|', 'href=$1https://dev.mysql.com/doc/refman/', $row['html']);
    
        $rows[] = $row;
    }

    $result->close();

    return $rows;
}
 

