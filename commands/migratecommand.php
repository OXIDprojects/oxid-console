<?php

/**
 * @copyright OXID eSales AG, All rights reserved
 * @author OXID Professional services
 *
 * See LICENSE file for license details.
 */

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Migrate command
 *
 * Runs migration handler with input timestamp. If no timestamp were passed
 * runs with current timestamp instead
 */
class MigrateCommand extends Command
{

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('migration:run')
            ->setAliases(['migrate'])
            ->setDescription('Run database migration scripts')
            ->addArgument('timestamp', InputArgument::OPTIONAL, "Migration to use for execution");
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $timestamp = $this->_parseTimestamp($input->getArgument('timestamp'));
        } catch (oxConsoleException $oEx) {
            $output->writeLn($oEx->getMessage());
            exit(1);
        }

        $output->writeLn('Running migration scripts');

        $debugOutput = $input->getOption('verbose')
            ? $output
            : new NullOutput();

        /** @var oxMigrationHandler $oMigrationHandler */
        $oMigrationHandler = oxRegistry::get('oxMigrationHandler');
        $oMigrationHandler->run($timestamp, $debugOutput);

        $output->writeLn('Migration finished successfully');
    }

    /**
     * Parse timestamp from user input
     *
     * @param string|null $timestamp
     *
     * @return string
     *
     * @throws oxConsoleException
     */
    protected function _parseTimestamp($timestamp)
    {
        if (is_null($timestamp))
            return oxMigrationQuery::getCurrentTimestamp();

        if (!oxMigrationQuery::isValidTimestamp($timestamp)) {
            if ($sTime = strtotime($timestamp)) {
                $timestamp = date('YmdHis', $sTime);
            } else {
                /** @var oxConsoleException $oEx */
                $oEx = oxNew('oxConsoleException');
                $oEx->setMessage('Invalid timestamp format, use YYYYMMDDhhmmss format');
                throw $oEx;
            }
        }

        return $timestamp;
    }
}
