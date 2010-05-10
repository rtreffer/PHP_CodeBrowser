<?php
/**
 * Error handler
 *
 * PHP Version 5.2.6
 *
 * Copyright (c) 2007-2009, Mayflower GmbH
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Mayflower GmbH nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category  PHP_CodeBrowser
 * @package   PHP_CodeBrowser
 * @author    Elger Thiele <elger.thiele@mayflower.de>
 * @copyright 2007-2009 Mayflower GmbH
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version   SVN: $Id$
 * @link      http://www.phpunit.de/
 * @since     File available since 1.0
 */

/**
 * CbErrorHandler
 *
 * This class is providing a lists of errors as well lists of filenames that
 * have related errors.
 * For providing this lists the prior generated PHP_CodeBrowser error xml file
 * is parsed.
 *
 * @category  PHP_CodeBrowser
 * @package   PHP_CodeBrowser
 * @author    Elger Thiele <elger.thiele@mayflower.de>
 * @author    Christopher Weckerle <christopher.weckerle@mayflower.de>
 * @copyright 2007-2009 Mayflower GmbH
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version   Release: @package_version@
 * @link      http://www.phpunit.de/
 * @since     Class available since 1.0
 */
class CbErrorHandler
{
    /**
     * cbXMLHandler object
     *
     * @var cbXMLHandler
     */
    public $cbXMLHandler;

    /**
     * Source path, if available.
     *
     * @var string
     */
    private $_source;

    /**
     * Supported file extensions in the code browser
     *
     * @var array
     */
    private $_fileExtensionWhitelist = array('php', 'js', 'html');

    private $_fileMimeWhitelist = array();

    /**
     * Default constructor
     *
     * @param cbXMLHandler $cbXMLHandler The cbXMLHandler object
     * @param string $source The optional source path
     */
    public function __construct (
        CbXMLHandler $cbXMLHandler,
        $source = NULL)
    {
        $this->cbXMLHandler = $cbXMLHandler;
        if ($source !== NULL) {
            $source = realpath($source);
        }
        $this->_source = $source;
    }

    /**
     * Get the path all errors have in common.
     *
     * @param array $errors List of all errors and its attributes
     *
     * @return string
     */
    public function getCommonSourcePath($errors)
    {
        if ($this->_source !== NULL) {
            return $this->_source;
        }
        if (empty($errors)) {
            return '';
        }
        $path = $errors[0]['path'];
        foreach ($errors as $error) {
            $errorpath = $error['path'];
            $path = $this->_getCommonErrorPath($errorpath, $path);
            if (strlen($path) === 0) {
                break;
            }
        }

        if (count(explode($path, DIRECTORY_SEPARATOR)) > 1) {
            return $path;
        }
        $relpath = $path;

        // retry with realpath
        $path = realpath($errors[0]['path']);
        foreach ($errors as $error) {
            $errorpath = realpath($error['path']);
            $path = realpath($this->_getCommonErrorPath($errorpath, $path));
        }

        if (realpath($relpath) === $path) {
            return $relpath;
        }

        return $path;
    }

    /**
     * Substitude the path all errors have in common, using the canonic path
     * if the source folder is known.
     *
     * @param array $errors The error list
     *
     * @return array
     */
    public function replaceCommonSourcePath($errors)
    {
        $commonSourcePath = $this->getCommonSourcePath($errors);
        $commonSourcePathLength = strlen($commonSourcePath);

        if ($commonSourcePathLength === 0) {
            return $errors;
        }

        if (!strlen($commonSourcePath)) {
            return $errors;
        }

        foreach ($errors as $key => &$error) {
            $pathcompare = strncmp(
                $error['path'],
                $commonSourcePath,
                $commonSourcePathLength
            );
            // check if this path starts with $commonSourcePath
            if ($pathcompare === 0) {
                $error['path'] = ltrim(
                    substr($error['path'], $commonSourcePathLength),
                    DIRECTORY_SEPARATOR
                );
                continue;
            }
            $realpath = realpath($error['path']);
            $pathcompare = strncmp(
                $realpath,
                $commonSourcePath,
                $commonSourcePathLength
            );
            if ($pathcompare !== 0) {
                continue;
            }
            $error['path'] = substr($realpath, $commonSourcePathLength + 1);
        }

        return $errors;
    }

