<script src="{{ url('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/custom/js/vendors/bootstrap-datepicker.init.js') }}" type="text/javascript">
</script>

<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-datepicker.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/lib.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/jquery.input.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/repeater.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/form-repeater.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/general/select2/dist/js/select2.full.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/select2.js') }}" type="text/javascript"></script>
<script src="{{asset('assets/form-repeater.js')}}" type="text/javascript"></script>
{{-- <script  type="text/javascript" src={{ asset('datatable_modify.js') }}></script> --}}

<script>
    function getBaseUrlToCompanyId() {
        return $('body').data('base-url') + '/' + $('body').data('lang') + '/' + $('body').data('current-company-id');
    }
</script>
<script>

    $(document).on('change','select[id*="customer_id"]',function(event){
                        if($(this).val())
                       {
							var val = $('#current_business_sector_id').data('value') ? $('#current_business_sector_id').data('value') : $('#current_business_sector_id').val();
                             updateField(getBaseUrlToCompanyId() +'/helpers/updateBasedOnGlobalController?parentModelId='+$(this).val()
                            +'&parentModelName=Customer&selectedItem='+ val
                            // +'&selectedItem='+ $('#current_tax_id').data('tax-id')
                            +'&append_id=.business-sector-class[data-filter-type="'+ $(this).data('filter-type') + '"]' 
                            + '&isFullQuerySelector=1&add_new_item='+ $(this).data('add-new') +'&select_all='+ $(this).data('all') +'&childRelationName=businessSector&model_id=id&model_value=name')
                       }
                })
                $('select[id*="customer_id"]').trigger('change') ;

   				     $(document).on('change','select[id*="country_id"]',function(event){
                    if($(this).val())
                    {
                         updateField(getBaseUrlToCompanyId() +'/helpers/updateCitiesBasedOnCountry?country_id='+$(this).val()
                        +'&selectedItem='+ $('#current_state_id').data('state-id')
                        // +'&selectedItem='+ $('#current_tax_id').data('tax-id')
                        +'&append_id=state_id'
                        + '&model_id=id&model_value=name_'+ $('body').data('lang'))
                        
                    }
                })
                $('select[id*="country_id"]').trigger('change') ;


                 $(document).on('change','select:visible[class*="revenue_business_line_class"]',function(event){
                    if($(this).val() && $(this).val() != 'Add New')
                    {
                    
                       // if in repeater so the name attribute has numeric [0] 

                       let repeaterFieldIndex = $(this).attr('name').match(/\d+/) ;
                       if(repeaterFieldIndex)
                       {
                           let repeaterGroupName = $(this).closest('.repeater-class').find('[data-repeater-list]').attr('data-repeater-list');
                            prefix = '[name="'+repeaterGroupName+'['+repeaterFieldIndex[0]+'][service_category_id]"]';
                       }
                       else{
                           prefix = '';
                       }

                       if($(this).val())
                       {
							let val = $('#current_service_category_id').data('value') ?$('#current_service_category_id').data('value') : $('#current_service_category_id').val();
						
                            updateField(getBaseUrlToCompanyId() +'/helpers/updateBasedOnGlobalController?parentModelId='+$(this).val()
                            +'&parentModelName=RevenueBusinessLine&selectedItem='+ val
                            // +'&selectedItem='+ $('#current_tax_id').data('tax-id')
                            +'&append_id=.service_category_class[data-filter-type="'+ $(this).data('filter-type') + '"]' + prefix   
                            + '&isFullQuerySelector=1&add_new_item='+ $(this).data('add-new') +'&select_all='+ $(this).data('all') +'&childRelationName=serviceCategories&model_id=id&model_value=name')
                            
                       }
                        
                    }
                })


                  $(document).on('change','select[class*="service_category_class"]',function(event){
                    if($(this).val() && $(this).val() != 'Add New')
                    {
                          let repeaterFieldIndex = $(this).attr('name').match(/\d+/) ;
                       if(repeaterFieldIndex)
                       {
                           let repeaterGroupName = $(this).closest('.repeater-class').find('[data-repeater-list]').attr('data-repeater-list');
                            prefix = '[name="'+repeaterGroupName+'['+repeaterFieldIndex[0]+'][service_item_id]"]';
                       }
                       else{
                           prefix = '';
                       }
					   			var val = $('#current_service_item_id').data('value') ? $('#current_service_item_id').data('value') : $('#current_service_item_id').val();
                         updateField(getBaseUrlToCompanyId() +'/helpers/updateBasedOnGlobalController?parentModelId='+$(this).val()
                        +'&parentModelName=ServiceCategory&selectedItem='+ val
                        // +'&selectedItem='+ $('#current_tax_id').data('tax-id')
                        +'&append_id=.service_item_class[data-filter-type="'+ $(this).data('filter-type') + '"]' + prefix   
                        + '&isFullQuerySelector=1&add_new_item='+ $(this).data('add-new') + '&select_all='+ $(this).data('all') +'&childRelationName=serviceItems&model_id=id&model_value=name')
                    }
                })
                //$('select[class*="revenue_business_line_class"]').trigger('change') ;
                
</script>
 <script>
        function updateField(route, parent = null) {
            $.ajax({
                type: 'GET'
                , url: route
                , data: {
                    "_token": "{{csrf_token()}}"
                , }
                , cache: false
                , contentType: false
                , processData: false
                , success: (res) => {
                    if (res.status) {

                        if (parent && parent.length) {
                            parent.find('#' + res.append_id).empty().append(res.result).trigger('change').trigger('changed.bs.select').selectpicker('render').selectpicker('setStyle', 'btn-large', 'remove');
                        } else {
                            if (res.isFullQuerySelector) {
								
                                if (res.addNew != '0') {
									
                                    $(res.append_id).find('option:not(.add-new-item)').remove();
                                    $(res.append_id).find('option.add-new-item').after(res.result).selectpicker('refresh').trigger('change')
                                } else {
                                    $(res.append_id).empty().append(res.result).selectpicker('refresh').trigger('change');

                                }
                            } else {
								
                                $('#' + res.append_id).empty().append(res.result).trigger('changed.bs.select').trigger('changed.bs.select').selectpicker('render');
                                $('#' + res.append_id).selectpicker('refresh').trigger('change');
								reinitializeSelect2()
                            }
                        }

                    }
                }
                , error: function(data) {}
            });


        }

    </script>
