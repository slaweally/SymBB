<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // MyBB tablolarında onlarca NOT NULL kolon var, hepsine default basmak yerine
        // strict mode'u geçici olarak kapatıyoruz — MySQL otomatik default atar.
        $this->connection->executeStatement("SET @OLD_SQL_MODE = @@SQL_MODE");
        $this->connection->executeStatement("SET SQL_MODE = ''");

        $now = time();

        // 1) Admin User (uid=1, usergroup=4)
        $this->connection->executeStatement(
            'INSERT INTO mybb_users (uid, username, loginkey, usergroup, password, salt, email, regdate, signature, buddylist, ignorelist, pmfolders, notepad, usernotes)
             VALUES (:uid, :username, :loginkey, :usergroup, :password, :salt, :email, :regdate, :signature, :buddylist, :ignorelist, :pmfolders, :notepad, :usernotes)
             ON DUPLICATE KEY UPDATE username = VALUES(username)',
            [
                'uid' => 1,
                'username' => 'Admin',
                'loginkey' => bin2hex(random_bytes(25)),
                'usergroup' => 4,
                'password' => '',
                'salt' => '',
                'email' => 'admin@symbb.local',
                'regdate' => $now,
                'signature' => '',
                'buddylist' => '',
                'ignorelist' => '',
                'pmfolders' => '',
                'notepad' => '',
                'usernotes' => '',
            ]
        );

        // 2) Kategoriler (type='c') ve Forumlar (type='f')
        $categories = [
            ['fid' => 1, 'name' => 'Genel', 'description' => '', 'type' => 'c', 'pid' => 0, 'disporder' => 1],
            ['fid' => 2, 'name' => 'Duyurular', 'description' => 'Resmi duyurular ve haberler', 'type' => 'f', 'pid' => 1, 'disporder' => 2],
            ['fid' => 3, 'name' => 'Genel Sohbet', 'description' => 'Her konuda serbest tartışma alanı', 'type' => 'f', 'pid' => 1, 'disporder' => 3],
            ['fid' => 4, 'name' => 'Teknik', 'description' => '', 'type' => 'c', 'pid' => 0, 'disporder' => 4],
            ['fid' => 5, 'name' => 'PHP & Symfony', 'description' => 'PHP, Symfony ve modern web geliştirme', 'type' => 'f', 'pid' => 4, 'disporder' => 5],
            ['fid' => 6, 'name' => 'Veritabanı', 'description' => 'MySQL, PostgreSQL ve diğer DB konuları', 'type' => 'f', 'pid' => 4, 'disporder' => 6],
        ];

        foreach ($categories as $cat) {
            $this->connection->executeStatement(
                'INSERT INTO mybb_forums (fid, name, description, type, pid, disporder, threads, posts)
                 VALUES (:fid, :name, :description, :type, :pid, :disporder, 0, 0)
                 ON DUPLICATE KEY UPDATE name = VALUES(name)',
                $cat
            );
        }

        // 3) Konular (5 adet, forum 2,3,5,6 arasında dağıtılmış)
        $threads = [
            ['tid' => 1, 'fid' => 2, 'subject' => 'SymBB Projesi Yayında!', 'uid' => 1, 'username' => 'Admin'],
            ['tid' => 2, 'fid' => 3, 'subject' => 'Merhaba Dünya — İlk Konu', 'uid' => 1, 'username' => 'Admin'],
            ['tid' => 3, 'fid' => 3, 'subject' => 'Strangler Fig Pattern Nedir?', 'uid' => 1, 'username' => 'Admin'],
            ['tid' => 4, 'fid' => 5, 'subject' => 'Symfony 8 ile Yeni Özellikler', 'uid' => 1, 'username' => 'Admin'],
            ['tid' => 5, 'fid' => 6, 'subject' => 'MySQL Performans İpuçları', 'uid' => 1, 'username' => 'Admin'],
        ];

        foreach ($threads as $t) {
            $this->connection->executeStatement(
                'INSERT INTO mybb_threads (tid, fid, subject, dateline, views, replies, uid, username, firstpost, lastpost, notes)
                 VALUES (:tid, :fid, :subject, :dateline, :views, :replies, :uid, :username, 0, :lastpost, :notes)
                 ON DUPLICATE KEY UPDATE subject = VALUES(subject)',
                [
                    'tid' => $t['tid'],
                    'fid' => $t['fid'],
                    'subject' => $t['subject'],
                    'dateline' => $now - (6 - $t['tid']) * 86400,
                    'views' => random_int(10, 500),
                    'replies' => 0,
                    'uid' => $t['uid'],
                    'username' => $t['username'],
                    'lastpost' => $now - (6 - $t['tid']) * 86400,
                    'notes' => '',
                ]
            );
        }

        // 4) Mesajlar (20 adet, konulara dağıtılmış)
        $messages = [
            'Strangler Fig pattern ile eski kodu adım adım modernize ediyoruz. [b]Harika bir yaklaşım![/b]',
            'Symfony\'nin DI container\'ı gerçekten güçlü. [i]Autowiring[/i] hayat kurtarıyor.',
            '[quote]Eski kod asla ölmez, sadece refactor edilir.[/quote] Katılıyorum!',
            'MyBB\'den Symfony\'ye geçiş düşündüğümüzden çok daha sorunsuz gidiyor.',
            'Doctrine ORM ile legacy tablolara erişim kurmak çocuk oyuncağı oldu.',
            '[url=https://symfony.com]Symfony Docs[/url] her zaman en iyi referans.',
            'PHP 8.4 ile gelen property hooks çok işlevsel.',
            'Bu forumdaki tartismalarin kalitesi gercekten yuksek. Bravo!',
            '[b]SOLID prensipleri[/b] her projede uygulanmalı.',
            'Twig template engine ile çalışmak Blade\'den bile kolay.',
            'MyBB\'nin BBCode sistemi basit ama etkili. [code]echo "Hello";[/code]',
            'Veritabanı indexleme performansı %300 iyileştirebilir.',
            'SELECT * kullanmayın, sadece ihtiyacınız olan kolonları çekin!',
            '[quote=Admin]Strangler Fig pattern ile eski kodu adım adım modernize ediyoruz.[/quote] Harika!',
            'N+1 sorgu problemini Doctrine ile çözmek çok kolay: [b]addSelect + leftJoin[/b]',
            'Guard clauses kullanarak nested if\'lerden kurtulabilirsiniz.',
            'Composer ile dependency management artık standart.',
            'PSR-12 coding standard\'ına uyum şart!',
            'Bu platform Symfony uzerinde calisiyor, inanamiyorum!',
            'Test yazın, gelecekteki kendinize teşekkür edeceksiniz.',
        ];

        $pid = 1;
        $replyCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $postCounts = [2 => 0, 3 => 0, 5 => 0, 6 => 0];

        foreach ($messages as $i => $msg) {
            $tid = ($i % 5) + 1;
            $threadData = $threads[$tid - 1];

            $this->connection->executeStatement(
                'INSERT INTO mybb_posts (pid, tid, uid, username, subject, message, dateline)
                 VALUES (:pid, :tid, :uid, :username, :subject, :message, :dateline)
                 ON DUPLICATE KEY UPDATE message = VALUES(message)',
                [
                    'pid' => $pid,
                    'tid' => $tid,
                    'uid' => 1,
                    'username' => 'Admin',
                    'subject' => 'RE: ' . $threadData['subject'],
                    'message' => $msg,
                    'dateline' => $now - (20 - $i) * 3600,
                ]
            );

            $replyCounts[$tid]++;
            $postCounts[$threadData['fid']] = ($postCounts[$threadData['fid']] ?? 0) + 1;
            $pid++;
        }

        // 5) Thread reply sayılarını güncelle
        foreach ($replyCounts as $tid => $count) {
            $this->connection->executeStatement(
                'UPDATE mybb_threads SET replies = :replies WHERE tid = :tid',
                ['replies' => max(0, $count - 1), 'tid' => $tid]
            );
        }

        // 6) Forum thread ve post sayılarını güncelle
        $forumThreadCounts = [2 => 1, 3 => 2, 5 => 1, 6 => 1];
        foreach ($forumThreadCounts as $fid => $threadCount) {
            $this->connection->executeStatement(
                'UPDATE mybb_forums SET threads = :threads, posts = :posts WHERE fid = :fid',
                ['threads' => $threadCount, 'posts' => $postCounts[$fid] ?? 0, 'fid' => $fid]
            );
        }

        // 7) Forum lastpost verilerini thread'lerden senkronize et
        try {
            $this->connection->executeStatement("
                UPDATE mybb_forums f
                INNER JOIN (
                    SELECT t.fid, t.tid, t.lastpost, t.username AS lp_user, t.uid AS lp_uid, t.subject
                    FROM mybb_threads t
                    INNER JOIN (SELECT fid, MAX(lastpost) AS maxlast FROM mybb_threads WHERE visible = 1 GROUP BY fid) m ON t.fid = m.fid AND t.lastpost = m.maxlast
                    WHERE t.visible = 1
                ) lt ON f.fid = lt.fid
                SET f.lastpost = lt.lastpost, f.lastposter = lt.lp_user, f.lastposteruid = lt.lp_uid, f.lastposttid = lt.tid, f.lastpostsubject = COALESCE(lt.subject, '')
                WHERE f.type = 'f'
            ");
        } catch (\Throwable $e) {
            // lastpost kolonlari yoksa atla
        }

        // SQL_MODE'u geri yükle
        $this->connection->executeStatement("SET SQL_MODE = @OLD_SQL_MODE");
    }
}
