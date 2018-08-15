<?php

namespace app\controllers;

use app\models\Product;
use Yii;
use yii\base\ErrorException;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;

class SiteController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionSave()
    {
        try {
            $property = self::getPropertyByName(Yii::$app->request->get('model'));
        } catch (ErrorException $e) {
            return $this->asJson([
                'status' => 400,
                'errors' => $e->getMessage()
            ]);
        }

        $model = new $property['class']();

        $this->saveModel($model, $property, Yii::$app->request->get('model'), Yii::$app->request->get('info'), false);
    }

    public function actionUpdate()
    {
        try {
            $propertyName = Yii::$app->request->get('model');
            $property = self::getPropertyByName(Yii::$app->request->get('model'));
        } catch (ErrorException $e) {
            return $this->asJson([
                'status' => 400,
                'errors' => $e->getMessage()
            ]);
        }

        $modelKeys = (new $property['class']())::primaryKey();
        foreach ($modelKeys as $modelKey) {
            $condition[$modelKey] = Yii::$app->request->get($modelKey);
        }

        $updateModel = ($property['class'])::findOne($condition);
        if (empty($updateModel)) {
            return $this->asJson([
                'status' => 404,
                'errors' => 'Model not found'
            ]);
        }

        $this->saveModel($updateModel, $property, $propertyName, Yii::$app->request->get('info'), true);
    }

    public function actionView()
    {
        try {
            $propertyName = Yii::$app->request->get('model');
            $property = self::getPropertyByName($propertyName);
        } catch (ErrorException $e) {
            return $this->asJson([
                'status' => 400,
                'errors' => $e->getMessage()
            ]);
        }

        $condition = Yii::$app->request->get('condition');
        if (!isset($condition)) {
            $modelKeys = (new $property['class']())::primaryKey();
            foreach ($modelKeys as $modelKey) {
                $primaryKey = Yii::$app->request->get($modelKey);
                if (empty($primaryKey)) {
                    break;
                }
                $condition[$modelKey] = Yii::$app->request->get($modelKey);
            }
        }

        if (!empty($property['relations'])) {
            foreach ($property['relations'] as $relation) {
                $relations[] = $this->getPropertyByName($relation)['relationName'];
            }
        }

        $sql = ($property['class'])::find()->with($relations);

        foreach ($condition as $singleCondition) {
            $sql->andWhere($singleCondition);
        }
        $viewModels = $sql->all();
        if (empty($viewModels)) {
            return $this->asJson([
                'status' => 404,
                'errors' => 'Model not found'
            ]);
        }

        $result = [];
        foreach ($viewModels as $model) {
            $result[] = $this->combineApiFields($model, $propertyName);
        }

        if (empty($result)) {
            return $this->asJson([
                'status' => 400,
                'data' => $result,
                'errors' => []
            ]);
        } else {
            return $this->asJson([
                'status' => 200,
                'data' => $result,
                'errors' => []
            ]);
        }


    }

    public function actionDelete()
    {
        try {
            $propertyName = Yii::$app->request->get('model');
            $property = self::getPropertyByName($propertyName);
        } catch (ErrorException $e) {
            return $this->asJson([
                'status' => 400,
                'errors' => $e->getMessage()
            ]);
        }

        $condition = Yii::$app->request->get('condition');
        if (!isset($condition)) {
            $modelKeys = (new $property['class']())::primaryKey();
            foreach ($modelKeys as $modelKey) {
                $primaryKey = Yii::$app->request->get($modelKey);
                if (empty($primaryKey)) {
                    break;
                }
                $condition[$modelKey] = Yii::$app->request->get($modelKey);
            }
        }

        try {
            $countDeletedModels = $this->deleteModels($property, $condition);
        } catch (\Exception $e) {
            return $this->asJson([
                'status' => 400,
                'errors' => $e->getMessage()
            ]);
        }


        return $this->asJson([
            'status' => 400,
            'data' => [
                'countDeletedModels' => $countDeletedModels
            ],
            'errors' => [],
        ]);
    }

    public static function properties()
    {
        return [
            'product' => [
                'class' => 'app\models\Product',
                'relations' => [
                    //'productColor',
                    'productSize',
                    'productColors',
                ],
                'customFields' => [
                    'hash' => function($model) {
                        return $model->hash . '?';
                    }
                ],
                'viewChangeFields' => [
                    'hash' => function($model) {
                        return 'hello ' . $model->hash;
                    }
                ]
            ],
            'productSize' => [
                'class' => 'app\models\Size',
                'relationFields' => ['product_id', 'name'],
                'relationName' => 'size',
                'delaySave' => false,
                'relations' => [],
            ],
            'productColors' => [
                'class' => 'app\models\Color',
                'relationName' => 'colors',
                'viaTable' => true,
                'strongRelation' => true,
                'relationFields' => ['product_id', 'color_id'],
            ],
            'productColor' => [
                'class' => 'app\models\Color',
                'relationFields' => ['color_id', 'color_id'],
                'delaySave' => false,
                'multiple' => true,
                'relations' => [],
            ],
            'color' => [
                'class' => 'app\models\Color',
                'relations' => [
                    'colorProduct'
                ],
            ],
            'colorProduct' => [
                'class' => 'app\models\Product',
                'relationFields' => ['color_hash', 'hash'],
                'delaySave' => true,
            ]
        ];
    }

    public function getPropertyClassByName($propertyName)
    {
        if(isset(self::properties()[$propertyName])) {
            return self::properties()[$propertyName]['class'];
        }

        throw new ErrorException('Wrong model name');
    }

    public function getPropertyByName($propertyName)
    {
        if(isset(self::properties()[$propertyName])) {
            return self::properties()[$propertyName];
        }

        throw new ErrorException('Wrong model name');
    }

    public function saveModel($model, $property, $modelName, $info, $isUpdate)
    {
        $connection = \Yii::$app->db;
        $transaction = $connection->beginTransaction();

        try {
            $resultModel = $this->saveModelInfo($model, $modelName, $property, $info, $isUpdate);
        } catch (\ErrorException $e) {
            $transaction->rollBack();
            return $this->asJson([
                'status' => 400,
                'errors' => $e->getMessage()
            ]);
        }


        if (empty($resultModel->errors)) {
            $status = 200;
            $transaction->commit();
        } else {
            $status = 400;
            $transaction->rollBack();
        }
        return $this->asJson([
            'status' => $status,
            'data' => $this->combineApiFields($resultModel),
            'errors' => $resultModel->errors
        ]);

    }

    public function saveRelation($mainModel, $propertyName, $relationProperty, $info, $isUpdate)
    {
        if ((isset($relationProperty['multiple']) && $relationProperty['multiple']) ||
            (isset($relationProperty['viaTable']) && $relationProperty['viaTable'])) {
            foreach ($info[$propertyName] as $singleInfo) {
                $relationModel = $this->getRelationModel($propertyName, $isUpdate, $singleInfo);

                if (!empty($relationProperty['viaTable'])) {
                    $resultModel = $this->saveModelInfo($relationModel, $propertyName, $relationProperty, $singleInfo, $isUpdate);

                    if (empty($resultModel->errors)) {
                        continue;
                    } else {
                        return $resultModel;
                    }
                }

                if (!$relationProperty['delaySave']) {
                    $resultModel = $this->saveModelInfo($relationModel, $propertyName, $relationProperty, $singleInfo, $isUpdate);

                    if (empty($resultModel->errors)) {
                        $mainModel->{$relationProperty['relationFields'][0]} = $resultModel->{$relationProperty['relationFields'][1]};
                    } else {
                        return $resultModel;
                    }
                } else {
                    $relationModel->{$relationProperty['relationFields'][1]} = $mainModel->{$relationProperty['relationFields'][0]};
                    $resultModel = $this->saveModelInfo($relationModel, $propertyName, $relationProperty, $singleInfo, $isUpdate);
                }
            }
        } else {
            $relationModel = $this->getRelationModel($propertyName, $isUpdate, $info);

            if (!$relationProperty['delaySave']) {
                $resultModel = $this->saveModelInfo($relationModel, $propertyName, $relationProperty, $info, $isUpdate);

                if (empty($resultModel->errors)) {
                    $mainModel->{$relationProperty['relationFields'][0]} = $resultModel->{$relationProperty['relationFields'][1]};
                }
            } else {
                $relationModel->{$relationProperty['relationFields'][1]} = $mainModel->{$relationProperty['relationFields'][0]};
                $resultModel = $this->saveModelInfo($relationModel, $propertyName, $relationProperty, $info, $isUpdate);
            }
        }



        return $resultModel;
    }

    public function saveModelInfo($model, $propertyName, $property, $info, $isUpdate)
    {
        try {
            $model->load($info, $propertyName);
        } catch (\Error $e) {
            throw new \ErrorException('Error while save ' . $propertyName);
        }


        if (!empty($property['customFields'])) {
            foreach ($property['customFields'] as $name => $value) {
                $model->$name = $value($model);
            }
        }


        if (!empty($property['relations'])) {
            $relationsResult = $this->saveRelations($model, $property, $info, $isUpdate);
            if (!is_bool($relationsResult)) {
                return $relationsResult;
            }
        }

        $model->save();

        if (!empty($property['relations'])) {
            $relationsResult = $this->saveDelayRelations($model, $property, $info, $isUpdate);
            if (!is_bool($relationsResult)) {
                return $relationsResult;
            }
        }

        return $model;
    }

    public function getRelatonProperty($relationModelName)
    {
        try {
            $property = self::getPropertyByName($relationModelName);
        } catch (ErrorException $e) {
            return false;
        }

        return $property;
    }

    public function getRelationModel($relationProperty, $isUpdate, $info)
    {
        try {
            $class = $this->getPropertyClassByName($relationProperty);
            if ($isUpdate) {
                try {
                    $modelKeys = (new $class())::primaryKey();
                    foreach ($modelKeys as $modelKey) {
                        $condition[$modelKey] = $info[$modelKey];
                    }
                    $model = $class::findOne($condition);
                    if (empty($model)) {
                        return new $class();
                    }
                } catch (\ErrorException $e) {
                    return new $class();
                }
            } else {
                $model = new $class();
            }
        } catch (ErrorException $e) {
            return false;
        }

        return $model;
    }

    public function saveRelations($model, &$property, $info, $isUpdate)
    {
        foreach ($property['relations'] as $key => $relation) {

            if (empty($info[$relation])) {
                continue;
            }
            $relationProperty = $this->getRelatonProperty($relation);

            if (!$relationProperty) {
                throw new \ErrorException('General Error while saving relation ' . $relation);
            }

            if (!empty($relationProperty['viaTable'])) {
                continue;
            }

            if (!$relationProperty['delaySave']) {
                $relationModelResult = $this->saveRelation($model, $relation, $relationProperty, $info, $isUpdate);
                if (!empty($relationModelResult->errors)) {
                    return $relationModelResult;
                } else {
                    unset($property['relations'][$key]);
                }
            }
        }

        return true;
    }

    public function saveDelayRelations($model, $property, $info)
    {
        foreach ($property['relations'] as $relationKey => $relation) {

            $relationProperty = $this->getRelatonProperty($relation);

            if (!$relationProperty) {
                throw new \ErrorException('General Error while saving relation ' . $relation);
            }

            if (!empty($relationProperty['viaTable'])) {
                $model->unlinkAll($relationProperty['relationName'], $relationProperty['strongRelation']);
                foreach ($info[$relation] as $relationItem) {
                    foreach ($relationItem as $fieldName => $value) {

                        if (empty($relationProperty['relationName'])) {
                            throw new \ErrorException('relationName not set in ' . $relation);
                        }
                        $relatedModel = $relationProperty['class']::findOne([$fieldName => $value]);
                        $model->link($relationProperty['relationName'], $relatedModel);
                        $model->refresh();
                    }
                }

                break;
            }

            if (!empty($relationProperty['delaySave'])) {
                $relationModelResult = $this->saveRelation($model, $relation, $relationProperty, $info);
                if (!empty($relationModelResult->errors)) {
                    return $relationModelResult;
                }
            }
        }

        return true;
    }

    protected function combineApiFields($object, $propertyName = '')
    {
        if ($propertyName) {
            $class = $this->getPropertyClassByName($propertyName);
            $propertyClass = isset($class)
                ? $class
                : '';


            if ($propertyClass && $object instanceof $propertyClass) {
                return $this->combineFields($propertyName, $object);
            }
        } else {
            foreach ($this->getCombinePropertyClasses() as $allowableClass) {
                if ($object instanceof $allowableClass) {
                    return $this->combineFields($this->getCombinePropertyByClassName($allowableClass), $object);
                }
            }
        }

        return [];
    }

    private function combineFields($propertyName, $object)
    {
        $fieldsArray = [];
        $propertyList = $this->getPropertyByName($propertyName);
        $dropArray = isset($propertyList['dropFields']) ? $propertyList['dropFields'] : [];

        //at start fill with main objects attributes
        foreach ($object->oldAttributes as $name => $value) {
            if (!in_array($name, $dropArray)) {
                $fieldsArray[$name] = $value;
            }
        }

        //fill custom attributes execute function in value
        if (isset($propertyList['viewChangeFields'])) {
            foreach ($propertyList['viewChangeFields'] as $name => $value) {
                $fieldsArray[$name] = $value($object);
            }
        }

        //fill relation attributes
        if (isset($propertyList['relations'])) {
            foreach ($propertyList['relations'] as $relation) {
                try {
                    $property = self::getPropertyByName($relation);
                } catch (ErrorException $e) {
                    return $this->asJson([
                        'status' => 400,
                        'errors' => $e->getMessage()
                    ]);
                }

                if (empty($object->{$property['relationName']})) {
                    $fieldsArray[$relation] = [];
                    continue;
                }
                if (!empty($property['multiple']) || !empty($property['viaTable'])) {
                    foreach ($object->{$property['relationName']} as $multiple) {
                        $fieldsArray[$relation][] = $this->combineFields($relation, $multiple);
                    }
                } else {
                    $fieldsArray[$relation] = $this->combineFields($relation, $object->{$property['relationName']});

                }
            }
        }

        return $fieldsArray;
    }

    private function getCombinePropertyClasses()
    {
        $resultClasses = [];

        foreach (self::properties() as $property) {
            $resultClasses[] = $property['class'];
        }

        return $resultClasses;
    }

    private function getCombinePropertyByClassName($className)
    {
        foreach (self::properties() as $name => $property) {
            if ($property['class'] === $className) {
                return $name;
            }
        }
    }

    public function deleteModels($property, $condition)
    {
        $sql = $property['class']::find();
        foreach ($condition as $singleCondition) {
            $sql->andWhere($singleCondition);
        }

        $deleteModels = $sql->all();

        if (empty($deleteModels)) {
            return 0;
        }
        $countDeleteModels = 0;
        foreach ($deleteModels as $deleteModel) {
            try {
                $deleteModel->delete();
                $countDeleteModels++;
            } catch (\ErrorException $e) {
                throw new \Exception($e->getMessage());
            }

        }

        return $countDeleteModels;
    }
}