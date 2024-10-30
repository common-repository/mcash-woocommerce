// Wait until DOM is loaded
jQuery( document ).ready(function(){
	// Verify that we are in the settings screen of the mcash gateway
	if( jQuery('#woocommerce_mcash2_autocapture').length > 0 ){
		
		// Check if the autocapture feature is checked
		var autocapture = ( jQuery('#woocommerce_mcash2_autocapture').is(':checked') );

		// Enable and disable the other capture features based on the status of autocapture
		function toggleCapture( autocapture ){
			if( autocapture ){
				jQuery('#woocommerce_mcash2_autocapture_virtual').prop('disabled', true);
				jQuery('#woocommerce_mcash2_capture_on').prop('disabled', true);
			} else {
				jQuery('#woocommerce_mcash2_autocapture_virtual').prop('disabled', false);
				jQuery('#woocommerce_mcash2_capture_on').prop('disabled', false);
			}
		}
		
		// Track the changes to autocapture
		jQuery('#woocommerce_mcash2_autocapture').on('change', function(e){
			toggleCapture( jQuery(this).is(':checked') );
		});
		
		// The initial toggle
		toggleCapture( autocapture );
		
	}	
});