    /**
     * Parse directory to get all files. Merging existing error list.
     *
     * @param array  $errors    The existing error list
     *
     * @return array
     */
    public function parseSourceDirectory($errors)
    {
        $sourceLength = strlen($this->_source);

        $errorsByRealpath = array();
        foreach ($errors as $error) {
            $errorsByRealpath[$error['complete']] = $error;
        }

        $fileList = array();

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->_source),
            RecursiveIteratorIterator::SELF_FIRST
        );
        // copy all errors related to known files
        // and generate an array of errorfree files
        foreach ($items as $name => $item) {
            $supported = $this->_isSupportedFileType(
                $item->getFilename(),
                $item->getPath()
            );
            if (!$supported) {
                continue;
            }
            $realpath = $item->getRealPath();
            if (array_key_exists($realpath, $errorsByRealpath)) {
                $error = $errorsByRealpath[$realpath];
                $fileList[$error['complete']] = $error;
                continue;
            }
            $error = array();
            $error['file'] = $item->getFilename();
            $error['path'] = substr(dirname($realpath), $sourceLength + 1);
            $error['complete'] = $realpath;
            $error['count_notices'] = 0;
            $error['count_errors']  = 0;
            $fileList[$error['complete']] = $error;
        }

        ksort($fileList);
        return $fileList;
    }

    /**
     * Get the related error elements for given $fileName.
     *
     * @param string $cbXMLFile The XML file to read in
     * @param string $fileName  The $fileName to search for, could be a mixe of
     *                          path with filename as well (e.g.
     *                          relative/path/filename.php)
     *
     * @return SimpleXMLElement
     */
    public function getErrorsByFile ($cbXMLFile, $fileName)
    {
        $element = $this->cbXMLHandler->loadXML($cbXMLFile);
        $fileName = realpath($fileName);

        foreach ($element as $file) {
            if ($file['name'] === $fileName) {
                return $file->children();
            }
        }
        return array();
    }

    /**
     * Get all the filenames with errors.
     *
     * @param string $cbXMLFileName The XML file with all information
     *
     * @return array
     */
    public function getFilesWithErrors ($cbXMLFileName)
    {
        $element = $this->cbXMLHandler->loadXML($cbXMLFileName);
        $files   = array();
        $path    = '';

        foreach ($element->children() as $file) {
            $tmp['complete']      = realpath($file['name']);
            $tmp['file']          = basename($tmp['complete']);
            $tmp['path']          = dirname($tmp['complete']);
            $tmp['count_errors']  = $this->cbXMLHandler->countItems(
                $file->children(),
                'severity',
                'error'
            );
            $tmp['count_notices'] = $this->cbXMLHandler->countItems(
                $file->children(),
                'severity',
                'notice'
            );
            $tmp['count_notices'] += $this->cbXMLHandler->countItems(
                $file->children(),
                'severity',
                'warning'
            );
            $files[]              = $tmp;
        }
        return $files;
    }

    /**
     * Filters the string from the beginning the two input strings have in
     * common.
     *
     * Example:
     * /path/to/source/folder/myFile.php
     * /path/to/source/other/folder/otherFile.php
     * will return
     * /path/to/source
     *
     * @param string $leftpath  String for comparing
     * @param string $rightpath  String for comparing
     *
     * @return string
     */
    private function _getCommonErrorPath($leftpath, $rightpath)
    {
        // split by '/'
        $leftpatharray = explode(DIRECTORY_SEPARATOR, $leftpath);
        $rightpatharray = explode(DIRECTORY_SEPARATOR, $rightpath);

        // pop filename
        array_pop($leftpatharray);
        array_pop($rightpatharray);

        $commonpath = array();

        $length = min(count($leftpatharray), count($rightpatharray));
        $position = 0;

        while ($position < $length &&
               $leftpatharray[$position] === $rightpatharray[$position]) {
               $commonpath[] = $leftpatharray[$position++];
        }

        $path = implode(DIRECTORY_SEPARATOR, $commonpath);
        return $path;
    }

    /**
     * Tests if a file is supported by the codebrowser.
     *
     * @param string $filename The filename of the probed file
     * @param string $path The path of the file
     *
     * @return boolean true if the fieltype is supported
     **/
    private function _isSupportedFileType($filename, $path)
    {
        $fileExtension = array_pop(explode('.', $filename));
        if (in_array($fileExtension, $this->_fileExtensionWhitelist)) {
            return true;
        }
        return false;
    }

}
