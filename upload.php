<?php

/**
 * This is the implementation of the server side part of
 * Resumable.js client script, which sends/uploads files
 * to a server in several chunks.
 *
 * The script receives the files in a standard way as if
 * the files were uploaded using standard HTML form (multipart).
 *
 * This PHP script stores all the chunks of a file in a temporary
 * directory (`temp`) with the extension `_part<#ChunkN>`. Once all
 * the parts have been uploaded, a final destination file is
 * being created from all the stored parts (appending one by one).
 *
 * @author Gregory Chris (http://online-php.com)
 * @email www.online.php@gmail.com
 *
 * @editor Bivek Joshi (http://www.bivekjoshi.com.np)
 * @email meetbivek@gmail.com
 */

////////////////////////////////////////////////////////////////////
// CONFIG
////////////////////////////////////////////////////////////////////

$upload_dir = 'files';
$tmp_dir = '/tmp';
$rm_chunks_on_completion = true; // only useful for debugging, should always be true


////////////////////////////////////////////////////////////////////
// THE FUNCTIONS
////////////////////////////////////////////////////////////////////

/**
 *
 * Logging operation - to a file (upload_log.txt) and to the stdout
 * @param string $str - the logging string
 */
function _log($str)
{
    // log to the output
    $log_str = date('Y/m/d H:i:s') . ": {$str}\r\n";
    echo $log_str;

    // log to file
    if (($fp = fopen('upload_log.txt', 'a+')) !== false) {
        fputs($fp, $log_str);
        fclose($fp);
    }
}

/**
 *
 * Delete a directory RECURSIVELY
 * @param string $dir - directory path
 * @link http://php.net/manual/en/function.rmdir.php
 */
function rrmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir . "/" . $object) == "dir") {
                    rrmdir($dir . "/" . $object);
                } else {
                    unlink($dir . "/" . $object);
                }
            }
        }
        reset($objects);
        rmdir($dir);
    }
}


/**
 *
 * Check if request option $key is set and valid.
 * Return it if it is, else return $default.
 * @param string $key - the option name
 * @param string $default - the value to return if the option is not set
 */
function getInput($key, $default = '')
{
    return isset($_REQUEST[$key]) && trim($_REQUEST[$key]) != ''
        ? $_REQUEST[$key]
        : $default;
}

/**
 *
 * Create directory if it doesn't exist
 * @param string $dir - the path of the directory
 */
function createDir($dir)
{
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

/**
 *
 * Create and return the temporary directory where the file chunks will be stored
 */
function getChunksDir()
{
    global $tmp_dir;
    if (!$filename = getInput('resumableFilename')) responseCodeAndDie(400);
    $id = getInput('resumableIdentifier') ?: md5($filename);
    $chunks_dir = "{$tmp_dir}/{$id}";
    createDir($chunks_dir);
    return $chunks_dir;
}

/**
 *
 * Change HTTP code and die
 * @param int $code
 */
function responseCodeAndDie($code = 404)
{
    http_response_code($code);
    die();
}

/**
 *
 * Merge the chunks of a file to $dest_file
 * @param string $dest_file - the path of the output file
 * @param string $chunk_basepath
 * @param int $total_chunks
 */
function mergeChunksToFile($dest_file, $chunk_basepath, $total_chunks)
{
    if (($fp = fopen($dest_file, 'w')) !== false) {
        for ($i = 1; $i <= $total_chunks; $i++) {
            fwrite($fp, file_get_contents("{$chunk_basepath}.{$i}"));
            _log("writing chunk {$i}");
        }
        fclose($fp);
        return true;
    } else {
        _log('cannot create the destination file');
        return false;
    }
}

/**
 *
 * Check if all the parts exist, and
 * gather all the parts of the file together
 * @param string $rm_chunks - delete the chunks after the merge
 */
function createFileFromChunksIfAllChunksUploaded()
{
    global $upload_dir, $rm_chunks_on_completion;

    $chunks_dir = getChunksDir();
    $filename = getInput('resumableFilename');
    $total_size = getInput('resumableTotalSize');
    $total_chunks = getInput('resumableTotalChunks');

    // add up the sizes of all the parts for this file
    $total_files_on_server_size = 0;
    foreach (scandir($chunks_dir) as $file) {
        $total_files_on_server_size += filesize($chunks_dir . '/' . $file);
    }

    // check that all the parts are present
    // If the Size of all the chunks on the server is equal to the size of the file uploaded.
    if ($total_files_on_server_size >= $total_size) {

        createDir($upload_dir);

        // create the final destination file
        $dest_file = "{$upload_dir}/{$filename}";
        $chunk_basepath = "{$chunks_dir}/{$filename}.part";

        // if (! mergeChunksToFile($dest_file, $chunk_basepath, $total_chunks)) return false;
        mergeChunksToFile($dest_file, $chunk_basepath, $total_chunks);

        // rename the temporary directory (to avoid access from other
        // concurrent chunks uploads) and than delete it
        if ($rm_chunks_on_completion) {
            if (rename($chunks_dir, "{$chunks_dir}_UNUSED")) {
                rrmdir("{$chunks_dir}_UNUSED");
            } else {
                rrmdir($chunks_dir);
            }
        }
    }
}



////////////////////////////////////////////////////////////////////
// THE SCRIPT
////////////////////////////////////////////////////////////////////

// Check if request is GET and the requested chunk exists or not.
// Will only be called if `testChunks == true` in `resumable.js`.
// If the chunk already exists, it doesn't need to be uploaded.
// That's how the "upload resuming" is implemented.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $chunks_dir = getChunksDir();
    $filename = getInput('resumableFilename');
    $chunk_number = getInput('resumableChunkNumber');
    $chunk_file = "{$chunks_dir}/{$filename}.part.{$chunk_number}";

    _log("test chunk {$chunk_number} of {$filename}");

    responseCodeAndDie(file_exists($chunk_file) ? 200 : 404);
}

// loop through files and move the chunks to a temporarily created directory
if (! empty($_FILES)) foreach ($_FILES as $file) {

    // check the error status
    if ($file['error'] != 0) {
        _log("error {$file['error']} in file {$_POST['resumableFilename']}");
        continue;
    }

    // init the destination file (format <filename.ext>.part<#chunk>
    // the file is stored in a temporary directory
    $chunks_dir = getChunksDir();
    $dest_file = "{$chunks_dir}/{$_POST['resumableFilename']}.part.{$_POST['resumableChunkNumber']}";

    // move the temporary file
    if (!move_uploaded_file($file['tmp_name'], $dest_file)) {
        _log("Error saving (move_uploaded_file) chunk {$_POST['resumableChunkNumber']} for file {$_POST['resumableFilename']}");
    } else {
        // check if all the parts present, and create the final destination file
        createFileFromChunksIfAllChunksUploaded();
    }
}
