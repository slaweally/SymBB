<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;

final class ActivitySubscriber implements EventSubscriberInterface
{
    private const THROTTLE_SECONDS = 60;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly RouterInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $user->getUid() <= 0) {
            return;
        }

        $now = time();
        $lastactive = $user->getLastactive();

        if ($lastactive !== null && ($now - $lastactive) < self::THROTTLE_SECONDS) {
            return;
        }

        $entity = $this->em->getRepository(User::class)->find($user->getUid());
        if ($entity === null) {
            return;
        }

        $entity->setLastactive($now);

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        if ($route !== null) {
            $entity->setLocation($route);
        }

        $this->em->flush();
    }
}
