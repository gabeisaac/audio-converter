# Audio Converter

Free ShrimpWeb tool. Convert MP3, WAV, FLAC, M4A, AAC, OGG, and a few other formats, with bitrate, sample rate, channels, and a simple volume trim.

Live: https://shrimpweb.com/audio_converter/

The page is `index.html`. `converter.php` shells out to FFmpeg, and `create_zip.php` bundles a batch download. The FFmpeg path in `converter.php` is the one on the ShrimpWeb server. Point it at your own `ffmpeg` binary before running this elsewhere.
