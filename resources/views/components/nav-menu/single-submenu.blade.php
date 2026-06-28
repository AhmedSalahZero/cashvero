                    @props([
                    'menuArr'
                    ])
					
                    @if($menuArr['show'])
                    <li @foreach(getAllDataKey($menuArr) as $k=>$v)
                        {{ $k.'='.$v }}
                        @endforeach

                        class="kt-menu__item " aria-haspopup="true"><a href="{{ $menuArr['link'] }}" class="kt-menu__link ">
						@if(isset($menuArr['icon']))
						
						<i class="{{ $menuArr['icon'] }}"></i>
						@else 
						<i class="kt-menu__link-bullet kt-menu__link-bullet--dot"><span></span></i>
						@endif 
						<span class="kt-menu__link-text">
						
						{!! $menuArr['title']  !!}
						
					
						
						</span> 	
						
						@if(isset($menuArr['count'] ) )
							@include('red-notification',['count'=>$menuArr['count']])
						@endif 
						</a> </li>
                    @endif
