<?php
/**
 * This file is part of OXID Console.
 *
 * OXID Console is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OXID Console is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OXID Console.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author        OXID Professional services
 * @link          http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG 2003-2018
 */

namespace OxidProfessionalServices\OxidConsole\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use OxidProfessionalServices\OxidConsole\Core\ShopConfig;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Module\ModuleInstaller;
use OxidEsales\Eshop\Core\Module\ModuleList;
use OxidEsales\Eshop\Core\Module\Module;

/**
 * Class ActivateModuleCommand
 */
class ActivateModuleCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('module:activate')
            ->setDescription('Activate module in shop')
            ->addOption(
                'shop',
                's',
                InputOption::VALUE_REQUIRED,
                "Specify which shop id should be used for the module activation"
            )->addArgument(
                'moduleid',
                InputArgument::REQUIRED,
                "Module name which should be activated"
            );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $moduleId = $input->getArgument('moduleid');
        $shopId = $input->hasOption('shop') ? $input->getOption('shop') : null;
        $this->activateModule($moduleId, $shopId, $output);
    }

    /**
     * @param string $moduleId
     * @param string $shopId
     * @param OutputInterface $output
     *
     * @throws \OxidEsales\Eshop\Core\Exception\StandardException
     */
    public function activateModule($moduleId, $shopId, $output)
    {
        /** @var ModuleInstaller $moduleInstaller */
        $moduleInstaller = Registry::get(ModuleInstaller::class);

        if ($shopId) {
            $oConfig = ShopConfig::get($shopId);
            $moduleInstaller->setConfig($oConfig);
        }

        $moduleList = oxNew(ModuleList::class);
        $moduleList->getModulesFromDir(Registry::getConfig()->getModulesDir());
        $modules = $moduleList->getList();

        /** @var Module $module */
        $module = $modules[$moduleId];
        if ($module == null) {
            $output->writeLn("$moduleId not found. choose from:");
            $output->writeLn(join("\n", array_keys($modules)));

            exit(1);
        }

        if ($module->isActive()) {
            $output->writeLn("$moduleId already active");
        } else {
            $moduleInstaller->activate($module);
        }
    }
}
