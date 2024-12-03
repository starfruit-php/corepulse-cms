<?php

namespace CorepulseBundle\EventSubscriber;

use CorepulseBundle\Services\AssetServices;
use CorepulseBundle\Services\DocumentServices;
use Pimcore\Db;
// use Rompetomp\InertiaBundle\Service\InertiaInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;
use Pimcore\Http\Request\Resolver\DocumentResolver;
use Pimcore\Bundle\CoreBundle\EventListener\Traits\PimcoreContextAwareTrait;
use Pimcore\Http\Request\Resolver\PimcoreContextResolver;
use Symfony\Component\HttpFoundation\Response;
use Pimcore\Model\Document;
use Pimcore\Extension\Bundle\PimcoreBundleManager;
use Pimcore\Version;
use Symfony\Component\Routing\RouterInterface;
use Pimcore\Document\Editable\EditmodeEditableDefinitionCollector;
use Pimcore\Model\Asset;
use Twig\Environment;
use Pimcore\Model\DataObject\ClassDefinition;
use CorepulseBundle\Services\Helper\SearchHelper;

class InertiaSubscriber implements EventSubscriberInterface
{
    use PimcoreContextAwareTrait;

    protected $security;

    protected $twig;
    /**
     * @var array
     */
    protected $contentTypes = [
        'text/html',
    ];

