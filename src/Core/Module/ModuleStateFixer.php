<?php

/**
 * @copyright OXID eSales AG, All rights reserved
 * @author OXID Professional services
 * @author Eligijus Vitkauskas <eligijusvitkauskas@gmail.com>
 *
 * See LICENSE file for license details.
 */

namespace OxidProfessionalServices\OxidConsole\Core\Module;

use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\SettingsHandler;
use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Module\ModuleInstaller;
use OxidEsales\Eshop\Core\Module\ModuleCache;
use OxidEsales\Eshop\Core\Exception\ModuleValidationException;

/**
 * Module state fixer
 */
class ModuleStateFixer extends ModuleInstaller
{
    /**
     * Fix module states task runs version, extend, files, templates, blocks,
     * settings and events information fix tasks
     *
     * @param Module      $module
     * @param Config|null $oConfig If not passed uses default base shop config
     */
    public function fix($module, $oConfig = null)
    {
        if ($oConfig !== null) {
            $this->setConfig($oConfig);
        }

        $moduleId = $module->getId();

        $this->removeModuleInformation($moduleId);
        $this->resetModuleCache($module);
        $this->restoreModuleInformation($module, $moduleId);
        $this->resetModuleCache($module);
    }

    /**
     * Code taken from OxidEsales\EshopCommunity\Core\Module::deactivate
     *
     * @param string $moduleId
     */
    private function removeModuleInformation($moduleId)
    {
        $this->_deleteTemplateFiles($moduleId);
        $this->_deleteModuleFiles($moduleId);
        $this->_deleteModuleEvents($moduleId);
        $this->deleteModuleControllers($moduleId);
    }

    /**
     * Code taken from OxidEsales\EshopCommunity\Core\Module::activate
     *
     * @param Module $module
     * @param string $moduleId
     *
     * @throws \OxidEsales\Eshop\Core\Exception\StandardException
     */
    private function restoreModuleInformation($module, $moduleId)
    {
        $this->_addExtensions($module);
        if (version_compare($module->getMetaDataVersion(), '2.0', '<')) {
            $this->_addModuleFiles($module->getInfo("files"), $moduleId);
        }
        $this->_addTemplateBlocks($module->getInfo("blocks"), $moduleId);
        $this->_addTemplateFiles($module->getInfo("templates"), $moduleId);
        $settingsHandler = oxNew(SettingsHandler::class);
        $settingsHandler->setModuleType('module')->run($module);
        $this->_addModuleVersion($module->getInfo("version"), $moduleId);
        $this->_addModuleExtensions($module->getExtensions(), $moduleId);
        $this->_addModuleEvents($module->getInfo("events"), $moduleId);

        if (version_compare($module->getMetaDataVersion(), '2.0', '>=')) {
            try {
                $this->addModuleControllers($module->getControllers(), $moduleId);
            } catch (ModuleValidationException $exception) {
                print $exception->getMessage();
            }
        }
    }

    /**
     * Reset module cache
     *
     * @param Module $module
     */
    private function resetModuleCache($module)
    {
        $moduleCache = oxNew(ModuleCache::class, $module);
        $moduleCache->resetCache();
    }



    /** @var oxIOutput $_debugOutput */
    protected $_debugOutput;

    public function setDebugOutput(oxIOutput $o)
    {
        $this->_debugOutput = $o;
    }

    /**
     * Add extension to module
     *
     * @param \OxidEsales\Eshop\Core\Module\Module $module
     */
    protected function _addExtensions(\OxidEsales\Eshop\Core\Module\Module $module)
    {
        $aModulesDefault = $this->getConfig()->getConfigParam('aModules');
        $aModules = $this->getModulesWithExtendedClass();
        $aModules = $this->_removeNotUsedExtensions($aModules, $module);


        if ($module->hasExtendClass()) {
            $this->validateMetadataExtendSection($module);
            $aAddModules = $module->getExtensions();
            $aModules = $this->_mergeModuleArrays($aModules, $aAddModules);
        }

        $aModules = $this->buildModuleChains($aModules);
        if ($aModulesDefault != $aModules) {
            $onlyInAfterFix = array_diff($aModules, $aModulesDefault);
            $onlyInBeforeFix = array_diff($aModulesDefault, $aModules);
            if ($this->_debugOutput) {
                $this->_debugOutput->writeLn("[INFO] fixing " . $module->getId());
                foreach ($onlyInAfterFix as $core => $ext) {
                    if ($oldChain = $onlyInBeforeFix[$core]) {
                        $newExt = substr($ext, strlen($oldChain));
                        if (!$newExt) {
                            //$newExt = substr($ext, strlen($oldChain));
                            $this->_debugOutput->writeLn("[INFO] remove ext for $core");
                            $this->_debugOutput->writeLn("[INFO] old: $oldChain");
                            $this->_debugOutput->writeLn("[INFO] new: $ext");
                            //$this->_debugOutput->writeLn("[ERROR] extension chain is corrupted for this module");
                            //return;
                            continue;
                        } else {
                            $this->_debugOutput->writeLn("[INFO] append $core => ...$newExt");
                        }
                        unset($onlyInBeforeFix[$core]);
                    } else {
                        $this->_debugOutput->writeLn("[INFO] add $core => $ext");
                    }
                }
                foreach ($onlyInBeforeFix as $core => $ext) {
                    $this->_debugOutput->writeLn("[INFO] remove $core => $ext");
                }
            }
            $this->_saveToConfig('aModules', $aModules);
        }
    }

