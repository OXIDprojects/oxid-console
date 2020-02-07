<?php

/**
 * @copyright OXID eSales AG, All rights reserved
 * @author OXID Professional services
 * @author Eligijus Vitkauskas <eligijusvitkauskas@gmail.com>
 *
 * See LICENSE file for license details.
 */

namespace OxidProfessionalServices\OxidConsole\Core\Migration;

use ReflectionClass;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;
use OxidProfessionalServices\OxidConsole\Core\Exception\MigrationException;
use ReflectionException;

/**
 * Migration query class. All migration queries must extend this class
 *
 * Migration class filename must match timestamp_classname.php format
 */
abstract class AbstractQuery
{

    /**
     * Regexp used for regexp timestamp validation
     */
    private const REGEXP_TIMESTAMP = '/^\d{14}$/';

    /**
     * Regexp used for regexp file name validation
     *
     * First match: timestamp
     * Second match: class name without "migration" appended
     */
    public const REGEXP_FILE = '/(\d{14})_([a-zA-Z][a-zA-Z0-9]+)\.php$/';

    /**
     * @var string Timestamp
     */
    protected $timestamp;

    /**
     * @var string Migration query file name
     */
    protected $filename;

    /**
     * @var string Class name in lower case
     */
    protected $className;

    /**
     * Constructor
     *
     * Extracts timestamp from filename of migration query
     *
     * @throws MigrationException
     * @throws ReflectionException
     */
    public function __construct()
    {
        $oReflection = new ReflectionClass($this);
        $sFilename = basename($oReflection->getFileName());
        $aMatches = array();

        if (!preg_match(static::REGEXP_FILE, $sFilename, $aMatches)) {
             throw new MigrationException('Wrong migration query file name');
        }

        $this->setFilename($sFilename);
        $this->setTimestamp($aMatches[1]);
        $this->setClassName($aMatches[2] . 'migration');

        $this->validateClassName();
    }

    /**
     * Validates class name
     *
     * @throws MigrationException
     */
    protected function validateClassName()
    {
        if (strtolower(get_class($this)) != $this->getClassName()) {
            throw new MigrationException(
                'Wrong migration class naming convention. Maybe you forgot to append "Migration"?'
            );
        }
    }

    /**
     * Migrate up
     */
    abstract public function up();

    /**
     * Migrate down
     */
    abstract public function down();

    /**
     * Set timestamp
     *
     * @param string $sTimestamp
     *
     * @throws MigrationException When wrong timestamp format passed
     */
    public function setTimestamp($sTimestamp)
    {
        if (!static::isValidTimestamp($sTimestamp)) {
            throw new MigrationException('Wrong timestamp format passed');
        }

        $this->timestamp = $sTimestamp;
    }

    /**
     * Get timestamp
     *
     * @return string
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Set filename
     *
     * @param string $sFilename
     */
    public function setFilename($sFilename)
    {
        $this->filename = $sFilename;
    }

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Set class name
     *
     * @param string $sClassName
     */
    public function setClassName($sClassName)
    {
        $this->className = strtolower($sClassName);
    }

    /**
     * Get class name
     *
     * @return string in lower case
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * Is valid timestamp for migration query
     *
     * @param $sTimestamp
     *
     * @return int
     */
    public static function isValidTimestamp($sTimestamp)
    {
        return preg_match(self::REGEXP_TIMESTAMP, $sTimestamp);
    }

    /**
     * Get current timestamp
     *
     * @return string
     */
    public static function getCurrentTimestamp()
    {
        return date('YmdHis');
    }

    /**
     * Table exists in database?
     *
     * @param string $sTable Table name
     *
     * @return bool
     */
    protected static function tableExists($sTable)
    {
        $oConfig = Registry::getConfig();
        $sDbName = $oConfig->getConfigParam('dbName');
        $sQuery = "
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = ?
            AND table_name = ?
        ";

        return (bool)DatabaseProvider::getDb()->getOne($sQuery, array($sDbName, $sTable));
    }

    /**
     * Column exists in specific table?
     *
     * @param string $sTable Table name
     * @param string $sColumn Column name
     *
     * @return bool
     */
    protected static function columnExists($sTable, $sColumn)
    {
        $oConfig = Registry::getConfig();
        $sDbName = $oConfig->getConfigParam('dbName');
        $sSql = 'SELECT 1
                    FROM information_schema.COLUMNS
                    WHERE
                        TABLE_SCHEMA = ?
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = ?';

        $oDb = DatabaseProvider::getDb();

        return (bool)$oDb->getOne($sSql, array($sDbName, $sTable, $sColumn));
    }
}
