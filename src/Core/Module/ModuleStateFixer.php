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
use OxidEsales\Eshop\Core\SettingsHandler;
use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Module\ModuleCache;
use OxidEsales\Eshop\Core\Module\ModuleInstaller;

/**
 * Module state fixer
 */
class ModuleStateFixer extends ModuleInstaller
{
    /**
     * Fix module states task runs version, extend, files, templates, blocks,
     * settings and events information fix tasks
     *
     * @param Module      $oModule
     * @param Config|null $oConfig If not passed uses default base shop config
     */
    public function fix(Module $oModule, Config $oConfig = null)
    {
        if ($oConfig !== null) {
            $this->setConfig($oConfig);
        }

        $sModuleId = $oModule->getId();

        $this->_deleteBlock($sModuleId);
        $this->_deleteTemplateFiles($sModuleId);
        $this->_deleteModuleFiles($sModuleId);
        $this->_deleteModuleEvents($sModuleId);
        $this->_deleteModuleVersions($sModuleId);

        $this->_addExtensions($oModule);

        $this->_addTemplateBlocks($oModule->getInfo("blocks"), $sModuleId);
        $this->_addModuleFiles($oModule->getInfo("files"), $sModuleId);
        $this->_addTemplateFiles($oModule->getInfo("templates"), $sModuleId);
        $settingsHandler = oxNew(SettingsHandler::class);
        $settingsHandler->setModuleType('module')->run($oModule);
        $this->_addModuleVersion($oModule->getInfo("version"), $sModuleId);
        $this->_addModuleEvents($oModule->getInfo("events"), $sModuleId);

        /** @var ModuleCache $oModuleCache */
        $oModuleCache = oxNew(ModuleCache::class, $oModule);
        $oModuleCache->resetCache();
    }
}
