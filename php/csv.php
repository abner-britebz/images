<?php
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 600);
date_default_timezone_set('America/Belize');

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;

// Logging function
$logFile = __DIR__ . '/php.log';
function logMessage($msg)
{
    global $logFile;
    file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

// PostgreSQL Connection
$pdo = new PDO(
    "pgsql:host=172.16.14.112;port=54321;dbname=cvs",
    "admin",
    "password",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
logMessage("Database connected");

// Read input JSON
$data = json_decode(file_get_contents('php://input'), true);
$imageColumn = $data['imageColumn'] ?? '';
$itemColumn  = $data['itemColumn'] ?? '';
$rowStart    = $data['rowStart'] ?? 2;
$fileName    = $data['fileName'] ?? 'upload.xlsx';
$fileData    = $data['fileData'] ?? '';

$tmpFile = sys_get_temp_dir() . '/' . $fileName;
file_put_contents($tmpFile, base64_decode($fileData));
logMessage("Temp file saved: $tmpFile");

// Ensure images folder exists
$imagesDir = __DIR__ . '/images';
if (!is_dir($imagesDir)) mkdir($imagesDir, 0755, true);

// Load spreadsheet (with images)
$reader = new Xlsx();
$reader->setReadDataOnly(false);
$spreadsheet = $reader->load($tmpFile);
$worksheet = $spreadsheet->getActiveSheet();
logMessage("Spreadsheet loaded, total drawings: " . count($worksheet->getDrawingCollection()));

// Map drawings by cell coordinate (support multiple images per cell)
$drawingsMap = [];
foreach ($worksheet->getDrawingCollection() as $drawing) {
    $coord = $drawing->getCoordinates();
    if (!isset($drawingsMap[$coord])) {
        $drawingsMap[$coord] = [];
    }
    $drawingsMap[$coord][] = $drawing; // append
logMessage("Drawings mapped: " . count($drawingsMap));

// Process rows
for ($row = $rowStart; $row <= $worksheet->getHighestRow(); $row++) {
    $cellRef = $imageColumn . $row;
    if (!isset($drawingsMap[$cellRef])) {
        logMessage("No drawing for $cellRef");
        continue;
    }

    foreach ($drawingsMap[$cellRef] as $index => $drawing) {
        try {
            // Get image contents
            if ($drawing instanceof MemoryDrawing) {
                ob_start();
                call_user_func($drawing->getRenderingFunction(), $drawing->getImageResource());
                $imageContents = ob_get_clean();
                $extension = $drawing->getMimeType() === 'image/png' ? 'png' : 'jpg';
            } else {
                $imageContents = file_get_contents($drawing->getPath());
                $extension = $drawing->getExtension();
            }

            // Save image (add index to filename to avoid overwriting)
            $filename = $cellRef . '_' . $index . '.' . $extension;
            $filePath = $imagesDir . '/' . $filename;
            file_put_contents($filePath, $imageContents);
            logMessage("Saved image for $cellRef -> $filePath");

            // Insert into DB
            $itemValue = $worksheet->getCell($itemColumn . $row)->getValue();
            $stmt = $pdo->prepare("INSERT INTO image (itemnumber, path) VALUES (:itemnumber, :path)");
            $stmt->execute([
                ':itemnumber' => $itemValue,
                ':path' => $filePath
            ]);
            logMessage("Inserted DB row for $cellRef ($itemValue)");
        } catch (\Exception $e) {
            logMessage("Error processing row $row drawing $index: " . $e->getMessage());
        }
    }
}

// Cleanup
unlink($tmpFile);
logMessage("Processing completed");

echo json_encode(['status' => 'done']);
