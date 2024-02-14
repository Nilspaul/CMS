<?php

namespace App\Controller;

use Pimcore\Model\DataObject\User;
use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use \Pimcore\Model\DataObject;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AccountController extends FrontendController
{
    /**
     * @param Request $request
     * @return Response
     */

    public function defaultAction(Request $request): Response
    {
        return $this->render('main/main.html.twig');
    }

    public function login(Request $request, TranslatorInterface $translator): Response
    {      
        // Retrieve the form data
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $translatedCheckInfo= $translator->trans("CheckYourEnteredInformation");
        // Find the user by email
        $user = $this->findUserByEmail($email);
        //Return Error if password or user is not correct
        if (!$user instanceof User || !password_verify($password, $user->getPassword())) {
            return new Response($translatedCheckInfo, 400);
        }
    
        // Successful login
        $session = $request->getSession();
        $session->set('user_logged_in', true);
        $session->set('user', $user);
        
        // Generate the route using Symfony's path function
        $onepagerRoute = $this->generateUrl('onepager');
        
        return new Response($onepagerRoute, 201);
    }

    public function logout(Request $request): Response
    {
        // Clear the user's session
        $session = $request->getSession();
        $session->clear();

        // Redirect to the main page
        return $this->render('onepager/onepager.html.twig'); 
    }
    
    public function register(Request $request, TranslatorInterface $translator): Response
    {
        // Retrieve the form data
        $username = $request->request->get('name');
        // Remove whitespaces from the username
        $username = str_replace(' ', '', $username);
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $confirmPassword = $request->request->get('confirmPassword');
        $translatedUsernameErrormessage = $translator->trans("UsernameErrormessage");
        $translatedEmailErrormessage = $translator->trans("EmailErrormessage");
        $translatedPasswordsErrormessage = $translator->trans("PasswordsDoNotMatchError");
        $translatedSuccessfullRegistration = $translator->trans("SuccessfullRegistration");
        // Validate the password
        if ($password !== $confirmPassword) {
            return new Response($translatedPasswordsErrormessage, 400);
        }
        //Check if the username is already taken
        if($this->findUserByUsername($username)) {
            return new Response($translatedUsernameErrormessage, 400);
        }
        //Check if this email already exists 
        if($this->findUserByEmail($email)) {
            return new Response($translatedEmailErrormessage, 400);
        } 
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
        // Create a new User (Pimcore DataObject) and store in User-folder
        $user = new User();
        $userFolder = DataObject::getByPath("/User");
        $userFolderId = $userFolder->getId();
        $user->setKey($username); 
        $user->setParentId($userFolderId);
        $user->setPublished(true);
    
        // Set user properties
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPassword($hashedPassword);
        $user->save();
        $imagePath = __DIR__ . '/../../public/static/assets/img/user-profile-with-cross-grey-icon-doctor-vector-32550061.png';
        $this->setProfileImage($user, $imagePath );
        return new Response($translatedSuccessfullRegistration, 200);
    }
    
    public function setProfileImage($user, $imagePath) {
        $userId = $user->getId();
        $username = $user->getUsername();  // Benutzernamen holen
        $timestamp = time(); // Aktueller Zeitstempel

        $newAsset = new \Pimcore\Model\Asset\Image();
        $userAsset = (new \Pimcore\Model\Asset\Listing())->setCondition("filename LIKE '%" . $username . "Profileimage%'")->current();
    
        if ($userAsset) {
            $userAsset->delete();
        }
    
        $newAsset->setFilename($username . "Profileimage" . $timestamp);
        $newAsset->setData(file_get_contents($imagePath));
        $newAsset->setParent(\Pimcore\Model\Asset::getByPath("/Users"));
        $newAsset->save();
    
        $user->setImage($newAsset);
        $user->save();
    }
    
    public function sendPasswordResetEmail(
        Request $request,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator,
        TranslatorInterface $translator
    ): Response {
        $email = $request->request->get('email');
        $user = $this->findUserByEmail($email); 
        $translatedCheckYourMailsOrReachSupport = $translator->trans("CheckYourMailsOrReachSupport");
        $translatedEmailSentSuccessfully = $translator->trans("EmailSentSuccessfully");
        if ($user instanceof User) {
            $token = $this->createUniqueToken($user);
            $resetPasswordUrl = $this->generateResetPasswordUrl($token, $urlGenerator);
            $emailContent = $this->createEmailContent($user, $resetPasswordUrl);

            $this->sendEmail($email, $emailContent, $mailer, $translator);

            return new Response($translatedEmailSentSuccessfully, 200);
        } else {
            return new Response($translatedCheckYourMailsOrReachSupport, 400);
        }
    }

    private function generateResetPasswordUrl($token, UrlGeneratorInterface $urlGenerator): string
    {
        return $urlGenerator->generate('setNewPassword', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function createEmailContent(User $user, string $resetPasswordUrl): string
    {
        $username = $user->getUsername();
        $language = $this->document->getProperty("language");
        if($language == "de") {
            return "
            <p>Hallo $username,</p><br>
            <p>Hier ist der Link zum Zurücksetzen deines Passworts: <a href='$resetPasswordUrl'>Passwort zurücksetzen</a></p><br>
            <p>Dieser Link wird nur für die nächsten 20 Minuten gültig sein!</p>
        ";
        } else {
            return "
            <p>Hello $username,</p><br>
            <p>Here you got the link to reset your password: <a href='$resetPasswordUrl'>Reset your password</a></p><br>
            <p>This link will expire in 20 minutes!</p>
        ";
        }
    }


    private function sendEmail(string $recipient, string $content, MailerInterface $mailer, TranslatorInterface $translator): void
    {
        $translatedResetOfYourPassword= $translator->trans("ResetOfYourPassword");

        $email = (new Email())
            ->from('THMStudyPlanner@gmail.com')
            ->to($recipient)
            ->subject($translatedResetOfYourPassword)
            ->html($content);

        $mailer->send($email);
    }
    
    public function showResetPasswordTemplate(Request $request, string $token, TranslatorInterface $translator): Response
    {
        $translatedTokenExpiryText = $translator->trans("TokenExpiryText");
        $user = $this->findUserByToken($token);
        $currentTime = time();
        $tokenProperty = $user->getClass()->getFieldDefinition('token');
        $expiryTime = $tokenProperty->getTooltip();; // 20 minutes in seconds

        if ($currentTime > $expiryTime) {
            return new Response($translatedTokenExpiryText, 400);
        }

        // Render the password reset form, passing the user
        return $this->render('resetPassword/resetPassword.html.twig', [
            'user' => $user,
        ]);
    }


    public function setNewUserPassword(Request $request, string $email, TranslatorInterface $translator): Response
    {
        $user = $this->findUserByEmail($email);
        $newPassword = $request->request->get('newPassword');
        $confirmPassword = $request->request->get('confirmNewPassword');
        $translatedPasswordsDoNotMatchError = $translator->trans("PasswordsDoNotMatchError");

        if ($newPassword !== $confirmPassword) {
            return new Response($translatedPasswordsDoNotMatchError, 400);
        }

        $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $user->setPassword($hashedNewPassword); // Set the new hashed password
        $user->save();
        
        return $this->render('onepager/onepager.html.twig');
    }

    public function createUniqueToken(object $user): string {
        $token = bin2hex(random_bytes(16));
        $userId = $user->getId();
        $combinedToken = $userId . '_' . $token;
        $tokenExpiryTime =  time() + (20 * 60); // 20 minutes in seconds
        $user->setToken($combinedToken);
        // Manually set the tooltip for the token property
        $tokenProperty = $user->getClass()->getFieldDefinition('token');
        $tooltip = $tokenExpiryTime; // Adjust this tooltip text as needed
        $tokenProperty->setTooltip($tooltip);
    
        // Save the user with updated token and tooltip
        $user->save();
        // Return the combined token
        return $combinedToken;
    }
    
    public function findUserByEmail($email) {
        $users = new User\Listing();
        $users->setCondition("email = ?", [$email]);
        $users->setLimit(1);
        
        foreach ($users as $user) {
            if ($user instanceof User) {
                return $user;
            }
        }
    }

    public function findUserByUsername($username)  {
        $users = new User\Listing(); // Get a listing of User objects
        $users->setCondition("username = ?", [$username]);
        $users->setLimit(1);
        
        foreach ($users as $user) {
            if ($user instanceof User) {
                return $user;
            }
        }
    }

    public function findUserByToken($token) {
        // Get a listing of User objects
        $users = new User\Listing(); 
        $users->setCondition("token = ?", [$token]);
        $users->setLimit(1);
        
        foreach ($users as $user) {
            if ($user instanceof User) {
                return $user;
            }
        }
    }

    public function updateProfile(Request $request, TranslatorInterface $translator): Response {
        $name = $request->request->get('name');
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $confirmPassword = $request->request->get('confirmPassword');
        $selectedSpecialization = $request->request->get('specialization');
        $session = $request->getSession();
        $user = $session->get('user');
        $uploadedFile = $request->files->get('profileimage');
        $translatedUpdated= $translator->trans("Updated");

        if($uploadedFile) {
            $imagePath = $uploadedFile->getPathname();
            $this->setProfileImage($user, $imagePath);
        }
        if($name) {
            $user->setUsername($name);
        }
        if($email) {
            $user->setEmail($email);
        }
        if(($password && $confirmPassword) && ($password == $confirmPassword) ) {
            $user->setPassword($password);
        }
        if($selectedSpecialization) {
            $user->setSpecialization($selectedSpecialization);
        }
        $user->save();

        return new Response($translatedUpdated, 200);
    }
}
