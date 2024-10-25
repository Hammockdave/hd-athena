(function ($, Drupal) {

    'use strict';

    Drupal.behaviors.hd_athena_general_pages = {
        attach: function (context) {

            $( ".main-topic .show-hide-more a" ).on( "click", function() {
                if ($(this).parent().hasClass('show')) {
                    var mainTopic = $(this).closest('.main-topic');
                    $(mainTopic).find('.hidden-menu-item').css('display', 'list-item');
                    $(this).html('Show less...');
                    $(this).parent().addClass('hide');
                    $(this).parent().removeClass('show');
                } else {
                    var mainTopic = $(this).closest('.main-topic');
                    $(mainTopic).find('.hidden-menu-item').css('display', 'none');
                    $(this).html('Show more...');
                    $(this).parent().addClass('show');
                    $(this).parent().removeClass('hide');
                }
            });

            var equalHeight = 1;
            $( ".equal-height" ).each(function( ) {
                var currentHeight = $(this).height();
                if (currentHeight > equalHeight) {
                    equalHeight = currentHeight;
                }
            });
            $( ".equal-height" ).height(equalHeight);

            // When a user logs in to Drupal and there's no destination query parameter
            // they'll be redirected automatically to the /athena page. When this happens,
            // the Admin toolbar, if it's on the logout tray, the page will be under the,
            // Admin toolbar a bit. It's somewhat of an edge case, but we'll attempt to fix it
            if (window.location.pathname === '/athena') {
                setTimeout(() => {  $('header.full-header').css('top', '79px'); }, 1000); // Lazy man's way, since the Admin Toolbar tries to set it first

                $('body').css('padding-top', '79px');
            }
        }
    };


})(jQuery, Drupal);



