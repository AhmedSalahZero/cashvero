<div class="kt-portlet" id="connecting">

        <div class="kt-portlet__head">
            <div class="kt-portlet__head-label">
                <h3 class="kt-portlet__head-title head-title text-primary">
                    {{ __('Allocating With Customer Contracts') }}
                </h3>
            </div>
        </div>
        <div class="kt-portlet__body">


            <div class="form-group row justify-content-center">
                @php
                $index = 0 ;
                @endphp

                {{-- start of fixed monthly repeating amount --}}
                @php
                $tableId = $contractsRelationName;

                $repeaterId = 'm_repeater_7';

                @endphp
                {{-- <input type="hidden" name="tableIds[]" value="{{ $tableId }}"> --}}
                <x-tables.repeater-table :repeater-with-select2="true" :parentClass="'show-class-js'" :tableName="$tableId" :repeaterId="$repeaterId" :relationName="'food'" :isRepeater="$isRepeater=true">
                    <x-slot name="ths">
                        @foreach([
                        __('Customer')=>'col-md-3',
                        __('Contract Name')=>'col-md-3',
                        __('Contract Code')=>'col-md-2',
                        __('Contract Amount')=>'col-md-2 custom-contract-amount-css',
                        __('Allocate Amount')=>'col-md-2 custom-contract-amount-css',
                        ] as $title=>$classes)
                        <x-tables.repeater-table-th class="{{ $classes }}" :title="$title"></x-tables.repeater-table-th>
                        @endforeach
                    </x-slot>
                    <x-slot name="trs">
                        @php
                        $rows = isset($model) ? $model->contracts :[-1] ;

                        @endphp
                        @foreach( count($rows) ? $rows : [-1] as $currentContract)
                        @php
                        $fullPath = new \App\Models\Contract ;
                        if( !($currentContract instanceof $fullPath) ){
                        unset($currentContract);
                        }
                        @endphp
                        <tr @if($isRepeater) data-repeater-item @endif>

                            <td class="text-center">
                                <input type="hidden" name="company_id" value="{{ $company->id }}">
                                <div class="">
                                    <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">
                                    </i>
                                </div>
                            </td>
                            <td>
                                <x-form.select :pleaseSelect="true" :selectedValue="isset($currentContract) && $currentContract->client ? $currentContract->client->id : ''" :options="formatOptionsForSelect($clientsWithContracts)" :add-new="false" class="select2-select suppliers-or-customers-js repeater-select  " data-filter-type="{{ 'create' }}" :all="false" name="{{ $isRepeater ? 'partner_id' : $tableId . '[0][partner_id]' }}"></x-form.select>
                            </td>

                            <td>
                                <x-form.select :pleaseSelect="true" data-current-selected="{{ isset($currentContract) ? $currentContract->id : '' }}" :selectedValue="isset($currentContract) ? $currentContract->id : ''" :options="[]" :add-new="false" class="select2-select  contracts-js repeater-select  " data-filter-type="{{ 'create' }}" :all="false" name="{{ $isRepeater ? 'contract_id' : $tableId . '[0][contract_id]' }}"></x-form.select>
                            </td>

                            <td>
                                <div class="kt-input-icon">
                                    <div class="input-group">
                                        <input disabled type="text" class="form-control contract-code" value="">
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="kt-input-icon ">
                                    <div class="input-group">
                                        <input disabled type="text" class="form-control contract-amount" value="0">
                                    </div>
                                </div>
                            </td>


                            <td>
                                <div class="kt-input-icon ">
                                    <div class="input-group">
                                        <input type="text" name="amount" class="form-control " value="{{ isset($currentContract) ? number_format($currentContract->pivot->amount,2) : 0 }}">
                                    </div>
                                </div>
                            </td>
















                        </tr>
                        @endforeach

                    </x-slot>




                </x-tables.repeater-table>
                {{-- end of fixed monthly repeating amount --}}















































































            </div>
        </div>







    </div>
