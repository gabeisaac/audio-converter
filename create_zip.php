<?php
header('Content-Type: application/json');

// Create uploads directory if it doesn't exist
$uploadsDir = 'uploads';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

// Create a unique ZIP filename
$zipFilename = 'gabeisaac.com_converted_files_' . uniqid() . '.zip';
$zipPath = $uploadsDir . '/' . $zipFilename;

// Create a new ZIP archive
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
    echo json_encode(['success' => false, 'message' => 'Cannot create ZIP file']);
    exit;
}

// Get the list of files and their names
$files = $_POST['files'] ?? [];
$filenames = $_POST['filenames'] ?? [];

// Add each file to the ZIP
foreach ($files as $index => $fileUrl) {
    $filename = $filenames[$index] ?? 'file_' . ($index + 1);
    $fileContent = file_get_contents($fileUrl);
    if ($fileContent !== false) {
        $zip->addFromString($filename, $fileContent);
    }
}

$zip->close();

// Clean up individual files
foreach ($files as $fileUrl) {
    $filePath = parse_url($fileUrl, PHP_URL_PATH);
    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

// Return the ZIP file URL
$zipUrl = 'uploads/' . $zipFilename;
echo json_encode([
    'success' => true,
    'zipUrl' => $zipUrl,
    'message' => 'ZIP file created successfully'
]); 