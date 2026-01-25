//run code only after the whole page is loaded
jQuery(document).ready( function($){ 

 // $(document).off('click', '.em-grid .em-item[data-href]');
  // $('#input_7_12').prop('disabled', 'disabled');
  $('#input_32_41').prop('disabled', 'disabled');
  document.addEventListener('gform/postRender', (event) => {
    const form = gform.utils.getNode(`#gform_${event.detail.formId}`, document, true);
    const inputs = Array.from(gform.utils.getNodes('.gf_readonly input', false, form, true));
 
    inputs.forEach(input => {
        input.readOnly = true;
    });
  });

  $(window).on('load', function() {
    $('.em-search-submit').click();
  });
 
 // if adminbar exist (should check for visible?) then add margin to our navbar
 // var $wpAdminBar = $('#wpadminbar');
 // if ($wpAdminBar.length) {
     // $('div.topbar').css('margin-top',$wpAdminBar.height()+'px');
 // }

 if (top.location.pathname.includes("/ride/")) {
    //when the button is clicked run the ajax function
    $("#mystatus" ).change(runMyStatus);

    if ($(".ride_attend")[0]) {
       $(window).on("beforeunload", runMyStatus);
    }

    $('.ride-update-button').click(runMyStatus);

	$("#becomeleader").click(runBecomeRideLeader);
 }

var requestSent = false;

 function runMyStatus() {
  var mystatus = $('#mystatus').val();
  var rideID = $('#rideID').val();
  var car_license = $('#car_lic').val();
  var emergency_phone = $('#emrg_fone').val();
  var cell_phone = $('#cell_fone').val();
  var data = {
        'action': 'bikeride_mystatus_function', //the function in php functions to call
        'mystatus': mystatus,
        'rideID' : rideID,
        'car_license' : car_license,
        'emergency_phone' : emergency_phone,
        'cell_phone' : cell_phone,
        'nonce': frontEndAjax.nonce
    };

    var na = $('.notification-area');

    if (!/\d/.test(data.emergency_phone)) {
        na.val('You must fill out an emergency number in order to sign up for a ride (and it must be a telephone number)');
        $('#mystatus').val('No');
    }
    else  {
        na.val('Form values updated!');
    }
    // na.show();
    setTimeout(function(){
        na.val('');
        // na.hide();
    }, 10000);

 //send data to the php file admin-ajax which was stored in the variable ajaxurl
   if (!requestSent) {
	 requestSent = true;
     $.post(frontEndAjax.ajaxurl, data, function(response) {
         if (response.data_from_backend != $('#rideAttendees').html()) {
             $('#rideAttendees').html(response.data_from_backend);
         }
         if (response.waitlisted != $('#waitList').html()) {
             $('#waitList').html(response.waitlisted);
         }
		 if (response.remaining != $('#signups-remaining').html()) {
		     $('#signups-remaining').html(response.remaining);
		 }
		 requestSent = false;
      }, 'json' );
   }
 } // end runMyStatus

function runBecomeRideLeader() {
	var link = window.location.href;
	var data = {
		'action': 'bk_become_ride_leader',
		'link': link,
       	'nonce': frontEndAjax.nonce
	};
	$("#becomeleader").attr("disabled", true);
	$.post(frontEndAjax.ajaxurl, data, function(response) {
		$(".result_area").html(response);
	    $("#becomeleader").attr("disabled", false);
		window.location.reload(true);
	});
}

// if (typeof($.datepicker) != "undefined") {
    // jQuery.datepicker._checkExternalClick = function() {};
    // $(document).unbind('mousedown', $.datepicker._checkExternalClick);
// }
//
if ( typeof gform !== 'undefined' ) {
  gform.addFilter( 'gform_datepicker_options_pre_init', function( optionsObj, formId, fieldId ) {
    if ( formId == 7 && fieldId == 5 ) {
		optionsObj.minDate = 0;
		optionsObj.firstDay = 0;
		optionsObj.dateFormat = 'mm/dd/yy';
		optionsObj.defaultDate = 0;
		optionsObj.gotoCurrent = true;
    }
	// else if ( fieldId == 1) {
        // let formIdArr = [ 11, 14, 15, 18 ];
		// if ( formIdArr.includes(formId) ) {
		// if ( formId == 11 || formId == 14 || formId == 15 || formId == 18 ) {
	    	// targetStr = '#input_' + formId + '_2';
       		// optionsObj.onClose = function (dateText, inst) {
       			// jQuery( targetStr ).datepicker('option', 'minDate', dateText).datepicker('setDate', dateText);
       		// };
		// }
	// }
    return optionsObj;
  });
}


    $('#reset').click(function() {
        $(':input','#gform_6')
            .chosen("destroy")
            .not(':button, :submit, :reset, :hidden, :radio')
            .val('')
            .removeAttr('checked')
            .removeAttr('selected');
        $('.result-selected').removeClass('result-selected');
        $('.gf_placeholder').addClass('result-selected');
    });
    $('#ride-list img').removeAttr("srcset");
    // $('.elementor-social-icon-facebook-f').prop("title", "Facebook");
    // $('.elementor-social-icon-twitter').prop("title", "Twitter");
    // $('.elementor-social-icon-instagram').prop("title", "Instagram");
    // $('.elementor-social-icon-strava').prop("title", "Strava");
    // $('.elementor-repeater-item-f98c51b').prop("title", "Ride with GPS");
    $('#strava-id').attr("title", "Current Rides on Strava");
    $('.field_first-and-last-name a').contents().unwrap();
    $('li#wp-admin-bar-my-account-xprofile-public').remove();
    $('li#wp-admin-bar-my-account-xprofile-edit').remove();
    var getUrlParameter = function getUrlParameter(sParam) {
        var sPageURL = window.location.search.substring(1),
            sURLVariables = sPageURL.split('&'),
            sParameterName,
            i;

        for (i = 0; i < sURLVariables.length; i++) {
            sParameterName = sURLVariables[i].split('=');

            if (sParameterName[0] === sParam) {
                return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
            }
        }
    };

    // if (getUrlParameter('riderole') != 2) {
        // $('#input_7_21 option:not(:selected)').attr('disabled', true);
    // }
    // function PopupBlocked() {
        // var PUtest = window.open(null,"","width=100,height=100");
        // try { PUtest.close(); return false; }
        // catch(e) { return true; }
    // }
    // if (PopupBlocked()) {
        // $('.elementor-element-183f789b a.elementor-button-link').attr('href', '/wp-login.php');
    // }
    // $('select#bcountry').attr('disabled', 'disabled');
    // $('.notification-area').hide();
	if ($(window).width() >= 1100) {
        var descriptionHeight = $('.ride_description').height();
        var attendHeight = $('.ride_attend').height();
        if (descriptionHeight > attendHeight) {
            $('.ride_attend').height(descriptionHeight);
        }
        else {
            $('.ride_description').height(attendHeight);
        }
	}
});
    // $(window).on('orientationchange' function(e) { window.location.reload(); };
    // $(window).on('resize' function(e) { window.location.reload(); };
