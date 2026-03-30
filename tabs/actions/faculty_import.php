<?php
session_start();
require_once __DIR__ . "/../../db_connect.php";

header("Content-Type: application/json");

function import_respond(bool $success, string $message, array $extra = []): void
{
    echo json_encode(
        array_merge(
            [
                "success" => $success,
                "message" => $message,
            ],
            $extra,
        ),
    );
    exit();
}

function import_normalize_header(string $value): string
{
    $value = preg_replace('/^\xEF\xBB\xBF/', "", $value) ?? $value;
    $value = trim(strtolower($value));
    return str_replace([" ", "-"], "_", $value);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    import_respond(false, "Invalid request method.");
}

if (!isset($_FILES["faculty_file"]) || !is_array($_FILES["faculty_file"])) {
    import_respond(false, "Please choose a CSV file to import.");
}

$file = $_FILES["faculty_file"];
if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    import_respond(false, "The uploaded file could not be processed.");
}

$tmpName = $file["tmp_name"] ?? "";
if ($tmpName === "" || !is_uploaded_file($tmpName)) {
    import_respond(false, "Invalid uploaded file.");
}

$handle = fopen($tmpName, "r");
if ($handle === false) {
    import_respond(false, "Failed to open uploaded file.");
}

$header = fgetcsv($handle);
if (!is_array($header) || $header === []) {
    fclose($handle);
    import_respond(false, "The CSV file is empty.");
}

$normalizedHeader = array_map("import_normalize_header", $header);
$expectedHeader = [
    "first_name",
    "middle_name",
    "last_name",
    "gender",
    "phone_number",
    "address",
    "status",
];

if ($normalizedHeader !== $expectedHeader) {
    fclose($handle);
    import_respond(
        false,
        "Invalid template. Use the faculty import template and keep the header row unchanged.",
    );
}

$duplicateStmt = $conn->prepare(
    "SELECT faculty_id
     FROM faculty
     WHERE LOWER(TRIM(fname)) = LOWER(TRIM(?))
       AND LOWER(TRIM(mname)) = LOWER(TRIM(?))
       AND LOWER(TRIM(lname)) = LOWER(TRIM(?))
     LIMIT 1",
);

$insertStmt = $conn->prepare(
    "INSERT INTO faculty (fname, mname, lname, gender, pnumber, address, status)
     VALUES (?, ?, ?, ?, ?, ?, ?)",
);

if (!$duplicateStmt || !$insertStmt) {
    fclose($handle);
    import_respond(false, "Database error while preparing import statements.");
}

$imported = 0;
$skipped = 0;
$errors = [];
$rowNumber = 1;

while (($row = fgetcsv($handle)) !== false) {
    $rowNumber++;

    if (
        count(
            array_filter(
                $row,
                static fn($value) => trim((string) $value) !== "",
            ),
        ) === 0
    ) {
        continue;
    }

    $row = array_pad($row, count($expectedHeader), "");
    $data = array_combine(
        $expectedHeader,
        array_map(static fn($value) => trim((string) $value), $row),
    );

    if ($data === false) {
        $errors[] = "Row {$rowNumber}: Invalid column mapping.";
        continue;
    }

    $fname = $data["first_name"];
    $mname = $data["middle_name"];
    $lname = $data["last_name"];
    $gender = strtolower($data["gender"]);
    $pnumber = preg_replace("/\D+/", "", $data["phone_number"]);
    $address = $data["address"];
    $status = $data["status"];

    if ($pnumber === "") {
        $pnumber = "00000000000";
    }

    if ($status === "") {
        $status = "Unknown";
    }

    if ($fname === "" || $lname === "") {
        $errors[] = "Row {$rowNumber}: First name and last name are required.";
        continue;
    }

    if (
        $gender !== "" &&
        !in_array($gender, ["female", "male", "other"], true)
    ) {
        $errors[] = "Row {$rowNumber}: Gender must be female, male, or other.";
        continue;
    }

    $duplicateStmt->bind_param("sss", $fname, $mname, $lname);
    $duplicateStmt->execute();
    $duplicateResult = $duplicateStmt->get_result();
    if ($duplicateResult && $duplicateResult->num_rows > 0) {
        $skipped++;
        $errors[] = "Row {$rowNumber}: Duplicate faculty name already exists.";
        continue;
    }

    $insertStmt->bind_param(
        "sssssss",
        $fname,
        $mname,
        $lname,
        $gender,
        $pnumber,
        $address,
        $status,
    );

    if ($insertStmt->execute()) {
        $imported++;
    } else {
        $errors[] = "Row {$rowNumber}: Failed to insert faculty.";
    }
}

fclose($handle);
$duplicateStmt->close();
$insertStmt->close();

$countResult = $conn->query("SELECT COUNT(*) AS total FROM faculty");
$totalRecords = $countResult
    ? (int) ($countResult->fetch_assoc()["total"] ?? 0)
    : 0;

$message = "Faculty import complete. Imported {$imported}, skipped {$skipped}.";
if ($errors !== []) {
    $message .= " " . count($errors) . " row(s) failed.";
}

import_respond(true, $message, [
    "imported" => $imported,
    "skipped" => $skipped,
    "errors" => $errors,
    "total_records" => $totalRecords,
]);
?>
