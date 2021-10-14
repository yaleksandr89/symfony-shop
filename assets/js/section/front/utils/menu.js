document.getElementById("mobile_menu_toggler").addEventListener("click", () => {
  document.getElementById("mobile_menu_container").classList.add("show");
});
document
  .getElementById("mobile_menu_close_btn")
  .addEventListener("click", () => {
    document.getElementById("mobile_menu_container").classList.remove("show");
  });
