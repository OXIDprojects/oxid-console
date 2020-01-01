<?php

/**
 * @copyright OXID eSales AG, All rights reserved
 * @author OXID Professional services
 * @author Eligijus Vitkauskas <eligijusvitkauskas@gmail.com>
 *
 * See LICENSE file for license details.
 */

namespace OxidProfessionalServices\OxidConsole\Core\Migration;

use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Symfony\Component\Console\Output\OutputInterface;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidProfessionalServices\OxidConsole\Core\Exception\MigrationException;

/**
 * Migration handler for migration queries
 *
 * Only one instance of this class is allowed
 *
 * Sample usage:
 *      $migrationHandler = OxidProfessionalServices\OxidConsole\Core\Migration\MigrationHandler::getInstance()
 *      $migrationHandler->run( '2014030709325468' );
 */
class MigrationHandler
{
    /**
     * @var bool Object already created?
     */
    protected static $created = false;

    /**
     * @var string Full path of cache file
     */
    protected $cacheFilePath;

    /**
     * @var string Directory where migration paths are stored
     */
    protected $migrationQueriesDir;

    /**
     * @var array Executed queries
     */
    protected $executedQueryNames = array();

    /**
     * @var AbstractQuery[]
     */
    protected $queries = array();

    /**
     * Constructor.
     *
     * Loads migration queries cache and builds migration queries objects
     *
     * @throws MigrationException
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function __construct()
    {
        if (static::$created) {
            throw new MigrationException('Only one instance for MigrationHandler allowed');
        }

        // Create BC alias for oxMigrationQuery class (old migrations)
        if (!class_exists('oxMigrationQuery')) {
            class_alias(AbstractQuery::class, 'oxMigrationQuery');
        }

        static::$created = true;

        $createSql = '
            CREATE TABLE IF NOT EXISTS `oxmigrationstatus` (
                `OXID` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `version` varchar(255) NOT NULL UNIQUE,
                `executed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT=\'Stores the migrationstatus\';
        ';

        $oDb = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);
        $oDb->execute($createSql);

        $this->executedQueryNames = $oDb->getAll('SELECT * FROM oxmigrationstatus');
        $this->migrationQueriesDir = OX_BASE_PATH . 'migration' . DIRECTORY_SEPARATOR;

        $this->buildMigrationQueries();
    }

    /**
     * Run migration
     *
     * @param string|null    $timestamp The time at which the migrations aims. Only migrations
     * up to this point are being executed
     * @param OutputInterface|null $oOutput    Out handler for console output
     */
    public function run($timestamp = null, OutputInterface $oOutput = null)
    {
        if (null === $timestamp) {
            $timestamp = AbstractQuery::getCurrentTimestamp();
        }

        foreach ($this->getQueries() as $oQuery) {
            $oQuery->getTimestamp() < $timestamp
                ? $this->goUp($oQuery, $oOutput)
                : $this->goDown($oQuery, $oOutput);
        }
    }

    /**
     * Executes an UP Migration
     *
     * @param AbstractQuery    $oQuery  The query object that is being executed
     * @param OutputInterface  $oOutput The output handler for the console output that might be generated
     *
     * @return bool
     */
    protected function goUp(AbstractQuery $oQuery, outputInterface $oOutput = null)
    {
        if ($this->isExecuted($oQuery)) {
            return false;
        }

        if ($oOutput) {
            $oOutput->writeln(
                sprintf(
                    '[DEBUG] Migrating up %s %s',
                    $oQuery->getTimestamp(),
                    $oQuery->getClassName()
                )
            );
        }

        $oQuery->up();
        $this->setExecuted($oQuery);

        return true;
    }

