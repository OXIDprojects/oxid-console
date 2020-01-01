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
use Symfony\Component\Console\Question\Question;
use OxidEsales\Eshop\Core\Registry;
use OxidProfessionalServices\OxidConsole\Core\Migration\AbstractQuery;

/**
 * Generate migration console command
 */
class GenerateMigrationCommand extends Command
{
    /** @var InputInterface */
    private $input;

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('migration:generate')
            ->setAliases(['g:migration'])
            ->setDescription('Generate new migration file')
            ->addArgument('name', InputArgument::OPTIONAL, "Name for the migration")
            ->setHelp(<<<'EOF'
Command <info>%command.name%</info> will generate new database migration file.

<comment>If <info>name</info> argument is not provided a prompt for it will be given.</comment>
EOF
            );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;

        $sMigrationsDir = OX_BASE_PATH . 'migration' . DIRECTORY_SEPARATOR;
        $sTemplatePath = $this->getTemplatePath();

        $sMigrationName = $this->parseMigrationNameFromInput();
        if (!$sMigrationName) {
            $questionHelper = $this->getHelper('question');
            $question = new Question('Enter short description for migration: ');
            do {
                $words = explode(" ", $questionHelper->ask($input, $output, $question));
                $sMigrationName = $this->buildMigrationName($words);
            } while (!$sMigrationName);
        }

        $sMigrationFileName = AbstractQuery::getCurrentTimestamp() . '_' . strtolower($sMigrationName) . '.php';
        $sMigrationFilePath = $sMigrationsDir . $sMigrationFileName;

        /** @var Smarty $oSmarty */
        $oSmarty = Registry::get('oxUtilsView')->getSmarty();
        $oSmarty->php_handling = SMARTY_PHP_PASSTHRU;
        $oSmarty->assign('sMigrationName', $sMigrationName);
        $sContent = $oSmarty->fetch($sTemplatePath);

        file_put_contents($sMigrationFilePath, $sContent);

        $output->writeln("Sucessfully generated $sMigrationFileName");
    }

    /**
     * Get template path
     *
     * This allows us to override where template file is stored
     *
     * @return string
     */
    protected function getTemplatePath()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'migration.tpl';
    }

    /**
     * Parse migration name from input arguments
     *
     * @return string
     */
    protected function parseMigrationNameFromInput()
    {
        $words = explode(" ", $this->input->getArgument('name'));

        return $this->buildMigrationName($words);
    }

    /**
     * Build migration name from tokens
     *
     * @param array $words
     *
     * @return string
     */
    protected function buildMigrationName(array $words)
    {
        $sMigrationName = '';

        foreach ($words as $word) {
            if (!$word) {
                continue;
            }

            $sMigrationName .= ucfirst($word);
        }

        return $sMigrationName;
    }
}
