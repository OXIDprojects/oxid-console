<?php

namespace OxidProfessionalServices\OxidConsole\Core;

use OxidEsales\EshopCommunity\Internal\Framework\Console\AbstractShopAwareCommand;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use OxidProfessionalServices\OxidConsole\Core\Composer\VersionHelper;
use OxidEsales\Eshop\Core\Registry;
use Throwable;
use OxidEsales\Eshop\Core\UtilsObject;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\ConfigFile;

/**
 * Class Application
 */
class Application extends BaseApplication
{
    protected $projectRoot = '';

    /**
     * @param string $projectRoot the root directory of the project that contains vendor and source folder
     */
    public function __construct($projectRoot)
    {
        $this->projectRoot = $projectRoot;
        $name = 'Oxid Professional Services Console';

        $v = new VersionHelper($projectRoot);
        $version = $v->getVersion('oxid-professional-services/oxid-console');
        parent::__construct($name, $version);
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $projectRoot = $this->projectRoot;
        $output->writeln(
            "OXID project root is found at $projectRoot",
            OutputInterface::VERBOSITY_VERBOSE
        );
        chdir($projectRoot);

        $this->loadBootstrap($input, $output);

        $oConfig = Registry::getConfig();
        $aLanguages = $oConfig->getConfigParam('aLanguages');
        $aLanguageParams = $oConfig->getConfigParam('aLanguageParams');

        if (false === $aLanguageParams) {
            echo 'Config Param for aLanguagesParams is broken. Setting default Values to de';
            $oConfig->saveShopConfVar(
                'aarr',
                'aLanguageParams',
                ['de' => ['baseId' => 0 , 'active' => 1 , 'sort' => 1]]
            );
        }
        if (false === $aLanguages) {
            echo 'Config Param for aLanguages is broken. Setting default Values to de';
            $oConfig->saveShopConfVar('aarr', 'aLanguages', ['de' => 'Deutsch']);
        }

        //adding a value to avoid php warnings when oxid core try to compare that value
        $_SERVER['HTTP_HOST'] = 'localhost';
        $output->writeln(
            "collecting comands...",
            OutputInterface::VERBOSITY_VERBOSE
        );
        $commandCollector = new CommandCollector();
        $application = $this;
        $commands = $commandCollector->getAllCommands();
        $output->writeln(
            "commands collected",
            OutputInterface::VERBOSITY_VERBOSE
        );
        foreach ($commands as $command) {
            try {
                $application->add($command);
            } catch (Throwable $e) {
                print get_class($command) . " not loadad " . $e->getMessage() . "\n" . $e->getTraceAsString();
            }
        }
        $output->writeln(
            "commands added",
            OutputInterface::VERBOSITY_VERBOSE
        );
        parent::doRun($input, $output);
    }

   /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null
     */
    public function loadBootstrap(InputInterface $input, OutputInterface $output)
    {
        //set shp patameter only if input parameter is set
        //to allow commands to check this and maybe iterate over all shops if shop parameter is obmitted
        if (true === $input->hasParameterOption(['--shop','-s'])) {
            $_GET['shp'] = $input->getParameterOption(['--shop','-s']);
            $_GET['actshop'] = $input->getParameterOption(['--shop','-s']);
        }

        $output->writeln(
            "Loading OXID bootstrap...",
            OutputInterface::VERBOSITY_VERBOSE
        );
        $possiblePathsForBootstrap = [
            $this->projectRoot . '/source/bootstrap.php',
            ];

        if (($customPathToBootstrap = getenv('BOOTSTRAP_PATH')) !== false) {
            array_unshift($possiblePathsForBootstrap, $customPathToBootstrap);
        }

        foreach ($possiblePathsForBootstrap as $fileToRequire) {
            if (file_exists($fileToRequire)) {
                require_once $fileToRequire;
                break;
            }
        }

        if (!defined('VENDOR_PATH')) {
            echo "Unable to locate valid 'bootstrap.php' in order to load OXID eShop framework.\n";
            echo "Please specify 'BOOTSTRAP_PATH' as environmental variable to use it directly.\n";
            exit(1);
        }
        $output->writeln(
            "OXID bootstrap done",
            OutputInterface::VERBOSITY_VERBOSE
        );
        return;
    }

    /**
     * @param Command $command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Throwable
     */
    public function doRunCommand(Command $command, InputInterface $input, OutputInterface $output)
    {

        if ($command->getDefinition()->hasShortcut('s') || $command->getDefinition()->hasOption('shop')) {
            $logger = new ConsoleLogger($output);
            $logger->critical('your command is using option shop or shortcut s which is already used by the console');
        } else {
            //adding shop paramter to the definition so it is availible in the list command
            //and can be used by commands
            $inputDefinition = $this->getDefinition();
            $inputDefinition->addOption(
                new InputOption(
                    '--shop',
                    '-s',
                    InputOption::VALUE_OPTIONAL,
                    'Shop Id (EE Relevant)'
                )
            );
        }

        return parent::doRunCommand($command, $input, $output);
    }

    /**
     * Completely switch shop
     *
     * @param string $shopId The shop id
     *
     * @return void
     */
    public function switchToShopId($shopId)
    {
        $_GET['shp'] = $shopId;
        $_GET['actshop'] = $shopId;

        $keepThese = [ConfigFile::class];
        $registryKeys = Registry::getKeys();
        foreach ($registryKeys as $key) {
            if (in_array($key, $keepThese)) {
                continue;
            }
            Registry::set($key, null);
        }

        $utilsObject = new UtilsObject();
        $utilsObject->resetInstanceCache();
        Registry::set(UtilsObject::class, $utilsObject);

        \OxidEsales\Eshop\Core\Module\ModuleVariablesLocator::resetModuleVariables();
        Registry::getSession()->setVariable('shp', $shopId);

        //ensure we get rid of all instances of config, even the one in Core\Base
        Registry::set(Config::class, null);
        Registry::getConfig()->setConfig(null);
        Registry::set(Config::class, null);

        $moduleVariablesCache = new \OxidEsales\Eshop\Core\FileCache();
        $shopIdCalculator = new \OxidEsales\Eshop\Core\ShopIdCalculator($moduleVariablesCache);

        if (
            ($shopId != $shopIdCalculator->getShopId())
            || ($shopId != Registry::getConfig()->getShopId())
        ) {
            throw new \Exception('Failed to switch to subshop id ' . $shopId . " Calculate ID: "
                . $shopIdCalculator->getShopId()
                . " Config ShopId: " . Registry::getConfig()->getShopId());
        }
    }
}
