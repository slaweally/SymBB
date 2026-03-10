<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PrivateMessage;
use App\Entity\User;
use App\Repository\PrivateMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PrivateMessageController extends AbstractController
{
    private const FOLDER_NAMES = [
        0 => 'Gelen Kutusu',
        1 => 'Okunmamış',
        2 => 'Gönderilenler',
        3 => 'Taslaklar',
        4 => 'Çöp Kutusu',
    ];

    #[Route('/private.php', name: 'app_private_message', priority: 100)]
    public function __invoke(Request $request, EntityManagerInterface $em, PrivateMessageRepository $pmRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();
        $action = $request->query->getString('action', 'inbox');

        if ($action === 'read') {
            return $this->readAction($request, $em, $user);
        }
        if ($action === 'send') {
            return $this->sendAction($request, $em, $user);
        }
        if ($action === 'folders') {
            return $this->foldersAction($request, $em, $user);
        }
        if ($action === 'do_folders' && $request->isMethod('POST')) {
            return $this->doFoldersAction($request, $em, $user);
        }
        if ($action === 'empty') {
            return $this->emptyAction($request, $pmRepo, $user);
        }
        if ($action === 'do_empty' && $request->isMethod('POST')) {
            return $this->doEmptyAction($request, $em, $pmRepo, $user);
        }
        if ($action === 'tracking') {
            return $this->trackingAction($request, $em, $user);
        }
        if ($action === 'do_stoptracking' && $request->isMethod('POST')) {
            return $this->doStopTrackingAction($request, $em, $user);
        }
        if ($action === 'delete' && $request->isMethod('POST')) {
            return $this->deleteAction($request, $em, $user);
        }
        if ($action === 'export') {
            return $this->exportAction($request, $em, $pmRepo, $user);
        }
        if ($action === 'do_export' && $request->isMethod('POST')) {
            return $this->doExportAction($request, $em, $pmRepo, $user);
        }
        if ($action === 'advanced_search') {
            return $this->advancedSearchAction($request, $em, $user);
        }
        if ($action === 'do_search' && $request->isMethod('POST')) {
            return $this->doSearchAction($request, $em, $pmRepo, $user);
        }
        if ($action === 'results') {
            return $this->searchResultsAction($request, $em, $pmRepo, $user);
        }

        return $this->inboxAction($request, $em, $pmRepo, $user);
    }

    private function parsePmfolders(?string $pmfolders): array
    {
        if ($pmfolders === null || $pmfolders === '') {
            return [
                ['id' => 0, 'name' => self::FOLDER_NAMES[0]],
                ['id' => 1, 'name' => self::FOLDER_NAMES[1]],
                ['id' => 2, 'name' => self::FOLDER_NAMES[2]],
                ['id' => 3, 'name' => self::FOLDER_NAMES[3]],
                ['id' => 4, 'name' => self::FOLDER_NAMES[4]],
            ];
        }
        $folders = [];
        foreach (explode('$%%$', $pmfolders) as $segment) {
            $parts = explode('**', $segment, 2);
            $id = (int) ($parts[0] ?? 0);
            $name = trim($parts[1] ?? '') ?: (self::FOLDER_NAMES[$id] ?? 'Klasör');
            $folders[] = ['id' => $id, 'name' => $name];
        }
        return $folders;
    }

    private function buildPmfoldersString(array $folders): string
    {
        $parts = [];
        foreach ($folders as $f) {
            $parts[] = $f['id'] . '**' . $f['name'];
        }
        return implode('$%%$', $parts);
    }

    private function inboxAction(Request $request, EntityManagerInterface $em, PrivateMessageRepository $pmRepo, User $user): Response
    {
        $fid = $request->query->getInt('fid', 0);
        $folder = ($fid === 0 || $fid === 1) ? 1 : $fid;
        $status = ($fid === 1) ? 0 : null;

        $messages = $pmRepo->findByUserAndFolder($user->getUid(), $folder, $status);

        $loadIds = [];
        foreach ($messages as $m) {
            if ($folder === 2) {
                if ($m->getToid() > 0) {
                    $loadIds[] = $m->getToid();
                }
            } else {
                if ($m->getFromid() > 0) {
                    $loadIds[] = $m->getFromid();
                }
            }
        }
        if (count($loadIds) > 0) {
            $users = $em->getRepository(User::class)->findBy(['uid' => array_unique($loadIds)]);
            $userMap = [];
            foreach ($users as $u) {
                $userMap[$u->getUid()] = $u;
            }
            foreach ($messages as $m) {
                if ($folder === 2) {
                    if (isset($userMap[$m->getToid()])) {
                        $m->setToUser($userMap[$m->getToid()]);
                    }
                } else {
                    if (isset($userMap[$m->getFromid()])) {
                        $m->setFromUser($userMap[$m->getFromid()]);
                    }
                }
            }
        }

        $folders = $this->parsePmfolders($user->getPmfolders());
        $folderTitle = self::FOLDER_NAMES[$fid] ?? 'Mesajlar';
        foreach ($folders as $f) {
            if ($f['id'] === $fid) {
                $folderTitle = $f['name'];
                break;
            }
        }

        return $this->render('default/private/inbox.html.twig', [
            'messages' => $messages,
            'folders' => $folders,
            'current_fid' => $fid,
            'folder_title' => $folderTitle,
        ]);
    }

    private function foldersAction(Request $request, EntityManagerInterface $em, User $user): Response
    {
        $folders = $this->parsePmfolders($user->getPmfolders());

        return $this->render('default/private/folders.html.twig', [
            'folders' => $folders,
        ]);
    }

    private function doFoldersAction(Request $request, EntityManagerInterface $em, User $user): Response
    {
        if (!$this->isCsrfTokenValid('pm_folders', $request->request->getString('_token', ''))) {
            $this->addFlash('error', 'Geçersiz istek.');
            return $this->redirectToRoute('app_private_message', ['action' => 'folders']);
        }

        $folderInput = $request->request->all('folder');
        $highestId = 4;
        $newFolders = [];

        foreach ($folderInput as $key => $name) {
            $name = trim((string) $name);
            if (str_contains($name, '$%%$')) {
                $this->addFlash('error', 'Klasör adında geçersiz karakter.');
                return $this->redirectToRoute('app_private_message', ['action' => 'folders']);
            }
            if (str_starts_with((string) $key, 'new')) {
                if ($name !== '') {
                    $highestId++;
                    $newFolders[] = ['id' => $highestId, 'name' => $name];
                }
            } else {
                $fid = (int) $key;
                if ($fid > $highestId) {
                    $highestId = $fid;
                }
                $defaultName = self::FOLDER_NAMES[$fid] ?? '';
                $finalName = ($name === '' || $name === $defaultName) ? $defaultName : $name;
                if ($fid <= 4) {
                    $newFolders[] = ['id' => $fid, 'name' => $finalName ?: (self::FOLDER_NAMES[$fid] ?? 'Klasör')];
                } elseif ($name !== '') {
                    $newFolders[] = ['id' => $fid, 'name' => $name];
                } else {
                    $pmRepo = $em->getRepository(PrivateMessage::class);
                    $pms = $pmRepo->findBy(['uid' => $user->getUid(), 'folder' => $fid]);
                    foreach ($pms as $pm) {
                        $em->remove($pm);
                    }
                }
            }
        }

        usort($newFolders, fn($a, $b) => $a['id'] <=> $b['id']);
        $user->setPmfolders($this->buildPmfoldersString($newFolders));
        $em->flush();

        $this->addFlash('success', 'Klasörler güncellendi.');
        return $this->redirectToRoute('app_private_message');
    }

    private function emptyAction(Request $request, PrivateMessageRepository $pmRepo, User $user): Response
    {
        $folders = $this->parsePmfolders($user->getPmfolders());
        $counts = [];
        foreach ($folders as $f) {
            $fid = $f['id'];
            if ($fid === 0) {
                $counts[$fid] = $pmRepo->countByUserAndFolder($user->getUid(), 1);
            } elseif ($fid === 1) {
                $counts[$fid] = $pmRepo->countByUserAndFolder($user->getUid(), 1, 0);
            } else {
                $counts[$fid] = $pmRepo->countByUserAndFolder($user->getUid(), $fid);
            }
        }

        return $this->render('default/private/empty.html.twig', [
            'folders' => $folders,
            'counts' => $counts,
        ]);
    }

    private function doEmptyAction(Request $request, EntityManagerInterface $em, PrivateMessageRepository $pmRepo, User $user): Response
    {
        if (!$this->isCsrfTokenValid('pm_empty', $request->request->getString('_token', ''))) {
            $this->addFlash('error', 'Geçersiz istek.');
            return $this->redirectToRoute('app_private_message', ['action' => 'empty']);
        }

        $keepUnread = $request->request->getInt('keepunread') === 1;
        $emptyIds = $request->request->all('empty');
        $deleted = 0;

        foreach ($emptyIds as $fid => $val) {
            if ((int) $val !== 1) {
                continue;
            }
            $fid = (int) $fid;
            if ($fid === 0) {
                $pms = $pmRepo->findByUserAndFolder($user->getUid(), 1);
            } elseif ($fid === 1) {
                $pms = $pmRepo->findByUserAndFolder($user->getUid(), 1, 0);
            } else {
                $pms = $pmRepo->findByUserAndFolder($user->getUid(), $fid);
            }
            foreach ($pms as $pm) {
                if ($keepUnread && $pm->getStatus() === 0) {
                    continue;
                }
                $em->remove($pm);
                $deleted++;
            }
        }
        $em->flush();

        $this->addFlash('success', $deleted . ' mesaj silindi.');
        return $this->redirectToRoute('app_private_message');
    }

    private function trackingAction(Request $request, EntityManagerInterface $em, User $user): Response
    {
        $pmRepo = $em->getRepository(PrivateMessage::class);
        $qb = $em->createQueryBuilder();
        $qb->select('p')
            ->from(PrivateMessage::class, 'p')
            ->where('p.fromid = :uid')
            ->andWhere('p.folder != 3')
            ->setParameter('uid', $user->getUid())
            ->orderBy('p.dateline', 'DESC');
        $sent = $qb->getQuery()->getResult();

        $readMessages = [];
        $unreadMessages = [];
        foreach ($sent as $m) {
            if ($m->getReceipt() === 2 && $m->getStatus() !== 0) {
                $readMessages[] = $m;
            } elseif ($m->getReceipt() === 1 && $m->getStatus() === 0) {
                $unreadMessages[] = $m;
            }
        }

        $toIds = [];
        foreach (array_merge($readMessages, $unreadMessages) as $m) {
            if ($m->getToid() > 0) {
                $toIds[] = $m->getToid();
            }
        }
        if (count($toIds) > 0) {
            $users = $em->getRepository(User::class)->findBy(['uid' => array_unique($toIds)]);
            $userMap = [];
            foreach ($users as $u) {
                $userMap[$u->getUid()] = $u;
            }
            foreach (array_merge($readMessages, $unreadMessages) as $m) {
                if (isset($userMap[$m->getToid()])) {
                    $m->setToUser($userMap[$m->getToid()]);
                }
            }
        }

        return $this->render('default/private/tracking.html.twig', [
            'read_messages' => $readMessages,
            'unread_messages' => $unreadMessages,
        ]);
    }

    private function doStopTrackingAction(Request $request, EntityManagerInterface $em, User $user): Response
    {
        if (!$this->isCsrfTokenValid('pm_stoptracking', $request->request->getString('_token', ''))) {
            $this->addFlash('error', 'Geçersiz istek.');
            return $this->redirectToRoute('app_private_message', ['action' => 'tracking']);
        }

        $pmids = array_map('intval', $request->request->all('pmid'));
        $pmRepo = $em->getRepository(PrivateMessage::class);
        $deleted = 0;
        foreach ($pmids as $pmid) {
            $pm = $pmRepo->find($pmid);
            if ($pm && $pm->getFromid() === $user->getUid() && $pm->getReceipt() === 1 && $pm->getStatus() === 0) {
                $em->remove($pm);
                $deleted++;
            }
        }
        $em->flush();

        $this->addFlash('success', $deleted . ' okunmamış giden mesaj iptal edildi.');
        return $this->redirectToRoute('app_private_message', ['action' => 'tracking']);
    }

    private function readAction(Request $request, EntityManagerInterface $em, User $user): Response
    {
        $pmid = $request->query->getInt('pmid');
        $pm = $em->getRepository(PrivateMessage::class)->find($pmid);

        if ($pm === null || $pm->getUid() !== $user->getUid()) {
            throw $this->createAccessDeniedException('Bu mesaji okuma yetkiniz yok veya mesaj bulunamadi.');
        }

        if ($pm->getStatus() === 0) {
            $pm->setStatus(1);
            $pm->setReadtime(time());
            if ($pm->getReceipt() === 1) {
                $pm->setReceipt(2);
            }
            $em->flush();
        }

        if ($pm->getFromid() > 0) {
            $sender = $em->getRepository(User::class)->find($pm->getFromid());
            $pm->setFromUser($sender);
        }

        return $this->render('default/private/read.html.twig', [
            'pm' => $pm,
        ]);
    }

    private function sendAction(Request $request, EntityManagerInterface $em, User $currentUser): Response
    {
        $error = null;

        if ($request->isMethod('POST')) {
            $toUsername = trim($request->request->getString('to_username'));
            $subject = trim($request->request->getString('subject'));
            $message = trim($request->request->getString('message'));

            if ($toUsername === '' || $subject === '' || $message === '') {
                $error = 'Tum alanlari doldurmaniz gerekiyor.';
            } else {
                $recipient = $em->getRepository(User::class)->findOneBy(['username' => $toUsername]);

                if ($recipient === null) {
                    $error = 'Belirtilen alici bulunamadi.';
                } else {
                    $now = time();

                    // Alici icin (Inbox)
                    $pmInbox = new PrivateMessage();
                    $pmInbox->setUid($recipient->getUid() ?? 0);
                    $pmInbox->setToid($recipient->getUid() ?? 0);
                    $pmInbox->setFromid($currentUser->getUid() ?? 0);
                    $pmInbox->setFolder(1);
                    $pmInbox->setSubject($subject);
                    $pmInbox->setMessage($message);
                    $pmInbox->setStatus(0);
                    $pmInbox->setDateline($now);
                    $pmInbox->setReceipt(1);
                    $pmInbox->setReadtime(null);
                    $em->persist($pmInbox);

                    // Gonderen icin (Sent)
                    $pmSent = new PrivateMessage();
                    $pmSent->setUid($currentUser->getUid() ?? 0);
                    $pmSent->setToid($recipient->getUid() ?? 0);
                    $pmSent->setFromid($currentUser->getUid() ?? 0);
                    $pmSent->setFolder(2);
                    $pmSent->setSubject($subject);
                    $pmSent->setMessage($message);
                    $pmSent->setStatus(1);
                    $pmSent->setDateline($now);
                    $em->persist($pmSent);

                    $em->flush();

                    $this->addFlash('success', 'Mesajiniz basariyla gonderildi.');
                    return $this->redirectToRoute('app_private_message');
                }
            }
        }

        return $this->render('default/private/send.html.twig', [
            'error' => $error,
        ]);
    }

    private function deleteAction(Request $request, EntityManagerInterface $em, User $user): Response
    {
        if (!$this->isCsrfTokenValid('pm_delete', $request->request->getString('_token', ''))) {
            $this->addFlash('error', 'Gecersiz istek.');
            return $this->redirectToRoute('app_private_message');
        }
        $pmid = $request->request->getInt('pmid', 0) ?: $request->query->getInt('pmid', 0);
        $pm = $em->getRepository(PrivateMessage::class)->find($pmid);
        if ($pm === null || $pm->getUid() !== $user->getUid()) {
            $this->addFlash('error', 'Mesaj bulunamadi.');
            return $this->redirectToRoute('app_private_message');
        }
        if ($pm->getFolder() === 4) {
            $em->remove($pm);
        } else {
            $pm->setFolder(4);
            $pm->setDeletetime(time());
        }
        $em->flush();
        $this->addFlash('success', 'Mesaj silindi.');
        return $this->redirectToRoute('app_private_message');
    }

    private function exportAction(Request $request, EntityManagerInterface $em, PrivateMessageRepository $pmRepo, User $user): Response
    {
        $totalPms = $pmRepo->countByUserAndFolder($user->getUid(), 1)
            + $pmRepo->countByUserAndFolder($user->getUid(), 2);
        if ($totalPms === 0) {
            $this->addFlash('error', 'Disari aktarilacak mesaj yok.');
            return $this->redirectToRoute('app_private_message');
        }
        $folders = $this->parsePmfolders($user->getPmfolders());
        return $this->render('default/private/export.html.twig', [
            'folders' => $folders,
        ]);
    }

    private function doExportAction(Request $request, EntityManagerInterface $em, PrivateMessageRepository $pmRepo, User $user): Response
    {
        if (!$this->isCsrfTokenValid('pm_export', $request->request->getString('_token', ''))) {
            $this->addFlash('error', 'Gecersiz istek.');
            return $this->redirectToRoute('app_private_message', ['action' => 'export']);
        }
        $exportFolders = $request->request->all('exportfolders');
        if (empty($exportFolders)) {
            $this->addFlash('error', 'En az bir klasor secin.');
            return $this->redirectToRoute('app_private_message', ['action' => 'export']);
        }
        $folderIds = array_map('intval', $exportFolders);
        if (in_array(0, $folderIds, true)) {
            $folderIds = [1, 2, 3, 4];
        }
        $folderIds = array_unique(array_filter($folderIds));
        $exportType = $request->request->getString('exporttype', 'txt');
        if (!in_array($exportType, ['txt', 'csv', 'html'], true)) {
            $exportType = 'txt';
        }
        $messages = [];
        foreach ($folderIds as $fid) {
            $folderMessages = $pmRepo->findByUserAndFolder($user->getUid(), $fid);
            foreach ($folderMessages as $m) {
                $messages[] = $m;
            }
        }
        usort($messages, fn($a, $b) => $a->getDateline() <=> $b->getDateline());
        $fromIds = array_unique(array_map(fn($m) => $m->getFromid(), $messages));
        $toIds = array_unique(array_map(fn($m) => $m->getToid(), $messages));
        $userIds = array_unique(array_merge(array_filter($fromIds), array_filter($toIds)));
        $users = $em->getRepository(User::class)->findBy(['uid' => $userIds]);
        $userMap = [];
        foreach ($users as $u) {
            $userMap[$u->getUid()] = $u->getUsername();
        }
        $username = $user->getUsername();
        $dateStr = date('d.m.Y H:i');
        $content = '';
        if ($exportType === 'txt') {
            $content = "Ozel Mesajlar - {$username}\nDisa aktarim: {$dateStr}\n\n";
            foreach ($messages as $m) {
                $from = $userMap[$m->getFromid()] ?? 'Misafir';
                $to = $userMap[$m->getToid()] ?? 'Misafir';
                $content .= "---\nKonu: {$m->getSubject()}\nGonderen: {$from} | Alici: {$to}\nTarih: " . date('d.m.Y H:i', $m->getDateline()) . "\n\n{$m->getMessage()}\n\n";
            }
        } elseif ($exportType === 'csv') {
            $buf = fopen('php://temp', 'r+');
            fputcsv($buf, ['Konu', 'Gonderen', 'Alici', 'Tarih', 'Mesaj']);
            foreach ($messages as $m) {
                $from = $userMap[$m->getFromid()] ?? 'Misafir';
                $to = $userMap[$m->getToid()] ?? 'Misafir';
                fputcsv($buf, [$m->getSubject(), $from, $to, date('d.m.Y H:i', $m->getDateline()), strip_tags($m->getMessage())]);
            }
            rewind($buf);
            $content = stream_get_contents($buf);
            fclose($buf);
        } else {
            $content = "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>PM Export - {$username}</title></head><body><h1>Ozel Mesajlar - {$username}</h1><p>Disa aktarim: {$dateStr}</p>";
            foreach ($messages as $m) {
                $from = $userMap[$m->getFromid()] ?? 'Misafir';
                $to = $userMap[$m->getToid()] ?? 'Misafir';
                $msg = nl2br(htmlspecialchars($m->getMessage()));
                $content .= "<hr><h2>{$m->getSubject()}</h2><p><strong>Gonderen:</strong> {$from} | <strong>Alici:</strong> {$to} | " . date('d.m.Y H:i', $m->getDateline()) . "</p><div>{$msg}</div>";
            }
            $content .= '</body></html>';
        }
        return new Response($content, 200, [
            'Content-Type' => $exportType === 'csv' ? 'text/csv' : ($exportType === 'html' ? 'text/html' : 'text/plain'),
            'Content-Disposition' => 'attachment; filename="pm_export_' . date('Y-m-d') . '.' . $exportType . '"',
        ]);
    }

    private function advancedSearchAction(Request $request, EntityManagerInterface $em, User $user): Response
    {
        $folders = $this->parsePmfolders($user->getPmfolders());
        return $this->render('default/private/advanced_search.html.twig', [
            'folders' => $folders,
        ]);
    }

    private function doSearchAction(Request $request, EntityManagerInterface $em, PrivateMessageRepository $pmRepo, User $user): Response
    {
        if (!$this->isCsrfTokenValid('pm_search', $request->request->getString('_token', ''))) {
            $this->addFlash('error', 'Gecersiz istek.');
            return $this->redirectToRoute('app_private_message', ['action' => 'advanced_search']);
        }
        $keywords = trim($request->request->getString('keywords', ''));
        $searchSubject = $request->request->getInt('subject', 0) === 1;
        $searchMessage = $request->request->getInt('message', 0) === 1;
        if (!$searchSubject && !$searchMessage) {
            $this->addFlash('error', 'Konu veya mesaj iceriginde arama secin.');
            return $this->redirectToRoute('app_private_message', ['action' => 'advanced_search']);
        }
        if ($keywords === '') {
            $this->addFlash('error', 'Arama ifadesi girin.');
            return $this->redirectToRoute('app_private_message', ['action' => 'advanced_search']);
        }
        $folderIds = $request->request->all('folder');
        $folderIds = array_filter(array_map('intval', (array) $folderIds));
        $uid = $user->getUid();
        $qb = $pmRepo->createQueryBuilder('p')
            ->where('p.uid = :uid')
            ->setParameter('uid', $uid);
        if (!empty($folderIds)) {
            $qb->andWhere('p.folder IN (:folders)')->setParameter('folders', $folderIds);
        }
        $kw = '%' . addcslashes($keywords, '%_') . '%';
        $or = [];
        if ($searchSubject) {
            $or[] = 'p.subject LIKE :kw';
        }
        if ($searchMessage) {
            $or[] = 'p.message LIKE :kw';
        }
        $qb->andWhere(implode(' OR ', $or))->setParameter('kw', $kw);
        $qb->orderBy('p.dateline', 'DESC');
        $pms = $qb->getQuery()->getResult();
        $pmids = array_map(fn($p) => $p->getPmid(), $pms);
        $sid = bin2hex(random_bytes(16));
        $conn = $em->getConnection();
        try {
            $conn->insert('mybb_searchlog', [
                'sid' => $sid,
                'uid' => $uid,
                'dateline' => time(),
                'ipaddress' => $request->getClientIp(),
                'threads' => '',
                'posts' => '',
                'resulttype' => 'pmmessages',
                'querycache' => implode(',', $pmids),
                'keywords' => $keywords,
            ]);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Arama kaydedilemedi.');
            return $this->redirectToRoute('app_private_message', ['action' => 'advanced_search']);
        }
        $sortby = in_array($request->request->getString('sortby'), ['subject', 'sender', 'dateline'], true)
            ? $request->request->getString('sortby') : 'dateline';
        $order = strtolower($request->request->getString('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $this->addFlash('success', 'Arama tamamlandi.');
        return $this->redirectToRoute('app_private_message', ['action' => 'results', 'sid' => $sid, 'sortby' => $sortby, 'order' => $order]);
    }

    private function searchResultsAction(Request $request, EntityManagerInterface $em, PrivateMessageRepository $pmRepo, User $user): Response
    {
        $sid = $request->query->getString('sid', '');
        if ($sid === '') {
            throw $this->createNotFoundException('Arama oturumu bulunamadi.');
        }
        $conn = $em->getConnection();
        $row = $conn->fetchAssociative('SELECT * FROM mybb_searchlog WHERE sid = ? AND uid = ?', [$sid, $user->getUid()], [\PDO::PARAM_STR, \PDO::PARAM_INT]);
        if (!$row) {
            throw $this->createNotFoundException('Arama bulunamadi.');
        }
        $querycache = $row['querycache'] ?? '';
        $pmids = $querycache !== '' ? array_filter(array_map('intval', explode(',', $querycache))) : [];
        $messages = [];
        if (!empty($pmids)) {
            $messages = $pmRepo->createQueryBuilder('p')
                ->where('p.pmid IN (:pmids)')
                ->setParameter('pmids', $pmids)
                ->orderBy('p.dateline', 'DESC')
                ->getQuery()
                ->getResult();
        }
        $sortby = $request->query->getString('sortby', 'dateline');
        $order = strtolower($request->query->getString('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $fromIds = array_unique(array_map(fn($m) => $m->getFromid(), $messages));
        $fromIds = array_filter($fromIds);
        $userMap = [];
        if (!empty($fromIds)) {
            $users = $em->getRepository(User::class)->findBy(['uid' => $fromIds]);
            foreach ($users as $u) {
                $userMap[$u->getUid()] = $u->getUsername();
            }
        }
        return $this->render('default/private/search_results.html.twig', [
            'messages' => $messages,
            'user_map' => $userMap,
            'keywords' => $row['keywords'] ?? '',
            'sid' => $sid,
            'sortby' => $sortby,
            'order' => $order,
        ]);
    }
}
