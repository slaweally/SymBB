<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Warning;
use App\Entity\WarningType;
use App\Repository\UserRepository;
use App\Repository\WarningRepository;
use App\Repository\WarningTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WarningController extends AbstractController
{
    private const MAX_WARNING_POINTS = 10;
    private const BANNED_GROUP_ID = 7;

    #[Route('/warnings.php', name: 'app_warnings', priority: 100)]
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        WarningTypeRepository $warningTypeRepository,
        WarningRepository $warningRepository,
    ): Response {
        $action = $request->query->getString('action', '') ?: $request->request->getString('action', '');

        if ($action === 'view') {
            return $this->viewWarnings($request, $userRepository, $warningRepository);
        }
        if ($action === 'revoke' && $request->isMethod('POST')) {
            return $this->revokeWarning($request, $em, $warningRepository, $userRepository);
        }

        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        if ($action === 'warn' && $request->isMethod('POST')) {
            return $this->handleWarn($request, $em, $userRepository, $warningTypeRepository);
        }

        $warningTypes = $warningTypeRepository->findAllOrdered();
        $targetUid = $request->query->getInt('uid', 0);
        $targetPid = $request->query->getInt('pid', 0);
        $redirect = $request->query->getString('redirect', '');

        return $this->render('default/warnings/index.html.twig', [
            'warning_types' => $warningTypes,
            'target_uid' => $targetUid,
            'target_pid' => $targetPid,
            'redirect' => $redirect,
        ]);
    }

    private function viewWarnings(Request $request, UserRepository $userRepository, WarningRepository $warningRepository): Response
    {
        $uid = $request->query->getInt('uid', 0);
        if ($uid <= 0) {
            throw $this->createNotFoundException('Kullanıcı ID gerekli.');
        }
        $user = $userRepository->find($uid);
        if ($user === null) {
            throw $this->createNotFoundException('Kullanıcı bulunamadı.');
        }
        /** @var User $current */
        $current = $this->getUser();
        if ($current->getUid() !== $uid && !$this->isGranted('ROLE_MODERATOR') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        $warnings = $warningRepository->findBy(['uid' => $uid], ['dateline' => 'DESC']);
        $issuerIds = array_unique(array_map(fn(Warning $w) => $w->getIssuedby(), $warnings));
        $issuers = [];
        if (!empty($issuerIds)) {
            $users = $userRepository->findBy(['uid' => $issuerIds]);
            foreach ($users as $u) {
                $issuers[$u->getUid()] = $u->getUsername();
            }
        }
        return $this->render('default/warnings/view.html.twig', [
            'user' => $user,
            'warnings' => $warnings,
            'issuers' => $issuers,
        ]);
    }

    private function revokeWarning(Request $request, EntityManagerInterface $em, WarningRepository $warningRepository, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');
        if (!$this->isCsrfTokenValid('warnings_revoke', $request->request->getString('_token', ''))) {
            $this->addFlash('error', 'Geçersiz istek.');
            return $this->redirectToRoute('app_warnings');
        }
        $wid = $request->request->getInt('wid', 0);
        if ($wid <= 0) {
            return $this->redirectToRoute('app_warnings');
        }
        $warning = $warningRepository->find($wid);
        if ($warning === null) {
            $this->addFlash('error', 'Uyarı bulunamadı.');
            return $this->redirectToRoute('app_warnings');
        }
        $targetUser = $userRepository->find($warning->getUid());
        if ($targetUser !== null) {
            $newPoints = max(0, $targetUser->getWarningpoints() - $warning->getPoints());
            $targetUser->setWarningpoints($newPoints);
        }
        $em->remove($warning);
        $em->flush();
        $this->addFlash('success', 'Uyarı kaldırıldı.');
        $uid = $request->request->getInt('uid', 0);
        if ($uid > 0) {
            return $this->redirectToRoute('app_warnings', ['action' => 'view', 'uid' => $uid]);
        }
        return $this->redirectToRoute('app_warnings');
    }

    private function handleWarn(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        WarningTypeRepository $warningTypeRepository
    ): Response {
        $uid = $request->request->getInt('uid');
        $tid = $request->request->getInt('type');
        $pid = $request->request->getInt('pid', 0);
        $notes = trim($request->request->getString('notes', ''));
        $customPoints = $request->request->getInt('custom_points', 0);
        $customTitle = trim($request->request->getString('custom_title', ''));

        if ($uid <= 0) {
            $this->addFlash('error', 'Geçersiz kullanıcı.');
            return $this->redirectToRoute('app_warnings');
        }

        $targetUser = $userRepository->find($uid);
        if ($targetUser === null) {
            $this->addFlash('error', 'Kullanıcı bulunamadı.');
            return $this->redirectToRoute('app_warnings');
        }

        $points = 0;
        $title = '';

        if ($tid > 0) {
            $warningType = $warningTypeRepository->find($tid);
            if ($warningType === null) {
                $this->addFlash('error', 'Geçersiz uyarı tipi.');
                return $this->redirectToRoute('app_warnings');
            }
            $points = $warningType->getPoints();
            $title = $warningType->getTitle();
        }

        if ($customPoints > 0) {
            $points = $customPoints;
        }
        if ($customTitle !== '') {
            $title = $customTitle;
        }

        if ($points <= 0) {
            $this->addFlash('error', 'En az 1 ceza puanı verilmelidir.');
            return $this->redirectToRoute('app_warnings');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if ($targetUser->getUid() === $currentUser->getUid()) {
            $this->addFlash('error', 'Kendinize uyarı veremezsiniz.');
            return $this->redirectToRoute('app_warnings');
        }

        $warning = new Warning();
        $warning->setUid($targetUser->getUid());
        $warning->setIssuedby($currentUser->getUid());
        $warning->setTid($tid);
        $warning->setPid($pid);
        $warning->setTitle($title ?: 'Uyarı');
        $warning->setPoints($points);
        $warning->setDateline(time());
        $warning->setNotes($notes);

        $em->persist($warning);

        $newPoints = $targetUser->getWarningpoints() + $points;
        $targetUser->setWarningpoints($newPoints);

        if ($newPoints >= self::MAX_WARNING_POINTS) {
            $targetUser->setUsergroup(self::BANNED_GROUP_ID);
            $targetUser->setDisplaygroup(self::BANNED_GROUP_ID);
            $this->addFlash('notice', sprintf(
                'Kullanıcı %s toplam %d uyarı puanına ulaştığı için otomatik olarak banlandı.',
                $targetUser->getUsername(),
                $newPoints
            ));
        }

        $em->flush();

        $redirect = $request->request->getString('redirect', '');
        if ($redirect !== '') {
            return $this->redirect($redirect);
        }

        $this->addFlash('success', sprintf('%s kullanıcısına %d puan uyarı verildi.', $targetUser->getUsername(), $points));
        return $this->redirectToRoute('app_warnings');
    }
}
