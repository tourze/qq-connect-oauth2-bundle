<?php

declare(strict_types=1);

namespace Tourze\QQConnectOAuth2Bundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2UserRepository;
use Tourze\QQConnectOAuth2Bundle\Service\QQOAuth2Service;

#[AsCommand(
    name: self::NAME,
    description: 'Refresh OAuth2 access tokens',
)]
class QQOAuth2RefreshTokenCommand extends Command
{
    protected const NAME = 'qq-oauth2:refresh-token';

    public function __construct(
        private QQOAuth2Service $oauth2Service,
        private QQOAuth2UserRepository $userRepository,
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

        if (null === $openid && false === $all) {
            $io->error('You must specify either an OpenID or use --all option');

            return Command::FAILURE;
        }

        if (null !== $openid) {
            $openidStr = is_string($openid) ? $openid : '';
            $dryRunBool = is_bool($dryRun) ? $dryRun : false;

            return $this->refreshSingleToken($openidStr, $io, $dryRunBool);
        }

        $dryRunBool = is_bool($dryRun) ? $dryRun : false;

        return $this->refreshAllExpiredTokens($io, $dryRunBool);
    }

    private function refreshSingleToken(string $openid, SymfonyStyle $io, bool $dryRun): int
    {
        $user = $this->userRepository->findByOpenid($openid);

        if (null === $user) {
            $io->error(sprintf('User with OpenID %s not found', $openid));

            return Command::FAILURE;
        }

        if (null === $user->getRefreshToken()) {
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
            }
            $io->error(sprintf('Failed to refresh token for user %s', $openid));

            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error(sprintf('Error refreshing token: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    private function refreshAllExpiredTokens(SymfonyStyle $io, bool $dryRun): int
    {
        $users = $this->userRepository->findAll();
        $stats = ['expired' => 0, 'refreshed' => 0, 'failed' => 0];

        foreach ($users as $user) {
            if (!$this->shouldRefreshUserToken($user)) {
                continue;
            }

            ++$stats['expired'];

            if ($dryRun) {
                $this->outputDryRunMessage($io, $user);
                continue;
            }

            $stats = $this->refreshUserToken($user, $io, $stats);
        }

        $this->outputFinalResults($io, $stats, $dryRun);

        return $stats['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function shouldRefreshUserToken(QQOAuth2User $user): bool
    {
        return $user->isTokenExpired() && null !== $user->getRefreshToken();
    }

    private function outputDryRunMessage(SymfonyStyle $io, QQOAuth2User $user): void
    {
        $io->info(sprintf('Would refresh token for user %s', $user->getOpenid()));
    }

    /**
     * @param array<string, int> $stats
     * @return array<string, int>
     */
    private function refreshUserToken(QQOAuth2User $user, SymfonyStyle $io, array $stats): array
    {
        try {
            $success = $this->oauth2Service->refreshToken($user->getOpenid());

            if ($success) {
                ++$stats['refreshed'];
                $io->info(sprintf('Refreshed token for user %s', $user->getOpenid()));
            } else {
                ++$stats['failed'];
                $io->warning(sprintf('Failed to refresh token for user %s', $user->getOpenid()));
            }
        } catch (\Exception $e) {
            ++$stats['failed'];
            $io->error(sprintf('Error refreshing token for user %s: %s', $user->getOpenid(), $e->getMessage()));
        }

        return $stats;
    }

    /**
     * @param array<string, int> $stats
     */
    private function outputFinalResults(SymfonyStyle $io, array $stats, bool $dryRun): void
    {
        if ($dryRun) {
            $io->success(sprintf('Would refresh %d expired tokens', $stats['expired']));
        } else {
            $io->success(sprintf(
                'Refreshed %d tokens successfully, %d failed out of %d expired tokens',
                $stats['refreshed'],
                $stats['failed'],
                $stats['expired']
            ));
        }
    }
}
