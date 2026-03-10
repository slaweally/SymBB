<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Poll;
use App\Entity\PollVote;
use App\Entity\Thread;
use App\Entity\User;
use App\Repository\PollRepository;
use App\Repository\PollVoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PollController extends AbstractController
{
    #[Route('/polls.php', name: 'app_polls', priority: 100)]
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        PollRepository $pollRepository,
        PollVoteRepository $pollVoteRepository,
    ): Response {
        $action = $request->query->getString('action');
        /** @var User|null $user */
        $user = $this->getUser();

        if ($action === 'newpoll') {
            return $this->newpollForm($request, $em, $pollRepository);
        }

        if ($action === 'do_newpoll' && $request->isMethod('POST')) {
            return $this->doNewpoll($request, $em, $pollRepository);
        }

        if ($action === 'vote' && $request->isMethod('POST')) {
            return $this->vote($request, $em, $pollRepository, $pollVoteRepository);
        }

        if ($action === 'editpoll') {
            return $this->editpollForm($request, $em, $pollRepository);
        }

        if ($action === 'do_editpoll' && $request->isMethod('POST')) {
            return $this->doEditpoll($request, $em, $pollRepository);
        }

        if ($action === 'do_undovote' && $request->isMethod('POST')) {
            return $this->doUndovote($request, $em, $pollRepository, $pollVoteRepository);
        }

        return $this->redirectToRoute('app_board_index');
    }

    private function newpollForm(Request $request, EntityManagerInterface $em, PollRepository $pollRepository): Response
    {
        $tid = $request->query->getInt('tid');
        if ($tid <= 0) {
            throw $this->createNotFoundException('Thread ID gerekli.');
        }

        $thread = $em->getRepository(Thread::class)->find($tid);
        if ($thread === null) {
            throw $this->createNotFoundException('Konu bulunamadı.');
        }

        $user = $this->getUser();
        if ($user === null) {
            throw $this->createAccessDeniedException('Oturum açmanız gerekiyor.');
        }

        $canManage = $thread->getUid() === $user->getUid() || $this->isGranted('ROLE_MODERATOR') || $this->isGranted('ROLE_ADMIN');
        if (!$canManage) {
            throw $this->createAccessDeniedException('Bu konuya anket ekleme yetkiniz yok.');
        }

        if ($thread->getPoll() > 0 || $pollRepository->findByTid($tid) !== null) {
            $this->addFlash('error', 'Bu konuda zaten anket var.');
            return $this->redirectToRoute('app_thread_show', ['tid' => $tid]);
        }

        return $this->render('default/poll/newpoll.html.twig', [
            'thread' => $thread,
        ]);
    }

    private function doNewpoll(Request $request, EntityManagerInterface $em, PollRepository $pollRepository): Response
    {
        $user = $this->getUser();
        if ($user === null) {
            throw $this->createAccessDeniedException('Oturum açmanız gerekiyor.');
        }

        if (!$this->isCsrfTokenValid('poll_newpoll', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Geçersiz istek.');
            return $this->redirectToRoute('app_board_index');
        }

        $tid = $request->request->getInt('tid');
        $question = trim($request->request->getString('question') ?? '');
        $optionsRaw = trim($request->request->getString('options') ?? '');

        if ($tid <= 0 || $question === '') {
            $this->addFlash('error', 'Soru ve seçenekler gerekli.');
            return $this->redirectToRoute('app_polls', ['action' => 'newpoll', 'tid' => $tid]);
        }

        $thread = $em->getRepository(Thread::class)->find($tid);
        if ($thread === null) {
            throw $this->createNotFoundException('Konu bulunamadı.');
        }

        $canManage = $thread->getUid() === $user->getUid() || $this->isGranted('ROLE_MODERATOR') || $this->isGranted('ROLE_ADMIN');
        if (!$canManage) {
            throw $this->createAccessDeniedException('Bu konuya anket ekleme yetkiniz yok.');
        }

        if ($thread->getPoll() > 0) {
            $this->addFlash('error', 'Bu konuda zaten anket var.');
            return $this->redirectToRoute('app_thread_show', ['tid' => $tid]);
        }

        $optionsList = array_filter(array_map('trim', explode(',', $optionsRaw)));
        if (count($optionsList) < 2) {
            $this->addFlash('error', 'En az 2 seçenek girin (virgülle ayırın).');
            return $this->redirectToRoute('app_polls', ['action' => 'newpoll', 'tid' => $tid]);
        }

        $optionsStr = implode(Poll::OPTIONS_SEPARATOR, $optionsList);
        $votesStr = implode(Poll::OPTIONS_SEPARATOR, array_fill(0, count($optionsList), '0'));

        $poll = new Poll();
        $poll->setTid($tid);
        $poll->setQuestion($question);
        $poll->setDateline(time());
        $poll->setOptions($optionsStr);
        $poll->setVotes($votesStr);
        $poll->setNumvotes(0);
        $poll->setTimeout(0);
        $poll->setClosed(0);
        $poll->setMultiple(0);

        $em->persist($poll);
        $em->flush();

        $thread->setPoll($poll->getPid() ?? 0);
        $em->flush();

        $this->addFlash('success', 'Anket oluşturuldu.');
        return $this->redirectToRoute('app_thread_show', ['tid' => $tid]);
    }

    private function vote(
        Request $request,
        EntityManagerInterface $em,
        PollRepository $pollRepository,
        PollVoteRepository $pollVoteRepository,
    ): Response {
        $user = $this->getUser();
        if ($user === null) {
            throw $this->createAccessDeniedException('Oy vermek için giriş yapmalısınız.');
        }

        if (!$this->isCsrfTokenValid('poll_vote', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Geçersiz istek.');
            return $this->redirect($request->headers->get('Referer', '/'));
        }

        $pid = $request->request->getInt('pid');
        $option = $request->request->getInt('option');

        if ($pid <= 0 || $option <= 0) {
            $this->addFlash('error', 'Geçersiz anket veya seçenek.');
            return $this->redirect($request->headers->get('Referer', '/'));
        }

        $poll = $pollRepository->find($pid);
        if ($poll === null) {
            throw $this->createNotFoundException('Anket bulunamadı.');
        }

        $thread = $em->getRepository(Thread::class)->find($poll->getTid());
        if ($thread === null || $thread->getClosed() !== '') {
            $this->addFlash('error', 'Anket kapalı.');
            return $this->redirectToRoute('app_thread_show', ['tid' => $poll->getTid()]);
        }

        if ($poll->getClosed() === 1 || $poll->isExpired()) {
            $this->addFlash('error', 'Anket kapalı.');
            return $this->redirectToRoute('app_thread_show', ['tid' => $poll->getTid()]);
        }

        $uid = $user->getUid() ?? 0;
        if ($pollVoteRepository->hasUserVoted($pid, $uid)) {
            $this->addFlash('error', 'Zaten oy verdiniz.');
            return $this->redirectToRoute('app_thread_show', ['tid' => $poll->getTid()]);
        }

        $votesArray = $poll->getVotesArray();
        $optionsArray = $poll->getOptionsArray();
        if (!isset($votesArray[$option - 1]) || !isset($optionsArray[$option - 1])) {
            $this->addFlash('error', 'Geçersiz seçenek.');
            return $this->redirectToRoute('app_thread_show', ['tid' => $poll->getTid()]);
        }

        $votesArray[$option - 1]++;
        $votesStr = implode(Poll::OPTIONS_SEPARATOR, $votesArray);

        $vote = new PollVote();
        $vote->setPid($pid);
        $vote->setUid($uid);
        $vote->setVoteoption($option);
        $vote->setDateline(time());

        $em->persist($vote);
        $poll->setVotes($votesStr);
        $poll->setNumvotes($poll->getNumvotes() + 1);
        $em->flush();

        $this->addFlash('success', 'Oyunuz kaydedildi.');
        return $this->redirectToRoute('app_thread_show', ['tid' => $poll->getTid()]);
    }

    private function editpollForm(Request $request, EntityManagerInterface $em, PollRepository $pollRepository): Response
    {
        $pid = $request->query->getInt('pid');
        if ($pid <= 0) {
            throw $this->createNotFoundException('Anket ID gerekli.');
        }

        $poll = $pollRepository->find($pid);
        if ($poll === null) {
            throw $this->createNotFoundException('Anket bulunamadi.');
        }

        $user = $this->getUser();
        if ($user === null) {
            throw $this->createAccessDeniedException('Oturum acmaniz gerekiyor.');
        }

        $thread = $em->getRepository(Thread::class)->find($poll->getTid());
        if ($thread === null) {
            throw $this->createNotFoundException('Konu bulunamadi.');
        }

        $canManage = $thread->getUid() === $user->getUid() || $this->isGranted('ROLE_MODERATOR') || $this->isGranted('ROLE_ADMIN');
        if (!$canManage) {
            throw $this->createAccessDeniedException('Bu anketi duzenleme yetkiniz yok.');
        }

        return $this->render('default/poll/editpoll.html.twig', [
            'poll' => $poll,
            'thread' => $thread,
        ]);
    }

    private function doEditpoll(Request $request, EntityManagerInterface $em, PollRepository $pollRepository): Response
    {
        $user = $this->getUser();
        if ($user === null) {
            throw $this->createAccessDeniedException('Oturum acmaniz gerekiyor.');
        }

        if (!$this->isCsrfTokenValid('poll_editpoll', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Gecersiz istek.');
            return $this->redirectToRoute('app_board_index');
        }

        $pid = $request->request->getInt('pid');
        $question = trim($request->request->getString('question') ?? '');
        $optionsRaw = trim($request->request->getString('options') ?? '');

        if ($pid <= 0 || $question === '') {
            $this->addFlash('error', 'Soru ve secenekler gerekli.');
            return $this->redirectToRoute('app_board_index');
        }

        $poll = $pollRepository->find($pid);
        if ($poll === null) {
            throw $this->createNotFoundException('Anket bulunamadi.');
        }

        $thread = $em->getRepository(Thread::class)->find($poll->getTid());
        if ($thread === null) {
            throw $this->createNotFoundException('Konu bulunamadi.');
        }

        $canManage = $thread->getUid() === $user->getUid() || $this->isGranted('ROLE_MODERATOR') || $this->isGranted('ROLE_ADMIN');
        if (!$canManage) {
            throw $this->createAccessDeniedException('Bu anketi duzenleme yetkiniz yok.');
        }

        $optionsList = array_filter(array_map('trim', explode(',', $optionsRaw)));
        if (count($optionsList) < 2) {
            $this->addFlash('error', 'En az 2 secenek girin (virgülle ayirin).');
            return $this->redirectToRoute('app_polls', ['action' => 'editpoll', 'pid' => $pid]);
        }

        $optionsStr = implode(Poll::OPTIONS_SEPARATOR, $optionsList);
        $votesArray = $poll->getVotesArray();
        $newVotes = [];
        for ($i = 0; $i < count($optionsList); $i++) {
            $newVotes[] = $votesArray[$i] ?? 0;
        }
        $votesStr = implode(Poll::OPTIONS_SEPARATOR, $newVotes);

        $poll->setQuestion($question);
        $poll->setOptions($optionsStr);
        $poll->setVotes($votesStr);
        $em->flush();

        $this->addFlash('success', 'Anket guncellendi.');
        return $this->redirectToRoute('app_thread_show', ['tid' => $poll->getTid()]);
    }

    private function doUndovote(
        Request $request,
        EntityManagerInterface $em,
        PollRepository $pollRepository,
        PollVoteRepository $pollVoteRepository,
    ): Response {
        $user = $this->getUser();
        if ($user === null) {
            throw $this->createAccessDeniedException('Oy geri almak icin giris yapmalisiniz.');
        }

        if (!$this->isCsrfTokenValid('poll_undovote', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Gecersiz istek.');
            return $this->redirect($request->headers->get('Referer', '/'));
        }

        $pid = $request->request->getInt('pid');
        if ($pid <= 0) {
            $this->addFlash('error', 'Gecersiz anket.');
            return $this->redirect($request->headers->get('Referer', '/'));
        }

        $poll = $pollRepository->find($pid);
        if ($poll === null) {
            throw $this->createNotFoundException('Anket bulunamadi.');
        }

        $thread = $em->getRepository(Thread::class)->find($poll->getTid());
        if ($thread === null || $thread->getClosed() !== '') {
            $this->addFlash('error', 'Anket kapali.');
            return $this->redirectToRoute('app_thread_show', ['tid' => $poll->getTid()]);
        }

        if ($poll->getClosed() === 1 || $poll->isExpired()) {
            $this->addFlash('error', 'Anket kapali.');
            return $this->redirectToRoute('app_thread_show', ['tid' => $poll->getTid()]);
        }

        $uid = $user->getUid() ?? 0;
        $userVotes = $pollVoteRepository->findUserVotes($pid, $uid);
        if (count($userVotes) === 0) {
            $this->addFlash('error', 'Daha once oy vermediniz.');
            return $this->redirectToRoute('app_thread_show', ['tid' => $poll->getTid()]);
        }

        $votesArray = $poll->getVotesArray();
        foreach ($userVotes as $vote) {
            $opt = $vote->getVoteoption();
            if (isset($votesArray[$opt - 1]) && $votesArray[$opt - 1] > 0) {
                $votesArray[$opt - 1]--;
            }
        }
        $votesStr = implode(Poll::OPTIONS_SEPARATOR, $votesArray);
        $poll->setVotes($votesStr);
        $poll->setNumvotes(max(0, $poll->getNumvotes() - count($userVotes)));

        foreach ($userVotes as $vote) {
            $em->remove($vote);
        }
        $em->flush();

        $this->addFlash('success', 'Oyunuz geri alindi.');
        return $this->redirectToRoute('app_thread_show', ['tid' => $poll->getTid()]);
    }
}
