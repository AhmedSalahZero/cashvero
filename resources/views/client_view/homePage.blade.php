@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .kt-list-timeline__items {
        margin-bottom: 2rem !important;
        width: 100%;
    }

    .kt-iconbox .kt-iconbox__body .kt-iconbox__desc {
        flex: 1;
    }

    .accordion .card .card-header .card-title {
        font-size: 1.5rem !important;
        font-weight: 500;
        color: black !important;

    }

    .subtitle-card-header {
        font-size: 1.25rem !important;
        color: #5578eb !important;
    }

    .with-padding {
        padding-left: 60px !important;
    }

    .repeater_item {
        border: dotted 1px #ccc;
        padding: 10px;
        margin: 10px;
        position: relative;
    }

    .repeater_item .trash_icon {
        position: absolute;
        top: 0px;
        right: 0px;
        cursor: pointer;
    }

    #add-row {
        background: #084BA6;
        border: #084BA6;
        cursor: pointer;
    }  
	.add-row {
        background: #084BA6;
        border: #084BA6;
        cursor: pointer;
    }

    .disabled-custom {
        background-color: #ececec !important;
    }

    html body .kt-list-timeline__items .kt-portlet__body .card div.card-title:not(.collapsed) {
        background-color: #046187 !important;
        color: white !important;
    }

    .card-title span {
        font-size: 22px !important;
    }

    .card-title.collapsed span {
        color: #366cf3 !important;
    }

    .card-title.collapsed i,
    .card-title.collapsed::after {
        color: #366cf3 !important
    }

    .card-title:not(.collapsed) i,
    .card-title:not(.collapsed)::after,
    .card-title:not(.collapsed) span {
        color: white !important;
    }

    table {
        white-space: nowrap;
    }

    .money-flow-dark .kt-iconbox__title {
        color: var(--text-primary) !important;
    }

    .money-flow-dark .kt-iconbox__title svg path {
        fill: var(--teal) !important;
    }

    .money-flow-dark .accordion .card {
        background: var(--bg-card-hover) !important;
        border: 1px solid var(--border) !important;
        margin-bottom: 0.5rem;
    }

    .money-flow-dark .accordion .card .card-header .card-title {
        color: var(--text-primary) !important;
    }

    .money-flow-dark .accordion .card .card-header .card-title.collapsed,
    .money-flow-dark .card-title.collapsed span,
    .money-flow-dark .card-title.collapsed i,
    .money-flow-dark .card-title.collapsed::after {
        color: var(--teal) !important;
    }

    .money-flow-dark .card-body {
        background: var(--bg-card) !important;
        color: var(--text-primary) !important;
    }

    .money-flow-dark .subtitle-card-header {
        color: var(--gold) !important;
    }

    .money-flow-dark .kt-list-timeline__text h4 {
        color: var(--text-primary) !important;
    }

    .money-flow-dark .btn-outline-info {
        color: var(--teal) !important;
        border-color: var(--teal) !important;
    }

    .money-flow-dark .btn-outline-info:hover {
        background: var(--teal-subtle) !important;
        color: #fff !important;
    }

</style>
@endsection
@section('sub-header')
<h1 class="kt-infobox__title" style="color: white">{{__("WELCOME TO  ".$company->name['en']." COMPANY") }}</h1>
<div class="kt-infobox__content" style="color: white">
    {{__("IT IS NOT ABOUT NUMBERS, IT IS ABOUT THE STORY BEHIND THE NUMBERS")}}
</div>
@endsection

