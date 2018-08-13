<?php

namespace app\models;

use yii\db\ActiveRecord;

class Size extends ActiveRecord
{
    public function rules()
    {
        return [
            //[['hash', 'color_id'], 'required'],
            [['name'], 'string'],
        ];
    }

    public static function tableName()
    {
        return 'size';
    }
}