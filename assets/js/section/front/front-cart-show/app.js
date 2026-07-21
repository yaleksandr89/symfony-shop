import Vue from "vue";
import App from "./App";
import store from "./store";
import cartSync from "../cart-sync";

cartSync.subscribe((cart) => store.commit("cart/setCart", cart));

if (document.getElementById("app")) {
  new Vue({
    el: "#app",
    store,
    render: (h) => h(App),
  });
}
