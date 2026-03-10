<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\MyBBPasswordHasher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{


    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_board_index');
        }

        return $this->render('default/security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_board_index');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $username = trim($request->request->getString('username'));
            $email = trim($request->request->getString('email'));
            $password = $request->request->getString('password');
            $passwordConfirm = $request->request->getString('password_confirm');

            if ($username === '' || $email === '' || $password === '') {
                $error = 'Tum alanlar zorunludur.';
            } elseif ($password !== $passwordConfirm) {
                $error = 'Sifreler eslesmedi.';
            } elseif ($userRepository->findOneBy(['username' => $username])) {
                $error = 'Bu kullanici adi zaten kullaniliyor.';
            } else {
                $hasher = new MyBBPasswordHasher();
                $salt = substr(md5((string) random_int(0, PHP_INT_MAX)), 0, 10);
                $hashedPassword = md5(md5($salt) . md5($password));
                $loginkey = bin2hex(random_bytes(25));

                $conn = $em->getConnection();
                $conn->executeStatement("SET @OLD_SQL_MODE = @@SQL_MODE");
                $conn->executeStatement("SET SQL_MODE = ''");

                $conn->executeStatement(
                    'INSERT INTO mybb_users (username, password, salt, loginkey, email, usergroup, regdate, signature, buddylist, ignorelist, pmfolders, notepad, usernotes)
                     VALUES (:username, :password, :salt, :loginkey, :email, :usergroup, :regdate, :signature, :buddylist, :ignorelist, :pmfolders, :notepad, :usernotes)',
                    [
                        'username' => $username,
                        'password' => $hashedPassword,
                        'salt' => $salt,
                        'loginkey' => $loginkey,
                        'email' => $email,
                        'usergroup' => 2,
                        'regdate' => time(),
                        'signature' => '',
                        'buddylist' => '',
                        'ignorelist' => '',
                        'pmfolders' => '',
                        'notepad' => '',
                        'usernotes' => '',
                    ]
                );

                $conn->executeStatement("SET SQL_MODE = @OLD_SQL_MODE");

                $this->addFlash('success', 'Kayit basarili! Simdi giris yapabilirsin.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('default/security/register.html.twig', [
            'error' => $error,
        ]);
    }
}
