<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CalendarController extends AbstractController
{
    #[Route('/calendar.php', name: 'app_calendar', priority: 100)]
    public function __invoke(Request $request): Response
    {
        return $this->render('default/calendar/index.html.twig', [
            'month' => $request->query->getInt('month', (int) date('n')),
            'year' => $request->query->getInt('year', (int) date('Y')),
        ]);
    }
}
