@props([
    'link'
])


                                    <div class="kt-list-timeline__items">

                                        <div class="kt-list-timeline__item">
                                            <span class="kt-list-timeline__badge kt-list-timeline__badge--brand"></span>
                                            <span class="kt-list-timeline__text"> <h4 class="subtitle-card-header"> {{ $slot }}
                                             </h4>  </span>
                                            <span class="kt-list-timeline__time disable"> <a href="{{ $link }}" class="btn btn-outline-info "><b>{{ __('GO') }}</b></a></span>
                                        </div>
                                    </div>