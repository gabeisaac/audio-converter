<?php
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set maximum execution time and memory limit
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

// Create uploads directory if it doesn't exist
$uploadsDir = 'uploads';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

// Set the path to FFmpeg
$ffmpegPath = '/home2/gabeisaa/ffmpeg_sources/ffmpeg-7.0.2-amd64-static/ffmpeg';

// Check if FFmpeg is accessible
if (!file_exists($ffmpegPath)) {
    die(json_encode([
        'success' => false,
        'message' => 'FFmpeg is not found at the specified path. Please contact support.'
    ]));
}

try {
    // Get the uploaded file
    if (!isset($_FILES['audioFile'])) {
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['audioFile'];
    $targetFormat = $_POST['targetFormat'] ?? 'mp3';
    $bitrate = (int) ($_POST['bitrate'] ?? 192);
    $sampleRate = (string) ($_POST['sampleRate'] ?? '44100');
    $channels = (($_POST['channels'] ?? '2') === '1') ? '1' : '2';
    $volume = (string) ($_POST['volume'] ?? '0');

    if ($bitrate < 64 || $bitrate > 320) {
        $bitrate = 192;
    }
    if (!in_array($sampleRate, ['44100', '48000', '96000'], true)) {
        $sampleRate = '44100';
    }
    if (!in_array($volume, ['0', '1', '2', '3'], true)) {
        $volume = '0';
    }

    // Validate file type
    $allowedTypes = [
        'audio/mpeg',
        'audio/wav',
        'audio/wave',
        'audio/x-wav',
        'audio/ogg',
        'audio/mp4',
        'audio/x-m4a',
        'audio/flac',
        'audio/x-flac',
        'audio/aac',
        'audio/x-aac',
        'audio/x-ms-wma',
        'audio/x-aiff',
        'application/octet-stream'
    ];

    $allowedExtensions = [
        'mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac', 'wma', 'aiff'
    ];

    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file['type'], $allowedTypes) && !in_array($fileExtension, $allowedExtensions)) {
        throw new Exception('Invalid file type. Supported formats: MP3, WAV, OGG, M4A, FLAC, AAC, WMA, AIFF');
    }

    $allowedFormats = ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac', 'wma', 'aiff'];
    if (!in_array($targetFormat, $allowedFormats, true)) {
        throw new Exception('Unsupported output format');
    }

    // Keep the stored names plain. Spaces and characters such as ß in the
    // original title were being pasted into the shell command unquoted.
    $uniqueId = uniqid();
    $storedExtension = in_array($fileExtension, $allowedExtensions, true) ? $fileExtension : 'audio';
    $tempPath = $uploadsDir . '/in_' . $uniqueId . '.' . $storedExtension;
    if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
        throw new Exception('Could not store the uploaded file');
    }

    $originalFilename = pathinfo($file['name'], PATHINFO_FILENAME);
    $safeBase = strtr($originalFilename, [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
        'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
        'ß' => 'ss'
    ]);
    $safeBase = preg_replace('/[^A-Za-z0-9_-]+/', '_', $safeBase);
    $safeBase = trim($safeBase, '_');
    if ($safeBase === '') {
        $safeBase = 'audio';
    }
    $safeBase = substr($safeBase, 0, 80);
    $outputFilename = 'gabeisaac.com_' . $safeBase . '_' . $uniqueId . '.' . $targetFormat;
    $outputPath = $uploadsDir . '/' . $outputFilename;

    // Set format-specific parameters with optimized settings
    $formatParams = [
        'mp3' => "-c:a libmp3lame -b:a {$bitrate}k -q:a 2", // Add quality setting for MP3
        'wav' => "-c:a pcm_s16le -ar {$sampleRate}", // Ensure sample rate is set for WAV
        'ogg' => "-c:a libvorbis -b:a " . max(320, $bitrate) . "k -q:a 5", // Add quality setting for OGG
        'm4a' => "-c:a aac -b:a {$bitrate}k -profile:a aac_low", // Add AAC profile
        'flac' => "-c:a flac -compression_level 8", // Add compression level for FLAC
        'aac' => "-c:a aac -b:a {$bitrate}k -profile:a aac_low", // Add AAC profile
        'wma' => "-c:a wmav2 -b:a {$bitrate}k -ar {$sampleRate}", // Ensure sample rate for WMA
        'aiff' => "-c:a pcm_s16be -ar {$sampleRate}" // Ensure sample rate for AIFF
    ];

    // Build FFmpeg command with additional safety parameters
    $command = escapeshellarg($ffmpegPath) . ' -y -i ' . escapeshellarg($tempPath) . ' ';
    $command .= '-ar ' . $sampleRate . ' -ac ' . $channels . ' ';

    // Add volume normalization if selected
    if ($volume > 0) {
        $lufs = ['-16', '-14', '-12'][$volume - 1];
        $command .= "-af loudnorm=I={$lufs}:TP=-1.5:LRA=11 ";
    }

    // Add format-specific parameters
    $command .= $formatParams[$targetFormat] . ' ' . escapeshellarg($outputPath) . ' 2>&1';

    // Execute FFmpeg command with error handling
    exec($command, $output, $returnCode);

    // Clean up temporary file
    unlink($tempPath);

    if ($returnCode === 0) {
        // Verify the output file exists and has content
        if (!file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new Exception('Conversion completed but output file is empty or missing');
        }

        // Check if the file is actually in the correct format
        $verifyCommand = escapeshellarg($ffmpegPath) . ' -i ' . escapeshellarg($outputPath) . ' -f null - 2>&1';
        exec($verifyCommand, $verifyOutput, $verifyCode);

        if ($verifyCode !== 0) {
            unlink($outputPath);
            throw new Exception('Output file verification failed: ' . implode("\n", $verifyOutput));
        }

        echo json_encode([
            'success' => true,
            'message' => 'Conversion successful',
            'downloadUrl' => $outputPath,
            'filename' => $outputFilename
        ]);
    } else {
        // Clean up output file if conversion failed
        if (file_exists($outputPath)) {
            unlink($outputPath);
        }
        $details = [];
        foreach ($output as $line) {
            if (stripos($line, 'Error') !== false) {
                $details[] = $line;
            }
        }
        $detail = $details ? implode(' ', $details) : 'FFmpeg could not convert this file';
        throw new Exception('Conversion failed: ' . $detail);
    }

} catch (Exception $e) {
    // Clean up any temporary files
    if (isset($tempPath) && file_exists($tempPath)) {
        unlink($tempPath);
    }
    if (isset($outputPath) && file_exists($outputPath)) {
        unlink($outputPath);
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 