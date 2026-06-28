"use strict";
var KTDatatablesBasicPaginations = (function () {
    var initTable1 = function () {
        var table = $(".kt_table_1");

        // begin first table
        table.DataTable({
            deferRender: true,
            responsive: true,
            pagingType: "full_numbers",
            pageLength: 25,
            scrollX: true,
            // Pagination settings
            dom: `<'row'<'col-sm-6 text-left'f><'col-sm-6 text-right'B>>
			<'row'<'col-sm-12'tr>>
			<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 dataTables_pager'lp>>`,

            buttons: ["print", "copyHtml5", getExportKey(), "pdfHtml5"],
        });
    };

    var initTableWithNoPagination = function () {
        var table = $(".kt_table_with_no_pagination");
        // table.fixedHeader.adjust();
        // begin first table
        table.DataTable({
            // responsive:true ,
            deferRender: true,
            scrollY: true,
            scrollX: true,
            search: true,
            pageLength: 100,
            scrollCollapse: true,
            fixedHeader: {
                header: true,
                headerOffset: 59,
            },

            paging: false,
            fixedColumns: {
                left: 1,
                right: 1,
            },
            paging: false,
            ordering: false,
			
            dom: `<'row'<'col-sm-6 text-left'f><'col-sm-6 text-right'B>>
			<'row'<'col-sm-12'tr>>
			<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 dataTables_pager'lp>>`,
            buttons: ["print", "copyHtml5", getExportKey(), "pdfHtml5"],
        });
    };
	
	
	var initTableWithNoPaginationNoFixed = function() {

        var table = $('.kt_table_with_no_pagination_no_fixed');
        // table.fixedHeader.adjust();
        // begin first table
        table.DataTable(
            {
                // responsive:true ,
            scrollY:        true,
            scrollX:        true,
            search:false,
          pageLength:100,
            scrollCollapse: true,
			header:true,
			
          
         
            paging:false,
           
            paging:   false,
            ordering: false,
			dom: `<'row'<'col-sm-6 text-left'f><'col-sm-6 text-right'B>>
			<'row'<'col-sm-12'tr>>
			<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 dataTables_pager'lp>>`,

			buttons: [
				'print',
				'copyHtml5',
				getExportKey(),
				'csvHtml5',
				'pdfHtml5',
                
			]
        });
        
        

    };
	
	
	var initTableWithNoPaginationNoFixedRight = function () {
        var table = $(".kt_table_with_no_pagination_no_fixed_right");
        // table.fixedHeader.adjust();
        // begin first table
        table.DataTable({
            // responsive:true ,
            deferRender: true,
            scrollY: true,
            scrollX: true,
            search: true,
            pageLength: 100,
            scrollCollapse: true,
            fixedHeader: {
                header: true,
                headerOffset: 59,
            },

            paging: false,
            fixedColumns: {
                left: 1,
            },
            paging: false,
            ordering: false,
            dom: `<'row'<'col-sm-6 text-left'f><'col-sm-6 text-right'B>>
			<'row'<'col-sm-12'tr>>
			<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 dataTables_pager'lp>>`,

            buttons: ["print", "copyHtml5", getExportKey(), "pdfHtml5"],
        });
    };

	
	
    var initTableWithNoPaginationNoCollapse = function () {
        var table = $(".kt_table_with_no_pagination_no_collapse");
        // begin first tablea
        table.DataTable({
            deferRender: true,
            // responsive: true,
            // scrollY:        true,
            // scrollX:        true,
            // scrollCollapse: true,
            // "pageLength":true,
            paging: false,
            fixedColumns: {
                left: 1,
                right: 1,
            },
            paging: false,
            ordering: false,
            search: true,
            dom: `<'row'<'col-sm-6 text-left'f><'col-sm-6 text-right'B>>
			<'row'<'col-sm-12'tr>>
			<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 dataTables_pager'lp>>`,

            buttons: ["print", "copyHtml5", getExportKey(), "pdfHtml5"],
        });
    };
    var initTableWithNoPaginationNoSearch = function () {
        var table = $(".kt_table_with_no_pagination_no_search");

        // begin first table
        table.DataTable({
            // responsive: true,
            deferRender: true,
            paging: false,
            ordering: false,
            searching: false,
            dom: `<'row'<'col-sm-6 text-left'f><'col-sm-6 text-right'B>>
			<'row'<'col-sm-12'tr>>
			<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 dataTables_pager'lp>>`,

            buttons: ["print", "copyHtml5", getExportKey(), "pdfHtml5"],
        });
    };
    var initTableWithNoPaginationNoScroll = function () {
        var table = $(".kt_table_with_no_pagination_no_scroll");

        // begin first table
        table.DataTable({
            deferRender: true,
            // responsive: true,
            paging: false,
            ordering: false,
            searching: true,
            dom: `<'row'<'col-sm-6 text-left'f><'col-sm-6 text-right'B>>
				<'row'<'col-sm-12'tr>>
				<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 dataTables_pager'lp>>`,

            buttons: [
                "print",
                "copyHtml5",
                getExportKey(),
                // 'excelHtml5',
                "pdfHtml5",
            ],
        });
    };
	
	var initTableWithNoPaginationNoScrollNoEntries = function () {
        var table = $(".kt_table_with_no_pagination_no_scroll_no_entries");

        // begin first table
        table.DataTable({
            deferRender: true,
            // responsive: true,
            paging: false,
            ordering: false,
            searching: true,
			"bInfo" : false,
            dom: `<'row'<'col-sm-6 text-left'f><'col-sm-6 text-right'B>>
				<'row'<'col-sm-12'tr>>
				<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 dataTables_pager'lp>>`,

            buttons: [
                "print",
                "copyHtml5",
                getExportKey(),
                // 'excelHtml5',
                "pdfHtml5",
            ],
        });
    };

    var initTableWithNoPaginationNoScrollWithoutPdf = function () {
        var table = $(".kt_table_with_no_pagination_no_scroll_without_pdf");

        // begin first table
        table.DataTable({
            deferRender: true,
            // responsive: true,
            paging: false,
            ordering: false,
            searching: false,
            dom: `<'row'<'col-sm-6 text-left'f><'col-sm-6 text-right'B>>
			<'row'<'col-sm-12'tr>>
			<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 dataTables_pager'lp>>`,

            buttons: ["print", "copyHtml5", getExportKey()],
        });
    };

    var initTableWithNoPaginationNoScrollNoInfo = function () {
        var table = $(".kt_table_with_no_pagination_no_scroll_no_info");

        // begin first table
        table.DataTable({
            deferRender: true,

            // responsive: true,
            paging: false,
            ordering: false,
            searching: true,
            info: false,
            dom: `<'row'<'col-sm-6 text-left'f><'col-sm-6 text-right'B>>
			<'row'<'col-sm-12'tr>>
			<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 dataTables_pager'lp>>`,

            buttons: ["print", "copyHtml5", getExportKey(), "pdfHtml5"],
        });
    };

    var initTableWithNoPaginationNoScrollNoSearch = function () {
        var table = $(".kt_table_with_no_pagination_no_scroll_no_search");

        // begin first table
        table.DataTable({
            deferRender: true,
            // responsive: true,
            paging: false,
            ordering: false,
            searching: false,
            dom: `<'row'<'col-sm-6 text-left'f><'col-sm-6 text-right'B>>
			<'row'<'col-sm-12'tr>>
			<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 dataTables_pager'lp>>`,

            buttons: ["print", "copyHtml5", getExportKey(), "pdfHtml5"],
        });
    };
	
	var initTableWithNoPaginationNoScrollNoSearchNoInfo = function () {
        var table = $(".kt_table_with_no_pagination_no_scroll_no_search_no_info");

        // begin first table
        table.DataTable({
            deferRender: true,
            // responsive: true,
            paging: false,
            ordering: false,
			info:false,
            searching: false,
            dom: `<'row'<'col-sm-6 text-left'f><'col-sm-6 text-right'B>>
			<'row'<'col-sm-12'tr>>
			<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 dataTables_pager'lp>>`,

            buttons: ["print", "copyHtml5", getExportKey(), "pdfHtml5"],
        });
    };
	

    return {
        //main function to initiate the module
        init: function () {
            initTable1();
            initTableWithNoPagination();
            initTableWithNoPaginationNoScroll();
            initTableWithNoPaginationNoScrollNoEntries();
            initTableWithNoPaginationNoSearch();
            initTableWithNoPaginationNoCollapse();
            initTableWithNoPaginationNoScrollNoSearch();
            initTableWithNoPaginationNoScrollNoSearchNoInfo();
            initTableWithNoPaginationNoScrollNoInfo();
            initTableWithNoPaginationNoScrollWithoutPdf();
            initTableWithNoPaginationNoFixedRight();
            initTableWithNoPaginationNoFixed();
        },
    };
})();

jQuery(document).ready(function () {
    KTDatatablesBasicPaginations.init();
});
function getExportKey() {
    return {
        extend: "excel",
        title: "",
        filename: "Vero Analysis Report",
        customize: function (xlsx) {
            exportToExcel(xlsx);
        },
    };
}
