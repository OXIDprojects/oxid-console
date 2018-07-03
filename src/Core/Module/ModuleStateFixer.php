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
        $this->restoreModuleInformation($module, $moduleId);

        $this->resetCache();
    }

    /**
     * Code taken from OxidEsales\EshopCommunity\Core\Module::deactivate
     *
     * @param string $moduleId
     */
    private function removeModuleInformation($moduleId)
    {
        $this->_deleteBlock($moduleId);
        $this->_deleteTemplateFiles($moduleId);
        $this->_deleteModuleFiles($moduleId);
        $this->_deleteModuleEvents($moduleId);
        $this->_deleteModuleVersions($moduleId);
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
                $this->deactivate($module);
                $lang = Registry::getLang();
                throw oxNew(
                    \OxidEsales\Eshop\Core\Exception\StandardException::class,
                    sprintf(
                        $lang->translateString('ERROR_METADATA_CONTROLLERS_NOT_UNIQUE', null, true),
                        $exception->getMessage()
                    )
                );
            }
        }
    }
}
