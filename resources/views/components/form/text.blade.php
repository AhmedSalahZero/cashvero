@props([
'label' , 'name' , 'labelClass'=>'' , 'inputClass'=>''  , 'id'=>'' , 'placeHolder' ,'type'=>'text' ,'required'=>false
,'model'=>false,
'inputAttributes'=>'' ,
'json'=>false ,
'jsonLang',
'json2'=>false ,
'json3'=>false ,
'index'=>-1,
'title'=>'',
'idSuffix'=>'',
'parentClass'=>'',
"defaultValue"=>"",
"withoutLabel"=>'',
'attrName'=>'',
'updateDefaultValue'=>'',
'parentStyle'=>'',
'fullWidth'=>'',
'resetMargin'=>'',
'smallBox'=>''
])
    @if(! $withoutLabel)
    <label title="{{$title}}" class=" fw-bold fs-6 mb-2 {{$labelClass}} " for="{{$id}}">{{$label}}
        @if($required)
    <span style="color:red">*</span>
            @endif
    </label>
    @endif
    <input id="{{isset($idSuffix) && $idSuffix ? $id . $idSuffix : $id }}" title="{{$title}}" {{$attributes}} type="{{$type}}" class="form-control  form-control-solid mb-3 mb-lg-0 {{$inputClass}}"

           @if($json)
           name="{{$name . '[' .  $jsonLang . ']'}}" id="{{$id."-" . $jsonLang}}"
           value="{{old($name .'.'.$jsonLang) ?? ( $model ?  ((array)json_decode(@$model->getRawOriginal($name)))[$jsonLang] : null )  }}"
           @elseif($json2)
           name="{{$name . '[' .  $index . ']'.'[' . $jsonLang . ']'}}" id="{{$id."-" . $index . '-' .  $jsonLang}}"
           value="{{old($name .'.'. $index .'.'.$jsonLang) ?? ( $model ?  ((array)json_decode(@$model->getRawOriginal($name)))[$jsonLang] : null )  }}"
           @elseif($json3)
           name="{{$name . '[' .  $index . ']'}}" id="{{$id."-" . $index }}"
           value="{{old($name .'.'. $index ) ?? ( $model ?  ((@$model->{$name})) : null )  }}"
           @else

           name="{{$name}}" id="{{$id}}"
           value="{{old($name) ?? ( @$model->{$attrName?:$name}) ?: $defaultValue }}"
           @endif
           placeholder="{{$placeHolder??''}}" />
