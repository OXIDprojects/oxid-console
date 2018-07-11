<?php

/**
 * @copyright OXID eSales AG, All rights reserved
 * @author OXID Professional services
 *
 * See LICENSE file for license details.
 */

namespace OxidProfessionalServices\OxidConsole\Core\Composer;

/**
 * Class VersionHelper
 * @package OxidProfessionalServices\OxidConsole\Core
 */
class VersionHelper {

    /**
     * @param $packageName string: the package name
     * e.g. 'oxid-professional-services/oxid-console'
     * @return string: the version number
     */
    function getVersion($packageName)
    {
        $fullPath = __FILE__;
        $vendorDir = dirname(dirname(dirname(dirname(dirname(dirname($fullPath))))));
        $rootDir = dirname($vendorDir);
        $fileName = $rootDir . '/composer.lock';
        $content = file_get_contents($fileName);
        $content = json_decode($content, true);
        $packages = $content['packages'];
        $version = null;
        foreach ($packages as $package) {
            if ($package['name'] == $packageName) {
                $version = $package['version'];
                break;
            }
        }
        return $version;
    }
}
