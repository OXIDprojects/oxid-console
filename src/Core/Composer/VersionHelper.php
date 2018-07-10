<?php

/**
 * @copyright OXID eSales AG, All rights reserved
 * @author OXID Professional services
 *
 * See LICENSE file for license details.
 */

namespace OxidProfessionalServices\OxidConsole\Core;

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
        $content = file_get_contents(dirname(dirname(__FILE__)) . '../..' . '/../../../composer.lock');
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
