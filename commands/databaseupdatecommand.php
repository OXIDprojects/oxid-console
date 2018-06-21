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
            ->setName('database:views')
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
        $output->writeLn('Updating database views...');
        $config = oxRegistry::getConfig();
        //avoid problems if views are already broken
        $config->setConfigParam('blSkipViewUsage', true);
        
        /** @var oxDbMetaDataHandler $oDbHandler */
        $oDbHandler = oxNew('oxDbMetaDataHandler');

        if (!$oDbHandler->updateViews()) {
            $output->writeLn('Could not update database views!');
            exit(1);
        }

        $output->writeLn('Database views updated successfully');
    }
}
