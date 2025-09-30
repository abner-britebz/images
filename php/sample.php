<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;

$inputFile = "example.xlsx";
$spreadsheet = IOFactory::load($inputFile);

$worksheet = $spreadsheet->getActiveSheet();

// Define the row range you want to scan
$startRow = 2;
$endRow   = 5000;

// Build a lookup map: [cell => drawing]
$drawingsMap = [];
foreach ($worksheet->getDrawingCollection() as $drawing) {
    $drawingsMap[$drawing->getCoordinates()] = $drawing;
}

// Now loop through only rows you care about
for ($row = $startRow; $row <= $endRow; $row++) {
    $cellRef = "C" . $row; // Example: column C has images

    if (!isset($drawingsMap[$cellRef])) {
        continue; // skip if no image here
    }

    $drawing = $drawingsMap[$cellRef];

    if ($drawing instanceof MemoryDrawing) {
        ob_start();
        call_user_func(
            $drawing->getRenderingFunction(),
            $drawing->getImageResource()
        );
        $imageContents = ob_get_contents();
        ob_end_clean();

        $extension = $drawing->getMimeType() == 'image/png' ? 'png' : 'jpg';
        $filename = $cellRef . '.' . $extension;
        file_put_contents($filename, $imageContents);
    } else {
        // File-based drawing
        $imageContents = file_get_contents($drawing->getPath());
        $filename = $cellRef . '.' . $drawing->getExtension();
        file_put_contents($filename, $imageContents);
    }

    echo "Saved image from $cellRef → $filename\n";
}