    /**
     * Executes a DOWN Migration
     *
     * @param AbstractQuery    $oQuery  The query object that is being executed
     * @param OutputInterface  $oOutput The output handler for the console output that might be generated
     *
     * @return bool
     */
    protected function goDown(AbstractQuery $oQuery, OutputInterface $oOutput = null)
    {
        if (!$this->isExecuted($oQuery)) {
            return false;
        }

        if ($oOutput) {
            $oOutput->writeln(
                sprintf(
                    '[DEBUG] Migrating down %s %s',
                    $oQuery->getTimestamp(),
                    $oQuery->getClassName()
                )
            );
        }

        $oQuery->down();
        $this->setUnexecuted($oQuery);

        return true;
    }

    /**
     * Is query already executed?
     *
     * @param AbstractQuery $oQuery The query object that is being checked for
     *
     * @return bool
     */
    public function isExecuted(AbstractQuery $oQuery)
    {
        foreach ($this->executedQueryNames as $executedQuery) {
            if ($oQuery->getFilename() == $executedQuery['version']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set query as executed
     *
     * @param AbstractQuery $oQuery The query object that is being set to executed
     */
    public function setExecuted(AbstractQuery $oQuery)
    {

        $sSQL = 'REPLACE INTO oxmigrationstatus SET version = ?';
        DatabaseProvider::getDb()->execute($sSQL, array($oQuery->getFilename()));
    }

    /**
     * Set query as not executed
     *
     * @param AbstractQuery $oQuery The query object that is being set to not executed
     */
    public function setUnexecuted(AbstractQuery $oQuery)
    {
        $sSQL = 'DELETE FROM oxmigrationstatus WHERE version = ?';
        DatabaseProvider::getDb()->execute($sSQL, array($oQuery->getFilename()));
    }

    /**
     * Load and build migration files
     *
     * @throws MigrationException
     *
     * @return bool
     */
    protected function buildMigrationQueries()
    {
        if (!is_dir($this->migrationQueriesDir)) {
            return false;
        }

        $oDirectory = new RecursiveDirectoryIterator($this->migrationQueriesDir);
        $oFlattened = new RecursiveIteratorIterator($oDirectory);

        $aFiles = new RegexIterator($oFlattened, AbstractQuery::REGEXP_FILE);
        foreach ($aFiles as $sFilePath) {
            include_once $sFilePath;

            $sClassName = $this->getClassNameFromFilePath($sFilePath);

            /** @var AbstractQuery $oQuery */
            $oQuery = oxNew($sClassName);

            $this->addQuery($oQuery);
        }

        return true;
    }

    /**
     * Get migration queries class name parsed from file path
     *
     * @param string $sFilePath The path of the file to extract the class name from
     *
     * @throws MigrationException
     *
     * @return string Class name in lower case most cases
     */
    protected function getClassNameFromFilePath($sFilePath)
    {
        $sFileName = basename($sFilePath);
        $aMatches = array();

        if (!preg_match(AbstractQuery::REGEXP_FILE, $sFileName, $aMatches)) {
            throw new MigrationException('Could not extract class name from file name');
        }

        return $aMatches[2] . 'migration';
    }

    /**
     * Set migration queries
     *
     * @param AbstractQuery[] $aQueries An Array of Quries to be stored insite $this->_aQueries
     */
    public function setQueries(array $aQueries)
    {
        $this->queries = $aQueries;
    }

    /**
     * Get migration queries
     *
     * @return AbstractQuery[]
     */
    public function getQueries()
    {
        ksort($this->queries);

        return $this->queries;
    }

    /**
     * Add query
     *
     * @param AbstractQuery $oQuery The query to be added
     */
    public function addQuery($oQuery)
    {
        $this->queries[$oQuery->getTimestamp()] = $oQuery;
    }

    /**
     * Set executed queries
     *
     * @param array $aExecutedQueryNames An array of queries, which should be set to executed
     */
    public function setExecutedQueryNames(array $aExecutedQueryNames)
    {
        $this->executedQueryNames = $aExecutedQueryNames;
    }

    /**
     * Get executed queries
     *
     * @return array
     */
    public function getExecutedQueryNames()
    {
        return $this->executedQueryNames;
    }
}
