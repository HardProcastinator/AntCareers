<?php
require 'config.php';

$db = getDB();

$sql = "SELECT TABLE_NAME, COLUMN_NAME, COLLATION_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME IN ('users', 'seeker_profiles', 'seeker_skills', 'applications', 'job_invitations') 
        ORDER BY TABLE_NAME, COLUMN_NAME";

try {
    $stmt = $db->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "COLLATION ANALYSIS:\n";
    echo "==================\n\n";

    $collations = [];
    foreach ($results as $row) {
        $table = $row['TABLE_NAME'];
        $col = $row['COLUMN_NAME'];
        $collation = $row['COLLATION_NAME'];
        
        if (!isset($collations[$collation])) {
            $collations[$collation] = [];
        }
        $collations[$collation][] = "$table.$col";
        
        printf("%-25s %-20s %s\n", $table, $col, $collation);
    }

    echo "\n\nSUMMARY BY COLLATION:\n";
    echo "====================\n";
    foreach ($collations as $collation => $columns) {
        echo "\n$collation:\n";
        foreach ($columns as $col) {
            echo "  - $col\n";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
