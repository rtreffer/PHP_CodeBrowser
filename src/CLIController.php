<?php
/**
 * Cli controller
 *
 * PHP Version 5.2
 *
 * Copyright (c) 2007-2010, Mayflower GmbH
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
 * @copyright 2007-2010 Mayflower GmbH
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version   SVN: $Id$
 * @link      http://www.phpunit.de/
 * @since     File available since 1.0
 */

if (strpos('@php_dir@', '@php_dir') === false) {
    if (!defined('PHPCB_ROOT_DIR')) {
        define('PHPCB_ROOT_DIR', '@php_dir@/PHP_CodeBrowser');
    }
    if (!defined('PHPCB_TEMPLATE_DIR')) {
        define('PHPCB_TEMPLATE_DIR', '@data_dir@/PHP_CodeBrowser/templates');
    }
} else {
    if (!defined('PHPCB_ROOT_DIR')) {
        define('PHPCB_ROOT_DIR', dirname(__FILE__) . '/../');
    }
    if (!defined('PHPCB_TEMPLATE_DIR')) {
        define('PHPCB_TEMPLATE_DIR', dirname(__FILE__) . '/../templates');
    }
}

require_once dirname(__FILE__) . '/Util/Autoloader.php';
require_once 'PHP/Timer.php';

/**
 * CbCLIController
 *
 * @category  PHP_CodeBrowser
 * @package   PHP_CodeBrowser
 * @author    Elger Thiele <elger.thiele@mayflower.de>
 * @author    Christopher Weckerle <christopher.weckerle@mayflower.de>
 * @copyright 2007-2010 Mayflower GmbH
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version   Release: @package_version@
 * @link      http://www.phpunit.de/
 * @since     Class available since 1.0
 */
class CbCLIController
{
    /**
     * Path to the Cruise Control input xml file
     *
     * @var string
     */
    private $_logDir;

    /**
     * Path to the generated code browser xml file
     *
     * @var string
     */
    private $_xmlFile;

    /**
     * Path to the code browser html output folder
     *
     * @var string
     */
    private $_htmlOutputDir;

    /**
     * Path to the project source code files
     *
     * @var string
     */
    private $_projectSourceDir;

    /**
     * The error plugin classes
     *
     * @var array
     */
    private $_registeredErrorPlugins;

    /**
     * The constructor
     *
     * Standard setters are initialized
     *
     * @param string $logPath          The (path-to) xml log files
     * @param string $projectSourceDir The project source directory
     * @param string $htmlOutputDir    The html output dir, where new files will
     *                                 be created
     * @param string $cbXMLFile        The (path-to) PHP_CodeBrowser XML file
     */
    public function __construct($logPath, $projectSourceDir, $htmlOutputDir, $cbXMLFile)
    {
        $this->setXMLLogDir($logPath);
        $this->setXMLFile($cbXMLFile);
        $this->setProjectSourceDir($projectSourceDir);
        $this->setHtmlOutputDir($htmlOutputDir);
    }

    /**
     * Setter method for the (path-to) XML log files
     *
     * @param string $directory The (path-to) XML file log directory
     *
     * @return void
     */
    public function setXMLLogDir($directory)
    {
        $this->_logDir = $directory;
    }

    /**
     * Setter method for the (path-to) XML files
     *
     * @param string $fileName The (path-to) XML file
     *
     * @return void
     */
    public function setXMLFile($fileName)
    {
        $this->_xmlFile = $fileName;
    }

    /**
     * Setter method for the project source directory
     *
     * @param string $projectSourceDir The (path-to) project source directory
     *
     * @return void
     */
    public function setProjectSourceDir($projectSourceDir)
    {
        $this->_projectSourceDir = $projectSourceDir;
    }

    /**
     * Setter method for the output directory
     *
     * @param string $htmlOutputDir The (path-to) output directory
     *
     * @return void
     */
    public function setHtmlOutputDir($htmlOutputDir)
    {
        $this->_htmlOutputDir = $htmlOutputDir;
    }

    /**
     * Setter/adder method for the used plugin classes.
     * For each plugin to use, add it to this array
     *
     * @param mixed $classNames Definition of plugin classes
     *
     * @return void
     */
    public function addErrorPlugins($classNames)
    {
        foreach ((array) $classNames as $className) {
            $this->_registeredErrorPlugins[] = $className;
        }
    }

