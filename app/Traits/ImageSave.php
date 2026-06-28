<?php

namespace App\Traits;

trait ImageSave {

    public static function saveIfExist($field_name,$model,$collection = 'default')
    {

        if (request()->hasFile($field_name)) {
            $model->getFirstMedia($collection) !== null ? $model->getFirstMedia($collection)->delete() : '';
                  
            $model->addMediaFromRequest($field_name)->toMediaCollection($collection);
        }
    }
}
