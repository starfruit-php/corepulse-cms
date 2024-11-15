<?php

namespace CorepulseBundle\EventListener\Indexing;

use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Event\Model\DocumentEvent;
use CorepulseBundle\Services\SeoServices;
use Starfruit\BuilderBundle\Config\ObjectConfig;
use Starfruit\BuilderBundle\Tool\LanguageTool;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\Document\Page;
use CorepulseBundle\Services\GoogleServices;

class SubmitListener
{
    private function isSaveVersion($event)
    {
        $args = $event->getArguments();
        $saveVersionOnly = isset($args['saveVersionOnly']) && $args['saveVersionOnly'];

        return $saveVersionOnly;
    }

    public function postObjectUpdate(DataObjectEvent $event)
    {
        if (!$this->isSaveVersion($event)) {
            $this->generateObject($event->getObject(), 'update');
        }
    }

    public function postObjectAdd(DataObjectEvent $event)
    {
        if (!$this->isSaveVersion($event)) {
            $this->generateObject($event->getObject(), 'create');
        }
    }

    public function postObjectDelete(DataObjectEvent $event)
    {
        $this->generateObject($event->getObject(), 'delete');
    }

    public function postDocumentAdd(DocumentEvent $event)
    {
        if (!$this->isSaveVersion($event)) {
            $this->generateDocument($event->getDocument(), 'create');
        }
    }

    public function postDocumentUpdate(DocumentEvent $event)
    {
        if (!$this->isSaveVersion($event)) {
            $this->generateDocument($event->getDocument(), 'update');
        }
    }

    public function postDocumentDelete(DocumentEvent $event)
    {
        $this->generateDocument($event->getDocument(), 'delete');
    }

    private function generateObject($object, $type)
    {
        if(GoogleServices::eventConfig()) {
            try {
                if (!($object instanceof Folder)) {
                    $setting = GoogleServices::getConfig();
                    if ($setting['value']) {
                        $action = $this->action($setting['classes'], 'name', $object->getClassName());

                        if (isset($action['check']) && $action['check']) {
                            $languages = LanguageTool::getList();
                            foreach ($languages as $language) {
                                $objectConfig = new ObjectConfig($object);
                                $url = $objectConfig->getSlug(['locale' => $language]);

                                $params = [
                                    'url' => $url,
                                    'type' => $type,
                                    'objectOrDocument' => $object,
                                    'language' => $language,
                                ];
                                GoogleServices::submitIndex($params);
                            }
                        }
                    }
                }
            } catch (\Throwable $th) {
                //throw $th;
            }
        }
    }

    private function generateDocument($document, $type)
    {
        if(GoogleServices::eventConfig()) {
            try {
                if ($document instanceof Page) {
                    $setting = GoogleServices::getConfig();
                    if ($setting['value']) {
                        $action = $this->action($setting['documents'], 'id', $document->getId());
                        $check = false;
                        $language = '';

                        if ((isset($action['generateSitemap']) && $action['generateSitemap']) || $type == 'delete') {
                            $check = true;
                            $language = isset($action['language']) ? $action['language'] : '';
                        }

                        if ($check) {
                            $url = $document->getPrettyUrl();
                            if (!$url) {
                                $url = $document->getPath() . $document->getKey();
                            }
                            $params = [
                                'url' => $url,
                                'type' => $type,
                                'objectOrDocument' => $document,
                                'language' => $language,
                            ];
                            GoogleServices::submitIndex($params);
                        }
                    }
                }
            } catch (\Throwable $th) {
            }
        }
    }

    private function action($array, $name, $search)
    {
        $action = array_filter($array, function($item) use ($name, $search) {
            return $item[$name] == $search;
        });

        if (isset($action) && $first = reset($action)) {
            return $first;
        }

        return null;
    }
}
