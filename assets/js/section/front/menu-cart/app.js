import Vue from "vue";
import App from "./App";
import store from "./store";

if (document.getElementById("appFrontMenuCart")) {
  const vueMenuCartInstance = new Vue({
    el: "#appFrontMenuCart",
    store,
    render: (h) => h(App),
  });

  window.vueMenuCartInstance = {};
  window.vueMenuCartInstance.addCartProduct = (productData) =>
    vueMenuCartInstance.$store.dispatch("cart/addCartProduct", productData);
  window.vueMenuCartInstance.setCart = () =>
    vueMenuCartInstance.$store.commit("cart/setCart", {});
}
