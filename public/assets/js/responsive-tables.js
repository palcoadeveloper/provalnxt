/* ProVal HVAC - Responsive DataTables Enhancement */

$(document).ready(function() {
    // Common responsive DataTable configuration
    const responsiveConfig = {
        responsive: true,
        scrollX: true,
        scrollCollapse: true,
        language: {
            lengthMenu: "Show _MENU_ entries",
            search: "Search:",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Prev"
            },
            emptyTable: "No data available in table"
        },
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        columnDefs: [
            { 
                targets: '_all',
                className: 'dt-center'
            }
        ],
        initComplete: function(settings, json) {
            // Add responsive class to wrapper
            $(this).closest('.dataTables_wrapper').addClass('table-responsive-enhanced');
            
            // Mobile-specific adjustments
            if (window.innerWidth < 768) {
                // Adjust pagination to show fewer buttons on mobile
                $('.dataTables_paginate .paginate_button').addClass('btn-sm');
                
                // Stack filter and length controls on mobile
                var wrapper = $(this).closest('.dataTables_wrapper');
                wrapper.find('.dataTables_length').addClass('mb-2');
                wrapper.find('.dataTables_filter').addClass('mb-2');
            }
        },
        drawCallback: function(settings) {
            // Re-apply mobile optimizations after each redraw
            if (window.innerWidth < 768) {
                $('.dataTables_paginate .paginate_button').addClass('btn-sm');
            }
        }
    };
    
    // Auto-initialize any tables with the 'responsive-table' class
    if ($.fn.DataTable) {
        $('.responsive-table').each(function() {
            var table = $(this);
            
            // Check if DataTable is already initialized
            if (!$.fn.DataTable.isDataTable(table)) {
                table.DataTable(responsiveConfig);
            }
        });
        
        // Auto-initialize common table selectors used in ProVal
        var commonSelectors = [
            '#example',
            '#example1', 
            '#example2',
            '.datatable',
            '.data-table'
        ];
        
        commonSelectors.forEach(function(selector) {
            if ($(selector).length && !$.fn.DataTable.isDataTable(selector)) {
                $(selector).DataTable(responsiveConfig);
            }
        });
    }
    
    // Handle window resize for responsive adjustments
    $(window).on('resize', debounce(function() {
        if ($.fn.DataTable) {
            // Recalculate column widths on resize
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            
            // Mobile-specific adjustments
            if (window.innerWidth < 768) {
                $('.dataTables_paginate .paginate_button').addClass('btn-sm');
                $('.dataTables_wrapper').addClass('mobile-optimized');
            } else {
                $('.dataTables_paginate .paginate_button').removeClass('btn-sm');
                $('.dataTables_wrapper').removeClass('mobile-optimized');
            }
        }
    }, 250));
    
    // Mobile touch enhancements for table scrolling
    $('.table-responsive').on('touchstart', function() {
        $(this).addClass('scrolling');
    }).on('touchend', function() {
        $(this).removeClass('scrolling');
    });
    
    // Add scroll indicators for mobile tables
    function addScrollIndicators() {
        $('.table-responsive').each(function() {
            var container = $(this);
            var table = container.find('table');
            
            if (table.width() > container.width()) {
                container.addClass('has-scroll');
                
                if (!container.find('.scroll-indicator').length) {
                    container.append('<div class="scroll-indicator">← Scroll to see more →</div>');
                }
            } else {
                container.removeClass('has-scroll');
                container.find('.scroll-indicator').remove();
            }
        });
    }
    
    // Initialize scroll indicators
    addScrollIndicators();
    
    // Update scroll indicators on window resize
    $(window).on('resize', debounce(addScrollIndicators, 250));
});

// Utility function to debounce rapid events
function debounce(func, wait, immediate) {
    var timeout;
    return function() {
        var context = this, args = arguments;
        var later = function() {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };
        var callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
    };
}

// Export responsive config for manual initialization
window.ProValResponsiveTable = {
    config: {
        responsive: true,
        scrollX: true,
        scrollCollapse: true,
        language: {
            lengthMenu: "Show _MENU_ entries",
            search: "Search:",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Prev"
            },
            emptyTable: "No data available in table"
        },
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        columnDefs: [
            { 
                targets: '_all',
                className: 'dt-center'
            }
        ]
    },
    init: function(selector, customConfig) {
        var config = $.extend({}, this.config, customConfig || {});
        return $(selector).DataTable(config);
    }
};