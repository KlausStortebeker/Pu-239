var v_offset = 250;
var animate_duration = 750;
var easing = 'swing';
var w = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
var h = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);

function SelectAll(id) {
    document.getElementById(id).focus();
    document.getElementById(id).select();
}

function togglepic(bu, picid, formid) {
    var pic = document.getElementById(picid);
    var form = document.getElementById(formid);
    if (pic.src === bu + '/images/plus.gif') {
        pic.src = bu + '/images/minus.gif';
        form.value = 'minus';
    } else {
        pic.src = bu + '/images/plus.gif';
        form.value = 'plus';
    }
}

$('.delete').on('click', function () {
    $(this).parent().fadeTo(animate_duration, .01, function () {
        $(this).slideUp(125, function () {
            $(this).remove();
        });
    });
});

function SmileIT(smile, form, text) {
    document.forms[form].elements[text].value = document.forms[form].elements[text].value + ' ' + smile + ' ';
    document.forms[form].elements[text].focus();
}

function refrClock() {
    var d = new Date();
    var s = d.getSeconds();
    var m = d.getMinutes();
    var h = d.getHours();
    var am_pm;
    if (s < 10) {
        s = '0' + s;
    }
    if (m < 10) {
        m = '0' + m;
    }
    if (is_12_hour === 'yes') {
        if (h > 12) {
            h -= 12;
            am_pm = ' pm';
        } else {
            am_pm = ' am';
        }
    } else {
        if (h < 10) {
            h = '0' + h;
            am_pm = '';
        } else {
            am_pm = '';
        }
    }
    document.getElementById('clock').innerHTML = h + ':' + m + ':' + s + am_pm;
    setTimeout('refrClock()', 1e3);
}

$('.mlike').on('click', function () {
    $.ajax({
        url: './ajax/like.php',
        type: 'POST',
        dataType: 'json',
        context: this,
        data: {
            type: this.dataset.type,
            id: this.dataset.id,
            current: $(this).html()
        },
        success: function (data) {
            $(this).html(data['label']);
            $('.' + data['class']).html(data['list']);
        }
    });
});

function do_rate(rate, id, what) {
    $.ajax({
        url: './ajax/rating.php',
        type: 'POST',
        data: {
            rate: rate,
            id: id,
            what: what
        },
        success: function (data) {
            var el = $('#rated');
            el.addClass('star-ratings-css').removeClass('rating');
            el.html(data);
            $('.star-ratings-css-top').tooltipster({
                theme: 'tooltipster-borderless',
                side: 'top',
                animation: animation,
                animationDuration: animate_duration,
                arrow: true,
                contentAsHTML: true,
                maxWidth: 500
            });
        }
    });
}

