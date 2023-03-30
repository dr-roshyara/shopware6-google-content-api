<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\DemodataGeneration\Command;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\DemodataGeneration\Patcher\OrderPatcher;
use Pickware\PickwareErpStarter\DemodataGeneration\Patcher\ProductPatcher;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * This command patches the existing shopware demo data
 */
class PickwareErpShopwareDemodataPatchCommand extends Command
{
    private EntityManager $entityManager;
    private NumberRangeValueGeneratorInterface $numberRangeValueGenerator;

    public function __construct(
        EntityManager $entityManager,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
    }

    protected function configure(): void
    {
        $this->setName('pickware-erp:demodata:patch-shopware-demodata');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pickware ERP demo data patcher');

        $io->warning('This command will overwrite existing data in your system. It should never be run in production.');
        do {
            $answer = mb_strtolower($io->ask('Do you want to continue? [y/n]', 'y'));
        } while ($answer !== 'y' && $answer !== 'n');
        if ($answer !== 'y') {
            return 0;
        }
        $context = Context::createDefaultContext();

        $io->text('Patching products...');
        $productPatcher = new ProductPatcher($this->entityManager, $this->numberRangeValueGenerator);
        $productPatcher->patch($context);

        $io->text('Patching orders...');
        $orderPatcher = new OrderPatcher($this->entityManager, $this->numberRangeValueGenerator);
        $orderPatcher->patch($context);

        $io->success('Done!');

        return 0;
    }
}
