    <ul class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
            @if(config('app.showTrendCharts'))
			    <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#kt_apps_contacts_view_tab_1" role="tab">
                        <i class="flaticon-line-graph"></i> &nbsp; Charts
                    </a>
                </li>
			@endif 
                <li class="nav-item">
                    <a class="nav-link   @if(!config('app.showTrendCharts')) active @endif " data-toggle="tab" href="#kt_apps_contacts_view_tab_2" role="tab">
                        <i class="flaticon2-checking"></i>Reports Table
                    </a>
                </li>



            </ul>
