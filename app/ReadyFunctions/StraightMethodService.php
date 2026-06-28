<?php
namespace App\ReadyFunctions;


class StraightMethodService {
	
	  public function calculateStraightAmount(float $amount,  int $startDateAsIndex, int $duration)
    {
        $steadyGrowthCount = [];
        if ($duration == 0) {
			
            return [
                $startDateAsIndex => $amount
            ];
        }
        for ($i = 1 ; $i <= $duration ; $i++) {
            $steadyGrowthCount[] = $duration;
        }
        $straightAmount = [];
		 $straightDate = $startDateAsIndex; 
        foreach ($steadyGrowthCount as $steadyGrowthCountElement) {
            $straightAmount[$straightDate] = $amount / $duration;
         	 $straightDate = $straightDate+1;  
        }
        return $straightAmount;
    }
	
	
}
