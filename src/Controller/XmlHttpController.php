<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Thread;
use App\Entity\User;
use App\Repository\PostRepository;
use App\Twig\AppExtension;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class XmlHttpController extends AbstractController
{
    #[Route('/xmlhttp.php', name: 'app_xmlhttp', priority: 100)]
    public function __invoke(
        Request $request,
        PostRepository $postRepository,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        AppExtension $appExtension,
    ): Response {
        $action = $request->query->getString('action', '');

        $response = new JsonResponse();
        $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        if ($action === 'edit_post') {
            return $this->editPost($request, $postRepository, $em, $appExtension);
        }
        if ($action === 'get_post') {
            return $this->getPost($request, $postRepository);
        }
        if ($action === 'edit_subject') {
            return $this->editSubject($request, $em, $postRepository);
        }
        if ($action === 'get_users') {
            return $this->getUsers($request, $userRepository);
        }
        if ($action === 'get_multiquoted') {
            return $this->getMultiquoted($request, $postRepository);
        }
        if ($action === 'username_availability') {
            return $this->usernameAvailability($request, $userRepository);
        }
        if ($action === 'email_availability') {
            return $this->emailAvailability($request, $userRepository);
        }
        if ($action === 'get_buddyselect') {
            return $this->getBuddyselect($request, $userRepository);
        }
        if ($action === 'get_referrals') {
            return $this->getReferrals($request, $em);
        }

        return new JsonResponse(['error' => 'Unknown action'], Response::HTTP_BAD_REQUEST);
    }

    private function editPost(Request $request, PostRepository $postRepository, EntityManagerInterface $em, AppExtension $appExtension): JsonResponse
    {
        if (!$request->isMethod('POST')) {
            return new JsonResponse(['error' => 'POST required'], Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $pid = $request->request->getInt('pid');
        $value = $request->request->getString('value') ?: $request->request->getString('message');

        if ($pid <= 0) {
            return new JsonResponse(['error' => 'Invalid post ID'], Response::HTTP_BAD_REQUEST);
        }

        $post = $postRepository->find($pid);
        if ($post === null) {
            return new JsonResponse(['error' => 'Post not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();

        $thread = $post->getThread();
        if ($thread === null) {
            return new JsonResponse(['error' => 'Invalid post'], Response::HTTP_BAD_REQUEST);
        }

        if ($thread->getClosed() !== '' && $thread->getClosed() !== '0') {
            if (!$this->isGranted('ROLE_MODERATOR') && !$this->isGranted('ROLE_ADMIN')) {
                return new JsonResponse(['error' => 'Thread is closed'], Response::HTTP_FORBIDDEN);
            }
        }

        if ($post->getUid() !== $user->getUid() && !$this->isGranted('ROLE_MODERATOR') && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'No permission to edit'], Response::HTTP_FORBIDDEN);
        }

        $post->setMessage($value);
        $em->flush();

        $html = (string) $appExtension->parseMessage($value);

        return new JsonResponse(['success' => true, 'message' => $value, 'html' => $html]);
    }

    private function getPost(Request $request, PostRepository $postRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $pid = $request->query->getInt('pid');
        if ($pid <= 0) {
            return new JsonResponse(['error' => 'Invalid post ID'], Response::HTTP_BAD_REQUEST);
        }

        $post = $postRepository->find($pid);
        if ($post === null) {
            return new JsonResponse(['error' => 'Post not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        if ($post->getUid() !== $user->getUid() && !$this->isGranted('ROLE_MODERATOR') && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'No permission'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse(['message' => $post->getMessage()]);
    }

    private function editSubject(Request $request, EntityManagerInterface $em, PostRepository $postRepository): JsonResponse
    {
        if (!$request->isMethod('POST')) {
            return new JsonResponse(['error' => 'POST required'], Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $tid = $request->request->getInt('tid');
        $value = trim($request->request->getString('value', ''));

        if ($tid <= 0 || $value === '') {
            return new JsonResponse(['error' => 'Invalid tid or subject'], Response::HTTP_BAD_REQUEST);
        }

        if (\strlen($value) > 120) {
            return new JsonResponse(['error' => 'Subject too long'], Response::HTTP_BAD_REQUEST);
        }

        $thread = $em->getRepository(Thread::class)->find($tid);
        if ($thread === null) {
            return new JsonResponse(['error' => 'Thread not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();

        if ($thread->getClosed() !== '' && $thread->getClosed() !== '0') {
            if (!$this->isGranted('ROLE_MODERATOR') && !$this->isGranted('ROLE_ADMIN')) {
                return new JsonResponse(['error' => 'Thread is closed'], Response::HTTP_FORBIDDEN);
            }
        }

        $firstPost = $postRepository->createQueryBuilder('p')
            ->where('p.thread = :thread')
            ->setParameter('thread', $thread)
            ->orderBy('p.dateline', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($firstPost === null) {
            return new JsonResponse(['error' => 'Post not found'], Response::HTTP_NOT_FOUND);
        }

        if ($firstPost->getUid() !== $user->getUid() && !$this->isGranted('ROLE_MODERATOR') && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'No permission to edit subject'], Response::HTTP_FORBIDDEN);
        }

        $thread->setSubject($value);
        $firstPost->setSubject($value);
        $em->flush();

        $subjectEscaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $link = $this->generateUrl('app_thread_show', ['tid' => $tid], true);
        $html = '<a href="' . $link . '">' . $subjectEscaped . '</a>';

        return new JsonResponse(['success' => true, 'subject' => $value, 'html' => $html]);
    }

    private function getUsers(Request $request, UserRepository $userRepository): JsonResponse
    {
        $query = trim($request->query->getString('query', ''));
        if (\strlen($query) < 2) {
            return new JsonResponse([]);
        }

        $limit = $request->query->getInt('getone') === 1 ? 1 : 15;
        $searchType = $request->query->getInt('search_type', 0);

        $qb = $userRepository->createQueryBuilder('u')
            ->where('u.uid > 0')
            ->orderBy('u.username', 'ASC')
            ->setMaxResults($limit);

        $likePattern = match ($searchType) {
            1 => '%' . $query,
            2 => '%' . $query . '%',
            default => $query . '%',
        };

        $qb->andWhere('u.username LIKE :q')->setParameter('q', $likePattern);

        $users = $qb->getQuery()->getResult();

        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'uid' => $user->getUid(),
                'id' => $user->getUsername(),
                'text' => $user->getUsername(),
            ];
        }

        if ($limit === 1 && \count($data) === 1) {
            return new JsonResponse($data[0]);
        }

        return new JsonResponse($data);
    }

    private function getMultiquoted(Request $request, PostRepository $postRepository): JsonResponse
    {
        if (!$request->isMethod('POST')) {
            return new JsonResponse(['error' => 'POST required'], Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $pids = $request->request->all('pid');
        $pids = is_array($pids) ? array_filter(array_map('intval', $pids)) : [];
        if (empty($pids)) {
            return new JsonResponse(['text' => '']);
        }

        $posts = $postRepository->createQueryBuilder('p')
            ->where('p.pid IN (:pids)')
            ->setParameter('pids', $pids)
            ->orderBy('p.dateline', 'ASC')
            ->getQuery()
            ->getResult();
        $parts = [];

        foreach ($posts as $post) {
            $username = $post->getUsername() ?: 'Misafir';
            $username = str_replace(["'", '"'], '', $username);
            $message = strip_tags($post->getMessage());
            $message = str_replace(["\r\n", "\r"], "\n", trim($message));
            $quoteChar = str_contains($username, '"') ? "'" : '"';
            $parts[] = "[quote={$quoteChar}{$username}{$quoteChar}]\n{$message}\n[/quote]\n\n";
        }

        $text = implode('', $parts);

        return new JsonResponse(['text' => $text]);
    }

    /**
     * AJAX endpoint for registration form: check if username is available.
     * MyBB xmlhttp.php action=username_availability
     */
    private function usernameAvailability(Request $request, UserRepository $userRepository): JsonResponse
    {
        if (!$request->isMethod('POST')) {
            return new JsonResponse(['errors' => ['POST required']], Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $username = trim($request->request->getString('username', ''));
        $username = preg_replace('/\s+/', ' ', $username);

        if ($username === '') {
            return new JsonResponse('Kullanici adi bos olamaz.');
        }

        if (str_contains($username, '<') || str_contains($username, '>') || str_contains($username, '&')
            || str_contains($username, '\\') || str_contains($username, ';') || str_contains($username, ',')
        ) {
            return new JsonResponse('Kullanici adinda yasakli karakterler var.');
        }

        $existing = $userRepository->findOneBy(['username' => $username]);
        if ($existing !== null) {
            return new JsonResponse('Bu kullanici adi zaten kullaniliyor.');
        }

        return new JsonResponse('true');
    }

    /**
     * AJAX: check if email is available (registration).
     */
    private function emailAvailability(Request $request, UserRepository $userRepository): JsonResponse
    {
        if (!$request->isMethod('POST')) {
            return new JsonResponse(['errors' => ['POST required']], Response::HTTP_METHOD_NOT_ALLOWED);
        }
        $email = trim($request->request->getString('email', ''));
        if ($email === '' || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse('Gecersiz e-posta.');
        }
        $existing = $userRepository->findOneBy(['email' => $email]);
        if ($existing !== null) {
            return new JsonResponse('Bu e-posta zaten kayitli.');
        }
        return new JsonResponse('true');
    }

    /**
     * AJAX: HTML/options for buddy list (PM recipient select).
     */
    private function getBuddyselect(Request $request, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var User $user */
        $user = $this->getUser();
        $buddylist = $user->getBuddylist();
        $buddies = [];
        if ($buddylist !== null && $buddylist !== '') {
            $uids = array_filter(array_map('intval', explode(',', $buddylist)));
            if (!empty($uids)) {
                $buddies = $userRepository->findBy(['uid' => $uids], ['username' => 'ASC']);
            }
        }
        $html = $this->renderView('default/xmlhttp/buddyselect.html.twig', ['buddies' => $buddies]);
        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * AJAX: referrals list for a user (e.g. profile).
     */
    private function getReferrals(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $uid = $request->query->getInt('uid', 0);
        if ($uid <= 0) {
            return new JsonResponse(['error' => 'Invalid uid'], Response::HTTP_BAD_REQUEST);
        }
        $conn = $em->getConnection();
        try {
            $rows = $conn->fetchAllAssociative(
                'SELECT uid, username, regdate FROM mybb_users WHERE referrer = ? ORDER BY regdate DESC LIMIT 50',
                [$uid],
                [\PDO::PARAM_INT]
            );
        } catch (\Throwable $e) {
            return new JsonResponse(['referrals' => []]);
        }
        $referrals = [];
        foreach ($rows as $r) {
            $referrals[] = [
                'uid' => (int) $r['uid'],
                'username' => $r['username'],
                'regdate' => (int) $r['regdate'],
            ];
        }
        return new JsonResponse(['referrals' => $referrals]);
    }
}
