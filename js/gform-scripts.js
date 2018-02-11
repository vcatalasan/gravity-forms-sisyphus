(function($) {
    var form_id = gform_vars['form_id'];

    var form = $('#gform_'+form_id).sisyphus({
        onBeforeRestore: function(){return false}
    });

    var hasFormState = Boolean(gform_vars['form_state']);
    var resumeToken = gform_vars['resume_token'];
    var savedToken =  form.browserStorage.get('resume_token');

    // Allow restore from local storage only when resume token matches and has no form state (i.e initial state)
    var restoreAllData = resumeToken === savedToken && hasFormState === false;

    $(document).ready(function() {
        $('#gform_'+form_id+' input, #gform_'+form_id+' select, #gform_'+form_id+' textarea').change(function(e){
            form.browserStorage.set('resume_token', resumeToken);
        });

        gform.addFilter( 'gform_list_item_pre_add', function ( clone ) {
            // do stuff
            console.info('gform_list_item_pre_add', clone);
            return clone;
        });

        gform.addAction( 'gform_list_post_item_add', function ( item, container ) {
            item.find( 'input[type="text"]' ).on( 'change', function() {
                console.info('gform_list_post_item_add', 'Changed!' );
            } );
        } );
    });

    if (restoreAllData) {
        form.restoreAllData();
    } else {
        // sync new form data to local storage
        form.manuallyReleaseData();
        form.saveAllData();
        form.browserStorage.set('resume_token', resumeToken);
    }
})(jQuery);