    protected function _removeGarbage($aInstalledExtensions, $aarGarbage)
    {
        if ($this->_debugOutput) {
            foreach ($aarGarbage as $moduleId => $aExt) {
                $this->_debugOutput->writeLn("[INFO] removing garbage for module $moduleId: " . join(',', $aExt));
            }
        }
        return parent::_removeGarbage($aInstalledExtensions, $aarGarbage);
    }

    /**
     * Add module templates to database.
     *
     * @deprecated please use setTemplateBlocks this method will be removed because
     * the combination of deleting and adding does unnessery writes and so it does not scale
     * also it's more likely to get race conditions (in the moment the blocks are deleted)
     *
     * @param array  $moduleBlocks Module blocks array
     * @param string $moduleId     Module id
     */
    protected function _addTemplateBlocks($moduleBlocks, $moduleId)
    {
        $this->setTemplateBlocks($moduleBlocks, $moduleId);
    }

    /**
     * Set module templates in the database.
     * we do not use delete and add combination because
     * the combination of deleting and adding does unnessery writes and so it does not scale
     * also it's more likely to get race conditions (in the moment the blocks are deleted)
     * @todo extract oxtplblocks query to ModuleTemplateBlockRepository
     *
     * @param array  $moduleBlocks Module blocks array
     * @param string $moduleId     Module id
     */
    protected function setTemplateBlocks($moduleBlocks, $moduleId)
    {
        if (!is_array($moduleBlocks)) {
            $moduleBlocks = array();
        }
        $shopId = $this->getConfig()->getShopId();
        $db = \OxidEsales\Eshop\Core\DatabaseProvider::getDb();
        $knownBlocks = ['dummy']; // Start with a dummy value to prevent having an empty list in the NOT IN statement.

        foreach ($moduleBlocks as $moduleBlock) {
            $blockId = md5($moduleId . json_encode($moduleBlock) . $shopId);
            $knownBlocks[] = $blockId;

            $template = $moduleBlock["template"];
            $position = isset($moduleBlock['position']) && is_numeric($moduleBlock['position']) ?
                intval($moduleBlock['position']) : 1;

            $block = $moduleBlock["block"];
            $filePath = $moduleBlock["file"];
            $theme = isset($moduleBlock['theme']) ? $moduleBlock['theme'] : '';

            $sql = "INSERT INTO `oxtplblocks` (`OXID`, `OXACTIVE`, `OXSHOPID`, `OXTHEME`, `OXTEMPLATE`, `OXBLOCKNAME`, `OXPOS`, `OXFILE`, `OXMODULE`)
                     VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                      `OXID` = VALUES(OXID),
                      `OXACTIVE` = VALUES(OXACTIVE),
                      `OXSHOPID` = VALUES(OXSHOPID),
                      `OXTHEME` = VALUES(OXTHEME),
                      `OXTEMPLATE` = VALUES(OXTEMPLATE),
                      `OXBLOCKNAME` = VALUES(OXBLOCKNAME),
                      `OXPOS` = VALUES(OXPOS),
                      `OXFILE` = VALUES(OXFILE),
                      `OXMODULE` = VALUES(OXMODULE)";

            $db->execute($sql, array(
                $blockId,
                $shopId,
                $theme,
                $template,
                $block,
                $position,
                $filePath,
                $moduleId
            ));
        }

        $listOfKnownBlocks = join(',', $db->quoteArray($knownBlocks));
        $deleteblocks = "DELETE FROM oxtplblocks WHERE OXSHOPID = ? AND OXMODULE = ? AND OXID NOT IN ({$listOfKnownBlocks});";

        $db->execute(
            $deleteblocks,
            array($shopId, $moduleId)
        );


    }

    /**
     * FIX that moduleid is used instead of modulpath https://github.com/OXID-eSales/oxideshop_ce/pull/333
     * Filter module array using module id
     *
     * @param array  $aModules  Module array (nested format)
     * @param string $sModuleId Module id/folder name
     *
     * @return array
     */
    protected function _filterModuleArray($aModules, $sModuleId)
    {
        $aModulePaths = $this->getConfig()->getConfigParam('aModulePaths');
        $sPath = $aModulePaths[$sModuleId];
        if (!$sPath) {
            $sPath = $sModuleId;
        }
        $sPath .= "/";
        $aFilteredModules = array();
        foreach ($aModules as $sClass => $aExtend) {
            foreach ($aExtend as $sExtendPath) {
                if (strpos($sExtendPath, $sPath) === 0) {
                    $aFilteredModules[$sClass][] = $sExtendPath;
                }
            }
        }
        return $aFilteredModules;
    }

}
