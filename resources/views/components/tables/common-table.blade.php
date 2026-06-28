<style>
    .max-w-weeks {
        max-width: 100px !important;
        min-width: 100px !important;
        width: 100px !important;
    }

    .is-sub-row.is-total-row td.sub-numeric-bg,
    .is-sub-row.is-total-row td.sub-text-bg {
        background-color: #087383 !important;
        color: white !important;
    }

    .is-name-cell {
        white-space: normal !important;
    }

    .top-0 {
        top: 0 !important;
    }

    .parent-tr td {
        border: 1px solid #E2EFFE !important;
    }

    .dataTables_filter {
        width: 30% !important;
        text-align: left !important;

    }

    .border-parent {
        border: 2px solid #E2EFFE;
    }

    .dt-buttons.btn-group,
    .buttons-print {
        max-width: 30%;
        margin-left: auto;
        position: relative;
        top: 45px;
    }

    .details-btn {
        display: block;
        margin-top: 10px;
        margin-left: auto;
        margin-right: auto;
        font-weight: 600;

    }

    .expand-all {
        cursor: pointer;
    }

    td.editable-date.max-w-fixed,
    th.editable-date.max-w-fixed,
    input.editable-date.max-w-fixed {
        width: 1050px !important;
        max-width: 1050px !important;
        min-width: 1050px !important;

    }

    td.editable-date.max-w-classes-expand,
    th.editable-date.max-w-classes-expand,
    input.editable-date.max-w-classes-expand {
        width: 70px !important;
        max-width: 70px !important;
        min-width: 70px !important;

    }

    td.max-w-classes-name,
    th.max-w-classes-name,
    input.max-w-classes-name {
        width: 350px !important;
        max-width: 350px !important;
        min-width: 350px !important;

    }

    td.max-w-grand-total,
    th.max-w-grand-total,
    input.max-w-grand-total {
        width: 100px !important;
        max-width: 100px !important;
        min-width: 100px !important;

    }

    * {
        box-sizing: border-box !important;
    }

    td.editable-date,
    th.editable-date,
    input.editable-date {
        width: 100px !important;
        min-width: 100px !important;
        max-width: 100px !important;
        overflow: hidden;
    }

    .width-66 {


        width: 66% !important;
    }

    .border-bottom-popup {
        border-bottom: 1px solid #d6d6d6;
        padding-bottom: 20px;
    }

    .flex-self-start {
        align-self: flex-start;
    }

    .flex-checkboxes {
        margin-top: 1rem;
        flex: 1;
        width: 100% !important;
    }


    .flex-checkboxes>div {
        width: 100%;
        width: 100% !important;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }

    .custom-divs-class {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
    }


    .modal-backdrop {
        display: none !important;
    }

    .modal-content {
        min-width: 600px !important;
    }

    .form-check {
        padding-left: 0 !important;

    }

    .main-with-no-child,
    .main-with-no-child td,
    .main-with-no-child th {
        background-color: #046187 !important;
        color: white !important;
        font-weight: bold;
    }

    .is-sub-row td.sub-numeric-bg,
    .is-sub-row td.sub-text-bg {
        border: 1.5px solid white !important;
        background-color: #0e96cd !important;
        color: white !important;


        color: black !important
    }



    .sub-numeric-bg {
        text-align: center;

    }



    th.dtfc-fixed-left {
        background-color: #074FA4 !important;
        color: white !important;
    }

    .header-tr,
        {
        background-color: #046187 !important;
    }

    .dt-buttons.btn-group {
        display: flex;
        align-items: flex-start;
        justify-content: flex-end;
        margin-bottom: 1rem;
    }

    .is-sales-rate,
    .is-sales-rate td,
    .is-sales-growth-rate,
    .is-sales-growth-rate td {
        background-color: #046187 !important;
        color: white !important;
    }

    .dataTables_wrapper .dataTable th,
    .dataTables_wrapper .dataTable td {
        font-weight: bold;
        color: black;
    }

    a[data-toggle="modal"] {
        color: #046187 !important;
    }

    a[data-toggle="modal"].text-white {
        color: white !important;
    }

    .btn-border-radius {
        border-radius: 10px !important;
    }

</style>


<div class="table-custom-container position-relative  ">



    <div class="responsive">
        <table class="table kt_table_with_no_pagination_no_collapse table-striped- table-bordered table-hover table-checkable position-relative table-with-two-subrows main-table-class dataTable no-footer">
            <thead>

                <tr class="header-tr ">

                    <th class="view-table-th  bg-lighter max-w-weeks header-th  align-middle text-center">
                        {{ __('#') }}
                    </th>

                    <th class="view-table-th  bg-lighter max-w-weeks header-th  align-middle text-center">
                        {{ __('Customer Name') }}
                    </th>

                    <th class="view-table-th  bg-lighter max-w-weeks header-th  align-middle text-center">
                        {{ __('Net Balance') }}
                    </th>

                    <th class="view-table-th  bg-lighter max-w-weeks header-th  align-middle text-center">
                        {{ __('Statement Report') }}
                    </th>
                    <th class="view-table-th  bg-lighter max-w-weeks header-th  align-middle text-center">
                        {{ __('Invoice') }}
                    </th>



                </tr>

            </thead>
            <tbody>
                <script>
                    let currentTable = null;

                </script>

                @foreach(['Cash & Banks Begining Balance','Checks Collected','Incoming Transfers','Customers Invoices Under Collection','Customers Checks Under Collection','Sales Forecast Collections','Total Cash Inflow','Raw Materils Payable Checks','Suppliers Payable','Operational Expenses Payments','Wages & Salaries Payments','Taxes & Social Insurance Payments','Forecasted Suppliers Payments','Total Cash Outflow','Cash Flow From Operations'] as $customerName)


                <tr class=" parent-tr reset-table-width text-nowrap  cursor-pointer sub-text-bg text-capitalize is-close   ">
                    {{-- <td class="red reset-table-width text-nowrap trigger-child-row-1 cursor-pointer sub-text-bg text-capitalize main-tr is-close"> @if($hasSubRows) + @endif</td> --}}
                    <td class="sub-text-bg   editable-text  max-w-classes-name is-name-cell ">{{ 1 }}</td>
                    <td class="sub-text-bg   editable-text  max-w-classes-name is-name-cell ">{{ $customerName }}</td>
                    <td class="sub-text-bg   editable-text  max-w-classes-name is-name-cell ">{{ 2500 }}</td>
                    <td class="sub-text-bg   editable-text  max-w-classes-name is-name-cell ">{{ 'statement report' }}</td>
                    <td class="sub-text-bg   editable-text  max-w-classes-name is-name-cell ">{{ 'invoice' }}</td>
                    {{-- <td class="  sub-numeric-bg text-center editable-date"></td> --}}


                    {{-- <td class="  sub-numeric-bg text-center editable-date">{{ number_format($result[$customerName]['total'][$year] ?? 0 ) }}</td> --}}

                </tr>









                @endforeach

            </tbody>
        </table>
    </div>

</div>
