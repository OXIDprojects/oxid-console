<?php

/*
 * This file is part of the OXID Console package.
 *
 * (c) Eligijus Vitkauskas <eligijusvitkauskas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Fix States command
 */
class FixStatesCommand extends oxConsoleCommand
{

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this->setName('fix:states');
        $this->setDescription('Fixes modules metadata states');
    }

    /**
     * {@inheritdoc}
     */
    public function help(oxIOutput $oOutput)
    {
        $oOutput->writeLn('Usage: fix:states [options] <module_id> [<other_module_id>...]');
        $oOutput->writeLn();
        $oOutput->writeLn('This command fixes information stored in database of modules');
        $oOutput->writeln();
        $oOutput->writeLn('Available options:');
        $oOutput->writeLn('  -a, --all         Passes all modules');
        $oOutput->writeLn('  -b, --base-shop   Fix only on base shop');
        $oOutput->writeLn('  --shop=<shop_id>  Specifies in which shop to fix states');
        $oOutput->writeLn('  -n, --no-debug    No debug output');
    }

    /**
     * {@inheritdoc}
     */
    public function execute(oxIOutput $oOutput)
    {
        $oInput = $this->getInput();
        $oDebugOutput = $oInput->hasOption(array('n', 'no-debug'))
            ? oxNew('oxNullOutput')
            : $oOutput;

        try {
            $aShopConfigs = $this->_parseShopConfigs();
        } catch (oxInputException $oEx) {
            $oOutput->writeLn($oEx->getMessage());

            return;
        }

        /** @var oxModuleStateFixer $oModuleStateFixer */
        $oModuleStateFixer = oxRegistry::get('oxModuleStateFixer');
        $oModuleStateFixer->setDebugOutput($oDebugOutput);

        /** @var oxModule $oModule */
        $oModule = oxNew('oxModule');
        foreach ($aShopConfigs as $oConfig) {
            try {
                $aModuleIds = $this->_parseModuleIds($oConfig);
            } catch (oxInputException $oEx) {
                $oOutput->writeLn($oEx->getMessage());

                return;
            }
            $sShopId = $oConfig->getShopId();
            $oDebugOutput->writeLn('[DEBUG] Working on shop id ' . $sShopId);
            $oModuleStateFixer->setConfig($oConfig);
            $oModuleStateFixer->setDebugOutput($oDebugOutput);

            foreach ($aModuleIds as $sModuleId) {
                if (!$oModule->load($sModuleId)) {
                    $oDebugOutput->writeLn("[DEBUG] {$sModuleId} can not be loaded - skipping");
                    continue;
                }

                //$oDebugOutput->writeLn("[DEBUG] Fixing {$sModuleId} module");
                $blWasActive = $oModule->isActive();

                try {
                    if ($oModuleStateFixer->fix($oModule, $oConfig)) {
                        $oDebugOutput->writeLn("[DEBUG] {$sModuleId} extensions fixed");
                        if (!$blWasActive && $oModule->isActive()) {
                            $oDebugOutput->writeLn("[WARN] {$sModuleId} is now activated again!");
                        }
                    }
                } catch (oxShopException $ex) {
                    $oDebugOutput->writeLn();
                    $oOutput->writeLn("[ERROR]:" . $ex->getMessage());
                    $oOutput->writeLn("No success! You have to fix that errors manually!!\n");
                    exit(1);
                }
            }

            $oDebugOutput->writeLn();

            $this->cleanup($oConfig, $oDebugOutput);
        }

        $oOutput->writeLn('Fixed module states successfully');
    }

    /**
     * Parse and return module ids from input
     *
     * @return array
     *
     * @throws oxInputException
     */
    protected function _parseModuleIds($oConfig)
    {
        $oInput = $this->getInput();

        if ($oInput->hasOption(array('a', 'all'))) {
            return $this->_getAvailableModuleIds($oConfig);
        }

        if (count($oInput->getArguments()) < 2) { // Note: first argument is command name
            /** @var oxInputException $oEx */
            $oEx = oxNew('oxInputException');
            $oEx->setMessage('Please specify at least one module if as argument or use --all (-a) option');
            throw $oEx;
        }

        $aModuleIds = $oInput->getArguments();
        array_shift($aModuleIds); // Getting rid of command name argument

        $aAvailableModuleIds = $this->_getAvailableModuleIds($oConfig);

        // Checking if all provided module ids exist
        foreach ($aModuleIds as $sModuleId) {
            if (!in_array($sModuleId, $aAvailableModuleIds)) {
                /** @var oxInputException $oEx */
                $oEx = oxNew('oxInputException');
                $oEx->setMessage("{$sModuleId} module does not exist");
                throw $oEx;
            }
        }

        return $aModuleIds;
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
        $oInput = $this->getInput();

        if ($oInput->hasOption(array('b', 'base-shop'))) {
            return array(oxRegistry::getConfig());
        }

        if ($mShopId = $oInput->getOption('shop')) {
            // No value for option were passed
            if (is_bool($mShopId)) {
                /** @var oxInputException $oEx */
                $oEx = oxNew('oxInputException');
                $oEx->setMessage('Please specify shop id in option following this format --shop=<shop_id>');
                throw $oEx;
            }

            if ($oConfig = oxSpecificShopConfig::get($mShopId)) {
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
    protected function _getAvailableModuleIds($oConfig)
    {
        // We are calling getModulesFromDir() because we want to refresh
        // the list of available modules. This is a workaround for OXID
        // bug.
        $oModuleList = oxNew('oxModuleList');
        $oModuleList->setConfig($oConfig);
        $oModuleList->getModulesFromDir($oConfig->getModulesDir());

        $_aAvailableModuleIds = array_keys($oConfig->getConfigParam('aModulePaths'));
        if (!is_array($_aAvailableModuleIds)) {
            $_aAvailableModuleIds = array();
        }

        return $_aAvailableModuleIds;
    }


    /**
     * @param $oDebugOutput
     * @param $oModuleList
     */
    protected function cleanup($oConfig, $oDebugOutput)
    {
        $oModuleList = oxNew("oxModuleList");
        $oModuleList->setConfig($oConfig);

        $aDeletedExt = $oModuleList->getDeletedExtensions();
        if ($aDeletedExt) {
            //collecting deleted extension IDs
            $aDeletedExtIds = array_keys($aDeletedExt);
            foreach ($aDeletedExtIds as $sIdIndex => $sId) {
                $oDebugOutput->writeLn(
                    "[ERROR] Module $sId has errors so module will be removed, including all settings"
                );
                if (isset($aDeletedExt[$sId]['extensions'])) {
                    foreach ($aDeletedExt[$sId]['extensions'] as $sClass => $aExtensions) {
                        foreach ($aExtensions as $sExtension) {
                            $sExtPath = $oConfig->getModulesDir() . $sExtension . '.php';
                            $oDebugOutput->writeLn("[ERROR] $sExtPath not found");
                        }
                    }
                }
            }
        }

        $oModuleList->cleanup();
    }
}
