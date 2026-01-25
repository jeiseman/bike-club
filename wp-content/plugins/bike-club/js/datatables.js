
jQuery(document).ready( function($) {
  // Disable search and ordering by default
  $('.tour_table').DataTable( {
       responsive: true,
       buttons: [ 'excel', 'csv' ],
       "lengthMenu": [ 50, 75, 100 ],
       "pageLength": 50,
       dom: 'Bfrtip',
       searching: false,
       "oLanguage":{
	       "sEmptyTable": "No tours found meeting your search criteria."
       },
   } );
  $('.club-members-table').DataTable( {
       responsive: false,
	   scrollX: true,
       buttons: [ 'excel', 'csv' ],
       dom: 'Bfrtip',
       searching: true,
       "oLanguage":{
	       "sEmptyTable": "No members found."
       },
   } );
  $('#ride-coordinator-ride-list, #search-edit-rides').DataTable( {
       responsive: true,
       buttons: [
       {
           extend: 'excel',
		   exportOptions: {
		       columns: [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 14, 15, 16, 17, 18, 19 ]
		   }
       },
       {
           extend: 'pdf',
		   exportOptions: {
		       columns: [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 14, 15, 16, 17, 18, 19 ]
		   }
       },
       {
           extend: 'csv',
		   exportOptions: {
		       columns: [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 14, 15, 16, 17, 18, 19 ]
		   }
       }
       ],
       dom: 'Bfrtip',
       searching: true,
       paging: true,
       "lengthMenu": [ 50, 75, 100 ],
       "pageLength": 50,
       ordering:  false,
       "language": { "emptyTable" : " " },
       info: false
   } );
  $('.proposed-ride-signup-table').DataTable( {
       responsive: true,
       buttons: [ 'excel', 'csv' ],
       dom: 'Bfrtip',
       searching: false,
       paging: false,
       ordering:  true,
	   order: [[ 3, "asc" ]],
       "language": { "emptyTable" : " " },
       info: false
   } );
  $('#my-lead-rides').DataTable( {
       responsive: true,
       buttons: [ 'excel', 'pdf', 'csv' ],
       dom: 'Bfrtip',
       searching: false,
       paging: false,
       ordering:  true,
	   order: [[ 1, "desc" ]],
       "language": { "emptyTable" : " " },
       info: false
   } );
  $('#my-signups').DataTable( {
       responsive: true,
       buttons: [ 'excel', 'pdf', 'csv' ],
       dom: 'Bfrtip',
       searching: false,
       paging: false,
       ordering:  false,
	   order: [[ 1, "desc" ]],
       "language": { "emptyTable" : " " },
       info: false
   } );
  var ridetable = $('#7-day-ride-schedule-2, #7-day-schedule, #ride-list, #my-scheduled-rides, #ride-schedule').DataTable( {
       responsive: true,
       autowidth: false,
       searching: true,
       paging: true,
       "lengthMenu": [ 50, 75, 100 ],
	   "pageLength": 50,
       stateSave: true,
       ordering:  false,
       "language": { "emptyTable" : " " },
       dom: 'ftlp',
       info: false
   } );
  $('.start_table').DataTable( {
       responsive: true,
       paging: false,
   } );
  $('.road-hazards-table').DataTable( {
       responsive: true,
       searching: false,
       paging: false,
   } );
  $('.food-stops-table').DataTable( {
       responsive: true,
       searching: true,
       paging: false,
   } );
  $('.blocked-dates-table').DataTable( {
       responsive: true,
       searching: false,
       oaging: false,
   } );
  $('.leader_table').DataTable( {
       buttons: [ 'excel', 'pdf', 'csv' ],
       responsive: true,
       paging: false,
       order:[[1, 'desc']],
       dom: 'Bfrtip',
       info: false
   } );
  $('.member_ride_table').DataTable( {
       buttons: [ 'excel', 'pdf', 'csv' ],
       responsive: true,
       paging: false,
       order:[[2, 'desc']],
       dom: 'Bfrtip',
       info: false
   } );
  $('.contacts_table').DataTable( {
       responsive: true,
       searching: false,
       "bInfo" : false,
       "ordering": false,
       "paging": false,
       "sScroolX": "100%"
   } );
   $(window).bind('resize', function() {
       ridetable.columns.adjust().draw();
   });
 } );