@section('content')
<div class="money-flow-dark">
<div class="row" id="first_card">

    <div class="kt-portlet kt-iconbox kt-iconbox--animate">
        <div class="kt-portlet__body">
            <div class="kt-iconbox__body">
                <div class="kt-iconbox__desc">
                    <h3 class="kt-iconbox__title"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1" class="kt-svg-icon">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect id="bound" x="0" y="0" width="24" height="24"></rect>
                                <path d="M2.56066017,10.6819805 L4.68198052,8.56066017 C5.26776695,7.97487373 6.21751442,7.97487373 6.80330086,8.56066017 L8.9246212,10.6819805 C9.51040764,11.267767 9.51040764,12.2175144 8.9246212,12.8033009 L6.80330086,14.9246212 C6.21751442,15.5104076 5.26776695,15.5104076 4.68198052,14.9246212 L2.56066017,12.8033009 C1.97487373,12.2175144 1.97487373,11.267767 2.56066017,10.6819805 Z M14.5606602,10.6819805 L16.6819805,8.56066017 C17.267767,7.97487373 18.2175144,7.97487373 18.8033009,8.56066017 L20.9246212,10.6819805 C21.5104076,11.267767 21.5104076,12.2175144 20.9246212,12.8033009 L18.8033009,14.9246212 C18.2175144,15.5104076 17.267767,15.5104076 16.6819805,14.9246212 L14.5606602,12.8033009 C13.9748737,12.2175144 13.9748737,11.267767 14.5606602,10.6819805 Z" id="Combined-Shape" fill="#000000" opacity="0.3"></path>
                                <path d="M8.56066017,16.6819805 L10.6819805,14.5606602 C11.267767,13.9748737 12.2175144,13.9748737 12.8033009,14.5606602 L14.9246212,16.6819805 C15.5104076,17.267767 15.5104076,18.2175144 14.9246212,18.8033009 L12.8033009,20.9246212 C12.2175144,21.5104076 11.267767,21.5104076 10.6819805,20.9246212 L8.56066017,18.8033009 C7.97487373,18.2175144 7.97487373,17.267767 8.56066017,16.6819805 Z M8.56066017,4.68198052 L10.6819805,2.56066017 C11.267767,1.97487373 12.2175144,1.97487373 12.8033009,2.56066017 L14.9246212,4.68198052 C15.5104076,5.26776695 15.5104076,6.21751442 14.9246212,6.80330086 L12.8033009,8.9246212 C12.2175144,9.51040764 11.267767,9.51040764 10.6819805,8.9246212 L8.56066017,6.80330086 C7.97487373,6.21751442 7.97487373,5.26776695 8.56066017,4.68198052 Z" id="Combined-Shape" fill="#000000"></path>
                            </g>
                        </svg> {{ __('Where You Want To Go :') }}
                    </h3>
                    <br><br>
                    <div class="kt-iconbox__content d-flex align-items-start flex-column">
                        <div class="kt-list-timeline__items">



                            <div class="kt-portlet__body">
                                <div class="kt-list-timeline">
                                    <div class="accordion  accordion-toggle-arrow" id="veroanalysisId">
                                       




                                        @if($company->hasCashvero())
                                        <div class="card">
                                            <div class="card-header" id="cashveroSection">
                                                <div class="card-title collapsed" data-toggle="collapse" data-target="#collapseCashveroSection" aria-expanded="true" aria-controls="collapseOne4">
                                                    <i class="flaticon2-layers-1"></i> {{ __('Cash Vero') }}
                                                </div>
                                            </div>
                                            <div id="collapseCashveroSection" class="collapse" aria-labelledby="headingOne" data-parent="#cashveroSection">
                                                <div class="card-body with-padding">
                                                    {{-- @can(uploadCustomerInvoiceData) --}}
                                                    <x-quick-nav :link="route('view.uploading', ['company'=>$company->id , 'model'=>'CustomerInvoice'])">{{ __('Upload Customer Invoices') }}</x-quick-nav>
                                                    {{-- @endcan --}}
                                                    {{-- @can(uploadSupplierInvoiceData) --}}
                                                    <x-quick-nav :link="route('view.uploading', ['company'=>$company->id , 'model'=>'SupplierInvoice'])">{{ __('Upload Supplier Invoices') }}</x-quick-nav>
                                                    {{-- @endcan --}}
                                                    {{-- @can('view cash status dashboard') --}}
                                                    <x-quick-nav :link="route('view.customer.invoice.dashboard.cash', ['company'=>$company->id ])">{{ __('Go To Cash Vero') }}</x-quick-nav>
                                                    {{-- @endcan --}}

                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                       


                                      
										


										
										


                                        @if(Auth()->user()->id ==1)
                                        <div class="card">
                                            <div class="card-header" id="loanCalculatorId">
                                                <div class="card-title collapsed" data-toggle="collapse" data-target="#collapseLoanCalculatorId" aria-expanded="false" aria-controls="collapseThree4">
                                                    <i class="flaticon2-bell-alarm-symbol"></i> {{ __("Loan Calculator") }}
                                                </div>
                                            </div>
											
                                            <div id="collapseLoanCalculatorId" class="collapse" aria-labelledby="headingThree1" data-parent="#loanCalculatorId">
                                                <div class="card-body">
                                                    <div class="card-body with-padding">
                                                        <x-quick-nav :link="route('fixed.loan.fixed.at.end',$company->getIdentifier())">{{ __('Fixed Payments At The End') }}</x-quick-nav>
                                                        <x-quick-nav :link="route('fixed.loan.fixed.at.beginning',$company->getIdentifier())">{{ __('Fixed Payments At The Begining') }}</x-quick-nav>
                                                        <x-quick-nav :link="route('calc.loan.amount',$company->getIdentifier())">{{ __('Calculate Loan Amount') }}</x-quick-nav>
                                                        <x-quick-nav :link="route('calc.interest.percentage',$company->getIdentifier())">{{ __('Calculate Interest Percentage') }}</x-quick-nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>


                                        <div class="card">
                                            <div class="card-header" id="collapseLoanCalculatorIdphp">
                                                <div class="card-title collapsed" data-toggle="collapse" data-target="#collapseLoanCalculatorIdphp" aria-expanded="false" aria-controls="collapseThree4">
                                                    <i class="flaticon2-bell-alarm-symbol"></i> {{ __("Loan Calculator[PHP]") }}
                                                </div>
                                            </div>
                                            <div id="collapseLoanCalculatorIdphp" class="collapse" aria-labelledby="headingThree1" data-parent="#collapseLoanCalculatorIdphp">
                                                <div class="card-body">
                                                    <div class="card-body with-padding">
                                                        <x-quick-nav :link="route('fixed.loan.fixed.at.end.and.beginning',$company->getIdentifier())">{{ __('Fixed Payments At The End / Beginning Loan Calculator') }}</x-quick-nav>
                                                        <x-quick-nav :link="route('variable.loan',$company->getIdentifier())">{{ __('Variable Payment Loan Calculator') }}</x-quick-nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif

                                    </div>

                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>
    <div class="row" style="display: none" id="second_card">

        <div class="kt-portlet kt-iconbox kt-iconbox--animate">
            <div class="kt-portlet__body">
                <div class="kt-iconbox__body">
                    <div class="kt-iconbox__desc">
                        <h3 class="kt-iconbox__title"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1" class="kt-svg-icon">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect id="bound" x="0" y="0" width="24" height="24"></rect>
                                    <path d="M2.56066017,10.6819805 L4.68198052,8.56066017 C5.26776695,7.97487373 6.21751442,7.97487373 6.80330086,8.56066017 L8.9246212,10.6819805 C9.51040764,11.267767 9.51040764,12.2175144 8.9246212,12.8033009 L6.80330086,14.9246212 C6.21751442,15.5104076 5.26776695,15.5104076 4.68198052,14.9246212 L2.56066017,12.8033009 C1.97487373,12.2175144 1.97487373,11.267767 2.56066017,10.6819805 Z M14.5606602,10.6819805 L16.6819805,8.56066017 C17.267767,7.97487373 18.2175144,7.97487373 18.8033009,8.56066017 L20.9246212,10.6819805 C21.5104076,11.267767 21.5104076,12.2175144 20.9246212,12.8033009 L18.8033009,14.9246212 C18.2175144,15.5104076 17.267767,15.5104076 16.6819805,14.9246212 L14.5606602,12.8033009 C13.9748737,12.2175144 13.9748737,11.267767 14.5606602,10.6819805 Z" id="Combined-Shape" fill="#000000" opacity="0.3"></path>
                                    <path d="M8.56066017,16.6819805 L10.6819805,14.5606602 C11.267767,13.9748737 12.2175144,13.9748737 12.8033009,14.5606602 L14.9246212,16.6819805 C15.5104076,17.267767 15.5104076,18.2175144 14.9246212,18.8033009 L12.8033009,20.9246212 C12.2175144,21.5104076 11.267767,21.5104076 10.6819805,20.9246212 L8.56066017,18.8033009 C7.97487373,18.2175144 7.97487373,17.267767 8.56066017,16.6819805 Z M8.56066017,4.68198052 L10.6819805,2.56066017 C11.267767,1.97487373 12.2175144,1.97487373 12.8033009,2.56066017 L14.9246212,4.68198052 C15.5104076,5.26776695 15.5104076,6.21751442 14.9246212,6.80330086 L12.8033009,8.9246212 C12.2175144,9.51040764 11.267767,9.51040764 10.6819805,8.9246212 L8.56066017,6.80330086 C7.97487373,6.21751442 7.97487373,5.26776695 8.56066017,4.68198052 Z" id="Combined-Shape" fill="#000000"></path>
                                </g>
                            </svg>
                            Please choose where do you want to go?
                        </h3>
                        <br><br>
                        <div class="kt-iconbox__content d-flex align-items-start flex-column">

                            <div class="kt-portlet__body">
                                <div class="kt-list-timeline">
                                    <div class="kt-list-timeline__items">

                                        <div class="kt-list-timeline__item">
                                            <span class="kt-list-timeline__badge kt-list-timeline__badge--brand"></span>
                                            <span class="kt-list-timeline__text">
                                                <h4> {{ __("Sales Dashboard") }} </h4>
                                            </span>
                                            <span class="kt-list-timeline__time"><a href="{{ route('dashboard', $company) }}" class="btn btn-label-info btn-pill"> <b>Go</b></a></span>
                                        </div>
                                    </div>
                                    <br>

                                </div>
                            </div>


                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>






    {{-- <div class="row" id="first_card">

    <div class="kt-portlet kt-iconbox kt-iconbox--animate">
        <div class="kt-portlet__body">
            <div class="kt-iconbox__body">
                <div class="kt-iconbox__desc">
                    <h3 class="kt-iconbox__title"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1" class="kt-svg-icon">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect id="bound" x="0" y="0" width="24" height="24"></rect>
                                <path d="M2.56066017,10.6819805 L4.68198052,8.56066017 C5.26776695,7.97487373 6.21751442,7.97487373 6.80330086,8.56066017 L8.9246212,10.6819805 C9.51040764,11.267767 9.51040764,12.2175144 8.9246212,12.8033009 L6.80330086,14.9246212 C6.21751442,15.5104076 5.26776695,15.5104076 4.68198052,14.9246212 L2.56066017,12.8033009 C1.97487373,12.2175144 1.97487373,11.267767 2.56066017,10.6819805 Z M14.5606602,10.6819805 L16.6819805,8.56066017 C17.267767,7.97487373 18.2175144,7.97487373 18.8033009,8.56066017 L20.9246212,10.6819805 C21.5104076,11.267767 21.5104076,12.2175144 20.9246212,12.8033009 L18.8033009,14.9246212 C18.2175144,15.5104076 17.267767,15.5104076 16.6819805,14.9246212 L14.5606602,12.8033009 C13.9748737,12.2175144 13.9748737,11.267767 14.5606602,10.6819805 Z" id="Combined-Shape" fill="#000000" opacity="0.3"></path>
                                <path d="M8.56066017,16.6819805 L10.6819805,14.5606602 C11.267767,13.9748737 12.2175144,13.9748737 12.8033009,14.5606602 L14.9246212,16.6819805 C15.5104076,17.267767 15.5104076,18.2175144 14.9246212,18.8033009 L12.8033009,20.9246212 C12.2175144,21.5104076 11.267767,21.5104076 10.6819805,20.9246212 L8.56066017,18.8033009 C7.97487373,18.2175144 7.97487373,17.267767 8.56066017,16.6819805 Z M8.56066017,4.68198052 L10.6819805,2.56066017 C11.267767,1.97487373 12.2175144,1.97487373 12.8033009,2.56066017 L14.9246212,4.68198052 C15.5104076,5.26776695 15.5104076,6.21751442 14.9246212,6.80330086 L12.8033009,8.9246212 C12.2175144,9.51040764 11.267767,9.51040764 10.6819805,8.9246212 L8.56066017,6.80330086 C7.97487373,6.21751442 7.97487373,5.26776695 8.56066017,4.68198052 Z" id="Combined-Shape" fill="#000000"></path>
                            </g>
                        </svg> {{ __('Where do you want to go ?') }}
    </h3>
    <br><br>
    <div class="kt-iconbox__content d-flex align-items-start flex-column">

        <div class="kt-portlet__body">
            <div class="kt-list-timeline">
                @foreach(getUploadParamsFromType() as $elementModelName => $params )
                @if(in_array($elementModelName,['ExportAnalysis','ExpenseAnalysis','LabelingItem','CustomerInvoice','SupplierInvoice','LoanSchedule']))
                @continue
                @endif

                @can($params['viewPermissionName'])
                <div class="kt-list-timeline__items">

                    <div class="kt-list-timeline__item ">
                        <span class="kt-list-timeline__badge kt-list-timeline__badge--brand"></span>
                        <span class="kt-list-timeline__text">
                            <h4> {{ getUploadDataText($params['typePrefixName']) }} </h4>
                        </span>
                        <span class="kt-list-timeline__time "> <a href="{{route('view.uploading',['company'=>$company->id , 'model'=>$elementModelName])}}" class="btn btn-outline-info"> <b>{{ __('Go') }}</b></a></span>
                    </div>
                </div>
                @endcan
                <br>
                @endforeach




                @can('view income statement planning')



                <div class="kt-list-timeline__items">
                    <div class="kt-list-timeline__item">
                        <span class="kt-list-timeline__badge kt-list-timeline__badge--brand"></span>
                        <span class="kt-list-timeline__text">
                            <h4> {{ __("Income Statement Planning / Actual") }} </h4>
                        </span>

                        <span class="kt-list-timeline__time disable"> <a href="{{ route('admin.view.financial.statement',['company'=>$company->id]) }}" class="btn btn-outline-info"><b>{{ __('GO') }}</b></a></span>
                    </div>
                </div>
                <br>

                @endcan

                @if($company->hasCashVero())


                <div class="kt-list-timeline__items">
                    <div class="kt-list-timeline__item">
                        <span class="kt-list-timeline__badge kt-list-timeline__badge--brand"></span>
                        <span class="kt-list-timeline__text">
                            <h4> {{ __("View Cash Management") }} </h4>
                        </span>

                        <span class="kt-list-timeline__time disable"> <a href="{{ route('view.financial.institutions',['company'=>$company->id]) }}" class="btn btn-outline-info"><b>{{ __('GO') }}</b></a></span>
                    </div>
                </div>
                @endif


                <br>


            </div>
        </div>

    </div>
</div>
</div>
@if(auth()->user() && auth()->user()->hasAccessToSystems([VERO]))
<div class="kt-widget6__action kt-align-left">
    <a href="#" onclick="return false;" id="skip" class="btn btn-outline-info"><b>{{ __('Go To Sales Analysis') }}</b></a>
</div>
@endif
</div>
</div>

</div> --}}

