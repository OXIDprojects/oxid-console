<?php
/**
 * This Software is the property of OXID eSales and is protected
 * by copyright law - it is NOT Freeware.
 *
 * Any unauthorized use of this software without a valid license key
 * is a violation of the license agreement and will be prosecuted by
 * civil and criminal law.
 *
 * @author        OXID Professional services
 * @link          http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG
 * Created at 7/19/18 2:10 PM by Keywan Ghadami
 */

namespace OxidProfessionalServices\OxidConsole\Core\Module;


use OxidEsales\Eshop\Core\Module\ModuleExtensionsCleaner;
use OxidEsales\Eshop\Core\Registry;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ModuleExtensionCleanerDebug extends ModuleExtensionsCleaner
{
    protected $_debugOutput;

    public function __construct()
    {
        $this->_debugOutput= new NullOutput();
    }

    public function setOutput(OutputInterface $out){

        $this->_debugOutput = $out;
    }

    /**
     * Removes garbage ( module not used extensions ) from all installed extensions list.
     * For example: some classes were renamed, so these should be removed.
     *
     * @param array                                $installedExtensions
     * @param \OxidEsales\Eshop\Core\Module\Module $module
     *
     * @return array
     */
    public function cleanExtensions($installedExtensions, \OxidEsales\Eshop\Core\Module\Module $module)
    {
        $installedExtensions = parent::cleanExtensions($installedExtensions, $module);

        $oModules = oxNew( \OxidEsales\EshopCommunity\Core\Module\ModuleList::class );
        //ids will include garbage in case there are files that not registered by any module
        $ids = $oModules->getModuleIds();

        $config = Registry::getConfig();
        $knownIds = array_keys($config->getConfigParam('aModulePaths'));
        $diff = array_diff($ids,$knownIds);
        if ($diff) {
            foreach ($diff as $item) {
                foreach ($installedExtensions as &$coreClassExtension) {
                    foreach ($coreClassExtension as $i => $ext) {
                        if ($ext === $item) {
                            $this->_debugOutput->writeln("$item will be removed");
                            unset($coreClassExtension[$i]);
                        }
                    }
                }
            }
        }

        return $installedExtensions;
    }

    protected function removeGarbage($aInstalledExtensions, $aarGarbage)
    {
        foreach ($aarGarbage as $moduleId => $aExt) {
            $this->_debugOutput->writeLn("[INFO] removing garbage for module $moduleId: " . join(',', $aExt));
        }
        return parent::removeGarbage($aInstalledExtensions, $aarGarbage);
    }

    /**
     * Returns extension which is no longer in metadata - garbage
     *
     * @param array $moduleMetaDataExtensions  extensions defined in metadata.
     * @param array $moduleInstalledExtensions extensions which are installed
     *
     * @return array
     */
    protected function getModuleExtensionsGarbage($moduleMetaDataExtensions, $moduleInstalledExtensions)
    {

        $garbage = parent::getModuleExtensionsGarbage($moduleMetaDataExtensions, $moduleInstalledExtensions);

        foreach ($moduleInstalledExtensions as $coreClassName => $listOfExtensions) {
            foreach ($listOfExtensions as $extensions) {
                if (! (isset($moduleMetaDataExtensions[$coreClassName]) && $moduleMetaDataExtensions[$coreClassName] == $extensions)) {
                    $garbage[$coreClassName][] = $extensions;
                }
            }
        }

        return $garbage;
    }

    /**
     * Returns extensions list by module id.
     *
     * @param array  $modules  Module array (nested format)
     * @param string $moduleId Module id/folder name
     *
     * @return array
     */
    protected function filterExtensionsByModuleId($modules, $moduleId)
    {
        $modulePaths = \OxidEsales\Eshop\Core\Registry::getConfig()->getConfigParam('aModulePaths');

        $path = '';
        if (isset($modulePaths[$moduleId])) {
            $path = $modulePaths[$moduleId] . '/';
        }

        // TODO: This condition should be removed. Need to check integration tests.
        if (!$path) {
            $path = $moduleId . "/";
        }

        $filteredModules = [];
        foreach ($modules as $class => $extend) {
            foreach ($extend as $extendPath) {
                if (strpos($extendPath, $path) === 0) {
                    $filteredModules[$class][] = $extendPath;
                }
            }
        }

        return $filteredModules;
    }
}