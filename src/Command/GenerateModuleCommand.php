<?php

/**
 * @copyright OXID eSales AG, All rights reserved
 * @author OXID Professional services
 *
 * See LICENSE file for license details.
 */

namespace OxidProfessionalServices\OxidConsole\Command;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use OxidEsales\Eshop\Core\Registry;

/**
 * Generate module command
 */
class GenerateModuleCommand extends Command
{

    /**
     * @var string Directory path where modules are stored
     */
    protected $sModuleDir;

    /**
     * @var string Templates dir
     */
    protected $sTemplatesDir;

    /**
     * @var Smarty
     */
    protected $smarty;

    /** @var InputInterface */
    private $input;

    /** @var OutputInterface */
    private $output;

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('module:generate')
            ->setAliases(['g:module'])
            ->setDescription('Generate new module scaffold');
    }

    private function init()
    {
        $this->smarty = Registry::get('oxUtilsView')->getSmarty();
        $this->smarty->php_handling = SMARTY_PHP_PASSTHRU;
        $this->sModuleDir = OX_BASE_PATH . 'modules' . DIRECTORY_SEPARATOR;
        $this->sTemplatesDir = __DIR__ . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR
            . 'module' . DIRECTORY_SEPARATOR;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init();
        $this->input = $input;
        $this->output = $output;

        $oScaffold = $this->buildScaffold();
        $this->generateModule($oScaffold);

        $output->writeln('Module generated successfully');
    }

    /**
     * Generate module from scaffold object
     *
     * @param object $oScaffold
     */
    protected function generateModule($oScaffold)
    {
        $oSmarty = $this->getSmarty();
        $oSmarty->assign('oScaffold', $oScaffold);

        if ($oScaffold->sVendor) {
            $this->generateVendorDir($oScaffold->sVendor);
        }

        $sModuleDir = $this->getModuleDir($oScaffold->sVendor, $oScaffold->sModuleName);
        $this->copyAndParseDir(
            $this->sTemplatesDir,
            $sModuleDir,
            array(
                '_prefix_' => strtolower($oScaffold->sVendor . $oScaffold->sModuleName)
            )
        );
    }

    /**
     * Copies files from directory, parses all files and puts
     * parsed content to another directory
     *
     * @param string $sFrom Directory from
     * @param string $sTo Directory to
     * @param array $aNameMap What should be changed in file name?
     */
    protected function copyAndParseDir($sFrom, $sTo, array $aNameMap = array())
    {
        $oFileInfos = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sFrom, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        if (!file_exists($sTo)) {
            mkdir($sTo);
        }

        foreach ($oFileInfos as $oFileInfo) {
            $sFilePath = (string)$oFileInfo;
            $aReplace = array(
                'search' => array_merge(array($sFrom), array_keys($aNameMap)),
                'replace' => array_merge(array($sTo), array_values($aNameMap))
            );
            $sNewPath = str_replace($aReplace['search'], $aReplace['replace'], $sFilePath);
            $this->copyAndParseFile($sFilePath, $sNewPath);
        }
    }

    /**
     * Copies file from one directory to another, parses file if original
     * file extension is .tpl
     *
     * @param $sFrom
     * @param $sTo
     */
    protected function copyAndParseFile($sFrom, $sTo)
    {
        $this->createMissingFolders($sTo);

        $sTo = preg_replace('/\.tpl$/', '', $sTo);
        if (preg_match('/\.tpl$/', $sFrom)) {
            $oSmarty = $this->getSmarty();
            $sContent = $oSmarty->fetch($sFrom);
        } else {
            $sContent = file_get_contents($sFrom);
        }

        file_put_contents($sTo, $sContent);
    }

    /**
     * Create missing folders of file path
     *
     * @param string $sFilePath
     */
    protected function createMissingFolders($sFilePath)
    {
        $sPath = dirname($sFilePath);

        if (!file_exists($sPath)) {
            mkdir($sPath, 0777, true);
        }
    }

    /**
     * Generate vendor directory
     *
     * @param string $sVendor
     */
    protected function generateVendorDir($sVendor)
    {
        $sVendorDir = $this->sModuleDir . $sVendor . DIRECTORY_SEPARATOR;
        if (!file_exists($sVendorDir)) {
            mkdir($sVendorDir);

            // Generate vendor metadata file
            file_put_contents($sVendorDir . 'vendormetadata.php', '<?php');
        }
    }

    /**
     * Build scaffold object from user inputs
     *
     * @return stdClass
     */
    protected function buildScaffold()
    {
        $oScaffold = new stdClass();
        $oScaffold->sVendor = strtolower($this->getUserInput('Vendor Prefix', true));

        $blFirstRequest = true;

        do {
            if (!$blFirstRequest) {
                $this->output->writeln('Module path or id is taken with given title');
            } else {
                $blFirstRequest = false;
            }

            $oScaffold->sModuleTitle = $this->getUserInput('Module Title');
            $oScaffold->sModuleName = str_replace(' ', '', ucwords($oScaffold->sModuleTitle));
            $oScaffold->sModuleId = $oScaffold->sVendor . strtolower($oScaffold->sModuleName);
        } while (
            !$this->modulePathAvailable($oScaffold->sVendor, $oScaffold->sModuleName)
            || !$this->moduleIdAvailable($oScaffold->sModuleId)
        );

        $oScaffold->sModuleDir = $this->getModuleDir($oScaffold->sVendor, $oScaffold->sModuleName);
        $oScaffold->sAuthor = $this->getUserInput('Author', true);
        $oScaffold->sUrl = $this->getUserInput('Url', true);
        $oScaffold->sEmail = $this->getUserInput('Email', true);

        return $oScaffold;
    }

    /**
     * Get module dir
     *
     * @param string $sVendor
     * @param string $sModuleName
     *
     * @return string
     */
    protected function getModuleDir($sVendor, $sModuleName)
    {
        $sModuleDir = $this->sModuleDir;
        if ($sVendor) {
            $sModuleDir .= strtolower($sVendor) . DIRECTORY_SEPARATOR;
        }

        return $sModuleDir . strtolower($sModuleName) . DIRECTORY_SEPARATOR;
    }

    /**
     * Module path available?
     *
     * @param string $sVendor
     * @param string $sModuleName
     *
     * @return bool
     */
    protected function modulePathAvailable($sVendor, $sModuleName)
    {
        return !is_dir($this->getModuleDir($sVendor, $sModuleName));
    }

    /**
     * Is module id available?
     *
     * @param string $sModuleId
     *
     * @return bool
     */
    protected function moduleIdAvailable($sModuleId)
    {
        return !array_key_exists($sModuleId, Registry::getConfig()->getConfigParam('aModulePaths'));
    }

    /**
     * Get user input
     *
     * @param string $sText
     * @param bool $bAllowEmpty
     *
     * @return string
     */
    protected function getUserInput($sText, $bAllowEmpty = false)
    {
        $questionHelper = $this->getHelper('question');

        do {
            $sTitle = "$sText: " . ($bAllowEmpty ? '[optional] ' : '[required] ');
            $question = new Question($sTitle);
            $sInput = $questionHelper->ask($this->input, $this->output, $question);
        } while (!$bAllowEmpty && !$sInput);

        return $sInput;
    }

    /**
     * Get Smarty
     *
     * @return Smarty
     */
    protected function getSmarty()
    {
        return $this->smarty;
    }
}
