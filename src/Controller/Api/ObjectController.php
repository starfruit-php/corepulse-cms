<?php

namespace CorepulseBundle\Controller\Api;

use CorepulseBundle\Services\ClassServices;
use CorepulseBundle\Services\DataObjectServices;
use Pimcore\Db;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyObjectRelation;
use Pimcore\Model\DataObject\ClassDefinition\Data\Relations\AbstractRelations;
use Pimcore\Model\DataObject\ClassDefinition\Data\ReverseObjectRelation;

/**
 * @Route("/object")
 */
class ObjectController extends BaseController
{
    private array $objectData = [];

    private array $objectLayoutData = [];

    private array $metaData = [];

    private array $optionData = [];

    /**
     * @Route("/get-column-setting", name="corepulse_api_object_get_column_setting", methods={"GET"})
     */
    public function getColumnSetting()
    {
        try {
            $condition = [
                'id' => 'required',
            ];
            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $classId = $this->request->get("id");

            $checkClass = ClassServices::isValid($classId);

            if (!$checkClass) {
                return $this->sendError([
                    'success' => false,
                    'message' => 'Class not found.'
                ]);
            }

            $classConfig = ClassServices::getConfig($classId);
            $visibleFields = json_decode($classConfig['visibleFields'], true);

            if (!$visibleFields) {
                return $this->sendError([
                    'success' => false,
                    'message' => 'Invalid or missing visible fields.'
                ]);
            }

            $fields = $visibleFields['fields'];
            $columns = array_merge(ClassServices::systemField($visibleFields), $fields);
            $tableView = isset($visibleFields['tableView']) ? $visibleFields['tableView'] : null;

            $visibleGridView = ClassServices::filterFill($columns, $tableView);

            $types = [];
            foreach ($columns as $key => $item) {
                if (in_array($item['fieldtype'], ClassServices::BACKLIST_TYPE) ) {
                    unset($columns[$key]);
                    continue;
                }

                if (!in_array($item['type'], $types)) {
                    $types[] = $item['type'];
                }
            }

            $data = [
                'columns' => $columns,
                'visibleGridView' => $visibleGridView,
                'types' => $types,
            ];

            return $this->sendResponse($data);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }

    /**
     * @Route("/listing-by-object", name="corepulse_api_object_listing", methods={"GET"})
     */
    public function listingByObject()
    {
        try {
            $conditions = $this->getPaginationConditions($this->request, []);
            list($page, $limit, $condition) = $conditions;

            $condition = array_merge($condition, [
                'id' => 'required',
                'columns' => 'array',
            ]);
            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            $classId = $this->request->get("id");

            $classValidation = $this->validateClass($classId);
            if (!$classValidation['success']) {
                return $this->sendError($classValidation);
            }

            $className = $classValidation['className'];
            $columns = $classValidation['columns'];
            $fields = $this->request->get('columns') ? ClassServices::filterFill($columns, $this->request->get('columns')) : $columns;

            $locale = $this->request->get('_locale', $this->request->getLocale());

            $conditionQuery = 'id is not NULL';
            $conditionParams = [];

            $filterRule = $this->request->get('filterRule');
            $filter = $this->request->get('filter');

            if ($filterRule && $filter) {
                $arrQuery = $this->getQueryCondition($filterRule, $filter);

                if ($arrQuery['query']) {
                    $conditionQuery .= ' AND (' . $arrQuery['query'] . ')';
                    $conditionParams = array_merge($conditionParams, $arrQuery['params']);
                }
            }

            $orderKey = $this->request->get('order_by');
            $order = $this->request->get('order');
            if (empty($orderKey)) $orderKey = 'key';
            if (empty($order)) $order = 'asc';

            if ($limit == -1) {
                $limit = 10000;
            }

            $listing = call_user_func_array('\\Pimcore\\Model\\DataObject\\' . $className . '::getList', [["unpublished" => true]]);
            $listing->setCondition($conditionQuery, $conditionParams);
            $listing->setLocale($locale);
            $listing->setUnpublished(true);
            $listing->setOrderKey($orderKey);
            $listing->setOrder($order);

            $pagination = $this->paginator($listing, $page, $limit);

            $data = [
                'paginationData' => $pagination->getPaginationData(),
                'data' => []
            ];

            foreach($pagination as $item) {
                $data['data'][] =  DataObjectServices::getData($item, $fields, true);
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/detail/{id}", name="corepulse_api_object_detail", methods={"GET", "POST"})
     */
    public function detail()
    {
        try {
            // Validate request
            $condition = [ 'id' => 'required' ];
            $messageError = $this->validator->validate($condition, $this->request);
            if($messageError) return $this->sendError($messageError);

            // Retrieve object from database
            $id = $this->request->get('id');
            $objectFromDatabase = DataObject\Concrete::getById($id);
            if (!$objectFromDatabase) return $this->sendError('Object not found');

            // Validate class
            $classId = $objectFromDatabase->getClassId();
            $classValidation = $this->validateClass($classId);
            if (!$classValidation['success']) {
                return $this->sendError($classValidation);
            }

            // Handle POST request for updates
            if ($this->request->isMethod(Request::METHOD_POST)) {
                return $this->handlePostUpdate($objectFromDatabase);
            }

            // Prepare object data for response
            return $this->prepareObjectDataResponse($objectFromDatabase);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/options", name="corepulse_api_object_option", methods={"GET", "POST"})
     */
    public function options()
    {
        try {
            $condition = [
                'id' => 'required',
                'class' => !$this->request->get('type') ? 'required' : '',
                'type' => $this->request->get('type') ? 'choice:block,fieldcollections,localizedfields' : '',
                'typeId' => $this->request->get('type') ? 'required' : '',
                'fieldId' => $this->request->get('type') ? 'required' : '',
            ];

            $messageError = $this->validator->validate($condition, $this->request);
            if ($messageError) {
                return $this->sendError($messageError);
            }

            $type = $this->request->get('type');

            $data = [];
            switch ($type) {
                case 'fieldcollections':
                    $typeId = $this->request->get('typeId');
                    $fieldId = $this->request->get('fieldId');
                    $data = ClassServices::handleOption('fieldcollections', $typeId, $fieldId);
                    break;
                case 'localizedfields':
                    $class = $this->request->get('class');
                    $fieldId = $this->request->get('fieldId');
                    $data = ClassServices::handleOption('localizedfields', $class,  $fieldId);
                    break;
                case 'block':
                    $class = $this->request->get('class');
                    $id = $this->request->get('id');
                    $fieldId = $this->request->get('fieldId');
                    $data = ClassServices::handleOption('block', $class, [$id, $fieldId]);
                    break;
                default:
                $class = $this->request->get('class');
                $id = $this->request->get('id');
                $data = ClassServices::handleOption('class', $class, $id);
                    break;
            }

            return $this->sendResponse($data);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/delete", name="corepulse_api_object_delete", methods={"POST"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function delete()
    {
        try {
            $condition = [
                'id' => 'required',
                'classId' => 'required',
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $classId = $this->request->get('classId');
            $classValidation = $this->validateClass($classId);

            if (!$classValidation['success']) {
                return $this->sendError($classValidation);
            }

            $ids = $this->request->get('id');
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    $this->deleteAction($id);
                }
            } else {
                $this->deleteAction($ids);
            }

            return $this->sendResponse([
                'success' => true,
                'message' => "Delete object success."
            ]);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function deleteAction($id)
    {
        $object = DataObject::getById((int) $id);
        if ($object) {
            $object->delete();
        } else {
            return $this->sendError("Can not find object $id to be deleted");
        }
    }

    /**
     * @Route("/add", name="corepulse_api_object_add", methods={"POST"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function add()
    {
        try {
            $condition = [
                'classId' => 'required',
                'type' => '',
                'key' => 'required',
                'parentId' => 'numeric',
                'folderName' => '',
                'checked' => ''
            ];

            $errorMessages = $this->validator->validate($condition, $this->request);
            if ($errorMessages) return $this->sendError($errorMessages);

            $classId = $this->request->get('classId');
            $classValidation = $this->validateClass($classId);
            if (!$classValidation['success']) {
                return $this->sendError($classValidation);
            }

            $className = $classValidation['className'];
            $fields = $classValidation['columns'];

            $folderName = $this->request->get('folderName', $className);
            $parentId = $this->request->get('parentId') ? (int)$this->request->get('parentId') : 1;
            $key = $this->request->get('key');

            $parentItem =  DataObject::getById($parentId);
            $pathItem = $parentItem->getPath() . $key;

            $item =  DataObject::getByPath($pathItem);

            if (!$item) {
                $parent = '';

                if ($folderName) {
                    $parent = DataObject::getByPath("/" . $folderName) ?? DataObject\Service::createFolderByPath("/" . $folderName);
                }

                if (!$parent) {
                    $parent = DataObject::getById($parentId);
                }

                $func = '\\Pimcore\\Model\\DataObject\\' . ucfirst($className);

                $object = new $func();
                $object->setKey($key);
                $object->setParent($parent);
                $object->save();

                if ($this->request->get('checked')) {
                    return $this->sendResponse(['success' => true, 'message' => 'Create success']);
                }

                $data['data'] =  DataObjectServices::getData($object, $fields);
                return $this->sendResponse($data);
            }

            return $this->sendError($className . ' with ' . $key . " already exists");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @Route("/get-sidebar", name="corepulse_api_object_slider_bar", methods={"GET"})
     *
     * {mô tả api}
     *
     * @param Cache $cache
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function getSidebar()
    {
        try {
            $objectSetting = Db::get()->fetchAssociative('SELECT * FROM `corepulse_settings` WHERE `type` = "object"', []);

            $datas = [];
            if ($objectSetting) {
                $query = 'SELECT * FROM `classes`';
                $classListing = Db::get()->fetchAllAssociative($query);
                $dataObjectSetting = json_decode($objectSetting['config']) ?? [];
                $data = [];
                foreach ($classListing as $class) {
                    if (in_array($class['id'], $dataObjectSetting)) {
                        $classDefinition = DataObject\ClassDefinition::getById($class['id']);

                        if ($classDefinition) {
                            $newData["id"] = $class["id"];
                            $newData["name"] = $class["name"];
                            $newData["title"] = $classDefinition?->getTitle() ?? $classDefinition?->getName();

                            $data[] = $newData;
                        }
                    }
                }
                $datas['data'] = $data;
            }

            return $this->sendResponse($datas);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    private function validateClass($classId)
    {
        $checkClass = ClassServices::isValid($classId);
        $classConfig = ClassServices::getConfig($classId);

        if (!isset($classConfig['visibleFields']) || ($visibleFields = json_decode($classConfig['visibleFields'], true)) === null) {
            return [
                'success' => false,
                'message' => 'Invalid or missing visible fields.'
            ];
        }

        $className = $visibleFields['class'] ?? null;

        if (!$checkClass || !$className || !class_exists('\\Pimcore\\Model\\DataObject\\' . ucfirst($className))) {
            return [
                'success' => false,
                'message' => 'Class not found.'
            ];
        }

        return [
            'success' => true,
            'className' => $className,
            'columns' => array_merge(ClassServices::systemField($visibleFields), $visibleFields['fields'])
        ];
    }

    private function handlePostUpdate($objectFromDatabase)
    {
        $localized = $this->request->get('_locale', $this->request->getLocale());
        $data = $this->request->get('data');
        if ($data) {
            $data = json_decode($data, true);
            // try {
                DataObjectServices::saveEdit($objectFromDatabase, $data, $localized);
                return $this->sendResponse(['success' => true, 'message' => 'Object update success.']);
            // } catch (\Throwable $th) {
            //     return $this->sendError(['success' => false, 'message' => $th->getMessage()]);
            // }
        }
        return $this->sendError(['success' => false, 'message' => 'Object update false.']);
    }

    private function prepareObjectDataResponse($objectFromDatabase)
    {
        $objectFromDatabase = clone $objectFromDatabase;
        $draftVersion = null;
        $object = $this->getLatestVersion($objectFromDatabase, $draftVersion);
        $objectFromVersion = $object !== $objectFromDatabase;

        $objectData = $this->getDraftData($objectFromDatabase, $draftVersion);
        $this->populateObjectData($object, $objectFromVersion, $objectData);

        return $this->sendResponse($objectData);
    }

    private function getDraftData($objectFromDatabase, $draftVersion)
    {
        $objectData = [];
        if ($draftVersion && $objectFromDatabase->getModificationDate() < $draftVersion->getDate()) {
            $objectData['draft'] = [
                'id' => $draftVersion->getId(),
                'modificationDate' => $draftVersion->getDate(),
                'isAutoSave' => $draftVersion->isAutoSave(),
            ];
        }
        return $objectData;
    }

    private function populateObjectData($object, $objectFromVersion, &$objectData)
    {
        try {
            $this->getDataForObject($object, $objectFromVersion);
        } catch (\Throwable $e) {
            $this->getDataForObject(clone $object, false);
        }

        $objectData['metaData'] = $this->metaData;
        $layout = DataObject\Service::getSuperLayoutDefinition($object);
        $objectData['layout'] = $this->getObjectVarsRecursive($object, $layout, $object->getClassId());
        $objectData['layoutData'] = $this->objectLayoutData;
        $objectData['sidebar'] = DataObjectServices::getSidebarData($object, $this->request->get('_locale', $this->request->getLocale()));
        $objectData['optionData'] = $this->optionData;
    }

    protected function getObjectVarsRecursive($object, $layout, $classId, $localized = 'fieldcollections', $optionKey = false)
    {
        $vars = get_object_vars($layout);
        if (method_exists($layout, 'getFieldType')) {
            $vars['fieldtype'] = $layout->getFieldType();
            $getClass = '\\CorepulseBundle\\Component\\Field\\' . ucfirst($vars['fieldtype']);
            if (class_exists($getClass)) {
                $component = new $getClass($object, $vars, null, $localized);
                $this->objectLayoutData[$vars['name']] = $component->getValue();
                if(!$optionKey && in_array($layout->getFieldType(), ClassServices::TYPE_OPTION)) {
                    $this->optionData[$vars['name']] = [
                        'id' => $vars['name'],
                        'class' => $classId
                    ];
                }

                if ($vars['fieldtype'] == 'fieldcollections') {
                    $vars['layouts'] = $component->getDefinitions();
                    $vars['api_options'] = [
                        'id' => $vars['name'],
                        'class' => $classId,
                        'value' => $component->getOptionKey(),
                    ];
                }

                if ($vars['fieldtype'] == 'block') {
                    $vars['children'] = $component->getDefinitions();
                    $vars['api_options'] = [
                        'id' => $vars['name'],
                        'class' => $classId,
                        'value' => $component->getOptionKey(),
                    ];
                }

                if ($vars['fieldtype'] == 'localizedfields') {
                    $component->getDefinitions();
                    $vars['api_options'] = [
                        'id' => $vars['name'],
                        'class' => $classId,
                        'value' => $component->getOptionKey(),
                    ];
                }
            }
        }

        $localized = $this->request->get('_locale', $this->request->getLocale());

        if (isset($vars['children']) && (isset($vars['fieldtype']) && $vars['fieldtype'] != 'block')) {
            foreach ($vars['children'] as $key => $value) {
                $vars['children'][$key] = $this->getObjectVarsRecursive($object, $value, $classId, $localized, $vars['fieldtype'] == 'localizedfields');
            }
        }

        return $vars;
    }

    protected function getLatestVersion(DataObject\Concrete $object,  ? DataObject\Concrete &$draftVersion = null) : DataObject\Concrete
    {
        $latestVersion = $object->getLatestVersion();
        if ($latestVersion) {
            $latestObj = $latestVersion->loadData();
            if ($latestObj instanceof DataObject\Concrete) {
                $draftVersion = $latestVersion;

                return $latestObj;
            }
        }

        return $object;
    }

    private function getDataForObject(DataObject\Concrete $object, bool $objectFromVersion = false): void
    {
        foreach ($object->getClass()->getFieldDefinitions(['object' => $object]) as $key => $def) {
            $this->getDataForField($object, $key, $def, $objectFromVersion);
        }
    }

    /**
     * Gets recursively attribute data from parent and fills objectData and metaData
     */
    private function getDataForField(DataObject\Concrete $object, string $key, DataObject\ClassDefinition\Data $fielddefinition, bool $objectFromVersion, int $level = 0): void
    {
        $parent = DataObject\Service::hasInheritableParentObject($object);

        $getter = 'get' . ucfirst($key);

        // Editmode optimization for lazy loaded relations (note that this is just for AbstractRelations, not for all
        // LazyLoadingSupportInterface types. It tries to optimize fetching the data needed for the editmode without
        // loading the entire target element.
        // ReverseObjectRelation should go in there anyway (regardless if it a version or not),
        // so that the values can be loaded.
        if (
            (!$objectFromVersion && $fielddefinition instanceof AbstractRelations)
            || $fielddefinition instanceof ReverseObjectRelation
        ) {
            $refId = null;

            if ($fielddefinition instanceof ReverseObjectRelation) {
                $refKey = $fielddefinition->getOwnerFieldName();
                $refClass = DataObject\ClassDefinition::getByName($fielddefinition->getOwnerClassName());
                if ($refClass) {
                    $refId = $refClass->getId();
                }
            } else {
                $refKey = $key;
            }

            $relations = $object->getRelationData($refKey, !$fielddefinition instanceof ReverseObjectRelation, $refId);

            if ($fielddefinition->supportsInheritance() && empty($relations) && !empty($parent)) {
                $this->getDataForField($parent, $key, $fielddefinition, $objectFromVersion, $level + 1);
            } else {
                $data = [];

                if ($fielddefinition instanceof DataObject\ClassDefinition\Data\ManyToOneRelation) {
                    if (isset($relations[0])) {
                        $data = $relations[0];
                        $data['published'] = (bool) $data['published'];
                    } else {
                        $data = null;
                    }
                } elseif (
                    ($fielddefinition instanceof DataObject\ClassDefinition\Data\OptimizedAdminLoadingInterface && $fielddefinition->isOptimizedAdminLoading())
                    || ($fielddefinition instanceof ManyToManyObjectRelation && !$fielddefinition->getVisibleFields() && !$fielddefinition instanceof DataObject\ClassDefinition\Data\AdvancedManyToManyObjectRelation)
                ) {
                    foreach ($relations as $rkey => $rel) {
                        $index = $rkey + 1;
                        $rel['fullpath'] = $rel['path'];
                        $rel['classname'] = $rel['subtype'];
                        $rel['rowId'] = $rel['id'] . AbstractRelations::RELATION_ID_SEPARATOR . $index . AbstractRelations::RELATION_ID_SEPARATOR . $rel['type'];
                        $rel['published'] = (bool) $rel['published'];
                        $data[] = $rel;
                    }
                } else {
                    $fieldData = $object->$getter();
                    $data = $fielddefinition->getDataForEditmode($fieldData, $object, ['objectFromVersion' => $objectFromVersion]);
                }
                $this->objectData[$key] = $data;
                $this->metaData[$key]['objectid'] = $object->getId();
                $this->metaData[$key]['inherited'] = $level != 0;
            }
        } else {
            $fieldData = $object->$getter();
            $isInheritedValue = false;

            if ($fielddefinition instanceof DataObject\ClassDefinition\Data\CalculatedValue) {
                $fieldData = new DataObject\Data\CalculatedValue($fielddefinition->getName());
                $fieldData->setContextualData('object', null, null, null, null, null, $fielddefinition);
                $value = $fielddefinition->getDataForEditmode($fieldData, $object, ['objectFromVersion' => $objectFromVersion]);
            } else {
                $value = $fielddefinition->getDataForEditmode($fieldData, $object, ['objectFromVersion' => $objectFromVersion]);
            }

            // following some exceptions for special data types (localizedfields, objectbricks)
            if ($value && ($fieldData instanceof DataObject\Localizedfield || $fieldData instanceof DataObject\Classificationstore)) {
                // make sure that the localized field participates in the inheritance detection process
                $isInheritedValue = $value['inherited'];
            }
            if ($fielddefinition instanceof DataObject\ClassDefinition\Data\Objectbricks && is_array($value)) {
                // make sure that the objectbricks participate in the inheritance detection process
                foreach ($value as $singleBrickData) {
                    if (!empty($singleBrickData['inherited'])) {
                        $isInheritedValue = true;
                    }
                }
            }

            if ($fielddefinition->isEmpty($fieldData) && !empty($parent)) {
                $this->getDataForField($parent, $key, $fielddefinition, $objectFromVersion, $level + 1);
                // exception for classification store. if there are no items then it is empty by definition.
                // consequence is that we have to preserve the metadata information
                // see https://github.com/pimcore/pimcore/issues/9329
                if ($fielddefinition instanceof DataObject\ClassDefinition\Data\Classificationstore && $level == 0) {
                    $this->objectData[$key]['metaData'] = $value['metaData'] ?? [];
                    $this->objectData[$key]['inherited'] = true;
                }
            } else {
                $isInheritedValue = $isInheritedValue || ($level != 0);
                $this->metaData[$key]['objectid'] = $object->getId();

                $this->objectData[$key] = $value;
                $this->metaData[$key]['inherited'] = $isInheritedValue;

                if ($isInheritedValue && !$fielddefinition->isEmpty($fieldData) && !$fielddefinition->supportsInheritance()) {
                    $this->objectData[$key] = null;
                    $this->metaData[$key]['inherited'] = false;
                    $this->metaData[$key]['hasParentValue'] = true;
                }
            }
        }
    }
}
