<?php
declare(strict_types = 1);
namespace B13\Warmup\Command;

/*
 * This file is part of TYPO3 CMS-based extension "warmup" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\Warmup\Service\PageWarmupService;
use B13\Warmup\Service\RootlineV8Service;
use B13\Warmup\Service\RootlineWarmupService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Called via cache:warmup
 */
class WarmupCommand extends Command
{
    private SymfonyStyle $io;

    public function configure()
    {
        $this
            ->addArgument(
                'type',
                InputArgument::OPTIONAL,
                'Choose between "rootline" and "pages", or "all"',
                'all'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io->title('Welcome to the Cache Warmup');

        $type = $input->getArgument('type');

        foreach ($this->getWarmupService($type) as $specificType => $service) {
            $this->io->section('Warming up ' . $specificType);
            call_user_func_array([$service, 'warmUp'], [$this->io]);
        }

        $this->io->success('All done');
        return Command::SUCCESS;
    }

    private function getWarmupService(string $type): iterable
    {
        switch ($type) {
            case 'all':
                yield 'rootline' => new RootlineWarmupService();
                yield 'pages' => new PageWarmupService();
                break;
            case 'rootline':
                yield 'rootline' => new RootlineWarmupService();
                break;
            case 'pages':
                yield 'pages' => new PageWarmupService();
                break;
        }
    }
}
