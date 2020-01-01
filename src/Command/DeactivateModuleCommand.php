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
 * @copyright (C) OXID eSales AG 2003-2019
 */

namespace OxidProfessionalServices\OxidConsole\Command;

use OxidEsales\Eshop\Core\Exception\StandardException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OxidProfessionalServices\OxidConsole\Core\ShopConfig;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Module\ModuleInstaller;
use OxidEsales\Eshop\Core\Module\ModuleList;
use OxidEsales\Eshop\Core\Module\Module;

/**
 * Class DeactivateModuleCommand
 */
class DeactivateModuleCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('module:deactivate')
            ->setDescription('Deactivate module in shop')
            ->addArgument(
                'moduleid',
                InputArgument::REQUIRED,
                "Module name which should be deactivated"
            );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $moduleId = $input->getArgument('moduleid');
        $shopId = $input->hasOption('shop') ? $input->getOption('shop') : null;
        $this->deactivateModule($moduleId, $shopId, $output);
    }

    /**
     * @param string $moduleId
     * @param string $shopId
     * @param OutputInterface $output
     *
     * @throws StandardException
     */
    public function deactivateModule($moduleId, $shopId, $output)
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
            $moduleInstaller->deactivate($module);
        } else {
            $output->writeLn("$moduleId already inactive");
        }
    }
}
