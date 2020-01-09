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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use OxidEsales\Eshop\Core\Registry;

/**
 * Cache Clear command
 *
 * Clears out OXID cache from tmp folder
 */
class CacheClearCommand extends Command
{

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('cache:clear')
            ->setDescription('Clear OXID cache')
            ->addOption('smarty', null, InputOption::VALUE_NONE, "Clears out smarty cache")
            ->addOption('files', 'f', InputOption::VALUE_NONE, "Clears out files cache")
            ->addOption('oxcache', 'o', InputOption::VALUE_NONE, "Clears out oxCache (for EE)")
            ->setHelp(<<<'EOF'
Command <info>%command.name%</info> clears contents of OXID eShop tmp folder.

Notes:
  
  * <comment>'<info>.htaccess</info>' file will not be deleted;</comment>
  * <comment>'<info>smarty</info>' folder will not be deleted, only the contents of it.</comment>
EOF
        );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $clearAllCache = !$input->getOption('smarty') && !$input->getOption('files') && !$input->getOption('oxcache');

        $cachePath = $this->appendDirectorySeparator(Registry::getConfig()->getConfigParam('sCompileDir'));
        if (!is_dir($cachePath)) {
            $output->writeln("Seems that compile directory '$cachePath' does not exist");
            exit(1);
        }

        if (($clearAllCache || $input->getOption('oxcache')) && class_exists('oxCache')) {
            $output->writeln('Clearing oxCache...');
            Registry::get('oxCache')->reset(false);
        } else {
            $output->writeln('Skipping oxCache...');
        }

        if ($clearAllCache || $input->getOption('smarty')) {
            $output->writeln('Clearing smarty cache...');
            $this->clearDirectory($cachePath . 'smarty');
        } else {
            $output->writeln('Skipping smarty cache...');
        }

        if ($clearAllCache || $input->getOption('files')) {
            $output->writeln('Clearing files cache...');
            $this->clearDirectory($cachePath, array('.htaccess', 'smarty'));
        } else {
            $output->writeln('Skipping files cache...');
        }

        $output->writeln('Cache cleared successfully');
    }

    /**
     * Clear files in given directory, except those which
     * are in $aKeep array
     *
     * @param string $sDir
     * @param array $aKeep
     */
    protected function clearDirectory($sDir, $aKeep = array())
    {
        $sDir = $this->appendDirectorySeparator($sDir);

        foreach (glob($sDir . '*') as $sFilePath) {
            $sFileName = basename($sFilePath);
            if (in_array($sFileName, $aKeep)) {
                continue;
            }

            is_dir($sFilePath)
                ? $this->removeDirectory($sFilePath)
                : unlink($sFilePath);
        }
    }

    /**
     * Remove directory
     *
     * @param string $sPath
     */
    protected function removeDirectory($sPath)
    {
        if (!is_dir($sPath)) {
            return;
        }

        $oIterator = new RecursiveDirectoryIterator($sPath, RecursiveDirectoryIterator::SKIP_DOTS);
        $oFiles = new RecursiveIteratorIterator($oIterator, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($oFiles as $oFile) {
            if ($oFile->getFilename() == '.' || $oFile->getFilename() === '..') {
                continue;
            }

            $oFile->isDir()
                ? rmdir($oFile->getRealPath())
                : unlink($oFile->getRealPath());
        }

        rmdir($sPath);
    }

    /**
     * Append directory separator to path
     *
     * @param string $sPath
     *
     * @return string
     */
    protected function appendDirectorySeparator($sPath)
    {
        if (substr($sPath, -1) != DIRECTORY_SEPARATOR) {
            return $sPath . DIRECTORY_SEPARATOR;
        }

        return $sPath;
    }
}
