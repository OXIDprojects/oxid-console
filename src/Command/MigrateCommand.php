<?php

/**
 * @copyright OXID eSales AG, All rights reserved
 * @author OXID Professional services
 *
 * See LICENSE file for license details.
 */

namespace OxidProfessionalServices\OxidConsole\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use OxidEsales\Eshop\Core\Registry;
use OxidProfessionalServices\OxidConsole\Core\Exception\ConsoleException;
use OxidProfessionalServices\OxidConsole\Core\Migration\MigrationHandler;
use OxidProfessionalServices\OxidConsole\Core\Migration\AbstractQuery;

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
            ->addArgument('timestamp', InputArgument::OPTIONAL, "Migration to use for execution")
            ->addOption('skip-module-migration', null, InputOption::VALUE_NONE, "Skip migration files from modules")
            ->setHelp(<<<'EOF'
Command <info>%command.name%</info> will skip migration files from modules.
EOF
            );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $timestamp = $this->parseTimestamp($input->getArgument('timestamp'));
        } catch (ConsoleException $oEx) {
            $output->writeln($oEx->getMessage());
            exit(1);
        }

        $output->writeln('Running migration scripts');

        $debugOutput = $input->getOption('verbose')
            ? $output
            : new NullOutput();

        /** @var MigrationHandler $oMigrationHandler */
        if ($input->getOption('skip-module-migration')) {
            $output->writeln('Skipping Module Migration...');
            MigrationHandler::$skipModuleMigration = true;
        }

        $output->writeln("NOTE: Running module migrations is currently an experimental feature.");
        $output->writeln("      You can skip this by using '--skip-module-migration'.");
        
        $oMigrationHandler = Registry::get(MigrationHandler::class);
        $oMigrationHandler->run($timestamp, $debugOutput);

        $output->writeln('Migration finished successfully');
    }

    /**
     * Parse timestamp from user input
     *
     * @param string|null $timestamp
     *
     * @return string
     * @throws ConsoleException
     */
    protected function parseTimestamp($timestamp)
    {
        if (is_null($timestamp)) {
            return AbstractQuery::getCurrentTimestamp();
        }

        if (!AbstractQuery::isValidTimestamp($timestamp)) {
            if ($sTime = strtotime($timestamp)) {
                $timestamp = date('YmdHis', $sTime);
            } else {
                throw oxNew(
                    ConsoleException::class,
                    'Invalid timestamp format, use YYYYMMDDhhmmss format'
                );
            }
        }

        return $timestamp;
    }
}
