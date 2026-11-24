import { createApp } from "vue";
import App from "./App.vue";
import store from "./store/index.js";

if (document.getElementById("app")) {
  createApp(App).use(store).mount("#app");
}
