$(document).ready(function() {

  
 var bhconfig = new Bloodhound({
            datumTokenizer: Bloodhound.tokenizers.obj.whitespace('service_name'),
            queryTokenizer: Bloodhound.tokenizers.whitespace,
            prefetch: {
        url:'results'+ +'.json',
        cache: true // defaults to true (so you can omit writing this)
    }
// remote: {
//        url: 'core/gettaservices.php?q=%QUERY',
//        wildcard: '%QUERY'                    // %QUERY will be replace by users input in
//    },


        });

        bhconfig.initialize();

var $myTypeahead = $("#services");


        $myTypeahead.typeahead(
            {
  hint: true,
  highlight: true,
  minLength: 1
}, {
            //name: 'stocks',
            display: 'service_name',
            source: bhconfig,
templates: {
    notFound: '<div style="padding-left: 10px;">Not Found</div>',   /* Rendered if 0 suggestions are available */
    pending: '<div style="padding-left: 10px;">Loading... <img style="height:45px;width:45px;" src="assets/images/spinner.gif"/></div>',   /* Rendered if 0 synchronous suggestions available 
                                           but asynchronous suggestions are expected */
    //header: '<div>Found Records:</div>',/* Rendered at the top of the dataset when suggestions are present */
    suggestion:  function(data) {       /* Used to render a single suggestion */
                    return '<div data_id="'+ data.service_id + '">' +data.service_name +'</div>'
						
                 },
    //footer: '<div>Footer Content</div>',/* Rendered at the bottom of the dataset when suggestions are present. */
}
      ,  });

  


// When the typeahead becomes active reset these values.
$myTypeahead.on("typeahead:active", function(aEvent) {
   selected       = null;
	selected_service_id  =null;
	selected_service_price =null;
	selected_service_time_taken=null;
   originalVal    = $myTypeahead.typeahead("val");

//alert( "typeahead:active");

})

// When a suggestion gets selected save that
$myTypeahead.on("typeahead:select", function(aEvent, aSuggestion) {
   selected = aSuggestion;
selected_service_id=selected.service_id;
selected_service_price =selected.price;
	selected_service_time_taken=selected.time_taken;


});

// Once user leaves the component and a change was registered
// check whether or not something was selected. If not reset to
// the original value.
$myTypeahead.on("typeahead:change", function(aEvent, aSuggestion) {
   if (!selected) {
      $myTypeahead.typeahead("val", '');
originalVal=null;
//alert( "typeahead:change");
   }

   // Do something with the selected value here as needed...
});   
















});
