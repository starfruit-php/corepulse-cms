<?php

namespace CorepulseBundle\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

use CorepulseBundle\Model\User;
use CorepulseBundle\Security\Token;

class UserAuthenticator extends AbstractAuthenticator
{
    const TOKEN_HEADER_NAME = 'CMS-TOKEN';

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning `false` will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request): ?bool
    {
        return $request->headers->has(self::TOKEN_HEADER_NAME);
    }

    public function authenticate(Request $request): Passport
    {
        $apiToken = $request->headers->get(self::TOKEN_HEADER_NAME);
        if (null === $apiToken) {
            // The token header was empty, authentication fails with HTTP Status
            // Code 401 "Unauthorized"
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }

        // implement your own logic to get the user identifier from `$apiToken`
        // e.g. by looking up a user in the database using its API key
        $verify = Token::verifyToken($apiToken);

        if (!$verify) {
            throw new CustomUserMessageAuthenticationException('Invalid API token');
        }

        $user = User::getByAuthToken($apiToken);

        if (!$user) {
            throw new CustomUserMessageAuthenticationException('User not found');
        }

        $userIdentifier = $user->getUsername();
        $passport = new SelfValidatingPassport(new UserBadge($userIdentifier));

        return $passport;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            // you may want to customize or obfuscate the message first
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData())

            // or to translate this message
            // $this->translator->trans($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}