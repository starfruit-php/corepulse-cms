<?php

namespace CorepulseBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Controller\FrontendController;
use Pimcore\Bundle\AdminBundle\Security\CsrfProtectionHandler;

class DefaultController extends FrontendController
{
    protected $csrfProtection;

    public function __construct(
        CsrfProtectionHandler $csrfProtection
        )
    {
        $this->csrfProtection = $csrfProtection;
    }

    public function cms(Request $request)
    {
        $csrfToken = $request->get('is-admin') ? $this->csrfProtection->getCsrfToken($request->getSession()) : null;

        return $this->render('@Corepulse/cms.html.twig', [
            'csrfToken' => $csrfToken
        ]);
    }
}
