import './bootstrap';
import $ from 'jquery';
import DataTable from 'datatables.net-dt';
import 'datatables.net-responsive-dt';

window.$ = window.jQuery = $;
window.DataTable = DataTable;

// DataTables Arabic Translation Object
const arabicTranslation = {
    "sProcessing": "جاري التحميل...",
    "sLengthMenu": "أظهر _MENU_ مدخلات",
    "sZeroRecords": "لم يعثر على أية سجلات",
    "sInfo": "إظهار _START_ إلى _END_ من أصل _TOTAL_ مدخل",
    "sInfoEmpty": "يعرض 0 إلى 0 من أصل 0 سجل",
    "sInfoFiltered": "(منتقاة من مجموع _MAX_ مُدخل)",
    "sSearch": "ابحث:",
    "oPaginate": {
        "sFirst": "الأول",
        "sPrevious": "السابق",
        "sNext": "التالي",
        "sLast": "الأخير"
    }
};

$.extend(true, $.fn.dataTable.defaults, {
    language: arabicTranslation,
    responsive: true,
    pageLength: 10
});

// Auto-initialize DataTables for all .data-table elements
$(document).ready(function () {
    $('.data-table').each(function () {
        if (!$.fn.DataTable.isDataTable(this)) {
            $(this).DataTable();
        }
    });
});
