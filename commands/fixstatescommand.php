<?php

/**
 * @copyright OXID eSales AG, All rights reserved
 * @author OXID Professional services
 *
 * See LICENSE file for license details.
 */

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Fix States command
 */
class FixStatesCommand extends Command
{

    /**
     * @var array|null Available module ids
     */
    protected $_aAvailableModuleIds = null;

    /** @var InputInterface */
    private $input;

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('module:states')
            ->setAliases(['fix:states'])
            ->setDescription('Fixes modules metadata states')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Includes all modules')
            ->addOption('base-shop', 'b', InputOption::VALUE_NONE, 'Apply changes to base shop only')
            ->addOption('shop', 's', InputOption::VALUE_REQUIRED, 'Apply changes to given shop only')
            ->addArgument('module-id', InputArgument::IS_ARRAY, 'Module id/ids to use');
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;

        $verboseOutput = $input->getOption('verbose')
            ? $output
            : new NullOutput();

        try {
            $aModuleIds = $this->_parseModuleIds();
            $aShopConfigs = $this->_parseShopConfigs();
        } catch (oxInputException $oEx) {
            $output->writeLn($oEx->getMessage());
            exit(1);
        }

        /** @var oxModuleStateFixer $oModuleStateFixer */
        $oModuleStateFixer = oxRegistry::get('oxModuleStateFixer');

        /** @var oxModule $oModule */
        $oModule = oxNew('oxModule');

        foreach ($aShopConfigs as $oConfig) {

            $verboseOutput->writeLn('[DEBUG] Working on shop id ' . $oConfig->getShopId());

            foreach ($aModuleIds as $sModuleId) {
                if (!$oModule->load($sModuleId)) {
                    $verboseOutput->writeLn("[DEBUG] {$sModuleId} does not exist - skipping");
                    continue;
                }

                $verboseOutput->writeLn("[DEBUG] Fixing {$sModuleId} module");
                $oModuleStateFixer->fix($oModule, $oConfig);
            }

            $verboseOutput->writeLn('');
        }

        $output->writeLn('Fixed module states successfully');
    }

    /**
     * Parse and return module ids from input
     *
     * @return array
     *
     * @throws oxInputException
     */
    protected function _parseModuleIds()
    {
        if ($this->input->getOption('all')) {
            return $this->_getAvailableModuleIds();
        }

        if (count($this->input->getArguments()['module-id']) === 0) {
            /** @var oxInputException $oEx */
            $oEx = oxNew('oxInputException');
            $oEx->setMessage('Please specify at least one module if as argument or use --all (-a) option');
            throw $oEx;
        }

        $requestedModuleIds = $this->input->getArguments()['module-id'];
        $availableModuleIds = $this->_getAvailableModuleIds();

        // Checking if all provided module ids exist
        foreach ($requestedModuleIds as $moduleId) {
            if (!in_array($moduleId, $availableModuleIds)) {
                /** @var oxInputException $oEx */
                $oEx = oxNew('oxInputException');
                $oEx->setMessage("{$moduleId} module does not exist");
                throw $oEx;
            }
        }

        return $requestedModuleIds;
    }

    /**
     * Parse and return shop config objects from input
     *
     * @return oxSpecificShopConfig[]
     *
     * @throws oxInputException
     */
    protected function _parseShopConfigs()
    {
        if ($this->input->getOption('base-shop')) {
            return array(oxRegistry::getConfig());
        }

        if ($shopId = $this->input->getOption('shop')) {
            if ($oConfig = oxSpecificShopConfig::get($shopId)) {
                return array($oConfig);
            }

            /** @var oxInputException $oEx */
            $oEx = oxNew('oxInputException');
            $oEx->setMessage('Shop id does not exist');
            throw $oEx;
        }

        return oxSpecificShopConfig::getAll();
    }

    /**
     * Get all available module ids
     *
     * @return array
     */
    protected function _getAvailableModuleIds()
    {
        if ($this->_aAvailableModuleIds === null) {
            $oConfig = oxRegistry::getConfig();

            // We are calling getModulesFromDir() because we want to refresh
            // the list of available modules. This is a workaround for OXID
            // bug.
            oxNew('oxModuleList')->getModulesFromDir($oConfig->getModulesDir());
            $this->_aAvailableModuleIds = array_keys($oConfig->getConfigParam('aModulePaths'));
        }

        return $this->_aAvailableModuleIds;
    }
}
