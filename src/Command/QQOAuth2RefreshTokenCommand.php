<?php

namespace Tourze\QQConnectOAuth2Bundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2UserRepository;
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service;

#[AsCommand(
    name: 'qq-oauth2:refresh-token',
    description: 'Refresh OAuth2 access tokens',
)]
class QQOAuth2RefreshTokenCommand extends Command
{
    public function __construct(
        private QQOAuth2Service $oauth2Service,
        private QQOAuth2UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('openid', InputArgument::OPTIONAL, 'Specific OpenID to refresh')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Refresh all expired tokens')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be refreshed without actually doing it')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $openid = $input->getArgument('openid');
        $all = $input->getOption('all');
        $dryRun = $input->getOption('dry-run');

        if (!$openid && !$all) {
            $io->error('You must specify either an OpenID or use --all option');
            return Command::FAILURE;
        }

        if ($openid) {
            return $this->refreshSingleToken($openid, $io, $dryRun);
        }

        return $this->refreshAllExpiredTokens($io, $dryRun);
    }

    private function refreshSingleToken(string $openid, SymfonyStyle $io, bool $dryRun): int
    {
        $user = $this->userRepository->findByOpenid($openid);
        
        if (!$user) {
            $io->error(sprintf('User with OpenID %s not found', $openid));
            return Command::FAILURE;
        }

        if (!$user->getRefreshToken()) {
            $io->warning(sprintf('User %s does not have a refresh token', $openid));
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->info(sprintf('Would refresh token for user %s', $openid));
            return Command::SUCCESS;
        }

        try {
            $success = $this->oauth2Service->refreshToken($openid);
            
            if ($success) {
                $io->success(sprintf('Successfully refreshed token for user %s', $openid));
                return Command::SUCCESS;
            } else {
                $io->error(sprintf('Failed to refresh token for user %s', $openid));
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error(sprintf('Error refreshing token: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function refreshAllExpiredTokens(SymfonyStyle $io, bool $dryRun): int
    {
        $users = $this->userRepository->findAll();
        $expiredCount = 0;
        $refreshedCount = 0;
        $failedCount = 0;

        foreach ($users as $user) {
            if (!$user->isTokenExpired() || !$user->getRefreshToken()) {
                continue;
            }

            $expiredCount++;

            if ($dryRun) {
                $io->info(sprintf('Would refresh token for user %s', $user->getOpenid()));
                continue;
            }

            try {
                $success = $this->oauth2Service->refreshToken($user->getOpenid());
                
                if ($success) {
                    $refreshedCount++;
                    $io->info(sprintf('Refreshed token for user %s', $user->getOpenid()));
                } else {
                    $failedCount++;
                    $io->warning(sprintf('Failed to refresh token for user %s', $user->getOpenid()));
                }
            } catch (\Exception $e) {
                $failedCount++;
                $io->error(sprintf('Error refreshing token for user %s: %s', $user->getOpenid(), $e->getMessage()));
            }
        }

        if ($dryRun) {
            $io->success(sprintf('Would refresh %d expired tokens', $expiredCount));
        } else {
            $io->success(sprintf(
                'Refreshed %d tokens successfully, %d failed out of %d expired tokens',
                $refreshedCount,
                $failedCount,
                $expiredCount
            ));
        }

        return $failedCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}