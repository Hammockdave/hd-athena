(function ($, Drupal) {

    'use strict';

    Drupal.behaviors.hd_athena_content_types = {
        attach: function (context) {

            updateCheckBoxesWidth($);

            $( ".vertical-tabs ul li a" ).on( "click", function() {
                setTimeout(() => {  updateCheckBoxesWidth($); }, 10);
            });
        }
    };

    $('input.read-only-checkbox-hack').parent().css('pointer-events', 'none');

})(jQuery, Drupal);


function updateCheckBoxesWidth($) {
    $( ".athena-content-types-checkboxes-horizontal.athena-checkboxes-width-not-calculated" ).each(function( index ) {
        var checkboxes = $(this).find('.form-type-checkbox');

        var maxWidth = 0;
        $( checkboxes).css('width', '');
        $( checkboxes ).each(function( ) {
            var width = Math.ceil($(this).width());
            if (width > maxWidth) {
                maxWidth = width;
            }
        });

        // The checkboxes are visible and not hidden
        if (maxWidth >= 1) {
            $(checkboxes).css('width', maxWidth + 20);
            $(this).addClass('athena-checkboxes-width-calculated');
            $(this).removeClass('athena-checkboxes-width-not-calculated');
        } else { // The checkboxes are hidden
            $(this).addClass('athena-checkboxes-width-not-calculated');
        }
    });
}


