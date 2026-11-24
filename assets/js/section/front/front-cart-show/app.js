import { createApp } from "vue";
import App from "./App.vue";
import store from "./store/index.js";
import cartSync from "../cart-sync.js";

cartSync.subscribe((cart) => store.commit("cart/setCart", cart));

if (document.getElementById("app")) {
  createApp(App).use(store).mount("#app");
}
