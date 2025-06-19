<?php

namespace Tourze\QQConnectOAuth2Bundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Repository\QQOAuth2ConfigRepository;

#[AsCommand(
    name: self::NAME,
    description: 'Manage QQ OAuth2 configuration',
)]
class QQOAuth2ConfigCommand extends Command
{
    protected const NAME = 'qq-oauth2:config';
    public function __construct(
        private QQOAuth2ConfigRepository $configRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform: create, update, delete, list')
            ->addOption('app-id', null, InputOption::VALUE_REQUIRED, 'QQ App ID')
            ->addOption('app-secret', null, InputOption::VALUE_REQUIRED, 'QQ App Secret')
            ->addOption('scope', null, InputOption::VALUE_OPTIONAL, 'OAuth scopes (comma separated)')
            ->addOption('enabled', null, InputOption::VALUE_OPTIONAL, 'Enable/disable config', true)
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Config ID for update/delete')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        switch ($action) {
            case 'create':
                return $this->createConfig($input, $io);
            case 'update':
                return $this->updateConfig($input, $io);
            case 'delete':
                return $this->deleteConfig($input, $io);
            case 'list':
                return $this->listConfigs($io);
            default:
                $io->error(sprintf('Unknown action: %s. Valid actions are: create, update, delete, list', $action));
                return Command::FAILURE;
        }
    }

    private function createConfig(InputInterface $input, SymfonyStyle $io): int
    {
        $appId = $input->getOption('app-id');
        $appSecret = $input->getOption('app-secret');

        if (!is_string($appId) || !is_string($appSecret) || $appId === '' || $appSecret === '') {
            $io->error('Required options: --app-id, --app-secret');
            return Command::FAILURE;
        }

        $config = new QQOAuth2Config();
        $config->setAppId($appId)
            ->setAppSecret($appSecret);

        $scope = $input->getOption('scope');
        if (is_string($scope) && $scope !== '') {
            $config->setScope($scope);
        }

        $config->setValid($input->getOption('enabled') !== 'false');

        $this->entityManager->persist($config);
        $this->entityManager->flush();

        $io->success(sprintf('QQ OAuth2 config created with ID: %d', $config->getId()));
        return Command::SUCCESS;
    }

    private function updateConfig(InputInterface $input, SymfonyStyle $io): int
    {
        $id = $input->getOption('id');
        if ($id === null) {
            $io->error('Option --id is required for update action');
            return Command::FAILURE;
        }

        $config = $this->configRepository->find($id);
        if ($config === null) {
            $io->error(sprintf('Config with ID %d not found', $id));
            return Command::FAILURE;
        }

        $appId = $input->getOption('app-id');
        if (is_string($appId) && $appId !== '') {
            $config->setAppId($appId);
        }

        $appSecret = $input->getOption('app-secret');
        if (is_string($appSecret) && $appSecret !== '') {
            $config->setAppSecret($appSecret);
        }


        if ($input->hasOption('scope')) {
            $config->setScope($input->getOption('scope'));
        }

        if ($input->hasOption('enabled')) {
            $config->setValid($input->getOption('enabled') !== 'false');
        }

        // TimestampableAware trait will automatically set updateTime
        $this->entityManager->persist($config);
        $this->entityManager->flush();

        $io->success(sprintf('QQ OAuth2 config %d updated', $id));
        return Command::SUCCESS;
    }

    private function deleteConfig(InputInterface $input, SymfonyStyle $io): int
    {
        $id = $input->getOption('id');
        if ($id === null) {
            $io->error('Option --id is required for delete action');
            return Command::FAILURE;
        }

        $config = $this->configRepository->find($id);
        if ($config === null) {
            $io->error(sprintf('Config with ID %d not found', $id));
            return Command::FAILURE;
        }

        $this->entityManager->remove($config);
        $this->entityManager->flush();

        $io->success(sprintf('QQ OAuth2 config %d deleted', $id));
        return Command::SUCCESS;
    }

    private function listConfigs(SymfonyStyle $io): int
    {
        $configs = $this->configRepository->findAll();

        if (empty($configs)) {
            $io->info('No QQ OAuth2 configurations found');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($configs as $config) {
            $rows[] = [
                $config->getId(),
                $config->getAppId(),
                'Auto-generated',
                $config->getScope() ?: 'default',
                $config->isValid() ? 'Yes' : 'No',
                $config->getCreateTime()->format('Y-m-d H:i:s'),
            ];
        }

        $io->table(
            ['ID', 'App ID', 'Type', 'Scope', 'Enabled', 'Created At'],
            $rows
        );

        return Command::SUCCESS;
    }
}