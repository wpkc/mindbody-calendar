/**
 * AJAX calendar navigation code
 *
 * All contents (c)2016 Kazimer Corp.
 * Removal in part or in whole of this copyright notice is not allowed.
 * Selling this code or any derivative works is a violation of copyright law.
 * Kazimer Corp and its employees will be held harmless should any loss occur while using this code.
 * Use of this code constitutes full agreement with all these terms.
 *
 *  http://www.kazimer.com
 *
 *  @author     Kazimer Corp
 *  @copyright  (c) 2016 - Kazimer Corp
 *  @version    1.0.0
 *
 *  @package WordPress
 *
 */
(function ($) {
    $('body').on('click', '#calendar-content div.month a', function () {
        $('body *').css('cursor', 'progress');
        $('#calendar-content').css('opacity', 0.5);
        var data = $(this).attr('data').split('_');
        $.post(cal_ajax.ajaxurl, {
            action: cal_ajax.action,
            timstamp: data[0],
            _nonce: data[1]},
        function (data) {
            $('body *').css('cursor', 'default');
            if (data && data.length > 100) {
                $('div.entry-content').html(data);
            }
            $('#calendar-content').css('opacity', 1.0);
        });
    });
})(jQuery);