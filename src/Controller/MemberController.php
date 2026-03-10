<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\MyBBPasswordHasher;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MemberController extends AbstractController
{
    #[Route('/memberlist.php', name: 'app_memberlist', priority: 100)]
    public function memberlist(Request $request, UserRepository $userRepository, PaginatorInterface $paginator): Response
    {
        $action = $request->query->getString('action', '');

        if ($action === 'search') {
            return $this->memberlistSearch($request, $userRepository);
        }

        $queryBuilder = $userRepository->createQueryBuilder('u')
            ->where('u.uid > 0');

        $username = trim($request->query->getString('username', ''));
        $skype = trim($request->query->getString('skype', ''));
        $google = trim($request->query->getString('google', ''));
        $icq = trim($request->query->getString('icq', ''));
        $regdateDays = $request->query->getInt('regdate_days', 0);
        $postnumMin = $request->query->getInt('postnum_min', 0);
        $postnumMax = $request->query->getInt('postnum_max', 0);

        if ($username !== '') {
            $queryBuilder->andWhere('u.username LIKE :username')
                ->setParameter('username', '%' . addcslashes($username, '%_') . '%');
        }
        if ($skype !== '') {
            $queryBuilder->andWhere('u.skype LIKE :skype')
                ->setParameter('skype', '%' . addcslashes($skype, '%_') . '%');
        }
        if ($google !== '') {
            $queryBuilder->andWhere('u.google LIKE :google')
                ->setParameter('google', '%' . addcslashes($google, '%_') . '%');
        }
        if ($icq !== '') {
            $queryBuilder->andWhere('u.icq LIKE :icq')
                ->setParameter('icq', '%' . addcslashes($icq, '%_') . '%');
        }
        if ($regdateDays > 0) {
            $cutoff = time() - ($regdateDays * 86400);
            $queryBuilder->andWhere('u.regdate >= :regdate_cutoff')
                ->setParameter('regdate_cutoff', $cutoff);
        }
        if ($postnumMin > 0) {
            $queryBuilder->andWhere('u.postnum >= :postnum_min')
                ->setParameter('postnum_min', $postnumMin);
        }
        if ($postnumMax > 0) {
            $queryBuilder->andWhere('u.postnum <= :postnum_max')
                ->setParameter('postnum_max', $postnumMax);
        }

        $queryBuilder->orderBy('u.postnum', 'DESC');

        $page = $request->query->getInt('page', 1);
        $pagination = $paginator->paginate($queryBuilder, $page, 30);

        return $this->render('default/member/list.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    private function memberlistSearch(Request $request, UserRepository $userRepository): Response
    {
        return $this->render('default/member/search.html.twig');
    }

    #[Route('/showteam.php', name: 'app_showteam', priority: 100)]
    public function showteam(UserRepository $userRepository): Response
    {
        $teamMembers = $userRepository->findTeamMembers();

        $admins = [];
        $superMods = [];
        $mods = [];
        foreach ($teamMembers as $user) {
            $gid = $user->getDisplaygroup() > 0 ? $user->getDisplaygroup() : $user->getUsergroup();
            if ($gid === 4) {
                $admins[] = $user;
            } elseif ($gid === 3) {
                $superMods[] = $user;
            } else {
                $mods[] = $user;
            }
        }

        return $this->render('default/member/team.html.twig', [
            'admins' => $admins,
            'super_mods' => $superMods,
            'mods' => $mods,
        ]);
    }

    #[Route('/member.php', name: 'app_member', priority: 100)]
    public function member(Request $request, EntityManagerInterface $em, MyBBPasswordHasher $passwordHasher): Response
    {
        $action = $request->query->getString('action', '');

        if ($action === 'lostpw') {
            return $this->lostpw($request, $em);
        }
        if ($action === 'resetpassword') {
            return $this->resetpassword($request, $em, $passwordHasher);
        }
        if ($action === 'activate') {
            return $this->activate($request, $em);
        }
        if ($action === 'resendactivation') {
            return $this->resendactivation($request, $em);
        }
        if ($action === 'do_resendactivation' && $request->isMethod('POST')) {
            return $this->doResendactivation($request, $em);
        }
        if ($action === 'viewnotes') {
            return $this->viewnotes($request, $em);
        }
        if ($action === 'emailuser') {
            return $this->emailuser($request, $em);
        }
        if ($action === 'do_emailuser' && $request->isMethod('POST')) {
            return $this->doEmailuser($request, $em);
        }
        if ($action === 'referrals') {
            return $this->referrals($request, $em);
        }

        if ($action === 'profile') {
            $uid = $request->query->getInt('uid');

            if ($uid <= 0) {
                throw $this->createNotFoundException('User ID gerekli.');
            }

            $user = $em->getRepository(User::class)->find($uid);

            if ($user === null) {
                throw $this->createNotFoundException(sprintf('Kullanici bulunamadi: uid=%d', $uid));
            }

            return $this->render('default/member/profile.html.twig', [
                'user' => $user,
            ]);
        }

        // Diger member.php yonlendirmeleri
        return match ($action) {
            'register' => $this->redirectToRoute('app_register'),
            'login' => $this->redirectToRoute('app_login'),
            default => $this->redirectToRoute('app_login'),
        };
    }

    private function lostpw(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $email = trim($request->request->getString('email'));
            if ($email === '') {
                $this->addFlash('error', 'E-posta adresi girin.');
                return $this->render('default/member/lostpw.html.twig');
            }

            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($user === null) {
                $this->addFlash('error', 'Bu e-posta adresiyle kayitli kullanici bulunamadi.');
                return $this->render('default/member/lostpw.html.twig');
            }

            $token = bin2hex(random_bytes(20));
            $user->setLostpwcode($token);
            $em->flush();

            $resetUrl = $this->generateUrl('app_member', [
                'action' => 'resetpassword',
                'uid' => $user->getUid(),
                'code' => $token,
            ], true);

            $this->addFlash('success', 'Sifre sifirlama linki: ' . $resetUrl);

            return $this->render('default/member/lostpw.html.twig');
        }

        return $this->render('default/member/lostpw.html.twig');
    }

    private function resetpassword(Request $request, EntityManagerInterface $em, MyBBPasswordHasher $passwordHasher): Response
    {
        $uid = $request->query->getInt('uid');
        $code = $request->query->getString('code', '');

        if ($uid <= 0 || $code === '') {
            $this->addFlash('error', 'Gecersiz sifre sifirlama linki.');
            return $this->redirectToRoute('app_member', ['action' => 'lostpw']);
        }

        $user = $em->getRepository(User::class)->find($uid);
        if ($user === null || $user->getLostpwcode() !== $code) {
            $this->addFlash('error', 'Gecersiz veya suresi dolmus sifre sifirlama linki.');
            return $this->redirectToRoute('app_member', ['action' => 'lostpw']);
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->getString('password');
            $passwordConfirm = $request->request->getString('password_confirm');

            if (\strlen($password) < 6) {
                return $this->render('default/member/resetpw.html.twig', [
                    'uid' => $uid,
                    'code' => $code,
                    'error' => 'Sifre en az 6 karakter olmalidir.',
                ]);
            }
            if ($password !== $passwordConfirm) {
                return $this->render('default/member/resetpw.html.twig', [
                    'uid' => $uid,
                    'code' => $code,
                    'error' => 'Sifreler eslesmiyor.',
                ]);
            }

            $newSalt = substr(md5((string) random_int(0, PHP_INT_MAX)), 0, 10);
            $user->setSalt($newSalt);
            $user->setPassword($passwordHasher->hashWithSalt($password, $newSalt));
            $user->setLostpwcode(null);
            $em->flush();

            $this->addFlash('success', 'Sifreniz basariyla guncellendi. Giris yapabilirsiniz.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('default/member/resetpw.html.twig', [
            'uid' => $uid,
            'code' => $code,
        ]);
    }

    private function activate(Request $request, EntityManagerInterface $em): Response
    {
        $uid = $request->query->getInt('uid', 0);
        $code = $request->query->getString('code', '');
        $username = $request->query->getString('username', '');

        if ($code === '') {
            return $this->render('default/member/activate.html.twig', [
                'uid' => $uid,
                'username' => $username,
                'code' => $code,
            ]);
        }

        $user = null;
        if ($uid > 0) {
            $user = $em->getRepository(User::class)->find($uid);
        } elseif ($username !== '') {
            $user = $em->getRepository(User::class)->findOneBy(['username' => $username]);
        }

        if ($user === null) {
            $this->addFlash('error', 'Gecersiz aktivasyon linki.');
            return $this->redirectToRoute('app_board_index');
        }

        $conn = $em->getConnection();
        $activation = $conn->fetchAssociative(
            'SELECT * FROM mybb_awaitingactivation WHERE uid = ? AND (type = ? OR type = ? OR type = ?)',
            [$user->getUid(), 'r', 'e', 'b'],
            ['integer', 'string', 'string', 'string']
        );

        if ($activation === false || ($activation['code'] ?? '') !== $code) {
            $this->addFlash('error', 'Gecersiz veya suresi dolmus aktivasyon kodu.');
            return $this->redirectToRoute('app_board_index');
        }

        $conn->executeStatement('DELETE FROM mybb_awaitingactivation WHERE uid = ? AND (type = ? OR type = ?)', [$user->getUid(), 'r', 'e']);

        if ($user->getUsergroup() === 5 && !in_array($activation['type'] ?? '', ['e', 'b'], true)) {
            $user->setUsergroup(2);
            $em->flush();
        }

        if (($activation['type'] ?? '') === 'b') {
            $conn->executeStatement('UPDATE mybb_awaitingactivation SET validated = 1 WHERE uid = ? AND type = ?', [$user->getUid(), 'b']);
        }

        $this->addFlash('success', 'Hesabiniz basariyla aktiflestirildi. Giris yapabilirsiniz.');
        return $this->redirectToRoute('app_board_index');
    }

    private function resendactivation(Request $request, EntityManagerInterface $em): Response
    {
        return $this->render('default/member/resendactivation.html.twig');
    }

    private function doResendactivation(Request $request, EntityManagerInterface $em): Response
    {
        $email = trim($request->request->getString('email'));
        if ($email === '') {
            $this->addFlash('error', 'E-posta adresi girin.');
            return $this->redirectToRoute('app_member', ['action' => 'resendactivation']);
        }

        $conn = $em->getConnection();
        $users = $conn->fetchAllAssociative(
            'SELECT u.uid, u.username, u.usergroup, u.email, a.code, a.type, a.validated
             FROM mybb_users u
             LEFT JOIN mybb_awaitingactivation a ON (a.uid = u.uid AND (a.type = ? OR a.type = ?))
             WHERE u.email = ?',
            ['r', 'b', $email],
            ['string', 'string', 'string']
        );

        if (empty($users)) {
            $this->addFlash('error', 'Bu e-posta adresiyle kayitli bekleyen hesap bulunamadi.');
            return $this->redirectToRoute('app_member', ['action' => 'resendactivation']);
        }

        $bburl = $request->getSchemeAndHttpHost() . $request->getBasePath();
        $bbname = 'SymBB';
        $sent = 0;
        foreach ($users as $row) {
            if (($row['validated'] ?? 0) == 1 && ($row['type'] ?? '') === 'b') {
                $this->addFlash('error', 'Admin aktivasyonu bekleyen hesaplar icin bu islem yapilamaz.');
                return $this->redirectToRoute('app_member', ['action' => 'resendactivation']);
            }
            if ((int) $row['usergroup'] === 5) {
                $code = $row['code'] ?? bin2hex(random_bytes(16));
                if (empty($row['code'])) {
                    $conn->executeStatement(
                        'INSERT INTO mybb_awaitingactivation (uid, dateline, code, type) VALUES (?, ?, ?, ?)',
                        [$row['uid'], time(), $code, $row['type'] ?? 'r'],
                        ['integer', 'integer', 'string', 'string']
                    );
                }
                $activateUrl = $bburl . '/member.php?action=activate&uid=' . $row['uid'] . '&code=' . $code;
                $subject = "{$bbname} - Hesap Aktivasyonu";
                $body = "Merhaba {$row['username']},\n\nHesabinizi aktiflestirmek icin asagidaki linke tiklayin:\n{$activateUrl}\n\n{$bbname}";
                mail($row['email'], $subject, $body, 'From: noreply@' . ($request->getHost() ?: 'localhost'));
                $sent++;
            }
        }

        $this->addFlash('success', $sent > 0 ? 'Aktivasyon e-postasi tekrar gonderildi.' : 'Islem tamamlandi.');
        return $this->redirectToRoute('app_board_index');
    }

    private function viewnotes(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        $uid = $request->query->getInt('uid', 0);
        if ($uid <= 0) {
            throw $this->createNotFoundException('Kullanici ID gerekli.');
        }

        $user = $em->getRepository(User::class)->find($uid);
        if ($user === null) {
            throw $this->createNotFoundException('Kullanici bulunamadi.');
        }

        $conn = $em->getConnection();
        $row = $conn->fetchAssociative('SELECT usernotes FROM mybb_users WHERE uid = ?', [$uid], ['integer']);
        $usernotes = $row['usernotes'] ?? '';

        return $this->render('default/member/viewnotes.html.twig', [
            'user' => $user,
            'usernotes' => $usernotes,
        ]);
    }

    private function emailuser(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $uid = $request->query->getInt('uid', 0);
        if ($uid <= 0) {
            throw $this->createNotFoundException('Kullanici ID gerekli.');
        }
        $toUser = $em->getRepository(User::class)->find($uid);
        if ($toUser === null) {
            throw $this->createNotFoundException('Kullanici bulunamadi.');
        }
        if ($toUser->getHideemail() !== 0) {
            $this->addFlash('error', 'Bu kullanici e-posta adresini gizlemis.');
            return $this->redirectToRoute('app_member', ['action' => 'profile', 'uid' => $uid]);
        }
        $error = null;
        if ($request->isMethod('POST')) {
            $result = $this->doEmailuser($request, $em);
            if ($result instanceof Response) {
                return $result;
            }
            $error = 'E-posta gonderilemedi. Lutfen alanlari kontrol edin.';
        }
        return $this->render('default/member/emailuser.html.twig', [
            'to_user' => $toUser,
            'error' => $error,
        ]);
    }

    private function doEmailuser(Request $request, EntityManagerInterface $em): ?Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if (!$this->isCsrfTokenValid('emailuser', $request->request->getString('_token', ''))) {
            $this->addFlash('error', 'Gecersiz istek.');
            return $this->redirectToRoute('app_board_index');
        }
        $uid = $request->request->getInt('uid', 0);
        $toUser = $em->getRepository(User::class)->find($uid);
        if ($toUser === null || $toUser->getHideemail() !== 0) {
            $this->addFlash('error', 'Gecersiz alici veya e-posta gizli.');
            return $this->redirectToRoute('app_board_index');
        }
        $fromName = trim($request->request->getString('fromname', ''));
        $fromEmail = trim($request->request->getString('fromemail', ''));
        $subject = trim($request->request->getString('subject', ''));
        $message = trim($request->request->getString('message', ''));
        if ($fromName === '' || $subject === '' || $message === '') {
            return null;
        }
        if (!filter_var($fromEmail, \FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $body = "Merhaba {$toUser->getUsername()},\n\n{$request->request->getString('fromname')} (" . ($this->getUser() ? $this->getUser()->getUsername() : 'Ziyaretci') . ") size SymBB uzerinden su mesaji gonderdi:\n\n---\n{$message}\n---\n\nYanitlamak icin bu e-postayi yanitlayin.";
        $sent = @mail($toUser->getEmail(), $subject, $body, 'From: ' . $fromName . ' <' . $fromEmail . '>', '-f' . $fromEmail);
        if (!$sent) {
            return null;
        }
        $this->addFlash('success', 'E-postaniz gonderildi.');
        return $this->redirectToRoute('app_member', ['action' => 'profile', 'uid' => $toUser->getUid()]);
    }

    private function referrals(Request $request, EntityManagerInterface $em): Response
    {
        $uid = $request->query->getInt('uid', 0);
        if ($uid <= 0) {
            throw $this->createNotFoundException('Kullanici ID gerekli.');
        }
        $user = $em->getRepository(User::class)->find($uid);
        if ($user === null) {
            throw $this->createNotFoundException('Kullanici bulunamadi.');
        }
        $conn = $em->getConnection();
        $referralCount = 0;
        try {
            $referralCount = (int) $conn->fetchOne('SELECT COUNT(uid) FROM mybb_users WHERE referrer = ?', [$uid], [\PDO::PARAM_INT]);
        } catch (\Throwable) {
            // referrer column might not exist
        }
        $perPage = 20;
        $page = max(1, $request->query->getInt('page', 1));
        $offset = ($page - 1) * $perPage;
        $referrals = [];
        try {
            $rows = $conn->fetchAllAssociative(
                'SELECT uid, username, usergroup, displaygroup, regdate FROM mybb_users WHERE referrer = ? ORDER BY regdate DESC LIMIT ? OFFSET ?',
                [$uid, $perPage, $offset],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]
            );
            foreach ($rows as $row) {
                $referrals[] = [
                    'uid' => (int) $row['uid'],
                    'username' => $row['username'],
                    'usergroup' => (int) $row['usergroup'],
                    'displaygroup' => (int) ($row['displaygroup'] ?? 0),
                    'regdate' => (int) $row['regdate'],
                ];
            }
        } catch (\Throwable) {
        }
        $totalPages = $referralCount > 0 ? (int) ceil($referralCount / $perPage) : 1;
        return $this->render('default/member/referrals.html.twig', [
            'user' => $user,
            'referrals' => $referrals,
            'referral_count' => $referralCount,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
        ]);
    }
}
