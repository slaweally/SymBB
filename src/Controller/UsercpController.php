<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Attachment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Security\MyBBPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class UsercpController extends AbstractController
{
    private const AVATAR_MAX_SIZE = 2 * 1024 * 1024; // 2MB
    private const AVATAR_ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    #[Route('/usercp.php', name: 'app_usercp', priority: 100)]
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        MyBBPasswordHasher $mybbHasher,
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $action = $request->query->getString('action', '');

        if ($action === 'profile') {
            return $this->profile($request, $em);
        }
        if ($action === 'password') {
            return $this->password($request, $em, $passwordHasher, $mybbHasher);
        }
        if ($action === 'email') {
            return $this->email($request, $em, $passwordHasher);
        }
        if ($action === 'avatar') {
            return $this->avatar($request, $em);
        }
        if ($action === 'subscriptions') {
            return $this->subscriptions($request, $em);
        }
        if ($action === 'drafts') {
            return $this->drafts($request, $em);
        }
        if ($action === 'notepad') {
            return $this->notepad($request, $em);
        }
        if ($action === 'do_notepad' && $request->isMethod('POST')) {
            return $this->doNotepad($request, $em);
        }
        if ($action === 'editlists') {
            return $this->editlists($request, $em);
        }
        if ($action === 'do_editlists' && $request->isMethod('POST')) {
            return $this->doEditlists($request, $em);
        }
        if ($action === 'attachments') {
            return $this->attachments($request, $em);
        }
        if ($action === 'do_attachments' && $request->isMethod('POST')) {
            return $this->doAttachments($request, $em);
        }
        if ($action === 'changename') {
            return $this->changename($request, $em, $passwordHasher);
        }
        if ($action === 'do_changename' && $request->isMethod('POST')) {
            return $this->doChangename($request, $em, $passwordHasher);
        }
        if ($action === 'forumsubscriptions') {
            return $this->forumsubscriptions($request, $em);
        }
        if ($action === 'do_forumsubscriptions' && $request->isMethod('POST')) {
            return $this->doForumsubscriptions($request, $em);
        }
        if ($action === 'usergroups') {
            return $this->usergroups($request, $em);
        }

        if ($action === 'editsig') {
            /** @var User $user */
            $user = $this->getUser();

            if ($request->isMethod('POST')) {
                $signature = trim($request->request->getString('signature'));
                $user->setSignature($signature);
                $em->flush();

                $this->addFlash('success', 'Imzaniz basariyla guncellendi.');
                return $this->redirectToRoute('app_usercp', ['action' => 'editsig']);
            }

            return $this->render('default/usercp/editsig.html.twig', [
                'user' => $user,
            ]);
        }
        
        if ($action === 'options') {
            /** @var User $user */
            $user = $this->getUser();

            if ($request->isMethod('POST')) {
                // Checkboxlar seciliyse 1, degilse 0 gelir (HTML form mantiginda, default value 0 atacagiz)
                $user->setAllownotices($request->request->getInt('allownotices', 0));
                $user->setHideemail($request->request->getInt('hideemail', 0));
                $user->setReceivepms($request->request->getInt('receivepms', 0));
                $user->setPmnotice($request->request->getInt('pmnotice', 0));
                $user->setShowsigs($request->request->getInt('showsigs', 0));
                $user->setShowavatars($request->request->getInt('showavatars', 0));
                $user->setShowquickreply($request->request->getInt('showquickreply', 0));
                
                // Select listleri
                $user->setPpp($request->request->getInt('ppp', 0));
                $user->setTpp($request->request->getInt('tpp', 0));
                $user->setTimezone((string) $request->request->get('timezone', '0'));
                $user->setDst($request->request->getInt('dst', 0));

                $em->flush();

                $this->addFlash('success', 'Secenekleriniz basariyla kaydedildi.');
                return $this->redirectToRoute('app_usercp', ['action' => 'options']);
            }

            return $this->render('default/usercp/options.html.twig', [
                'user' => $user,
            ]);
        }

        // Action baska veya bos ise varsayilan cp sayfasi
        return $this->render('default/usercp/index.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    private function profile(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $error = null;

        if ($request->isMethod('POST')) {
            $user->setLocation($request->request->getString('location') ?: null);
            $user->setSkype($request->request->getString('skype') ?: null);
            $user->setGoogle($request->request->getString('google') ?: null);
            $user->setIcq($request->request->getString('icq') ?: null);
            $em->flush();
            $this->addFlash('success', 'Profil bilgileriniz guncellendi.');
            return $this->redirectToRoute('app_usercp', ['action' => 'profile']);
        }

        return $this->render('default/usercp/profile.html.twig', [
            'user' => $user,
            'error' => $error,
        ]);
    }

    private function password(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher, MyBBPasswordHasher $mybbHasher): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $error = null;

        if ($request->isMethod('POST')) {
            $oldPassword = $request->request->getString('old_password');
            $newPassword = $request->request->getString('new_password');
            $confirmPassword = $request->request->getString('confirm_password');

            if (!$passwordHasher->isPasswordValid($user, $oldPassword)) {
                $error = 'Eski sifre yanlis.';
            } elseif (\strlen($newPassword) < 6) {
                $error = 'Yeni sifre en az 6 karakter olmalidir.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Yeni sifreler eslesmiyor.';
            } else {
                $newSalt = substr(md5((string) random_int(0, PHP_INT_MAX)), 0, 10);
                $user->setSalt($newSalt);
                $user->setPassword($mybbHasher->hashWithSalt($newPassword, $newSalt));
                $em->flush();
                $this->addFlash('success', 'Sifreniz basariyla guncellendi.');
                return $this->redirectToRoute('app_usercp', ['action' => 'password']);
            }
        }

        return $this->render('default/usercp/password.html.twig', [
            'user' => $user,
            'error' => $error,
        ]);
    }

    private function email(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $error = null;

        if ($request->isMethod('POST')) {
            $newEmail = trim($request->request->getString('email'));
            $password = $request->request->getString('password');

            if (!filter_var($newEmail, \FILTER_VALIDATE_EMAIL)) {
                $error = 'Gecersiz e-posta adresi.';
            }

            if ($error === null && !$passwordHasher->isPasswordValid($user, $password)) {
                $error = 'Sifre yanlis.';
            }

            if ($error === null) {
                $user->setEmail($newEmail);
                $em->flush();
                $this->addFlash('success', 'E-posta adresiniz guncellendi.');
                return $this->redirectToRoute('app_usercp', ['action' => 'email']);
            }
        }

        return $this->render('default/usercp/email.html.twig', [
            'user' => $user,
            'error' => $error,
        ]);
    }

    private function avatar(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $error = null;

        if ($request->isMethod('POST')) {
            $file = $request->files->get('avatar');
            if ($file === null || !$file->isValid()) {
                $error = 'Lutfen gecerli bir resim secin.';
            } elseif ($file->getSize() > self::AVATAR_MAX_SIZE) {
                $error = 'Dosya boyutu en fazla 2MB olabilir.';
            } elseif (!\in_array($file->getMimeType(), self::AVATAR_ALLOWED_TYPES, true)) {
                $error = 'Sadece JPG, PNG, GIF veya WebP formatlari desteklenir.';
            } else {
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $ext = $file->guessExtension() ?: 'jpg';
                $filename = 'avatar_' . $user->getUid() . '_' . time() . '.' . $ext;
                try {
                    $file->move($uploadDir, $filename);
                    $user->setAvatar('/uploads/avatars/' . $filename);
                    $em->flush();
                    $this->addFlash('success', 'Avatar basariyla guncellendi.');
                    return $this->redirectToRoute('app_usercp', ['action' => 'avatar']);
                } catch (FileException $e) {
                    $error = 'Avatar yuklenirken hata olustu.';
                }
            }
        }

        return $this->render('default/usercp/avatar.html.twig', [
            'user' => $user,
            'error' => $error,
        ]);
    }

    private function subscriptions(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $threadRepository = $em->getRepository(\App\Entity\Thread::class);

        $threads = $threadRepository->createQueryBuilder('t')
            ->innerJoin(\App\Entity\ThreadSubscription::class, 's', 'WITH', 's.tid = t.tid AND s.uid = :uid')
            ->setParameter('uid', $user->getUid())
            ->where('t.visible = 1')
            ->orderBy('t.dateline', 'DESC')
            ->getQuery()
            ->getResult();

        $lastVisit = $user->getLastvisit() ?? 0;

        return $this->render('default/usercp/subscriptions.html.twig', [
            'user' => $user,
            'threads' => $threads,
            'last_visit' => $lastVisit,
        ]);
    }

    private function drafts(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $postRepository = $em->getRepository(\App\Entity\Post::class);
        $forumRepository = $em->getRepository(\App\Entity\Forum::class);

        $drafts = $postRepository->findDraftsByUser($user->getUid());

        $fids = [];
        foreach ($drafts as $post) {
            $thread = $post->getThread();
            if ($thread !== null) {
                $fids[$thread->getFid()] = true;
            }
        }
        $forums = [];
        if (count($fids) > 0) {
            $forumList = $forumRepository->findBy(['fid' => array_keys($fids)]);
            foreach ($forumList as $f) {
                $forums[$f->getFid()] = $f;
            }
        }

        return $this->render('default/usercp/drafts.html.twig', [
            'user' => $user,
            'drafts' => $drafts,
            'forums' => $forums,
        ]);
    }

    private function notepad(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render('default/usercp/notepad.html.twig', [
            'user' => $user,
        ]);
    }

    private function doNotepad(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('notepad', $request->request->getString('_token', ''))) {
            $this->addFlash('error', 'Gecersiz istek.');
            return $this->redirectToRoute('app_usercp', ['action' => 'notepad']);
        }
        /** @var User $user */
        $user = $this->getUser();
        $notepad = $request->request->getString('notepad', '');
        if (mb_strlen($notepad) > 60000) {
            $notepad = mb_substr($notepad, 0, 60000);
        }
        $user->setNotepad($notepad);
        $em->flush();
        $this->addFlash('success', 'Not defteri guncellendi.');
        return $this->redirectToRoute('app_usercp');
    }

    private function editlists(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $conn = $em->getConnection();
        $buddies = [];
        $ignored = [];
        $wolCutoff = time() - 900;
        if ($user->getBuddylist()) {
            $uids = array_filter(array_map('intval', explode(',', $user->getBuddylist())));
            if (!empty($uids)) {
                $placeholders = implode(',', array_fill(0, count($uids), '?'));
                $rows = $conn->fetchAllAssociative(
                    'SELECT uid, username, usergroup, displaygroup, lastactive, lastvisit, invisible FROM mybb_users WHERE uid IN (' . $placeholders . ') ORDER BY username',
                    $uids,
                    array_fill(0, count($uids), \PDO::PARAM_INT)
                );
                foreach ($rows as $r) {
                    $buddies[] = [
                        'uid' => (int) $r['uid'],
                        'username' => $r['username'],
                        'usergroup' => (int) $r['usergroup'],
                        'displaygroup' => (int) ($r['displaygroup'] ?? 0),
                        'online' => ($r['lastactive'] ?? 0) > $wolCutoff && (($r['invisible'] ?? 0) === 0 || $user->getUsergroup() === 4) && ($r['lastvisit'] ?? 0) != ($r['lastactive'] ?? 0),
                    ];
                }
            }
        }
        if ($user->getIgnorelist()) {
            $uids = array_filter(array_map('intval', explode(',', $user->getIgnorelist())));
            if (!empty($uids)) {
                $placeholders = implode(',', array_fill(0, count($uids), '?'));
                $rows = $conn->fetchAllAssociative(
                    'SELECT uid, username, usergroup, displaygroup FROM mybb_users WHERE uid IN (' . $placeholders . ') ORDER BY username',
                    $uids,
                    array_fill(0, count($uids), \PDO::PARAM_INT)
                );
                foreach ($rows as $r) {
                    $ignored[] = [
                        'uid' => (int) $r['uid'],
                        'username' => $r['username'],
                    ];
                }
            }
        }
        return $this->render('default/usercp/editlists.html.twig', [
            'user' => $user,
            'buddies' => $buddies,
            'ignored' => $ignored,
        ]);
    }

    private function doEditlists(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('editlists', $request->request->getString('_token', ''))) {
            $this->addFlash('error', 'Gecersiz istek.');
            return $this->redirectToRoute('app_usercp', ['action' => 'editlists']);
        }
        /** @var User $user */
        $user = $this->getUser();
        $manage = $request->request->getString('manage', 'buddy');
        $addUsername = trim($request->request->getString('add_username', ''));
        $deleteUid = $request->request->getInt('delete', 0);

        $isIgnore = $manage === 'ignored';
        $existing = $isIgnore ? $user->getIgnorelist() : $user->getBuddylist();
        $existingIds = $existing ? array_filter(array_map('intval', explode(',', $existing))) : [];

        if ($deleteUid > 0) {
            $key = array_search($deleteUid, $existingIds, true);
            if ($key !== false) {
                array_splice($existingIds, $key, 1);
                if ($isIgnore) {
                    $user->setIgnorelist($existingIds === [] ? null : implode(',', $existingIds));
                } else {
                    $user->setBuddylist($existingIds === [] ? null : implode(',', $existingIds));
                    $other = $em->getRepository(User::class)->find($deleteUid);
                    if ($other && $other->getBuddylist()) {
                        $otherIds = array_filter(array_map('intval', explode(',', $other->getBuddylist())));
                        $otherIds = array_values(array_diff($otherIds, [$user->getUid()]));
                        $other->setBuddylist($otherIds === [] ? null : implode(',', $otherIds));
                    }
                }
                $em->flush();
                $this->addFlash('success', $isIgnore ? 'Engelleme listesinden kaldirildi.' : 'Arkadas listesinden kaldirildi.');
            }
            return $this->redirectToRoute('app_usercp', ['action' => 'editlists', 'manage' => $manage]);
        }

        if ($addUsername !== '') {
            $usernames = array_unique(array_map('trim', explode(',', $addUsername)));
            $usernames = array_filter($usernames);
            $otherList = $isIgnore ? $user->getBuddylist() : $user->getIgnorelist();
            $otherIds = $otherList ? array_filter(array_map('intval', explode(',', $otherList))) : [];
            $added = 0;
            foreach ($usernames as $uname) {
                if (strcasecmp($uname, $user->getUsername()) === 0) {
                    continue;
                }
                $found = $em->getRepository(User::class)->findOneBy(['username' => $uname]);
                if (!$found || in_array($found->getUid(), $existingIds, true) || in_array($found->getUid(), $otherIds, true)) {
                    continue;
                }
                $existingIds[] = $found->getUid();
                $added++;
            }
            $existingIds = array_values(array_unique($existingIds));
            if ($isIgnore) {
                $user->setIgnorelist($existingIds === [] ? null : implode(',', $existingIds));
            } else {
                $user->setBuddylist($existingIds === [] ? null : implode(',', $existingIds));
            }
            $em->flush();
            if ($added > 0) {
                $this->addFlash('success', $isIgnore ? 'Engelleme listesine eklendi.' : 'Arkadas listesine eklendi.');
            }
        }
        return $this->redirectToRoute('app_usercp', ['action' => 'editlists', 'manage' => $manage]);
    }

    private function attachments(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $repo = $em->getRepository(Attachment::class);
        $qb = $repo->createQueryBuilder('a')
            ->innerJoin('a.post', 'p')
            ->innerJoin('p.thread', 't')
            ->where('a.uid = :uid')
            ->setParameter('uid', $user->getUid())
            ->andWhere('t.visible = 1')
            ->orderBy('p.dateline', 'DESC')
            ->addOrderBy('a.aid', 'DESC');
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;
        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);
        $attachments = $qb->getQuery()->getResult();
        $total = (int) $em->createQueryBuilder()
            ->select('COUNT(a.aid)')
            ->from(Attachment::class, 'a')
            ->innerJoin('a.post', 'p')
            ->innerJoin('p.thread', 't')
            ->where('a.uid = :uid')
            ->setParameter('uid', $user->getUid())
            ->andWhere('t.visible = 1')
            ->getQuery()
            ->getSingleScalarResult();
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        return $this->render('default/usercp/attachments.html.twig', [
            'user' => $user,
            'attachments' => $attachments,
            'total' => $total,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
        ]);
    }

    private function doAttachments(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('attachments', $request->request->getString('_token', ''))) {
            $this->addFlash('error', 'Gecersiz istek.');
            return $this->redirectToRoute('app_usercp', ['action' => 'attachments']);
        }
        /** @var User $user */
        $user = $this->getUser();
        $aids = $request->request->all('attachments');
        $aids = array_filter(array_map('intval', (array) $aids));
        if (empty($aids)) {
            $this->addFlash('error', 'Secim yapin.');
            return $this->redirectToRoute('app_usercp', ['action' => 'attachments']);
        }
        $repo = $em->getRepository(Attachment::class);
        $attachments = $repo->createQueryBuilder('a')
            ->where('a.aid IN (:aids)')
            ->andWhere('a.uid = :uid')
            ->setParameter('aids', $aids)
            ->setParameter('uid', $user->getUid())
            ->getQuery()
            ->getResult();
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/attachments';
        foreach ($attachments as $att) {
            $path = $uploadDir . '/' . $att->getFilename();
            if ($att->getFilename() && is_file($path)) {
                @unlink($path);
            }
            $em->remove($att);
        }
        $em->flush();
        $this->addFlash('success', 'Eklentiler silindi.');
        return $this->redirectToRoute('app_usercp', ['action' => 'attachments']);
    }

    private function changename(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render('default/usercp/changename.html.twig', [
            'user' => $user,
            'error' => null,
        ]);
    }

    private function doChangename(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        if (!$this->isCsrfTokenValid('changename', $request->request->getString('_token', ''))) {
            $this->addFlash('error', 'Gecersiz istek.');
            return $this->redirectToRoute('app_usercp', ['action' => 'changename']);
        }
        /** @var User $user */
        $user = $this->getUser();
        $password = $request->request->getString('password', '');
        $newUsername = trim($request->request->getString('username', ''));
        if (!$passwordHasher->isPasswordValid($user, $password)) {
            return $this->render('default/usercp/changename.html.twig', [
                'user' => $user,
                'error' => 'Sifre yanlis.',
            ]);
        }
        if (strlen($newUsername) < 3) {
            return $this->render('default/usercp/changename.html.twig', [
                'user' => $user,
                'error' => 'Kullanici adi en az 3 karakter olmalidir.',
            ]);
        }
        $existing = $em->getRepository(User::class)->findOneBy(['username' => $newUsername]);
        if ($existing && $existing->getUid() !== $user->getUid()) {
            return $this->render('default/usercp/changename.html.twig', [
                'user' => $user,
                'error' => 'Bu kullanici adi zaten alinmis.',
            ]);
        }
        $user->setUsername($newUsername);
        $em->flush();
        $this->addFlash('success', 'Kullanici adiniz guncellendi.');
        return $this->redirectToRoute('app_usercp', ['action' => 'changename']);
    }

    private function forumsubscriptions(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $conn = $em->getConnection();
        $forums = [];
        try {
            $rows = $conn->fetchAllAssociative(
                'SELECT fs.fid, f.name FROM mybb_forumsubscriptions fs INNER JOIN mybb_forums f ON f.fid = fs.fid WHERE fs.uid = ? AND f.type = ? ORDER BY f.name',
                [$user->getUid(), 'f'],
                [\PDO::PARAM_INT, \PDO::PARAM_STR]
            );
            foreach ($rows as $r) {
                $forums[] = ['fid' => (int) $r['fid'], 'name' => $r['name']];
            }
        } catch (\Throwable $e) {
        }
        return $this->render('default/usercp/forumsubscriptions.html.twig', [
            'user' => $user,
            'forums' => $forums,
        ]);
    }

    private function doForumsubscriptions(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('forumsubscriptions', $request->request->getString('_token', ''))) {
            $this->addFlash('error', 'Gecersiz istek.');
            return $this->redirectToRoute('app_usercp', ['action' => 'forumsubscriptions']);
        }
        /** @var User $user */
        $user = $this->getUser();
        $fids = $request->request->all('check');
        $fids = array_filter(array_map('intval', (array) $fids));
        if (!empty($fids)) {
            $conn = $em->getConnection();
            try {
                $conn->executeStatement(
                    'DELETE FROM mybb_forumsubscriptions WHERE uid = ? AND fid IN (' . implode(',', array_fill(0, count($fids), '?')) . ')',
                    array_merge([$user->getUid()], $fids),
                    array_merge([\PDO::PARAM_INT], array_fill(0, count($fids), \PDO::PARAM_INT))
                );
                $this->addFlash('success', 'Forum abonelikleri kaldirildi.');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Islem yapilamadi.');
            }
        }
        return $this->redirectToRoute('app_usercp', ['action' => 'forumsubscriptions']);
    }

    private function usergroups(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $setdisplay = $request->query->getInt('setdisplay', 0);
        $leavegroup = $request->query->getInt('leavegroup', 0);
        $joingroup = $request->query->getInt('joingroup', 0);
        if ($setdisplay > 0 && $this->isCsrfTokenValid('usergroups_setdisplay', $request->query->getString('_token', ''))) {
            $gid = $setdisplay;
            $conn = $em->getConnection();
            $allowed = $conn->fetchOne('SELECT gid FROM mybb_usergroups WHERE gid = ? AND (type = 3 OR type = 4 OR type = 5)', [$gid], [\PDO::PARAM_INT]);
            $myGroups = array_merge([$user->getUsergroup(), $user->getDisplaygroup()], $this->getAdditionalGroups($user, $conn));
            if ($allowed && in_array((int) $gid, $myGroups, true)) {
                $user->setDisplaygroup($gid);
                $em->flush();
                $this->addFlash('success', 'Gorunum grubu guncellendi.');
            }
            return $this->redirectToRoute('app_usercp', ['action' => 'usergroups']);
        }
        if ($leavegroup > 0 && $this->isCsrfTokenValid('usergroups_leave', $request->query->getString('_token', ''))) {
            $this->removeAdditionalGroup($em, $user, $leavegroup);
            $this->addFlash('success', 'Gruptan ayrildiniz.');
            return $this->redirectToRoute('app_usercp', ['action' => 'usergroups']);
        }
        if ($joingroup > 0 && $this->isCsrfTokenValid('usergroups_join', $request->query->getString('_token', ''))) {
            $this->addAdditionalGroup($em, $user, $joingroup);
            $this->addFlash('success', 'Gruba katildiniz.');
            return $this->redirectToRoute('app_usercp', ['action' => 'usergroups']);
        }
        $conn = $em->getConnection();
        $memberOf = [];
        $joinable = [];
        try {
            $primary = $user->getUsergroup();
            $display = $user->getDisplaygroup();
            $additional = $this->getAdditionalGroups($user, $conn);
            $allGids = array_unique(array_merge([$primary], $additional, [$display]));
            if (!empty($allGids)) {
                $placeholders = implode(',', array_fill(0, count($allGids), '?'));
                $rows = $conn->fetchAllAssociative('SELECT gid, title, type FROM mybb_usergroups WHERE gid IN (' . $placeholders . ') ORDER BY title', array_values($allGids), array_fill(0, count($allGids), \PDO::PARAM_INT));
                foreach ($rows as $r) {
                    $memberOf[] = [
                        'gid' => (int) $r['gid'],
                        'title' => $r['title'],
                        'is_primary' => (int) $r['gid'] === $primary,
                        'is_display' => (int) $r['gid'] === $display,
                    ];
                }
            }
            $existing = array_merge([$primary], $additional);
            $placeholders = implode(',', array_fill(0, count($existing), '?'));
            $rows = $conn->fetchAllAssociative('SELECT gid, title FROM mybb_usergroups WHERE (type = 3 OR type = 4 OR type = 5) AND gid NOT IN (' . $placeholders . ') ORDER BY title', array_values($existing), array_fill(0, count($existing), \PDO::PARAM_INT));
            foreach ($rows as $r) {
                $joinable[] = ['gid' => (int) $r['gid'], 'title' => $r['title']];
            }
        } catch (\Throwable $e) {
        }
        return $this->render('default/usercp/usergroups.html.twig', [
            'user' => $user,
            'member_of' => $memberOf,
            'joinable' => $joinable,
        ]);
    }

    /** @return array<int> */
    private function getAdditionalGroups(User $user, \Doctrine\DBAL\Connection $conn): array
    {
        try {
            $row = $conn->fetchOne('SELECT additionalgroups FROM mybb_users WHERE uid = ?', [$user->getUid()], [\PDO::PARAM_INT]);
            if ($row === false || $row === null || $row === '') {
                return [];
            }
            return array_filter(array_map('intval', explode(',', (string) $row)));
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function removeAdditionalGroup(EntityManagerInterface $em, User $user, int $gid): void
    {
        $conn = $em->getConnection();
        $current = $this->getAdditionalGroups($user, $conn);
        $current = array_values(array_diff($current, [$gid]));
        $conn->executeStatement('UPDATE mybb_users SET additionalgroups = ? WHERE uid = ?', [implode(',', $current) ?: null, $user->getUid()], [\PDO::PARAM_STR, \PDO::PARAM_INT]);
    }

    private function addAdditionalGroup(EntityManagerInterface $em, User $user, int $gid): void
    {
        $conn = $em->getConnection();
        $current = $this->getAdditionalGroups($user, $conn);
        if (in_array($gid, $current, true)) {
            return;
        }
        $current[] = $gid;
        $conn->executeStatement('UPDATE mybb_users SET additionalgroups = ? WHERE uid = ?', [implode(',', $current), $user->getUid()], [\PDO::PARAM_STR, \PDO::PARAM_INT]);
    }
}
