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
 * Migration query class. All migration queries must extend this class
 *
 * Migration class filename must match timestamp_classname.php format
 */
abstract class oxDbMigrationQuery extends oxMigrationQuery
{

    /**
     * This method in the subclass must return the list of columns that should be added or removed
     * @return array
     */
    protected function getTables(){return null;}
    protected function getUpFiles(){return null;}

    /* TODO add support for indexes
    protected function getIndexes()
    {
        return [];
    }
    */

    /**
     * @var string $sSql the sql string that's being generated
     */
    protected $sSql = "";

    /**
     * @var array $aSql array of sql parts to be combined
     */
    protected $aSql;

    /**
     * @var bool $blUp up or down migration
     */
    protected $blUp = true;

    /**
     * {@inheritdoc}
     */
    public function up()
    {
        if($this->getTables() !== null) {
            $this->alterTables();
        } else {
            $files = $this->getUpFiles();
            $config = oxRegistry::getConfig();
            $host = $config->getConfigParam('dbHost');
            $user = $config->getConfigParam('dbUser');
            $password = $config->getConfigParam('dbPwd');
            $database = $config->getConfigParam('dbName');
            foreach ($files as $fullpath) {
                //WARNING this code is not portable but do not underestimate to make it portable because PDO
                //http://stackoverflow.com/questions/6203503/invalid-pdo-query-does-not-return-an-error
                $command = "mysql -h $host -u$user -p'$password' $database < $fullpath 2>&1";
                $this->_oOutput->writeLn($command);
                $output = shell_exec($command);
                if ($output) {
                    $this->_oOutput->writeLn($output);
                    throw new Exception("Error importing $fullpath: $output");
                }
            }
        }
    }



    /**
     * iterates over the table definitions and build and execute the sql statements
     */
    protected function alterTables()
    {
        $aTables = $this->getTables();
        $this->sSql = "";
        foreach ($aTables as $sTable => $aTableDef) {
            $this->alterColumns($sTable, $aTableDef['columns']);
            if ($this->sSql != "") {
                $this->sSql = "ALTER TABLE $sTable " . $this->sSql;
            }
            $this->executeSqlStm();
        }
    }

    /**
     * execute a sql statement and rests the sql string
     */
    protected function executeSqlStm($params = null)
    {
        if ($this->sSql != "") {
            $oDb = oxDb::getDb();
            $this->_oOutput->writeLn($this->sSql);
            $oDb->execute($this->sSql, $params);
            $this->sSql = "";
        }
    }

    /**
     * builds the sql to create or drom columns for a table
     * @param string $sTable the name of the table
     * @param array $aColumns the array of columns
     */
    protected function alterColumns($sTable, $aColumns)
    {
        foreach ($aColumns as $aColumnInfo) {
            $this->alterColumn($sTable, $aColumnInfo[0], $aColumnInfo[1]);
        }
        if($this->aSql) {
            $this->sSql = join(',', $this->aSql);
        }
    }

    /**
     * builds the sql to create or drom columns for a table
     * @param string $sTable the name of the table
     * @param string $sColumn the name of the column
     * @param array $sColumnDef the column definition
     */
    protected function alterColumn($sTable, $sColumn, $sColumnDef)
    {

        $blExists = $this->_columnExists($sTable, $sColumn);
        if ($this->blUp) {
            if (!$blExists) {
                $this->aSql[] = "ADD COLUMN `$sColumn` $sColumnDef";
            }
        } else {
            if ($blExists) {
                $this->aSql[] = "DROP COLUMN `$sColumn`";
            }
        }
    }


    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $this->blUp = false;
        if($this->getTables() !== null) {
            $this->alterTables();
        }
    }

    /**
     * executes multiline SQL separated by ;
     * tries to recognise SQL syntax and splits only after a statement even if the statement it self contains a semicolon (;) within the data
     * be warned that this should only be used for simple statements because it is not guarantied to support full sql syntax
     * @param $sql
     */
    public function executeSql($sql, $params = null)
    {
        $statements = preg_split("/;(?=([^\']*\'[^\']*\')*[^\']*$)/", $sql);
        foreach($statements as $statement) {
            $this->sSql = trim($statement);
            $this->executeSqlStm($params);
        }
    }
}
