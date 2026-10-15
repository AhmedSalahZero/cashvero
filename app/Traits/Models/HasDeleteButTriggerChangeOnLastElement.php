<?php 
namespace App\Traits\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

trait HasDeleteButTriggerChangeOnLastElement 
{
	/**
	 * * هنا لما نعوز نحذف اكثر من واحدة .. فا مش هنشغل التريجر غير مع اللي اقل تاريخ فيهم
	 */
	public static function deleteButTriggerChangeOnLastElement(Collection $statements):void
	{
		$length = count($statements);
		$statements->each(function($statement,$index) use ($length){
			/**
			 * * لو هو اخر عنصر اللي هو تاريخ الاصغر ما بينهم .. في الحاله دي هنحذفه بالطريقة اللي بتشغل ال
			 * * observers
			 * * علشان لو عندك خمسين عنصر مثلا هيتحذفوا ما يروحش يترجر مع كل واحد
			 * * انما لما هيترجر مع الاصفر تاريخ منهم فا في الحاله دي هيعمل مرة واحدة بس ترجر ياحدث من اول التاريخ الصغير دا وانت نازل .. و وانت نازل دي
			 * * معناه انه هيحدث العناصر اللي المفروض يحدثها كلها
			 * * وخلي بالك انك مرتب 
			 * * currentAccountBankStatements 
			 * * من الكبير للصغير من حيث ال 
			 * * full_date 
			 * * فا الاخير هيكون هو الاصغر اللي هنبدا نشكل ال
			 * * observer 
			 * * من عندة
			 */
			
		
			if($index == $length-1){
				$statement->delete();
			}elseif(isset($statement->id)){
					DB::table((new self)->getTable())->where('id',$statement->id)->delete();
			}
		});
		
	}
	
}
