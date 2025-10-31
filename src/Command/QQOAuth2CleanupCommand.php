<?php

namespace Tourze\QQConnectOAuth2Bundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service;

#[AsCommand(
    name: self::NAME,
    description: 'Clean up expired OAuth2 states',
)]
class QQOAuth2CleanupCommand extends Command
{
    protected const NAME = 'qq-oauth2:cleanup';

    public function __construct(
        private QQOAuth2Service $oauth2Service,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $count = $this->oauth2Service->cleanupExpiredStates();

            $io->success(sprintf('Successfully cleaned up %d expired states', $count));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to clean up states: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