{{-- <div class="row" style="display: none" id="second_card">

    <div class="kt-portlet kt-iconbox kt-iconbox--animate">
        <div class="kt-portlet__body">
            <div class="kt-iconbox__body">
                <div class="kt-iconbox__desc">
                    <h3 class="kt-iconbox__title"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1" class="kt-svg-icon">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect id="bound" x="0" y="0" width="24" height="24"></rect>
                                <path d="M2.56066017,10.6819805 L4.68198052,8.56066017 C5.26776695,7.97487373 6.21751442,7.97487373 6.80330086,8.56066017 L8.9246212,10.6819805 C9.51040764,11.267767 9.51040764,12.2175144 8.9246212,12.8033009 L6.80330086,14.9246212 C6.21751442,15.5104076 5.26776695,15.5104076 4.68198052,14.9246212 L2.56066017,12.8033009 C1.97487373,12.2175144 1.97487373,11.267767 2.56066017,10.6819805 Z M14.5606602,10.6819805 L16.6819805,8.56066017 C17.267767,7.97487373 18.2175144,7.97487373 18.8033009,8.56066017 L20.9246212,10.6819805 C21.5104076,11.267767 21.5104076,12.2175144 20.9246212,12.8033009 L18.8033009,14.9246212 C18.2175144,15.5104076 17.267767,15.5104076 16.6819805,14.9246212 L14.5606602,12.8033009 C13.9748737,12.2175144 13.9748737,11.267767 14.5606602,10.6819805 Z" id="Combined-Shape" fill="#000000" opacity="0.3"></path>
                                <path d="M8.56066017,16.6819805 L10.6819805,14.5606602 C11.267767,13.9748737 12.2175144,13.9748737 12.8033009,14.5606602 L14.9246212,16.6819805 C15.5104076,17.267767 15.5104076,18.2175144 14.9246212,18.8033009 L12.8033009,20.9246212 C12.2175144,21.5104076 11.267767,21.5104076 10.6819805,20.9246212 L8.56066017,18.8033009 C7.97487373,18.2175144 7.97487373,17.267767 8.56066017,16.6819805 Z M8.56066017,4.68198052 L10.6819805,2.56066017 C11.267767,1.97487373 12.2175144,1.97487373 12.8033009,2.56066017 L14.9246212,4.68198052 C15.5104076,5.26776695 15.5104076,6.21751442 14.9246212,6.80330086 L12.8033009,8.9246212 C12.2175144,9.51040764 11.267767,9.51040764 10.6819805,8.9246212 L8.56066017,6.80330086 C7.97487373,6.21751442 7.97487373,5.26776695 8.56066017,4.68198052 Z" id="Combined-Shape" fill="#000000"></path>
                            </g>
                        </svg>
                        {{__('Please choose where do you want to go?')}}


</h3>
<br><br>

<a class="btn btn-success" href="{{ route('fixed.loan.fixed.at.end.php',['company'=>$company->id]) }}">loan22222222222</a>


<div class="kt-iconbox__content d-flex align-items-start flex-column">


    <div class="kt-portlet__body">
        <div class="kt-list-timeline">

            @can('view sales dashboard')
            <div class="kt-list-timeline__items">
                <div class="kt-list-timeline__item">
                    <span class="kt-list-timeline__badge kt-list-timeline__badge--brand"></span>
                    <span class="kt-list-timeline__text">
                        <h4> {{ __("Sales Dashboard") }} </h4>
                    </span>
                    <span class="kt-list-timeline__time"><a href="{{ route('dashboard', $company) }}" class="btn btn-label-info btn-pill"> <b>Go</b></a></span>
                </div>
            </div>
            <br>
            @endcan
            @can('view sales breakdown analysis report')
            <div class="kt-list-timeline__items">

                <div class="kt-list-timeline__item">
                    <span class="kt-list-timeline__badge kt-list-timeline__badge--brand"></span>
                    <span class="kt-list-timeline__text">
                        <h4> {{ __("Sales Breakdown Analysis") }} </h4>
                    </span>
                    <span class="kt-list-timeline__time"> <a href="{{route('sales.breakdown.analysis',$company)}}" class="btn btn-label-info btn-pill"><b>Go</b></a></span>
                </div>
            </div>
            <br>
            @endcan
            @can('view sales trend analysis')

            <div class="kt-list-timeline__items">

                <div class="kt-list-timeline__item">
                    <span class="kt-list-timeline__badge kt-list-timeline__badge--brand"></span>
                    <span class="kt-list-timeline__text">
                        <h4> {{ __("Sales Trend Analysis") }} </h4>
                    </span>
                    <span class="kt-list-timeline__time"> <a href="{{route('sales.trend.analysis',$company)}}" class="btn btn-label-info btn-pill"><b>Go</b></a></span>
                </div>
            </div>
            <br>
            @endcan
            @can('view sales report')
            <div class="kt-list-timeline__items">

                <div class="kt-list-timeline__item">
                    <span class="kt-list-timeline__badge kt-list-timeline__badge--brand"></span>
                    <span class="kt-list-timeline__text">
                        <h4> {{ __("Sales Report") }} </h4>
                    </span>
                    <span class="kt-list-timeline__time"><a href="{{route('salesReport.view',$company)}}" class="btn btn-label-info btn-pill"><b>Go</b></a></span>
                </div>
            </div>
            @endcan
        </div>
    </div>

</div>
</div>

</div>
</div>
</div>

</div> --}}










@endsection
@section('js')
<script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/custom/js/vendors/bootstrap-datepicker.init.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-datepicker.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js') }}" type="text/javascript">
</script>
<script>
    $(function() {
        $('#skip').on('click', function(e) {
            e.preventDefault();
            $('#first_card').fadeOut("slow", function() {
                $('#second_card').fadeIn(500);
            });
        });

    })

</script>
<!-- Resources -->

@endsection
