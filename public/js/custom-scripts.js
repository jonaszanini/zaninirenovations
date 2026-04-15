/**
 * Zanini Renovations — front-end interactions (navigation, sliders, contact form, etc.)
 */
jQuery(function ($) {

    "use strict";
    
    // 00. RTL status check.
    var rtl_status = false;
    if ($('html').is('[dir]')) {
        rtl_status = true;
    }


    // DETECT TOUCH DEVICE
    
    function is_touch_device() {
        return !!('ontouchstart' in window) || (!!('onmsgesturechange' in window) && !!window.navigator.maxTouchPoints);
    }

    /**
     * Pixel offset for one-page anchor scroll: fixed/sticky header height plus a small buffer,
     * never less than the target section's CSS scroll-margin-top (see styles.css).
     */
    function zr_one_page_scroll_offset($target) {
        var headerClearance = 0;
        var $m2 = $('.main-header-2');
        if ($m2.length) {
            var $stuckNav = $m2.find('.navbar-default.stuck');
            if ($stuckNav.length) {
                headerClearance = Math.ceil($stuckNav.outerHeight() || 0);
            } else {
                headerClearance = Math.ceil($m2.find('.header-wrapper').first().outerHeight() || 0);
            }
        } else {
            var wrapH = $('.header-wrapper').outerHeight() || 0;
            if ($('.stuck').length === 1) {
                headerClearance = Math.ceil($('.stuck').outerHeight() || 0);
            } else {
                headerClearance = Math.ceil(wrapH) + 24;
            }
        }
        var scrollMarginPx = 0;
        if ($target && $target.length && $target[0]) {
            var sm = window.getComputedStyle($target[0]).scrollMarginTop;
            var parsed = parseFloat(sm);
            if (!isNaN(parsed)) {
                scrollMarginPx = parsed;
            }
        }
        return Math.max(headerClearance + 8, scrollMarginPx);
    }

    // ANIMATIONS //
     function animations() {

        animations = new WOW({
            boxClass: 'wow',
            animateClass: 'animated',
            offset: 30,
            mobile: false,
            live: true
        });

        animations.init();

    }

    // ONE PAGE SMOOTH SCROLLING 

    function smooth_scrolling() {

        $(".nav_menu").on("click", function () {

            if (location.pathname.replace(/^\//, '') === this.pathname.replace(/^\//, '') && location.hostname === this.hostname) {

                var target = $(this.hash);
                target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
                var offset = zr_one_page_scroll_offset(target);

                if (target.length) {
                    var pos = target.offset();
                    if (!pos) {
                        return false;
                    }
                    var scrollTop = pos.top - offset;
                    if (scrollTop < 0) {
                        scrollTop = 0;
                    }
                    $('html,body').animate({
                        scrollTop: scrollTop
                    }, 1300);

                    return false;

                }

            }

        });

    }

    // PARALLAX

    if (typeof $.fn.stellar !== 'undefined') {

        if (!is_touch_device()) {

            $(window).stellar({
                horizontalScrolling: false,
                verticalScrolling: true,
                responsive: true,
                verticalOffset: 50
            });

        }

    }
    
    // BANNER.
    
    if ($(".section-banner").length) {
    
     $(".section-banner").each(function () {

            var $this = $(this);

            var bg_img = "images/home_1_slider_1.webp",
                    bg_color = "#000000",
                    bg_opacity = "0.5",
                    bg_color_2 = "#000000",
                    bg_opacity_2 = "0.8";

            if ($this.is('[data-bg_img]')) {
                bg_img = ', url("' + $this.data('bg_img') + '")';
            } else {
                bg_img = ', url("' + bg_img + '")';
            }

            if ($this.is('[data-bg_color]')) {
                bg_color = $this.data('bg_color');
            }

            if ($this.is('[data-bg_opacity]')) {
                bg_opacity = $this.data('bg_opacity');
            }

            var $color_overlay = hexToRgbA(bg_color, bg_opacity);
            
            $color_overlay_2 = $color_overlay;
            
            if ($this.is('[data-gardient]') && $this.data('gardient') === true) {


                if ($this.is('[data-bg_color_2]')) {
                    bg_color_2 = $this.data('bg_color_2');
                }

                if ($this.is('[data-bg_opacity_2]')) {
                    bg_opacity_2 = $this.data('bg_opacity_2');
                }

                var $color_overlay_2 = hexToRgbA(bg_color_2, bg_opacity_2);

            }
            

            $this.attr("style", "background:linear-gradient( " + $color_overlay + ",  " + $color_overlay_2 + " )" + bg_img + "; background-position: center center; background-repeat: repeat; background-attachment: inherit; background-size: cover; overflow:hidden;");

        });
        
    }
    
    // SLIDER 1

    function slider_resize() {

        if ($(window).width() > 991) {
            
             $(".slider-content").first().attr("style", "margin-top: 0px;");
             
        } else {

            $(".slider-content").first().attr("style", "margin-top: 0px;");

        }


    }

    // SLIDER 1
    
    function slider_resize() {
        if ($(window).width() > 991) {
             $(".slider-content").first().attr("style", "margin-top: 0px;");
        } else {
            $(".slider-content").first().attr("style", "margin-top: 0px;");
        }
    }
    
    function hexToRgbA(hex, opacity) {
        var c;
        if (/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)) {
            c = hex.substring(1).split('');
            if (c.length === 3) {
                c = [c[0], c[0], c[1], c[1], c[2], c[2]];
            }
            c = '0x' + c.join('');
            return 'rgba(' + [(c >> 16) & 255, (c >> 8) & 255, c & 255].join(',') + ',' + opacity + ')';
        } else {
            return 'rgba("0,0,0,' + opacity + '")';
        }
    }
    
    // add animate.css class(es) to the elements to be animated
    function setAnimation(_elem, _InOut) {
        // Store all animationend event name in a string.
        // cf animate.css documentation
        var animationEndEvent = 'webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend animationend';
        _elem.each(function () {
            var $elem = $(this);
            var $animationType = 'animated ' + $elem.data('animation-' + _InOut);
            $elem.addClass($animationType).one(animationEndEvent, function () {
                $elem.removeClass($animationType); // remove animate.css Class at the end of the animations
            });
        });
    }

    
    if($('.slider-wrap')) {
        
        var $this = $('.slider-wrap');
        
        if ($this.is('[data-bg_img]')) {
            
            var bg_img = 'url("' + $this.data('bg_img') + '")';
            $this.css({
                'background-image': bg_img,
                'background-repeat': 'repeat',
                'background-position': 'center center',
                'background-size': 'cover'
            });
            
        }
        
    }
    
    if ($("#slider_1").length) {
        
        // BG & Color Settings.
        $("#slider_1").find('.slider_item_container').each(function () {
            var $this = $(this);
            var bg_img = "",
                    bg_color = "#000000",
                    bg_opacity = "0.1";
            if ($this.is('[data-bg_img]')) {
                bg_img = ', url("' + $this.data('bg_img') + '")';
            }
            if ($this.is('[data-bg_color]')) {
                bg_color = $this.data('bg_color');
            }
            if ($this.is('[data-bg_opacity]')) {
                bg_opacity = $this.data('bg_opacity');
            }
            var $color_overlay = hexToRgbA(bg_color, bg_opacity);
            
            $this.find('.item').before('<div class="slide-bg"></div>');
            
            $this.find('.slide-bg').attr("style", "background:linear-gradient( " + $color_overlay + ",  " + $color_overlay + " )" + bg_img + "; background-position: center center; background-repeat: no-repeat; background-attachment: inherit; background-size: cover; overflow:hidden;");
            
        });
        
        slider_resize();
        
        $(window).on("resize", function () {
            if ($(window).width() > 767) {
                slider_resize();
            } else {
                $(".slider-content").removeAttr("style");
            }
        });
        
        // Carousel.
        
        var $slider_1 = $("#slider_1");
        
        var $this = $slider_1;
        
        var items_val = 1,
                bg_effect_val = true,
                nav_val = false,
                dots_val = true,
                autoplay_val = true,
                autoplaytimeout_val = 10000;
        // Status.
        if ($this.attr('data-carousel') && $this.data('carousel') !== 1) {
            $this.removeClass('owl-carousel');
            return '';
        }
        
        // Status.
        if ($this.attr('data-bg_effect') && !isNaN($this.data('bg_effect'))) {
            bg_effect_val = $this.data('bg_effect');
        }
        // navigation status.
        if ($this.attr('data-nav') && !isNaN($this.data('nav'))) {
            nav_val = $this.data('nav');
        }

        // navigation status.
        if ($this.attr('data-dots') && !isNaN($this.data('dots'))) {
            dots_val = $this.data('dots');
        }
        // Autoplay status.
        if ($this.attr('data-autoplay') && !isNaN($this.data('autoplay'))) {
            autoplay_val = $this.data('autoplay');
        }
        // Autoplay status.
        if ($this.attr('data-autoplaytimeout') && !isNaN($this.data('autoplaytimeout'))) {
            autoplaytimeout_val = $this.data('autoplaytimeout');
        }
        
        $slider_1.owlCarousel({
            rtl: rtl_status,
            callbacks: true,
            margin:0,
            items: items_val,
            loop: true,
            autoplay: autoplay_val,
            autoplayTimeout: autoplaytimeout_val,
            autoplayHoverPause: false,
            dots: dots_val,
            nav: nav_val,
            responsive: {
                0: {
                    items: items_val,
                    nav: false,
                    loop: true,
                    dots: false
                },
                600: {
                    items: items_val,
                    nav: false,
                    loop: true,
                    dots: false
                },
                1000: {
                    items: items_val
                }
            },
            navText: ["<i class='fa fa-angle-left'></i>", "<i class='fa fa-angle-right'></i>"]
        });
        
        
        
        
        var $slider_animation = $slider_1;
        
         
        
        // Fired before current slide change
        $slider_animation.on('change.owl.carousel', function (event) {
            var $currentItem = $('.owl-item', $slider_animation).eq(event.item.index);
            var $elemsToanim = $currentItem.find("[data-animation-out]");
            setAnimation($elemsToanim, 'out');
        });
        
     
        // Fired after current slide has been changed

        $slider_animation.on('changed.owl.carousel', function (event) {
            var $currentItem = $('.owl-item', $slider_animation).eq(event.item.index);
            var $elemsToanim = $currentItem.find("[data-animation-in]");
            setAnimation($elemsToanim, 'in');
      
        });
        
        
        
        if( bg_effect_val === true ) {
           
            $slider_animation.on('translated.owl.carousel',function(e){
               $(".active .slide-bg").addClass("slidezoom");
           }); 

           $slider_animation.on('translate.owl.carousel',function(e){
               $(".active .slide-bg").removeClass("slidezoom");
           });
        
        }
        
        
    }
    
    // STICKY HEADER & MENU

   if ( $(".main-header").attr('data-sticky_header')) {
    
        $('.main-header .header-wrapper').waypoint('sticky', {
            wrapper: '<div class="sticky-wrapper" />',
            stuckClass: 'stuck'
        });
    
    }
    
    if ($(".main-header-2").length) {

        if ($(".main-header-2").attr('data-sticky_header')) {

            $('.main-header-2 .navbar-default').waypoint('sticky', {
                wrapper: '<div class="sticky-wrapper" />',
                stuckClass: 'stuck'
            });

        }
    }
    
    // CUSTOM BACKGROUND.

    if ($(".section-custom-bg").length) {

        $(".section-custom-bg").each(function () {

            var $this = $(this);

            var bg_img = "images/home_1_slider_1.webp",
                    bg_color = "#000000",
                    bg_opacity = "0.5",
                    bg_color_2 = "#000000",
                    bg_opacity_2 = "0.8",
                    bg_position = "center center",
                    bg_repeat = "no-repeat",
                    bg_size = "cover",
                    bg_overflow = "hidden",
                    bg_attachment = "inherit";



            // Background Image.

            if ($this.is('[data-bg_img]')) {
                bg_img = ', url("' + $this.data('bg_img') + '")';
            } else {
                bg_img = ', url("' + bg_img + '")';
            }

            // Background Color.

            if ($this.is('[data-bg_color]')) {
                bg_color = $this.data('bg_color');
            }

            if ($this.is('[data-bg_opacity]')) {
                bg_opacity = $this.data('bg_opacity');
            }

            var $color_overlay = hexToRgbA(bg_color, bg_opacity);

            $color_overlay_2 = $color_overlay;

            if ($this.is('[data-gardient]') && $this.data('gardient') === true) {

                if ($this.is('[data-bg_color_2]')) {
                    bg_color_2 = $this.data('bg_color_2');
                }

                if ($this.is('[data-bg_opacity_2]')) {
                    bg_opacity_2 = $this.data('bg_opacity_2');
                }

                var $color_overlay_2 = hexToRgbA(bg_color_2, bg_opacity_2);

            }

            // Background Position.

            var $bg_position = "";

            if ($this.is('[data-bg_position]')) {
                $bg_position += 'background-position: ' + $this.data('bg_position') + ';';
            } else {
                $bg_position += 'background-position: ' + bg_position + '; ';
            }

            // Background Repeat.

            var $bg_repeat = "";

            if ($this.is('[data-bg_repeat]')) {
                $bg_repeat += 'background-repeat: ' + $this.data('bg_repeat') + ';';
            } else {
                $bg_repeat += 'background-repeat: ' + bg_repeat + '; ';
            }

            // Background Size.

            var $bg_size = "";

            if ($this.is('[data-bg_size]')) {
                $bg_size += 'background-size: ' + $this.data('bg_size') + ';';
            } else {
                $bg_size += 'background-size: ' + bg_size + '; ';
            }
            
            // Background Size.

            var $bg_attachment = "";

            if ($this.is('[data-bg_attachment]')) {
                $bg_attachment += 'background-attachment: ' + $this.data('bg_attachment') + ';';
            } else {
                $bg_attachment += 'background-attachment: ' + bg_attachment + '; ';
            }

            // Background Overflow.

            var $bg_overflow = "";

            if ($this.is('[data-bg_overflow]')) {
                $bg_overflow += 'overflow: ' + $this.data('bg_overflow') + ';';
            } else {
                $bg_overflow += 'overflow: ' + bg_overflow + '; ';
            }

            $this.attr("style", "background:linear-gradient( " + $color_overlay + ",  " + $color_overlay_2 + " )" + bg_img + "; " + $bg_position + " " + $bg_repeat + " " + $bg_attachment + " " + $bg_size + " " + $bg_overflow + "");

        });

    }
    
    
        // HIGHLIGHT CAROUSEL
    
    if ($(".highlight-carousel").length) {
        var $highlight_carousel = $('.highlight-carousel');
        $highlight_carousel.each(function () {
            var $this = $(this);

            var items_val = 3,
                    nav_val = true,
                    dots_val = true,
                    autoplay_val = true,
                    autoplaytimeout_val = 5000;
            // Status.
            if ($this.attr('data-carousel') && $this.data('carousel') !== 1) {

                $this.removeClass('owl-carousel');
                return '';
            }
            // no of items
            if ($this.attr('data-items') && !isNaN($this.data('items'))) {
                items_val = $this.data('items');
            }
            // navigation status.
            if ($this.attr('data-nav') && !isNaN($this.data('nav'))) {
                nav_val = $this.data('nav');
            }

            // navigation status.
            if ($this.attr('data-dots') && !isNaN($this.data('dots'))) {
                dots_val = $this.data('dots');
            }
            // Autoplay status.
            if ($this.attr('data-autoplay') && !isNaN($this.data('autoplay'))) {
                autoplay_val = $this.data('autoplay');
            }
            // Autoplay status.
            if ($this.attr('data-autoplaytimeout') && !isNaN($this.data('autoplaytimeout'))) {
                autoplaytimeout_val = $this.data('autoplaytimeout');
            }

            $this.owlCarousel({
                rtl: rtl_status,
                items: items_val,
                loop: true,
                autoplay: autoplay_val,
                autoplayTimeout: autoplaytimeout_val,
                autoplayHoverPause: true,
                dots: dots_val,
                nav: nav_val,
                navText: ["<i class='fa fa-angle-left'></i>", "<i class='fa fa-angle-right'></i>"],
                responsive: {
                    0: {
                        items: 1,
                        nav: false,
                        dots: false
                    },
                    720: {
                        items: 2,
                        nav: false
                    },
                    1000: {
                        items: items_val
                    }
                }
            });
        });
    } 
    
    // SERVICE.
    
      if ($(".service-carousel").length) {
        var $service_carousel = $('.service-carousel');
        $service_carousel.each(function () {
            var $this = $(this);
            
            var items_val = 3,
                   sm_items_val = 1, 
                    nav_val = true,
                    dots_val = true,
                    autoplay_val = true,
                    autoplaytimeout_val = 5000;
            // Status.
            if ($this.attr('data-carousel') && $this.data('carousel') !== 1) {
                
                $this.removeClass('owl-carousel');
                return '';
            }
            // no of items
            if ($this.attr('data-items') && !isNaN($this.data('items'))) {
                items_val = $this.data('items');
            }
            
            // no of items in small screen.
            if ($this.attr('data-sm-items') && !isNaN($this.data('sm-items'))) {
                sm_items_val = $this.data('sm-items');
            }
            
            // navigation status.
            if ($this.attr('data-nav') && !isNaN($this.data('nav'))) {
                nav_val = $this.data('nav');
            }
            
            // navigation status.
            if ($this.attr('data-dots') && !isNaN($this.data('dots'))) {
                dots_val = $this.data('dots');
            }
            // Autoplay status.
            if ($this.attr('data-autoplay') && !isNaN($this.data('autoplay'))) {
                autoplay_val = $this.data('autoplay');
            }
            // Autoplay status.
            if ($this.attr('data-autoplaytimeout') && !isNaN($this.data('autoplaytimeout'))) {
                autoplaytimeout_val = $this.data('autoplaytimeout');
            }
            
            $this.owlCarousel({
                rtl: rtl_status,
                items: items_val,
                loop: true,
                autoplay: autoplay_val,
                autoplayTimeout: autoplaytimeout_val,
                autoplayHoverPause: true,
                dots: dots_val,
                nav: nav_val,
                navText: ["<i class='fa fa-angle-left'></i>", "<i class='fa fa-angle-right'></i>"],
                responsive: {
                    0: {
                        items: sm_items_val,
                        nav: false
                    },
                    600: {
                        items: 2,
                        nav: false
                    },
                    1000: {
                        items: items_val
                    }
                }
            });
        });
    } 
    
    // GALLERY.
    
    if ( $('.gallery-light-box').length ) {
            
        $('.gallery-light-box').venobox();
        
    }
    
    // FITERABLE GALLERY.
    
    $(".filter-button").on("click",function () {
        var value = $(this).attr('data-filter');

        if (value === "all")
        {
            //$('.filter').removeClass('hidden');
            $('.filter').show('1000');
        }
        else
        {
//            $('.filter[filter-item="'+value+'"]').removeClass('hidden');
//            $(".filter").not('.filter[filter-item="'+value+'"]').addClass('hidden');
            $(".filter").not('.' + value).hide('3000');
            $('.filter').filter('.' + value).show('3000');

        }
    });
    
     // GALLERY CAROUSEL   
    
    if ($(".gallery-carousel").length ) {
        
        var $gallery_carousel = $('.gallery-carousel');
        
        $gallery_carousel.each(function () {

            var $this = $(this);
            
            var items_val = 5,
                    nav_val = false,
                    dots_val = true,
                    autoplay_val = true,
                    autoplaytimeout_val = 5000;


            // Status.
            if ($this.attr('data-carousel') && $this.data('carousel') !== 1) {
                
                $this.removeClass('owl-carousel');
                return '';
            }

            // no of items

            if ($this.attr('data-items') && !isNaN($this.data('items'))) {

                items_val = $this.data('items');
            }

            // navigation status.

            if ($this.attr('data-nav') && !isNaN($this.data('nav'))) {

                nav_val = $this.data('nav');

            }
            
            // navigation status.


            if ($this.attr('data-dots') && !isNaN($this.data('dots'))) {

                dots_val = $this.data('dots');

            }

            // Autoplay status.

            if ($this.attr('data-autoplay') && !isNaN($this.data('autoplay'))) {

                autoplay_val = $this.data('autoplay');
            }

            // Autoplay status.

            if ($this.attr('data-autoplaytimeout') && !isNaN($this.data('autoplaytimeout'))) {

                autoplaytimeout_val = $this.data('autoplaytimeout');
            }
            

            $this.owlCarousel({
                rtl: rtl_status,
                items: items_val,
                loop: true,
                autoplay: autoplay_val,
                autoplayTimeout: autoplaytimeout_val,
                autoplayHoverPause: true,
                dots: dots_val,
                nav: nav_val,
                navText: ["<i class='fa fa-angle-left'></i>", "<i class='fa fa-angle-right'></i>"],
                responsive: {
                    0: {
                        items: 2,
                        nav: false
                    },
                    600: {
                        items: 3,
                        nav: false
                    },
                    1000: {
                        items: items_val
                    }
                }
            });

        });
        
    }
    
    // COUNTER

    if ($(".counter").length ) {
        $('.counter').counterUp({
            delay: 10,
            time: 2000
        });
    }

    //CLIENTS LOGOS 
    
    if ($(".logo-items").length ) {
        
        var $logo_items = $('.logo-items');
        
        $logo_items.each(function () {

            var $this = $(this);
            
            var items_val = 6,
                    nav_val = false,
                    dots_val = true,
                    autoplay_val = true,
                    autoplaytimeout_val = 5000;


            // Status.
            if ($this.attr('data-carousel') && $this.data('carousel') !== 1) {
                
                $this.removeClass('owl-carousel');
                return '';
            }

            // no of items

            if ($this.attr('data-items') && !isNaN($this.data('items'))) {

                items_val = $this.data('items');
            }

            // navigation status.

            if ($this.attr('data-nav') && !isNaN($this.data('nav'))) {

                nav_val = $this.data('nav');

            }
            
            // navigation status.


            if ($this.attr('data-dots') && !isNaN($this.data('dots'))) {

                dots_val = $this.data('dots');

            }

            // Autoplay status.

            if ($this.attr('data-autoplay') && !isNaN($this.data('autoplay'))) {

                autoplay_val = $this.data('autoplay');
            }

            // Autoplay status.

            if ($this.attr('data-autoplaytimeout') && !isNaN($this.data('autoplaytimeout'))) {

                autoplaytimeout_val = $this.data('autoplaytimeout');
            }
            

            $this.owlCarousel({
                rtl: rtl_status,
                items: items_val,
                loop: true,
                autoplay: autoplay_val,
                autoplayTimeout: autoplaytimeout_val,
                autoplayHoverPause: true,
                dots: dots_val,
                nav: nav_val,
                navText: ["<i class='fa fa-angle-left'></i>", "<i class='fa fa-angle-right'></i>"],
                responsive: {
                    0: {
                        items: 2,
                        nav: false
                    },
                    600: {
                        items: 3,
                        nav: false
                    },
                    1000: {
                        items: items_val
                    }
                }
            });

        });
        
    }

    // TESTIMONIAL CAROUSEL.

    if ($(".testimonial-container").length) {

        var $testimonial_container = $('.testimonial-container');

        $testimonial_container.each(function () {

            var $this = $(this);
            
            var items_val = 2,
                    nav_val = false,
                    dots_val = true,
                    autoplay_val = true,
                    autoplaytimeout_val = 5000;


            // Status.
            if ($this.attr('data-carousel') && $this.data('carousel') !== 1) {
                
                $this.removeClass('owl-carousel');
                return '';
            }

            // no of items

            if ($this.attr('data-items') && !isNaN($this.data('items'))) {

                items_val = $this.data('items');
            }

            // navigation status.

            if ($this.attr('data-nav') && !isNaN($this.data('nav'))) {

                nav_val = $this.data('nav');

            }
            
            // navigation status.


            if ($this.attr('data-dots') && !isNaN($this.data('dots'))) {

                dots_val = $this.data('dots');

            }

            // Autoplay status.

            if ($this.attr('data-autoplay') && !isNaN($this.data('autoplay'))) {

                autoplay_val = $this.data('autoplay');
            }

            // Autoplay status.

            if ($this.attr('data-autoplaytimeout') && !isNaN($this.data('autoplaytimeout'))) {

                autoplaytimeout_val = $this.data('autoplaytimeout');
            }
            

            $this.owlCarousel({
                rtl: rtl_status,
                items: items_val,
                loop: true,
                autoplay: autoplay_val,
                autoplayTimeout: autoplaytimeout_val,
                autoplayHoverPause: true,
                dots: dots_val,
                nav: nav_val,
                navText: ["<i class='fa fa-angle-left'></i>", "<i class='fa fa-angle-right'></i>"],
                responsive: {
                    0: {
                        items: 1,
                        nav: nav_val,
                        dots: dots_val
                    },
                    600: {
                        items: 1,
                        nav: nav_val,
                        dots: dots_val
                    },
                    1000: {
                        items: items_val,
                        nav: nav_val,
                        dots: dots_val
                    }
                }
            });

        });


    } 
    
    // TEAM CAROUSEL.

    if ($(".team-carousel").length) {

        var $team_carousel = $('.team-carousel');

        $team_carousel.each(function () {

            var $this = $(this);
            
            var items_val = 3,
                    nav_val = false,
                    dots_val = true,
                    autoplay_val = true,
                    autoplaytimeout_val = 5000;


            // Status.
            if ($this.attr('data-carousel') && $this.data('carousel') !== 1) {
                
                $this.removeClass('owl-carousel');
                return '';
            }

            // no of items

            if ($this.attr('data-items') && !isNaN($this.data('items'))) {

                items_val = $this.data('items');
            }

            // navigation status.

            if ($this.attr('data-nav') && !isNaN($this.data('nav'))) {

                nav_val = $this.data('nav');

            }
            
            // navigation status.


            if ($this.attr('data-dots') && !isNaN($this.data('dots'))) {

                dots_val = $this.data('dots');

            }

            // Autoplay status.

            if ($this.attr('data-autoplay') && !isNaN($this.data('autoplay'))) {

                autoplay_val = $this.data('autoplay');
            }

            // Autoplay status.

            if ($this.attr('data-autoplaytimeout') && !isNaN($this.data('autoplaytimeout'))) {

                autoplaytimeout_val = $this.data('autoplaytimeout');
            }
            

            $this.owlCarousel({
                rtl: rtl_status,
                items: items_val,
                loop: true,
                autoplay: autoplay_val,
                autoplayTimeout: autoplaytimeout_val,
                autoplayHoverPause: true,
                dots: dots_val,
                nav: nav_val,
                navText: ["<i class='fa fa-angle-left'></i>", "<i class='fa fa-angle-right'></i>"],
                responsive: {
                    0: {
                        items: 1,
                        nav: false
                    },
                    600: {
                        items: 1,
                        nav: false
                    },
                    1000: {
                        items: items_val
                    }
                }
            });

        });

    } 
    
    //LATEST NEWS

      if ($("#latest_news").length) {

        $("#latest_news").owlCarousel({
            items: 3,
            loop: true,
            autoplay: true,
            autoplayTimeout: 4000,
            autoplayHoverPause: true,
            responsive: {
                0: {
                    items: 1,
                    nav: true
                },
                600: {
                    items: 2,
                    nav: false
                },
                1000: {
                    items: 2,
                    nav: true,
                    loop: true
                }
            },
            nav: true,
            navText: ["<i class='fa fa-angle-left'></i>", "<i class='fa fa-angle-right'></i>"]
        });
    }     

    // GOOGLE MAP FOR CONTACT & EVENT PAGE.

    if ($('#map_canvas').length) {

        var map;

        $('#map_canvas').css({
            'height': '400px'
        });

        map = new GMaps({
            div: '#map_canvas',
            lat: -12.043333,
            lng: -77.028333
        });
        
        map.addMarker({
            lat: -12.043333,
            lng: -77.028333,
            title: 'Lima',
            click: function (e) {
                alert('You clicked in this marker');
            }
        });

    }
    
    // VENOBOX VIDEO.
    
       $(document).ready(function(){
            $('.venobox').venobox();   
        });  

    // COUNTDOWN.
    
    if( $('#coming_countdown').length > 0 ) {
    
        $('#coming_countdown').countdown('2020/10/10', function (event) {
            var $this = $(this).html(event.strftime(''
                    + '<div class="countdown_container"><span class="count_number">%d</span><span class="count_text">Days</span></div>'
                    + '<div class="countdown_container"><span class="count_number">%H</span><span class="count_text">Hours</span></div>'
                    + '<div class="countdown_container"><span class="count_number">%M</span><span class="count_text">Miniutes</span></div>'
                    + '<div class="countdown_container"><span class="count_number">%S</span><span class="count_text">Seconds</span></div>'));
        });

    }      
    
    
    // CONTACT FORM 
    
    function email_checkRegexp( o, regexp ) {

        if (!(regexp.test(o.val()))) {

            return false;

        } else {

            return true;

        }

    }
        
    
    if($('#contact-form').length) {
 
        var $contact_form = $("#contact-form");
        
        var $contact_submit_btn = $contact_form.find("button.btn-custom");
        var $user_name = $contact_form.find("#user_name");
        var $user_email = $contact_form.find("#user_email");
        var $location_city = $contact_form.find("#location_city");
        var $starting_date = $contact_form.find("#starting_date");
        var $budget = $contact_form.find("#budget");
        var $email_subject = $contact_form.find("#email_subject");
        var $email_message = $contact_form.find("#email_message");
        
        var emailRegex = /^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
        
        var $all_fields = $([]).add($user_name).add($user_email).add($location_city).add($starting_date).add($budget).add($email_subject).add($email_message);

        var $contact_feedback = $contact_form.find("#contact-form-feedback");
        if (!$contact_feedback.length) {
            $contact_submit_btn.closest(".form-group").after(
                '<div id="contact-form-feedback" class="home-contact-form-feedback" role="status" aria-live="polite"></div>'
            );
            $contact_feedback = $contact_form.find("#contact-form-feedback");
        }

        function contactClearFeedback() {
            $contact_feedback.empty();
        }

        function contactDismissibleAlert(type, messageText) {
            var $box = $(
                "<div class=\"alert alert-" +
                    type +
                    " alert-dismissible fade in home-contact-form-alert\" role=\"alert\"></div>"
            );
            $box.append(
                "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\"><span aria-hidden=\"true\">&times;</span></button>"
            );
            $box.append($("<span></span>").text(messageText));
            contactClearFeedback();
            $contact_feedback.append($box);
        }

        $all_fields.val("");
        
        var $error_border =  "border-bottom: 1px solid red;";
        var contact_form_bValid, user_name_bValid, user_email_bValid, location_city_bValid, user_email_subject_bValid, user_email_message_bValid;
        
        
        $contact_form.find("button[type=submit]").on("click", function() {

                contactClearFeedback();
                contact_form_bValid = true;
            
                if( $user_name.val() === "" ) {
                    
                    user_name_bValid = false;
                    $user_name.next("span").remove();
                    $user_name.attr("style", $error_border).after("<span class='error'>" + $user_name.attr("data-msg") + "</span>");
                    
                } else {
                    user_name_bValid = true;
                    $user_name.removeAttr("style").next("span").remove();
                    
                }
                
                 contact_form_bValid = contact_form_bValid && user_name_bValid;
            
            
                if( $user_email.val() === ""  || email_checkRegexp( $user_email , emailRegex) === false  ) {
                    
                    user_email_bValid = false;
                    $user_email.next("span").remove();
                    $user_email.attr("style", $error_border).after("<span class='error'>" + $user_email.attr("data-msg") + "</span>");
                    
                } else {
                     user_email_bValid = true;
                    $user_email.removeAttr("style").next("span").remove();
                    
                }
                
                contact_form_bValid = contact_form_bValid && user_email_bValid;

                if ($location_city.length) {
                    if ($location_city.val() === "") {
                        location_city_bValid = false;
                        $location_city.next("span").remove();
                        $location_city.attr("style", $error_border).after("<span class='error'>" + $location_city.attr("data-msg") + "</span>");
                    } else {
                        location_city_bValid = true;
                        $location_city.removeAttr("style").next("span").remove();
                    }
                    contact_form_bValid = contact_form_bValid && location_city_bValid;
                }

                
                
                if( $email_subject.val() === "" ) {
                    
                    user_email_subject_bValid = false;
                    $email_subject.next("span").remove();
                    $email_subject.attr("style", $error_border).after("<span class='error'>" + $email_subject.attr("data-msg") + "</span>");
                    
                } else {
                    user_email_subject_bValid = true;
                    $email_subject.removeAttr("style").next("span").remove();
                }
                
                contact_form_bValid = contact_form_bValid && user_email_subject_bValid;
                
                if( $email_message.val() === "" ) {
                    
                    user_email_message_bValid = false;
                    $email_message.next("span").remove();
                    $email_message.attr("style", $error_border).after("<span class='error'>" + $email_message.attr("data-msg") + "</span>");
                    
                } else {
                    user_email_message_bValid = true;
                    $email_message.removeAttr("style").next("span").remove();
                    
                }
                
                contact_form_bValid = contact_form_bValid && user_email_message_bValid;
            
                if ( contact_form_bValid === true ) {

                    $all_fields.attr("disabled", "disabled");
                    $contact_submit_btn.attr("disabled", "disabled");
                    contactClearFeedback();
                    $contact_feedback.append(
                        "<div class=\"alert alert-info fade in home-contact-form-alert home-contact-form-alert-wait\" role=\"status\">Sending…</div>"
                    );
                    
                    var contactPostUrl = $('meta[name="contact-post-url"]').attr("content");
                    if (!contactPostUrl) {
                        contactPostUrl = "contact_email.php";
                    }
                    var csrfToken = $('meta[name="csrf-token"]').attr("content");
                    var ajaxHeaders = {};
                    if (csrfToken) {
                        ajaxHeaders["X-CSRF-TOKEN"] = csrfToken;
                    }

                    $.ajax({
                    url: contactPostUrl,
                    type: 'POST',
                    dataType: 'JSON',
                    headers: ajaxHeaders,
                    data: (function () {
                        var payload = {
                            safety_key: 'dynatf',
                            user_name: $user_name.val(),
                            user_email: $user_email.val(),
                            email_subject: $email_subject.val(),
                            email_message: $email_message.val()
                        };
                        if ($location_city.length) {
                            payload.location_city = $location_city.val();
                        }
                        if ($starting_date.length) {
                            payload.starting_date = $starting_date.val();
                        }
                        if ($budget.length) {
                            payload.budget = $budget.val();
                        }
                        return payload;
                    })(),
                    success: function (data) {

                        if ( data.status === 1) {

                            contactDismissibleAlert("success", data.msg || "Thank you! Your message has been sent.");
                            $all_fields.removeAttr("disabled").val("");
                            $contact_submit_btn.removeAttr("disabled");

                        } else {
                            $all_fields.removeAttr("disabled");
                            $contact_submit_btn.removeAttr("disabled");
                            contactDismissibleAlert("danger", data.msg || "Something went wrong.");
                        }

                    },
                    error: function (xhr) {
                        var msg = "Email can not be sent. Please try again.";
                        if (xhr.responseJSON && xhr.responseJSON.msg) {
                            msg = xhr.responseJSON.msg;
                        }
                        $all_fields.removeAttr("disabled");
                        $contact_submit_btn.removeAttr("disabled");
                        contactDismissibleAlert("danger", msg);
                    }

                });
                    
                    
                }
            
            return false;
            
        })
        
    }

    function zr_refresh_testimonial_carousel() {
        $(".testimonial-container.owl-carousel").each(function () {
            var $carousel = $(this);
            if ($carousel.data("owl.carousel")) {
                $carousel.trigger("refresh.owl.carousel");
            }
        });
    }

    //WoW Animation.
    animations();
    setTimeout(zr_refresh_testimonial_carousel, 0);

    //One Page Scrolling.
    smooth_scrolling();

    $(document).on("click", "a.nav_menu", function () {
        if ($(".navbar-toggle").is(":visible") && $(".navbar-collapse").hasClass("in")) {
            $(".navbar-collapse").collapse("hide");
        }
    });


    // BACK TO TOP BUTTON.

    if ($('#backTop').length === 1) {

        $('#backTop').backTop({
            'theme': 'custom'
        });
        
    }
    
    // Go to Specific Section After Load
    function zr_on_load_scroll() {

        var urlHash = window.location.href.split("#")[1];

        if (typeof urlHash === "undefined" || urlHash === "") {
            return;
        }

        var $target = $("#" + urlHash);
        if (!$target.length) {
            $target = $('[name="' + urlHash.replace(/"/g, "") + '"]');
        }
        if (!$target.length) {
            $target = $("." + urlHash);
        }

        if ($target.length) {
            var $first = $target.first();
            var pos = $first.offset();
            if (!pos) {
                return;
            }
            var top = pos.top - zr_one_page_scroll_offset($first);
            if (top < 0) {
                top = 0;
            }
            $("html,body").animate({
                scrollTop: top
            }, 1000);
        }

    }

    // PRELOADER

    $(window).on("load", function () {

         if ($("#preloader").length) {

            $("#preloader").fadeOut(500);

        }
        zr_on_load_scroll();
        zr_refresh_testimonial_carousel();
    });

    // Latest projects gallery (Bootstrap 3 modal + lightweight translate3d track)
    function zr_project_gallery_modal() {
        var $modal = $('#latestProjectGalleryModal');
        if (!$modal.length) {
            return;
        }
        var $track = $('#projectGalleryTrack');
        var $viewport = $track.parent();
        var $dots = $('#projectGalleryDots');
        var $title = $('#latestProjectGalleryModalLabel');
        var $prev = $('#projectGalleryPrev');
        var $next = $('#projectGalleryNext');
        var urls = [];
        var index = 0;
        var touchStartX = null;

        function updateTransform() {
            var w = $viewport.outerWidth();
            if (!w) {
                return;
            }
            $track.css('transform', 'translate3d(' + (-index * w) + 'px,0,0)');
            $dots.find('li').removeClass('active').eq(index).addClass('active');
        }

        function go(delta) {
            if (urls.length <= 1) {
                return;
            }
            index = (index + delta + urls.length) % urls.length;
            updateTransform();
        }

        function isSafeProjectImageUrl(src) {
            if (typeof src !== 'string') {
                return false;
            }
            var s = src.trim();
            if (!s || /[\0\r\n<>]|javascript:/i.test(s)) {
                return false;
            }
            if (/^https?:\/\//i.test(s)) {
                return true;
            }
            return s.charAt(0) === '/' && s.charAt(1) !== '/';
        }

        function buildFromUrls(list, title) {
            urls = list.slice();
            index = 0;
            $track.empty();
            $dots.empty();
            $title.text(title || '');

            $.each(list, function (i, src) {
                var $slide = $('<div class="project-gallery-slide"/>');
                var alt = (title || 'Project') + ' — photo ' + (i + 1);
                var imgAttrs = {
                    alt: alt,
                    decoding: 'async',
                    src: src
                };
                if (i > 0) {
                    imgAttrs.loading = 'lazy';
                }
                var $img = $('<img/>').attr(imgAttrs);
                $slide.append($img);
                $track.append($slide);

                var $li = $('<li/>').attr({
                    role: 'button',
                    tabindex: 0,
                    'aria-label': 'Photo ' + (i + 1)
                });
                if (i === 0) {
                    $li.addClass('active');
                }
                $li.on('click keydown', function (e) {
                    if (e.type === 'keydown' && e.which !== 13 && e.which !== 32) {
                        return;
                    }
                    e.preventDefault();
                    index = i;
                    updateTransform();
                });
                $dots.append($li);
            });

            if (urls.length <= 1) {
                $modal.addClass('project-gallery-single');
                $dots.hide();
                $prev.hide();
                $next.hide();
            } else {
                $modal.removeClass('project-gallery-single');
                $dots.show();
                $prev.show();
                $next.show();
            }

            updateTransform();
        }

        $(document).on('click', '.js-project-gallery', function (e) {
            e.preventDefault();
            var $a = $(this);
            var list = $a.data('gallery');
            if (!$.isArray(list) || !list.length) {
                try {
                    list = JSON.parse($a.attr('data-gallery') || '[]');
                } catch (ignore) {
                    list = [];
                }
            }
            if (!$.isArray(list) || !list.length) {
                return;
            }
            var filtered = $.grep(list, function (s) {
                return isSafeProjectImageUrl(s);
            });
            if (!filtered.length) {
                return;
            }
            var projTitle = $a.attr('data-project-title');
            buildFromUrls(filtered, projTitle || '');
            $modal.modal('show');
        });

        $prev.on('click', function () {
            go(-1);
        });
        $next.on('click', function () {
            go(1);
        });

        $viewport.on('touchstart.projectGallery', function (e) {
            touchStartX = e.originalEvent.touches[0].clientX;
        });
        $viewport.on('touchend.projectGallery', function (e) {
            if (touchStartX === null) {
                return;
            }
            var dx = e.originalEvent.changedTouches[0].clientX - touchStartX;
            touchStartX = null;
            if (Math.abs(dx) < 48) {
                return;
            }
            go(dx > 0 ? -1 : 1);
        });

        $(window).on('resize.projectGalleryModal', function () {
            if ($modal.hasClass('in')) {
                updateTransform();
            }
        });

        $modal.on('shown.bs.modal', function () {
            updateTransform();
            $(document).on('keydown.projectGallery', function (e) {
                if (e.keyCode === 37) {
                    go(-1);
                }
                if (e.keyCode === 39) {
                    go(1);
                }
            });
        });

        $modal.on('hidden.bs.modal', function () {
            $(document).off('keydown.projectGallery');
            $track.empty();
            $dots.empty();
            urls = [];
        });
    }

    zr_project_gallery_modal();

    function zr_latest_projects_filter() {
        var $filters = $('.latest-projects-filters');
        if (!$filters.length) {
            return;
        }

        $filters.on('click', '.latest-project-filter-btn', function () {
            var $btn = $(this);
            var filter = $btn.attr('data-filter') || 'all';

            $filters.find('.latest-project-filter-btn').removeClass('is-active').attr('aria-pressed', 'false');
            $btn.addClass('is-active').attr('aria-pressed', 'true');

            $('.js-latest-project-item').each(function () {
                var slug = $(this).attr('data-category-slug') || '';
                $(this).toggle(filter === 'all' || slug === filter);
            });
        });
    }

    zr_latest_projects_filter();

});