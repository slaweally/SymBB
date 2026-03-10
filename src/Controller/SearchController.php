<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SearchLog;
use App\Entity\User;
use App\Repository\ForumRepository;
use App\Repository\PostRepository;
use App\Repository\SearchLogRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    private const RESULTS_PER_PAGE = 20;

    #[Route('/search.php', name: 'app_search', priority: 100)]
    public function __invoke(
        Request $request,
        PostRepository $postRepository,
        ThreadRepository $threadRepository,
        ForumRepository $forumRepository,
        UserRepository $userRepository,
        SearchLogRepository $searchLogRepository,
        EntityManagerInterface $em,
    ): Response {
        $action = $request->query->getString('action');

        if ($action === 'results') {
            $sid = $request->query->getString('sid');
            if ($sid === '') {
                $this->addFlash('error', 'Geçersiz arama oturumu.');
                return $this->redirectToRoute('app_search');
            }
            $searchLog = $searchLogRepository->findBySid($sid);
            if ($searchLog === null) {
                $this->addFlash('error', 'Arama sonuçları bulunamadı veya süresi dolmuş.');
                return $this->redirectToRoute('app_search');
            }
            $user = $this->getUser();
            $currentUid = $user instanceof User ? $user->getUid() : 0;
            if ($searchLog->getUid() !== $currentUid) {
                $this->addFlash('error', 'Bu arama sonuçlarına erişim yetkiniz yok.');
                return $this->redirectToRoute('app_search');
            }
            $pids = $searchLog->getPostIds();
            $keywords = $searchLog->getKeywords() ?? '';

            $page = max(1, $request->query->getInt('page', 1));
            $offset = ($page - 1) * self::RESULTS_PER_PAGE;
            $slice = array_slice($pids, $offset, self::RESULTS_PER_PAGE);
            $posts = $postRepository->findByIdsOrdered($slice);

            $totalPages = (int) ceil(count($pids) / self::RESULTS_PER_PAGE);

            return $this->render('default/search/results.html.twig', [
                'keywords' => $keywords ?: 'Arama sonuçları',
                'posts' => $posts,
                'sid' => $sid,
                'page' => $page,
                'total_pages' => $totalPages,
                'total_results' => count($pids),
            ]);
        }

        if ($action === 'advanced') {
            $forumData = $forumRepository->findCategoriesWithForums();
            return $this->render('default/search/advanced.html.twig', [
                'forum_data' => $forumData,
            ]);
        }

        if ($action === 'do_advanced' && $request->isMethod('POST')) {
            $keywords = trim($request->request->getString('keywords', ''));
            $author = trim($request->request->getString('author', ''));
            $exactAuthor = $request->request->getBoolean('exact_author', false);
            $forums = $request->request->all('forums');
            $forums = is_array($forums) ? array_map('intval', array_filter($forums)) : [];
            $days = $request->request->getInt('days', 0);
            $matchType = $request->request->getString('match_type', 'and');

            $criteria = [
                'keywords' => $keywords,
                'author' => $author,
                'exact_author' => $exactAuthor,
                'forums' => $forums,
                'days' => $days,
                'match_type' => $matchType,
            ];

            $authorUid = null;
            if ($author !== '') {
                $authorUser = $exactAuthor
                    ? $userRepository->findOneBy(['username' => $author])
                    : $userRepository->findOneByUsernameLike($author);
                $authorUid = $authorUser?->getUid();
            }

            $criteria['author_uid'] = $authorUid;

            $posts = $postRepository->advancedSearch($criteria);
            $pids = array_values(array_filter(array_map(fn ($p) => $p->getPid(), $posts)));

            $user = $this->getUser();
            $uid = $user instanceof User ? $user->getUid() : 0;
            $sid = md5(uniqid((string) microtime(true), true));
            $searchLog = new SearchLog();
            $searchLog->setSid($sid);
            $searchLog->setUid($uid);
            $searchLog->setDateline(time());
            $searchLog->setIpaddress($request->getClientIp());
            $searchLog->setThreads('');
            $searchLog->setPosts(implode(',', $pids));
            $searchLog->setResulttype('posts');
            $searchLog->setQuerycache(null);
            $searchLog->setKeywords($keywords ?: 'Gelişmiş arama');
            $em->persist($searchLog);
            $em->flush();

            return $this->redirectToRoute('app_search', ['action' => 'results', 'sid' => $sid]);
        }

        if ($action === 'getnew' || $action === 'vthreads') {
            $since = time() - (24 * 60 * 60);
            $user = $this->getUser();
            if ($user !== null && $user->getLastactive() !== null) {
                $since = $user->getLastactive();
            }
            $threads = $threadRepository->findNewSince($since);

            return $this->render('default/search/new_posts.html.twig', [
                'threads' => $threads,
            ]);
        }

        if ($action === 'do_search' && $request->isMethod('POST')) {
            $keywords = trim($request->request->getString('keywords'));

            if ($keywords === '') {
                $this->addFlash('error', 'Lutfen aranacak kelimeyi girin.');
                return $this->redirectToRoute('app_search');
            }

            $posts = $postRepository->searchPosts($keywords);
            $pids = array_values(array_filter(array_map(fn ($p) => $p->getPid(), $posts)));

            $user = $this->getUser();
            $uid = $user instanceof User ? $user->getUid() : 0;
            $sid = md5(uniqid((string) microtime(true), true));
            $searchLog = new SearchLog();
            $searchLog->setSid($sid);
            $searchLog->setUid($uid);
            $searchLog->setDateline(time());
            $searchLog->setIpaddress($request->getClientIp());
            $searchLog->setThreads('');
            $searchLog->setPosts(implode(',', $pids));
            $searchLog->setResulttype('posts');
            $searchLog->setQuerycache(null);
            $searchLog->setKeywords($keywords);
            $em->persist($searchLog);
            $em->flush();

            return $this->redirectToRoute('app_search', ['action' => 'results', 'sid' => $sid]);
        }

        return $this->render('default/search/index.html.twig');
    }
}
