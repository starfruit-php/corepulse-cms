<?php

namespace CorepulseBundle\Services;

use CorepulseBundle\Services\APIService;
use Pimcore\Db;
use Starfruit\BuilderBundle\Tool\LanguageTool;
use Starfruit\BuilderBundle\Model\Option;
use CorepulseBundle\Model\Indexing;
use Pimcore\Model\Document;
use Starfruit\BuilderBundle\Sitemap\Setting;

class SeoServices
{
    static public function checkApi($key)
    {
        $content = [
            [
                "role" => "user",
                "content" => "Say this is a test!"
            ]
        ];

        $connect = self::sendCompletions($content, $key);
        if ($connect && !$connect['success']) {
            return false;
        }

        $data = ['config' => $key, 'type' => 'setting-ai'];
        // Insert or update in a single query
        $setting = self::getApiKey();
        $setting ? Db::get()->update('corepulse_settings', $data, ['type' => 'setting-ai']) : Db::get()->insert('corepulse_settings', $data);

        return true;
    }

    static public function getApiKey()
    {
        $setting = Db::get()->fetchAssociative('SELECT * FROM `corepulse_settings` WHERE `type` = "setting-ai"');
        return $setting['config'] ?? null; // Use null coalescing operator
    }

    static public function sendCompletions($content, $key = null)
    {
        $key = $key ?? self::getApiKey(); // Use null coalescing assignment

        $url = 'https://api.openai.com/v1/chat/completions';
        $header = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $key,
        ];
        $params = [
            // "model" => "gpt-4o-mini",
            "model" => "gpt-3.5-turbo",
            "messages" => $content,
            "temperature" => 0.7
        ];

        $response = APIService::post($url, 'POST', $params, $header);

        $data = $response && isset($response['choices']) ? ['success' => true,'data' => $response['choices'][0]['message']['content']] : ['success' => false, 'data' => ''];

        return $data;
    }

    static public function choicesContent($keyword, $type, $language = null)
    {
        $language = $language ?? LanguageTool::getDefault(); // Use null coalescing assignment
        $languageName = \Locale::getDisplayLanguage($language);
        $keyword = str_replace('"', "", $keyword);

        $content = [];
        if ($type == 'sematic') {
            $content = [[
                'role' => 'user',
                'content' => "I have a blog post about '$keyword' Can you give me 10 keywords and semantic entities in  $languageName that I can include in the content to make it better and more relevant so that Google understands my content faster and more accurately? Please return only the results as an unordered list directly in HTML code format, without any explanation or introduction",
            ]];
        } else if ($type == 'outline') {
            $content = [[
                'role' => 'user',
                'content' => "Please create an outline for the keyword '$keyword' based on EEAT principles. The outline should focus on depth and detail of content, demonstration of expertise and credibility, and how well it meets user intent. Ensure the outline is at least as good as the competitors'. Please return only the results as an unordered list directly in HTML code format, without any explanation or introduction the outline in a visual list style using HTML with appropriate heading levels (h1, h2, h3) and list formats (ol, li) in the language $languageName.",
            ]];
        }

        return $content;
    }

    static public function saveData($seo, $params)
    {
        $keyUpdate = ['keyword', 'title', 'slug', 'description', 'image', 'canonicalUrl', 'redirectLink',
        'nofollow', 'indexing', 'redirectType', 'destinationUrl', 'schemaBlock', 'imageAsset'];

        foreach ($params as $key => $value) {
            $function = 'set' . ucfirst($key);
            if (in_array($key, $keyUpdate) && method_exists($seo, $function)) {
                if (in_array($key, ['nofollow', 'indexing', 'redirectLink'])) {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
                $seo->$function($value);
            }
        }
        $seo->save();
        return $seo;
    }

    static public function saveMetaData($seo, $params)
    {
        $ogMeta = self::revertMetaData($params['ogMeta'] ?? []);
        $twitterMeta = self::revertMetaData($params['twitterMeta'] ?? []);
        $customMeta = self::revertMetaData($params['customMeta'] ?? []);

        $seo->setMetaDatas($ogMeta, $twitterMeta, $customMeta);
        $seo->save();
        return $seo;
    }

    static public function revertMetaData($array)
    {
        return array_reduce($array, function ($carry, $item) {
            return array_merge($carry, $item);
        }, []);
    }

    static public function getSetting()
    {
        $setting = Option::getByName('seo_setting') ?? new Option(); // Use null coalescing operator
        if (!$setting->getId()) {
            $data = [
                'type' => null,
                'defaultValue' => null,
                'customValue' => null,
            ];
            $setting->setName('seo_setting');
            $setting->setContent(json_encode($data));
            $setting->save();
        }
        return $setting;
    }

    static public function saveSetting($setting, $params = [])
    {
        if (!empty($params)) {
            $data = [
                'type' => null,
                'defaultValue' => null,
                'customValue' => null,
            ];

            foreach ($data as $key => $value) {
                if (isset($params[$key])) {
                    $data[$key] = $params[$key];
                }
            }

            $setting->setContent(json_encode($data));
            $setting->save();
        }
        return $setting;
    }

    static public function updateRedirect($redirect, $data = [])
    {
        if (!empty($data['target']) && $doc = Document::getByPath($data['target'])) {
            $data['target'] = $doc->getId();
        }

        if (isset($data['regex']) && !$data['regex'] && !empty($data['source'])) {
            $data['source'] = str_replace('+', ' ', $data['source']);
        }

        $redirect->setValues($data, true);
        $redirect->save();

        if (is_numeric($redirectTarget = $redirect->getTarget()) && $doc = Document::getById((int)$redirectTarget)) {
            $redirect->setTarget($doc->getRealFullPath());
        }

        return $redirect;
    }
}
