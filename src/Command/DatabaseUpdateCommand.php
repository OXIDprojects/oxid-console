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
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\DbMetaDataHandler;

/**
 * Database update command
 *
 * Updates OXID database views
 */
class DatabaseUpdateCommand extends Command
{

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('views:update')
            ->setAliases(['db:update'])
            ->setDescription('Regenerate database table views')
            ->setHelp(<<<'EOF'
Command <info>%command.name%</info> regenerates table views.

<comment>Table views should be regenerated after changes to database schema.</comment>
EOF
        );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Updating database views...');
        $config = Registry::getConfig();
        //avoid problems if views are already broken
        $config->setConfigParam('blSkipViewUsage', true);
        
        /** @var DbMetaDataHandler $oDbHandler */
        $oDbHandler = oxNew(DbMetaDataHandler::class);

        if (!$oDbHandler->updateViews()) {
            $output->writeln('Could not update database views!');
            exit(1);
        }

        $output->writeln('Database views updated successfully');
    }
}
