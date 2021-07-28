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
