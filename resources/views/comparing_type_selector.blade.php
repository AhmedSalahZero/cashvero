  <div class="col-md-6">
                            <label>{{ __('Report Type') }} </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select name="report_type" id="report_type" class="form-control ">
                                        <option selected value="trend">{{ __('Trend') }}</option>
                                        <option value="comparing">{{ __('Interval Comparing') }}</option>

                                    </select>
                                </div>
                            </div>
                        </div>




                        @push('js')

                        <script>
                            $(function(){
                                $('#report_type').on('change',function(){
                                    let reportType = $(this).val();
                                     $('#comparing__id').remove();
                                     $('select[name="interval"]').closest('div[class*="col-"]').removeClass('d-none');
                                     $('select[name="interval"]').attr('required','required');
                                    
                                    if(reportType == 'comparing')
                                    {
                                        $('select[name="interval"]').closest('div[class*="col-"]').addClass('d-none');
                                        $('select[name="interval"]').removeAttr('required');
                                        let clonedField = $('input[name="start_date"]').closest('.row').clone(true);
										
                                        $(clonedField).find('input').each(function(index , inputField){
											if($(inputField).attr('type') == 'date'){
												var currentValue = $(inputField).attr('value') ;
												if(currentValue){
													var year = currentValue.split('-')[0] - 1 ;
													var month =currentValue.split('-')[1];
													var day =currentValue.split('-')[2];
													
												$(inputField).attr('value', year + '-'+month+'-'+day);
													
												}
												
											}	
                                            $(inputField).attr('name',$(inputField).attr('name') + '_second'); 
                                        })
										  $(clonedField).find('label.first-interval').each(function(index , inputField){
                                            $(inputField).html("{{ __('Second Interval') }}"); 
											$(inputField).removeClass('first-interval').addClass('d-block')
											$(inputField).addClass('second-interval').addClass('d-block')
											
                                        })
                                        if(clonedField.length)
                                        {
                                        
                                            let div = $('<div id="comparing__id"></div>');
                                            $('input[name="start_date"]').closest('.row').after(div);
                                            $('#comparing__id').empty();
                                            $('#comparing__id').append(clonedField);
											
											    	$('label.first-interval').closest('div.first-interval').removeClass('d-none').addClass('d-block')
                                        $('label.second-interval').closest('div.first-interval').removeClass('d-none').addClass('d-block')
										$('input[type="date"]').trigger('change')
                                   
                                        }
                                    }
                                    else{
										$('label.first-interval').closest('div.first-interval').addClass('d-none').removeClass('d-block')
                                        $('label.second-interval').closest('div.first-interval').addClass('d-none').removeClass('d-block')
                                    }
                                }); 
                                $('#report_type').trigger('change');
                            })
                        </script>

                        @endpush