    /**
     * Main execute function for PHP_CodeBrowser.
     *
     * Following steps are resolved:
     * 1. Clean-up output directory
     * 2. Merge xml log files
     * 3. Generate cbXML file via errorlist from plugins
     * 4. Save the cbErrorList as XML file
     * 5. Generate HTML output from cbXML
     * 6. Copy ressources (css, js, images) from template directory to output
     *
     * @return void
     */
    public function run()
    {
        // init needed classes
        $cbFDHandler    = new CbFDHandler();
        $cbXMLHandler   = new CbXMLHandler($cbFDHandler);
        $cbJSGenerator  = new CbJSGenerator($cbFDHandler);
        $cbErrorHandler =
            new CbErrorHandler($cbXMLHandler, $this->_projectSourceDir);

        // clear and create output directory
        $cbFDHandler->deleteDirectory($this->_htmlOutputDir);
        $cbFDHandler->createDirectory($this->_htmlOutputDir);

        // merge xml files
        $cbXMLHandler->addDirectory($this->_logDir);
        $mergedDOMDoc = $cbXMLHandler->mergeFiles();

        // conversion of XML file cc to cb format
        $list = array();
        foreach ($this->_registeredErrorPlugins as $className) {
            $plugin = new $className($this->_projectSourceDir, $cbXMLHandler);
            $plugin->setXML($mergedDOMDoc);
            $list = array_merge_recursive($list, $plugin->parseXMLError());
        }

        // construct the error list
        $cbXMLGenerator = new CbXMLGenerator($cbFDHandler);
        $cbXMLGenerator->setXMLName($this->_xmlFile);
        $cbXMLGenerator->saveCbXML($cbXMLGenerator->generateXMLFromErrors($list));

        // get cb error list, filter common source path
        $errors = $cbErrorHandler->getFilesWithErrors($this->_xmlFile);
        $errors = $cbErrorHandler->replaceCommonSourcePath($errors);

        // parse source directory
        $errors = $cbErrorHandler->parseSourceDirectory($errors);
        sort($errors);

        // set project source dir from error list
        if (!isset($this->_projectSourceDir)) {
            $this->setProjectSourceDir(
                $cbErrorHandler->getCommonSourcePath($errors)
            );
        }

        $html   = new CbHTMLGenerator(
            $cbFDHandler, $cbErrorHandler, $cbJSGenerator
        );
        $html->setTemplateDir(PHPCB_TEMPLATE_DIR);
        $html->setOutputDir($this->_htmlOutputDir);

        if (!empty($errors)) {
            $html->generateViewFlat($errors);
            $html->generateViewTree($errors);
            $html->generateViewReview(
                $errors, $this->_xmlFile, $this->_projectSourceDir
            );
        }
        // copy needed resources like css, js, images
        $html->copyRessourceFolders(!empty($errors));
    }


    /**
     * Main method called by script
     *
     * @return void
     */
    public static function main()
    {
        $xmlLogDir    = null;
        $sourceFolder = null;
        $htmlOutput   = null;
        $xmlFileName  = 'cbCodeBrowser.xml';

        // register autoloader
        spl_autoload_register(array(new CbAutoloader(), 'autoload'));


        $argv = $_SERVER['argv'];
        foreach ($argv as $key => $argument) {
            switch ($argument) {
            case '--log':
                $xmlLogDir = $argv[$key + 1];
                break;
            case '--source':
                $sourceFolder = isset($argv[$key + 1]) ? $argv[$key + 1] : NULL;
                break;
            case '--output':
                $htmlOutput = $argv[$key + 1];
                break;
            case '--help':
            case '-h':
                self::printHelp();
                break;
            case '--version':
                self::printVersion();
                break;
            }
        }

        // Check for directories
        if (!is_dir($xmlLogDir) || !is_dir($htmlOutput)
        || (isset($sourceFolder) && !is_dir($sourceFolder))) {
            printf(
                "Error: \n%s%s%s\n",
                !is_dir($xmlLogDir)
                ? "- xml log directory [$xmlLogDir] not found\n"
                : '',
                (isset($sourceFolder) && !is_dir($sourceFolder))
                ? "- project source directory [$sourceFolder] not found\n"
                : '',
                !is_dir($htmlOutput)
                ? "- output directory [$htmlOutput] not found\n"
                : ''
            );
            self::printHelp();
        }

        printf("Generating PHP_CodeBrowser files\n");

        // init new CLIController
        $controller = new CbCLIController(
            $xmlLogDir,
            $sourceFolder,
            $htmlOutput,
            $htmlOutput . '/' . $xmlFileName
        );
        $controller->addErrorPlugins(
            array('CbErrorCheckstyle', 'CbErrorPMD', 'CbErrorCPD', 'CbErrorPadawan')
        );

        try {
            $controller->run();
        } catch (Exception $e) {
            printf("PHP-CodeBrowser Error: \n%s\n", $e->getMessage());
        }

        print "\n" . PHP_Timer::resourceUsage() . "\n";
    }

    /**
     * Print help menu for shell
     *
     * @return void
     */
    public static function printHelp()
    {
        $help = sprintf(
            "Usage: phpcb --log <dir> --output <dir> [--source <dir>]

            PHP_CodeBrowser arguments:
            \t--log <dir>      \t\tThe path to the xml log files, e.g. generated from phpunit.
            \t--output <dir>   \t\tPath to the output folder where generated files should be stored.
            \t--source <dir> (opt)   \tPath to the project source code. Parse complete source directory
                                \t\t\t\tif is set, else only files from logs.

            General arguments:
            \t--help           \t\t\tPrint this help.\n\n"
        );
        echo str_replace("  ", "", $help);
        exit();
    }

    /**
     * Print version information to shell
     *
     * @return void
     */
    public static function printVersion()
    {
        $help = sprintf(
            "PHP_CodeBrowser by Mayflower GmbH\n\n"
        );
        echo str_replace("  ", "", $help);
        exit();
    }
}