$(function () {
    if ($('#clock').length) {
        refrClock();
    }
    var help_open = $('#help_open');
    if (help_open.length) {
        help_open.click(function () {
            $('#help').slideToggle(animate_duration, easing, function () {
            });
            $('#help_open').hide();
            $('#help_close').show();
        });
    }
    var help_close = $('#help_close');
    if (help_close.length) {
        help_close.click(function () {
            $('#help').slideToggle(animate_duration, easing, function () {
            });
            $('#help_close').hide();
            $('#help_open').show();
        });
    }
    var backtotop = $('.back-to-top');
    $(window).scroll(function () {
        if ($(this).scrollTop() > v_offset) {
            backtotop.fadeIn(animate_duration);
        } else {
            backtotop.fadeOut(animate_duration);
        }
    });
    backtotop.click(function (event) {
        event.preventDefault();
        $('html, body').animate({
            scrollTop: 0
        }, animate_duration, easing);
        backtotop.blur();
        return false;
    });
    $('.flip').click(function (e) {
        $(this).next().slideToggle(animate_duration, easing, function () {
        });
    });
    if ($('#carousel-container').length) {
        $('#icarousel').iCarousel({
            easing: 'ease-in-out',
            slides: 11,
            make3D: 1,
            perspective: 400,
            animationSpeed: animate_duration,
            pauseTime: 5e3,
            startSlide: 1,
            directionNav: 1,
            autoPlay: 1,
            keyboardNav: 0,
            touchNav: 1,
            mouseWheel: 0,
            pauseOnHover: 1,
            nextLabel: 'Next',
            previousLabel: 'Previous',
            playLabel: 'Play',
            pauseLabel: 'Pause',
            randomStart: 1,
            slidesSpace: '225',
            slidesTopSpace: 'auto',
            direction: 'rtl',
            timer: '360bar',
            timerBg: '#000',
            timerColor: '#0f0',
            timerOpacity: .4,
            timerDiameter: 35,
            timerPadding: 4,
            timerStroke: 3,
            timerBarStroke: 1,
            timerBarStrokeColor: '#0f0',
            timerBarStrokeStyle: 'solid',
            timerBarStrokeRadius: 4,
            timerPosition: 'top-right',
            timerX: 10,
            timerY: 10
        });
    }
    var ie_alert = $('#IE_ALERT');
    if (ie_alert.length) {
        if (navigator.userAgent.search('MSIE') >= 0) {
            ie_alert.slideToggle(animate_duration, easing, function () {
            });
        }
    }
    var menuwrapper = $('#menuWrapper');
    $(window).resize(function () {
        var windowWidth = $(window).width();
        if (windowWidth > 768) {
            menuwrapper.show();
        }
    });
    var navbar = $('#navbar');
    $('#hamburger').click(function (event) {
        event.preventDefault();
        navbar.addClass('showNav');
        var winHeight = $(window).outerHeight();
        menuwrapper.css('height', winHeight + 'px');
        menuwrapper.slideToggle(250, easing, function () {
        });
    });
    $('#close').click(function (event) {
        event.preventDefault();
        menuwrapper.slideToggle(animate_duration, easing, function () {
            navbar.removeClass('showNav');
            menuwrapper.css('height', 'auto');
        });
    });
    menuwrapper.find('ul li').hover(function () {
        var el = $(this).children('ul');
        if (el.hasClass('hov')) {
            $(el).removeClass('hov');
        } else {
            $(el).addClass('hov');
        }
    });
    if ($('.content').length) {
        $('.h1').click(function () {
            $('.content').slideToggle(animate_duration, easing, function () {
            });
        });
    }
    var tzcheckdst = $('#tz-checkdst');
    if (tzcheckdst.length) {
        if (!tzcheckdst.is(':checked')) {
            $('#tz-checkmanual').show();
        }
    }
    tzcheckdst.click(function () {
        $('#tz-checkmanual').slideToggle(animate_duration, easing, function () {
        });
    });
    $('li a[href=".' + this.location.pathname + this.location.search + '"]').addClass('is_active');
    var checkThemAll = $('#checkThemAll');
    if (checkThemAll.length) checkThemAll.change(function () {
        $('input:checkbox').prop('checked', $(this).prop('checked'));
    });
    var checkAll = $('#checkAll');
    var checkbox_container = $('#checkbox_container');
    if (checkAll.length) {
        checkAll.change(function () {
            checkbox_container.find(':checkbox').prop('checked', $(this).prop('checked'));
        });
        if (checkbox_container.find(':checkbox:checked').length === checkbox_container.find(':checkbox').length) {
            checkAll.prop('checked', true);
        }
        checkbox_container.find(':checkbox').click(function () {
            if (checkbox_container.find(':checkbox:checked').length === checkbox_container.find(':checkbox').length) {
                checkAll.prop('checked', true);
            } else {
                checkAll.prop('checked', false);
            }
        });
    }
    var defcat = $('#defcat');
    if (defcat.length) {
        $('#cat_open').click(function () {
            $('#defcat').slideToggle(animate_duration, function () {
            });
        });
    }
    var notification = $('.notification');
    if (notification.length) {
        setTimeout(function () {
            notification.fadeTo(animate_duration, .01, function () {
                notification.slideUp(125, function () {
                    notification.remove();
                });
            });
        }, 15e3);
    }
    var accordion = $('#accordion');
    if (accordion.length) {
        accordion.find('.accordion-toggle').click(function () {
            $(this).next().slideToggle(animate_duration);
            $('.accordion-content').not($(this).next()).slideUp(animate_duration);
        });
    }
    $('a[href^=\\#]:not([href=\\#])').click(function (e) {
        if (location.pathname.replace(/^\//, '') === this.pathname.replace(/^\//, '') || location.hostname === this.hostname) {
            e.preventDefault();
            var headerHeight = navbar.outerHeight() + 10;
            var target = $(this).attr('href');
            var scrollToPosition = $(target).offset().top - headerHeight;
            $('html, body').animate({
                scrollTop: scrollToPosition
            }, animate_duration, function () {
                window.location.hash = '' + target;
                $('html, body').animate({
                    scrollTop: scrollToPosition
                }, 0);
            });
        }
    });
    if (window.location.hash) {
        var headerHeight = navbar.outerHeight() + 10;
        var scrollToPosition = $(window.location.hash).offset().top - headerHeight;
        $('html, body').animate({
            scrollTop: scrollToPosition
        }, animate_duration, 'swing');
    }

    if (w >= h && typeof body_image !== 'undefined' && document.body.contains(document.getElementById('body-overlay'))) {
        document.getElementsByTagName('body')[0].style.backgroundColor = 'black';
        document.getElementsByTagName('body')[0].style.backgroundImage = 'url(' + body_image + ')';
        document.getElementsByTagName('body')[0].style.backgroundSize = 'cover';
    }

    var iframe_ajaxchat = document.getElementById('iframe_ajaxchat');
    if (iframe_ajaxchat) {
        iframe_ajaxchat.style.height = chat_height;
    }
});


var ojvid = $('.object-fit-video');
var vidWidth = ojvid.width();
ojvid.height(vidWidth * .184);
ojvid.css('visibility', 'visible');

document.addEventListener('DOMContentLoaded', function () {
    yall({
        observeChanges: true
    });
});
