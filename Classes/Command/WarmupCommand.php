<?php

declare(strict_types=1);

namespace B13\Warmup\Command;

/*
 * This file is part of TYPO3 CMS-based extension "warmup" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\Warmup\Service\PageWarmupService;
use B13\Warmup\Service\RootlineWarmupService;
use B13\Warmup\Service\WarmupServiceInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Called via cache:warmupPages
 */
class WarmupCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        protected PageWarmupService $pageWarmupService,
        protected RootlineWarmupService $rootlineWarmupService,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'type',
                InputArgument::OPTIONAL,
                'Choose between "rootline" and "pages", or "all"',
                'all'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Welcome to the Cache Warmup');

        $type = (string)$input->getArgument('type');
        $services = $this->getWarmupServices($type);
        if ($services === []) {
            $this->io->error('Unknown type "' . $type . '", use one of "all", "rootline" or "pages".');
            return Command::INVALID;
        }

        foreach ($services as $specificType => $service) {
            $this->io->section('Warming up ' . $specificType);
            $service->warmUp($this->io);
        }

        $this->io->success('All done');
        return Command::SUCCESS;
    }

    /**
     * @return array<string, WarmupServiceInterface>
     */
    private function getWarmupServices(string $type): array
    {
        return match ($type) {
            'all' => ['rootline' => $this->rootlineWarmupService, 'pages' => $this->pageWarmupService],
            'rootline' => ['rootline' => $this->rootlineWarmupService],
            'pages' => ['pages' => $this->pageWarmupService],
            default => [],
        };
    }
}
