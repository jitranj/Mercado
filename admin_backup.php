<?php
// admin_backup.php
include 'db_connect.php';
// Check role if needed: if($_SESSION['role'] !== 'admin') die("Access Denied");

$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) $tables[] = $row[0];

$sql_script = "";
foreach ($tables as $table) {
    $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch_row();
    $sql_script .= "\n\n" . $row2[1] . ";\n\n";
    $result = $conn->query("SELECT * FROM $table");
    $num_fields = $result->field_count;
    
    while ($row = $result->fetch_row()) {
        $sql_script .= "INSERT INTO $table VALUES(";
        for ($j=0; $j<$num_fields; $j++) {
            $row[$j] = $conn->real_escape_string($row[$j]);
            $sql_script .= (isset($row[$j])) ? '"'.$row[$j].'"' : '""';
            if ($j < ($num_fields-1)) $sql_script .= ',';
        }
        $sql_script .= ");\n";
    }
}

$backup_name = 'mall_backup_' . date("Y-m-d_H-i") . '.sql';
header('Content-Type: application/octet-stream');
header("Content-Disposition: attachment; filename=\"$backup_name\"");
echo $sql_script;
?>