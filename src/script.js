function openTab(evt, tabSelector, contentSelector, TabName) {
  var i;
  // Hide all the contents
  var x = document.querySelectorAll(contentSelector);
  for (i = 0; i < x.length; i++) {
    x[i].style.display = "none";
  }
  // Show the one content
  document.getElementById(TabName).style.display = "block";
  // grey all the tabs
  tablinks = document.querySelectorAll(tabSelector);
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" front", "");
  }
  //Bolden the one tab
  evt.currentTarget.className += " front";
}

var coll = document.getElementsByClassName("collapsible");
var i;
for (i = 0; i < coll.length; i++) {
  coll[i].addEventListener("click", function() {
    this.classList.toggle("active");
    var content = this.nextElementSibling;
    if (content.style.display === "block") {
      content.style.display = "none";
    } else {
      content.style.display = "block";
    }
  });
}

$( function() {
  // auto-complete using jquery
  $( ".accountnames" ).autocomplete({
    source: function( request, response ) {
      $.ajax( {
        url: "http://ccleaf/account/names/"+request.term,
        dataType: "json",
        contentType: "application/json;  charset=utf-8",
        headers: {
          'cc-user': ccuser,
          'cc-auth': ccauth
        },
       //data: {fragment: request.term},
        success: function( data ) {
          response( data );
        }
      } );
    },
    minLength: 1,
  } );
} );
