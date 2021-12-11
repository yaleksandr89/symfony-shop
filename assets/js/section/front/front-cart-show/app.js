import Vue from "vue";
import App from "./App";
import store from "./store";

if (document.getElementById("app")) {
  new Vue({
    el: "#app",
    store,
    render: (h) => h(App),
  });
}
