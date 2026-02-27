(function ($) {

	var Cookies = (function () {
		// Von MDN übernommen
		var MdnCookies = {
			getItem: function (sKey) {
				if (!sKey || !this.hasItem(sKey)) { return null; }
			var escapedKey = encodeURIComponent(sKey).replace(/[.*+?^${}()|[\]\\-]/g, "\\$&");
			return decodeURIComponent(document.cookie.replace(new RegExp("(?:(?:^|.*;)\\s*" + escapedKey + "\\s*\\=\\s*([^;]*).*$)|^.*$"), "$1"));
			setItem: function (sKey, sValue, vEnd, sPath, sDomain, bSecure) {
				if (!sKey || /^(?:expires|max\-age|path|domain|secure)$/i.test(sKey)) { return; }
				var sExpires = "";
				if (vEnd) {
					switch (typeof vEnd) {
						case "number":
							sExpires = vEnd === Infinity ? "; expires=Fri, 31 Dec 9999 23:59:59 GMT" : "; max-age=" + vEnd;
							break;
						case "string":
							sExpires = "; expires=" + vEnd;
							break;
						case "object":
							if (vEnd.hasOwnProperty("toGMTString")) {
								sExpires = "; expires=" + vEnd.toGMTString();
							}
							break;
					}
				}
				document.cookie = encodeURIComponent(sKey) + "=" + encodeURIComponent(sValue) + sExpires + (sDomain ? "; domain=" + sDomain : "") + (sPath ? "; path=" + sPath : "") + (bSecure ? "; secure" : "");
			},
	
			removeItem: function (sKey, sPath) {
				if (!sKey || !this.hasItem(sKey)) { return; }
				document.cookie = encodeURIComponent(sKey) + "=; expires=Thu, 01 Jan 1970 00:00:00 GMT" + (sPath ? "; path=" + sPath : "");
			},
	
			hasItem: function (sKey) {
			var escapedKey = encodeURIComponent(sKey).replace(/[.*+?^${}()|[\]\\-]/g, "\\$&");
			return new RegExp("(?:^|;\\s*)" + escapedKey + "\\s*\\=").test(document.cookie);
	
			keys: function () {
				var aKeys = document.cookie.replace(/((?:^|\s*;)[^\=]+)(?=;|$)|^\s*|\s*(?:\=[^;]*)?(?:\1|$)/g, "").split(/\s*(?:\=[^;]*)?;\s*/);
				for (var nIdx = 0; nIdx < aKeys.length; nIdx++) { aKeys[nIdx] = decodeURIComponent(aKeys[nIdx]); }
				return aKeys;
			}
		};
	
		return {
			get: function (key) {
				return MdnCookies.getItem(key);
			},
			set: function (key, value) {
				if (!window._wdsi_data || !window._wdsi_data.reshow) return false;
				var reshow = parseInt(_wdsi_data.reshow.timeout, 10) || 0,
					timeout = new Date((new Date()).getTime() + (reshow * 1000)),
					path = _wdsi_data.reshow.path,
					cookie_name = this.create_page_key(key, value)

				;
				return MdnCookies.setItem(cookie_name, value, timeout, path);
			},
			remove: function (key) {
				return MdnCookies.removeItem(key);
			},
			has: function (key) {
				return MdnCookies.hasItem(key);
			},
			keys: function () {
				return MdnCookies.keys();
			},
			create_page_key: function (key, value) {
				if (!window._wdsi_data || !window._wdsi_data.reshow) return key;
				hide_all = _wdsi_data.reshow.all;
				return hide_all
					? key
					: (key + value.replace(/[^-_a-z0-9]/ig, '_'))
				;
			}
		};
	})();
	
	function register_seen_uri () {
		if (!window._wdsi_data || !window._wdsi_data.reshow) return false;
		var cookie_name = _wdsi_data.reshow.name,
			path = window.location.pathname
		;
		Cookies.set(cookie_name, path);
	}
	
	function cache_cookie_exists () {
		if (!window._wdsi_data || !window._wdsi_data.reshow) return false;
		var cookie_name = _wdsi_data.reshow.name,
			path = window.location.pathname,
			cookie = false
		;
		if (_wdsi_data.reshow.all) { // Check all-cache
			cookie = Cookies.get(cookie_name);
		} else { // Check page chache
			cookie_name = Cookies.create_page_key(cookie_name, path);
			cookie = Cookies.get(cookie_name);
		}
		return cookie;
	}
	
	$(function () {
	
		// Zuerst prüfen, ob wir vom Cache getäuscht wurden
		if (!!cache_cookie_exists()) return false;
	
		// Als nächstes, wenn es sich um Inhalte zu verwandten Beiträgen handelt, die Breite korrigieren
		var $root = $("#wdsi-slide_in"),
			$content = $root.find(".wdsi-slide-content"),
			$related = $root.find(".wdsi-slide-columns"),
			$wrap = $root.find(".wdsi-slide-wrap"),
			$posts = $related.length ? $related.find(".wdsi-slide-col") : []
		;
		if ($related.length && $posts.length) {
			var single_width = $posts.outerWidth(),
				count = $posts.length,
				window_width = $(window).width(),
				padding = (count+1.5) * ($posts.outerWidth() - $posts.width()),
				total_width = (single_width * count) + padding
			;
			if (total_width > window_width) {
				// Wir sind größer als der Bildschirm, ouch! Zusätzliche Beiträge ausblenden
				var delta = Math.ceil((total_width - window_width) / single_width),
					iter = 0
				;
				count -= delta;
				$($posts.get().reverse()).each(function () {
					// Zusätzliche Beiträge ausblenden
					if (iter >= delta) return false;
					$(this).hide();
					iter++;
				});
			} else {
				// Wir haben weniger Beiträge als wir können - Breite anpassen
				$wrap.width(total_width);
			}
		}
		// Breite festgelegt, weitermachen
	
		var slidein_obj = [];
		var legacy = false;
	
		function css_support( property )
		{
			var div = document.createElement('div');
			var reg = new RegExp("(khtml|moz|ms|webkit|)"+property, "i");
			for ( var s in div.style ) {
				if ( s.match(reg) )
					return true;
			}
			return false;
		}
	
		function calculate_vertical_side( obj ){
			var h = $(obj).innerHeight();
			if ( $(obj).hasClass('wdsi-slide-top') )
				$(obj).css('top', h*-1).data('slidein-pos', h*-1);
			else if ( $(obj).hasClass('wdsi-slide-bottom') )
				$(obj).css('bottom', h*-1).data('slidein-pos', h*-1);
		}
	
		function calculate_horizontal_side( obj ){
			var h = $(obj).innerHeight();
			$(obj).css('margin-top', Math.floor(h/2)*-1);
			var w = $(obj).innerWidth();
			if ( $(obj).hasClass('wdsi-slide-right') )
				$(obj).css('right', w*-1).data('slidein-pos', w*-1);
			else if ( $(obj).hasClass('wdsi-slide-left') )
				$(obj).css('left', w*-1).data('slidein-pos', w*-1);
		}
	
		function slidein_scroll(){
			if ( slidein_obj.length == 0 )
				return; // Kein Schiebeelement vorhanden
			var current_pos = $(window).scrollTop();
			var height = $(document).height()-$(window).height();
			var percentage = current_pos/height*100;
			for ( i = 0; i < slidein_obj.length; i++ ) {
				var obj = slidein_obj[i];
				var start = $(obj).data('slidein-start');
				var end = $(obj).data('slidein-end');
				var len = $(obj).data('slidein-for');
				var start_after = $(obj).data('slidein-after');
				var timeout = $(obj).data('slidein-timeout');
				if ( ! start )
					continue;
				var start_pos = 0;
				var end_pos = height;
				var start_at = 0;
				var end_at = 0;
				// Startposition erhalten
				if ( start.match(/^\d+%$/) )
					start_pos = Math.round( parseInt(start.replace(/%$/, ''), 10)/100*height );
				else if ( $(start).length > 0 )
					start_pos = $(start).offset().top-$(window).height();
				start_pos = ( start_pos < 0 ) ? 0 : start_pos;
				// Endposition erhalten
				if ( end ){
					if ( end.match(/^\d+%$/) )
						end_pos = Math.round( parseInt(end.replace(/%$/, ''), 10)/100*height );
					else if ( $(end).length > 0 )
						end_pos = $(end).offset().top+$(end).height();
				}
				else if ( len && len.match(/^\d+%$/) ){
					end_pos = Math.round( parseInt(len.replace(/%$/, ''), 10)/100*height + start_pos );
				}
				// Startzeit erhalten
				if ( start_after ){
					if ( start_after.match(/^\d+s$/) )
						start_at = Math.round( parseInt(start_after.replace(/s$/, ''), 10) );
					else if ( start_after.match(/^\d+m$/) )
						start_at = Math.round( parseInt(start_after.replace(/m$/, ''), 10)*60 );
					else if ( start_after.match(/^\d+h$/) )
						start_at = Math.round( parseInt(start_after.replace(/h$/, ''), 10)*3600 );
				}
				// Endzeit erhalten
				if ( timeout ){
					if ( timeout.match(/^\d+s$/) )
						end_at = Math.round( parseInt(timeout.replace(/s$/, ''), 10) );
					else if ( timeout.match(/^\d+m$/) )
						end_at = Math.round( parseInt(timeout.replace(/m$/, ''), 10)*60 );
					else if ( timeout.match(/^\d+h$/) )
						end_at = Math.round( parseInt(timeout.replace(/h$/, ''), 10)*3600 );
					end_at += start_at;
				}
				//console.log('current_pos: '+current_pos+', height: '+height+', start: '+start+', start_pos: '+start_pos+', for: '+len+', end: '+end+', end_pos:'+end_pos+', start_at: '+start_at+', end_at: '+end_at);
				if ( $(obj).hasClass('wdsi-slide-active') ) {
					// Prüfen, ob die Endposition erreicht ist
					if (
						(current_pos <= height /* <-- Imbecile Mac-Verhalten erfassen */ && current_pos > end_pos)
						||
						(current_pos >= 0 /* <-- Imbecile Mac-Verhalten erfassen */ && current_pos < start_pos)
					)
						slidein_hide(obj);
				} else {
					// Prüfen, ob es sich in Position befindet, um anzuzeigen
					if ( current_pos >= start_pos && current_pos <= end_pos ){
						slidein_show(obj, start_at);
						if ( end_at > start_at )
							slidein_hide(obj, end_at);
					}
				}
			}
		}
	
		function slidein_hide(obj, timeout, closed) {
			var $obj = $(obj);
			if ( ! timeout ) timeout = 0;
			if ( closed ) {
				$obj.data('slidein-closed', '1');
			}
			clearTimeout($obj.data('slidein-temp-time-hide'));
			$obj.data( 'slidein-temp-time-hide', setTimeout(function(){
				if ( legacy && $obj.data('slidein-running') != '2' ){
					$obj.data('slidein-running', '2');
					$obj.removeClass('wdsi-slide-active');
					if ( $obj.hasClass('wdsi-slide-top') )
						$obj.stop(true).animate({top: $obj.data('slidein-pos')}, 1000, legacy_hide_after);
					else if ( $obj.hasClass('wdsi-slide-left') )
						$obj.stop(true).animate({left: $obj.data('slidein-pos')}, 1000, legacy_hide_after);
					else if ( $obj.hasClass('wdsi-slide-right') )
						$obj.stop(true).animate({right: $obj.data('slidein-pos')}, 1000, legacy_hide_after);
					else if ( $obj.hasClass('wdsi-slide-bottom') )
						$obj.stop(true).animate({bottom: $obj.data('slidein-pos')}, 1000, legacy_hide_after);
				}
				else {
					$obj.removeClass('wdsi-slide-active');
				}
			}, timeout*1000) );
		}
	
		function slidein_show(obj, timeout) {
			var $obj = $(obj);
			if ( $obj.data('slidein-closed') == '1' )
				return;
			if ( ! timeout )
				timeout = 0;
			clearTimeout($(obj).data('slidein-temp-time-show'));
			$obj.data( 'slidein-temp-time-show', setTimeout(function(){
				if ( legacy && $obj.data('slidein-running') != '1' ){
					$obj.data('slidein-running', '1');
					$obj.css('visibility', 'visible');
					if ( $obj.hasClass('wdsi-slide-top') )
						$obj.stop(true).animate({top: 0}, 1000, legacy_show_after);
					else if ( $obj.hasClass('wdsi-slide-left') )
						$obj.stop(true).animate({left: 0}, 1000, legacy_show_after);
					else if ( $obj.hasClass('wdsi-slide-right') )
						$obj.stop(true).animate({right: 0}, 1000, legacy_show_after);
					else if ( $obj.hasClass('wdsi-slide-bottom') )
						$obj.stop(true).animate({bottom: 0}, 1000, legacy_show_after);
				}
				else {
					$obj.addClass('wdsi-slide-active');
				}
			}, timeout*1000) );
		}
	
		function legacy_hide_after() {
			$(this).css('visibility', 'hidden');
			$(this).data('slidein-running', '0');
		}
	
		function legacy_show_after() {
			$(this).addClass('wdsi-slide-active');
			$(this).data('slidein-running', '0');
		}
	
		// Wenn die Breite auf der Front-End in Pixeln definiert ist, setzen Sie sie zurück, wenn die Fensterbreite < Schieberegler ist und verlassen Sie sich auf 100%
		function responsify( obj ) {
			var $wrap = obj.find('.wdsi-slide-wrap');
			if ( $wrap.length && $wrap.attr('style') && $wrap.attr('style').indexOf('width') >= 0 ) {
				var slidewidth = parseInt( $wrap.attr('style').replace(/\D/g,''), 10 ),
					winwidth = $(window).width()
				;
	
				if ( winwidth <= slidewidth ) {
					$wrap.removeAttr('style');
				}
				$(window).resize(function() {
					if ( $(window).width() <= slidewidth ) {
						$wrap.removeAttr('style');
					} else if (!$wrap.attr('style')) {
						$wrap.width( slidewidth );
					}
				});
			}
		}
	
		if (document.readyState === 'complete') {
			$(document).trigger("wdsi-init");
		} else {
			$(window).on('load', function(){
				$(document).trigger("wdsi-init");
			});
		}
	
		$(document).on('click', '.wdsi-slide-close a', function(e){
			e.preventDefault();
			var obj = $(this).closest('.wdsi-slide');
			slidein_hide(obj, 0, true);
			register_seen_uri();
		});
		$(document).on("wdsi-init", function () {
			// Initialisieren
			$('.wdsi-slide').each(function(){
				var $me = $(this);
				$me.css("display", '');
				if ( $me.is('.wdsi-slide-top, .wdsi-slide-bottom') ) {
					calculate_vertical_side(this);
				}
				if ( $me.is('.wdsi-slide-right, .wdsi-slide-left') ) {
					calculate_horizontal_side(this);
				}
				$me.data('slidein-running', '0');
				slidein_obj.push(this);
				responsify( $me );
			});
			var is_timed = $("#wdsi-slide_in").length
				? !!parseInt($("#wdsi-slide_in").attr("data-slidein-after"), 10)
				: false
			;
			if ( ! css_support('transition') )
				legacy = true;
			$(window).on('scroll', slidein_scroll);
			// Hier zuerst slidein_scroll aufrufen, damit wir nicht auf das Scroll-Ereignis warten müssen, bevor es das Slide-in zeigt :))
			slidein_scroll();
		});
	
	});
	})(jQuery);
	