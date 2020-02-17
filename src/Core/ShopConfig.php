<?php

/**
 * @copyright OXID eSales AG, All rights reserved
 * @author OXID Professional services
 * @author Eligijus Vitkauskas <eligijusvitkauskas@gmail.com>
 *
 * See LICENSE file for license details.
 */

namespace OxidProfessionalServices\OxidConsole\Core;

use oxDb;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\ConnectionException;

/**
 * Specific shop config class
 *
 * Helper class for generating Config instance for specific shop
 */
class ShopConfig extends Config
{

    /**
     * @var int
     */
    protected $iShopId;

    /**
     * the config params before child theme overloaded them
     * @var array
     */
    protected $aConfigParamsParentTheme;

    /**
     * Constructor
     *
     * @param $iShopId
     */
    public function __construct($iShopId)
    {
        print "Using this shop config class is deprecated\n";
        print "Please extract what you need from this class to a lib or to your application\n";
        $this->iShopId = $iShopId;
        $this->init();
    }

    /**
     * Returns config arrays for all shops
     *
     * @return ShopConfig[]
     */
    public static function getAll()
    {
        $aShopIds = DatabaseProvider::getDb()->getCol('SELECT oxid FROM oxshops');
        $aConfigs = array();

        foreach ($aShopIds as $mShopId) {
            // Note: not using static::get() for avoiding checking of is shop id valid
            $aConfigs[] = new ShopConfig($mShopId);
        }

        return $aConfigs;
    }

    /**
     * Get config object of given shop id
     *
     * @param string|integer $mShopId
     *
     * @return ShopConfig|null
     */
    public static function get($mShopId)
    {
        $sSQL = 'SELECT 1 FROM oxshops WHERE oxid = %s';
        $oDb = DatabaseProvider::getDb();

        if (!$oDb->getOne(sprintf($sSQL, $oDb->quote($mShopId)))) { // invalid shop id
            // Not using oxConfig::_isValidShopId() because its not static, but YES it should be
            return null;
        }

        return new ShopConfig($mShopId);
    }

    /**
     * {@inheritdoc}
     *
     * @return null|void
     */
    public function init()
    {
        // Duplicated init protection
        if ($this->_blInit) {
            return;
        }
        $this->_blInit = true;

        $this->_loadVarsFromFile();
        $this->_setDefaults();
        $this->storedVarTypes = $this->getStoredVarTypes();

        try {
            $sShopID = $this->getShopId();
            $blConfigLoaded = $this->_loadVarsFromDb($sShopID);

            // loading shop config
            if (empty($sShopID) || !$blConfigLoaded) {
                throw oxNew(ConnectionException::class, "Unable to load shop config values from database");
            }

            // loading theme config options
            $this->_loadVarsFromDb(
                $sShopID,
                null,
                Config::OXMODULE_THEME_PREFIX . $this->getConfigParam('sTheme')
            );

            $this->aConfigParamsParentTheme = $this->_aConfigParams;

            // checking if custom theme (which has defined parent theme) config options should be loaded
            // over parent theme (#3362)
            if ($this->getConfigParam('sCustomTheme')) {
                $this->_loadVarsFromDb(
                    $sShopID,
                    null,
                    Config::OXMODULE_THEME_PREFIX . $this->getConfigParam('sCustomTheme')
                );
            }

            // loading modules config
            $this->_loadVarsFromDb($sShopID, null, Config::OXMODULE_MODULE_PREFIX);

            $aOnlyMainShopVars = array('blMallUsers', 'aSerials', 'IMD', 'IMA', 'IMS');
            $this->_loadVarsFromDb($this->getBaseShopId(), $aOnlyMainShopVars);
        } catch (ConnectionException $oEx) {
            $oEx->debugOut();
            Registry::getUtils()->showMessageAndExit($oEx->getString());
        }
    }

    /**
     * Get shop id
     *
     * @return int
     */
    public function getShopId()
    {
        return $this->iShopId;
    }


    /**
     *  Getting all the stored variable type info from database
     *  to be able to check if there was a type change
     *  this helps to improve performance when saving a huge amount of config values
     *  (e.g. module:fix or config importers)
     */
    protected function getStoredVarTypes()
    {
        $db = oxDb::getDb(oxDb::FETCH_MODE_ASSOC);
        $sQ = "select CONCAT(oxvarname,'+',oxmodule) as mapkey, oxvartype from oxconfig where oxshopid = ?";
        $allRows = $db->getAll($sQ, [$this->iShopId]);
        $map = [];
        foreach ($allRows as $row) {
            $map[$row['mapkey']] = $row['oxvartype'];
        }
        return $map;
    }

    public function getShopConfType($sVarName, $sSectionModule)
    {
        return $this->storedVarTypes[$sVarName . '+' . $sSectionModule];
    }

    /**
     * overwritten method for performance reasons
     * @param $sVarType
     * @param $sVarName
     * @param $sVarVal
     * @param null $sShopId
     * @param string $sModule
     * @return bool
     */
    public function saveShopConfVar($sVarType, $sVarName, $sVarVal, $sShopId = null, $sModule = '')
    {
        $sShopId = $sShopId === null ? $this->iShopId : $sShopId;
        if ($sShopId == $this->iShopId) {
            $storedType = $this->getShopConfType($sVarName, $sModule);
            if ($sModule == Config::OXMODULE_THEME_PREFIX . $this->getConfigParam('sTheme')) {
                $storedValue = $this->aConfigParamsParentTheme[$sVarName];
            } else {
                $storedValue = $this->getConfigParam($sVarName);
            }
            if ($sVarType == 'bool') {
                //some modules that have all kinds of bool representations in metadata.php may cause
                //$sVarVal to something else then a boolean, converting the value like parent::saveShopConfVar
                //would do so we can compare it to the strored representation
                $sVarVal = (($sVarVal == 'true' || $sVarVal) && $sVarVal && strcasecmp($sVarVal, "false"));
            }

            if ($sVarType == $storedType && $sVarVal == $storedValue) {
                return false;
            }
        }
        parent::saveShopConfVar($sVarType, $sVarName, $sVarVal, $sShopId, $sModule);
        return true;
    }
}
