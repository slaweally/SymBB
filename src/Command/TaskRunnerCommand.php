<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mybb:tasks:run',
    description: 'SymBB arka plan görevleri: eski oturumları sil, süresi dolmuş banları kaldır.',
)]
final class TaskRunnerCommand extends Command
{
    private const SESSION_DAYS = 30;

    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sm = $this->connection->createSchemaManager();
        $tables = $sm->listTableNames();

        // Eski oturumları sil (mybb_sessions - time/lastactive 30 günden eski)
        if (in_array('mybb_sessions', $tables, true)) {
            $cutoff = time() - (self::SESSION_DAYS * 24 * 60 * 60);
            $columns = $sm->listTableColumns('mybb_sessions');
            $timeCol = isset($columns['time']) ? 'time' : (isset($columns['lastactive']) ? 'lastactive' : null);
            if ($timeCol !== null) {
                $deleted = $this->connection->executeStatement(
                    "DELETE FROM mybb_sessions WHERE {$timeCol} < :cutoff",
                    ['cutoff' => $cutoff]
                );
                if ($deleted > 0) {
                    $io->text(sprintf('Silinen eski oturum: %d', $deleted));
                }
            }
        }

        // Süresi dolmuş banları kaldır (mybb_banned - lifted < now)
        if (in_array('mybb_banned', $tables, true)) {
            $now = time();
            $expired = $this->connection->fetchAllAssociative(
                'SELECT uid, oldgroup, oldadditionalgroups, olddisplaygroup FROM mybb_banned WHERE lifted > 0 AND lifted < :now',
                ['now' => $now]
            );
            foreach ($expired as $ban) {
                $uid = (int) $ban['uid'];
                $oldgroup = (int) ($ban['oldgroup'] ?? 2);
                $oldadditionalgroups = (string) ($ban['oldadditionalgroups'] ?? '');
                $olddisplaygroup = (int) ($ban['olddisplaygroup'] ?? 0);

                $this->connection->executeStatement(
                    'UPDATE mybb_users SET usergroup = :usergroup, additionalgroups = :additionalgroups, displaygroup = :displaygroup WHERE uid = :uid',
                    [
                        'usergroup' => $oldgroup,
                        'additionalgroups' => $oldadditionalgroups,
                        'displaygroup' => $olddisplaygroup,
                        'uid' => $uid,
                    ]
                );
                $this->connection->executeStatement('DELETE FROM mybb_banned WHERE uid = :uid', ['uid' => $uid]);
            }
            if (count($expired) > 0) {
                $io->text(sprintf('Kaldırılan süresi dolmuş ban: %d', count($expired)));
            }
        }

        $io->info('Görevler tamamlandı.');

        return Command::SUCCESS;
    }
}
