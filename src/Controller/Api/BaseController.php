<?php

namespace CorepulseBundle\Controller\Api;

use DateTime;
use ValidatorBundle\Validator\Validator;
use Pimcore\Controller\FrontendController;
use Pimcore\Translation\Translator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Process\Process;

class BaseController extends FrontendController
{
    protected $request;
    protected $translator;
    protected $validator;
    protected $paginator;
    protected $params;

    public function __construct(
        RequestStack $requestStack,
        Translator $translator,
        ValidatorInterface $validator,
        PaginatorInterface $paginator,
        ParameterBagInterface $params
        )
    {
        $this->request = $requestStack->getCurrentRequest();
        $this->translator = $translator;
        $this->validator = new Validator($validator, $this->translator);
        $this->paginator = $paginator;
        $this->params = $params;
        $this->setLocaleRequest();
    }

     /**
     * Assign language to request.
     */
    public function setLocaleRequest()
    {
        if ($this->request->get('_locale')) {
            $this->request->setLocale($this->request->get('_locale'));
        }

        if ($this->request->headers->has('locale')) {
            $this->translator->setLocale($this->request->headers->get('locale'));
        }
    }

    /**
     * Return a success response.
     *
     * @param array $data
     *
     * @return JsonResponse
     */
    public function sendResponse($data = null, string $message = null)
    {
        $result = [];

        if ($message) {
            $result['message'] = $this->translator->trans($message);
        }

        $result['data'] = $data;

        return new JsonResponse($result, Response::HTTP_OK);
    }

    /**
     * Return an error response.
     *
     * @param $error
     * @param int $statusCode
     *
     * @return JsonResponse
     */
    public function sendError($error, $statusCode = Response::HTTP_BAD_REQUEST)
    {
        // log if status code = 500
        if ($statusCode == Response::HTTP_INTERNAL_SERVER_ERROR) {
            // Lưu log vào db hoặc file
        } else {
            if (is_array($error)) {
                $error = ["errors" => $error];
            }
            if (is_string($error)) {
                $error = ["error" => ["message" => $this->translator->trans($error)]];
            }
        }

        return new JsonResponse($error, $statusCode);
    }

    public function getPaginationConditions(Request $request, array $orderByOptions)
    {
        $page = $request->get('page') ?: 1;
        $limit = $request->get('limit') ?: 10;

        $condition = [
            'page' => 'numeric|positive',
            'limit' => 'numeric|positive|lessthan:101',
            'order_by' => $request->get('order_by') ? (!empty($orderByOptions) ? 'choice:' . implode(',', $orderByOptions) : '' ) : '',
            'order' => $request->get('order') ? 'choice:desc,asc' : '',
        ];

        return [$page, $limit, $condition];
    }

    /**
     * Paginator helper.
     */
    public function paginator($listing, $page, $limit)
    {
        $pagination = $this->paginator->paginate(
            $listing,
            $page,
            $limit,
        );

        return $pagination;
    }

    public function helperPaginator($paginator, $listing, $page = 1, $limit = 10)
    {
        $pagination = $paginator->paginate(
            $listing,
            $page,
            $limit,
        );

        $paginationData['paginationData'] = $pagination->getPaginationData();

        return $paginationData;
    }


    public function checkLastest($object)
    {
        $lastest = $this->getLastest($object);

        if ($lastest) {
            return $object->getModificationDate() !== $lastest->getModificationDate();
        }
        return false;
    }

    public function getLastest($object)
    {
        $versions = $object->getVersions();

        if (empty($versions)) {
            return $object;
        }

        $previousVersion = $versions[count($versions) - 1];
        $previousObject = $previousVersion->getData();
        return $previousObject;
    }

    public function getTimeAgo($timestamp)
    {
        // Create DateTime objects for the current time and the given timestamp
        $currentDateTime = new DateTime();
        $timestampDateTime = new DateTime("@$timestamp");

        // Calculate the difference between the current time and the given timestamp
        $interval = $currentDateTime->diff($timestampDateTime);

        // Format the result based on the difference
        if ($interval->y > 0) {
            return $interval->y . " year" . ($interval->y > 1 ? "s" : "") . " ago";
        } elseif ($interval->m > 0) {
            return $interval->m . " month" . ($interval->m > 1 ? "s" : "") . " ago";
        } elseif ($interval->d > 0) {
            return $interval->d . " day" . ($interval->d > 1 ? "s" : "") . " ago";
        } elseif ($interval->h > 0) {
            return $interval->h . " hour" . ($interval->h > 1 ? "s" : "") . " ago";
        } elseif ($interval->i > 0) {
            return $interval->i . " minute" . ($interval->i > 1 ? "s" : "") . " ago";
        } else {
            return "just now";
        }
    }

    public function runProcess($command)
    {
        try {
            $process = new Process(explode(' ', 'php ' . str_replace("\\", '/', PIMCORE_PROJECT_ROOT) . '/bin/console ' . $command), null, null, null, 900);

            $process->run();
        } catch (\Throwable $e) {
        }
    }


    public function getQueryCondition($rule, $filters)
    {
        $conditionQuery = [];
        $conditionParams = [];
        $rule = strtoupper($rule);

        foreach ($filters as $key => $value) {
            foreach ($value as $k => $val) {
                foreach ($val as $i => $v) {
                    if (isset($v['value']) && $v['value']) {
                        // Convert date to timestamp if applicable
                        if ($i === 'date') {
                            $v['value'] = strtotime($v['value']);
                        }

                        // Create query string based on condition
                        switch ($v['condition']) {
                            case 'equal':
                            case 'alike':
                                $conditionQuery[] = " $k = :$k ";
                                break;
                            case 'biggerThan':
                            case 'after':
                                $conditionQuery[] = " $k > :$k ";
                                break;
                            case 'smallerThan':
                            case 'before':
                                $conditionQuery[] = " $k < :$k ";
                                break;
                            case 'includes':
                                $conditionQuery[] = " LOWER($k) LIKE LOWER(:$k) ";
                                $v['value'] = "%" . $v['value'] . "%";
                                break;
                            case 'notIncludes':
                                $conditionQuery[] = " LOWER($k) NOT LIKE LOWER(:$k) ";
                                $v['value'] = "%" . $v['value'] . "%";
                                break;
                            case 'notAlike':
                                $conditionQuery[] = " $k != :$k ";
                                break;
                        }

                        $conditionParams[$k] = $v['value'];
                    }
                }
            }
        }

        $query = implode($rule, $conditionQuery);
        return ['query' => $query, 'params' => $conditionParams];
    }
}
