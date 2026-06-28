@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<style>
    .money-flow-dark .kt-portlet {
        overflow: visible !important;
    }
</style>
@endsection
@section('content')
<div class="money-flow-dark">
    {{-- Table Component ['href of Add Button'] & ['The Title Of The Table'] --}}
    <x-table :href="route('companySection.create')" :tableTitle="__('Companies Table')">
        {{-- Head Of The Table --}}
        @slot('table_header')
            <tr class="table-standard-color">
                <th>{{ __('Company') }}</th>
                <th>{{ __('Company Name') }} </th>
                <th>{{ __('Controll') }}</th>
            </tr>
        @endslot

        {{-- Body Of The Table --}}
        @slot('table_body')
            @foreach ($companies as $item)
                <tr class="text-center">
                    <td>
                        <img class="index-img" width="100" height="100" src="{{ $item->getFirstMediaUrl() }}" alt="image">
                        @if($item->getFirstMediaUrl() )
<br>

                          <a href="{{ route('remove.company.image' , [App()->getLocale()   , $item->id]) }}" class="btn btn-secondary btn-outline-hover-danger btn-icon remove-item-class " title="Delete" s><i
                                    class="fa fa-trash"></i>
                                </a>
@endif 
                    </td>
                    <td>{{ $item->name[lang()] }}</td>


                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">
                        <span style="overflow: visible; position: relative; width: 110px;">
                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{ route('companySection.edit', [$item]) }}"><i
                                   class="fa fa-pen-alt"></i></a>

                                  

                                   <a  class="btn btn-secondary btn-outline-hover-danger btn-icon remove-item-class remove-user-class" data-id="{{ $item->id }}" title="Delete" ><i
                                    class="fa fa-trash-alt"></i>
                                </a>

{{-- 
                            <form method="post"   action="{{route('companySection.destroy',$item)}}" style="display: inline">
                                @method('DELETE')
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href=""><i class="fa fa-trash-alt"></i></button>
                            </form> --}}
                            {{-- <a type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href=""><i
                                    class="fa fa-trash-alt"></i></a> --}}
                        </span>
                    </td>
                </tr>
            @endforeach
        @endslot
    </x-table>
</div>
@endsection

@section('js')
    <script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
    <script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript">
    </script>

    
    <script>
        $('.remove-user-class').on('click' , function(e){
            e.preventDefault();

            Swal.fire({
  icon: 'warning',
  title: '{{ __("Warning") }}',
  showConfirmButton:true,
  showCancelButton:true,
  cancelButtonText:'{{ __("Cancel") }}',
  text: '{{ __("Are You Sure To Delete This Company") }}',
}).then(()=>{
     let company_id = $(this).data('id');
            $.ajax({
                        type: 'post',
                        url: "{{ route('remove.company') }}",
                        data: {
                            '_token':"{{csrf_token()}}",
                            'company_id':company_id,
                        },
                        success: function (data) {
                            if(data.status)
                            {
                                Swal.fire({
                                        position: 'top-end',
                                        icon: 'success',
                                        title: "{{ __('Company Has Been Removed Successfully') }}",
                                        showConfirmButton: false,
                                        timer: 1500
                                        }).then(function(){
                                            window.location.reload();
                                            
                                        })
                            }
                        }, error: function (reject) {
                        }
                    });

})

           
        });
        
    </script>
@endsection
