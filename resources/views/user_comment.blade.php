<div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title head-title text-primary">
                                        {{__('User Comment')}}
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">


                                <div class="form-group row">


                                    <div class="col-md-12">
                                      
                                            <label for="user-comment">{{__('Comment')}}
                                            {{-- @include('star') --}}
                                        </label>
                                            <textarea id="user-comment" class="form-control" name="user_comment">{{ isset($model) ? $model->getUserComment() : '' }}</textarea>

                                        </div>




                                </div>
                            </div>
                        </div>