    public function __construct(Security $security, protected DocumentResolver $documentResolver, protected PimcoreBundleManager $bundleManager, protected RouterInterface $router, private EditmodeEditableDefinitionCollector $editableConfigCollector, Environment $twig)
    {
        $this->security = $security;
        $this->twig = $twig;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onControllerEvent',
            // KernelEvents::CONTROLLER => 'onKernelController',
            // KernelEvents::VIEW => 'onKernelView',
            KernelEvents::RESPONSE => 'onKernelResponse',
            KernelEvents::CONTROLLER_ARGUMENTS => 'onKernelControllerArguments',
        ];
    }

    public function onControllerEvent($event)
    {
        $user = $this->security->getUser();
        $request = $event->getRequest();

        $pathInfo = $request->getPathInfo();
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->matchesPimcoreContext($request, PimcoreContextResolver::CONTEXT_DEFAULT)) {
            return;
        }

        if ($request->get('cms_editmode') != 'true') {
            return;
        }

        if (!$this->contentTypeMatches($response)) {
            return;
        }

        $document = Document::getById((int) $request->get('id'));

        if (!$document) {
            return;
        }

        $this->addEditmodeAssets($document, $response);
        $this->twig->addGlobal('document', $this->twig->getGlobals()['document']);
        // set sameorigin header for editmode responses
        // $response->headers->set('X-Frame-Options', 'ALLOWALL', true);
    }

    public function onKernelView($event)
    {
        if (array_key_exists('document', $this->twig->getGlobals())) {
            // Key 'document1' exists in the array
            // Your code here
            $this->twig->addGlobal('document', $this->twig->getGlobals()['document']);
        }
    }

    public function onKernelControllerArguments($event)
    {
        if (array_key_exists('document', $this->twig->getGlobals())) {
            // Key 'document1' exists in the array
            // Your code here
            $this->twig->addGlobal('document', $this->twig->getGlobals()['document']);
        }
    }

    /**
     * Inject editmode assets into response HTML
     *
     * @param Document $document
     * @param Response $response
     */
    protected function addEditmodeAssets(Document $document, Response $response)
    {
        if (Document\Service::isValidType($document->getType())) {
            $html = $response->getContent();

            if (!$html) {
                return;
            }

            // $user = $this->userLoader->getUser();

            $htmlElement = preg_match('/<html[^a-zA-Z]?( [^>]+)?>/', $html);
            $headElement = preg_match('/<head[^a-zA-Z]?( [^>]+)?>/', $html);
            $bodyElement = preg_match('/<body[^a-zA-Z]?( [^>]+)?>/', $html);

            $skipCheck = false;

            // if there's no head and no body, create a wrapper including these elements
            // add html headers for snippets in editmode, so there is no problem with javascript
            if (!$headElement && !$bodyElement && !$htmlElement) {
                $html = "<!DOCTYPE html>\n<html>\n<head></head><body>" . $html . '</body></html>';
                $skipCheck = true;
            }

            if ($skipCheck || ($headElement && $bodyElement && $htmlElement)) {
                $startupJavascript = '/bundles/corepulse/js/editmode.js';

                // $headHtml = $this->buildHeadHtml($document, $user->getLanguage());
                $headHtml = $this->buildHeadHtml($document, 'en');
                $bodyHtml = "\n\n" . $this->editableConfigCollector->getHtml() . "\n\n";
                $bodyHtml .= "\n\n" . '<script src="' . $startupJavascript . '?_dc=' . time() . '"></script>' . "\n\n";

                $html = $this->insertBefore('</head>', $html, $headHtml);
                $html = $this->insertBefore('</body>', $html, $bodyHtml);

                $response->setContent($html);
            } else {
                $response->setContent('<div style="font-size:30px; font-family: Arial; font-weight:bold; color:red; text-align: center; margin: 40px 0">You have to define a &lt;html&gt;, &lt;head&gt;, &lt;body&gt;<br />HTML-tag in your view/layout markup!</div>');
            }
        }
    }

    private function insertBefore(string $search, string $code, string $insert): string
    {
        $endPosition = strripos($code, $search);

        if (false !== $endPosition) {
            $code = substr_replace($code, $insert . "\n\n" . $search, $endPosition, 7);
        }

        return $code;
    }

    /**
     * @param Document $document
     * @param string $language
     *
     * @return string
     */
    protected function buildHeadHtml(Document $document, $language)
    {
        date_default_timezone_set('Asia/Bangkok');
        $libraries = $this->getEditmodeLibraries();
        $scripts = $this->getEditmodeScripts();
        $stylesheets = $this->getEditmodeStylesheets();

        $headHtml = "\n\n\n<!-- pimcore editmode -->\n";
        $headHtml .= '<meta name="google" value="notranslate">';
        $headHtml .= "\n\n";

        // include stylesheets
        foreach ($stylesheets as $stylesheet) {
            $headHtml .= '<link rel="stylesheet" type="text/css" href="' . $stylesheet . '?_dc=' . time() . '" />';
            $headHtml .= "\n";
        }

        $headHtml .= "\n\n";

        // include script libraries
        foreach ($libraries as $script) {
            $headHtml .= '<script src="' . $script . '?_dc=' . Version::getRevision() . '"></script>';
            $headHtml .= "\n";
        }


        $path = $this->router->generate('pimcore_admin_misc_jsontranslationssystem', [
            'language' => $language,
            '_dc' => Version::getRevision(),
        ]);

        $headHtml .= '<script src="' . $path . '"></script>' . "\n";
        $headHtml .= '<script src="' . $this->router->generate('fos_js_routing_js', ['callback' => 'fos.Router.setData']) . '"></script>' . "\n";
        $headHtml .= '<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>' . "\n";
        $headHtml .= '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">' . "\n";
        $headHtml .= "\n\n";

        // lấy dữ liệu
        $data = DocumentServices::getDataDocument($document);

        // Chuyển đổi mảng $data thành chuỗi JSON
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT);

        // set var for editable configurations which is filled by Document\Tag::admin()
        $headHtml .= '<script>
            var editableDefinitions = [];
            var dataDocument = ' . $jsonData . ';
            var pimcore_document_id = ' . $document->getId() . ';
        </script>';

        $headHtml .= "\n\n<!-- /pimcore editmode -->\n\n\n";

        return $headHtml;
    }

    /**
     * @return array
     */
    protected function getEditmodeLibraries()
    {
        $disableMinifyJs = \Pimcore::disableMinifyJs();

        return [
            'https://cdn.ckeditor.com/ckeditor5/40.0.0/inline/ckeditor.js'
            // '/bundles/vuetify/js/lib/editmode.js?_dc=' . time(),
        ];
    }

    /**
     * @return array
     */
    protected function getEditmodeScripts()
    {
        return array_merge(
            [
                '/bundles/fosjsrouting/js/router.js',
                '/bundles/pimcoreadmin/js/pimcore/functions.js',
                '/bundles/pimcoreadmin/js/pimcore/overrides.js',
                '/bundles/pimcoreadmin/js/pimcore/tool/milestoneslider.js',
                '/bundles/pimcoreadmin/js/pimcore/element/tag/imagehotspotmarkereditor.js',
                '/bundles/pimcoreadmin/js/pimcore/element/tag/imagecropper.js',
                '/bundles/pimcoreadmin/js/pimcore/document/edit/helper.js',
                '/bundles/pimcoreadmin/js/pimcore/elementservice.js',
                '/bundles/pimcoreadmin/js/pimcore/document/edit/dnd.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editable.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/block.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/scheduledblock.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/date.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/relation.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/relations.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/checkbox.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/image.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/input.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/link.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/select.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/snippet.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/textarea.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/numeric.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/wysiwyg.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/renderlet.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/table.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/video.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/multiselect.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/area_abstract.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/areablock.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/area.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/pdf.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/embed.js',
                '/bundles/pimcoreadmin/js/pimcore/document/editables/manager.js',
                '/bundles/pimcoreadmin/js/pimcore/document/edit/helper.js',
            ],
            $this->bundleManager->getEditmodeJsPaths()
        );
    }

    /**
     * @return array
     */
    protected function getEditmodeStylesheets()
    {
        return array_merge(
            [
                '/bundles/corepulse/css/editmode.css',
            ],
            $this->bundleManager->getEditmodeCssPaths()
        );
    }

    /**
     * @param Response $response
     *
     * @return bool
     */
    protected function contentTypeMatches(Response $response)
    {
        $contentType = $response->headers->get('Content-Type');
        if (!$contentType) {
            return true;
        }

        // check for substring as the content type could define attributes (e.g. charset)
        foreach ($this->contentTypes as $ct) {
            if (false !== strpos($contentType, $ct)) {
                return true;
            }
        }

        return false;
    }
}
