/**
 * JS library for Multiple Category Filter Widget
 */


/*
 * Chained - jQuery non AJAX(J) chained selects plugin
 *
 * Copyright (c) 2010-2011 Mika Tuupola
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 */

(function($) {

    $.fn.chained = function(parent_selector, options) { 
        
        return this.each(function() {
            
            /* Save this to self because this changes when scope changes. */            
            var self   = this;
            var backup = $(self).clone();
                        
            /* Handles maximum two parents now. */
            $(parent_selector).each(function() {
                                                
				$(this).bind("change", function() {
					$(self).html(backup.html());

					/* If multiple parents build classname like foo\bar. */
					var selected = "";
					//console.log('parent_select = ' + parent_selector);
					
					$(parent_selector).each(function() {
						
						//console.log( jQuery(':selected', this)[0] );
						
						//console.log( parent_selector + ': ' +jQuery(':selected', this)[0] + ' = ' +jQuery(':selected', this).attr('selected') );
						selected += "\\" + $(":selected", this).val();
						
					});
					selected = selected.substr(1);

					/* Also check for first parent without subclassing. */
					/* TODO: This should be dynamic and check for each parent */
					/*       without subclassing. */
					var first = $(parent_selector).first();
					var selected_first = $(":selected", first).val();
                
                    $("option", self).each(function() {
                        /* Remove unneeded items but save the default value. */
                        if (!$(this).hasClass(selected) && 
                            !$(this).hasClass(selected_first) && $(this).val() !== "") {
                                $(this).remove();
                        }
                    });
                
                    /* If we have only the default value disable select. */
                    if (1 == $("option", self).size() && $(self).val() === "") {
                        $(self).attr("disabled", "disabled");
                    } else {
                        $(self).removeAttr("disabled");
                    }
                    $(self).trigger("change");
                });
                
                /* Force IE to see something selected on first page load, */
                /* unless something is already selected */
				if ( !$("option:selected", this).length && $.browser.msie ) {
					$("option", this).first().attr("selected", "selected");
				}
	    
                /* Force updating the children. */
                $(this).trigger("change");             

            });
        });
    };
    
    /* Alias for those who like to use more English like syntax. */
    $.fn.chainedTo = $.fn.chained;
    
})(jQuery);

/**
 * Utility functions, mostly from phpjs.net
 */
 
function str_replace (search, replace, subject, count) {
    // http://kevin.vanzonneveld.net
    // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Gabriel Paderni
    // +   improved by: Philip Peterson
    // +   improved by: Simon Willison (http://simonwillison.net)
    // +    revised by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
    // +   bugfixed by: Anton Ongson
    // +      input by: Onno Marsman
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +    tweaked by: Onno Marsman
    // +      input by: Brett Zamir (http://brett-zamir.me)
    // +   bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   input by: Oleg Eremeev
    // +   improved by: Brett Zamir (http://brett-zamir.me)
    // +   bugfixed by: Oleg Eremeev
    // %          note 1: The count parameter must be passed as a string in order
    // %          note 1:  to find a global variable in which the result will be given
    // *     example 1: str_replace(' ', '.', 'Kevin van Zonneveld');
    // *     returns 1: 'Kevin.van.Zonneveld'
    // *     example 2: str_replace(['{name}', 'l'], ['hello', 'm'], '{name}, lars');
    // *     returns 2: 'hemmo, mars'
    var i = 0,
        j = 0,
        temp = '',
        repl = '',
        sl = 0,
        fl = 0,
        f = [].concat(search),
        r = [].concat(replace),
        s = subject,
        ra = Object.prototype.toString.call(r) === '[object Array]',
        sa = Object.prototype.toString.call(s) === '[object Array]';
    s = [].concat(s);
    if (count) {
        this.window[count] = 0;
    }

    for (i = 0, sl = s.length; i < sl; i++) {
        if (s[i] === '') {
            continue;
        }
        for (j = 0, fl = f.length; j < fl; j++) {
            temp = s[i] + '';
            repl = ra ? (r[j] !== undefined ? r[j] : '') : r[0];
            s[i] = (temp).split(f[j]).join(repl);
            if (count && s[i] !== temp) {
                this.window[count] += (temp.length - s[i].length) / f[j].length;
            }
        }
    }
    return sa ? s : s[0];
}


/*!
 * jQuery Cookie Plugin
 * https://github.com/carhartl/jquery-cookie
 *
 * Copyright 2011, Klaus Hartl
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://www.opensource.org/licenses/mit-license.php
 * http://www.opensource.org/licenses/GPL-2.0
 */
(function($) {
    $.cookie = function(key, value, options) {

        // key and at least value given, set cookie...
        if (arguments.length > 1 && (!/Object/.test(Object.prototype.toString.call(value)) || value === null || value === undefined)) {
            options = $.extend({}, options);

            if (value === null || value === undefined) {
                options.expires = -1;
            }

            if (typeof options.expires === 'number') {
                var days = options.expires, t = options.expires = new Date();
                t.setDate(t.getDate() + days);
            }

            value = String(value);

            return (document.cookie = [
                encodeURIComponent(key), '=', options.raw ? value : encodeURIComponent(value),
                options.expires ? '; expires=' + options.expires.toUTCString() : '', // use expires attribute, max-age is not supported by IE
                options.path    ? '; path=' + options.path : '',
                options.domain  ? '; domain=' + options.domain : '',
                options.secure  ? '; secure' : ''
            ].join(''));
        }

        // key and possibly options given, get cookie...
        options = value || {};
        var decode = options.raw ? function(s) { return s; } : decodeURIComponent;

        var pairs = document.cookie.split('; ');
        for (var i = 0, pair; pair = pairs[i] && pairs[i].split('='); i++) {
            if (decode(pair[0]) === key) return decode(pair[1] || ''); // IE saves cookies with empty string as "c; ", e.g. without "=" as opposed to EOMB, thus pair[1] may be undefined
        }
        return null;
    };
})(jQuery);


/**
 * Submit check for MCF widget forms - TBD
 */
/*
(function($) {
	
	jQuery.fn.mcf_widget_check_submit = function(selector) {
		
		var that   = this;
		var backup = $(self).clone();
		
		window.submitCatID = '';
		
		
		jQuery(selector).bind('submit', function() {
			jQuery(selector).find('select[name=cat]').each(function() {
				var thisEmpty = '';
				
				if(jQuery(selector).val() ) {
					var thisEmpty = 'not';
					
					window.submitCatID = jQuery(this).val()
				}
				
				//console.log(jQuery(selector).attr('id') + ': ' + jQuery(this).val() + ', value is ' + thisEmpty + ' empty, current_cat_id = ' + window.submitCatID  )
				
			})
			
			if(window.submitCatID != '') {
				jQuery(that).find('input[name=cat_id]').val( window.submitCatID )

				if(window.console) {
					console.log('cat_id=' + window.submitCatID + '&action=directed');
				}
				//window.location.href = '<?php echo $_SERVER['PHP_SELF']; ?>?cat_id=' + window.submitCatID + '&action=directed'

			}
			
		});
		//console.log( window.submitCatID + ' ?= ' + jQuery(this).find('input[name=cat_id]').val() );

		
		return false
		
	}
})(jQuery);
*/

