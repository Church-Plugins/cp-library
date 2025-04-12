/**
 * JavaScript to handle visibility UI interactions in the admin
 */
jQuery(document).ready(function($) {
    // Only run on sermon edit page
    if (typeof cplVisibility === 'undefined') {
        return;
    }
    
    var $seriesField = $('#cpl_item_type'),
        $serviceTypeField = $('#cpl_service_type'),
        $visibilityField = $('#show_in_main_list'),
        $inheritedField = $('#cpl_visibility_inherited'),
        $noticeArea = $('#cpl-visibility-notice');
    
    function checkVisibilityInheritance() {
        // Make an AJAX request to check the visibility inheritance
        var postId = $('#post_ID').val();
        
        if (!postId) {
            return;
        }
        
        // For new posts, check based on selected values
        if (postId === '0' || $('#original_post_status').val() === 'auto-draft') {
            handleSeriesAndServiceTypeChange();
            return;
        }
        
        $.ajax({
            url: cplVisibility.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cpl_check_visibility_inheritance',
                post_id: postId,
                nonce: cplVisibility.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateVisibilityUI(response.data.series, response.data.service_type);
                }
            }
        });
    }
    
    function handleSeriesAndServiceTypeChange() {
        // This function would ideally check the selected series and service types
        // to see if any are marked as excluded, but we don't have that information
        // without making additional AJAX requests
        
        // For simplicity, we're just disabling real-time checking on selection change
        // The visibility will be properly set when the post is saved
    }
    
    function updateVisibilityUI(seriesHidden, serviceTypeHidden) {
        if (seriesHidden || serviceTypeHidden) {
            // Something is forcing this to be hidden
            $inheritedField.val('true');
            $visibilityField.prop('disabled', true);
            $visibilityField.prop('checked', false);
            
            // Show appropriate notice
            var noticeText = '';
            if (seriesHidden && serviceTypeHidden) {
                noticeText = cplVisibility.strings.inherited_both;
            } else if (seriesHidden) {
                noticeText = cplVisibility.strings.inherited_series;
            } else if (serviceTypeHidden) {
                noticeText = cplVisibility.strings.inherited_service_type;
            }
            
            $noticeArea.html('<p class="cmb2-metabox-description" style="color:#d63638;"><strong>' + noticeText + '</strong></p>');
            $noticeArea.show();
        } else {
            // Nothing is forcing this to be hidden
            $inheritedField.val('false');
            $visibilityField.prop('disabled', false);
            $noticeArea.hide();
        }
    }
    
    // Check initial state
    checkVisibilityInheritance();
    
    // Listen for changes to series and service type
    $seriesField.on('change', handleSeriesAndServiceTypeChange);
    $serviceTypeField.on('change', handleSeriesAndServiceTypeChange);
});