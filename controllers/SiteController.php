<?php

namespace app\controllers;

use Yii;
use yii\base\ErrorException;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\db\Query;

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
            $property = self::getPropertyByModelName(Yii::$app->request->get('model'));
        } catch (ErrorException $e) {
            return $this->asJson([
                'status' => 400,
                'errors' => $e->getMessage()
            ]);
        }

        $model = new $property['class']();

        $this->saveModel($model, $property, Yii::$app->request->get('model'), Yii::$app->request->get('info'));
    }

    public static function properties()
    {
        return [
            'product' => [
                'class' => 'app\models\Product',
                'relations' => [
                    //'productColor',
                    //'productSize',
                    'productColors',
                ],
                'customFields' => [
                    'hash' => function($model) {
                        return $model->hash . '.';
                    }
                ]
            ],
            'productSize' => [
                'class' => 'app\models\Size',
                'relationFields' => ['product_id', 'name'],
                'delaySave' => false,
                'relations' => [],
            ],
            'productColors' => [
                'class' => 'app\models\Color',
                'relationName' => 'colors',
                'viaTable' => true,
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

    public function getPropertyByModelName($propertyName)
    {
        if(isset(self::properties()[$propertyName])) {
            return self::properties()[$propertyName];
        }

        throw new ErrorException('Wrong model name');
    }

    public function saveModel($model, $property, $modelName, $info)
    {
        $connection = \Yii::$app->db;
        $transaction = $connection->beginTransaction();

        try {
            $resultModel = $this->saveModelInfo($model, $modelName, $property, $info);
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
            'model' => $resultModel,
            'errors' => $resultModel->errors
        ]);

    }

    public function saveRelation($mainModel, $propertyName, $relationProperty, $info)
    {
        if ((isset($relationProperty['multiple']) && $relationProperty['multiple']) ||
            (isset($relationProperty['viaTable']) && $relationProperty['viaTable'])) {
            foreach ($info as $singleInfo) {
                $relationModel = $this->getRelationModel($propertyName);

                if (!empty($relationProperty['viaTable'])) {
                    $resultModel = $this->saveModelInfo($relationModel, $propertyName, $relationProperty, $singleInfo);

                    if (empty($resultModel->errors)) {
                        continue;
                    } else {
                        return $resultModel;
                    }
                }

                if (!$relationProperty['delaySave']) {
                    $resultModel = $this->saveModelInfo($relationModel, $propertyName, $relationProperty, $singleInfo);

                    if (empty($resultModel->errors)) {
                        $mainModel->{$relationProperty['relationFields'][0]} = $resultModel->{$relationProperty['relationFields'][1]};
                    } else {
                        return $resultModel;
                    }
                } else {
                    $relationModel->{$relationProperty['relationFields'][1]} = $mainModel->{$relationProperty['relationFields'][0]};
                    $resultModel = $this->saveModelInfo($relationModel, $propertyName, $relationProperty, $singleInfo);
                }
            }
        } else {
            $relationModel = $this->getRelationModel($propertyName);

            if (!$relationProperty['delaySave']) {
                $resultModel = $this->saveModelInfo($relationModel, $propertyName, $relationProperty, $info);

                if (empty($resultModel->errors)) {
                    $mainModel->{$relationProperty['relationFields'][0]} = $resultModel->{$relationProperty['relationFields'][1]};
                }
            } else {
                $relationModel->{$relationProperty['relationFields'][1]} = $mainModel->{$relationProperty['relationFields'][0]};
                $resultModel = $this->saveModelInfo($relationModel, $propertyName, $relationProperty, $info);
            }
        }



        return $resultModel;
    }

    public function saveModelInfo($model, $propertyName, $property, $info)
    {
        $model->load($info, $propertyName);

        if (!empty($property['customFields'])) {
            foreach ($property['customFields'] as $name => $value) {
                $model->$name = $value($model);
            }
        }


        if (!empty($property['relations'])) {
            $relationsResult = $this->saveRelations($model, $property, $info);
            if (!is_bool($relationsResult)) {
                return $relationsResult;
            }
        }

        $model->save();

        if (!empty($property['relations'])) {
            $relationsResult = $this->saveDelayRelations($model, $property, $info);
            if (!is_bool($relationsResult)) {
                return $relationsResult;
            }
        }

        return $model;
    }

    public function getRelatonProperty($relationModelName)
    {
        try {
            $property = self::getPropertyByModelName($relationModelName);
        } catch (ErrorException $e) {
            return false;
        }

        return $property;
    }

    public function getRelationModel($relationProperty)
    {
        try {
            $class = $this->getPropertyClassByName($relationProperty);
            $model = new $class();
        } catch (ErrorException $e) {
            return false;
        }

        return $model;
    }

    public function saveRelations($model, &$property, $info)
    {
        foreach ($property['relations'] as $key => $relation) {

            $relationProperty = $this->getRelatonProperty($relation);

            if (!$relationProperty) {
                throw new \ErrorException('General Error while saving relation ' . $relation);
            }

            if (!empty($relationProperty['viaTable'])) {
                continue;
            }

            if (!$relationProperty['delaySave']) {
                $relationModelResult = $this->saveRelation($model, $relation, $relationProperty, $info);
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
                foreach ($info as $relationItem) {
                    foreach ($relationItem[$relation] as $fieldName => $value) {

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
}