<?php
namespace app\models;

use yii\db\ActiveRecord;

class Product extends ActiveRecord
{

    public function rules()
    {
        return [
            //[['hash', 'color_id'], 'required'],
            [['color_id'], 'integer'],
        ];
    }

    public static function tableName()
    {
        return 'product';
    }

    public function attributeLabels()
    {
        return [
            'name' => \Yii::t('app', 'Your name'),
            'hash' => \Yii::t('app', 'Your email address'),
            'code_id' => \Yii::t('app', 'Subject'),
            'size_id' => \Yii::t('app', 'Content'),
            'color_id' => \Yii::t('app', 'Color'),
            'total_count' => \Yii::t('app', 'Subject'),
        ];
    }

    public function getColor() {
        return $this->hasOne(Color::className(), ['color_id' => 'color_id']);
    }

    public function getColors()
    {
        return $this->hasMany(Color::className(), ['color_id' => 'color_id'])
            ->viaTable('product_color', ['product_id' => 'product_id']);
    }
}