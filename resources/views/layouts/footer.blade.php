<div class="kt-footer kt-footer--extended kt-grid__item @if(isMoneyFlowDarkPage()) money-flow-footer @endif" id="kt_footer" @unless(isMoneyFlowDarkPage()) style="background-image: url({{ asset('assets/media/bg/bg-2.jpg') }});" @endunless>
    @if(isMoneyFlowDarkPage())
        <div class="money-flow-footer__accent" aria-hidden="true"></div>
    @endif

    <div class="kt-footer__bottom">
        <div class="kt-container">
            <div class="kt-footer__wrapper">
                <div class="kt-footer__copyright cashvero-footer__copyright">
                    &copy; {{ date('Y') }} CashVero &middot; Built by SQUAD Business Consulting &middot; Cairo, Egypt
                </div>
            </div>
        </div>
    </div>
</div>
