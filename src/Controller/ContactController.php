<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact.php', name: 'app_contact', priority: 100)]
    public function __invoke(Request $request): Response
    {
        $contactEmail = 'admin@example.com';
        $error = null;
        $success = false;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('contact', $request->request->getString('_token', ''))) {
                $error = 'Gecersiz istek.';
            } else {
                $email = trim($request->request->getString('email', ''));
                $subject = trim($request->request->getString('subject', ''));
                $message = trim($request->request->getString('message', ''));
                if ($subject === '' || $message === '') {
                    $error = 'Konu ve mesaj zorunludur.';
                } elseif (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                    $error = 'Gecersiz e-posta adresi.';
                } else {
                    $fromName = $this->getUser() ? $this->getUser()->getUserIdentifier() : 'Ziyaretci';
                    $body = "Iletisim formu\nGonderen: {$fromName} <{$email}>\n\nKonu: {$subject}\n\n{$message}";
                    $sent = @mail($contactEmail, '[SymBB] ' . $subject, $body, 'From: ' . $email, '-f' . $email);
                    if ($sent) {
                        $this->addFlash('success', 'Mesajiniz gonderildi.');
                        return $this->redirectToRoute('app_contact');
                    }
                    $error = 'Mesaj gonderilemedi.';
                }
            }
        }
        return $this->render('default/contact/index.html.twig', [
            'contact_email' => $contactEmail,
            'error' => $error,
        ]);
    }
}
