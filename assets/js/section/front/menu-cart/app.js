import Vue from "vue";
import App from "./App";
import store from "./store";
import cartSync from "../cart-sync";

cartSync.subscribe((cart) => store.commit("cart/setCart", cart));

if (document.getElementById("appFrontMenuCart")) {
  const vueMenuCartInstance = new Vue({
    el: "#appFrontMenuCart",
    store,
    render: (h) => h(App),
  });

  window.vueMenuCartInstance = {};
  window.vueMenuCartInstance.addCartProduct = (productData) =>
    vueMenuCartInstance.$store.dispatch("cart/addCartProduct", productData);
}
