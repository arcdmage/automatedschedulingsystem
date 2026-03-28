<?php
header("Content-Type: text/csv; charset=UTF-8");
header('Content-Disposition: attachment; filename="faculty_import_template.csv"');

$output = fopen("php://output", "w");
fputcsv($output, [
    "first_name",
    "middle_name",
    "last_name",
    "gender",
    "phone_number",
    "address",
    "status",
]);
fputcsv($output, [
    "Juan",
    "Dela",
    "Cruz",
    "male",
    "00000000000",
    "Manila, Philippines",
    "Unknown",
]);
fputcsv($output, [
    "Maria",
    "Santos",
    "Reyes",
    "female",
    "00000000000",
    "Quezon City, Philippines",
    "Unknown",
]);
fclose($output);
exit();
?>
