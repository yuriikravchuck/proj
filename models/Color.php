<?php

namespace app\models;

use yii\db\ActiveRecord;

class Color extends ActiveRecord
{
    public function rules()
    {
        return [
            [['color_hash'], 'required'],
        ];
    }

    public static function tableName()
    {
        return 'color';
    }
}